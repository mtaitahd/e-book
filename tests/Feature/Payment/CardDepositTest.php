<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Card collection, which shares POST /api/v1/deposits with the mobile push but
 * behaves differently in three ways that each need proving:
 *
 *  1. No phone number is sent or required — card details are collected on
 *     Abliner's own page, so sending one would be meaningless.
 *  2. The response carries a payment_url, and the customer must be redirected
 *     off-site to it. A card payment that does NOT leave the building is
 *     silently broken: the order would sit pending forever.
 *  3. Completion still arrives by webhook, exactly as for mobile money.
 */
class CardDepositTest extends TestCase
{
    use RefreshDatabase;

    protected const API_BASE = 'https://abliner.test';

    protected const API_KEY = 'tsl_live_test_key_abcdef123456';

    protected const WEBHOOK_SECRET = 'whsec_test_secret_zyxwvu987654';

    protected const CHECKOUT_URL = 'https://checkout.abliner.test/session/cs_live_9f2a41';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.abliner.base_url', self::API_BASE);
        config()->set('services.abliner.api_key', self::API_KEY);
        config()->set('services.abliner.webhook_secret', self::WEBHOOK_SECRET);
        config()->set('services.abliner.webhook_url', 'https://shop.test/webhooks/abliner');
        config()->set('services.abliner.enabled', true);
        config()->set('services.abliner.verify_on_webhook', false);
        config()->set('shop.currency', 'TZS');

        Http::fake([
            self::API_BASE.'/api/v1/deposits' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'tx_CARD_0001',
                    'type' => 'deposit',
                    'status' => 'pending',
                    'amount' => 15000,
                    'currency' => 'TZS',
                    'method' => 'card',
                    // A card deposit is hosted: this is the whole mechanism.
                    'payment_url' => self::CHECKOUT_URL,
                    'reference' => 'cardref12345678',
                    'customer_reference' => 'EBS-CARD-TEST',
                ],
            ]),
        ]);
    }

    protected function customer(): User
    {
        return User::factory()->customer()->create([
            'phone' => '2557'.fake()->unique()->numerify('########'),
        ]);
    }

    protected function makeOrder(User $user, string $total = '15000.00'): Order
    {
        do {
            $number = 'EBS-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (Order::where('order_number', $number)->exists());

        return Order::create([
            'user_id' => $user->id,
            'order_number' => $number,
            'subtotal' => $total,
            'total' => $total,
            'currency' => 'TZS',
            'status' => Order::STATUS_PENDING,
        ]);
    }

    /**
     * Submit the payment form choosing Card, exactly as the dedicated payment
     * page does.
     */
    protected function payWithCard(User $user, Order $order, array $overrides = [])
    {
        return $this->actingAs($user)->post(
            "/account/orders/{$order->id}/payment",
            array_merge([
                'method' => 'card',
                'network' => null,
                'phone' => null,
            ], $overrides)
        );
    }

    protected function depositRequest(): ?\Illuminate\Http\Client\Request
    {
        foreach (Http::recorded() as $pair) {
            if ($pair[0]->url() === self::API_BASE.'/api/v1/deposits') {
                return $pair[0];
            }
        }

        return null;
    }

    public function test_card_is_offered_as_a_payment_method(): void
    {
        $this->assertArrayHasKey(Payment::TYPE_CARD, Payment::METHODS);
        $this->assertSame('Card', Payment::METHODS[Payment::TYPE_CARD]['label']);
    }

    public function test_a_card_payment_needs_no_phone_number(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->payWithCard($user, $order)->assertRedirect(self::CHECKOUT_URL);

        $request = $this->depositRequest();

        $this->assertNotNull($request, 'a card payment must still call /deposits');
        $body = $request->data();

        $this->assertSame('card', $body['method']);
        // Card details are collected on Abliner's page; a number would be noise.
        $this->assertArrayNotHasKey('phone', $body);
        $this->assertSame(15000, $body['amount']);
        $this->assertSame('TZS', $body['currency']);
    }

    public function test_the_customer_is_redirected_to_the_hosted_card_page(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        // Not a redirect back into our own app: the whole point of a card
        // deposit is that the customer leaves for the provider's page.
        $this->payWithCard($user, $order)
            ->assertRedirect(self::CHECKOUT_URL);

        $payment = $order->payments()->latest('id')->first();

        $this->assertSame(Payment::TYPE_CARD, $payment->payment_type);
        $this->assertSame(self::CHECKOUT_URL, $payment->payment_url);
        $this->assertSame('tx_CARD_0001', $payment->provider_reference);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
    }

    public function test_a_card_payment_does_not_pay_the_order_until_the_callback(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->payWithCard($user, $order);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, Purchase::where('order_id', $order->id)->count());
    }

    public function test_a_card_payment_completes_by_webhook(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->payWithCard($user, $order);

        $payload = [
            'id' => 'evt_card_'.Str::random(8),
            'event' => 'transaction.completed',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'id' => 'tx_CARD_0001',
                'type' => 'deposit',
                'method' => 'card',
                'amount' => 15000,
                'currency' => 'TZS',
                'status' => 'completed',
                'customer_reference' => $order->order_number,
                'completed_at' => now()->toIso8601String(),
            ],
        ];

        $raw = json_encode($payload);
        $timestamp = now()->timestamp;

        $this->postJson('/webhooks/abliner', $payload, [
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => hash_hmac('sha256', $timestamp.'.'.$raw, self::WEBHOOK_SECRET),
        ])->assertOk();

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(
            Payment::STATUS_COMPLETED,
            $order->payments()->latest('id')->first()->fresh()->status
        );
    }

    public function test_a_declined_card_leaves_the_order_payable(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->payWithCard($user, $order);

        $payload = [
            'id' => 'evt_card_'.Str::random(8),
            'event' => 'transaction.failed',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'id' => 'tx_CARD_0001',
                'method' => 'card',
                'amount' => 15000,
                'currency' => 'TZS',
                'status' => 'failed',
                'customer_reference' => $order->order_number,
            ],
        ];

        $raw = json_encode($payload);
        $timestamp = now()->timestamp;

        $this->postJson('/webhooks/abliner', $payload, [
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => hash_hmac('sha256', $timestamp.'.'.$raw, self::WEBHOOK_SECRET),
        ])->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, Purchase::where('order_id', $order->id)->count());
    }
}