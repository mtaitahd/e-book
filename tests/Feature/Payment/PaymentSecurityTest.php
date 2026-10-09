<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class PaymentSecurityTest extends PaymentTestCase
{
    public function test_payment_page_never_exposes_api_key_or_webhook_secret(): void
    {
        Http::fake([
            'https://api.snippe.test/v1/payments' => Http::response([
                'success' => true,
                'data' => ['reference' => 'SNIP-SEC-1', 'status' => 'pending', 'amount' => 1500, 'currency' => 'TZS'],
            ], 200),
        ]);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->actingAs($user)
            ->get(route('account.orders.payments.show', $order))
            ->assertOk()
            ->assertDontSee('test-api-key')
            ->assertDontSee('test-webhook-secret');

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertDontSee('test-api-key')
            ->assertDontSee('test-webhook-secret');
    }

    public function test_webhook_response_never_exposes_secrets(): void
    {
        $order = $this->makeOrder($this->customer());
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => Payment::PROVIDER_SNIPPE,
            'payment_type' => Payment::TYPE_MOBILE,
            'provider_reference' => 'SNIP-SEC-2',
            'idempotency_key' => 'ebs-pay-secure-2',
            'amount' => 1500,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PENDING,
        ]);

        [$payload, $ts, $sig] = $this->signedWebhook([
            'event' => 'payment.failed',
            'data' => [
                'id' => 'evt-sec-2',
                'reference' => 'SNIP-SEC-2',
                'status' => 'failed',
            ],
        ]);

        $response = $this->postWebhook($payload, $ts, $sig)->assertOk();
        $this->assertStringNotContainsString('test-api-key', $response->getContent());
        $this->assertStringNotContainsString('test-webhook-secret', $response->getContent());

        $this->assertTrue($payment->fresh()->isFailed());
    }

    public function test_webhook_processing_is_idempotent_and_atomic(): void
    {
        $order = $this->makeOrder($this->customer());
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => Payment::PROVIDER_SNIPPE,
            'payment_type' => Payment::TYPE_MOBILE,
            'provider_reference' => 'SNIP-SEC-3',
            'idempotency_key' => 'ebs-pay-secure-3',
            'amount' => 1500,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PENDING,
        ]);

        $payload = [
            'event' => 'payment.completed',
            'data' => [
                'id' => 'evt-sec-3',
                'reference' => 'SNIP-SEC-3',
                'status' => 'completed',
                'amount' => ['value' => 1500, 'currency' => 'TZS'],
            ],
        ];
        [$payload, $ts, $sig] = $this->signedWebhook($payload);

        for ($i = 0; $i < 5; $i++) {
            $this->postWebhook($payload, $ts, $sig)->assertOk();
        }

        $this->assertSame(1, \App\Models\WebhookEvent::count());
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }
}