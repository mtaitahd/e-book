<?php

namespace Tests\Feature\Settings;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Snippe\SnippePaymentService;
use Illuminate\Support\Facades\Http;

class PaymentSettingsEnablementTest extends PaymentSettingsTestCase
{
    public function test_the_provider_is_enabled_by_default(): void
    {
        // Adding the flag must not change behaviour for an existing install.
        $this->assertTrue(app(SnippePaymentService::class)->isEnabled());
        $this->assertTrue(app(SnippePaymentService::class)->isAvailable());
    }

    public function test_the_flag_accepts_the_usual_env_spellings(): void
    {
        foreach (['true', '1', 'on', 'yes', true] as $truthy) {
            config()->set('services.snippe.enabled', $truthy);
            $this->assertTrue(app(SnippePaymentService::class)->isEnabled(), var_export($truthy, true));
        }

        foreach (['false', '0', 'off', 'no', '', false] as $falsy) {
            config()->set('services.snippe.enabled', $falsy);
            $this->assertFalse(app(SnippePaymentService::class)->isEnabled(), var_export($falsy, true));
        }
    }

    public function test_a_disabled_provider_is_not_available(): void
    {
        config()->set('services.snippe.enabled', false);

        $this->assertFalse(app(SnippePaymentService::class)->isEnabled());
        $this->assertFalse(app(SnippePaymentService::class)->isAvailable());
    }

    public function test_an_enabled_provider_without_a_key_is_not_available(): void
    {
        config()->set('services.snippe.api_key', null);

        $this->assertTrue(app(SnippePaymentService::class)->isEnabled());
        $this->assertFalse(app(SnippePaymentService::class)->isAvailable());
    }

    public function test_a_disabled_provider_never_sends_a_payment_request(): void
    {
        config()->set('services.snippe.enabled', false);
        Http::fake();

        $order = $this->makeOrder($this->customer());
        $payment = $this->payment(Payment::STATUS_PENDING, ['order_id' => $order->id]);

        $this->actingAs($order->user)
            ->from(route('account.orders.payments.show', $order))
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'mpesa',
                'phone' => '0754123456',
            ])
            ->assertRedirect(route('account.orders.payments.show', $order));

        Http::assertNothingSent();
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_a_disabled_provider_hides_the_payment_form(): void
    {
        config()->set('services.snippe.enabled', false);

        $order = $this->makeOrder($this->customer());

        $response = $this->actingAs($order->user)
            ->get(route('account.orders.payments.show', $order));

        $response->assertOk();
        $response->assertDontSee(route('account.orders.payments.store', $order), false);
    }

    public function test_an_enabled_provider_still_shows_the_payment_form(): void
    {
        $order = $this->makeOrder($this->customer());

        $this->actingAs($order->user)
            ->get(route('account.orders.payments.show', $order))
            ->assertOk()
            ->assertSee(route('account.orders.payments.store', $order), false);
    }

    public function test_an_admin_told_the_differently_that_payments_were_switched_off(): void
    {
        config()->set('services.snippe.enabled', false);
        Http::fake();

        $admin = $this->admin();
        $order = $this->makeOrder($admin);

        $this->actingAs($admin)
            ->from(route('account.orders.payments.show', $order))
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'mpesa',
                'phone' => '0754123456',
            ])
            ->assertRedirect(route('account.orders.payments.show', $order))
            ->assertSessionHas('error');
    }

    public function test_a_disabled_provider_still_lets_a_pending_payment_be_checked(): void
    {
        // A customer who already pushed a payment must not be stranded by an
        // operator switch, so an existing pending payment stays visible and
        // refreshable.
        config()->set('services.snippe.enabled', false);

        $order = $this->makeOrder($this->customer());
        $this->payment(Payment::STATUS_PENDING, ['order_id' => $order->id]);

        $response = $this->actingAs($order->user)
            ->get(route('account.orders.payments.show', $order));

        $response->assertOk();
        $response->assertSee('A payment is already in progress');
        $response->assertSee(route('account.orders.payments.refresh', $order), false);
        // ...but still no way to start a new one. Match the form action rather
        // than the bare URL, which the refresh URL starts with.
        $response->assertDontSee('action="'.route('account.orders.payments.store', $order).'"', false);
    }

    public function test_a_disabled_provider_still_verifies_incoming_webhooks(): void
    {
        // Switching payments off must never open the webhook endpoint.
        // Outbound re-verification is turned off here so the assertion is
        // purely about the enabled flag, not about a second API call.
        config()->set('services.snippe.enabled', false);
        config()->set('services.snippe.verify_on_webhook', false);

        $order = $this->makeOrder($this->customer());
        $payment = $this->payment(Payment::STATUS_PENDING, [
            'order_id' => $order->id,
            'provider_reference' => 'ref-live-1',
        ]);

        [$payload, $timestamp, $signature] = $this->signedWebhook(
            $this->completedWebhookPayload('ref-live-1', 1500)
        );

        $this->postJson('/webhooks/snippe', $payload, [
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => $signature,
        ])->assertOk();

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_a_disabled_provider_still_rejects_a_forged_webhook(): void
    {
        config()->set('services.snippe.enabled', false);
        config()->set('services.snippe.verify_on_webhook', false);

        $order = $this->makeOrder($this->customer());
        $payment = $this->payment(Payment::STATUS_PENDING, [
            'order_id' => $order->id,
            'provider_reference' => 'ref-forged',
        ]);

        [$payload, $timestamp] = $this->signedWebhook(
            $this->completedWebhookPayload('ref-forged', 1500)
        );

        $response = $this->postJson('/webhooks/snippe', $payload, [
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => 'deadbeef'.str_repeat('0', 56),
        ]);

        // The endpoint deliberately answers 200 with a "rejected" body rather
        // than a 4xx, so a prober learns nothing about being detected.
        $response->assertOk();
        $this->assertSame('rejected', $response->json('status'));
        $this->assertSame('invalid signature', $response->json('reason'));

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_a_disabled_provider_does_not_revoke_an_already_paid_order(): void
    {
        // Turning payments off is an availability switch, never a data switch.
        config()->set('services.snippe.enabled', false);

        $order = $this->makeOrder($this->customer(), '0.00');
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        $this->assertTrue($order->fresh()->isPaid());
    }
}
