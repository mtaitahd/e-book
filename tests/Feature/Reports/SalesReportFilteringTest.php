<?php

namespace Tests\Feature\Reports;

use App\Http\Requests\Admin\SalesReportRequest;
use App\Models\Order;
use App\Reports\SalesPeriod;
use App\Reports\SalesReportService;
use Carbon\CarbonImmutable;

/**
 * Every widget on the page shares one inclusive, timezone-aware window.
 * These tests prove the window itself is correct and that the defaults,
 * validation and presets all behave.
 */
class SalesReportFilteringTest extends SalesReportTestCase
{
    private function service(): SalesReportService
    {
        return app(SalesReportService::class);
    }

    private function revenue(string $from, string $to): string
    {
        $summary = $this->service()->summary(SalesPeriod::between($from, $to));

        return $summary['revenue_by_currency']['TZS'] ?? '0.00';
    }

    public function test_the_range_is_inclusive_on_both_ends(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '100.00', 'TZS', '2026-05-01 00:00:00');
        $this->paidOrder($user, '200.00', 'TZS', '2026-05-15 12:30:00');
        $this->paidOrder($user, '300.00', 'TZS', '2026-05-31 23:59:59');

        $this->assertSame('600.00', $this->revenue('2026-05-01', '2026-05-31'));
        $this->assertSame('100.00', $this->revenue('2026-05-01', '2026-05-01'));
        $this->assertSame('300.00', $this->revenue('2026-05-31', '2026-05-31'));
    }

    public function test_a_sale_one_second_outside_the_window_is_excluded(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '100.00', 'TZS', '2026-05-01 00:00:00');
        $this->paidOrder($user, '999.00', 'TZS', '2026-06-01 00:00:00');

        $this->assertSame('100.00', $this->revenue('2026-05-01', '2026-05-31'));
    }

    public function test_the_default_period_is_the_current_month(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 12:00:00', 'UTC'));

        $period = $this->resolvePeriod();

        $this->assertSame('2026-05-01', $period->fromDate());
        $this->assertSame('2026-05-31', $period->toDate());
        $this->assertSame('this_month', $period->preset);
    }

    public function test_the_default_period_uses_the_app_timezone_not_the_browser(): void
    {
        config()->set('app.timezone', 'Africa/Dar_es_Salaam');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 12:00:00', 'UTC'));

        $period = $this->resolvePeriod();

        $this->assertSame('Africa/Dar_es_Salaam', $period->timezoneName());
        // 12:00 UTC is 15:00 in Dar es Salaam, so the local month is unchanged
        // but the day boundary is expressed in the app zone.
        $this->assertSame('2026-05-01', $period->fromDate());
    }

    public function test_last_7_days_preset_covers_exactly_seven_days(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 12:00:00', 'UTC'));

        $period = $this->resolvePeriod(['range' => 'last_7_days']);

        $this->assertSame('2026-05-14', $period->fromDate());
        $this->assertSame('2026-05-20', $period->toDate());
        $this->assertSame(7, $period->dayCount());
    }

    public function test_last_30_days_preset_covers_exactly_thirty_days(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 12:00:00', 'UTC'));

        $period = $this->resolvePeriod(['range' => 'last_30_days']);

        $this->assertSame(30, $period->dayCount());
        $this->assertSame('2026-04-21', $period->fromDate());
    }

    public function test_today_preset_is_a_single_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 18:45:00', 'UTC'));

        $period = $this->resolvePeriod(['range' => 'today']);

        $this->assertSame(1, $period->dayCount());
        $this->assertSame('2026-05-20', $period->fromDate());
        $this->assertSame('2026-05-20', $period->toDate());
    }

    public function test_last_month_preset_covers_the_previous_calendar_month(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 12:00:00', 'UTC'));

        $period = $this->resolvePeriod(['range' => 'last_month']);

        $this->assertSame('2026-04-01', $period->fromDate());
        $this->assertSame('2026-04-30', $period->toDate());
    }

    public function test_this_year_preset_covers_the_calendar_year(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 12:00:00', 'UTC'));

        $period = $this->resolvePeriod(['range' => 'this_year']);

        $this->assertSame('2026-01-01', $period->fromDate());
        $this->assertSame('2026-12-31', $period->toDate());
    }

    public function test_all_time_preset_starts_at_the_earliest_sale(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '100.00', 'TZS', '2026-02-09 10:00:00');
        $this->paidOrder($user, '200.00', 'TZS', '2026-05-09 10:00:00');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 12:00:00', 'UTC'));

        $period = $this->resolvePeriod(['range' => 'all_time']);

        $this->assertSame('2026-02-09', $period->fromDate());
        $this->assertSame('2026-05-20', $period->toDate());
    }

    public function test_all_time_preset_on_an_empty_store_does_not_fail(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 12:00:00', 'UTC'));

        $period = $this->resolvePeriod(['range' => 'all_time']);

        $this->assertSame('2026-05-20', $period->fromDate());
    }

    public function test_an_unknown_preset_is_rejected_rather_than_guessed(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.sales', ['range' => 'nonsense']))
            ->assertSessionHasErrors('range');
    }

    public function test_a_custom_range_is_used_verbatim(): void
    {
        $period = $this->resolvePeriod(['range' => 'custom', 'from' => '2026-03-05', 'to' => '2026-03-20']);

        $this->assertSame('2026-03-05', $period->fromDate());
        $this->assertSame('2026-03-20', $period->toDate());
        $this->assertSame('custom', $period->preset);
        $this->assertTrue($period->isCustom());
    }

    public function test_a_custom_range_requires_both_dates(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.sales', ['range' => 'custom']))
            ->assertSessionHasErrors('from')
            ->assertSessionHasErrors('to');
    }

    public function test_a_custom_range_rejects_a_reversed_range(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.sales', [
                'range' => 'custom',
                'from' => '2026-05-20',
                'to' => '2026-05-01',
            ]))
            ->assertSessionHasErrors('to');
    }

    public function test_a_custom_range_rejects_impossible_dates(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.sales', [
                'range' => 'custom',
                'from' => 'not-a-date',
                'to' => '2026-05-01',
            ]))
            ->assertSessionHasErrors('from');
    }

    /**
     * A hostile filter string must never reach SQL as anything but a bound,
     * rejected parameter.
     */
    public function test_filter_input_cannot_be_used_for_injection(): void
    {
        $user = $this->customer();
        $this->paidOrder($user, '100.00', 'TZS', '2026-05-10 09:00:00');

        $this->actingAs($this->admin())
            ->get(route('admin.reports.sales', [
                'range' => 'custom',
                'from' => "2026-05-01' OR 1=1 --",
                'to' => '2026-05-31',
            ]))
            ->assertSessionHasErrors('from');

        $this->assertSame(1, Order::count(), 'No rows were altered.');
    }

    public function test_the_filter_state_survives_pagination(): void
    {
        $user = $this->customer();

        for ($i = 0; $i < 20; $i++) {
            $this->paidOrder($user, '100.00', 'TZS', '2026-05-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).' 09:00:00');
        }

        $this->actingAs($this->admin())
            ->get($this->customUrl('2026-05-01', '2026-05-31').'&page=2')
            ->assertOk()
            ->assertSee('range=custom', false)
            ->assertSee('from=2026-05-01', false);
    }

    public function test_long_ranges_are_bucketed_by_month(): void
    {
        $period = SalesPeriod::between('2025-01-01', '2026-05-31');

        $this->assertSame('month', $period->bucketsBy());
        $this->assertCount(17, $period->buckets());
    }

    public function test_short_ranges_are_bucketed_by_day(): void
    {
        $period = SalesPeriod::between('2026-05-01', '2026-05-31');

        $this->assertSame('day', $period->bucketsBy());
        $this->assertCount(31, $period->buckets());
    }

    public function test_the_period_boundaries_are_inclusive_sql_timestamps(): void
    {
        $period = SalesPeriod::between('2026-05-01', '2026-05-31');

        $this->assertSame('2026-05-01 00:00:00', $period->sqlFrom());
        $this->assertSame('2026-05-31 23:59:59', $period->sqlTo());
    }

    public function test_every_preset_is_offered_by_the_filter(): void
    {
        $this->assertArrayHasKey('today', SalesReportRequest::PRESETS);
        $this->assertArrayHasKey('custom', SalesReportRequest::PRESETS);
        $this->assertArrayHasKey('all_time', SalesReportRequest::PRESETS);
    }

    private function resolvePeriod(array $query = []): SalesPeriod
    {
        $request = SalesReportRequest::create(
            route('admin.reports.sales', $query),
            'GET',
            $query,
        );
        $request->setContainer(app())->setRedirector(app('redirect'));

        app()->instance('request', $request);

        $request->setUserResolver(fn () => $this->admin());
        $request->validateResolved();

        return $request->period();
    }
}
