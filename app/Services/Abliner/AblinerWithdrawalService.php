<?php

namespace App\Services\Abliner;

use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Log;

/**
 * Admin-initiated payouts, via POST /api/v1/withdrawals.
 *
 * The request itself is signed with the webhook secret, exactly like a
 * collection, so the store can never accidentally send an unsigned payout that
 * the provider would reject.
 *
 * A withdrawal row is written BEFORE the API call and is updated afterwards,
 * so a provider timeout still leaves an auditable record rather than a payout
 * that may or may not have happened. The idempotency key is derived from the row
 * id, so retrying the same row never sends the money twice.
 */
class AblinerWithdrawalService
{
    /**
     * The documented minimum and maximum for a single payout, in whole TZS.
     * Abliner itself is the authority (422 amount_out_of_range); these are only
     * used to fail fast with a clear message.
     */
    public const MINIMUM_AMOUNT = 500;

    public const MAXIMUM_AMOUNT = 30000000;

    public const CURRENCY = 'TZS';

    public function __construct(
        private readonly AblinerApiClient $http,
        private readonly AblinerPaymentService $payments,
    ) {}

    public function isConfigured(): bool
    {
        return $this->http->isConfigured();
    }

    /**
     * Ask the provider what a payout would cost, without sending anything.
     *
     * Returns null when the quote could not be read; $error then explains why in
     * words that are safe to render.
     */
    public function preview(int $amount, string $method, ?string &$error = null): ?AblinerFeePreview
    {
        $error = null;

        if (! $this->isConfigured()) {
            $error = 'No Abliner API key is configured on this server.';

            return null;
        }

        [$success, $status, $body] = $this->http->get('/withdrawals/preview', [
            'method' => $method,
            'amount' => $amount,
            'currency' => self::CURRENCY,
        ]);

        if (! $success) {
            $error = $this->http->friendlyError($status, $body);

            return null;
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        if (! is_numeric($data['fee_tzs'] ?? null)) {
            $error = 'Abliner returned an unexpected fee preview response.';

            return null;
        }

        return new AblinerFeePreview(
            amount: (int) ($data['amount_tzs'] ?? $amount),
            fee: (int) $data['fee_tzs'],
            totalDebited: (int) ($data['total_debited_tzs'] ?? ($amount + (int) $data['fee_tzs'])),
            currency: strtoupper((string) ($data['currency'] ?? self::CURRENCY)),
        );
    }

    /**
     * Send a payout.
     *
     * The caller has already validated and confirmed the amount with a human;
     * this method only talks to the provider and records the outcome.
     *
     * @throws AblinerApiException
     */
    public function send(Withdrawal $withdrawal, User $actor): Withdrawal
    {
        if (! $this->isConfigured()) {
            throw new AblinerApiException('Payment provider is not configured.', 0, [], false);
        }

        if ($withdrawal->provider_reference !== null && $withdrawal->provider_reference !== '') {
            // Already handed to the provider; sending again could pay twice.
            throw new AblinerApiException('This payout has already been sent.', 0, [], false);
        }

        $payload = [
            'amount' => (int) $withdrawal->amount,
            'method' => (string) $withdrawal->method,
            'currency' => (string) $withdrawal->currency,
            'recipient' => (string) $withdrawal->recipient,
            'reference' => (string) $withdrawal->reference,
        ];

        if ($withdrawal->method === Withdrawal::METHOD_BANK) {
            $payload['bank_code'] = (string) $withdrawal->bank_code;
            $payload['account_name'] = (string) $withdrawal->account_name;
        }

        $this->log('withdrawal.send', [
            'withdrawal_id' => $withdrawal->id,
            'amount' => $withdrawal->amount,
            'method' => $withdrawal->method,
            'currency' => $withdrawal->currency,
            'actor_id' => $actor->id,
        ]);

        [$success, $statusCode, $body] = $this->http->post(
            '/withdrawals',
            $payload,
            'ebs-wd-'.$withdrawal->id,
        );

        if (! $success) {
            $withdrawal->update([
                'status' => Withdrawal::STATUS_FAILED,
                'failure_reason' => $this->http->friendlyError($statusCode, $body),
            ]);

            $this->log('withdrawal.send_failed', [
                'withdrawal_id' => $withdrawal->id,
                'status_code' => $statusCode,
                'provider_error' => $this->http->errorCode($body),
            ]);

            throw $this->http->exception($statusCode, $body);
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $transactionId = (string) ($data['id'] ?? $data['transaction_id'] ?? '');

        $withdrawal->update([
            'provider_reference' => $transactionId !== '' ? $transactionId : $withdrawal->provider_reference,
            'external_reference' => $data['reference'] ?? null,
            'status' => $this->mapProviderStatus((string) ($data['status'] ?? Withdrawal::STATUS_PENDING)),
            'fee' => is_numeric($data['fee'] ?? null) ? (int) $data['fee'] : $withdrawal->fee,
            'provider_payload' => $body,
            'failure_reason' => null,
        ]);

        $this->log('withdrawal.sent', [
            'withdrawal_id' => $withdrawal->id,
            'provider_reference' => $withdrawal->provider_reference,
            'status' => $withdrawal->status,
        ]);

        return $withdrawal->refresh();
    }

    /**
     * Re-read a payout from the provider.
     *
     * @throws AblinerApiException
     */
    public function verify(Withdrawal $withdrawal): ?string
    {
        if (! $this->isConfigured() || $withdrawal->provider_reference === null) {
            return null;
        }

        [$success, $statusCode, $body] = $this->http->get('/transactions', [
            'id' => $withdrawal->provider_reference,
        ]);

        if (! $success) {
            if ($statusCode === 404) {
                return null;
            }

            throw $this->http->exception($statusCode, $body);
        }

        $rows = $body['data'] ?? [];
        $rows = is_array($rows) && array_is_list($rows) ? $rows : (is_array($rows) ? [$rows] : []);

        foreach ($rows as $row) {
            if (is_array($row) && (string) ($row['id'] ?? '') === $withdrawal->provider_reference) {
                return isset($row['status']) ? (string) $row['status'] : null;
            }
        }

        return null;
    }

    public function mapProviderStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'paid', 'success' => Withdrawal::STATUS_COMPLETED,
            'failed' => Withdrawal::STATUS_FAILED,
            'cancelled', 'canceled', 'reversed' => Withdrawal::STATUS_REVERSED,
            default => Withdrawal::STATUS_PENDING,
        };
    }

    /**
     * Safe audit line: who asked for what, and how it ended. No recipient
     * secrets and no credentials.
     */
    private function log(string $event, array $context): void
    {
        Log::channel('stack')->info("abliner.{$event}", $context);
    }
}