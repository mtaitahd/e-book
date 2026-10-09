<?php

namespace Tests\Feature\Settings;

use App\Models\Payment;
use App\Reports\SalesPeriod;
use App\Settings\PaymentSettingsService;

class PaymentSettingsTimezoneTest extends PaymentSettingsTestCase
{
    public function test_the_application_timezone_is_unchanged_by_this_stage(): void
    {
        // Stage 10.1 must not move the clock. See TIMEZONE FINDINGS in the
        // report: changing it now would reinterpret every historical timestamp
        // already written to the database.
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_the_settings_page_reports_the_same_timezone_as_the_sales_report(): void
    {
        $settings = app(PaymentSettingsService::class);

        $this->assertSame(
            SalesPeriod::timezone()->getName(),
            $settings->activity()->timezone
        );
    }

    public function test_activity_timestamps_are_rendered_in_the_reporting_timezone(): void
    {
        $this->backdate(
            $this->payment(Payment::STATUS_COMPLETED),
            'created_at',
            now()
        );

        $summary = app(PaymentSettingsService::class)->activity();

        $this->assertSame(
            SalesPeriod::timezone()->getName(),
            $summary->lastPaymentAt->getTimezone()->getName()
        );
    }

    public function test_a_payment_time_is_not_shifted_by_the_reporting_timezone(): void
    {
        $moment = now()->startOfDay()->addHours(9);
        $this->backdate(
            $this->payment(Payment::STATUS_COMPLETED),
            'created_at',
            $moment
        );

        $summary = app(PaymentSettingsService::class)->activity();

        $this->assertSame(
            $moment->format('d M Y, H:i'),
            $summary->lastPaymentLabel()
        );
    }

    public function test_the_page_and_the_report_agree_on_the_store_timezone(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee(SalesPeriod::timezone()->getName());
    }

    public function test_the_page_explains_that_the_timezone_is_left_alone(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('left alone', false);
    }

    public function test_the_reporting_timezone_has_a_single_source_of_truth(): void
    {
        // Everything that renders or buckets a timestamp must resolve its zone
        // from config('app.timezone'), never from the server or the browser.
        // The test suite runs on SQLite, which has no session clock of its own,
        // so this is asserted at the source rather than at the connection.
        $this->assertSame(
            (string) config('app.timezone', 'UTC'),
            SalesPeriod::timezone()->getName()
        );

        $this->assertSame(
            SalesPeriod::timezone()->getName(),
            SalesPeriod::today('today')->timezoneName()
        );
    }

    public function test_no_timezone_env_override_was_introduced(): void
    {
        // APP_TIMEZONE is intentionally absent from .env and .env.example, so
        // config/app.php stays the single source of truth and the running
        // value cannot silently drift per environment.
        $env = file_get_contents(base_path('.env.example'));

        $this->assertStringNotContainsString('APP_TIMEZONE', $env);
    }
}
