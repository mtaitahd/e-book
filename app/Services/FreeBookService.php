<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hands a free book straight to a customer, with no cart and no payment.
 *
 * A free book still has to produce a real `purchases` row, because that row is
 * what every reader, download and progress endpoint authorises against — there
 * is no second way in. So a claim records a genuine order for the book at
 * 0.00 and marks it paid in the same transaction: the order is the receipt, the
 * order item is the line, and the purchase is the entitlement. Nothing is
 * fabricated — the book really was free, and the money really was zero.
 *
 * Claims are idempotent. Claiming a book the customer already owns returns the
 * existing entitlement instead of creating a second one, and the unique
 * constraints on `purchases` settle a race between two simultaneous clicks.
 */
class FreeBookService
{
    public function __construct(
        private readonly PurchaseService $purchases,
    ) {}

    /**
     * Give a free book to a customer, or return the entitlement they already have.
     */
    public function claim(Book $book, User $user): Purchase
    {
        if (! $book->isFree()) {
            throw new \InvalidArgumentException('This book is not free.');
        }

        if (! $book->isPublished()) {
            throw new \InvalidArgumentException('This book is not available.');
        }

        $existing = $this->existingPurchase($book, $user);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($book, $user) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'order_number' => Order::generateNumber(),
                    'subtotal' => '0.00',
                    'total' => '0.00',
                    'currency' => config('shop.currency'),
                    'status' => Order::STATUS_PAID,
                    'paid_at' => now(),
                ]);

                $item = $order->items()->create([
                    'book_id' => $book->id,
                    'quantity' => 1,
                    'unit_price' => '0.00',
                    'subtotal' => '0.00',
                ]);

                // The Order model already does this on any transition to paid.
                // Calling it again is free and keeps this method correct even if
                // that hook is ever changed.
                $this->purchases->createFromPaidOrder($order);

                return Purchase::where('order_item_id', $item->id)->firstOrFail();
            });
        } catch (QueryException $exception) {
            // Two clicks arrived together and the loser hit a unique constraint.
            // The winner's row is the answer, so hand it back rather than 500.
            $purchase = $this->existingPurchase($book, $user);

            if ($purchase === null) {
                throw $exception;
            }

            Log::info('free_books.claim_race_resolved', [
                'book_id' => $book->id,
                'user_id' => $user->id,
            ]);

            return $purchase;
        }
    }

    /**
     * The entitlement this customer already holds for a free book, if any.
     *
     * Scoped to paid orders so a cancelled or unpaid order can never be read as
     * ownership, exactly as the storefront and library pages do.
     */
    public function existingPurchase(Book $book, User $user): ?Purchase
    {
        return Purchase::query()
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->whereHas('order', fn ($order) => $order->where('status', Order::STATUS_PAID))
            ->first();
    }
}
