<?php

namespace Tests\Feature\EmailNotifications;

use App\Mail\PaymentUnsuccessfulMail;
use App\Models\EmailNotification;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Mail;

class PaymentFailureEmailTest extends EmailTestCase
{
    public function test_a_failed_payment_emails_the_customer(): void
    {
        $order = $this->makeOrder($this->customer(), '15000.00');
        $payment = $this->pendingPayment($order);

        $payment->update([
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => 'request_failed:insufficient funds',
        ]);

        Mail::assertSent(PaymentUnsuccessfulMail::class, 1);

        $log = EmailNotification::query()
            ->where('order_id', $order->id)
            ->where('type', EmailNotification::TYPE_PAYMENT_FAILED)
            ->first();

        $this->assertNotNull($log);
        $this->assertTrue($log->isSent());
        $this->assertSame($payment->id, $log->payment_id);
    }

    public function test_a_failure_email_uses_the_required_subject(): void
    {
        $order = $this->makeOrder($this->customer(), '15000.00');
        $payment = $this->pendingPayment($order);

        $payment->update(['status' => Payment::STATUS_FAILED]);

        Mail::assertSent(PaymentUnsuccessfulMail::class, 1);

        $subject = null;

        Mail::assertSent(function (PaymentUnsuccessfulMail $mail) use (&$subject) {
            $subject = $mail->envelope()->subject;

            return true;
        });

        $this->assertSame('Payment failed — Order '.$order->order_number, $subject);
    }

    public function test_a_failure_email_never_leaks_the_raw_provider_error(): void
    {
        $order = $this->makeOrder($this->customer(), '15000.00');
        $payment = $this->pendingPayment($order);

        $secretish = 'request_failed:declined_by:api_secret_9f3b2a :: trace#1234';

        $payment->update([
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => $secretish,
        ]);

        $html = $this->sentHtml();

        $this->assertStringNotContainsString($secretish, $html);
        $this->assertStringNotContainsString('api_secret_9f3b2a', $html);
        $this->assertStringNotContainsString('trace#1234', $html);
        $this->assertStringNotContainsString('request_failed', $html);
    }

    public function test_a_failure_email_never_leaks_credentials(): void
    {
        $order = $this->makeOrder($this->customer(), '15000.00');
        $payment = $this->pendingPayment($order);

        $payment->update(['status' => Payment::STATUS_FAILED]);

        $html = $this->sentHtml();

        $this->assertStringNotContainsString('test-webhook-secret', $html);
        $this->assertStringNotContainsString('test-api-key', $html);
    }

    public function test_a_failure_email_reassures_and_offers_a_retry_link(): void
    {
        $order = $this->makeOrder($this->customer(), '15000.00');
        $payment = $this->pendingPayment($order);

        $payment->update(['status' => Payment::STATUS_FAILED]);

        $html = $this->sentHtml();

        $this->assertStringContainsString('You have not been charged', $html);
        $this->assertStringContainsString('Try payment again', $html);
        $this->assertStringContainsString(route('account.orders.payments.show', $order), $html);
        $this->assertStringContainsString(route('account.orders.show', $order), $html);
        $this->assertStringContainsString($order->order_number, $html);
    }

    public function test_a_voided_payment_gets_its_own_single_notice(): void
    {
        $order = $this->makeOrder($this->customer(), '15000.00');
        $payment = $this->pendingPayment($order);

        $payment->update(['status' => Payment::STATUS_VOIDED]);

        Mail::assertSent(PaymentUnsuccessfulMail::class, 1);

        $this->assertSame(1, EmailNotification::query()
            ->where('type', EmailNotification::TYPE_PAYMENT_VOIDED)
            ->count());

        $this->assertStringContainsString('voided', $this->sentHtml());
    }

    public function test_an_expired_payment_gets_its_own_single_notice(): void
    {
        $order = $this->makeOrder($this->customer(), '15000.00');
        $payment = $this->pendingPayment($order);

        $payment->update(['status' => Payment::STATUS_EXPIRED]);

        Mail::assertSent(PaymentUnsuccessfulMail::class, 1);

        $this->assertSame(1, EmailNotification::query()
            ->where('type', EmailNotification::TYPE_PAYMENT_EXPIRED)
            ->count());

        $this->assertStringContainsString('expired', $this->sentHtml());
    }

    public function test_repeating_the_same_failure_never_sends_twice(): void
    {
        $order = $this->makeOrder($this->customer(), '15000.00');
        $payment = $this->pendingPayment($order);

        $payment->update(['status' => Payment::STATUS_FAILED]);
        $payment->update(['failure_reason' => 'request_failed:timeout']);
        $payment->update(['failure_reason' => 'request_failed:timeout again']);

        Mail::assertSent(PaymentUnsuccessfulMail::class, 1);
        $this->assertSame(1, EmailNotification::query()->count());
    }

    public function test_a_payment_that_stays_pending_emails_nothing(): void
    {
        $order = $this->makeOrder($this->customer(), '15000.00');
        $payment = $this->pendingPayment($order);

        $payment->update(['failure_reason' => 'still waiting']);

        Mail::assertNothingSent();
        $this->assertSame(0, EmailNotification::query()->count());
    }

    public function test_a_successful_payment_emails_no_failure_notice(): void
    {
        $order = $this->makeOrder($this->customer(), '15000.00');
        $payment = $this->pendingPayment($order);

        $this->completedPayment($order);

        $this->assertTrue($payment->fresh()->isCompleted());

        Mail::assertNotSent(PaymentUnsuccessfulMail::class);
        $this->assertSame(0, EmailNotification::query()
            ->whereIn('type', [
                EmailNotification::TYPE_PAYMENT_FAILED,
                EmailNotification::TYPE_PAYMENT_VOIDED,
                EmailNotification::TYPE_PAYMENT_EXPIRED,
            ])
            ->count());
    }

    public function test_a_late_failure_event_never_warns_a_customer_who_already_paid(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $payment = $this->pendingPayment($order);
        $this->completedPayment($order);

        Mail::fake();

        // The provider retries an old failure event after the payment settled.
        $this->emails()->sendUnsuccessfulNoticeFor($payment->fresh());

        Mail::assertNothingSent();
    }

    public function test_a_failure_email_is_addressed_to_the_order_owner(): void
    {
        $customer = $this->customer(['email' => 'amina@example.test', 'name' => 'Amina Said']);
        $order = $this->makeOrder($customer, '15000.00');
        $payment = $this->pendingPayment($order);

        $payment->update(['status' => Payment::STATUS_FAILED]);

        Mail::assertSent(PaymentUnsuccessfulMail::class, function (PaymentUnsuccessfulMail $mail) {
            return $mail->hasTo('amina@example.test');
        });

        $this->assertStringContainsString('Amina Said', $this->sentHtml());
    }

    public function test_no_failure_email_is_sent_when_the_order_has_no_valid_recipient(): void
    {
        $customer = $this->customer(['email' => 'not-an-email']);
        $order = $this->makeOrder($customer, '15000.00');
        $payment = $this->pendingPayment($order);

        $this->emails()->sendUnsuccessfulNoticeFor($payment);

        Mail::assertNothingSent();
        $this->assertSame(0, EmailNotification::query()->count());
    }

    public function test_a_failure_email_never_grants_a_purchase(): void
    {
        $order = $this->makeOrder($this->customer(), '15000.00');
        $payment = $this->pendingPayment($order);

        $payment->update(['status' => Payment::STATUS_FAILED]);

        $this->assertSame(0, $order->purchases()->count());
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }
}
