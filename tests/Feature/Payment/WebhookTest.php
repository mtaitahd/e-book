<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Snippe\SnippeSignatureVerifier;
use App\Settings\PaymentProviderConfig;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookTest extends PaymentTestCase
{
    private function pendingPayment(Order $order, string $reference = 'SNIP-WH-001')
    {
        return Payment::create([
            'order_id' => $order->id,
            'provider' => Payment::PROVIDER_SNIPPE,
            'payment_type' => Payment::TYPE_MOBILE,
            'provider_reference' => $reference,
            'idempotency_key' => 'ebs-pay-'.Str::random(8),
            'amount' => 1500,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PENDING,
            'channel_provider' => 'mpesa',
        ]);
    }

    public function test_completed_event_marks_payment_and_order_paid(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);
        [$payload, $ts, $sig] = $this->signedWebhook($this->completedWebhookPayload($payment->provider_reference));

        $this->postWebhook($payload, $ts, $sig)
            ->assertOk()
            ->assertJsonPath('status', WebhookEvent::STATUS_PROCESSED);

        $this->assertTrue($payment->fresh()->isCompleted());
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame($payment->fresh()->paid_at->timestamp, $order->fresh()->paid_at->timestamp);
    }

    public function test_failed_event_updates_payment_only(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);
        $payload = $this->completedWebhookPayload($payment->provider_reference);
        $payload['event'] = 'payment.failed';
        $payload['data']['status'] = 'failed';

        [$payload, $ts, $sig] = $this->signedWebhook($payload);

        $this->postWebhook($payload, $ts, $sig)->assertOk();

        $this->assertTrue($payment->fresh()->isFailed());
        $this->assertTrue($order->fresh()->isPending());
    }

    public function test_voided_event_updates_payment_only(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);
        $payload = $this->completedWebhookPayload($payment->provider_reference);
        $payload['event'] = 'payment.voided';
        $payload['data']['status'] = 'voided';

        [$payload, $ts, $sig] = $this->signedWebhook($payload);

        $this->postWebhook($payload, $ts, $sig)->assertOk();

        $this->assertTrue($payment->fresh()->isVoided());
        $this->assertTrue($order->fresh()->isPending());
    }

    public function test_expired_event_updates_payment_only(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);
        $payload = $this->completedWebhookPayload($payment->provider_reference);
        $payload['event'] = 'payment.expired';
        $payload['data']['status'] = 'expired';

        [$payload, $ts, $sig] = $this->signedWebhook($payload);

        $this->postWebhook($payload, $ts, $sig)->assertOk();

        $this->assertTrue($payment->fresh()->isExpired());
        $this->assertTrue($order->fresh()->isPending());
    }

    public function test_event_type_falls_back_to_status(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);
        $payload = $this->completedWebhookPayload($payment->provider_reference);
        $payload['data']['status'] = 'completed';
        unset($payload['event']);

        [$payload, $ts, $sig] = $this->signedWebhook($payload);

        $this->postWebhook($payload, $ts, $sig)->assertOk();

        $this->assertTrue($payment->fresh()->isCompleted());
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_duplicate_event_is_not_reprocessed(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);
        [$payload, $ts, $sig] = $this->signedWebhook($this->completedWebhookPayload($payment->provider_reference));

        $this->postWebhook($payload, $ts, $sig)->assertOk();
        $this->postWebhook($payload, $ts, $sig)->assertOk();

        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame(1, $order->payments()->where('status', Payment::STATUS_COMPLETED)->count());
    }

    public function test_completed_payment_is_final_and_not_overridden(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);

        [$complete, $ts, $sig] = $this->signedWebhook($this->completedWebhookPayload($payment->provider_reference));
        $this->postWebhook($complete, $ts, $sig)->assertOk();

        $failed = $this->completedWebhookPayload($payment->provider_reference);
        $failed['event'] = 'payment.failed';
        $failed['data']['status'] = 'failed';
        [$failed, $ts, $sig] = $this->signedWebhook($failed);

        $this->postWebhook($failed, $ts, $sig)->assertOk();

        $this->assertTrue($payment->fresh()->isCompleted());
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_verify_on_webhook_rechecks_with_provider_before_completing(): void
    {
        config()->set('services.snippe.verify_on_webhook', true);

        Http::fake([
            'https://api.snippe.test/v1/payments/SNIP-WH-001' => Http::response([
                'data' => ['reference' => 'SNIP-WH-001', 'status' => 'completed'],
            ], 200),
        ]);

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);
        [$payload, $ts, $sig] = $this->signedWebhook($this->completedWebhookPayload($payment->provider_reference));

        $this->postWebhook($payload, $ts, $sig)->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://api.snippe.test/v1/payments/SNIP-WH-001');

        $this->assertTrue($payment->fresh()->isCompleted());
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_verify_on_webhook_does_not_complete_when_provider_says_pending(): void
    {
        config()->set('services.snippe.verify_on_webhook', true);

        Http::fake([
            'https://api.snippe.test/v1/payments/SNIP-WH-001' => Http::response([
                'data' => ['reference' => 'SNIP-WH-001', 'status' => 'pending'],
            ], 200),
        ]);

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);
        [$payload, $ts, $sig] = $this->signedWebhook($this->completedWebhookPayload($payment->provider_reference));

        $this->postWebhook($payload, $ts, $sig)->assertOk()->assertJsonPath('status', WebhookEvent::STATUS_FAILED);

        $this->assertTrue($payment->fresh()->isPending());
        $this->assertTrue($order->fresh()->isPending());
    }

    public function test_amount_mismatch_never_marks_order_paid(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);

        [$payload, $ts, $sig] = $this->signedWebhook(
            $this->completedWebhookPayload($payment->provider_reference, amount: 1501)
        );

        $this->postWebhook($payload, $ts, $sig)->assertOk()->assertJsonPath('status', WebhookEvent::STATUS_FAILED);

        $this->assertTrue($payment->fresh()->isPending());
        $this->assertTrue($order->fresh()->isPending());
    }

    public function test_currency_mismatch_never_marks_order_paid(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);

        [$payload, $ts, $sig] = $this->signedWebhook(
            $this->completedWebhookPayload($payment->provider_reference, currency: 'USD')
        );

        $this->postWebhook($payload, $ts, $sig)->assertOk()->assertJsonPath('status', WebhookEvent::STATUS_FAILED);

        $this->assertTrue($payment->fresh()->isPending());
        $this->assertTrue($order->fresh()->isPending());
    }

    public function test_unrelated_event_is_recorded_and_ignored(): void
    {
        Http::fake();

        $payload = [
            'data' => [
                'id' => 'evt-payout-1',
                'type' => 'payout.completed',
                'status' => 'completed',
            ],
        ];
        [$payload, $ts, $sig] = $this->signedWebhook($payload);

        $this->postWebhook($payload, $ts, $sig)
            ->assertOk()
            ->assertJsonPath('status', WebhookEvent::STATUS_IGNORED);

        $this->assertDatabaseHas('webhook_events', [
            'event_id' => 'evt-payout-1',
            'status' => WebhookEvent::STATUS_IGNORED,
        ]);
    }

    public function test_unknown_reference_records_failed_event(): void
    {
        Http::fake();

        [$payload, $ts, $sig] = $this->signedWebhook($this->completedWebhookPayload('SNIP-DOES-NOT-EXIST'));

        $this->postWebhook($payload, $ts, $sig)
            ->assertOk()
            ->assertJsonPath('status', WebhookEvent::STATUS_FAILED);

        $this->assertDatabaseHas('webhook_events', [
            'status' => WebhookEvent::STATUS_FAILED,
            'failure_reason' => 'no matching payment',
        ]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);
        [$payload, $ts] = $this->signedWebhook($this->completedWebhookPayload($payment->provider_reference));

        $badSignature = hash_hmac('sha256', $ts.'.'.json_encode($payload), 'wrong-secret');

        $this->postWebhook($payload, $ts, $badSignature)
            ->assertOk()
            ->assertJsonPath('status', 'rejected');

        $this->assertTrue($payment->fresh()->isPending());
        $this->assertTrue($order->fresh()->isPending());
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);
        [$payload, , $sig] = $this->signedWebhook(
            $this->completedWebhookPayload($payment->provider_reference),
            now()->subSeconds(400)->timestamp
        );

        $this->postWebhook($payload, now()->subSeconds(400)->timestamp, $sig)->assertOk()->assertJsonPath('status', 'rejected');

        $this->assertTrue($payment->fresh()->isPending());
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_signature_verifier_rejects_missing_parts_and_stale_boundary(): void
    {
        // The verifier now takes the provider configuration and reads the secret
        // per call, so a rotated credential takes effect immediately.
        $verifier = new SnippeSignatureVerifier(app(PaymentProviderConfig::class));
        $payload = json_encode(['hello' => 'world']);
        $ts = (string) now()->timestamp;
        $sig = hash_hmac('sha256', $ts.'.'.$payload, 'test-webhook-secret');

        $this->assertTrue($verifier->verify($ts, $sig, $payload));
        $this->assertFalse($verifier->verify(null, $sig, $payload));
        $this->assertFalse($verifier->verify($ts, null, $payload));
        $this->assertFalse($verifier->verify($ts, '', $payload));

        $stale = (string) (now()->subSeconds(301)->timestamp);
        $staleSig = hash_hmac('sha256', $stale.'.'.$payload, 'test-webhook-secret');
        $this->assertFalse($verifier->verify($stale, $staleSig, $payload));

        $boundary = (string) (now()->subSeconds(300)->timestamp);
        $boundarySig = hash_hmac('sha256', $boundary.'.'.$payload, 'test-webhook-secret');
        $this->assertTrue($verifier->verify($boundary, $boundarySig, $payload));
    }

    public function test_the_verifier_refuses_everything_when_no_secret_is_available(): void
    {
        // Fail closed: without a secret there is no way to tell a genuine
        // callback from a forged one, so nothing may be accepted.
        config()->set('services.snippe.webhook_secret', null);

        $verifier = new SnippeSignatureVerifier(app(PaymentProviderConfig::class));
        $payload = json_encode(['hello' => 'world']);
        $ts = (string) now()->timestamp;
        $sig = hash_hmac('sha256', $ts.'.'.$payload, '');

        $this->assertFalse($verifier->isConfigured());
        $this->assertFalse($verifier->verify($ts, $sig, $payload));
    }

    public function test_webhook_is_configures_as_the_only_csrf_exemption(): void
    {
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->pendingPayment($order);
        [$payload, $ts, $sig] = $this->signedWebhook($this->completedWebhookPayload($payment->provider_reference));

        // The public webhook works without any session/CSRF token.
        $this->postWebhook($payload, $ts, $sig)->assertOk();

        // The only configured CSRF exclusion is the webhook route itself —
        // every other storefront mutation keeps CSRF protection.
        $excluded = app(VerifyCsrfToken::class)->getExcludedPaths();
        $this->assertContains('webhooks/snippe', $excluded);
        $this->assertSame(['webhooks/snippe'], array_values(array_filter($excluded, fn ($path) => $path !== '' && $path !== '/')));
    }
}
