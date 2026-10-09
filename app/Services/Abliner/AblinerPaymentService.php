<?php

namespace App\Services\Abliner;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Settings\PaymentProviderConfig;
use App\Support\Money;
use Illuminate\Support\Facades\Log;

/**
 * Server-side Abliner collection client.
 *
 * Three ways for a customer to pay, all on the same POST /api/v1/deposits
 * endpoint except control numbers:
 *
 *   mobile          -> USSD push straight to the customer's phone. No page to
 *                      open, payment_url is null.
 *   card            -> Abliner returns payment_url; the customer is redirected
 *                      to it and the webhook confirms the result.
 *   control_number  -> a ClickPesa BillPay control number the customer pays
 *                      from any mobile money app or bank (see
 *                      AblinerControlNumberService).
 *
 * The secret API key and webhook secret are used only here, server-side, and
 * are never exposed to the browser.
 */
class AblinerPaymentService
{
    public const METHOD_MOBILE = 'mobile';

    public const METHOD_CARD = 'card';

    public const METHOD_DYNAMIC_QR = 'dynamic-qr';

    public const CURRENCY = 'TZS';

    /**
     * Documented Abliner per-transaction deposit limits, in whole TZS.
     */
    public const MINIMUM_AMOUNT = 500;

    public const MAXIMUM_AMOUNT = 3000000;

    public const MAX_IDEMPOTENCY_KEY_LENGTH = 100;

    /**
     * Read-only endpoint used by the admin "Test connection" button.
     */
    public const CONNECTION_TEST_ENDPOINT = '/balance';

    public function __construct(
        private readonly AblinerApiClient $http,
        private readonly PaymentProviderConfig $config,
    ) {}

    /**
     * Whether an API key is available, from the admin settings page first and
     * the environment second.
     */
    public function isConfigured(): bool
    {
        return $this->http->isConfigured();
    }

    /**
     * Whether a shared HMAC secret is present, which is what makes an inbound
     * callback trustworthy AND what allows us to sign outbound requests.
     * This is reported as a boolean only; the value itself is never read
     * outside the signer.
     */
    public function hasWebhookSecret(): bool
    {
        return $this->config->hasWebhookSecret();
    }

    /**
     * Operator kill switch, set by an administrator on the payment settings
     * page and falling back to ABLINER_ENABLED in the environment.
     *
     * When it is off, new Abliner payment requests are refused at the
     * customer-facing entry point; existing orders, paid orders, webhook
     * verification and download/reader access are all untouched.
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Whether the store may start a NEW payment right now.
     *
     * All three are required, not just the API key. Without the shared secret
     * the request cannot be signed (so the provider would reject it) and no
     * callback could ever be verified, which means a payment taken now could
     * never be confirmed automatically. Refusing up front is honest; taking the
     * money and stranding the customer is not.
     */
    public function isAvailable(): bool
    {
        return $this->isEnabled() && $this->isConfigured() && $this->hasWebhookSecret();
    }

    public function baseUrl(): string
    {
        return $this->http->baseUrl();
    }

    /**
     * The integer whole-TZS amount to charge for an order.
     */
    public function amountFor(Order $order): int
    {
        return Money::toWholeInt($order->total);
    }

    public function isAmountInRange(int $amount): bool
    {
        return $amount >= self::MINIMUM_AMOUNT && $amount <= self::MAXIMUM_AMOUNT;
    }

    /**
     * Deterministic, stable idempotency key for a single payment attempt.
     *
     * Re-submitting the same attempt reuses this key, so Abliner replays the
     * original transaction (200, idempotent_replay) instead of sending a second
     * USSD push. Required for every deposit on a key created from 5 Oct 2026.
     */
    public function idempotencyKeyFor(Payment $payment): string
    {
        return 'ebs-pay-'.$payment->id;
    }

    /**
     * The public URL Abliner posts callbacks to.
     */
    public function callbackUrl(): ?string
    {
        return $this->config->webhookUrl();
    }

