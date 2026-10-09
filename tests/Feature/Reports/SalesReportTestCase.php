<?php

namespace Tests\Feature\Reports;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class SalesReportTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('shop.currency', 'TZS');
        config()->set('app.timezone', 'UTC');
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function customer(string $name = 'Hassan Ali'): User
    {
        return User::factory()->customer()->create(['name' => $name]);
    }

    protected function book(string $title = 'Test Book', string $price = '1000.00'): Book
    {
        return Book::factory()->published()->priced($price)->create([
            'title' => $title,
            'file_path' => null,
        ]);
    }

    protected function category(string $name = 'Fiction'): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'status' => 'active',
        ]);
    }

    /**
     * A paid order with a precise paid_at, so date-window tests are exact.
     */
    protected function paidOrder(
        User $user,
        string $total = '1000.00',
        string $currency = 'TZS',
        ?string $paidAt = null,
        array $items = [],
    ): Order {
        $order = $this->order($user, $total, $currency, $paidAt);

        foreach ($items as $item) {
            $this->item($order, $item['book'], $item['unit_price'] ?? $total, $item['qty'] ?? 1);
        }

        $order->update([
            'status' => Order::STATUS_PAID,
            'paid_at' => $paidAt ?? now(),
        ]);

        return $order->fresh();
    }

    protected function order(
        User $user,
        string $total = '1000.00',
        string $currency = 'TZS',
        ?string $paidAt = null,
        string $status = Order::STATUS_PENDING,
    ): Order {
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'EBS-'.Str::upper(Str::random(10)),
            'subtotal' => $total,
            'total' => $total,
            'currency' => $currency,
            'status' => $status,
        ]);

        if ($paidAt !== null) {
            $order->forceFill([
                'created_at' => $paidAt,
                'updated_at' => $paidAt,
            ])->save();
        }

        return $order->fresh();
    }

    protected function item(Order $order, Book $book, string $unitPrice = '1000.00', int $qty = 1): OrderItem
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
     * A completed payment row, which is what the channel report reads.
     */
    protected function completedPayment(
        Order $order,
        ?string $channel = 'mpesa',
        int $amount = 1500,
        ?string $paidAt = null,
    ): Payment {
        return Payment::create([
            'order_id' => $order->id,
            'provider' => Payment::PROVIDER_SNIPPE,
            'payment_type' => Payment::TYPE_MOBILE,
            'provider_reference' => 'SNIP-'.Str::upper(Str::random(8)),
            'idempotency_key' => 'ebs-'.Str::random(10),
            'amount' => $amount,
            'currency' => $order->currency,
            'status' => Payment::STATUS_COMPLETED,
            'channel_provider' => $channel,
            'paid_at' => $paidAt ?? now(),
        ]);
    }

    protected function customUrl(string $from, string $to, string $range = 'custom'): string
    {
        return route('admin.reports.sales', ['range' => $range, 'from' => $from, 'to' => $to]);
    }
}
