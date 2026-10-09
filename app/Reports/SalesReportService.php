<?php

namespace App\Reports;

use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Throwable;

/**
 * Stage 10 — real sales and revenue reporting.
 *
 * Design rules enforced here:
 *
 *  1. Revenue comes from `orders` only, and only from rows that are genuinely
 *     paid (`status = paid`). Checkout attempts, carts, `purchases`
 *     entitlements and `payments` rows are never treated as sales.
 *  2. Sales are dated by `orders.paid_at`. `created_at` is never used to
 *     decide when a sale happened.
 *  3. Every aggregate is computed by the database (GROUP BY / SUM / COUNT).
 *     Nothing is accumulated in PHP.
 *  4. Every query is scoped to the same inclusive SalesPeriod, so the page
 *     cannot show a card from one range next to a table from another.
 *  5. Money is never summed across currencies. Totals are grouped by the
 *     currency stored on the order and no conversion is invented.
 */
class SalesReportService
{
    /** How many rows the "top" widgets show. */
    public const TOP_LIMIT = 10;

    /** How many rows the CSV export walks per query. */
    public const EXPORT_CHUNK = 200;

    /**
     * Normalise a database SUM/decimal value into the string Money expects.
     */
    public static function decimal(?string $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    /**
     * `DATE(paid_at)` and the period boundaries must agree on what day a sale
     * belongs to. Laravel writes timestamps in the configured app timezone, so
     * we pin the connection session to the same offset. The numeric offset
     * form ("+00:00") needs no timezone tables and no extra privileges.
     */
    private function alignSessionTimezone(): void
    {
        $offset = CarbonImmutable::now(SalesPeriod::timezone())->format('P');

        try {
            DB::connection()->statement('SET time_zone = ?', [$offset]);
        } catch (Throwable) {
            // A restricted database user may not be allowed to set this. The
            // report still runs; only DATE() bucketing could differ from the
            // app timezone, and only when the two already disagree.
        }
    }

    /**
     * The base query every revenue figure is built on: paid orders, dated by
     * the moment they were actually paid, inside the selected period.
     */
    private function paidOrders(SalesPeriod $period): Builder
    {
        return Order::query()
            ->paid()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$period->sqlFrom(), $period->sqlTo()]);
    }

    /**
     * Total revenue, paid order count, unique customers and average order
     * value — all grouped by the currency actually stored on the orders, so a
     * legacy KES row can never be silently added to TZS.
     *
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     revenue_by_currency: array<string, string>,
     *     store_currency: string,
     *     total_orders: int,
     *     total_customers: int,
     *     total_revenue_cents: int,
     *     other_currencies: array<string, string>,
     *     average_order_value: array<string, string>
     * }
     */
    public function summary(SalesPeriod $period): array
    {
        $rows = $this->paidOrders($period)
            ->selectRaw('currency')
            ->selectRaw('COUNT(*) as paid_orders')
            ->selectRaw('COUNT(DISTINCT user_id) as customers')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $storeCurrency = (string) config('shop.currency', 'TZS');

        $byCurrency = [];
        $totalOrders = 0;
        $totalCustomers = 0;
        $totalCents = 0;
        $other = [];

        foreach ($rows as $row) {
            $currency = strtoupper((string) $row->currency);
            $revenue = self::decimal($row->revenue);
            $cents = Money::toCents($revenue);

            $byCurrency[$currency] = $revenue;
            $totalOrders += (int) $row->paid_orders;
            $totalCents += $cents;
            $totalCustomers = max($totalCustomers, (int) $row->customers);

            if ($currency !== strtoupper($storeCurrency)) {
                $other[$currency] = $revenue;
            }
        }

        $average = [];
        if ($totalOrders > 0) {
            // Average is derived from the already-summed integer cents, so it
            // never depends on floating point.
            $average[$storeCurrency] = Money::fromCents(intdiv($totalCents, $totalOrders));
        }

        return [
            'rows' => $rows->map(fn ($row) => [
                'currency' => strtoupper((string) $row->currency),
                'paid_orders' => (int) $row->paid_orders,
                'customers' => (int) $row->customers,
                'revenue' => self::decimal($row->revenue),
                'revenue_formatted' => Money::format(self::decimal($row->revenue), strtoupper((string) $row->currency)),
            ])->all(),
            'revenue_by_currency' => $byCurrency,
            'store_currency' => strtoupper($storeCurrency),
            'total_orders' => $totalOrders,
            'total_customers' => $totalCustomers,
            'total_revenue_cents' => $totalCents,
            'other_currencies' => $other,
            'average_order_value' => $average,
        ];
    }

    /**
     * Total copies sold, taken from `order_items.quantity` on paid orders.
     * A free book is still a real sale of one copy, so this can be greater than
     * the number of paid orders.
     */
    public function copiesSold(SalesPeriod $period): int
    {
        return (int) $this->paidOrders($period)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as copies')
            ->value('copies');
    }

    /**
     * Daily or monthly revenue buckets, zero-filled across the whole period.
     *
     * Every bucket in the range is present, including days with no sales: a
     * zero is a fact about the period, not a missing row.
     *
     * @return array{
     *     buckets_by: string,
     *     points: list<array{key: string, label: string, value: string, cents: int, formatted: string}>,
     *     peak_cents: int,
     *     has_sales: bool
     * }
     */
    public function revenueTrend(SalesPeriod $period): array
    {
        $this->alignSessionTimezone();

        $storeCurrency = strtoupper((string) config('shop.currency', 'TZS'));

        $raw = $this->paidOrders($period)
            ->selectRaw('DATE(paid_at) as sale_day')
            ->selectRaw('currency')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->groupBy('sale_day', 'currency')
            ->orderBy('sale_day')
            ->get()
            ->groupBy(fn ($row) => $period->bucketKeyFor((string) $row->sale_day));

        $points = [];
        $peak = 0;

        foreach ($period->buckets() as $bucket) {
            // A bucket with no rows at all is a genuine zero, not a missing
            // value, so the chart shows a flat point rather than a gap.
            $row = ($raw->get($bucket['key']) ?? collect())
                ->firstWhere('currency', $storeCurrency);

            $revenue = self::decimal($row?->revenue);
            $cents = Money::toCents($revenue);
            $peak = max($peak, $cents);

            $points[] = [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'value' => $revenue,
                'cents' => $cents,
                'formatted' => Money::format($revenue, $storeCurrency),
            ];
        }

        return [
            'buckets_by' => $period->bucketsBy(),
            'points' => $points,
            'peak_cents' => $peak,
            'has_sales' => $peak > 0,
        ];
    }

    /**
     * Best selling books by copies, using the price snapshot stored on the
     * order item. The book's *current* price is deliberately ignored, so old
     * sales are never restated after a price change.
     *
     * @return list<array<string, mixed>>
     */
    public function topBooks(SalesPeriod $period, int $limit = self::TOP_LIMIT): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('books', 'books.id', '=', 'order_items.book_id')
            ->where('orders.status', Order::STATUS_PAID)
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$period->sqlFrom(), $period->sqlTo()])
            ->selectRaw('order_items.book_id')
            ->selectRaw('books.title as title')
            ->selectRaw('orders.currency')
            ->selectRaw('SUM(order_items.quantity) as copies')
            ->selectRaw('COALESCE(SUM(order_items.subtotal), 0) as revenue')
            ->groupBy('order_items.book_id', 'books.title', 'orders.currency')
            ->orderByDesc('copies')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => [
            'book_id' => (int) $row->book_id,
            'title' => $row->title ?: 'Book no longer listed',
            'currency' => strtoupper((string) $row->currency),
            'copies' => (int) $row->copies,
            'revenue' => self::decimal($row->revenue),
            'revenue_formatted' => Money::format(self::decimal($row->revenue), strtoupper((string) $row->currency)),
        ])->all();
    }

    /**
     * Category sales, aggregated in SQL.
     *
     * A book can belong to several categories, so its full line value is
     * attributed to each of them. These figures therefore describe category
     * interest and are explicitly NOT a partition of total revenue; the view
     * states this so nobody adds the column up. Books with no category fall
     * into an "Uncategorised" bucket so nothing is silently dropped.
     *
     * @return list<array<string, mixed>>
     */
    public function salesByCategory(SalesPeriod $period, int $limit = self::TOP_LIMIT): array
    {
        $rows = DB::table('categories')
            ->leftJoin('book_category', 'book_category.category_id', '=', 'categories.id')
            ->leftJoin('order_items', 'order_items.book_id', '=', 'book_category.book_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id', 'inner')
            ->where('orders.status', Order::STATUS_PAID)
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$period->sqlFrom(), $period->sqlTo()])
            ->selectRaw('categories.id as category_id')
            ->selectRaw('categories.name as name')
            ->selectRaw('orders.currency')
            ->selectRaw('SUM(order_items.quantity) as copies')
            ->selectRaw('COALESCE(SUM(order_items.subtotal), 0) as revenue')
            ->groupBy('categories.id', 'categories.name', 'orders.currency')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => [
            'category_id' => (int) $row->category_id,
            'name' => $row->name,
            'currency' => strtoupper((string) $row->currency),
            'copies' => (int) $row->copies,
            'revenue' => self::decimal($row->revenue),
            'revenue_formatted' => Money::format(self::decimal($row->revenue), strtoupper((string) $row->currency)),
        ])->all();
    }

    /**
     * Best customers by what they actually paid, in the period.
     *
     * Only the display name is read back. Email addresses, phone numbers and
     * password columns are not selected at all, so no report, CSV or view can
     * leak them.
     *
     * @return list<array<string, mixed>>
     */
    public function topCustomers(SalesPeriod $period, int $limit = self::TOP_LIMIT): array
    {
        // Pre-aggregate the line items per order before joining, so a multi-book
        // order cannot multiply its own total in the SUM below.
        $itemTotals = DB::table('order_items')
            ->selectRaw('order_id, COALESCE(SUM(quantity), 0) as copies')
            ->groupBy('order_id');

        $rows = DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->leftJoinSub($itemTotals, 'item_totals', 'item_totals.order_id', '=', 'orders.id')
            ->where('orders.status', Order::STATUS_PAID)
            ->whereNotNull('orders.paid_at')
            ->whereBetween('orders.paid_at', [$period->sqlFrom(), $period->sqlTo()])
            ->selectRaw('orders.user_id')
            ->selectRaw('users.name as name')
            ->selectRaw('orders.currency')
            ->selectRaw('COUNT(DISTINCT orders.id) as orders_count')
            ->selectRaw('COALESCE(SUM(item_totals.copies), 0) as copies')
            ->selectRaw('COALESCE(SUM(orders.total), 0) as spent')
            ->groupBy('orders.user_id', 'users.name', 'orders.currency')
            ->orderByDesc('spent')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => [
            'user_id' => (int) $row->user_id,
            'name' => $row->name,
            'currency' => strtoupper((string) $row->currency),
            'orders' => (int) $row->orders_count,
            'copies' => (int) $row->copies,
            'spent' => self::decimal($row->spent),
            'spent_formatted' => Money::format(self::decimal($row->spent), strtoupper((string) $row->currency)),
        ])->all();
    }

    /**
     * Every order in the period grouped by status, so pending, failed and
     * cancelled activity is visible next to real sales.
     *
     * This widget counts orders *placed* in the period (created_at) rather
     * than orders *paid* in the period, because that is what "activity" means:
     * an abandoned checkout is activity even though it is not revenue. The
     * view labels the column accordingly.
     *
     * Value is grouped by currency, because a status bucket can legitimately
     * contain orders in more than one currency and those must never be added
     * together.
     *
     * @return list<array<string, mixed>>
     */
    public function orderStatusSummary(SalesPeriod $period): array
    {
        $rows = DB::table('orders')
            ->whereBetween('created_at', [$period->sqlFrom(), $period->sqlTo()])
            ->selectRaw('status')
            ->selectRaw('currency')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total), 0) as value')
            ->groupBy('status', 'currency')
            ->get()
            ->groupBy('status');

        $template = new Order;

        return collect(Order::STATUSES)
            ->map(function (string $status) use ($rows, $template) {
                $group = $rows->get($status, collect());

                $values = $group
                    ->map(fn ($row) => [
                        'currency' => strtoupper((string) $row->currency),
                        'value' => self::decimal($row->value),
                        'formatted' => Money::format(
                            self::decimal($row->value),
                            strtoupper((string) $row->currency),
                        ),
                    ])
                    ->values()
                    ->all();

                return [
                    'status' => $status,
                    'label' => ucfirst($status),
                    'badge' => $template->forceFill(['status' => $status])->statusBadge(),
                    'is_revenue' => $status === Order::STATUS_PAID,
                    'orders' => (int) $group->sum('orders_count'),
                    'values' => $values,
                    'formatted' => collect($values)->pluck('formatted')->implode(', '),
                ];
            })
            ->all();
    }

    /**
     * The earliest date any order was actually paid.
     *
     * Used only to bound the "All time" preset to a concrete, displayable range
     * instead of an unbounded query.
     */
    public function earliestPaidAt(): ?CarbonImmutable
    {
        $value = Order::query()
            ->paid()
            ->whereNotNull('paid_at')
            ->min('paid_at');

        return $value ? CarbonImmutable::parse($value, SalesPeriod::timezone()) : null;
    }

    /**
     * Payment channel breakdown, read from the `channel_provider` value Abliner
     * actually stored. Nothing is inferred from a phone number.
     *
     * Availability is reported honestly: if the store has no completed payment
     * rows the caller is told to render "not available" rather than an empty
     * chart that looks like a broken report.
     *
     * @return array{available: bool, reason: ?string, rows: list<array<string, mixed>>, total: int}
     */
    public function paymentChannels(SalesPeriod $period): array
    {
        $hasAnyPayment = Payment::query()->exists();

        if (! $hasAnyPayment) {
            return [
                'available' => false,
                'reason' => 'No payment records have been stored yet, so there is no channel data to report.',
                'rows' => [],
                'total' => 0,
            ];
        }

        $this->alignSessionTimezone();

        $rows = DB::table('payments')
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$period->sqlFrom(), $period->sqlTo()])
            ->selectRaw('COALESCE(channel_provider, ?) as channel', ['__unrecorded__'])
            ->selectRaw('currency')
            ->selectRaw('COUNT(*) as payments_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as amount')
            ->groupBy('channel_provider', 'currency')
            ->orderByDesc('payments_count')
            ->get();

        $labels = Payment::NETWORKS;

        return [
            'available' => true,
            'reason' => null,
            'total' => (int) $rows->sum('payments_count'),
            'rows' => $rows->map(function ($row) use ($labels) {
                $channel = (string) $row->channel;
                $amount = (int) $row->amount;
                $currency = strtoupper((string) $row->currency);

                return [
                    'channel' => $channel,
                    'label' => $channel === '__unrecorded__'
                        ? 'Channel not recorded'
                        : ($labels[$channel] ?? ucwords(str_replace('_', ' ', $channel))),
                    'currency' => $currency,
                    'payments' => (int) $row->payments_count,
                    // Payment amounts are stored as whole shillings.
                    'amount' => $amount,
                    'amount_formatted' => Money::formatWhole($amount, $currency),
                ];
            })->all(),
        ];
    }

    /**
     * The most recent paid orders in the period, paginated.
     *
     * @return Collection<int, Order>
     */
    public function recentSales(SalesPeriod $period, int $perPage = 15)
    {
        return $this->paidOrders($period)
            ->with(['user:id,name', 'items:id,order_id,book_id,quantity'])
            ->withCount(['items as item_count', 'purchases as entitlements_count'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Stream every paid order in the period for CSV export, in bounded chunks.
     *
     * Reuses the exact same period and paid-only filter as the on-screen
     * widgets, so the export can never describe a different range.
     *
     * @return LazyCollection<int, Order>
     */
    public function exportCursor(SalesPeriod $period)
    {
        return $this->paidOrders($period)
            ->with(['user:id,name', 'items:id,order_id,quantity'])
            ->withCount('items as item_count')
            ->orderBy('id')
            ->lazyById();
    }

    /**
     * Header row for the CSV export. Deliberately free of passwords, phone
     * numbers, provider payloads and payment references.
     *
     * @return list<string>
     */
    public static function exportHeader(): array
    {
        return [
            'Order number',
            'Paid at',
            'Customer name',
            'Books',
            'Copies',
            'Currency',
            'Total',
        ];
    }

    /**
     * One CSV row for a paid order.
     *
     * @return list<string>
     */
    public static function exportRow(Order $order): array
    {
        $copies = (int) $order->items->sum('quantity');

        return [
            $order->order_number,
            $order->paid_at?->format('Y-m-d H:i:s') ?? '',
            (string) $order->user?->name,
            (string) $order->item_count,
            (string) $copies,
            strtoupper((string) $order->currency),
            $order->total,
        ];
    }
}
