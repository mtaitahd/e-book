<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Abliner\AblinerApiException;
use App\Services\Abliner\AblinerControlNumberService;
use App\Services\Abliner\AblinerPaymentService;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Customer-facing payment flow.
 *
 * The customer chooses how to pay — a mobile money USSD push, a card, or a
 * control number they pay from any app or bank — confirms the details, and we
 * start the collection through Abliner. When the store does not yet have a real
 * API key configured, customers are shown a friendly "not available yet" state
 * instead of an error.
 */
class CustomerPaymentController extends Controller
{
    public function __construct(
        private readonly AblinerPaymentService $payments,
        private readonly AblinerControlNumberService $controlNumbers,
    ) {}

    /**
     * Payment page for the authenticated user's own order.
     */
    public function show(Order $order): View|RedirectResponse
    {
        $order = auth()->user()->orders()
            ->with(['items.book', 'payments'])
            ->findOrFail($order->id);

        if ($order->isPaid()) {
            return redirect()->route('account.orders.show', $order);
        }

        // The currency rails: local payments only make sense when the order
        // was placed in the shop's active currency.
        $orderAmount = $this->payments->amountFor($order);

        if ($order->currency !== config('shop.currency')
            || ! $this->payments->isAmountInRange($orderAmount)) {
            return view('account.payments.show', [
                'order' => $order,
                'available' => false,
                'notConfigured' => false,
                'minimumNotMet' => $orderAmount < AblinerPaymentService::MINIMUM_AMOUNT,
                'maximumExceeded' => $orderAmount > AblinerPaymentService::MAXIMUM_AMOUNT,
                'payment' => null,
                'networks' => [],
                'methods' => Payment::METHODS,
                'selectedNetwork' => null,
                'selectedMethod' => Payment::TYPE_MOBILE,
                'canStartPayment' => false,
                'instructions' => [],
                'instructionsFor' => $this->instructionsFor(null),
            ]);
        }

        $payment = $order->payments()->latest('id')->first();

        return view('account.payments.show', [
            'order' => $order,
            'available' => true,
            'notConfigured' => ! $this->payments->isAvailable(),
            'minimumNotMet' => false,
            'maximumExceeded' => false,
            'payment' => $payment,
            'networks' => Payment::NETWORKS,
            'methods' => Payment::METHODS,
            'selectedNetwork' => $payment?->channel_provider
                && array_key_exists($payment->channel_provider, Payment::NETWORKS)
                ? $payment->channel_provider
                : null,
            'selectedMethod' => $payment?->payment_type
                && array_key_exists($payment->payment_type, Payment::METHODS)
                ? $payment->payment_type
                : Payment::TYPE_MOBILE,
            // A NEW payment intent is only ever created when the provider is
            // both enabled and configured. An already-pending payment stays
            // visible and refreshable either way, so shutting the provider off
            // can never leave a customer unable to see what happened to a
            // request that was already sent.
            'canStartPayment' => $this->payments->isAvailable(),
            'instructions' => $payment?->isControlNumber()
                ? $this->instructionsFor($payment->channel_provider, $payment->control_number)
                : [],
            'instructionsFor' => $this->instructionsFor(null),
        ]);
    }

    /**
     * Server-authoritative payment state for the order.
     *
     * The payment dialog renders from this instead of from anything it
     * remembers in the browser, so a reload, the back button, or a second
     * click always lands on the state the database and the webhook actually
     * agree on. It never calls the provider: this is the cheap read used when
     * the dialog opens, while `refresh` is the deliberate "ask Abliner" call.
     */
    public function status(Request $request, Order $order): JsonResponse
    {
        $order = auth()->user()->orders()
            ->with('payments')
            ->findOrFail($order->id);

        return response()->json($this->payState($order));
    }

