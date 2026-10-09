<?php

namespace Tests\Feature\EmailNotifications;

use App\Mail\PurchaseReceiptMail;
use App\Models\EmailNotification;
use App\Services\PurchaseService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;

class EmailIdempotencyTest extends EmailTestCase
{
    public function test_repeating_the_paid_flow_never_sends_a_second_receipt(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);
        $this->emails()->sendPurchaseReceiptFor($order);
        $this->emails()->sendPurchaseReceiptFor($order->fresh());

        Mail::assertSent(PurchaseReceiptMail::class, 1);
        $this->assertSame(1, EmailNotification::query()->count());
        $this->assertSame(1, $this->receiptFor($order)->attempts);
    }

    public function test_the_database_refuses_a_second_row_for_the_same_order_and_type(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);
        $this->emails()->sendPurchaseReceiptFor($order);

        $this->expectException(UniqueConstraintViolationException::class);

        EmailNotification::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'payment_id' => null,
            'type' => EmailNotification::TYPE_PURCHASE_RECEIPT,
            'recipient' => $order->user->email,
            'status' => EmailNotification::STATUS_PENDING,
        ]);
    }

    public function test_a_concurrent_confirmation_that_loses_the_race_sends_nothing(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);
        Mail::fake();

        // A second confirmation arriving while the first delivery is in flight
        // must find the slot taken and stop, not queue a duplicate.
        $this->emails()->sendPurchaseReceiptFor($order->fresh());

        Mail::assertNothingSent();
    }

    public function test_a_failed_delivery_is_recorded_and_does_not_roll_back_the_purchase(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP connection refused'));

        // Must not throw: the money is already correct and the customer already
        // owns the book, so a broken mailer cannot undo any of it.
        $this->emails()->sendPurchaseReceiptFor($order);

        $log = $this->receiptFor($order);
        $this->assertNotNull($log);
        $this->assertSame(EmailNotification::STATUS_FAILED, $log->status);
        $this->assertNull($log->sent_at);
        $this->assertSame(1, $log->attempts);
        $this->assertStringContainsString('RuntimeException', $log->error_message);
        $this->assertStringContainsString('SMTP connection refused', $log->error_message);

        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(1, $order->purchases()->count());
        $this->assertTrue($order->purchases()->first()->book->is($book));
        $this->assertTrue($order->payments()->first()->isCompleted());
    }

    public function test_a_later_confirmation_retries_a_previously_failed_delivery(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $payment = $this->completedPayment($order);

        // The state a previous broken delivery left behind.
        EmailNotification::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'type' => EmailNotification::TYPE_PURCHASE_RECEIPT,
            'recipient' => $order->user->email,
            'status' => EmailNotification::STATUS_FAILED,
            'attempts' => 1,
            'error_message' => 'RuntimeException: SMTP connection refused',
        ]);

        $this->emails()->sendPurchaseReceiptFor($order);

        Mail::assertSent(PurchaseReceiptMail::class, 1);

        $log = $this->receiptFor($order);
        $this->assertSame(EmailNotification::STATUS_SENT, $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertSame(2, $log->attempts);
        $this->assertNull($log->error_message);

        // The retry reuses the same ledger row rather than adding a second.
        $this->assertSame(1, EmailNotification::query()->count());
    }

    public function test_the_ledger_never_stores_mail_credentials(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        // A DSN can carry the mailbox password in the URL user-info part.
        Mail::shouldReceive('to')->andThrow(
            new \RuntimeException('Connection failed: smtp://mailer:hunter2@mail.test:587')
        );

        $this->emails()->sendPurchaseReceiptFor($order);

        $message = (string) $this->receiptFor($order)->error_message;

        $this->assertStringNotContainsString('hunter2', $message);
        $this->assertStringNotContainsString('mailer:', $message);
        $this->assertStringContainsString('smtp://mail.test', $message);
    }

    public function test_replaying_the_paid_funnel_creates_one_entitlement_and_one_receipt(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        // Exercise the real funnel that raises the event, twice, the way a
        // replayed webhook and a customer status refresh would.
        app(PurchaseService::class)->createFromPaidOrder($order);
        app(PurchaseService::class)->createFromPaidOrder($order->fresh());

        $this->assertSame(1, $order->purchases()->count());

        Mail::assertSent(PurchaseReceiptMail::class, 1);
        $this->assertSame(1, EmailNotification::query()->count());
        $this->assertSame(1, $this->receiptFor($order)->attempts);
    }

    public function test_a_paid_order_with_no_items_raises_no_notification(): void
    {
        $order = $this->paidOrder($this->customer(), []);

        $this->assertSame(0, app(PurchaseService::class)->createFromPaidOrder($order->fresh()));

        Mail::assertNothingSent();
        $this->assertSame(0, EmailNotification::query()->count());
    }
}
