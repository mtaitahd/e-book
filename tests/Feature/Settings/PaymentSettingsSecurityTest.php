<?php

namespace Tests\Feature\Settings;

use App\Models\Payment;
use App\Services\Snippe\SnippePaymentService;
use Illuminate\Support\Facades\Http;

class PaymentSettingsSecurityTest extends PaymentSettingsTestCase
{
    public function test_the_api_key_value_is_never_rendered(): void
    {
        config()->set('services.snippe.api_key', 'snp_super_secret_key_value');

        $response = $this->actingAs($this->admin())->get('/admin/settings/payments');

        $response->assertOk();
        $response->assertDontSee('snp_super_secret_key_value');
        $response->assertSee('Configured');
    }

    public function test_the_webhook_secret_value_is_never_rendered(): void
    {
        config()->set('services.snippe.webhook_secret', 'whsec_super_secret_value');

        $response = $this->actingAs($this->admin())->get('/admin/settings/payments');

        $response->assertOk();
        $response->assertDontSee('whsec_super_secret_value');
        $response->assertSee('Configured');
    }

    public function test_no_secret_appears_anywhere_in_the_page_source(): void
    {
        config()->set('services.snippe.api_key', 'snp_key_abcdef123456');
        config()->set('services.snippe.webhook_secret', 'whsec_zyxwvu987654');

        $html = (string) $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->getContent();

        foreach (['snp_key_abcdef123456', 'whsec_zyxwvu987654'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }
    }

    /**
     * The page now has to accept credentials, so the guarantee changes shape:
     * there must be a way in, but nothing already stored may ever come back out
     * through the form.
     */
    public function test_credential_inputs_accept_a_secret_but_never_carry_one_back(): void
    {
        config()->set('services.snippe.api_key', 'snp_stored_key_value_1111');
        config()->set('services.snippe.webhook_secret', 'whsec_stored_value_2222');

        $html = (string) $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->getContent();

        // There is an input, and it is masked so the browser will not offer to
        // autofill or remember the real value.
        $this->assertStringContainsString('name="api_key"', $html);
        $this->assertStringContainsString('name="webhook_secret"', $html);
        $this->assertStringContainsString('type="password"', $html);

        foreach (['snp_stored_key_value_1111', 'whsec_stored_value_2222'] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }

        // A password input must not be pre-filled with the current value.
        $this->assertDoesNotMatchRegularExpression(
            '/name="(api_key|webhook_secret)"[^>]*\svalue="(?!")/i',
            $html,
            'A credential input must not be rendered with a value attribute.'
        );
    }

    public function test_a_stored_secret_never_appears_in_the_form_even_after_a_failed_validation(): void
    {
        $this->saveSettings(apiKey: 'snp_persisted_key_9999', webhookSecret: 'whsec_persisted_8888');

        // Redirect back with errors, which is what a bad webhook_url produces.
        $html = (string) $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments', ['webhook_url' => 'not-a-url'])
            ->assertRedirect('/admin/settings/payments')
            ->assertSessionHasErrors()
            ->getContent();

        $this->assertStringNotContainsString('snp_persisted_key_9999', $html);
        $this->assertStringNotContainsString('whsec_persisted_8888', $html);
    }

    public function test_a_saved_secret_is_not_left_in_the_session_after_saving(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/payments', [
                'api_key' => 'snp_session_probe_5555',
                'webhook_secret' => 'whsec_session_probe_6666',
            ])
            ->assertRedirect();

        // Sessions here live in the database, so anything flashed on save would
        // outlive the response and be reachable by a later request.
        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSessionMissing('api_key')
            ->assertSessionMissing('webhook_secret')
            ->assertSessionMissing('revealed');

        $html = (string) $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('snp_session_probe_5555', $html);
        $this->assertStringNotContainsString('whsec_session_probe_6666', $html);
    }

    public function test_an_empty_api_key_string_counts_as_not_configured(): void
    {
        // An env var that is present but empty must not read as configured.
        config()->set('services.snippe.api_key', '');

        $this->assertFalse(app(SnippePaymentService::class)->isConfigured());
    }

    public function test_an_empty_webhook_secret_counts_as_not_configured(): void
    {
        config()->set('services.snippe.webhook_secret', '');

        $this->assertFalse(app(SnippePaymentService::class)->hasWebhookSecret());
    }

    public function test_the_page_never_shows_a_customer_phone_number(): void
    {
        $this->customer(['phone' => '255754999111']);

        $this->payment(Payment::STATUS_COMPLETED);

        $html = (string) $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('255754999111', $html);
    }

    public function test_the_page_never_shows_a_provider_reference(): void
    {
        $this->payment(Payment::STATUS_COMPLETED, [
            'provider_reference' => '6a490816-799b-4fc9-b9b6-2ec67c54e17e',
        ]);

        $html = (string) $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('6a490816-799b-4fc9-b9b6-2ec67c54e17e', $html);
    }

    public function test_the_page_never_shows_a_stored_provider_payload(): void
    {
        $this->payment(Payment::STATUS_COMPLETED, [
            'provider_payload' => json_encode(['secret_note' => 'do-not-render-9f3a2b']),
        ]);

        $html = (string) $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('do-not-render-9f3a2b', $html);
    }

    public function test_a_missing_api_key_is_reported_as_not_set(): void
    {
        config()->set('services.snippe.api_key', null);

        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('Not set');
    }

    public function test_disabling_the_provider_is_reported_honestly(): void
    {
        config()->set('services.snippe.enabled', false);

        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('Switched off')
            ->assertSee('New payments are refused');
    }

    public function test_the_page_explains_a_missing_webhook_secret(): void
    {
        config()->set('services.snippe.webhook_secret', null);

        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('No webhook secret is set', false);
    }

    public function test_a_fully_configured_provider_reports_no_blocking_reason(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('ready to accept payments');
    }

    public function test_the_connection_endpoint_is_a_post_route(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'admin/settings/payments/connection');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());
    }

    public function test_the_connection_result_does_not_echo_the_api_key(): void
    {
        config()->set('services.snippe.api_key', 'snp_key_for_connection_test');
        Http::fake([
            'api.snippe.test/*' => Http::response(['status' => 'success'], 200),
        ]);

        $response = $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments/connection');

        $response->assertRedirect('/admin/settings/payments');
        $response->assertSessionHasNoErrors();
        $this->assertStringNotContainsString(
            'snp_key_for_connection_test',
            (string) $response->getContent()
        );
    }

    public function test_the_connection_result_does_not_echo_the_account_balance(): void
    {
        Http::fake([
            'api.snippe.test/*' => Http::response([
                'status' => 'success',
                'data' => ['balance' => ['currency' => 'TZS', 'value' => 9876543]],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments/connection');

        $response->assertSessionHas('success');
        // The balance is useful to the provider and irrelevant to this page.
        $this->assertStringNotContainsString(
            '9876543',
            (string) $response->getSession()->get('success')
        );
    }
}