    /**
     * Reduce the order and its latest payment to the small vocabulary the
     * dialog needs to pick a panel.
     *
     * @return array<string, mixed>
     */
    private function payState(Order $order, ?Payment $payment = null): array
    {
        $payment ??= $order->payments()->latest('id')->first();

        $amount = Money::formatWhole($this->payments->amountFor($order), $order->currency);

        $payable = $order->currency === config('shop.currency')
            && $this->payments->isAmountInRange($this->payments->amountFor($order));

        if ($order->isPaid()) {
            $state = 'completed';
        } elseif ($payment === null) {
            $state = 'idle';
        } elseif ($payment->isCompleted()) {
            $state = 'completed';
        } elseif ($payment->isPending() && ! $payment->hasExpiredRequest()) {
            $state = 'pending';
        } elseif ($payment->isPending()) {
            $state = 'expired';
        } elseif ($payment->isFailed() || $payment->isVoided() || $payment->isExpired()) {
            $state = 'failed';
        } else {
            $state = 'idle';
        }

        return [
            'state' => $state,
            'paid' => $state === 'completed',
            'can_start' => $state !== 'completed' && $payable && $this->payments->isAvailable(),
            'available' => $this->payments->isAvailable(),
            'order' => [
                'number' => $order->order_number,
                'status' => $order->status,
                'amount' => $amount,
                // The success panel needs somewhere to send the customer, so
                // resolve their own links rather than guessing at them here.
                'read_url' => ($purchase = $order->purchases()->first())
                    ? route('account.purchases.read', $purchase)
                    : null,
                'library_url' => route('account.purchases.index'),
            ],
            'payment' => $payment === null ? null : [
                'method' => $payment->payment_type,
                // Payment::METHODS maps to ['label' => ..., 'hint' => ...],
                // so the entry itself is an array. Handing that array straight
                // to the browser stringified it as "[object Object]" next to
                // the "Method" row - take the label out of it.
                // RETIRED_METHOD_LABELS covers payments started before a method
                // was withdrawn, so they still name themselves correctly.
                'method_label' => Payment::METHODS[$payment->payment_type]['label']
                    ?? Payment::RETIRED_METHOD_LABELS[$payment->payment_type]
                    ?? null,
                'status' => $payment->status,
                'network' => $payment->channel_provider,
                'network_label' => Payment::NETWORKS[$payment->channel_provider] ?? null,
                // Confirming which number the prompt went to is the whole point
                // of the waiting panel. The row records the line the push was
                // actually sent to; older rows fall back to the account number,
                // which is what was used back then.
                'phone' => $payment->isMobile()
                    ? ($payment->phone ?: $order->user?->phone)
                    : null,
                'amount' => Money::formatWhole($payment->amount, $payment->currency),
                'reference' => $payment->provider_reference,
                'control_number' => $payment->control_number,
                'instructions' => $payment->isControlNumber()
                    ? $this->instructionsFor($payment->channel_provider, $payment->control_number)
                    : [],
                'requested_at' => $payment->created_at?->toIso8601String(),
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'message' => $this->humanFailureReason($payment->failure_reason),
            ],
        ];
    }

    /**
     * Turn a stored failure reason into something a customer can act on.
     *
     * The stored value is written for us ("provider reports declined"), so it
     * is never shown raw. Unknown reasons fall back to a retryable line rather
     * than leaking provider wording into the page.
     */
    private function humanFailureReason(?string $reason): ?string
    {
        if ($reason === null || trim($reason) === '') {
            return null;
        }

        $reason = trim((string) preg_replace('/^(request_failed|provider reports):\s*/i', '', $reason));

        return match (true) {
            str_contains($reason, 'declin') => 'Your bank declined this payment.',
            str_contains($reason, 'insufficient') => 'There was not enough balance to complete this payment.',
            str_contains($reason, 'cancel') => 'You cancelled this payment.',
            str_contains($reason, 'timeout'), str_contains($reason, 'timed out') => 'The request timed out before it was approved.',
            default => 'This payment did not go through. You can try again.',
        };
    }

