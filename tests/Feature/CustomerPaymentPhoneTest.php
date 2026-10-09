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
 * The Mobile Money number a customer pays with.
 *
 * Registration captures the number so the payment page can fill it in for them,
 * and the number stays editable so a customer can always pay from a different
 * phone without editing their account first. The pay flow accepts ANY valid
 * number, even one another account has claimed: the prompt goes to the number
 * typed, while the account contact is only updated when that number is not
 * already registered to someone else.
 */
class CustomerPaymentPhoneTest extends TestCase
{
    use RefreshDatabase;

    protected const API_BASE = 'https://abliner.test';

    protected const DEPOSIT_URL = self::API_BASE.'/api/v1/deposits';

    protected function setUp(): void
    {
        parent::setUp();

        // The payment form is only rendered once the provider is switched on and
        // has an API key, so stand one up for these tests.
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

    public function test_payment_page_fills_in_the_number_from_the_account(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);

        $this->actingAs($user)
            ->get(route('account.orders.payments.show', $this->pendingOrder($user)))
            ->assertOk()
            ->assertSee('value="255754123456"', false)
            ->assertSee('Filled in automatically from your account', false);
    }

    public function test_payment_page_prompts_for_a_number_when_the_account_has_none(): void
    {
        $user = User::factory()->customer()->create(['phone' => null]);

        $this->actingAs($user)
            ->get(route('account.orders.payments.show', $this->pendingOrder($user)))
            ->assertOk()
            ->assertSee('Add one when you create your account', false);
    }

    public function test_paying_with_a_different_number_saves_it_for_next_time(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->pendingOrder($user);

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'method' => 'mobile',
                'network' => array_key_first(Payment::NETWORKS),
                'phone' => '0713111222',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('255713111222', $user->fresh()->phone);
    }

    public function test_paying_uses_a_number_another_account_holds_without_claiming_it(): void
    {
        User::factory()->create(['phone' => '255713111222']);
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->pendingOrder($user);

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'method' => 'mobile',
                'network' => array_key_first(Payment::NETWORKS),
                'phone' => '0713111222',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('255754123456', $user->fresh()->phone);

        // The push still went out to the number the customer typed, and the
        // waiting panel reports exactly that.
        $this->assertSame('255713111222', $order->payments()->latest('id')->firstOrFail()->phone);

        $this->actingAs($user)
            ->getJson(route('account.orders.payments.status', $order))
            ->assertOk()
            ->assertJsonPath('payment.phone', '255713111222');
    }

    public function test_paying_again_with_the_own_number_is_allowed(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->pendingOrder($user);

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'method' => 'mobile',
                'network' => array_key_first(Payment::NETWORKS),
                'phone' => '0754123456',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('255754123456', $user->fresh()->phone);
    }

    public function test_paying_rejects_a_number_it_cannot_parse(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->pendingOrder($user);

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'method' => 'mobile',
                'network' => array_key_first(Payment::NETWORKS),
                'phone' => '12345',
            ])
            ->assertSessionHas('error');

        $this->assertSame('255754123456', $user->fresh()->phone);
        $this->assertDatabaseCount('payments', 0);
    }

    /* ------------------------------------------------------------------
     | Switching lines while a request is already on a phone.
     |------------------------------------------------------------------ */

    public function test_a_different_number_while_pending_starts_its_own_request(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->pendingOrder($user);

        $this->payFrom($order, '0713111222');
        $this->payFrom($order, '0766111222');

        // A request is addressed to one number, so the new line gets its own
        // row - and its own push. Re-using the first row would have left the
        // customer approving a prompt on the number they just walked away from.
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame('255766111222', $order->payments()->latest('id')->firstOrFail()->phone);
        $this->assertSame(2, $this->depositCount());

        // The dialog now reports the number the newest request went to.
        $this->actingAs($user)
            ->getJson(route('account.orders.payments.status', $order))
            ->assertOk()
            ->assertJsonPath('payment.phone', '255766111222');
    }

    public function test_the_same_number_while_pending_does_not_send_a_second_prompt(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->pendingOrder($user);

        $this->payFrom($order, '0713111222');
        $this->payFrom($order, '0713111222');

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, $this->depositCount());
    }

    public function test_the_waiting_page_checks_automatically_and_offers_another_number(): void
    {
        $user = User::factory()->customer()->create(['phone' => '255754123456']);
        $order = $this->pendingOrder($user);

        $this->payFrom($order, '0713111222');

        $this->actingAs($user)
            ->get(route('account.orders.payments.show', $order))
            ->assertOk()
            // The automatic check: both endpoints it alternates between, and
            // the cadence it promises the customer.
            ->assertSee('data-payment-wait', false)
            ->assertSee(route('account.orders.payments.status', $order), false)
            ->assertSee(route('account.orders.payments.refresh', $order), false)
            ->assertSee('Checking for your payment automatically every 2 seconds', false)
            // Which line the request is on, so a wrong number is obvious.
            ->assertSee('0713111222', false)
            // The way out of the wrong line, plus the manual fallback.
            ->assertSee('data-pay-other-number', false)
            ->assertSee('Pay with a different phone number', false)
            ->assertSee('Check payment status', false);
    }

    /** Start a mobile money request exactly as the form does. */
    private function payFrom(Order $order, string $phone): void
    {
        $this->actingAs($order->user)
            ->post(route('account.orders.payments.store', $order), [
                'method' => 'mobile',
                'network' => 'mpesa',
                'phone' => $phone,
            ])
            ->assertSessionHasNoErrors();
    }

    /** How many USSD pushes were actually sent to the provider. */
    private function depositCount(): int
    {
        $count = 0;

        foreach (Http::recorded() as $pair) {
            if ($pair[0]->url() === self::DEPOSIT_URL) {
                $count++;
            }
        }

        return $count;
    }

    private function pendingOrder(User $user): Order
    {
        $book = Book::factory()->published()->create(['price' => '1500.00']);

        $this->actingAs($user)->post(route('cart.add'), ['book_id' => $book->id]);
        $this->actingAs($user)->post(route('checkout.store'));

        return $user->orders()->latest('id')->firstOrFail();
    }
}