    /**
     * Start a collection for an order.
     *
     * The local payment row must already exist with a stable idempotency key.
     * On success the row is updated with the transaction id, the reference the
     * customer sees, the provider status and (for cards) the redirect URL. On
     * transport/provider failure an AblinerApiException is thrown; the payment
     * is left pending so the SAME request (same key + same body) can be retried.
     *
     * @param  string  $method  one of METHOD_MOBILE, METHOD_CARD, METHOD_DYNAMIC_QR
     *
     * @throws AblinerApiException
     */
    public function createPayment(Payment $payment, User $customer, string $network, string $method = self::METHOD_MOBILE): Payment
    {
        if (! $this->isConfigured()) {
            throw new AblinerApiException('Payment provider is not configured.', 0, [], false);
        }

        $order = $payment->order()->firstOrFail();
        $amount = $this->amountFor($order);
        $idempotencyKey = $this->idempotencyKeyFor($payment);

        if (strlen($idempotencyKey) > self::MAX_IDEMPOTENCY_KEY_LENGTH) {
            throw new AblinerApiException('Internal idempotency key exceeded provider length limit.', 0, [], false);
        }

        $payload = [
            'amount' => $amount,
            'method' => $method,
            'currency' => self::CURRENCY,
            // The reference we send is echoed back as customer_reference on the
            // synchronous response and on every webhook, which gives us a second,
            // order-scoped way to match an incoming event. A "-<payment id>"
            // suffix is appended so every attempt gets its own provider
            // reference: Abliner refuses new requests that reuse a reference
            // whose earlier transaction never reached a terminal state (409
            // request_in_progress), which would otherwise brick every retry for
            // the same order. The webhook matcher strips the suffix back to the
            // order number, and findTransaction falls back to it verbatim.
            'reference' => (string) $order->order_number.'-'.$payment->id,
        ];

        // Only a USSD push needs a phone number; a card payment collects the
        // card details on Abliner's own hosted page. The row's phone is the
        // exact line the customer chose at payment time, falling back to the
        // account contact for rows created before the payment phone existed.
        if ($method === self::METHOD_MOBILE) {
            $payload['phone'] = (string) ($payment->phone ?: $customer->phone);
        }

        if (filled($this->callbackUrl())) {
            $payload['callback_url'] = (string) $this->callbackUrl();
        }

        $this->log('payment.create', $payment, [
            'amount' => $amount,
            'currency' => self::CURRENCY,
            'method' => $method,
            'channel_provider' => $network,
            'order_id' => $order->id,
            'payment_id' => $payment->id,
        ]);

        [$success, $statusCode, $body] = $this->http->post('/deposits', $payload, $idempotencyKey);

        if (! $success) {
            $this->log('payment.create_failed', $payment, [
                'status_code' => $statusCode,
                'method' => $method,
                'provider_error' => $this->http->errorCode($body),
                'provider_error_field' => $this->http->errorField($body),
                'provider_message' => $this->http->errorMessage($body),
            ]);

            throw $this->http->exception($statusCode, $body);
        }

        $data = $this->dataOf($body);

        $payment->update([
            'provider' => Payment::PROVIDER_ABLINER,
            'payment_type' => $method,
            // data.id is the canonical handle: /api/v1/transactions?id= resolves
            // it and every webhook repeats it, which is what we match on.
            'provider_reference' => $data['id'] ?? $payment->provider_reference,
            // data.reference is the short reference the customer actually dials
            // or reads out, so it belongs in the admin-visible reference column.
            'external_reference' => $data['reference'] ?? $payment->external_reference,
            'customer_reference' => $data['customer_reference'] ?? $payment->customer_reference,
            'amount' => (int) ($data['amount'] ?? $amount),
            'currency' => strtoupper((string) ($data['currency'] ?? self::CURRENCY)),
            'status' => $this->mapProviderStatus((string) ($data['status'] ?? Payment::STATUS_PENDING)),
            'channel_provider' => $network,
            'payment_url' => $data['payment_url'] ?? null,
            'provider_payload' => $body,
        ]);

        $payment->refresh();

        $this->log('payment.created', $payment, [
            'provider_reference' => $payment->provider_reference,
            'external_reference' => $payment->external_reference,
            'status' => $payment->status,
        ]);

        return $payment;
    }

    /**
     * Server-side verification of a payment's current provider state.
     * Returns the provider status string, or null when there is nothing to look
     * up or the API key is not configured.
     *
     * @throws AblinerApiException
     */
    public function verifyPayment(Payment $payment): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $transactionId = (string) ($payment->provider_reference ?? '');

        if ($transactionId === '') {
            return null;
        }

        $this->log('payment.verify', $payment, ['provider_reference' => $transactionId]);

        $transaction = $this->findTransaction($payment, $transactionId);

        if ($transaction === null) {
            return null;
        }

        $this->log('payment.verified', $payment, [
            'provider_reference' => $transactionId,
            'provider_status' => $transaction['status'] ?? null,
        ]);

