<?php

namespace App\Services\Abliner;

use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Models\Withdrawal;
use App\Settings\PaymentProviderConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Verifies and applies Abliner webhooks.
 *
 * A webhook is only trusted when its HMAC signature verifies against the raw
 * request body (AblinerSignatureVerifier). Events arrive for two different
 * things, distinguished by the event prefix, and each is routed to its own
 * model:
 *
 *   transaction.completed / transaction.failed  -> a customer payment
 *   withdrawal.completed  / withdrawal.failed   -> an admin payout
 *
 * Anything else (for example checkout.paid for a hosted page we never create)
 * is recorded and ignored rather than guessed at.
 *
 * Security invariant: an order is marked paid ONLY when Abliner confirms the
 * transaction as completed — either directly via this webhook, or (when
 * verify_on_webhook is enabled) by re-fetching the transaction from the API
 * before applying completion.
 */
class AblinerWebhookService
{
    public const EVENT_TRANSACTION_COMPLETED = 'transaction.completed';

    public const EVENT_TRANSACTION_FAILED = 'transaction.failed';

    public const EVENT_WITHDRAWAL_COMPLETED = 'withdrawal.completed';

    public const EVENT_WITHDRAWAL_FAILED = 'withdrawal.failed';

    /**
     * @var list<string>
     */
    public const PAYMENT_EVENTS = [
        self::EVENT_TRANSACTION_COMPLETED,
        self::EVENT_TRANSACTION_FAILED,
    ];

    /**
     * @var list<string>
     */
    public const WITHDRAWAL_EVENTS = [
        self::EVENT_WITHDRAWAL_COMPLETED,
        self::EVENT_WITHDRAWAL_FAILED,
    ];

    public function __construct(
        private readonly AblinerPaymentService $payments,
        private readonly PaymentProviderConfig $config,
    ) {}

    /**
     * An Abliner webhook payload with the fields we care about.
     */
    public function normalizePayload(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $eventType = (string) ($payload['event'] ?? ($data['type'] ?? ''));

        // The top-level id (evt_..._completed) is stable across the provider's
        // retries and is also sent as the x-webhook-id header, which makes it
        // the correct de-duplication key.
        $eventId = (string) ($payload['id'] ?? '');

        if ($eventId === '') {
            $eventId = (string) ($data['id'] ?? '').':'.$eventType.':'.(string) ($data['status'] ?? '');
        }

        return [
            'event_id' => $eventId !== '' ? $eventId : hash('sha256', $this->rawJson($payload)),
            'event_type' => $eventType,
            'kind' => $this->kindFor($eventType),
            // data.id is the canonical transaction handle. It is what
            // GET /api/v1/transactions?id= resolves and what we store in
            // payments.provider_reference / withdrawals.provider_reference.
            'transaction_id' => (string) ($data['id'] ?? ''),
            // data.reference is the short network reference the customer dials.
            'reference' => (string) ($data['reference'] ?? ''),
            // data.customer_reference is the `reference` WE sent, which is the
            // order number. It is our second, order-scoped match key.
            'customer_reference' => (string) ($data['customer_reference'] ?? ''),
            'provider_reference' => (string) ($data['provider_reference'] ?? ''),
            'status' => strtolower((string) ($data['status'] ?? '')),
            'amount' => (int) ($data['amount'] ?? 0),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'order_id' => 0,
            'paid_at' => $this->timestamp($data['completed_at'] ?? null),
            'expires_at' => $this->timestamp($data['expires_at'] ?? null),
        ];
    }

    /**
     * Handle one webhook invocation. Returns the normalized event that was
     * recorded (or the existing record for a duplicate/ignored event) so the
     * controller can always answer 2xx.
     */
    public function handle(string $rawBody, array $payload): WebhookEvent
    {
        $normalized = $this->normalizePayload($payload);

        $event = $this->recordEvent($normalized, $rawBody);

        if ($event->wasRecentlyCreated === false) {
            // A duplicate delivery. Record it once and never re-apply.
            return $event;
        }

        if ($event->status === WebhookEvent::STATUS_IGNORED) {
            return $event;
        }

        $this->applyEvent($event, $normalized);

        return $event;
    }

