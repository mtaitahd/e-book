<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * One-tap "Quick pay" on the order page.
 *
 * An unpaid order is the only place a new payment can be started, so the page
 * offers a button that starts the mobile money push immediately using the last
 * number and network this customer paid with. Older payment rows carry no phone
 * of their own, so those reuse the account number.
 */
class QuickPayTest extends TestCase
{
    use RefreshDatabase;

    protected const API_BASE = 'https://abliner.test';

    protected const DEPOSIT_URL = self::API_BASE.'/api/v1/deposits';

    protected function setUp(): void
    {
        parent::setUp();

        // The OrderController only offers Quick pay once the provider is on and
        // can start a real push, so stand one up for these tests.
        config([
            'services.abliner.enabled' => true,
            'services.abliner.base_url' => self::API_BASE,
            'services.abliner.api_key' => 'tsl_test_key_abcdef123456',
            'services.abliner.webhook_secret' => 'whsec_test_secret_zyxwvu987654',
            'services.abliner.webhook_url' => 'https://shop.test/webhooks/abliner',
        ]);

        Http::fake([
            self::DEPOSIT_URL => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 'tx_'.Str::random(12),
                    'type' => 'deposit',
                    'flow' => 'push',
                    'status' => 'pending',
                    'amount' => 1500,
                    'currency' => 'TZS',
                    'method' => 'mobile',
                    'payment_url' => null,
                    'reference' => Str::random(12),
                    'customer_reference' => 'dummy',
                    'message' => 'Mobile money push sent.',
                ],
            ], 200),
        ]);
    }

    public function test_order_page_offers_quick_pay_with_the_last_number_and_network(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->orderFor($user);

        $order->payments()->create([
            'provider' => Payment::PROVIDER_ABLINER,
            'payment_type' => Payment::TYPE_MOBILE,
            'phone' => '255713111222',
            'channel_provider' => 'mpesa',
            'idempotency_key' => 'tst_'.Str::random(16),
            'amount' => 1500,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
        ]);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('Quick pay', false)
            ->assertSee(route('account.orders.payments.store', $order), false)
            ->assertSee('value="mpesa"', false)
            ->assertSee('value="255713111222"', false);
    }

    public function test_quick_pay_falls_back_to_a_number_from_another_order(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $paid = $this->orderFor($user, 'EBS-19990101-0001');

        $paid->payments()->create([
            'provider' => Payment::PROVIDER_ABLINER,
            'payment_type' => Payment::TYPE_MOBILE,
            'phone' => '255755000111',
            'channel_provider' => 'mixx_yas',
            'idempotency_key' => 'tst_'.Str::random(16),
            'amount' => 1500,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
        ]);

        $order = $this->orderFor($user, 'EBS-20000101-0002');

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('value="mixx_yas"', false)
            ->assertSee('value="255755000111"', false);
    }

    public function test_quick_pay_uses_the_account_number_when_the_last_payment_has_no_phone(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->orderFor($user);

        // Rows created before the payment phone was introduced carried no phone
        // of their own; the prompt went to the account contact.
        $order->payments()->create([
            'provider' => Payment::PROVIDER_ABLINER,
            'payment_type' => Payment::TYPE_MOBILE,
            'phone' => null,
            'channel_provider' => 'halotel',
            'idempotency_key' => 'tst_'.Str::random(16),
            'amount' => 1500,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
        ]);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('value="halotel"', false)
            ->assertSee('value="255754123456"', false);
    }

    public function test_order_page_has_no_quick_pay_before_any_mobile_payment(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->orderFor($user);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Quick pay');
    }

    public function test_quick_pay_is_not_offered_for_an_order_in_a_foreign_currency(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->orderFor($user, null, 'KES');

        $order->payments()->create([
            'provider' => Payment::PROVIDER_ABLINER,
            'payment_type' => Payment::TYPE_MOBILE,
            'phone' => '255713111222',
            'channel_provider' => 'mpesa',
            'idempotency_key' => 'tst_'.Str::random(16),
            'amount' => 1500,
            'currency' => 'KES',
            'status' => Payment::STATUS_COMPLETED,
        ]);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Quick pay');
    }

    public function test_quick_pay_submit_starts_a_payment_with_those_details(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->orderFor($user);

        $order->payments()->create([
            'provider' => Payment::PROVIDER_ABLINER,
            'payment_type' => Payment::TYPE_MOBILE,
            'phone' => '255713111222',
            'channel_provider' => 'mpesa',
            'idempotency_key' => 'tst_'.Str::random(16),
            'amount' => 1500,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
        ]);

        // Exactly what the hidden Quick pay form posts.
        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'method' => 'mobile',
                'network' => 'mpesa',
                'phone' => '255713111222',
            ])
            ->assertSessionHasNoErrors();

        $payment = $order->payments()->latest('id')->firstOrFail();

        $this->assertSame(Payment::TYPE_MOBILE, $payment->payment_type);
        $this->assertSame('mpesa', $payment->channel_provider);
        $this->assertSame('255713111222', $payment->phone);

        $this->actingAs($user)
            ->getJson(route('account.orders.payments.status', $order))
            ->assertOk()
            ->assertJsonPath('payment.phone', '255713111222');
    }

    public function test_payment_page_renders_the_change_and_warning_affordances(): void
    {
        $user = User::factory()->customer()->create(['phone' => null]);

        $this->actingAs($user)
            ->get(route('account.orders.payments.show', $this->orderFor($user)))
            ->assertOk()
            ->assertSee('data-phone-change', false)
            ->assertSee('data-phone-warn', false)
            ->assertSee('data-mobile-phone', false);
    }

    /* ------------------------------------------------------------------
     | Coming back to an order whose payment is already in flight.
     |------------------------------------------------------------------ */

    public function test_the_order_page_resumes_a_request_that_is_already_waiting(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->orderFor($user);

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'method' => 'mobile',
                'network' => 'mpesa',
                'phone' => '255713111222',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            // The dialog opens itself onto the waiting panel...
            ->assertSee('data-pay-resume="1"', false)
            // ...checks with the provider automatically every couple of seconds...
            ->assertSee(route('account.orders.payments.refresh', $order), false)
            ->assertSee('every 2 seconds', false)
            // ...and offers a way out of the wrong number without giving up
            // the request that is already on the phone.
            ->assertSee('data-pay-different-number', false)
            ->assertSee('Pay from a different number', false);
    }

    public function test_a_fresh_order_does_not_open_the_payment_dialog_on_its_own(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->orderFor($user);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('data-pay-resume="0"', false)
            ->assertDontSee('data-pay-resume="1"', false);
    }

    public function test_a_request_that_has_expired_does_not_resume_the_dialog(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->orderFor($user);

        $order->payments()->create([
            'provider' => Payment::PROVIDER_ABLINER,
            'payment_type' => Payment::TYPE_MOBILE,
            'phone' => '255713111222',
            'channel_provider' => 'mpesa',
            'idempotency_key' => 'tst_'.Str::random(16),
            'amount' => 1500,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('data-pay-resume="0"', false);
    }

    private function orderFor(User $user, string $orderNumber = null, string $currency = 'TZS'): Order
    {
        $book = Book::factory()->published()->create(['price' => '1500.00']);

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => $orderNumber ?? 'EBS-'.Str::random(8),
            'subtotal' => '1500.00',
            'total' => '1500.00',
            'currency' => $currency,
            'status' => Order::STATUS_PENDING,
        ]);

        $order->items()->create([
            'book_id' => $book->id,
            'quantity' => 1,
            'unit_price' => '1500.00',
            'subtotal' => '1500.00',
        ]);

        return $order;
    }
}