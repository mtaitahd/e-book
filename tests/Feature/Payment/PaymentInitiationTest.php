<?php

namespace Tests\Feature\Payment;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Facades\Http;

class PaymentInitiationTest extends PaymentTestCase
{
    public function test_guest_is_redirected_from_payment_page(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->from(route('cart.show'))->get(route('account.orders.payments.show', $order))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('auth_intended', route('account.orders.payments.show', $order));

        $this->from(route('cart.show'))->post(route('account.orders.payments.store', $order))
            ->assertRedirect(route('cart.show'));
    }

    public function test_customer_cannot_pay_another_users_order(): void
    {
        $owner = $this->customer();
        $order = $this->makeOrder($owner);
        $stranger = $this->customer();

        $this->actingAs($stranger)
            ->get(route('account.orders.payments.show', $order))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'airtel_money',
                'phone' => '0754123456',
            ])
            ->assertNotFound();
    }

    public function test_not_configured_shows_friendly_state_and_blocks_initiation(): void
    {
        config()->set('services.snippe.api_key', null);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->actingAs($user)
            ->get(route('account.orders.payments.show', $order))
            ->assertOk()
            ->assertSee('Payments are not enabled yet');

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'mpesa',
                'phone' => '0754123456',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_starting_payment_calls_snippe_with_correct_payload(): void
    {
        Http::fake([
            'https://api.snippe.test/v1/payments' => Http::response([
                'success' => true,
                'data' => [
                    'reference' => 'SNIP-REF-001',
                    'external_reference' => 'EXT-001',
                    'status' => 'pending',
                    'amount' => 1500,
                    'currency' => 'TZS',
                    'expires_at' => now()->addHour()->toISOString(),
                ],
            ], 200),
        ]);

        $user = $this->customer();
        $order = $this->makeOrder($user, '1500.00');

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'airtel_money',
                'phone' => '0754123456',
                'first_name' => 'Hassan',
                'last_name' => 'Ali',
            ])
            ->assertRedirect(route('account.orders.payments.show', $order))
            ->assertSessionHas('success');

        Http::assertSent(function ($request) use ($order, $user) {
            if ($request->url() !== 'https://api.snippe.test/v1/payments') {
                return false;
            }

            $data = $request->data();

            $this->assertSame('mobile', $data['payment_type']);
            $this->assertSame(1500, $data['details']['amount']);
            $this->assertSame('TZS', $data['details']['currency']);
            $this->assertSame('255754123456', $data['phone_number']);
            $this->assertSame('Hassan', $data['customer']['firstname']);
            $this->assertSame('Ali', $data['customer']['lastname']);
            $this->assertSame($user->email, $data['customer']['email']);
            $this->assertSame($order->id, $data['metadata']['order_id']);
            $this->assertArrayNotHasKey('network', $data);
            $this->assertArrayNotHasKey('operator', $data);
            $this->assertArrayNotHasKey('chain_provider', $data);

            return true;
        });

        $payment = $order->payments()->first();
        $this->assertNotNull($payment);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame('SNIP-REF-001', $payment->provider_reference);
        $this->assertSame(1500, $payment->amount);
        $this->assertSame('TZS', $payment->currency);
        $this->assertSame('airtel_money', $payment->channel_provider);
        $this->assertSame('ebs-pay-'.$payment->id, $payment->idempotency_key);
        $this->assertSame('255754123456', $user->fresh()->phone);
        $this->assertTrue($order->fresh()->isPending());
    }

    public function test_transient_failure_retries_with_same_idempotency_key(): void
    {
        Http::fake([
            'https://api.snippe.test/v1/payments' => Http::sequence()
                ->push(['success' => false, 'error' => 'temporary'], 500)
                ->push([
                    'success' => true,
                    'data' => [
                        'reference' => 'SNIP-REF-002',
                        'status' => 'pending',
                        'amount' => 1500,
                        'currency' => 'TZS',
                    ],
                ], 200),
        ]);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'mpesa',
                'phone' => '0754123456',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSentCount(2);

        $requests = Http::recorded();
        $this->assertCount(2, $requests);
        $firstKey = implode(',', $requests[0][0]->header('Idempotency-Key'));
        $secondKey = implode(',', $requests[1][0]->header('Idempotency-Key'));
        $this->assertSame($firstKey, $secondKey);
        $this->assertMatchesRegularExpression('/^ebs-pay-\d+$/', $firstKey);

        $payment = $order->payments()->first();
        $this->assertSame('SNIP-REF-002', $payment->provider_reference);
        $this->assertNull($payment->failure_reason);
    }

    public function test_api_error_is_stored_and_payment_left_pending(): void
    {
        Http::fake([
            'https://api.snippe.test/v1/payments' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'halotel',
                'phone' => '0754123456',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $payment = $order->payments()->first();
        $this->assertNotNull($payment);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertStringStartsWith('request_failed:', $payment->failure_reason);
        $this->assertTrue($order->fresh()->isPending());
    }

    public function test_account_without_a_first_name_still_sends_usable_identity_fields(): void
    {
        // A storefront account created through the sign-up modal has no
        // first/last name, and the provider rejects a blank identity.
        Http::fake([
            'https://api.snippe.test/v1/payments' => Http::response([
                'data' => ['reference' => 'SNIP-REF-003', 'status' => 'pending', 'amount' => 1500, 'currency' => 'TZS'],
            ], 200),
        ]);

        $user = $this->customer(['first_name' => null, 'last_name' => null, 'name' => 'Baraka Baraka']);
        $order = $this->makeOrder($user);

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'airtel_money',
                'phone' => '0754123456',
            ])
            ->assertSessionHasNoErrors();

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.snippe.test/v1/payments') {
                return false;
            }

            $data = $request->data();

            $this->assertSame('Baraka', $data['customer']['firstname']);
            $this->assertSame('Baraka', $data['customer']['lastname']);

            return true;
        });
    }

    public function test_a_rejected_request_reports_why_rather_than_a_blank_message(): void
    {
        Http::fake([
            'https://api.snippe.test/v1/payments' => Http::response([
                'code' => 'PAY_014',
                'message' => 'phone_number is not a valid mobile money number',
            ], 422),
        ]);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'airtel_money',
                'phone' => '0754123456',
            ])
            ->assertSessionHas('error');

        // The provider's own reason is what makes a 422 actionable, so it has to
        // survive into both the flashed message and the stored failure reason.
        $message = (string) session('error');
        $this->assertStringContainsString('could not be processed', $message);
        $this->assertStringContainsString('PAY_014', $message);
        $this->assertStringContainsString('not a valid mobile money number', $message);

        $payment = $order->payments()->first();
        $this->assertStringContainsString('PAY_014', (string) $payment->failure_reason);
    }

    public function test_payment_feedback_is_rendered_as_a_sweetalert_block(): void
    {
        // The account layout used to print a plain .flash div, so a customer
        // never got the SweetAlert feedback the storefront gives them.
        Http::fake([
            'https://api.snippe.test/v1/payments' => Http::response(['error' => 'rejected'], 422),
        ]);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $response = $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'airtel_money',
                'phone' => '0754123456',
            ]);

        $response->assertRedirect();

        $this->followingRedirects()
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'airtel_money',
                'phone' => '0754123456',
            ])
            ->assertOk()
            ->assertSee('data-swal-flash', false)
            ->assertSee('data-swal-type="error"', false)
            ->assertSee('assets/shared/swal-flash.js', false);
    }

    public function test_invalid_phone_is_rejected_without_creating_payment(): void
    {
        Http::fake();

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'mixx_yas',
                'phone' => '123',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invalid_network_value_is_rejected(): void
    {
        Http::fake();

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $response = $this->from(route('account.orders.payments.show', $order))
            ->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'visa',
                'phone' => '0754123456',
            ]);

        $response->assertSessionHasErrors('network');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_below_minimum_amount_is_blocked(): void
    {
        Http::fake();

        $user = $this->customer();
        $order = $this->makeOrder($user, '450.00');

        $this->actingAs($user)
            ->post(route('account.orders.payments.store', $order), [
                'network' => 'airtel_money',
                'phone' => '0754123456',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_retry_reuses_same_pending_payment_row(): void
    {
        Http::fake([
            'https://api.snippe.test/v1/payments' => Http::response([
                'success' => true,
                'data' => [
                    'reference' => 'SNIP-REF-003',
                    'status' => 'pending',
                    'amount' => 1500,
                    'currency' => 'TZS',
                ],
            ], 200),
        ]);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($user)
                ->post(route('account.orders.payments.store', $order), [
                    'network' => 'airtel_money',
                    'phone' => '0754123456',
                ])
                ->assertRedirect()
                ->assertSessionHas('success');
        }

        $this->assertSame(1, $order->payments()->count());

        Http::assertSentCount(2);
        $requests = Http::recorded();
        $firstKey = implode(',', $requests[0][0]->header('Idempotency-Key'));
        $secondKey = implode(',', $requests[1][0]->header('Idempotency-Key'));
        $this->assertSame($firstKey, $secondKey);
    }

    public function test_remaining_minimum_display(): void
    {
        $this->assertSame(500, \App\Services\Snippe\SnippePaymentService::MINIMUM_AMOUNT);
        $this->assertSame('500 TZS', Money::formatWhole(500));
    }
}