    /**
     * Persist the incoming event once. A repeated event_id (Abliner retries a
     * failed delivery up to twice) returns the existing record unchanged.
     */
    private function recordEvent(array $normalized, string $rawBody): WebhookEvent
    {
        $existing = WebhookEvent::where('event_id', $normalized['event_id'])->first();

        if ($existing !== null) {
            return $existing;
        }

        $ignored = $this->shouldIgnore($normalized);

        return WebhookEvent::create([
            'event_id' => $normalized['event_id'],
            'event_type' => $normalized['event_type'],
            // Store the transaction id: it is the only value both a payment and
            // a payout row agree on, and it is what reconciliation queries use.
            'provider_reference' => $normalized['transaction_id'] ?: null,
            'payload_hash' => hash('sha256', $rawBody),
            'received_at' => now(),
            'processed_at' => $ignored ? now() : null,
            'status' => $ignored ? WebhookEvent::STATUS_IGNORED : WebhookEvent::STATUS_PROCESSED,
            'failure_reason' => $ignored ? 'unrelated event type' : null,
        ]);
    }

    private function shouldIgnore(array $normalized): bool
    {
        if (! in_array($normalized['event_type'], array_merge(self::PAYMENT_EVENTS, self::WITHDRAWAL_EVENTS), true)) {
            return true;
        }

        // An event with no handle we can match on is not actionable.
        if ($normalized['transaction_id'] === ''
            && $normalized['reference'] === ''
            && $normalized['customer_reference'] === '') {
            return true;
        }

        return false;
    }

