<?php

namespace Tests\Feature\Payment;

use App\Models\Book;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Abliner\AblinerPaymentService;
use App\Services\Abliner\AblinerSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The mobile money USSD push flow, asserted against the wire.
 *
 * This suite deliberately checks the ACTUAL outbound request rather than only
 * the database side effects, because the defect that started all of this was
 * invisible locally: the form posted no `method`, Laravel rejected the request
 * before any HTTP call was ever made, and no assertion on local state could
 * have caught it.
 *
 * Reference flow under test:
 *   customer enters 0712345678 -> backend normalises to 255712345678 ->
 *   POST /api/v1/deposits with method=mobile -> provider returns pending ->
 *   customer approves on their phone -> transaction.completed webhook ->
 *   order PAID + entitlement granted.
 */
class MobileMoneyDepositTest extends TestCase
{
    use RefreshDatabase;

    protected const API_BASE = 'https://abliner.test';

    protected const API_KEY = 'tsl_live_test_key_abcdef123456';

    protected const WEBHOOK_SECRET = 'whsec_test_secret_zyxwvu987654';

    protected const DEPOSIT_URL = self::API_BASE.'/api/v1/deposits';

    protected string $depositTransactionId = 'tx_01HZABCDEF';

    /**
     * A single persistent /deposits stub whose reply the test dictates.
     *
     * One closure-based stub for the whole class, rather than a fresh
     * Http::fake() per assertion: Laravel PUSHES stub callbacks and the first
     * matching one wins, so re-faking the same URL inside a single test is
     * silently ignored. Reading the reply from a property is what makes each
     * successive request genuinely observable.
     *
     * @var array{status: int, body: array|\Closure}
     */
    protected array $nextDepositReply = [];

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

        $this->depositTransactionId = 'tx_01HZABCDEF';
        // Default: the documented successful push.
        $this->nextDepositReply = [
            'status' => 200,
            'body' => fn ($request) => $this->pendingPush($request),
        ];

