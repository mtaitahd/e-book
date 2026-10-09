<?php

namespace Tests\Feature\Purchase;

use App\Models\Order;
use App\Models\Purchase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PurchaseCreationTest extends PurchaseTestCase
{
    public function test_paid_order_creates_one_purchase_per_item(): void
    {
        $user = $this->customer();
        $bookA = $this->publishedBook(['title' => 'First Book']);
        $bookB = $this->publishedBook(['title' => 'Second Book']);

        $order = $this->paidOrder($user, [
            ['book' => $bookA, 'unit_price' => '1000.00'],
            ['book' => $bookB, 'unit_price' => '2500.00'],
        ]);

        $this->assertSame(2, Purchase::count());
        $this->assertDatabaseHas('purchases', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'book_id' => $bookA->id,
        ]);
        $this->assertDatabaseHas('purchases', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'book_id' => $bookB->id,
        ]);
    }

    public function test_pending_order_creates_no_purchases(): void
    {
        $user = $this->customer();
        $book = $this->publishedBook();

        $order = $this->makeOrder($user);
        $this->addItem($order, $book);

        $this->assertSame(0, Purchase::count());
        $this->assertTrue($order->fresh()->isPending());
    }

    public function test_purchase_amount_and_currency_are_snapshotted_from_order(): void
    {
        $user = $this->customer();
        $book = $this->publishedBook(['title' => 'Snapshot Book']);

        $order = $this->makeOrder($user, '3000.00', 'TZS');
        $this->addItem($order, $book, '3000.00');

        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        $purchase = Purchase::where('order_item_id', $order->items->first()->id)->firstOrFail();
        $this->assertSame('3000.00', $purchase->amount);
        $this->assertSame('TZS', $purchase->currency);
        $this->assertSame($user->id, $purchase->user_id);
        $this->assertNotNull($purchase->purchased_at);
    }

    public function test_later_book_price_change_does_not_alter_purchase_amount(): void
    {
        $user = $this->customer();
        $book = $this->publishedBook(['title' => 'Priced Book']);

        $order = $this->makeOrder($user);
        $this->addItem($order, $book, '1500.00');

        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        $book->update(['price' => '9999.00']);

        $purchase = $order->purchases()->firstOrFail();
        $this->assertSame('1500.00', $purchase->amount);
    }

    public function test_service_is_idempotent_under_repeated_calls(): void
    {
        $user = $this->customer();
        $book = $this->publishedBook();

        $order = $this->paidOrder($user, [['book' => $book]]);

        $this->service()->createFromPaidOrder($order);
        $this->service()->createFromPaidOrder($order->fresh());

        $this->assertSame(1, Purchase::count());
    }

    public function test_service_creates_nothing_for_orders_without_items(): void
    {
        $user = $this->customer();
        $order = $this->paidOrder($user, []);

        $this->assertSame(0, Purchase::count());
        $this->assertTrue($order->isPaid());
    }

    public function test_completed_webhook_creates_purchases_end_to_end(): void
    {
        Http::fake();

        $user = $this->customer();
        $book = $this->publishedBook();
        $order = $this->makeOrder($user, '2000.00');
        $this->addItem($order, $book, '2000.00');
        $payment = $this->pendingPayment($order, 'SNIP-PUR-WEBHOOK');

        [$payload, $ts, $sig] = $this->signedWebhook($this->completedWebhookPayload($payment->provider_reference, amount: 2000));

        $this->postWebhook($payload, $ts, $sig)->assertOk();

        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(1, Purchase::count());
        $this->assertDatabaseHas('purchases', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_payment_refresh_creates_purchases_when_order_confirmed_paid(): void
    {
        $user = $this->customer();
        $book = $this->publishedBook();
        $order = $this->makeOrder($user, '1800.00');
        $this->addItem($order, $book, '1800.00');
        $payment = $this->pendingPayment($order, 'SNIP-PUR-REFRESH');

        Http::fake([
            'https://api.snippe.test/v1/payments/SNIP-PUR-REFRESH' => Http::response([
                'data' => ['reference' => 'SNIP-PUR-REFRESH', 'status' => 'completed'],
            ], 200),
        ]);

        $this->actingAs($user)
            ->post(route('account.orders.payments.refresh', $order))
            ->assertRedirect();

        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(1, Purchase::count());
    }

    public function test_purchase_is_recorded_with_order_id_and_type_safe_amount(): void
    {
        $user = $this->customer();
        $book = $this->publishedBook();

        $order = $this->paidOrder($user, [['book' => $book, 'unit_price' => '750.50']]);

        $purchase = $order->purchases()->firstOrFail();
        $this->assertSame('750.50', $purchase->amount);
        $this->assertIsString($purchase->currency);
        $this->assertSame((int) $order->id, (int) $purchase->order_id);
    }
}