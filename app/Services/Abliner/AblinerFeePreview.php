<?php

namespace App\Services\Abliner;

/**
 * The provider's fee quote for a payout, before anything is sent.
 *
 * Read-only and safe to display: it contains money figures and nothing else,
 * no credential and no raw provider payload.
 */
final readonly class AblinerFeePreview
{
    public function __construct(
        public int $amount,
        public int $fee,
        public int $totalDebited,
        public string $currency,
    ) {}

    public function feePercentage(): string
    {
        if ($this->amount <= 0) {
            return '—';
        }

        return number_format(($this->fee / $this->amount) * 100, 2).'%';
    }
}