    /**
     * Begin (or continue) a payment for the order.
     */
    public function store(Request $request, Order $order, AblinerPaymentService $payments): JsonResponse|RedirectResponse
    {
        $order = auth()->user()->orders()
            ->with('payments')
            ->findOrFail($order->id);

        if ($order->isPaid()) {
            return $request->expectsJson()
                ? response()->json($this->payState($order))
                : back()->with('info', 'This order has already been paid.')->with('alert_title', 'Already paid');
        }

        if ($order->currency !== config('shop.currency')) {
            return $this->refuse($request, $order, 'This order cannot be paid online.', 'Payment unavailable');
        }

        $customer = auth()->user();

        // The number the account holds *before* this request possibly updates
        // it: it is what an older payment row without a phone of its own was
        // pushed to, so it is the only way to tell whether the number the
        // customer just typed is actually a different line.
        $accountPhone = $customer->phone;

        $method = (string) $request->input('method', Payment::TYPE_MOBILE);

        if (! array_key_exists($method, Payment::METHODS)) {
            $method = Payment::TYPE_MOBILE;
        }

        // Normalise before validating so the push goes to the canonical form
        // (0755123456 and 255755123456 are the same number).
        if ($method === Payment::TYPE_MOBILE && filled($request->input('phone'))) {
            $request->merge([
                'phone' => Phone::normalizeTanzanian(trim((string) $request->input('phone'))),
            ]);
        }

        $validated = $request->validate([
            'method' => ['required', 'string', 'in:'.implode(',', array_keys(Payment::METHODS))],
            'network' => ['nullable', 'string', 'in:'.implode(',', array_keys(Payment::NETWORKS))],
            'phone' => [
                'nullable',
                'string',
                'max:32',
                // Any valid number is acceptable here: the customer decides
                // which line to pay from. The account contact stays unique, but
                // that is enforced below by only persisting a number no other
                // account already holds.
            ],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
        ]);

        $amount = $payments->amountFor($order);

        if (! $payments->isAmountInRange($amount)) {
            $message = $amount < AblinerPaymentService::MINIMUM_AMOUNT
                ? 'This order is below the '.Money::formatWhole(AblinerPaymentService::MINIMUM_AMOUNT).' minimum payment.'
                : 'This order is above the '.Money::formatWhole(AblinerPaymentService::MAXIMUM_AMOUNT).' maximum single payment.';

            return $this->refuse($request, $order, $message, 'Amount not payable');
        }

        if (! $payments->isAvailable()) {
            if (auth()->user()->isAdmin()) {
                return $this->refuse($request, $order,
                    $payments->isEnabled()
                        ? 'Payments are not enabled yet. Configure ABLINER_API_KEY and ABLINER_WEBHOOK_SECRET on the payment settings page.'
                        : 'Payments are currently switched off by the administrator.',
                    'Payments not enabled',
                );
            }

            return $this->refuse($request, $order, 'Payments are not available yet. Please try again later.', 'Payments unavailable');
        }

        // A USSD push needs a number to push to. Cards are collected on
        // Abliner's own hosted page, and a control number is paid later from
        // whatever app the customer likes, so neither asks for one here.
        $phone = null;

        if ($method === Payment::TYPE_MOBILE) {
            $phone = Phone::normalizeTanzanian(trim((string) $request->input('phone', '')));

            if ($phone === null) {
                return $this->refuse($request, $order, 'Please enter a valid Tanzanian mobile number (e.g. 07XXXXXXXXX).', 'Check your mobile number');
            }
        }

        // Cards and control numbers are not network-bound, so there is nothing
        // meaningful to record as the channel.
        $network = $method === Payment::TYPE_MOBILE
            ? (string) ($validated['network'] ?? '')
            : ($validated['network'] ?? $method);

        if ($method === Payment::TYPE_MOBILE && $network === '') {
            return $this->refuse($request, $order, 'Please choose the mobile money network you want to pay from.', 'Choose your network');
        }

        $customer->update([
            'first_name' => $validated['first_name'] ?? $customer->first_name,
            'last_name' => $validated['last_name'] ?? $customer->last_name,
        ]);

        // Remember the number as the customer's next-time default, but only
        // when no other account already holds it: the account contact must
        // stay unique, yet a payment must still be pushable to any number the
        // customer typed. A claimed number is used for this payment only and
        // never overwrites the account.
        if ($phone !== null
            && ! User::where('phone', $phone)->where('id', '!=', $customer->id)->exists()) {
            $customer->update(['phone' => $phone]);
        }

        $payment = $this->buildPayment($order, $method, $phone, $accountPhone);

        // An attempt for this order and method is already live. Never push a
        // second prompt at the customer: report what is already in flight and
        // let the status check decide what happens next. Re-sending here is
        // what makes a double click, a refresh mid-request, or an impatient
        // second tap charge them twice.
        if ($payment->wasRecentlyCreated === false) {
            if ($payment->isCard() && filled($payment->payment_url)) {
                return $request->expectsJson()
                    ? response()->json($this->payState($order->fresh('payments'), $payment) + [
                        'redirect_url' => $payment->payment_url,
                    ])
                    : redirect()->away((string) $payment->payment_url);
            }

            return $request->expectsJson()
                ? response()->json($this->payState($order->fresh('payments'), $payment))
                : redirect()->route('account.orders.payments.show', $order)
                    ->with('info', 'You already have a payment in progress for this order.')
                    ->with('alert_title', 'Payment already in progress');
        }

        // Record which line the push goes out on, so the waiting panel never
        // has to guess at the number.
        if ($phone !== null && $payment->wasRecentlyCreated) {
            $payment->update(['phone' => $phone]);
        }

        try {
            // `method` has already been checked against Payment::METHODS, so
            // control_number cannot reach here. createPayment takes it as the
            // provider `method` value directly.
            $payments->createPayment($payment, $customer, $network, $method);
        } catch (AblinerApiException $exception) {
            if ($exception->errorCode() === 'request_in_progress') {
                // The provider has the request in flight (it even says "retry
                // in a few seconds"). Declaring it failed would make the
                // customer leave exactly when the push may arrive; instead keep
                // the waiting screen open, exactly like refresh() does, and let
                // the status checks settle it.
                return $request->expectsJson()
                    ? response()->json($this->payState($order->fresh('payments'), $payment))
                    : redirect()->route('account.orders.payments.show', $order)
                        ->with('info', 'Your payment is still being processed. Keep this page open - we keep checking.')
                        ->with('alert_title', 'Payment in progress');
            }

            // The failure is with the request itself, not the customer's intent.
            // The same payment row + idempotency key can be reused for a retry;
            // the prompt instructs the customer to simply retry.
            $payment->update(['failure_reason' => 'request_failed:'.$exception->getMessage()]);

            return $this->refuse($request, $order, $exception->getMessage(), $this->alertTitleFor($exception));
        } catch (Throwable $exception) {
            // An unexpected failure must never reach the customer as a stack
            // trace, but it must not vanish either: report it so the cause is
            // actually diagnosable from the log afterwards.
            report($exception);

            $payment->update(['failure_reason' => 'request_failed:the payment request could not be completed.']);

            return $this->refuse(
                $request,
                $order,
                'The payment request could not be completed. Please try again.',
                'Payment could not start',
            );
        }

        // A card payment continues on the provider's hosted page. Send them
        // straight there instead of making them confirm and then click again.
        if ($payment->isCard() && filled($payment->payment_url)) {
            return $request->expectsJson()
                ? response()->json($this->payState($order->fresh('payments'), $payment) + [
                    'redirect_url' => $payment->payment_url,
                ])
                : redirect()->away((string) $payment->payment_url);
        }

        if ($request->expectsJson()) {
            // Starting the request is not paying the order. The dialog gets a
            // "pending" state to render and asks the customer to approve the
            // prompt; nothing here may look like a receipt.
            return response()->json($this->payState($order->fresh('payments'), $payment));
        }

        return redirect()->route('account.orders.payments.show', $order)
            ->with('success', $this->confirmationMessage($payment))
            ->with('alert_title', $payment->isControlNumber() ? 'Control number ready' : 'Check your phone');
    }

