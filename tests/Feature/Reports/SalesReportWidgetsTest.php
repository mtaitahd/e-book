<?php

namespace Tests\Feature\Reports;

use App\Reports\SalesPeriod;
use App\Reports\SalesReportService;

/**
 * The breakdown widgets: trend, top books, categories, customers, order
 * activity and payment channels.
 */
class SalesReportWidgetsTest extends SalesReportTestCase
{
    private function service(): SalesReportService
    {
        return app(SalesReportService::class);
    }

    private function period(string $from = '2026-05-01', string $to = '2026-05-31'): SalesPeriod
    {
        return SalesPeriod::between($from, $to);
    }

    public function test_the_trend_has_one_point_per_day_with_zeros_filled_in(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '1000.00', 'TZS', '2026-05-02 09:00:00');
        $this->paidOrder($user, '2500.00', 'TZS', '2026-05-05 09:00:00');

        $trend = $this->service()->revenueTrend($this->period('2026-05-01', '2026-05-07'));

        $this->assertSame(7, count($trend['points']));
        $this->assertTrue($trend['has_sales']);
        $this->assertSame('1000.00', $trend['points'][1]['value']);
        $this->assertSame('0.00', $trend['points'][0]['value'], 'A day with no sales is a real zero.');
        $this->assertSame('0.00', $trend['points'][6]['value']);
        $this->assertSame(250000, $trend['peak_cents']);
    }

    public function test_the_trend_groups_sales_into_the_correct_day(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '100.00', 'TZS', '2026-05-01 00:00:00');
        $this->paidOrder($user, '200.00', 'TZS', '2026-05-01 23:59:59');

        $trend = $this->service()->revenueTrend($this->period('2026-05-01', '2026-05-01'));

        $this->assertCount(1, $trend['points']);
        $this->assertSame('300.00', $trend['points'][0]['value']);
    }

    public function test_the_trend_reports_no_sales_for_an_empty_period(): void
    {
        $trend = $this->service()->revenueTrend($this->period('2026-01-01', '2026-01-05'));

        $this->assertCount(5, $trend['points']);
        $this->assertFalse($trend['has_sales']);
        $this->assertSame(0, $trend['peak_cents']);
    }

    public function test_top_books_rank_by_copies_sold(): void
    {
        $user = $this->customer();
        $popular = $this->book('Popular Book', '1000.00');
        $rare = $this->book('Rare Book', '9000.00');

        $this->paidOrder($user, '3000.00', 'TZS', '2026-05-10 09:00:00', [
            ['book' => $popular, 'unit_price' => '1000.00', 'qty' => 3],
        ]);
        $this->paidOrder($user, '9000.00', 'TZS', '2026-05-11 09:00:00', [
            ['book' => $rare, 'unit_price' => '9000.00', 'qty' => 1],
        ]);

        $books = $this->service()->topBooks($this->period());

        $this->assertSame('Popular Book', $books[0]['title']);
        $this->assertSame(3, $books[0]['copies']);
        $this->assertSame('3,000.00 TZS', $books[0]['revenue_formatted']);
        $this->assertSame('Rare Book', $books[1]['title']);
    }

    /**
     * Historic sales must keep the price that was actually paid, even after
     * the book has been repriced.
     */
    public function test_top_books_use_the_historical_price_not_the_current_one(): void
    {
        $user = $this->customer();
        $book = $this->book('Repriced Book', '1000.00');

        $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00', [
            ['book' => $book, 'unit_price' => '1000.00', 'qty' => 1],
        ]);

        $book->update(['price' => '5500.00']);

        $books = $this->service()->topBooks($this->period());

        $this->assertSame('1,000.00 TZS', $books[0]['revenue_formatted']);
    }

    public function test_top_books_ignores_unpaid_orders(): void
    {
        $user = $this->customer();
        $book = $this->book('Unpaid Book', '1000.00');

        $order = $this->order($user, '1000.00', 'TZS', '2026-05-10 09:00:00');
        $this->item($order, $book);

        $this->assertSame([], $this->service()->topBooks($this->period()));
    }

    public function test_top_books_respects_the_limit(): void
    {
        $user = $this->customer();

        for ($i = 1; $i <= 4; $i++) {
            $book = $this->book('Book '.$i, '1000.00');
            $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00', [
                ['book' => $book, 'unit_price' => '1000.00', 'qty' => $i],
            ]);
        }

        $this->assertCount(4, $this->service()->topBooks($this->period(), 10));
        $this->assertCount(2, $this->service()->topBooks($this->period(), 2));
    }

    public function test_top_customers_rank_by_total_spent(): void
    {
        $big = $this->customer('Big Spender');
        $small = $this->customer('Small Spender');

        $this->paidOrder($big, '5000.00', 'TZS', '2026-05-10 09:00:00');
        $this->paidOrder($big, '3000.00', 'TZS', '2026-05-11 09:00:00');
        $this->paidOrder($small, '1000.00', 'TZS', '2026-05-12 09:00:00');

        $customers = $this->service()->topCustomers($this->period());

        $this->assertSame('Big Spender', $customers[0]['name']);
        $this->assertSame(2, $customers[0]['orders']);
        $this->assertSame('8,000.00 TZS', $customers[0]['spent_formatted']);
    }

    /**
     * A multi-book order must not multiply its own total when the line items
     * are joined in to count copies.
     */
    public function test_a_multi_book_order_is_not_counted_twice_in_customer_spend(): void
    {
        $user = $this->customer();
        $first = $this->book('First', '1000.00');
        $second = $this->book('Second', '2000.00');

        $this->paidOrder($user, '3000.00', 'TZS', '2026-05-10 09:00:00', [
            ['book' => $first, 'unit_price' => '1000.00', 'qty' => 1],
            ['book' => $second, 'unit_price' => '2000.00', 'qty' => 1],
        ]);

        $customers = $this->service()->topCustomers($this->period());

        $this->assertSame('3,000.00 TZS', $customers[0]['spent_formatted']);
        $this->assertSame(1, $customers[0]['orders']);
        $this->assertSame(2, $customers[0]['copies']);
    }

    public function test_sales_by_category_aggregates_paid_orders(): void
    {
        $user = $this->customer();
        $fiction = $this->category('Fiction');
        $book = $this->book('Novel', '1000.00');
        $book->categories()->attach($fiction->id);

        $this->paidOrder($user, '2000.00', 'TZS', '2026-05-10 09:00:00', [
            ['book' => $book, 'unit_price' => '1000.00', 'qty' => 2],
        ]);

        $categories = $this->service()->salesByCategory($this->period());

        $this->assertSame('Fiction', $categories[0]['name']);
        $this->assertSame(2, $categories[0]['copies']);
        $this->assertSame('2,000.00 TZS', $categories[0]['revenue_formatted']);
    }

    public function test_sales_by_category_is_empty_when_nothing_sold(): void
    {
        $this->assertSame([], $this->service()->salesByCategory($this->period()));
    }

    public function test_order_activity_counts_every_status_but_flags_only_paid_as_revenue(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00');
        $this->order($user, '500.00', 'TZS', '2026-05-11 09:00:00', 'pending');
        $this->order($user, '600.00', 'TZS', '2026-05-12 09:00:00', 'cancelled');
        $this->order($user, '700.00', 'TZS', '2026-05-13 09:00:00', 'failed');

        $rows = collect($this->service()->orderStatusSummary($this->period()))->keyBy('status');

        $this->assertSame(1, $rows['paid']['orders']);
        $this->assertTrue($rows['paid']['is_revenue']);
        $this->assertSame('1,000.00 TZS', $rows['paid']['formatted']);

        $this->assertSame(1, $rows['pending']['orders']);
        $this->assertFalse($rows['pending']['is_revenue']);
        $this->assertSame(1, $rows['cancelled']['orders']);
        $this->assertFalse($rows['cancelled']['is_revenue']);
        $this->assertSame(1, $rows['failed']['orders']);
        $this->assertFalse($rows['failed']['is_revenue']);
    }

    public function test_order_activity_never_mixes_currencies(): void
    {
        $user = $this->customer();

        $this->order($user, '1000.00', 'TZS', '2026-05-10 09:00:00', 'pending');
        $this->order($user, '900.00', 'KES', '2026-05-11 09:00:00', 'pending');

        $rows = collect($this->service()->orderStatusSummary($this->period()))->keyBy('status');
        $pending = $rows['pending'];

        $this->assertSame(2, $pending['orders']);
        $this->assertCount(2, $pending['values']);
        $this->assertStringContainsString('1,000.00 TZS', $pending['formatted']);
        $this->assertStringContainsString('900.00 KES', $pending['formatted']);
    }

    public function test_payment_channels_report_nothing_available_without_payment_rows(): void
    {
        $this->paidOrder($this->customer(), '1000.00', 'TZS', '2026-05-10 09:00:00');

        $channels = $this->service()->paymentChannels($this->period());

        $this->assertFalse($channels['available']);
        $this->assertSame([], $channels['rows']);
        $this->assertStringContainsString('No payment records', $channels['reason']);
    }

    public function test_payment_channels_group_by_the_stored_channel(): void
    {
        $user = $this->customer();

        $first = $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00');
        $second = $this->paidOrder($user, '2000.00', 'TZS', '2026-05-11 09:00:00');

        $this->completedPayment($first, 'mpesa', 1000, '2026-05-10 09:01:00');
        $this->completedPayment($second, 'mpesa', 2000, '2026-05-11 09:01:00');

        $channels = $this->service()->paymentChannels($this->period());

        $this->assertTrue($channels['available']);
        $this->assertCount(1, $channels['rows']);
        $this->assertSame('M-Pesa', $channels['rows'][0]['label']);
        $this->assertSame(2, $channels['rows'][0]['payments']);
        $this->assertSame('3,000 TZS', $channels['rows'][0]['amount_formatted']);
    }

    public function test_payment_channels_are_not_guessed_from_a_phone_number(): void
    {
        $user = $this->customer();
        $user->forceFill(['phone' => '255754123456'])->save();

        $order = $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00');
        $this->completedPayment($order, null, 1000, '2026-05-10 09:01:00');

        $channels = $this->service()->paymentChannels($this->period());

        $this->assertTrue($channels['available']);
        $this->assertSame('Channel not recorded', $channels['rows'][0]['label']);
    }

    public function test_recent_sales_are_paginated_and_only_paid(): void
    {
        $user = $this->customer();

        for ($i = 0; $i < 18; $i++) {
            $this->paidOrder($user, '100.00', 'TZS', '2026-05-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).' 09:00:00');
        }
        $this->order($user, '999.00', 'TZS', '2026-05-10 10:00:00', 'pending');

        $page = $this->service()->recentSales($this->period(), 15);

        $this->assertSame(18, $page->total());
        $this->assertCount(15, $page->items());
        $this->assertTrue(
            collect($page->items())->every(fn ($order) => $order->isPaid()),
            'Only paid orders may reach the recent-sales table.',
        );
        $this->assertTrue(
            collect($page->items())->every(fn ($order) => $order->paid_at !== null),
        );
    }

    public function test_the_report_page_renders_every_widget_for_real_data(): void
    {
        $user = $this->customer();
        $category = $this->category('Fiction');
        $book = $this->book('Real Book', '1000.00');
        $book->categories()->attach($category->id);

        $order = $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00', [
            ['book' => $book, 'unit_price' => '1000.00', 'qty' => 2],
        ]);
        $this->completedPayment($order, 'mpesa', 1000, '2026-05-10 09:01:00');

        $this->actingAs($this->admin())
            ->get($this->customUrl('2026-05-01', '2026-05-31'))
            ->assertOk()
            ->assertSee('1,000.00 TZS')
            ->assertSee('Real Book')
            ->assertSee('Fiction')
            ->assertSee('M-Pesa')
            ->assertSee('Recent sales')
            ->assertSee('Order activity')
            ->assertSee('Top books')
            ->assertSee('Best customers')
            ->assertDontSee('No sales were recorded in this period.');
    }

    public function test_the_report_page_explains_the_category_double_count(): void
    {
        $user = $this->customer();
        $first = $this->category('Fiction');
        $second = $this->category('Education');
        $book = $this->book('Cross Listed', '1000.00');
        $book->categories()->attach([$first->id, $second->id]);

        $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00', [
            ['book' => $book, 'unit_price' => '1000.00', 'qty' => 1],
        ]);

        $this->actingAs($this->admin())
            ->get($this->customUrl('2026-05-01', '2026-05-31'))
            ->assertOk()
            ->assertSee('These figures are not additive')
            ->assertSee('Fiction')
            ->assertSee('Education');
    }
}
