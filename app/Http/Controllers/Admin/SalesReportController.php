<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SalesReportRequest;
use App\Reports\SalesReportService;
use App\Support\RevenueChart;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    public function __construct(private readonly SalesReportService $reports) {}

    /**
     * The sales and revenue report.
     *
     * Every figure below is read through one SalesPeriod, so the cards, the
     * trend, the tables and the export always describe the same window.
     */
    public function index(SalesReportRequest $request): View
    {
        $period = $request->period();

        $summary = $this->reports->summary($period);
        $trend = $this->reports->revenueTrend($period);
        $channels = $this->reports->paymentChannels($period);

        return view('admin.reports.sales', [
            'period' => $period,
            'summary' => $summary,
            'copiesSold' => $this->reports->copiesSold($period),
            'topBooks' => $this->reports->topBooks($period),
            'topCategories' => $this->reports->salesByCategory($period),
            'topCustomers' => $this->reports->topCustomers($period),
            'statusSummary' => $this->reports->orderStatusSummary($period),
            'recentSales' => $this->reports->recentSales($period),
            'channels' => $channels,
            'chart' => RevenueChart::make(
                $trend['points'],
                $summary['store_currency'],
                $trend['buckets_by'],
            ),
            'trend' => $trend,
            'averageOrderValue' => $summary['average_order_value'],
            'presets' => SalesReportRequest::PRESETS,
            'exportUrl' => route('admin.reports.sales.export', array_filter([
                'range' => $request->selectedRange(),
                'from' => $period->fromDate(),
                'to' => $period->toDate(),
            ], fn ($value) => $value !== null && $value !== '')),
        ]);
    }

    /**
     * Streamed CSV of the paid orders in the current period.
     *
     * The export reuses the same service, period and paid-only filter as the
     * screen, and is written in bounded chunks so a large history cannot
     * exhaust memory.
     */
    public function export(SalesReportRequest $request): StreamedResponse
    {
        $period = $request->period();

        $filename = sprintf(
            'sales-%s-to-%s.csv',
            $period->fromDate(),
            $period->toDate(),
        );

        return response()->streamDownload(function () use ($period) {
            $handle = fopen('php://output', 'wb');

            // BOM so Excel opens UTF-8 correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, SalesReportService::exportHeader());

            foreach ($this->reports->exportCursor($period) as $order) {
                fputcsv($handle, SalesReportService::exportRow($order));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
