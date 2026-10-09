<?php

namespace Tests\Feature\Reports;

use App\Reports\SalesReportService;

/**
 * The CSV is a projection of exactly the same paid-order set the screen shows.
 */
class SalesReportExportTest extends SalesReportTestCase
{
    /**
     * @return list<string>
     */
    private function rows(string $csv): array
    {
        // Strip the UTF-8 BOM the export writes for spreadsheet compatibility.
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;

        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];

        return array_values(array_filter(array_map(
            fn (string $line) => str_getcsv($line),
            $lines,
        )));
    }

    public function test_the_export_contains_only_paid_orders(): void
    {
        $user = $this->customer();
        $book = $this->book('Exported Book', '1000.00');

        $paid = $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00', [
            ['book' => $book, 'unit_price' => '1000.00', 'qty' => 1],
        ]);

        $this->order($user, '5000.00', 'TZS', '2026-05-11 09:00:00', 'pending');
        $this->order($user, '6000.00', 'TZS', '2026-05-12 09:00:00', 'cancelled');

        $csv = $this->actingAs($this->admin())
            ->get(route('admin.reports.sales.export', ['range' => 'custom', 'from' => '2026-05-01', 'to' => '2026-05-31']))
            ->assertOk()
            ->streamedContent();

        $rows = $this->rows($csv);

        $this->assertSame(SalesReportService::exportHeader(), $rows[0]);
        $this->assertCount(2, $rows, 'Header plus the single paid order.');
        $this->assertSame($paid->order_number, $rows[1][0]);
        $this->assertStringNotContainsString('5000.00', $csv);
    }

    public function test_the_export_respects_the_selected_period(): void
    {
        $user = $this->customer();

        $may = $this->paidOrder($user, '100.00', 'TZS', '2026-05-10 09:00:00');
        $june = $this->paidOrder($user, '200.00', 'TZS', '2026-06-10 09:00:00');

        $csv = $this->actingAs($this->admin())
            ->get($this->customUrl('2026-05-01', '2026-05-31') === '' ? '' : route('admin.reports.sales.export', [
                'range' => 'custom', 'from' => '2026-05-01', 'to' => '2026-05-31',
            ]))
            ->streamedContent();

        $rows = $this->rows($csv);

        $this->assertCount(2, $rows);
        $this->assertSame($may->order_number, $rows[1][0]);
        $this->assertStringNotContainsString($june->order_number, $csv);
    }

    public function test_the_export_uses_the_same_inclusive_boundaries_as_the_screen(): void
    {
        $user = $this->customer();

        $this->paidOrder($user, '100.00', 'TZS', '2026-05-01 00:00:00');
        $this->paidOrder($user, '200.00', 'TZS', '2026-05-31 23:59:59');
        $this->paidOrder($user, '300.00', 'TZS', '2026-06-01 00:00:00');

        $csv = $this->actingAs($this->admin())
            ->get(route('admin.reports.sales.export', ['range' => 'custom', 'from' => '2026-05-01', 'to' => '2026-05-31']))
            ->streamedContent();

        $this->assertCount(3, $this->rows($csv));
    }

    public function test_the_export_is_downloadable_with_a_dated_filename(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.reports.sales.export', ['range' => 'custom', 'from' => '2026-05-01', 'to' => '2026-05-31']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'sales-2026-05-01-to-2026-05-31.csv',
            $response->headers->get('content-disposition') ?? '',
        );
    }

    /**
     * The export must be safe to hand to someone: no secrets, no contact
     * details, no provider references.
     */
    public function test_the_export_never_contains_secrets_or_personal_columns(): void
    {
        $user = $this->customer();
        $user->forceFill(['email' => 'leak@example.test', 'phone' => '255700999888'])->save();

        $book = $this->book('Safe Book', '1000.00');
        $order = $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00', [
            ['book' => $book, 'unit_price' => '1000.00', 'qty' => 1],
        ]);
        $this->completedPayment($order, 'mpesa', 1000, '2026-05-10 09:01:00');

        $csv = $this->actingAs($this->admin())
            ->get(route('admin.reports.sales.export', ['range' => 'all_time']))
            ->streamedContent();

        $this->assertStringContainsString($order->order_number, $csv);
        $this->assertStringNotContainsString('leak@example.test', $csv);
        $this->assertStringNotContainsString('255700999888', $csv);
        $this->assertStringNotContainsString('SNIP-', $csv);
        $this->assertStringNotContainsString('$2y$', $csv);
    }

    public function test_the_export_header_has_no_password_or_provider_columns(): void
    {
        $header = array_map('strtolower', SalesReportService::exportHeader());

        foreach (['password', 'email', 'phone', 'reference', 'payload', 'idempotency', 'token', 'secret'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                implode(' ', $header),
                "The export must not contain a {$forbidden} column.",
            );
        }
    }

    public function test_the_export_is_not_reachable_by_a_customer(): void
    {
        $this->paidOrder($this->customer(), '1000.00', 'TZS', '2026-05-10 09:00:00');

        $this->actingAs($this->customer())
            ->get(route('admin.reports.sales.export', ['range' => 'all_time']))
            ->assertForbidden();
    }

    public function test_the_export_validates_the_filter_like_the_page_does(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.sales.export', [
                'range' => 'custom',
                'from' => '2026-05-20',
                'to' => '2026-05-01',
            ]))
            ->assertSessionHasErrors('to');
    }

    public function test_the_export_handles_an_empty_period(): void
    {
        $csv = $this->actingAs($this->admin())
            ->get(route('admin.reports.sales.export', ['range' => 'custom', 'from' => '2020-01-01', 'to' => '2020-01-31']))
            ->assertOk()
            ->streamedContent();

        $rows = $this->rows($csv);

        $this->assertCount(1, $rows, 'Only the header row is emitted.');
        $this->assertSame(SalesReportService::exportHeader(), $rows[0]);
    }

    public function test_customer_names_with_commas_do_not_break_the_csv(): void
    {
        $user = $this->customer('Smith, John Jr');

        $this->paidOrder($user, '100.00', 'TZS', '2026-05-10 09:00:00');

        $csv = $this->actingAs($this->admin())
            ->get(route('admin.reports.sales.export', ['range' => 'all_time']))
            ->streamedContent();

        $rows = $this->rows($csv);

        $this->assertSame('Smith, John Jr', $rows[1][2]);
    }

    public function test_the_export_bom_is_present_for_spreadsheet_compatibility(): void
    {
        $csv = $this->actingAs($this->admin())
            ->get(route('admin.reports.sales.export', ['range' => 'all_time']))
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Order number', $csv);
    }

    public function test_the_export_url_on_the_page_carries_the_current_filter(): void
    {
        $this->actingAs($this->admin())
            ->get($this->customUrl('2026-03-01', '2026-03-31'))
            ->assertOk()
            ->assertSee('export?range=custom&amp;from=2026-03-01&amp;to=2026-03-31', false);
    }

    public function test_a_preset_export_preserves_the_preset(): void
    {
        $user = $this->customer();
        $order = $this->paidOrder($user, '100.00', 'TZS', now()->toDateString().' 09:00:00');
        $older = $this->paidOrder($user, '100.00', 'TZS', '2020-01-15 09:00:00');

        $csv = $this->actingAs($this->admin())
            ->get(route('admin.reports.sales.export', ['range' => 'this_month']))
            ->streamedContent();

        $this->assertStringContainsString($order->order_number, $csv);
        $this->assertStringNotContainsString($older->order_number, $csv);
    }
}
