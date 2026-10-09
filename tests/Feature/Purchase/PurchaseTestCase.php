<?php

namespace Tests\Feature\Purchase;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Payment\PaymentTestCase;

abstract class PurchaseTestCase extends PaymentTestCase
{
    use RefreshDatabase;

    protected function publishedBook(array $attributes = []): Book
    {
        return Book::factory()->published()->create(array_merge([
            'file_path' => null,
            'file_type' => 'pdf',
        ], $attributes));
    }

    protected function addItem(Order $order, Book $book, string $unitPrice = '1000.00', int $qty = 1): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'subtotal' => number_format($qty * (float) $unitPrice, 2, '.', ''),
        ]);
    }

    /**
     * An order that started pending and then legitimately became PAID — the
     * same transition the confirmed Snippe webhook performs.
     */
    protected function paidOrder(User $user, array $items = []): Order
    {
        $order = $this->makeOrder($user, is_countable($items) && count($items) ? array_sum(array_column($items, 'unit_price')) : '1000.00');

        foreach ($items as $item) {
            $this->addItem($order, $item['book'], $item['unit_price'] ?? '1000.00', $item['qty'] ?? 1);
        }

        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        return $order->fresh();
    }

    protected function pendingPayment(Order $order, string $reference = 'SNIP-PUR-001'): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'provider' => Payment::PROVIDER_SNIPPE,
            'payment_type' => Payment::TYPE_MOBILE,
            'provider_reference' => $reference,
            'idempotency_key' => 'ebs-pay-'.Str::random(8),
            'amount' => (int) $order->total,
            'currency' => $order->currency,
            'status' => Payment::STATUS_PENDING,
            'channel_provider' => 'mpesa',
        ]);
    }

    protected function service(): PurchaseService
    {
        return app(PurchaseService::class);
    }
}