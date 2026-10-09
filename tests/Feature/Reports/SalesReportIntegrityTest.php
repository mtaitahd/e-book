<?php

namespace Tests\Feature\Reports;

use App\Models\Order;
use App\Reports\SalesPeriod;
use App\Reports\SalesReportService;
use App\Support\RevenueChart;
use Illuminate\Support\Facades\DB;

/**
 * A report is read-only, repeatable, and must not quietly turn into a slow
 * page. These tests cover the two operational risks of adding analytics.
 */
class SalesReportIntegrityTest extends SalesReportTestCase
{
    private function service(): SalesReportService
    {
        return app(SalesReportService::class);
    }

    private function seedSales(): void
    {
        $user = $this->customer();
        $category = $this->category('Fiction');
        $book = $this->book('Repeatable Book', '1500.00');
        $book->categories()->attach($category->id);

        for ($day = 1; $day <= 5; $day++) {
            $order = $this->paidOrder($user, '1500.00', 'TZS', sprintf('2026-05-%02d 09:00:00', $day), [
                ['book' => $book, 'unit_price' => '1500.00', 'qty' => 1],
            ]);
            $this->completedPayment($order, 'mpesa', 1500, sprintf('2026-05-%02d 09:01:00', $day));
        }
    }

    public function test_viewing_the_report_repeatedly_changes_nothing(): void
    {
        $this->seedSales();

        $ordersBefore = Order::count();
        $rowsBefore = Order::sum('total');
        $sumBefore = Order::where('status', Order::STATUS_PAID)->count();

        $admin = $this->admin();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($admin)
                ->get($this->customUrl('2026-05-01', '2026-05-31'))
                ->assertOk();
        }

        $this->assertSame($ordersBefore, Order::count());
        $this->assertSame($rowsBefore, Order::sum('total'));
        $this->assertSame($sumBefore, Order::where('status', Order::STATUS_PAID)->count());
    }

    public function test_the_report_never_writes_to_the_database(): void
    {
        $this->seedSales();

        // Create the acting admin before the log is switched on, so only the
        // report request itself is measured.
        $admin = $this->admin();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($admin)
            ->get($this->customUrl('2026-05-01', '2026-05-31'))
            ->assertOk();

        $writes = array_filter(
            DB::getQueryLog(),
            function (array $query) {
                if (preg_match('/^\s*(insert|update|delete|replace)/i', $query['query']) !== 1) {
                    return false;
                }

                // The session table is written by the framework's own session
                // middleware on every response; it is not report state.
                return ! str_contains($query['query'], 'sessions');
            },
        );

        $this->assertSame(
            [],
            array_values($writes),
            'The report must be strictly read-only.',
        );
    }

    public function test_repeated_service_calls_return_identical_numbers(): void
    {
        $this->seedSales();

        $period = SalesPeriod::between('2026-05-01', '2026-05-31');

        $first = $this->service()->summary($period);
        $second = $this->service()->summary($period);
        $third = $this->service()->summary($period);

        $this->assertSame($first, $second);
        $this->assertSame($second, $third);
    }

    public function test_the_summary_uses_a_single_aggregate_query(): void
    {
        $this->seedSales();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->service()->summary(SalesPeriod::between('2026-05-01', '2026-05-31'));

        $this->assertSame(
            1,
            $queries,
            'Revenue, orders and customers should all come from one grouped query.',
        );
    }

    public function test_the_page_does_not_run_a_query_per_order(): void
    {
        $user = $this->customer();
        $book = $this->book('Scale Test Book', '1000.00');

        for ($i = 0; $i < 15; $i++) {
            $this->paidOrder($user, '1000.00', 'TZS', sprintf('2026-05-%02d 09:00:00', ($i % 28) + 1), [
                ['book' => $book, 'unit_price' => '1000.00', 'qty' => 1],
            ]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->actingAs($this->admin())
            ->get($this->customUrl('2026-05-01', '2026-05-31'))
            ->assertOk();

        // 15 orders must not mean 15+ extra round trips. The recent-sales
        // table eager-loads its user and counts its items.
        $this->assertLessThan(
            60,
            $queries,
            "The report issued {$queries} queries; a per-order N+1 has crept back in.",
        );
    }

    public function test_an_empty_store_renders_every_panel(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.sales'))
            ->assertOk()
            ->assertSee('Total revenue')
            ->assertSee('Paid orders')
            ->assertSee('Books sold')
            ->assertSee('Customers')
            ->assertSee('Order activity')
            ->assertSee('Payment channel')
            ->assertSee('Sales by category')
            ->assertSee('Recent sales')
            ->assertSee('No sales were recorded in this period.');
    }

    public function test_every_preset_renders_successfully(): void
    {
        $this->seedSales();

        foreach (['today', 'last_7_days', 'last_30_days', 'this_month', 'last_month', 'this_year', 'all_time'] as $preset) {
            $this->actingAs($this->admin())
                ->get(route('admin.reports.sales', ['range' => $preset]))
                ->assertOk();
        }
    }

    public function test_the_chart_never_renders_for_a_store_with_no_sales(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.sales', ['range' => 'last_month']))
            ->assertOk()
            ->assertSee('No paid orders in this period.')
            ->assertDontSee('<svg class="revenue-chart"', false);
    }

    public function test_the_chart_renders_when_there_is_real_data(): void
    {
        $this->seedSales();

        $this->actingAs($this->admin())
            ->get($this->customUrl('2026-05-01', '2026-05-31'))
            ->assertOk()
            ->assertSee('<svg class="revenue-chart"', false)
            ->assertSee('role="img"', false);
    }

    public function test_the_chart_helper_handles_a_real_trend_from_the_service(): void
    {
        $this->seedSales();

        $trend = $this->service()->revenueTrend(SalesPeriod::between('2026-05-01', '2026-05-10'));

        $chart = RevenueChart::make($trend['points'], 'TZS', $trend['buckets_by']);

        $this->assertFalse($chart->isEmpty());
        // Five paid days inside a ten-day window: five real values and five
        // genuine zeros, so the series total is the five sales.
        $this->assertCount(10, $trend['points']);
        $this->assertSame(5 * 150000, $chart->totalCents());
    }

    public function test_the_existing_paid_orders_page_still_works(): void
    {
        $this->seedSales();

        $this->actingAs($this->admin())
            ->get(route('admin.orders.paid'))
            ->assertOk();
    }

    public function test_the_report_does_not_disturb_the_snippet_webhook_funnel(): void
    {
        $user = $this->customer();
        $book = $this->book('Webhook Book', '1500.00');

        $order = $this->order($user, '1500.00', 'TZS');
        $this->item($order, $book, '1500.00');
        $this->completedPayment($order, 'mpesa', 1500);
        $order->update(['status' => Order::STATUS_PENDING]);

        $pendingPayment = $order->payments()->first();
        $pendingPayment->update(['status' => 'completed', 'paid_at' => now()]);

        $summary = $this->service()->summary(SalesPeriod::between('2026-01-01', '2026-12-31'));

        $this->assertSame(0, $summary['total_orders'], 'The order is still pending until the webhook confirms it.');

        // Now confirm the order the way the verified webhook does.
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        $summary = $this->service()->summary(SalesPeriod::between('2026-01-01', '2026-12-31'));

        $this->assertSame(1, $summary['total_orders']);
        $this->assertSame('1500.00', $summary['revenue_by_currency']['TZS']);
    }
}
