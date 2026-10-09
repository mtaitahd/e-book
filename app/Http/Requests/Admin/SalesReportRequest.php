<?php

namespace App\Http\Requests\Admin;

use App\Reports\SalesPeriod;
use App\Reports\SalesReportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the sales report filter and resolves it into the single
 * SalesPeriod that every widget on the page will use.
 *
 * A named preset ("last_30_days", "this_month", ...) is resolved server-side
 * against the application's timezone, never against a browser-supplied offset,
 * so two admins looking at the same store always see the same window.
 */
class SalesReportRequest extends FormRequest
{
    /**
     * The date filters offered by the report UI.
     *
     * @var array<string, string>
     */
    public const PRESETS = [
        'today' => 'Today',
        'last_7_days' => 'Last 7 days',
        'last_30_days' => 'Last 30 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'all_time' => 'All time',
        'custom' => 'Custom range',
    ];

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'range' => ['sometimes', 'string', Rule::in(array_keys(self::PRESETS))],
            'from' => [
                Rule::requiredIf($this->selectedRange() === 'custom'),
                'nullable',
                'date_format:Y-m-d',
            ],
            'to' => [
                Rule::requiredIf($this->selectedRange() === 'custom'),
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:from',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required' => 'Choose a start date for the custom range.',
            'to.required' => 'Choose an end date for the custom range.',
            'from.date_format' => 'The start date must be a valid date.',
            'to.date_format' => 'The end date must be a valid date.',
            'to.after_or_equal' => 'The end date must not be before the start date.',
        ];
    }

    public function selectedRange(): string
    {
        $range = (string) $this->query('range', 'this_month');

        return array_key_exists($range, self::PRESETS) ? $range : 'this_month';
    }

    /**
     * The validated, timezone-aware reporting window.
     */
    public function period(): SalesPeriod
    {
        return match ($this->selectedRange()) {
            'today' => SalesPeriod::today(),
            'last_7_days' => SalesPeriod::lastDays(7, 'last_7_days'),
            'last_30_days' => SalesPeriod::lastDays(30, 'last_30_days'),
            'this_month' => SalesPeriod::thisMonth(),
            'last_month' => SalesPeriod::lastMonth(),
            'this_year' => SalesPeriod::thisYear(),
            'all_time' => $this->allTimePeriod(),
            default => SalesPeriod::between(
                (string) $this->query('from'),
                (string) $this->query('to'),
                'custom',
            ),
        };
    }

    /**
     * "All time" is bounded by the earliest genuine sale rather than sent to
     * the database as an open-ended scan, so the form always has two concrete
     * dates to show and re-submit.
     */
    private function allTimePeriod(): SalesPeriod
    {
        $earliest = app(SalesReportService::class)->earliestPaidAt()
            ?? CarbonImmutable::now(SalesPeriod::timezone());

        return SalesPeriod::allTime($earliest);
    }
}
