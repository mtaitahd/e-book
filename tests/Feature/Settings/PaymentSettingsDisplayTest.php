<?php

namespace Tests\Feature\Settings;

use App\Models\Payment;
use App\Settings\PaymentSettingsService;

class PaymentSettingsDisplayTest extends PaymentSettingsTestCase
{
    public function test_the_page_shows_the_provider_and_the_safe_urls(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/settings/payments');

        $response->assertOk();
        $response->assertSee('Snippe Mobile Money');
        $response->assertSee('https://api.snippe.test');
        $response->assertSee('https://shop.test/webhooks/snippe');
    }

    public function test_an_unset_webhook_url_falls_back_to_the_real_route(): void
    {
        config()->set('services.snippe.webhook_url', null);

        $response = $this->actingAs($this->admin())->get('/admin/settings/payments');

        $response->assertOk();
        $response->assertSee(route('webhooks.snippe'));
        $response->assertSee("derived from this app's route", false);
    }

    public function test_the_callback_endpoint_is_shown(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee(route('webhooks.snippe'));
    }

    public function test_the_page_shows_the_currency_and_minimum_amount(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('TZS')
            ->assertSee('500', false);
    }

    public function test_the_page_lists_every_supported_network(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/settings/payments');

        $response->assertOk();

        foreach (Payment::NETWORKS as $label) {
            $response->assertSee($label);
        }
    }

    public function test_the_page_reports_the_webhook_freshness_window(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('300 seconds replay window', false);
    }

    public function test_disabled_webhook_verification_is_shown_as_a_problem(): void
    {
        config()->set('services.snippe.verify_on_webhook', false);

        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('Disabled');
    }

    public function test_an_empty_store_reports_no_payments_rather_than_fake_numbers(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('No Snippe payments have been recorded yet');
    }

    public function test_real_payment_counts_are_shown(): void
    {
        $this->payment(Payment::STATUS_COMPLETED);
        $this->payment(Payment::STATUS_COMPLETED);
        $this->payment(Payment::STATUS_PENDING);
        $this->payment(Payment::STATUS_FAILED);
        $this->payment(Payment::STATUS_VOIDED);
        $this->payment(Payment::STATUS_EXPIRED);

        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertDontSee('No Snippe payments have been recorded yet');
    }

    public function test_voided_and_expired_payments_are_counted_separately(): void
    {
        $this->payment(Payment::STATUS_VOIDED);
        $this->payment(Payment::STATUS_EXPIRED);

        $summary = app(PaymentSettingsService::class)->activity();

        $this->assertSame(2, $summary->total);
        $this->assertSame(0, $summary->completed);
        $this->assertSame(2, $summary->discarded);
    }

    public function test_payments_from_another_provider_are_not_counted_as_snippe(): void
    {
        $this->payment(Payment::STATUS_COMPLETED, ['provider' => 'some-other-gateway']);

        $summary = app(PaymentSettingsService::class)->activity();

        $this->assertSame(0, $summary->total);
        $this->assertTrue($summary->isEmpty());
    }

    public function test_the_activity_total_equals_the_sum_of_every_state(): void
    {
        $this->payment(Payment::STATUS_COMPLETED);
        $this->payment(Payment::STATUS_PENDING);
        $this->payment(Payment::STATUS_PENDING);
        $this->payment(Payment::STATUS_FAILED);

        $summary = app(PaymentSettingsService::class)->activity();

        $this->assertSame(4, $summary->total);
        $this->assertSame(
            $summary->total,
            $summary->completed + $summary->pending + $summary->failed + $summary->discarded
        );
    }

    public function test_the_last_completed_time_prefers_paid_at_over_created_at(): void
    {
        $this->payment(Payment::STATUS_COMPLETED, ['paid_at' => now()->subDay()]);
        $this->backdate(
            $this->payment(Payment::STATUS_COMPLETED),
            'created_at',
            now()->subDays(3)
        );

        $summary = app(PaymentSettingsService::class)->activity();

        $this->assertNotNull($summary->lastCompletedAt);
        $this->assertSame(
            now()->subDay()->format('Y-m-d'),
            $summary->lastCompletedAt->format('Y-m-d')
        );
    }

    public function test_the_page_shows_the_reporting_timezone(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/settings/payments');

        $response->assertOk();
        $response->assertSee(config('app.timezone'), false);
    }

    public function test_the_page_explains_how_to_set_the_values(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/settings/payments');

        $response->assertOk();
        $response->assertSee('SNIPPE_API_KEY');
        $response->assertSee('SNIPPE_WEBHOOK_SECRET');
        $response->assertSee('SNIPPE_ENABLED');
        $response->assertSee('config:clear', false);
    }

    public function test_the_page_states_that_secrets_are_not_editable(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('not editable here', false);
    }
}
