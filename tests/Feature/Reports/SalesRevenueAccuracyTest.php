<?php

namespace Tests\Feature\Reports;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Purchase;
use App\Reports\SalesPeriod;
use App\Reports\SalesReportService;

/**
 * The central promise of Stage 10: revenue means money that was actually
 * collected. These tests pin that down against the ways it could silently
 * go wrong.
 */
class SalesRevenueAccuracyTest extends SalesReportTestCase
{
    private function service(): SalesReportService
    {
        return app(SalesReportService::class);
    }

    private function period(string $from, string $to): SalesPeriod
    {
        return SalesPeriod::between($from, $to);
    }

    public function test_total_revenue_sums_paid_orders_in_the_range(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00');
        $this->paidOrder($user, '2500.50', 'TZS', '2026-05-11 09:00:00');
        $this->paidOrder($user, '  500.25', 'TZS', '2026-05-12 09:00:00');

        $summary = $this->service()->summary($this->period('2026-05-01', '2026-05-31'));

        $this->assertSame(3, $summary['total_orders']);
        $this->assertSame('4000.75', $summary['revenue_by_currency']['TZS']);
        $this->assertSame(1, $summary['total_customers']);
    }

    public function test_pending_orders_are_never_revenue(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00');
        $this->order($user, '9000.00', 'TZS', '2026-05-10 10:00:00', Order::STATUS_PENDING);

        $summary = $this->service()->summary($this->period('2026-05-01', '2026-05-31'));

        $this->assertSame('1000.00', $summary['revenue_by_currency']['TZS']);
        $this->assertSame(1, $summary['total_orders']);
    }

    public function test_cancelled_and_failed_orders_are_never_revenue(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00');
        $this->order($user, '7000.00', 'TZS', '2026-05-10 10:00:00', Order::STATUS_CANCELLED);
        $this->order($user, '6000.00', 'TZS', '2026-05-10 11:00:00', Order::STATUS_FAILED);

        $summary = $this->service()->summary($this->period('2026-05-01', '2026-05-31'));

        $this->assertSame('1000.00', $summary['revenue_by_currency']['TZS']);
        $this->assertSame(1, $summary['total_orders']);
    }

    /**
     * A payment row alone is not a sale. Revenue follows the order, so an
     * inconsistent payment row cannot invent money.
     */
    public function test_completed_payment_row_without_a_paid_order_is_not_revenue(): void
    {
        $user = $this->customer();
        $book = $this->book();

        $pending = $this->order($user, '5000.00', 'TZS', '2026-05-10 09:00:00');
        $this->item($pending, $book, '5000.00');
        $this->completedPayment($pending, 'mpesa', 5000, '2026-05-10 09:05:00');

        $summary = $this->service()->summary($this->period('2026-05-01', '2026-05-31'));

        $this->assertSame(0, $summary['total_orders']);
        $this->assertSame([], $summary['revenue_by_currency']);
    }

