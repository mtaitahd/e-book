<?php

namespace Tests\Feature\Settings;

use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Settings\PaymentProviderConfig;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Payment\PaymentTestCase;

/**
 * Settings tests need the same Snippe fixtures and signed-webhook helpers the
 * payment suite already owns, so this extends that base case rather than
 * duplicating the HMAC plumbing.
 */
abstract class PaymentSettingsTestCase extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The payment base case turns webhook re-verification off for speed;
        // Stage 10.1 asserts on the hardened posture, so it is turned back on.
        config()->set('services.snippe.verify_on_webhook', true);
        config()->set('services.snippe.enabled', true);
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Store credentials the way the admin page does, then return the row.
     *
     * Written through the model rather than the HTTP route so a test can assert
     * on what was stored without also asserting on the request handling.
     */
    protected function saveSettings(
        ?string $apiKey = null,
        ?string $webhookSecret = null,
        ?string $webhookUrl = null,
        bool $enabled = true,
        bool $verifyOnWebhook = true,
    ): PaymentSetting {
        $row = PaymentSetting::query()->firstOrNew([
            'provider' => PaymentSetting::PROVIDER_SNIPPE,
        ]);

        $row->fill([
            'enabled' => $enabled,
            'verify_on_webhook' => $verifyOnWebhook,
            'updated_by' => $this->admin()->id,
        ]);

        if ($apiKey !== null) {
            $row->api_key = $apiKey;
        }

        if ($webhookSecret !== null) {
            $row->webhook_secret = $webhookSecret;
        }

        if ($webhookUrl !== null) {
            $row->webhook_url = $webhookUrl;
        }

        $row->save();

        // The resolver memoises per request; a test that saves then reads needs
        // it to look at the new state.
        app(PaymentProviderConfig::class)->refresh();

        return $row;
    }

    /**
     * The raw column value exactly as it sits in the database, without going
     * through the encrypted cast.
     */
    protected function rawSecretColumn(string $field): ?string
    {
        return DB::table('payment_settings')
            ->where('provider', PaymentSetting::PROVIDER_SNIPPE)
            ->value($field);
    }

    /**
     * Post the settings form from the settings page, so `back()` resolves to a
     * real URL rather than to the test root.
     */
    protected function saveViaPage(array $input = [])
    {
        return $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments', $input);
    }

    /**
     * Start a real payment for a fresh order, so the outbound payload Snippe
     * would receive can be inspected.
     */
    protected function postPayment(array $attributes = []): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post(route('account.orders.payments.store', $this->makeOrder($customer)), array_merge([
                'network' => 'airtel_money',
                'phone' => '0754123456',
            ], $attributes));
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

    /**
     * A payment row in a chosen state. `payments.order_id` is NOT NULL, so every
     * row is attached to a real order; pass order_id to reuse one.
     */
    protected function payment(string $status, array $attributes = []): Payment
    {
        static $sequence = 0;
        $sequence++;

        return Payment::create(array_merge([
            'order_id' => $this->makeOrder($this->customer())->id,
            'provider' => Payment::PROVIDER_SNIPPE,
            'payment_type' => Payment::TYPE_MOBILE,
            'provider_reference' => 'ref-'.$sequence,
            'external_reference' => 'ext-'.$sequence,
            'idempotency_key' => 'idem-'.$sequence,
            'amount' => 1500,
            'currency' => 'TZS',
            'status' => $status,
            'channel_provider' => 'mpesa',
        ], $attributes));
    }

    /**
     * Backdate a timestamp column on a payment row.
     *
     * created_at/updated_at are not mass-assignable on Payment, so a fixture
     * that needs a payment at a known moment in the past must write the column
     * directly rather than hope create() accepted it.
     */
    protected function backdate(Payment $payment, string $column, DateTimeInterface $moment): Payment
    {
        DB::table('payments')
            ->where('id', $payment->id)
            ->update([$column => $moment]);

        return $payment->fresh();
    }
}
