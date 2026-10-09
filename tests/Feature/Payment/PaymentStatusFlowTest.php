<?php

namespace Tests\Feature\Payment;

use App\Models\Book;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The status machine behind the payment dialog.
 *
 * The dialog is a method chooser first and a status panel second, so the two
 * things worth proving here are:
 *
 *  1. What the dialog is told is decided by the server, never by the browser.
 *      Starting a payment, checking a payment, reloading and coming back via
 *      the back button all have to land on the same state.
 *  2. The four states are told apart honestly. Starting a request is NOT
 *     paying: only completed unlocks the books, and a pending request never
 *     produces a second prompt.
 */
class PaymentStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    protected const API_BASE = 'https://abliner.test';

    protected const API_KEY = 'tsl_live_test_key_abcdef123456';

    protected const WEBHOOK_SECRET = 'whsec_test_secret_zyxwvu987654';

    protected const CHECKOUT_URL = 'https://checkout.abliner.test/session/cs_live_9f2a41';

    /** What GET /transactions will report until a test changes it. */
    protected string $providerStatus = 'pending';

    /** Lets a test replace just the deposit response. */
    protected ?\Closure $depositResponse = null;

    /** provider_reference is unique, so each deposit needs its own id. */
    protected int $depositSequence = 0;

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

        $this->fakeProvider();
    }

    /**
     * Fake every provider endpoint this flow touches. The deposit response
     * varies by method because Abliner only returns payment_url for a card.
     */
    protected function fakeProvider(): void
    {
        Http::fake([
            self::API_BASE.'/api/v1/deposits' => function (ClientRequest $request) {
                if ($this->depositResponse !== null) {
                    return call_user_func($this->depositResponse, $request);
                }

                $body = $request->data();
                $method = $body['method'] ?? 'mobile';
                $this->depositSequence++;

                return Http::response([
                    'status' => 'success',
                    'data' => [
                        'id' => 'tx_'.strtoupper($method).'_'.str_pad((string) $this->depositSequence, 4, '0', STR_PAD_LEFT),
                        'type' => 'deposit',
                        'status' => 'pending',
                        'amount' => 15000,
                        'currency' => 'TZS',
                        'method' => $method,
                        'payment_url' => $method === 'card' ? self::CHECKOUT_URL : null,
                        'reference' => 'ref'.strtoupper($method).str_pad((string) $this->depositSequence, 4, '0', STR_PAD_LEFT),
                        'customer_reference' => $body['reference'] ?? null,
                    ],
                ]);
            },

            self::API_BASE.'/api/v1/control-numbers' => Http::response([
                'status' => 'success',
                'data' => [
                    'control_number' => '9912345678',
                    'status' => 'pending',
                    'amount' => 15000,
                    'currency' => 'TZS',
                    'expires_at' => now()->addHours(3)->toIso8601String(),
                ],
            ]),

            self::API_BASE.'/api/v1/transactions*' => function (ClientRequest $request) {
                return Http::response([
                    'status' => 'success',
                    'data' => [[
                        'id' => $request->data()['id'] ?? 'tx_UNKNOWN',
                        'status' => $this->providerStatus,
                        'amount' => 15000,
                        'currency' => 'TZS',
                    ]],
                ]);
            },
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

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => $number,
            'subtotal' => $total,
            'total' => $total,
            'currency' => 'TZS',
            'status' => Order::STATUS_PENDING,
        ]);

        $order->items()->create([
            'book_id' => Book::factory()->create()->id,
            'quantity' => 1,
            'unit_price' => $total,
            'subtotal' => $total,
        ]);

        return $order;
    }

    /** The JSON shape a browser sends. */
    protected function asAjax(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }

    protected function start(User $user, Order $order, array $overrides = [])
    {
        return $this->actingAs($user)->post(
            "/account/orders/{$order->id}/payment",
            array_merge(['method' => 'mobile', 'network' => 'mpesa', 'phone' => '0712345678'], $overrides),
            $this->asAjax(),
        );
    }

    protected function readState(User $user, Order $order)
    {
        return $this->actingAs($user)
            ->getJson("/account/orders/{$order->id}/payment/status")
            ->json();
    }

    /* ------------------------------------------------------------------
     | The chooser itself.
     |------------------------------------------------------------------ */

    public function test_the_dialog_offers_all_three_methods_before_anything_else(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $html = $this->actingAs($user)->get("/account/orders/{$order->id}")->assertOk()->getContent();

        // The method is the first question, so it is the first panel.
        $this->assertStringContainsString('data-pay-step="choose"', $html);

        foreach (array_keys(Payment::METHODS) as $method) {
            $this->assertStringContainsString('name="method_choice" value="'.$method.'"', $html);
            $this->assertStringContainsString('data-pay-step="'.$method.'"', $html);
        }

        // Control numbers are withdrawn: the ClickPesa endpoint that issued
        // them is not serving requests, so the option must not be offered and
        // no step may exist for it.
        $this->assertStringNotContainsString('value="control_number"', $html);
        $this->assertStringNotContainsString('data-pay-step="control_number"', $html);
        $this->assertArrayNotHasKey(Payment::TYPE_CONTROL_NUMBER, Payment::METHODS);

        $this->assertStringContainsString('data-status-url="'.route('account.orders.payments.status', $order).'"', $html);
    }

    public function test_all_four_networks_are_offered(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $html = $this->actingAs($user)->get("/account/orders/{$order->id}")->assertOk()->getContent();

        foreach (array_keys(Payment::NETWORKS) as $network) {
            $this->assertStringContainsString('name="network" value="'.$network.'"', $html);
        }

        $this->assertSame(
            ['airtel_money', 'mpesa', 'mixx_yas', 'halotel'],
            array_keys(Payment::NETWORKS),
            'the four networks the shop has always supported',
        );
    }

    public function test_the_dialog_never_renders_the_api_credentials(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $html = $this->actingAs($user)->get("/account/orders/{$order->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString(self::API_KEY, $html);
        $this->assertStringNotContainsString(self::WEBHOOK_SECRET, $html);
    }

    /* ------------------------------------------------------------------
     | Starting a payment is not paying the order.
     |------------------------------------------------------------------ */

    public function test_starting_a_mobile_payment_reports_pending_not_paid(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $state = $this->start($user, $order)->assertOk()->json();

        $this->assertSame('pending', $state['state']);
        $this->assertFalse($state['paid']);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, Purchase::where('order_id', $order->id)->count());
        $this->assertSame('255712345678', $state['payment']['phone']);
    }

    public function test_starting_a_card_payment_hands_back_the_hosted_page(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $state = $this->start($user, $order, [
            'method' => 'card',
            'network' => null,
            'phone' => null,
        ])->assertOk()->json();

        $this->assertSame(self::CHECKOUT_URL, $state['redirect_url']);
        $this->assertFalse($state['paid']);
        $this->assertNull($state['payment']['phone'], 'a card request never carries a phone number');
    }

    public function test_a_control_number_payment_can_no_longer_be_started(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        // The method is withdrawn, so the server must refuse it even if the
        // request is crafted by hand: the hidden input is not the guard.
        // start() posts as AJAX, so validation answers 422 with errors.method.
        $this->start($user, $order, [
            'method' => 'control_number',
            'network' => 'mpesa',
            'phone' => null,
        ])->assertStatus(422)->assertJsonValidationErrors('method');

        $this->assertSame(0, $order->payments()->count(), 'no payment row may be created');
    }

    public function test_a_retired_control_number_payment_is_still_labelled_and_settles(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        // Rows created before the removal must keep rendering and must still be
        // matched by the webhook, or an already-issued control number would
        // never unlock the books.
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => Payment::PROVIDER_ABLINER,
            'payment_type' => Payment::TYPE_CONTROL_NUMBER,
            'amount' => 15000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PENDING,
            'external_reference' => '9912345678',
            'control_number' => '9912345678',
            // The real flow sets a temporary key and then swaps it for the
            // stable provider one; the column is NOT NULL.
            'idempotency_key' => 'legacy-cn-'.$order->id,
        ]);

        $state = $this->actingAs($user)
            ->get("/account/orders/{$order->id}/payment/status")
            ->assertOk()->json();

        $this->assertSame('Control number', $state['payment']['method_label']);
        $this->assertSame('9912345678', $state['payment']['control_number']);

        $timestamp = now()->timestamp;
        $payload = [
            'id' => 'evt_cn_'.Str::random(8),
            'event' => 'transaction.completed',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'id' => 'tx_CN_LEGACY',
                'method' => 'control_number',
                'amount' => 15000,
                'currency' => 'TZS',
                'status' => 'completed',
                'customer_reference' => $order->order_number,
            ],
        ];
        $raw = json_encode($payload);

        $this->postJson('/webhooks/abliner', $payload, [
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => hash_hmac('sha256', $timestamp.'.'.$raw, self::WEBHOOK_SECRET),
        ])->assertOk();

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
    }

    /* ------------------------------------------------------------------
     | One prompt per attempt.
     |------------------------------------------------------------------ */

    public function test_a_second_click_does_not_send_a_second_prompt(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->start($user, $order)->assertOk();
        $this->start($user, $order)->assertOk();
        $this->start($user, $order)->assertOk();

        $deposits = 0;
        foreach (Http::recorded() as $pair) {
            if ($pair[0]->url() === self::API_BASE.'/api/v1/deposits') {
                $deposits++;
            }
        }

        $this->assertSame(1, $deposits, 'three clicks must not mean three USSD prompts');
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_switching_method_while_pending_does_not_push_the_old_one_again(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->start($user, $order)->assertOk();

        // A different method is a genuinely new intent, so it may start one.
        $state = $this->start($user, $order, [
            'method' => 'card',
            'network' => null,
            'phone' => null,
        ])->assertOk()->json();

        $this->assertSame(self::CHECKOUT_URL, $state['redirect_url']);
        $this->assertSame(2, $order->payments()->count());
    }

    /* ------------------------------------------------------------------
     | Checking the status.
     |------------------------------------------------------------------ */

    public function test_checking_while_pending_says_still_pending(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->start($user, $order);

        $state = $this->actingAs($user)
            ->post("/account/orders/{$order->id}/payment/refresh", [], $this->asAjax())
            ->assertOk()
            ->json();

        $this->assertSame('pending', $state['state']);
        $this->assertFalse($state['paid']);
    }

    public function test_checking_after_the_bank_approves_reports_paid(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->start($user, $order);

        $this->providerStatus = 'completed';

        $state = $this->actingAs($user)
            ->post("/account/orders/{$order->id}/payment/refresh", [], $this->asAjax())
            ->assertOk()
            ->json();

        $this->assertSame('completed', $state['state']);
        $this->assertTrue($state['paid']);
        $this->assertFalse($state['can_start'], 'a paid order can never start another payment');
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);

        // The success panel needs somewhere to send them, and only their own
        // purchase may be linked.
        $this->assertNotNull($state['order']['read_url']);
        $this->assertStringContainsString((string) $order->purchases()->first()->id, $state['order']['read_url']);
        $this->assertSame(1, Purchase::where('order_id', $order->id)->count());
    }

    public function test_a_declined_payment_reads_as_failed_and_can_be_retried(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->start($user, $order);

        $this->providerStatus = 'failed';

        $state = $this->actingAs($user)
            ->post("/account/orders/{$order->id}/payment/refresh", [], $this->asAjax())
            ->assertOk()
            ->json();

        $this->assertSame('failed', $state['state']);
        $this->assertTrue($state['can_start'], 'a failed payment must leave the order payable');
        $this->assertNotEmpty($state['payment']['message']);
        $this->assertStringNotContainsString('provider reports', $state['payment']['message']);
    }

    /**
     * Payment::METHODS maps each method to ['label' => ..., 'hint' => ...].
     * Handing that array to the browser stringified it as "[object Object]"
     * next to the "Method" row on the confirmation panel.
     */
    public function test_the_payment_state_carries_plain_string_labels(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->start($user, $order, ['network' => 'halotel', 'phone' => '0612345678']);

        $state = $this->actingAs($user)
            ->getJson("/account/orders/{$order->id}/payment/status")
            ->assertOk()
            ->json();

        $this->assertIsString($state['payment']['method_label']);
        $this->assertSame(Payment::METHODS[Payment::TYPE_MOBILE]['label'], $state['payment']['method_label']);
        $this->assertSame('Mobile money', $state['payment']['method_label']);

        $this->assertIsString($state['payment']['network_label']);
        $this->assertSame(Payment::NETWORKS['halotel'], $state['payment']['network_label']);
        $this->assertSame('Halotel / HaloPesa', $state['payment']['network_label']);

        // Belt and braces: nothing anywhere in the payload may be an object.
        $this->assertStringNotContainsString(
            '[object Object]',
            json_encode($state),
            'the status payload must not leak a raw array into the UI'
        );
    }

    /**
     * The number is stored on the account, so the confirmation panel masks it.
     * It has to be the canonical form to mask reliably.
     */
    public function test_the_confirmation_number_is_canonical(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->start($user, $order, ['network' => 'mpesa', 'phone' => '0712345678']);

        $this->assertSame('255712345678', $user->fresh()->phone);

        $state = $this->actingAs($user)
            ->getJson("/account/orders/{$order->id}/payment/status")
            ->assertOk()
            ->json();

        $this->assertSame('255712345678', $state['payment']['phone']);
    }

    public function test_a_timed_out_request_reads_as_expired_and_can_be_retried(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->start($user, $order);

        $payment = $order->payments()->latest('id')->first();
        $payment->update(['expires_at' => now()->subMinute(), 'failure_reason' => 'provider reports timeout']);

        $state = $this->readState($user, $order);

        $this->assertSame('expired', $state['state']);
        $this->assertTrue($state['can_start']);
    }

    public function test_checking_a_finished_payment_just_reports_it(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->start($user, $order);

        $this->providerStatus = 'completed';
        $this->actingAs($user)->post("/account/orders/{$order->id}/payment/refresh", [], $this->asAjax());

        // Asking again must not complain, and must not try to pay twice.
        $state = $this->actingAs($user)
            ->post("/account/orders/{$order->id}/payment/refresh", [], $this->asAjax())
            ->assertOk()
            ->json();

        $this->assertSame('completed', $state['state']);
        $this->assertSame(1, Purchase::where('order_id', $order->id)->count());
    }

    /* ------------------------------------------------------------------
     | The state survives a reload or the back button.
     |------------------------------------------------------------------ */

    public function test_reopening_the_dialog_reads_the_server_state_not_a_remembered_one(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->assertSame('idle', $this->readState($user, $order)['state']);

        $this->start($user, $order);
        $this->assertSame('pending', $this->readState($user, $order)['state']);

        $this->providerStatus = 'completed';
        $this->actingAs($user)->post("/account/orders/{$order->id}/payment/refresh", [], $this->asAjax());

        $this->assertSame('completed', $this->readState($user, $order)['state']);
    }

    public function test_an_already_paid_order_can_never_start_another_payment(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->start($user, $order);

        $this->providerStatus = 'completed';
        $this->actingAs($user)->post("/account/orders/{$order->id}/payment/refresh", [], $this->asAjax());

        $state = $this->start($user, $order)->assertOk()->json();

        $this->assertSame('completed', $state['state']);
        $this->assertFalse($state['can_start']);
        $this->assertSame(1, $order->payments()->count(), 'no second payment was created');

        // A paid order does not even render the dialog.
        $this->actingAs($user)
            ->get("/account/orders/{$order->id}")
            ->assertOk()
            ->assertDontSee('id="payModal"', false);
    }

    /* ------------------------------------------------------------------
     | The status endpoint is the customer's own order and nothing else.
     |------------------------------------------------------------------ */

    public function test_someone_else_cannot_read_my_payment_status(): void
    {
        $user = $this->customer();
        $other = $this->customer();
        $order = $this->makeOrder($user);
        $this->start($user, $order);

        $this->actingAs($other)
            ->getJson("/account/orders/{$order->id}/payment/status")
            ->assertNotFound();
    }

    public function test_a_guest_cannot_read_a_payment_status(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->getJson("/account/orders/{$order->id}/payment/status")->assertUnauthorized();
    }

    /* ------------------------------------------------------------------
     | A request that never started is retryable and says why.
     |------------------------------------------------------------------ */

    public function test_a_rejected_request_reports_a_title_and_keeps_the_order_payable(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->depositResponse = fn () => Http::response([
            'code' => 'invalid_phone',
            'message' => 'The mobile number is not a valid Tanzanian number.',
            'field' => 'phone',
        ], 422);

        $response = $this->start($user, $order)->assertStatus(422);
        $body = $response->json();

        $this->assertTrue($body['failed']);
        $this->assertTrue($body['can_start']);
        $this->assertSame('Check your mobile number', $body['title']);
        $this->assertSame('idle', $body['state']);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_a_missing_network_is_reported_as_a_choice_not_a_crash(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $body = $this->start($user, $order, ['network' => null])
            ->assertStatus(422)
            ->json();

        $this->assertSame('Choose your network', $body['title']);
    }

    public function test_every_supported_network_can_actually_start_a_payment(): void
    {
        foreach (array_keys(Payment::NETWORKS) as $network) {
            $user = $this->customer();
            $order = $this->makeOrder($user);

            // A unique number per network: the account phone has to stay unique
            // across accounts, and this is proving the number is normalised,
            // not that the same number can be reused.
            $phone = '0712'.fake()->unique()->numerify('######');

            $state = $this->start($user, $order, ['network' => $network, 'phone' => $phone])
                ->assertOk()
                ->json();

            $this->assertSame('pending', $state['state'], $network.' should start a payment');
            $this->assertSame($network, $state['payment']['network']);
            $this->assertSame(Payment::NETWORKS[$network], $state['payment']['network_label']);

            $body = null;
            foreach (Http::recorded() as $pair) {
                if ($pair[0]->url() === self::API_BASE.'/api/v1/deposits') {
                    $body = $pair[0]->data();
                }
            }

            $this->assertSame('mobile', $body['method'], $network.' still uses the mobile method');
            $this->assertSame(
                '255'.ltrim($phone, '0'),
                $body['phone'],
                $network.' still gets a normalised number',
            );
            // The waiting panel shows the account's canonical number back, so
            // the customer can confirm which phone the prompt went to.
            $this->assertSame(
                $body['phone'],
                $state['payment']['phone'],
                $network.' is shown back to the customer',
            );
        }
    }

    public function test_an_unknown_network_is_refused(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->start($user, $order, ['network' => 'vodacom_momo'])->assertStatus(422);
    }
}