    private function applyEvent(WebhookEvent $event, array $normalized): void
    {
        try {
            if ($normalized['kind'] === 'withdrawal') {
                $this->applyWithdrawalEvent($event, $normalized);

                return;
            }

            $this->applyPaymentEvent($event, $normalized);
        } catch (\Throwable $exception) {
            Log::error('abliner.webhook.application_error', [
                'event_id' => $event->event_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $event->update([
                'status' => WebhookEvent::STATUS_FAILED,
                'failure_reason' => 'application error',
                'processed_at' => now(),
            ]);
        }
    }

    private function applyPaymentEvent(WebhookEvent $event, array $normalized): void
    {
        $payment = $this->resolvePayment($normalized);

        if ($payment === null) {
            $event->update([
                'status' => WebhookEvent::STATUS_FAILED,
                'failure_reason' => 'no matching payment',
                'processed_at' => now(),
            ]);

            return;
        }

        if (! $this->verificationMatches($normalized, $payment)) {
            $event->update([
                'status' => WebhookEvent::STATUS_FAILED,
                'failure_reason' => 'verification mismatch',
                'processed_at' => now(),
            ]);

            return;
        }

        DB::transaction(function () use ($event, $payment, $normalized) {
            $this->transitionPayment($payment, $normalized, $event);
        });
    }

    /**
     * Match a webhook to a local payment.
     *
     * In order of confidence: the exact transaction id we stored, the short
     * network reference, then the order number we sent as `reference` (which
     * limits a stale or renamed reference to at most the latest payment on that
     * one order rather than a cross-order guess).
     */
    private function resolvePayment(array $normalized): ?Payment
    {
        if ($normalized['transaction_id'] !== '') {
            $payment = Payment::where('provider_reference', $normalized['transaction_id'])->first();

            if ($payment !== null) {
                return $payment;
            }
        }

        if ($normalized['reference'] !== '') {
            $payment = Payment::where('external_reference', $normalized['reference'])->first();

            if ($payment !== null) {
                return $payment;
            }
        }

        if ($normalized['customer_reference'] !== '') {
            $customerRef = (string) $normalized['customer_reference'];

            // Since the reference carries a "-<payment id>" suffix per attempt
            // (see AblinerPaymentService::createPayment), an incoming event can
            // be pinned to the exact payment row it belongs to. Only trust the
            // suffix when it resolves to the very order the prefix names, so a
            // legacy bare order number (whose trailing digits are not a payment
            // id) can never be misread as one.
            if (preg_match('/^(.*)-(\d{1,10})$/', $customerRef, $suffix)) {
                $payment = Payment::find((int) $suffix[2]);

                if ($payment !== null
                    && $payment->order()->exists()
                    && (string) $payment->order->order_number === (string) $suffix[1]) {
                    return $payment;
                }
            }

            $order = Order::where('order_number', $customerRef)->first();

            if ($order !== null) {
                return $order->payments()->latest('id')->first();
            }
        }

        return null;
    }

    /**
     * Guard rails: the webhook amount/currency must line up with the local
     * payment and the order's currency. A mismatch means we must not mark
     * anything paid.
     */
    private function verificationMatches(array $normalized, Payment $payment): bool
    {
        if ($normalized['amount'] > 0 && $normalized['amount'] !== (int) $payment->amount) {
            return false;
        }

        if ($normalized['currency'] !== '' && $normalized['currency'] !== (string) $payment->currency) {
            return false;
        }

        if ($payment->order()->exists() && $payment->order->currency !== $payment->currency) {
            return false;
        }

        return true;
    }

    private function transitionPayment(Payment $payment, array $normalized, WebhookEvent $event): void
    {
        $status = $payment->status;

        // An already-paid payment is final; later events must never undo it.
        if ($payment->isCompleted()) {
            return;
        }

        match ($normalized['status']) {
            'completed' => $this->markCompleted($payment, $normalized, $event),
            'failed' => $payment->update([
                'status' => Payment::STATUS_FAILED,
                'failure_reason' => 'provider reported payment failed',
            ]),
            'voided', 'cancelled', 'canceled' => $payment->update([
                'status' => Payment::STATUS_VOIDED,
                'failure_reason' => 'provider voided the payment',
            ]),
            'expired' => $payment->update([
                'status' => Payment::STATUS_EXPIRED,
                'failure_reason' => 'payment request expired',
            ]),
            default => null,
        };

        if ($status !== $payment->status) {
            $event->update([
                'status' => WebhookEvent::STATUS_PROCESSED,
                'processed_at' => now(),
            ]);
        }
    }

    private function markCompleted(Payment $payment, array $normalized, WebhookEvent $event): void
    {
        // Ask the provider to confirm before trusting the callback, unless an
        // administrator explicitly turned that guard off.
        if ($this->config->verifyOnWebhook()
            && ! $this->payments->confirmCompletion($payment)) {
            $event->update([
                'status' => WebhookEvent::STATUS_FAILED,
                'failure_reason' => 'provider did not confirm completed',
                'processed_at' => now(),
            ]);

            return;
        }

        $payment->update([
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => $normalized['paid_at'] ?? now(),
            'failure_reason' => null,
        ]);

        $payment->order()->update([
            'status' => Order::STATUS_PAID,
            'paid_at' => $payment->paid_at,
        ]);

        Log::info('abliner.payment.completed', [
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'provider_reference' => $payment->provider_reference,
        ]);
    }

    private function applyWithdrawalEvent(WebhookEvent $event, array $normalized): void
    {
        $withdrawal = $this->resolveWithdrawal($normalized);

        if ($withdrawal === null) {
            $event->update([
                'status' => WebhookEvent::STATUS_FAILED,
                'failure_reason' => 'no matching withdrawal',
                'processed_at' => now(),
            ]);

            return;
        }

        if ($withdrawal->isCompleted()) {
            return;
        }

        $status = match ($normalized['status']) {
            'completed', 'paid', 'success' => Withdrawal::STATUS_COMPLETED,
            'failed' => Withdrawal::STATUS_FAILED,
            'cancelled', 'canceled', 'reversed' => Withdrawal::STATUS_REVERSED,
            default => $withdrawal->status,
        };

        $withdrawal->update([
            'status' => $status,
            'paid_at' => $status === Withdrawal::STATUS_COMPLETED
                ? ($normalized['paid_at'] ?? now())
                : $withdrawal->paid_at,
            'failure_reason' => $status === Withdrawal::STATUS_FAILED
                ? 'provider reported payout failed'
                : $withdrawal->failure_reason,
        ]);

        $event->update([
            'status' => WebhookEvent::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);

        Log::info('abliner.withdrawal.settled', [
            'withdrawal_id' => $withdrawal->id,
            'status' => $withdrawal->status,
        ]);
    }

    private function resolveWithdrawal(array $normalized): ?Withdrawal
    {
        if ($normalized['transaction_id'] !== '') {
            $withdrawal = Withdrawal::where('provider_reference', $normalized['transaction_id'])->first();

            if ($withdrawal !== null) {
                return $withdrawal;
            }
        }

        if ($normalized['reference'] !== '') {
            $withdrawal = Withdrawal::where('external_reference', $normalized['reference'])->first();

            if ($withdrawal !== null) {
                return $withdrawal;
            }
        }

        if ($normalized['customer_reference'] !== '') {
            return Withdrawal::where('reference', $normalized['customer_reference'])->first();
        }

        return null;
    }

    private function kindFor(string $eventType): string
    {
        if (in_array($eventType, self::WITHDRAWAL_EVENTS, true)) {
            return 'withdrawal';
        }

        if (in_array($eventType, self::PAYMENT_EVENTS, true)) {
            return 'payment';
        }

        return 'other';
    }

    private function timestamp(mixed $value): ?\Illuminate\Support\Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return now()->parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function rawJson(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}