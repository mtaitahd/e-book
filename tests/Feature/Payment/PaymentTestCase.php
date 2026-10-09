<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class PaymentTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.snippe.base_url', 'https://api.snippe.test');
        config()->set('services.snippe.api_key', 'test-api-key');
        config()->set('services.snippe.webhook_secret', 'test-webhook-secret');
        config()->set('services.snippe.webhook_url', 'https://shop.test/webhooks/snippe');
        config()->set('services.snippe.verify_on_webhook', false);
        config()->set('shop.currency', 'TZS');
    }

    protected function customer(array $attributes = []): User
    {
        return User::factory()->customer()->create(array_merge([
            'first_name' => 'Hassan',
            'last_name' => 'Ali',
            // users.phone is unique, so every customer needs its own number.
            // Pass a specific one through $attributes when a test cares.
            'phone' => '2557'.fake()->unique()->numerify('########'),
        ], $attributes));
    }

    protected function makeOrder(User $user, string $total = '1500.00', string $currency = 'TZS'): Order
    {
        do {
            $number = 'EBS-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (Order::where('order_number', $number)->exists());

        return Order::create([
            'user_id' => $user->id,
            'order_number' => $number,
            'subtotal' => $total,
            'total' => $total,
            'currency' => $currency,
            'status' => Order::STATUS_PENDING,
        ]);
    }

    /**
     * Build a webhook payload, timestamp, signature and the raw body.
     *
     * @return array{0: array, 1: int, 2: string, 3: string}
     */
    protected function signedWebhook(array $payload, ?int $timestamp = null, string $secret = 'test-webhook-secret'): array
    {
        $timestamp ??= now()->timestamp;
        $raw = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp.'.'.$raw, $secret);

        return [$payload, $timestamp, $signature, $raw];
    }

    protected function postWebhook(array $payload, int $timestamp, string $signature): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/webhooks/snippe', $payload, [
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => $signature,
        ]);
    }

    protected function completedWebhookPayload(string $reference, int $amount = 1500, string $currency = 'TZS'): array
    {
        return [
            'event' => 'payment.completed',
            'data' => [
                'id' => 'evt-'.Str::random(12),
                'reference' => $reference,
                'status' => 'completed',
                'amount' => ['value' => $amount, 'currency' => $currency],
                'metadata' => [],
            ],
        ];
    }
}