        return isset($transaction['status']) ? (string) $transaction['status'] : null;
    }

    /**
     * Confirms with Abliner that a payment is actually completed. Returns
     * false when the provider reports anything other than completed.
     */
    public function confirmCompletion(Payment $payment): bool
    {
        try {
            return $this->verifyPayment($payment) === 'completed';
        } catch (AblinerApiException $exception) {
            $this->log('payment.confirm_completion_error', $payment, [
                'provider_reference' => $payment->provider_reference,
                'status_code' => $exception->statusCode,
            ]);

            return false;
        }
    }

    /**
     * Look the transaction up, preferring the exact transaction id and falling
     * back to the customer reference we sent (the order number).
     *
     * @return array<mixed>|null
     *
     * @throws AblinerApiException
     */
    private function findTransaction(Payment $payment, string $transactionId): ?array
    {
        [$ok, $statusCode, $body] = $this->http->get('/transactions', ['id' => $transactionId]);

        if ($ok) {
            $match = $this->pickTransaction($body, $transactionId);

            if ($match !== null) {
                return $match;
            }
        } elseif ($statusCode !== 404) {
            throw $this->http->exception($statusCode, $body);
        }

        $customerReference = (string) ($payment->customer_reference ?? '');

        if ($customerReference === '') {
            return null;
        }

        [$ok, $statusCode, $body] = $this->http->get('/transactions', [
            'customer_reference' => $customerReference,
        ]);

        if (! $ok) {
            // An unknown reference is a legitimate "nothing to find yet", not an
            // error worth failing a customer's status check over.
            if ($statusCode === 404) {
                return null;
            }

            throw $this->http->exception($statusCode, $body);
        }

        return $this->pickTransaction($body, $customerReference);
    }

    /**
     * @return array<mixed>|null
     */
    private function pickTransaction(array $body, string $needle): ?array
    {
        foreach ($this->rowsOf($body) as $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach (['id', 'reference', 'customer_reference'] as $key) {
                if (isset($row[$key]) && (string) $row[$key] === $needle) {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * Abliner returns `data` as a list for /transactions and as a single object
     * for /deposits. Normalise both into a list.
     *
     * @return array<mixed>
     */
    private function rowsOf(array $body): array
    {
        $data = $body['data'] ?? null;

        if (is_array($data) && array_is_list($data)) {
            return $data;
        }

        return is_array($data) ? [$data] : [];
    }

    /**
     * @return array<mixed>
     */
    private function dataOf(array $body): array
    {
        $data = $body['data'] ?? null;

        return is_array($data) ? $data : [];
    }

    public function mapProviderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'paid', 'success' => Payment::STATUS_COMPLETED,
            'failed' => Payment::STATUS_FAILED,
            'voided', 'cancelled', 'canceled' => Payment::STATUS_VOIDED,
            'expired' => Payment::STATUS_EXPIRED,
            default => Payment::STATUS_PENDING,
        };
    }

    /**
     * Verify that Abliner is reachable and that the configured API key is
     * accepted, using a single read-only request.
     *
     * Safety properties, all deliberate:
     *  - it never calls the payment-creation endpoint;
     *  - it makes exactly one request, so it cannot turn into request spam;
     *  - it never returns the provider's response body, the account balance,
     *    the API key or the Authorization header to the caller;
     *  - it logs only the endpoint, status, latency and outcome.
     */
    public function testConnection(): AblinerConnectionTestResult
    {
        if (! $this->isEnabled()) {
            return AblinerConnectionTestResult::disabled();
        }

        if (! $this->isConfigured()) {
            return AblinerConnectionTestResult::misconfigured();
        }

        $startedAt = microtime(true);

        [$success, $status, $body] = $this->http->getOnce(self::CONNECTION_TEST_ENDPOINT);
        $latency = $this->elapsedMs($startedAt);

        if ($success) {
            $this->logConnection(AblinerConnectionTestResult::OUTCOME_SUCCESS, $status, $latency);

            return AblinerConnectionTestResult::success($latency, $status);
        }

        if (in_array($status, [401, 403], true)) {
            $this->logConnection(AblinerConnectionTestResult::OUTCOME_UNAUTHORIZED, $status, $latency);

            return AblinerConnectionTestResult::unauthorized($latency, $status);
        }

        if ($status === 0) {
            $this->logConnection(AblinerConnectionTestResult::OUTCOME_UNREACHABLE, null, $latency);

            return AblinerConnectionTestResult::unreachable($latency);
        }

        $this->logConnection(AblinerConnectionTestResult::OUTCOME_UNEXPECTED, $status, $latency);

        return AblinerConnectionTestResult::unexpected($latency, $status);
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    /**
     * Safe diagnostics only. No key, no header, no body, no URL with a query.
     */
    private function logConnection(string $outcome, ?int $status, ?int $latencyMs, ?string $exceptionClass = null): void
    {
        Log::channel('stack')->info('abliner.connection_test', array_filter([
            'outcome' => $outcome,
            'endpoint' => self::CONNECTION_TEST_ENDPOINT,
            'status_code' => $status,
            'latency_ms' => $latencyMs,
            'transport_error' => $exceptionClass,
        ], fn ($value) => $value !== null));
    }

    private function log(string $event, Payment $payment, array $context = []): void
    {
        Log::channel('stack')->info("abliner.{$event}", array_merge([
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'status' => $payment->status,
            'currency' => $payment->currency,
        ], $context));
    }
}