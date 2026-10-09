<?php

namespace Tests\Feature\EmailNotifications;

use App\Mail\PaymentUnsuccessfulMail;
use App\Mail\PurchaseReceiptMail;
use App\Models\Book;
use App\Models\EmailNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Proves the email trigger is tied to the real confirmed paid-order flow and
 * is released only by a commit.
 *
 * These are the only tests that open real transactions, because the guarantee
 * under test is precisely about transaction boundaries. The suite database is
 * in-memory SQLite, so a transaction opened here is a genuine root
 * transaction whose commit and rollback the framework cannot hide.
 */
class EmailTriggerTest extends EmailTestCase
{
    public function test_a_receipt_is_withheld_until_the_transaction_commits(): void
    {
        $order = $this->paidOrderWithPayment();

        DB::transaction(function () use ($order) {
            app(PurchaseService::class)->createFromPaidOrder($order);

            // Still uncommitted: the customer must not be told yet.
            Mail::assertNothingSent();
            $this->assertSame(0, EmailNotification::query()->count());
        });

        Mail::assertSent(PurchaseReceiptMail::class, 1);
        $this->assertSame(1, EmailNotification::query()->count());
    }

    public function test_a_receipt_is_discarded_when_the_transaction_rolls_back(): void
    {
        $order = $this->paidOrderWithPayment();

        try {
            DB::transaction(function () use ($order) {
                app(PurchaseService::class)->createFromPaidOrder($order);

                throw new RuntimeException('payment could not be recorded');
            });

            $this->fail('The transaction should have rolled back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('payment could not be recorded', $exception->getMessage());
        }

        // No money, no entitlement, and above all no receipt.
        Mail::assertNothingSent();
        $this->assertSame(0, EmailNotification::query()->count());
    }

    public function test_a_confirmed_snippe_webhook_sends_exactly_one_receipt(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer(), '15000.00');
        $this->addItem($order, $this->bookWithAuthor('Habari za Uhuru'), '15000.00');

        $payment = $this->pendingPayment($order, 'SNIP-TRIGGER-001');
        [$payload, $ts, $sig] = $this->signedWebhook($this->completedWebhookPayload($payment->provider_reference, (int) $order->total));

        $this->postWebhook($payload, $ts, $sig)->assertOk();

        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(1, $order->purchases()->count());

        Mail::assertSent(PurchaseReceiptMail::class, 1);

        $log = EmailNotification::query()->sole();
        $this->assertSame(EmailNotification::TYPE_PURCHASE_RECEIPT, $log->type);
        $this->assertTrue($log->isSent());
        $this->assertSame($order->id, $log->order_id);
        $this->assertSame($order->user->email, $log->recipient);
    }

    public function test_a_replayed_webhook_still_sends_only_one_receipt(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer(), '15000.00');
        $this->addItem($order, $this->bookWithAuthor('Habari za Uhuru'), '15000.00');

        $payment = $this->pendingPayment($order, 'SNIP-REPLAY-001');
        [$payload, $ts, $sig] = $this->signedWebhook($this->completedWebhookPayload($payment->provider_reference, (int) $order->total));

        // Snippe delivers the same completion event more than once.
        $this->postWebhook($payload, $ts, $sig)->assertOk();
        $this->postWebhook($payload, $ts, $sig)->assertOk();

        // Identical event id, so the second delivery is deduplicated outright.
        Mail::assertSent(PurchaseReceiptMail::class, 1);
        $this->assertSame(1, EmailNotification::query()->count());
        $this->assertSame(1, $order->purchases()->count());
    }

    public function test_a_webhook_redelivered_under_a_new_event_id_still_sends_one_receipt(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer(), '15000.00');
        $this->addItem($order, $this->bookWithAuthor('Habari za Uhuru'), '15000.00');

        $payment = $this->pendingPayment($order, 'SNIP-REPLAY-002');

        $first = $this->completedWebhookPayload($payment->provider_reference, (int) $order->total);
        [$payload, $ts, $sig] = $this->signedWebhook($first);
        $this->postWebhook($payload, $ts, $sig)->assertOk();

        // Same payment, but the provider assigned a new event id, so the
        // webhook dedup does not catch it and the funnel runs again.
        $second = $this->completedWebhookPayload($payment->provider_reference, (int) $order->total);
        [$payload2, $ts2, $sig2] = $this->signedWebhook($second);
        $this->postWebhook($payload2, $ts2, $sig2)->assertOk();

        $this->assertSame(2, WebhookEvent::query()->count());

        Mail::assertSent(PurchaseReceiptMail::class, 1);
        $this->assertSame(1, EmailNotification::query()->count());
        $this->assertSame(1, $order->purchases()->count());
    }

    public function test_a_failed_webhook_emails_the_customer_without_granting_a_purchase(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer(), '15000.00');
        $this->addItem($order, $this->bookWithAuthor('Habari za Uhuru'), '15000.00');

        $payment = $this->pendingPayment($order, 'SNIP-FAILED-001');
        $payload = $this->completedWebhookPayload($payment->provider_reference, (int) $order->total);
        $payload['event'] = 'payment.failed';
        $payload['data']['status'] = 'failed';
        $payload['data']['failure_reason'] = 'request_failed:raw provider text';

        [$payload, $ts, $sig] = $this->signedWebhook($payload);
        $this->postWebhook($payload, $ts, $sig)->assertOk();

        $this->assertTrue($payment->fresh()->isFailed());
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, $order->purchases()->count());

        Mail::assertSent(PaymentUnsuccessfulMail::class, 1);
        $this->assertSame(0, EmailNotification::query()
            ->where('type', EmailNotification::TYPE_PURCHASE_RECEIPT)
            ->count());
    }

    public function test_a_webhook_with_a_bad_signature_sends_nothing(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer(), '15000.00');
        $this->addItem($order, $this->bookWithAuthor('Habari za Uhuru'), '15000.00');

        $payment = $this->pendingPayment($order, 'SNIP-FORGED-001');
        [$payload, $ts] = $this->signedWebhook($this->completedWebhookPayload($payment->provider_reference, (int) $order->total));

        config()->set('services.snippe.verify_on_webhook', true);

        $this->postWebhook($payload, $ts, 'forged-signature')
            ->assertOk()
            ->assertJsonPath('status', 'rejected');

        $this->assertFalse($payment->fresh()->isCompleted());
        Mail::assertNothingSent();
        $this->assertSame(0, EmailNotification::query()->count());
    }

    public function test_a_free_book_claim_sends_no_receipt(): void
    {
        $customer = $this->customer();

        $free = $this->publishedBook(['pricing_type' => Book::PRICING_FREE, 'price' => 0]);

        $response = $this->actingAs($customer)->post(route('books.claim-free', $free));

        $this->assertTrue($response->isRedirect());

        $this->assertSame(1, $customer->purchases()->count());
        Mail::assertNothingSent();
        $this->assertSame(0, EmailNotification::query()->count());
    }

    private function paidOrderWithPayment(): Order
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        return $order;
    }
}
