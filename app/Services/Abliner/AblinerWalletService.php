<?php

namespace App\Services\Abliner;

/**
 * Read model for the admin Abliner Wallet & Payouts page.
 *
 * Every value here comes from a live API call. The wallet balance, the
 * transaction list and the payout fee preview are never cached in our own
 * database, because a stale balance on a payout screen is worse than no
 * balance at all: the operator would withdraw against money that is not there.
 *
 * Failures are converted into a result object with a safe, pre-written message
 * so no provider payload, balance or credential can reach the browser through
 * an exception.
 */
class AblinerWalletService
{
    public const DEFAULT_TRANSACTION_LIMIT = 25;

    public const MAX_TRANSACTION_LIMIT = 50;

    public function __construct(
        private readonly AblinerApiClient $http,
    ) {}

    public function isConfigured(): bool
    {
        return $this->http->isConfigured();
    }

    /**
     * The live wallet balance. Returns null when the balance could not be read,
     * in which case $error explains why in words safe to render.
     *
     * @return array{balance: int, currency: string, balances: array<string, int>, checked_at: int}|null
     */
    public function balance(?string &$error = null): ?array
    {
        $error = null;

        if (! $this->isConfigured()) {
            $error = 'No Abliner API key is configured on this server.';

            return null;
        }

        [$success, $status, $body] = $this->http->get('/balance');

        if (! $success) {
            $error = $this->http->friendlyError($status, $body);

            return null;
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        $balance = $data['balance'] ?? null;

        if (! is_numeric($balance)) {
            $error = 'Abliner returned an unexpected balance response.';

            return null;
        }

        $balances = [];

        foreach ((array) ($data['balances'] ?? []) as $code => $value) {
            if (is_numeric($value)) {
                $balances[(string) $code] = (int) $value;
            }
        }

        return [
            'balance' => (int) $balance,
            'currency' => strtoupper((string) ($data['currency'] ?? 'TZS')),
            'balances' => $balances,
            'checked_at' => now()->timestamp,
        ];
    }

    /**
     * Recent wallet activity (deposits, withdrawals, SMS top-ups).
     *
     * @return list<array<string, mixed>>
     */
    public function transactions(?int $limit = null, ?string &$error = null): array
    {
        $error = null;

        if (! $this->isConfigured()) {
            $error = 'No Abliner API key is configured on this server.';

            return [];
        }

        $limit = max(1, min($limit ?? self::DEFAULT_TRANSACTION_LIMIT, self::MAX_TRANSACTION_LIMIT));

        [$success, $status, $body] = $this->http->get('/transactions', ['limit' => $limit]);

        if (! $success) {
            $error = $this->http->friendlyError($status, $body);

            return [];
        }

        $rows = $body['data'] ?? [];
        $rows = is_array($rows) && array_is_list($rows) ? $rows : (is_array($rows) ? [$rows] : []);

        $transactions = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $transactions[] = $this->normaliseTransaction($row);
        }

        return $transactions;
    }

    /**
     * Reduce one provider transaction to the handful of fields an admin needs.
     *
     * @return array<string, mixed>
     */
    private function normaliseTransaction(array $row): array
    {
        // Abliner reports money in whole units on `amount` and cents on
        // `amount_cents`. Prefer the whole-unit figure; fall back to the cents
        // value divided down, which is what the shorter transaction list uses.
        $amount = $row['amount'] ?? null;

        if (! is_numeric($amount)) {
            $cents = $row['amount_cents'] ?? null;
            $amount = is_numeric($cents) ? ((int) $cents / 100) : null;
        }

        return [
            'id' => (string) ($row['id'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
            'method' => (string) ($row['method'] ?? ''),
            'amount' => is_numeric($amount) ? (int) $amount : null,
            'currency' => strtoupper((string) ($row['currency'] ?? 'TZS')),
            'status' => (string) ($row['status'] ?? ''),
            'reference' => (string) ($row['reference'] ?? ''),
            'customer_reference' => (string) ($row['customer_reference'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}