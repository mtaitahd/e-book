<?php

namespace App\Reports;

use Carbon\CarbonImmutable;

/**
 * The one selected reporting period, shared by every widget on the page.
 *
 * A single instance is created per request from the validated filter input and
 * handed to every report query, so the summary cards, the trend, the top books,
 * the recent sales list and the CSV export can never silently disagree about
 * which dates they describe.
 *
 * Boundaries are inclusive and are built in the application's configured
 * timezone, never the browser's:
 *
 *   from 2026-09-01 to 2026-09-30
 *     =>  2026-09-01 00:00:00 through 2026-09-30 23:59:59 (inclusive)
 *
 * Sales are dated by `orders.paid_at`, which is the only timestamp that records
 * when an order actually became a sale.
 */
final readonly class SalesPeriod
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $preset = 'custom',
    ) {}

    /**
     * Build an inclusive period from two dates, normalising to whole days in
     * the reporting timezone.
     */
    public static function between(
        \DateTimeInterface|string $from,
        \DateTimeInterface|string $to,
        string $preset = 'custom',
    ): self {
        $timezone = self::timezone();

        $start = self::startOfDay($from, $timezone);
        $end = self::startOfDay($to, $timezone)->endOfDay();

        // A defensive clamp so a hand-crafted range can never invert itself.
        if ($end->lessThan($start)) {
            $end = $start->endOfDay();
        }

        return new self($start, $end, $preset);
    }

    public static function today(string $preset = 'today'): self
    {
        $now = CarbonImmutable::now(self::timezone());

        return self::between($now, $now, $preset);
    }

    public static function lastDays(int $days, string $preset): self
    {
        $timezone = self::timezone();
        $end = CarbonImmutable::now($timezone);

        return self::between($end->subDays($days - 1), $end, $preset);
    }

    public static function thisMonth(string $preset = 'this_month'): self
    {
        $now = CarbonImmutable::now(self::timezone());

        return self::between($now->startOfMonth(), $now->endOfMonth(), $preset);
    }

    public static function lastMonth(string $preset = 'last_month'): self
    {
        $month = CarbonImmutable::now(self::timezone())->subMonthNoOverflow();

        return self::between($month->startOfMonth(), $month->endOfMonth(), $preset);
    }

    public static function thisYear(string $preset = 'this_year'): self
    {
        $now = CarbonImmutable::now(self::timezone());

        return self::between($now->startOfYear(), $now->endOfYear(), $preset);
    }

    public static function allTime(CarbonImmutable $earliest, string $preset = 'all_time'): self
    {
        $today = CarbonImmutable::now(self::timezone());

        return self::between($earliest->startOfDay(), $today, $preset);
    }

    public static function timezone(): \DateTimeZone
    {
        return new \DateTimeZone((string) config('app.timezone', 'UTC'));
    }

    /**
     * Interpret a date (or datetime) as midnight in the reporting timezone.
     */
    private static function startOfDay(\DateTimeInterface|string $value, \DateTimeZone $timezone): CarbonImmutable
    {
        $date = $value instanceof \DateTimeInterface
            ? CarbonImmutable::instance(
                $value instanceof \DateTimeImmutable
                    ? $value
                    : \DateTimeImmutable::createFromInterface($value),
            )
            : CarbonImmutable::parse($value, $timezone);

        return $date->setTimezone($timezone)->startOfDay();
    }

    public function timezoneName(): string
    {
        return self::timezone()->getName();
    }

    /** Inclusive lower bound, ready for a `whereBetween`. */
    public function sqlFrom(): string
    {
        return $this->from->format('Y-m-d H:i:s');
    }

    /** Inclusive upper bound, ready for a `whereBetween`. */
    public function sqlTo(): string
    {
        return $this->to->format('Y-m-d H:i:s');
    }

    public function fromDate(): string
    {
        return $this->from->format('Y-m-d');
    }

    public function toDate(): string
    {
        return $this->to->format('Y-m-d');
    }

    public function dayCount(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * Long ranges are bucketed by month so the trend stays readable and the
     * chart never has to draw thousands of points. Daily is used whenever the
     * range is short enough to be genuinely informative.
     */
    public function bucketsBy(): string
    {
        return $this->dayCount() > 92 ? 'month' : 'day';
    }

    /**
     * Every bucket key in the period, in order, including days with no sales.
     * A zero is a real, honest value: the day existed and nothing was sold.
     *
     * @return list<array{key: string, label: string}>
     */
    public function buckets(): array
    {
        $timezone = self::timezone();
        $buckets = [];

        if ($this->bucketsBy() === 'month') {
            $cursor = $this->from->startOfMonth();
            $last = $this->to->startOfMonth();

            while ($cursor->lessThanOrEqualTo($last)) {
                $buckets[] = [
                    'key' => $cursor->format('Y-m'),
                    'label' => $cursor->format('M Y'),
                ];
                $cursor = $cursor->addMonthNoOverflow();
            }

            return $buckets;
        }

        $cursor = $this->from->startOfDay();
        $last = $this->to->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $buckets[] = [
                'key' => $cursor->format('Y-m-d'),
                'label' => $cursor->format('j M'),
            ];
            $cursor = $cursor->addDay();
        }

        unset($timezone);

        return $buckets;
    }

    /**
     * Convert a database day (as returned by DATE(paid_at)) into the bucket it
     * belongs to for this period's granularity.
     */
    public function bucketKeyFor(string $day): string
    {
        return $this->bucketsBy() === 'month'
            ? substr($day, 0, 7)
            : substr($day, 0, 10);
    }

    public function label(): string
    {
        return match ($this->preset) {
            'today' => 'Today',
            'last_7_days' => 'Last 7 days',
            'last_30_days' => 'Last 30 days',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_year' => 'This year',
            'all_time' => 'All time',
            default => $this->from->format('j M Y').' – '.$this->to->format('j M Y'),
        };
    }

    public function isCustom(): bool
    {
        return $this->preset === 'custom';
    }
}