    /**
     * Entitlements are created for paid orders, but they are a record of
     * ownership, not of money. They must not be added to anything.
     */
    public function test_purchase_entitlements_do_not_inflate_revenue(): void
    {
        $user = $this->customer();
        $book = $this->book();

        $order = $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00', [
            ['book' => $book, 'unit_price' => '1000.00', 'qty' => 2],
        ]);

        $this->assertGreaterThan(0, Purchase::where('order_id', $order->id)->count());

        $summary = $this->service()->summary($this->period('2026-05-01', '2026-05-31'));

        $this->assertSame('1000.00', $summary['revenue_by_currency']['TZS']);
        $this->assertSame(1, $summary['total_orders']);
    }

    /**
     * The sale belongs to the day it was paid, not the day the order row was
     * created. A customer who checked out yesterday and paid today is a sale
     * today.
     */
    public function test_sales_are_dated_by_paid_at_not_created_at(): void
    {
        $user = $this->customer();

        $order = $this->order($user, '3000.00', 'TZS', '2026-05-01 08:00:00');
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => '2026-05-20 15:00:00']);

        $paidMay = $this->service()->summary($this->period('2026-05-15', '2026-05-25'));
        $createdMay = $this->service()->summary($this->period('2026-05-01', '2026-05-10'));

        $this->assertSame('3000.00', $paidMay['revenue_by_currency']['TZS']);
        $this->assertArrayNotHasKey('TZS', $createdMay['revenue_by_currency']);
    }

    public function test_paid_order_with_a_null_paid_at_is_excluded(): void
    {
        $user = $this->customer();

        $order = $this->order($user, '4000.00', 'TZS', '2026-05-10 09:00:00');
        // Force the inconsistent state directly, bypassing the model hook.
        $order->newQuery()->whereKey($order->id)->update([
            'status' => Order::STATUS_PAID,
            'paid_at' => null,
        ]);

        $summary = $this->service()->summary($this->period('2026-05-01', '2026-05-31'));

        $this->assertSame(0, $summary['total_orders']);
    }

    public function test_a_free_book_sale_counts_as_a_sale_of_zero(): void
    {
        $user = $this->customer();
        $book = $this->book('Free Guide', '0.00');

        $this->paidOrder($user, '0.00', 'TZS', '2026-05-10 09:00:00', [
            ['book' => $book, 'unit_price' => '0.00', 'qty' => 1],
        ]);

        $period = $this->period('2026-05-01', '2026-05-31');

        $summary = $this->service()->summary($period);

        $this->assertSame(1, $summary['total_orders'], 'A zero-value claim is still a completed sale.');
        $this->assertSame('0.00', $summary['revenue_by_currency']['TZS']);
        $this->assertSame(1, $this->service()->copiesSold($period), 'A book was genuinely delivered.');
    }

    public function test_currencies_are_never_added_together(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00');
        $this->paidOrder($user, '900.00', 'KES', '2026-05-11 09:00:00');

        $summary = $this->service()->summary($this->period('2026-05-01', '2026-05-31'));

        $this->assertSame('1000.00', $summary['revenue_by_currency']['TZS']);
        $this->assertSame('900.00', $summary['revenue_by_currency']['KES']);

        // The legacy currency is surfaced, never merged into the store total.
        $this->assertSame(['KES' => '900.00'], $summary['other_currencies']);
        $this->assertSame('TZS', $summary['store_currency']);
    }

    public function test_books_sold_counts_copies_not_orders(): void
    {
        $user = $this->customer();
        $first = $this->book('Book A', '1000.00');
        $second = $this->book('Book B', '500.00');

        $this->paidOrder($user, '2500.00', 'TZS', '2026-05-10 09:00:00', [
            ['book' => $first, 'unit_price' => '1000.00', 'qty' => 2],
            ['book' => $second, 'unit_price' => '500.00', 'qty' => 1],
        ]);

        $this->assertSame(3, $this->service()->copiesSold($this->period('2026-05-01', '2026-05-31')));
    }

    public function test_average_order_value_is_computed_per_currency(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00');
        $this->paidOrder($user, '2000.00', 'TZS', '2026-05-11 09:00:00');

        $summary = $this->service()->summary($this->period('2026-05-01', '2026-05-31'));

        $this->assertSame('1500.00', $summary['average_order_value']['TZS']);
    }

    public function test_summary_is_zero_for_a_store_with_no_paid_orders(): void
    {
        $user = $this->customer();
        $this->order($user, '1000.00', 'TZS', '2026-05-10 09:00:00', Order::STATUS_PENDING);

        $summary = $this->service()->summary($this->period('2026-05-01', '2026-05-31'));

        $this->assertSame(0, $summary['total_orders']);
        $this->assertSame(0, $summary['total_customers']);
        $this->assertSame([], $summary['revenue_by_currency']);
        $this->assertSame([], $summary['average_order_value']);
    }

    public function test_unique_customers_are_counted_once_per_period(): void
    {
        $first = $this->customer('A One');
        $second = $this->customer('B Two');

        $this->paidOrder($first, '1000.00', 'TZS', '2026-05-10 09:00:00');
        $this->paidOrder($first, '1500.00', 'TZS', '2026-05-12 09:00:00');
        $this->paidOrder($second, '2000.00', 'TZS', '2026-05-13 09:00:00');

        $summary = $this->service()->summary($this->period('2026-05-01', '2026-05-31'));

        $this->assertSame(3, $summary['total_orders']);
        $this->assertSame(2, $summary['total_customers']);
    }

    public function test_the_report_page_shows_zero_rather_than_fabricating_numbers(): void
    {
        $this->actingAs($this->admin())
            ->get($this->customUrl('2026-05-01', '2026-05-31'))
            ->assertOk()
            ->assertSee('No sales were recorded in this period.')
            ->assertSee('No paid orders in this period.')
            ->assertSee('Not available yet.');
    }

    public function test_the_report_page_never_prints_raw_personal_or_secret_columns(): void
    {
        $user = $this->customer('Private Person');
        $user->forceFill(['email' => 'private.person@example.test', 'phone' => '255700111222'])->save();

        $book = $this->book('Paid Title', '1200.00');
        $order = $this->paidOrder($user, '1200.00', 'TZS', '2026-05-10 09:00:00', [
            ['book' => $book, 'unit_price' => '1200.00', 'qty' => 1],
        ]);
        $this->completedPayment($order, 'mpesa', 1200);

        $response = $this->actingAs($this->admin())
            ->get($this->customUrl('2026-05-01', '2026-05-31'));

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringNotContainsString('private.person@example.test', $html);
        $this->assertStringNotContainsString('255700111222', $html);
        $this->assertStringNotContainsString('SNIP-', $html);
    }

    public function test_orders_outside_the_period_are_not_counted_in_revenue(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00');
        $this->paidOrder($user, '5000.00', 'TZS', '2026-06-10 09:00:00');
        $this->paidOrder($user, '7000.00', 'TZS', '2026-04-10 09:00:00');

        $summary = $this->service()->summary($this->period('2026-05-01', '2026-05-31'));

        $this->assertSame('1000.00', $summary['revenue_by_currency']['TZS']);
        $this->assertSame(1, $summary['total_orders']);
    }

    public function test_payment_status_other_than_completed_is_excluded_from_channels(): void
    {
        $user = $this->customer();
        $order = $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00');

        $failed = $this->completedPayment($order, 'mpesa', 1000, '2026-05-10 09:01:00');
        $failed->update(['status' => Payment::STATUS_FAILED]);

        $channels = $this->service()->paymentChannels($this->period('2026-05-01', '2026-05-31'));

        $this->assertTrue($channels['available']);
        $this->assertSame([], $channels['rows']);
    }
}
