<?php

namespace App\Settings;

use App\Models\Payment;
use App\Reports\SalesPeriod;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Real payment activity, counted from the payments table.
 *
 * No synthetic data, no placeholders, and no customer's phone number or
 * reference — only counts and the most recent timestamps. Timestamps are
 * rendered in the same reporting timezone the sales report uses, so the two
 * pages cannot disagree about what day something happened.
 */
final readonly class PaymentActivitySummary
{
    /**
     * @param  array<string, int>  $byStatus
     */
    public function __construct(
        public int $total,
        public int $completed,
        public int $pending,
        public int $failed,
        public int $discarded,
        public array $byStatus,
        public ?CarbonImmutable $lastPaymentAt,
        public ?CarbonImmutable $lastCompletedAt,
        public string $timezone,
    ) {}

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }

    public function lastPaymentLabel(): string
    {
        return $this->lastPaymentAt?->format('d M Y, H:i') ?? 'No payments yet';
    }

    public function lastCompletedLabel(): string
    {
        return $this->lastCompletedAt?->format('d M Y, H:i') ?? 'No completed payments yet';
    }

    /**
     * @param  array<string, int>  $rows
     */
    public static function fromCounts(array $rows, ?string $lastPaymentAt, ?string $lastCompletedAt): self
    {
        $byStatus = [];

        foreach ($rows as $status => $count) {
            $byStatus[(string) $status] = (int) $count;
        }

        $timezone = SalesPeriod::timezone()->getName();

        return new self(
            total: array_sum($byStatus),
            completed: $byStatus[Payment::STATUS_COMPLETED] ?? 0,
            pending: $byStatus[Payment::STATUS_PENDING] ?? 0,
            failed: $byStatus[Payment::STATUS_FAILED] ?? 0,
            // Voided and expired are real outcomes, not successes and not bugs,
            // so they are counted but never presented as revenue.
            discarded: ($byStatus[Payment::STATUS_VOIDED] ?? 0)
                + ($byStatus[Payment::STATUS_EXPIRED] ?? 0),
            byStatus: $byStatus,
            lastPaymentAt: self::toReportingTime($lastPaymentAt, $timezone),
            lastCompletedAt: self::toReportingTime($lastCompletedAt, $timezone),
            timezone: $timezone,
        );
    }

    private static function toReportingTime(?string $value, string $timezone): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        return CarbonImmutable::parse($value)->setTimezone($timezone);
    }

    public function money(int $amount, string $currency = 'TZS'): string
    {
        return Money::formatWhole($amount, $currency);
    }
}