        Http::fake([
            self::DEPOSIT_URL => function ($request) {
                $body = $this->nextDepositReply['body'];

                if ($body instanceof \Closure) {
                    $body = $body($request);
                }

                return Http::response($body, $this->nextDepositReply['status']);
            },
            // The status check looks the transaction up by id. Without a stub
            // for it the lookup would leave the test process and fail on DNS,
            // which says nothing about the behaviour under test.
            '*'.self::API_BASE.'/api/v1/transactions*' => function ($request) {
                return Http::response([
                    'status' => 'success',
                    'data' => [[
                        'id' => $this->depositTransactionId,
                        'type' => 'deposit',
                        'flow' => 'push',
                        'status' => 'pending',
                        'amount' => 15000,
                        'currency' => 'TZS',
                        'method' => 'mobile',
                        'reference' => 'pp3k8fa91c2b7d',
                    ]],
                ], 200);
            },
        ]);
    }

    /**
     * Queue the reply the next /deposits call will receive.
     *
     * @param  array|null  $body  null for the documented successful push, which
     *                            echoes back whatever WE sent as `reference`
     *                            exactly as the real API does.
     */
    protected function replyWith(int $status = 200, ?array $body = null, string $transactionId = 'tx_01HZABCDEF'): void
    {
        $this->depositTransactionId = $transactionId;
        $this->nextDepositReply = [
            'status' => $status,
            'body' => $body ?? fn ($request) => $this->pendingPush($request),
        ];
    }

    /**
     * The successful initiation response documented for a mobile push:
     * payment_url is null and the status is still pending.
     *
     * @param  \Illuminate\Http\Client\Request  $request
     */
    protected function pendingPush($request): array
    {
        return [
            'status' => 'success',
            'data' => [
                'id' => $this->depositTransactionId,
                'type' => 'deposit',
                'flow' => 'push',
                'status' => 'pending',
                'amount' => $request['amount'],
                'currency' => $request['currency'],
                'method' => $request['method'],
                'payment_url' => null,
                'reference' => 'pp3k8fa91c2b7d',
                'customer_reference' => $request['reference'],
                'message' => 'Mobile money push sent. Ask the payer to approve the prompt on their phone.',
            ],
        ];
    }

    protected function customer(array $attributes = []): User
    {
        return User::factory()->customer()->create(array_merge([
            'phone' => '2557'.fake()->unique()->numerify('########'),
        ], $attributes));
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

    /**
     * Post the mobile money form exactly as the pay modal submits it.
     */
    protected function pay(User $user, Order $order, array $overrides = [])
    {
        return $this->actingAs($user)->post(
            "/account/orders/{$order->id}/payment",
            array_merge([
                'method' => 'mobile',
                'network' => 'mpesa',
                'phone' => '0712345678',
                'first_name' => 'Hassan',
                'last_name' => 'Ali',
            ], $overrides)
        );
    }

    /**
     * @return array{0: array, 1: int, 2: string}
     */
    protected function signed(array $payload, ?int $timestamp = null, ?string $secret = null): array
    {
        $timestamp ??= now()->timestamp;
        $raw = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp.'.'.$raw, $secret ?? self::WEBHOOK_SECRET);

        return [$payload, $timestamp, $signature];
    }

    /**
     * POST the signed callback to our own webhook route.
     */
    protected function deliverWebhook(array $payload)
    {
        [$body, $timestamp, $signature] = $this->signed($payload);

        return $this->postJson('/webhooks/abliner', $body, [
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => $signature,
        ]);
    }

    protected function completedPayload(string $orderNumber, string $transactionId, int $amount = 15000, string $currency = 'TZS'): array
    {
        return [
            'id' => 'evt_'.Str::random(12),
            'event' => 'transaction.completed',
            'created_at' => now()->toIso8601String(),
            'data' => [
                'id' => $transactionId,
                'type' => 'deposit',
                'method' => 'mobile',
                'amount' => $amount,
                'amount_cents' => $amount * 100,
                'currency' => $currency,
                'status' => 'completed',
                'reference' => 'ABL8F2K1Q9',
                'customer_reference' => $orderNumber,
                'provider_reference' => 'PRV-77812345',
                'completed_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Only the calls that actually went to /api/v1/deposits.
     *
     * @return list<\Illuminate\Http\Client\Request>
     */
    protected function depositCalls(): array
    {
        return Http::recorded()
            ->filter(fn ($pair) => $pair[0]->url() === self::DEPOSIT_URL)
            ->map(fn ($pair) => $pair[0])
            ->values()
            ->all();
    }

    /* ------------------------------------------------------------------
     | The defect: the form never sent `method`.
     |------------------------------------------------------------------ */

    public function test_the_pay_modal_posts_the_mobile_money_method(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $response = $this->actingAs($user)->get("/account/orders/{$order->id}");

        $response->assertOk();
        // The hidden field is what makes the request legal against
        // POST /api/v1/deposits, whose `method` is mandatory.
        $response->assertSee('name="method" value="mobile"', false);
    }

    public function test_a_payment_without_a_method_is_still_rejected(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $response = $this->actingAs($user)->post("/account/orders/{$order->id}/payment", [
            'network' => 'mpesa',
            'phone' => '0712345678',
        ]);

        // Proves the field is genuinely required by the deposit API contract,
        // i.e. it could not simply have been made optional.
        $response->assertSessionHasErrors('method');
        $this->assertSame([], $this->depositCalls(), 'nothing may be sent without a method');
    }

    /* ------------------------------------------------------------------
     | The outbound request, asserted on the wire.
     |------------------------------------------------------------------ */

    public function test_it_posts_the_documented_mobile_deposit_payload(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->pay($user, $order)
            ->assertRedirect("/account/orders/{$order->id}/payment");

        $this->assertCount(1, $this->depositCalls());

        $request = $this->depositCalls()[0];
        $this->assertSame(self::DEPOSIT_URL, $request->url());
        $this->assertSame('POST', $request->method());

        $body = $request->data();

        // The six documented fields, with whole-TZS amount.
        $this->assertSame(15000, $body['amount'], 'amount must be whole TZS, not cents');
        $this->assertSame('mobile', $body['method']);
        $this->assertSame('TZS', $body['currency']);
        $this->assertSame('255712345678', $body['phone'], 'local format must be normalised');
        // The reference prefixes the order number with the payment id so each
        // attempt gets its own provider reference (Abliner 409s reuses).
        $this->assertSame(
            $order->order_number.'-'.$order->payments()->latest('id')->value('id'),
            $body['reference']
        );
        $this->assertSame('https://shop.test/webhooks/abliner', $body['callback_url']);

        // Credentials stay server-side and are never in the body.
        $this->assertSame('Bearer '.self::API_KEY, $request->header('Authorization')[0]);
        $this->assertArrayNotHasKey('api_key', $body);
        $this->assertStringNotContainsString(self::API_KEY, $request->body());
    }

    public function test_the_request_is_signed_and_carries_an_idempotency_key(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->pay($user, $order);

        $request = $this->depositCalls()[0];

        $timestamp = $request->header('x-abliner-timestamp')[0] ?? null;
        $signature = $request->header('x-abliner-signature')[0] ?? null;

        $this->assertNotNull($timestamp, 'outbound requests must be timestamped');
        $this->assertNotNull($signature, 'outbound requests must be signed');

        // Recomputed independently, over the exact bytes on the wire.
        $this->assertSame(
            hash_hmac('sha256', $timestamp.'.'.$request->body(), self::WEBHOOK_SECRET),
            $signature
        );

        $idempotencyKey = $request->header('Idempotency-Key')[0] ?? null;
        $this->assertNotNull($idempotencyKey, 'every deposit needs an Idempotency-Key');
        $this->assertMatchesRegularExpression('/^ebs-pay-\d+$/', $idempotencyKey);
    }

    public function test_a_retried_attempt_reuses_the_same_payment_instead_of_pushing_again(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->pay($user, $order);
        $first = $order->payments()->latest('id')->first()->idempotency_key;

        // A customer hammering "Pay" must not trigger a second USSD push: one
        // prompt per attempt is the whole point of the waiting state.
        $this->pay($user, $order);
        $second = $order->payments()->latest('id')->first()->idempotency_key;

        $this->assertSame($first, $second);
        $this->assertSame(1, $order->payments()->count(), 'a retry reuses the payment row');

        $keys = array_map(
            fn ($request) => $request->header('Idempotency-Key')[0],
            $this->depositCalls()
        );
        $this->assertCount(1, $keys, 'the provider was called once, not once per click');
    }

    public function test_the_amount_is_whole_shillings_not_cents(): void
    {
        $user = $this->customer();
        // 15,000.00 is stored internally as 1,500,000 cents.
        $order = $this->makeOrder($user, '15000.00');

        $this->pay($user, $order);

        $this->assertSame(15000, $this->depositCalls()[0]['amount']);
        $this->assertSame(15000, (int) $order->payments()->latest('id')->first()->amount);
    }

    /**
     * Every local format a customer might type must reach the provider in the
     * one canonical international form.
     */
    #[DataProvider('localPhoneNumbers')]
    public function test_local_numbers_are_normalised_to_the_international_form(string $entered, string $expected): void
    {
        $user = $this->customer(['phone' => null]);
        $order = $this->makeOrder($user);

        $this->pay($user, $order, ['phone' => $entered]);

        $this->assertSame($expected, $this->depositCalls()[0]['phone']);
    }

    public static function localPhoneNumbers(): array
    {
        return [
            'leading zero' => ['0712345678', '255712345678'],
            'bare national' => ['712345678', '255712345678'],
            'already international' => ['255712345678', '255712345678'],
            'international with plus' => ['+255712345678', '255712345678'],
            'spaces and dashes' => ['0712 345 678', '255712345678'],
        ];
    }

    public function test_an_invalid_number_never_reaches_the_provider(): void
    {
        $user = $this->customer(['phone' => null]);
        $order = $this->makeOrder($user);

        // Frontend validation bypassed entirely.
        $response = $this->pay($user, $order, ['phone' => '12345']);

        $response->assertSessionHas('error');
        $this->assertSame([], $this->depositCalls());
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_an_amount_below_the_provider_minimum_is_refused(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user, '100.00');

        $this->pay($user, $order)->assertSessionHas('error');

        $this->assertSame([], $this->depositCalls());
    }

    public function test_an_amount_above_the_provider_maximum_is_refused(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user, '9000000.00');

        $this->pay($user, $order)->assertSessionHas('error');

        $this->assertSame([], $this->depositCalls());
    }

    /* ------------------------------------------------------------------
     | What the local state looks like straight after the push.
     |------------------------------------------------------------------ */

    public function test_a_pending_push_does_not_mark_the_order_paid(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->pay($user, $order);

        $payment = $order->payments()->latest('id')->first();

        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(Payment::TYPE_MOBILE, $payment->payment_type);
        $this->assertSame('tx_01HZABCDEF', $payment->provider_reference);
        $this->assertSame($order->order_number.'-'.$payment->id, $payment->customer_reference);
        $this->assertSame('mpesa', $payment->channel_provider);

        // The order stays unpaid and nothing is unlocked until the callback.
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertSame(0, Purchase::where('order_id', $order->id)->count());
    }

    public function test_the_customer_is_not_redirected_off_site_for_mobile_money(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $response = $this->pay($user, $order);

        // payment_url is null for a push, so we stay on our own site and tell
        // the customer to approve the prompt.
        $response->assertRedirect("/account/orders/{$order->id}/payment");
        $response->assertSessionHas('success');
        $this->assertNull($order->payments()->latest('id')->first()->payment_url);
    }

    public function test_the_confirmation_page_tells_the_customer_to_check_their_phone(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->pay($user, $order);

        $this->actingAs($user)
            ->get("/account/orders/{$order->id}/payment")
            ->assertOk()
            ->assertSee('Check payment status');
    }

    public function test_the_api_key_is_never_rendered_into_any_page(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->pay($user, $order);

        foreach (["/account/orders/{$order->id}", "/account/orders/{$order->id}/payment"] as $url) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertDontSee(self::API_KEY, false)
                ->assertDontSee(self::WEBHOOK_SECRET, false);
        }
    }

    /* ------------------------------------------------------------------
     | Provider errors, surfaced honestly.
     |------------------------------------------------------------------ */

    public function test_an_invalid_phone_from_the_provider_is_reported_specifically(): void
    {
        $this->replyWith(422, [
            'code' => 'invalid_phone',
            'message' => 'The mobile number is not a valid Tanzanian number.',
            'field' => 'phone',
        ]);

        $user = $this->customer(['phone' => null]);
        $order = $this->makeOrder($user);

        $response = $this->pay($user, $order);

        $response->assertSessionHas('error');
        $response->assertSessionHas('alert_title', 'Check your mobile number');
        $this->assertStringContainsString('mobile number', (string) session('error'));

        // The failed attempt is recorded for support, never silently dropped.
        $this->assertStringContainsString(
            'invalid_phone',
            (string) $order->payments()->latest('id')->first()->failure_reason
        );
    }

    /**
     * Every status the API documents must leave the customer with something
     * actionable and must never silently succeed.
     *
     * Each case runs as its own test rather than inside a loop: a loop shares
     * one session and one users table, so the second iteration would trip the
     * uniqueness rule on a phone number the first iteration already claimed
     * and would be measuring the wrong thing entirely.
     */
    #[DataProvider('documentedFailureModes')]
    public function test_a_documented_failure_always_produces_a_message(int $status, string $code): void
    {
        $this->replyWith($status, ['code' => $code, 'message' => 'upstream said no']);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->pay($user, $order);

        $this->assertNotEmpty(
            session('error'),
            "HTTP {$status} / {$code} produced no customer-facing message"
        );

        // A dedicated heading, so the customer is not left with the shared
        // flash partial's generic "Please check your details".
        $this->assertNotEmpty(
            session('alert_title'),
            "HTTP {$status} / {$code} produced no specific heading"
        );

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, Purchase::where('order_id', $order->id)->count());

        // The attempt is on record, tagged with the provider's own code.
        $payment = $order->payments()->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertStringContainsString($code, (string) $payment->failure_reason);
    }

    public static function documentedFailureModes(): array
    {
        return [
            'bad request' => [400, 'invalid_json'],
            'expired or wrong key' => [401, 'unauthorized'],
            'store out of balance' => [402, 'insufficient_funds'],
            'store suspended' => [403, 'account_suspended'],
            'unknown route' => [404, 'not_found'],
            'outside the limits' => [422, 'amount_out_of_range'],
            'provider fault' => [500, 'internal_error'],
            'upstream down' => [502, 'provider_error'],
            'rail switched off' => [503, 'gateway_not_configured'],
        ];
    }

    public function test_a_retryable_5xx_is_retried_before_we_give_up(): void
    {
        // 502 is documented as retryable, so the client must try again rather
        // than failing a paying customer on a single blip.
        $this->replyWith(502, [
            'code' => 'provider_error',
            'message' => 'upstream is having a moment',
        ]);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->pay($user, $order);

        $calls = $this->depositCalls();

        $this->assertGreaterThan(1, count($calls), 'a retryable 502 must be retried');
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertNotEmpty(session('error'));
    }

    /**
     * A create whose response never arrived leaves the row pending with no
     * provider reference - the state the automatic status check keeps finding.
     * The check must re-issue the identical request (same idempotency key) so
     * the provider replays the original outcome and the row can settle, rather
     * than telling the customer there is nothing to look at.
     */
    public function test_a_status_check_recovers_a_request_that_lost_its_response(): void
    {
        $this->replyWith(502, [
            'code' => 'provider_error',
            'message' => 'upstream is having a moment',
        ]);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->pay($user, $order);

        $payment = $order->payments()->latest('id')->first();

        $this->assertTrue($payment->isPending());
        $this->assertNull($payment->provider_reference, 'the response was lost, so no reference exists yet');

        // The next call gets through and hands back the push transaction.
        $this->replyWith();

        $this->actingAs($user)
            ->postJson("/account/orders/{$order->id}/payment/refresh")
            ->assertOk()
            ->assertJsonPath('state', 'pending');

        $payment->refresh();

        $this->assertNotNull($payment->provider_reference, 'the replay returned the transaction');
        $this->assertSame(1, $order->payments()->count(), 'the recovery must not open a second payment');

        $keys = array_map(
            fn ($request) => $request->header('Idempotency-Key')[0],
            $this->depositCalls()
        );

        $this->assertSame(
            $keys[0],
            $keys[count($keys) - 1],
            'the recovery reuses the row\'s idempotency key so no second push is sent'
        );
    }

    /**
     * 409 means the provider is still working on the original request. The
     * waiting screen must stay open on that answer instead of reporting a
     * failure the provider has not confirmed.
     */
    public function test_a_request_still_being_processed_keeps_the_wait_screen_open(): void
    {
        $this->replyWith(502, [
            'code' => 'provider_error',
            'message' => 'upstream is having a moment',
        ]);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->pay($user, $order);

        $this->replyWith(409, [
            'code' => 'request_in_progress',
            'message' => 'A request with this Idempotency-Key is still being processed. Retry in a few seconds.',
            'retryable' => false,
        ]);

        $this->actingAs($user)
            ->postJson("/account/orders/{$order->id}/payment/refresh")
            ->assertOk()
            ->assertJsonPath('state', 'pending')
            ->assertJsonPath('paid', false);

        $this->assertTrue($order->payments()->latest('id')->first()->isPending());
    }

    /**
     * A create that answers 409 request_in_progress (the provider is still
     * chewing on the original request) must NOT be reported to the customer as
     * a failure: keep the waiting screen open so the status checks can settle
     * it, the same decision refresh() already makes.
     */
    public function test_starting_again_while_the_provider_is_processing_keeps_the_wait_screen_open(): void
    {
        $this->replyWith(409, [
            'code' => 'request_in_progress',
            'message' => 'A request with this Idempotency-Key is still being processed. Retry in a few seconds.',
            'retryable' => false,
        ]);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->actingAs($user)
            ->postJson("/account/orders/{$order->id}/payment", [
                'method' => 'mobile',
                'network' => 'mpesa',
                'phone' => '0712345678',
                'first_name' => 'Hassan',
                'last_name' => 'Ali',
            ])
            ->assertOk()
            ->assertJsonPath('state', 'pending')
            ->assertJsonPath('paid', false);

        $payment = $order->payments()->latest('id')->first();

        $this->assertTrue($payment->isPending());
        $this->assertNull($payment->provider_reference);
    }

    public function test_no_payment_is_started_when_the_provider_is_not_configured(): void
    {
        config()->set('services.abliner.api_key', null);

        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->pay($user, $order)->assertSessionHas('error');

        $this->assertSame([], $this->depositCalls());
    }

    /* ------------------------------------------------------------------
     | The webhook.
     |------------------------------------------------------------------ */

    public function test_a_completed_callback_marks_the_order_paid_and_unlocks_the_book(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);

        $this->pay($user, $order);

        $bookId = $order->items()->first()->book_id;

        $this->deliverWebhook($this->completedPayload($order->order_number, 'tx_01HZABCDEF'))
            ->assertOk();

        $order->refresh();
        $payment = $order->payments()->latest('id')->first()->fresh();

        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status);
        $this->assertNotNull($payment->paid_at);

        // The e-book is unlocked.
        $this->assertSame(1, Purchase::where('order_id', $order->id)->count());
        $this->assertTrue(
            Purchase::where('user_id', $user->id)->where('book_id', $bookId)->exists()
        );
    }

    public function test_the_callback_is_matched_on_customer_reference(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->pay($user, $order);

        // Nothing here matches except what WE sent as `reference`.
        $this->deliverWebhook([
            'id' => 'evt_'.Str::random(12),
            'event' => 'transaction.completed',
            'data' => [
                'id' => 'tx_a_completely_different_id',
                'status' => 'completed',
                'amount' => 15000,
                'currency' => 'TZS',
                'reference' => 'SOMEOTHERREF',
                'customer_reference' => $order->order_number,
                'completed_at' => now()->toIso8601String(),
            ],
        ])->assertOk();

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_the_callback_is_matched_on_the_payment_reference_with_id_suffix(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->pay($user, $order);

        $payment = $order->payments()->latest('id')->first();

        // The provider echoes back the per-attempt reference
        // (`<order number>-<payment id>`), which the matcher pins to this exact
        // row even when the short network reference differs.
        $this->deliverWebhook([
            'id' => 'evt_'.Str::random(12),
            'event' => 'transaction.completed',
            'data' => [
                'id' => 'tx_a_completely_different_id',
                'status' => 'completed',
                'amount' => 15000,
                'currency' => 'TZS',
                'reference' => 'SOMEOTHERREF',
                'customer_reference' => $order->order_number.'-'.$payment->id,
                'completed_at' => now()->toIso8601String(),
            ],
        ])->assertOk();

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
    }

    public function test_a_forged_signature_is_rejected(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->pay($user, $order);

        // A body that says "completed", signed with somebody else's secret.
        [$body, $timestamp] = $this->signed(
            $this->completedPayload($order->order_number, 'tx_01HZABCDEF')
        );

        $this->postJson('/webhooks/abliner', $body, [
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => hash_hmac(
                'sha256',
                $timestamp.'.'.json_encode($body),
                'whsec_attacker_secret'
            ),
        ]);

        $this->assertSame(
            Order::STATUS_PENDING,
            $order->fresh()->status,
            'an unverified callback must never pay an order'
        );
        $this->assertSame(0, Purchase::where('order_id', $order->id)->count());
        $this->assertSame(0, WebhookEvent::where('event_id', $body['id'])->count());
    }

    public function test_a_callback_with_no_signature_at_all_is_rejected(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->pay($user, $order);

        $payload = $this->completedPayload($order->order_number, 'tx_01HZABCDEF');

        $this->postJson('/webhooks/abliner', $payload);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, Purchase::where('order_id', $order->id)->count());
        $this->assertSame(0, WebhookEvent::where('event_id', $payload['id'])->count());
    }

    public function test_a_tampered_body_is_rejected(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->pay($user, $order);

        [$original, $timestamp, $signature] = $this->signed(
            $this->completedPayload($order->order_number, 'tx_01HZABCDEF')
        );

        // The amount is altered in flight: the signature no longer covers the
        // bytes that actually arrived.
        $tampered = $original;
        $tampered['data']['amount'] = 1;

        $this->postJson('/webhooks/abliner', $tampered, [
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => $signature,
        ]);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, Purchase::where('order_id', $order->id)->count());
    }

    public function test_a_stale_callback_is_rejected(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->pay($user, $order);

        $stale = now()->timestamp - (AblinerSignatureVerifier::FRESHNESS_SECONDS + 60);

        [$body, , $signature] = $this->signed(
            $this->completedPayload($order->order_number, 'tx_01HZABCDEF'),
            $stale
        );

        $this->postJson('/webhooks/abliner', $body, [
            'X-Webhook-Timestamp' => (string) $stale,
            'X-Webhook-Signature' => $signature,
        ]);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, Purchase::where('order_id', $order->id)->count());
    }

    public function test_a_redelivered_callback_is_processed_exactly_once(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->pay($user, $order);

        $payload = $this->completedPayload($order->order_number, 'tx_01HZABCDEF');

        $this->deliverWebhook($payload)->assertOk();
        $this->deliverWebhook($payload)->assertOk();
        $this->deliverWebhook($payload)->assertOk();

        $this->assertSame(1, WebhookEvent::where('event_id', $payload['id'])->count());
        $this->assertSame(
            1,
            Purchase::where('order_id', $order->id)->count(),
            'retries must not create duplicate entitlements'
        );
    }

    public function test_a_callback_for_the_wrong_amount_does_not_pay_the_order(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->pay($user, $order);

        $this->deliverWebhook(
            $this->completedPayload($order->order_number, 'tx_01HZABCDEF', 1)
        )->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame(0, Purchase::where('order_id', $order->id)->count());
    }

    public function test_a_callback_for_the_wrong_currency_does_not_pay_the_order(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->pay($user, $order);

        $this->deliverWebhook(
            $this->completedPayload($order->order_number, 'tx_01HZABCDEF', 15000, 'USD')
        )->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_a_failed_callback_leaves_the_order_payable(): void
    {
        $user = $this->customer();
        $order = $this->makeOrder($user);
        $this->pay($user, $order);

        $this->deliverWebhook([
            'id' => 'evt_'.Str::random(12),
            'event' => 'transaction.failed',
            'data' => [
                'id' => 'tx_01HZABCDEF',
                'status' => 'failed',
                'amount' => 15000,
                'currency' => 'TZS',
                'customer_reference' => $order->order_number,
            ],
        ])->assertOk();

        $payment = $order->payments()->latest('id')->first();

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertNotSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(0, Purchase::where('order_id', $order->id)->count());

        // The order can still be paid: a genuinely new attempt, with a new
        // idempotency key, is accepted after a failure.
        $this->replyWith(200, null, 'tx_SECOND_ATTEMPT');

        $this->pay($user, $order)->assertRedirect();

        $this->assertSame(2, $order->payments()->count(), 'a retry is a new payment row');
        $this->assertSame(
            'tx_SECOND_ATTEMPT',
            $order->payments()->latest('id')->first()->provider_reference
        );
        $this->assertSame(Payment::STATUS_PENDING, $order->payments()->latest('id')->first()->status);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_the_webhook_route_needs_no_csrf_token(): void
    {
        // Abliner holds no session cookie; authenticity comes from the HMAC.
        $body = ['event' => 'transaction.completed'];

        $this->postJson('/webhooks/abliner', $body, [
            'X-Webhook-Timestamp' => (string) now()->timestamp,
            'X-Webhook-Signature' => hash_hmac(
                'sha256',
                now()->timestamp.'.'.json_encode($body),
                self::WEBHOOK_SECRET
            ),
        ])->assertOk();
    }

    public function test_the_amount_bounds_match_the_documented_provider_limits(): void
    {
        $this->assertSame(500, AblinerPaymentService::MINIMUM_AMOUNT);
        $this->assertSame(3000000, AblinerPaymentService::MAXIMUM_AMOUNT);
        $this->assertSame('TZS', AblinerPaymentService::CURRENCY);
    }

    public function test_someone_else_cannot_pay_for_my_order(): void
    {
        $owner = $this->customer();
        $stranger = $this->customer();
        $order = $this->makeOrder($owner);

        $this->actingAs($stranger)
            ->post("/account/orders/{$order->id}/payment", [
                'method' => 'mobile',
                'network' => 'mpesa',
                'phone' => '0712345678',
            ])
            ->assertNotFound();

        $this->assertSame([], $this->depositCalls());
    }
}