    /**
     * A payment request that never started is retryable: report it in the
     * shape the caller asked for so the dialog can offer "Try again" and put
     * the customer back on the method chooser.
     */
    private function refuse(Request $request, Order $order, string $message, string $title): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'state' => 'idle',
                'failed' => true,
                'can_start' => true,
                'title' => $title,
                'message' => $message,
                'payment' => null,
                'order' => [
                    'number' => $order->order_number,
                    'amount' => Money::formatWhole($this->payments->amountFor($order), $order->currency),
                ],
            ], 422);
        }

        return back()->withInput()->with('error', $message)->with('alert_title', $title);
    }

    /**
     * Re-check a pending payment's status with the provider.
     */
    public function refresh(Request $request, Order $order, AblinerPaymentService $payments): JsonResponse|RedirectResponse
    {
        $order = auth()->user()->orders()
            ->with('payments')
            ->findOrFail($order->id);

        $payment = $order->payments()->latest('id')->first();

        if ($payment === null || $payment->isCompleted()) {
            // Nothing left to ask: report the settled state instead of a
            // "nothing to refresh" complaint, because the customer's intent
            // was to learn the status.
            return $request->expectsJson()
                ? response()->json($this->payState($order))
                : back()->with('info', 'There is no pending payment to refresh.')->with('alert_title', 'Nothing to check');
        }

        if (! $payments->isConfigured()) {
            return $this->refuse($request, $order, 'Payments are not available yet.', 'Payments unavailable');
        }

        if ($payment->provider_reference === null || $payment->provider_reference === '') {
            // The row exists but the provider never handed back a reference:
            // the create call either lost its response (a 5xx on their side)
            // or is still in flight upstream (409 request_in_progress). This
            // row's idempotency key is stable, so re-issuing the identical
            // request replays the original outcome instead of pushing a second
            // prompt - and it is the only way to learn what became of a request
            // whose response never arrived. Control numbers have no provider
            // transaction to replay, so they keep the plain refusal below.
            if ($payment->isMobile() || $payment->isCard()) {
                try {
                    $payments->createPayment(
                        $payment,
                        $order->user,
                        $payment->channel_provider ?? '',
                        $payment->payment_type,
                    );
                } catch (AblinerApiException $exception) {
                    if ($exception->errorCode() === 'request_in_progress') {
                        // Still being processed upstream: keep the waiting
                        // screen open and let the next check come back for the
                        // answer, rather than declaring a failure the provider
                        // has not confirmed.
                        return $request->expectsJson()
                            ? response()->json($this->payState($order->fresh('payments')))
                            : back()
                                ->with('info', 'Your payment is still being processed. Keep this page open - we keep checking.')
                                ->with('alert_title', 'Payment in progress');
                    }

                    $payment->update(['failure_reason' => 'request_failed:'.$exception->getMessage()]);

                    return $this->refuse($request, $order, $exception->getMessage(), $this->alertTitleFor($exception));
                } catch (Throwable $exception) {
                    report($exception);

                    return $this->refuse($request, $order, 'The payment status could not be checked right now. Please try again in a moment.', 'Could not check the status');
                }

                // The replay handed back the transaction: fall through to the
                // status handling below, so a completed or failed outcome
                // settles the order on this check instead of the next one.
            } else {
                return $this->refuse($request, $order, 'There is no payment reference yet. Please start a payment first.', 'No reference yet');
            }
        }

        try {
            $providerStatus = $payments->verifyPayment($payment);
        } catch (AblinerApiException $exception) {
            $payment->update(['failure_reason' => 'request_failed:'.$exception->getMessage()]);

            return $this->refuse($request, $order, $exception->getMessage(), 'Could not check the status');
        }

        if ($providerStatus === null) {
            return $this->refuse($request, $order, 'There is no payment reference yet. Please start a payment first.', 'No reference yet');
        }

        $updated = $payments->mapProviderStatus($providerStatus);

        DB::transaction(function () use ($payment, $updated, $providerStatus, $order) {
            if ($updated === Payment::STATUS_COMPLETED) {
                $payment->update(['status' => $updated, 'paid_at' => now(), 'failure_reason' => null]);
                $order->update(['status' => Order::STATUS_PAID, 'paid_at' => $payment->paid_at]);
            } else {
                $payment->update([
                    'status' => $updated,
                    'failure_reason' => $updated === Payment::STATUS_PENDING
                        ? $payment->failure_reason
                        : "provider reports {$providerStatus}",
                ]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json($this->payState($order->fresh('payments')));
        }

        if ($updated === Payment::STATUS_COMPLETED) {
            return redirect()->route('account.orders.show', $order)
                ->with('success', 'Payment confirmed. Thank you for your purchase!')
                ->with('alert_title', 'Payment received');
        }

        return back()->with('success', 'Payment status updated.')->with('alert_title', 'Payment status');
    }

    /**
     * Reuse the latest pending payment row for the order, otherwise create a
     * fresh row (fresh idempotency key). A pending-but-not-expired payment
     * with a provider reference blocks duplicate creation.
     *
     * Re-using the row is what makes a retry safe: the same row id means the
     * same Idempotency-Key, so the provider replays its original response
     * instead of pushing a second prompt to the customer's phone.
     *
     * The one thing that must NOT be re-used is a mobile request addressed to
     * another line. The row's phone is the number the prompt went to, so
     * paying from a different number gets its own row (and its own key): the
     * push has to reach the number actually typed, not repeat on the one the
     * customer just walked away from.
     */
    private function buildPayment(Order $order, string $method, ?string $phone = null, ?string $accountPhone = null): Payment
    {
        $latest = $order->payments()->latest('id')->first();

        if ($latest !== null
            && $latest->isPending()
            && ! $latest->hasExpiredRequest()
            && $latest->provider_reference !== null
            && $latest->payment_type === $method
            && ! $this->retargeted($latest, $method, $phone, $accountPhone)) {
            return $latest;
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => Payment::PROVIDER_ABLINER,
            'payment_type' => $method,
            'idempotency_key' => 'tmp-'.uniqid('', true),
            'amount' => $this->payments->amountFor($order),
            'currency' => config('shop.currency'),
            'status' => Payment::STATUS_PENDING,
        ]);

        // The stable idempotency key is derived from the row id: the same
        // attempt (and any transport-level retry) always reuses the same key.
        $payment->update(['idempotency_key' => $this->payments->idempotencyKeyFor($payment)]);

        return $payment;
    }

    /**
     * Has the customer asked for the push to go to a different line?
     *
     * A row records the number it was pushed to; rows from before that field
     * existed were sent to the account number as it stood at the time, which
     * is the number passed in here.
     */
    private function retargeted(Payment $payment, string $method, ?string $phone, ?string $accountPhone): bool
    {
        if ($method !== Payment::TYPE_MOBILE || $phone === null) {
            return false;
        }

        $sentTo = $payment->phone ?: $accountPhone;

        return $sentTo !== null && $sentTo !== $phone;
    }

    /**
     * The USSD dial sequences for a control-number payment, with the actual
     * control number substituted in.
     *
     * @return list<array{label: string, code: string, steps: list<string>}>
     */
    private function instructionsFor(?string $network, ?string $controlNumber = null): array
    {
        return array_map(
            static function (array $channel) use ($controlNumber): array {
                if ($controlNumber === null) {
                    return $channel;
                }

                $channel['steps'] = array_map(
                    static fn (string $step): string => str_replace('[control number]', $controlNumber, $step),
                    $channel['steps']
                );

                return $channel;
            },
            $this->controlNumbers->instructionsFor($network)
        );
    }

    private function confirmationMessage(Payment $payment): string
    {
        return match (true) {
            $payment->isControlNumber() => 'Your control number is ready. Pay it from any mobile money app or bank using the steps below.',
            $payment->isCard() => 'Card payment started.',
            default => 'Payment started. Approve the prompt on your phone to finish.',
        };
    }

    /**
     * A short, specific popup heading so the customer is told WHAT went wrong
     * rather than being handed the generic "Please check your details" the
     * shared flash partial falls back to. Branched on the provider's
     * machine-readable code, never on message text.
     */
    private function alertTitleFor(AblinerApiException $exception): string
    {
        return match ($exception->errorCode()) {
            'invalid_phone' => 'Check your mobile number',
            'unauthorized', 'invalid_request_signature' => 'Payments are not set up',
            'webhook_secret_required' => 'Payments are not set up',
            'insufficient_funds' => 'Not enough balance',
            'amount_out_of_range' => 'Amount not accepted',
            'unsupported_request' => 'Payment method unavailable',
            'idempotency_conflict', 'request_in_progress' => 'Payment already in progress',
            'gateway_not_configured', 'account_suspended' => 'Payments temporarily unavailable',
            default => match ($exception->statusCode) {
                401, 403 => 'Payments are not available',
                429 => 'Too many requests',
                502, 503 => 'Payment network unavailable',
                default => 'Payment could not start',
            },
        };
    }
}
