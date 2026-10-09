<?php

namespace App\Services;

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Purchase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates purchase entitlements from a confirmed PAID order.
 *
 * A purchase records that a customer owns a specific book as the result of a
 * paid order item. The amount/currency are snapshotted from the order item
 * and order, never recomputed from the book's current price.
 *
 * Creation is idempotent: re-running it (duplicate webhook, racing request,
 * page refresh, retried job) yields the same single entitlement per paid
 * order item. Database unique constraints on order_item_id and
 * user+order+book are the final protection against duplicates.
 */
class PurchaseService
{
    /**
     * Create one purchase per order item for a paid order.
     *
     * Returns the number of newly created purchases (existing entitlements
     * are left untouched).
     */
    public function createFromPaidOrder(Order $order): int
    {
        if (! $order->isPaid()) {
            return 0;
        }

        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            return 0;
        }

        $created = 0;

        DB::transaction(function () use ($order, &$created) {
            foreach ($order->items as $item) {
                if ($item->book_id === null) {
                    continue;
                }

                $purchase = $this->firstOrCreatePurchase($order, $item);

                if ($purchase !== null && $purchase->wasRecentlyCreated) {
                    $created++;
                }
            }
        });

        if ($created > 0) {
            Log::info('purchases.created', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'count' => $created,
            ]);
        }

        // Customer notification hook. This is the single existing funnel that
        // grants entitlements, so it is also the only correct place to raise
        // "this order was paid". The event carries no rendered content and the
        // listener defers delivery until the transaction commits, so the
        // purchase flow above is entirely unchanged by the addition. A free
        // order also reaches here but has no completed payment, so its receipt
        // is skipped rather than sent.
        OrderPaid::dispatch($order);

        return $created;
    }

    /**
     * Insert one entitlement for the item, tolerating a concurrent request
     * that won the race (the unique constraint simply yields the existing row).
     */
    private function firstOrCreatePurchase(Order $order, OrderItem $item): ?Purchase
    {
        $attributes = [
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'book_id' => $item->book_id,
            'amount' => $item->unit_price,
            'currency' => $order->currency,
            'purchased_at' => $order->paid_at ?? now(),
        ];

        try {
            return Purchase::firstOrCreate(['order_item_id' => $item->id], $attributes);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            return Purchase::where('order_item_id', $item->id)->first();
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        foreach (['UNIQUE constraint failed', 'Duplicate entry', 'UNIQUE', 'Integrity constraint violation'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        foreach ((array) $exception->errorInfo as $info) {
            if (is_string($info) && in_array($info, ['23000', '19', '1062'], true)) {
                return true;
            }
        }

        return false;
    }
}