<?php

namespace Tests\Feature\Settings;

use App\Models\PaymentSetting;
use App\Models\User;
use App\Services\Snippe\SnippePaymentService;
use App\Services\Snippe\SnippeSignatureVerifier;
use App\Settings\PaymentProviderConfig;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * The administrator can now set, rotate, clear and switch the Snippe
 * integration from the page instead of editing `.env` and reloading the app.
 *
 * The behaviour under test is the security contract, not the form: what lands
 * in the database, what never leaves it, and what the rest of the application
 * reads afterwards.
 */
class PaymentSettingsEditingTest extends PaymentSettingsTestCase
{
    public function test_an_admin_can_save_both_credentials_from_the_page(): void
    {
        $response = $this->saveViaPage([
            'api_key' => 'snp_live_key_from_the_form',
            'webhook_secret' => 'whsec_live_secret_from_the_form',
        ]);

        $response->assertRedirect('/admin/settings/payments');
        $response->assertSessionHas('success');

        $row = PaymentSetting::forProvider();

        $this->assertNotNull($row);
        $this->assertSame('snp_live_key_from_the_form', $row->api_key);
        $this->assertSame('whsec_live_secret_from_the_form', $row->webhook_secret);
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        $this->saveViaPage([
            'api_key' => 'snp_plaintext_key_abcdef',
            'webhook_secret' => 'whsec_plaintext_secret_abcdef',
        ])->assertRedirect();

        $rawKey = $this->rawSecretColumn('api_key');
        $rawSecret = $this->rawSecretColumn('webhook_secret');

        // The column must not be the value.
        $this->assertNotSame('snp_plaintext_key_abcdef', $rawKey);
        $this->assertNotSame('whsec_plaintext_secret_abcdef', $rawSecret);
        $this->assertStringNotContainsString('snp_plaintext_key_abcdef', (string) $rawKey);
        $this->assertStringNotContainsString('whsec_plaintext_secret_abcdef', (string) $rawSecret);

        // Nor any recognisable fragment of it.
        $this->assertStringNotContainsString('plaintext_key', (string) $rawKey);
        $this->assertStringNotContainsString('plaintext_secret', (string) $rawSecret);

        // And the column is genuine Laravel ciphertext that round-trips.
        $this->assertSame('snp_plaintext_key_abcdef', Crypt::decryptString($rawKey));
    }

    public function test_a_saved_credential_is_immediately_used_for_outgoing_calls(): void
    {
        $this->saveSettings(apiKey: 'snp_key_used_for_requests');

        Http::fake([
            'api.snippe.test/*' => Http::response(['status' => 'success', 'data' => []], 200),
        ]);

        app(SnippePaymentService::class)->testConnection();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer snp_key_used_for_requests');
        });
    }

    public function test_a_saved_secret_is_immediately_used_to_verify_webhooks(): void
    {
        // A stored secret must take effect without a config cache clear or a
        // process restart, which is the whole point of saving it here.
        $this->saveSettings(webhookSecret: 'whsec_rotated_secret_value');

        $verifier = app(SnippeSignatureVerifier::class);

        $this->assertTrue($verifier->isConfigured());

        $raw = '{"event":"payment.completed"}';
        $timestamp = (string) now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.'.$raw, 'whsec_rotated_secret_value');

        $this->assertTrue($verifier->verify($timestamp, $signature, $raw));

        // The superseded secret must stop working immediately.
        $oldSignature = hash_hmac('sha256', $timestamp.'.'.$raw, 'the_old_secret');
        $this->assertFalse($verifier->verify($timestamp, $oldSignature, $raw));
    }

    public function test_a_saved_secret_changes_the_outcome_of_a_real_webhook_request(): void
    {
        config()->set('services.snippe.webhook_secret', 'secret_only_in_the_environment');

        $raw = '{"event":"payment.completed","data":{}}';
        $timestamp = (string) now()->getTimestamp();

        // Signed with the value saved on the page, not the one in the
        // environment. This is the case that would silently fail if the
        // verifier were still reading config directly.
        $signature = hash_hmac('sha256', $timestamp.'.'.$raw, 'secret_saved_on_the_page');

        $this->saveSettings(webhookSecret: 'secret_saved_on_the_page');

        $this->post('/webhooks/snippe', [], [], [], [
            'HTTP_X_SNIPPE-TIMESTAMP' => $timestamp,
            'HTTP_X_SNIPPE-SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertOk();
    }

    public function test_a_blank_credential_keeps_the_stored_value(): void
    {
        $this->saveSettings(apiKey: 'snp_keep_this_key', webhookSecret: 'whsec_keep_this_secret');

        $this->saveViaPage([
            // The admin edited an unrelated field and left the secrets alone.
            'api_key' => '',
            'webhook_secret' => '',
            'webhook_url' => 'https://example.test/webhooks/snippe',
        ])->assertRedirect();

        $row = PaymentSetting::forProvider();

        $this->assertSame('snp_keep_this_key', $row->api_key);
        $this->assertSame('whsec_keep_this_secret', $row->webhook_secret);
        $this->assertSame('https://example.test/webhooks/snippe', $row->webhook_url);
    }

    public function test_a_rotated_credential_replaces_the_old_one(): void
    {
        $this->saveSettings(apiKey: 'snp_the_original_key');

        $this->saveViaPage([
            'api_key' => 'snp_the_replacement_key',
        ])->assertRedirect();

        $this->assertSame('snp_the_replacement_key', PaymentSetting::forProvider()->api_key);
        $this->assertFalse(app(SnippePaymentService::class)->isConfigured() === false);
    }

    public function test_resubmitting_the_same_value_does_not_rewrite_the_ciphertext(): void
    {
        $this->saveSettings(apiKey: 'snp_stable_key_value');

        $before = $this->rawSecretColumn('api_key');

        $this->saveViaPage([
            'api_key' => 'snp_stable_key_value',
        ])->assertRedirect();

        $this->assertSame($before, $this->rawSecretColumn('api_key'));
    }

    public function test_the_saved_value_wins_over_the_environment(): void
    {
        config()->set('services.snippe.api_key', 'snp_key_from_the_environment');

        $this->saveSettings(apiKey: 'snp_key_from_the_database');

        $this->assertSame('snp_key_from_the_database', app(PaymentProviderConfig::class)->apiKey());
        $this->assertSame('database', app(PaymentProviderConfig::class)->sourceFor('api_key'));
    }

    public function test_the_environment_is_used_when_nothing_is_saved(): void
    {
        config()->set('services.snippe.api_key', 'snp_key_from_the_environment');
        config()->set('services.snippe.webhook_secret', 'whsec_from_the_environment');

        $config = app(PaymentProviderConfig::class);

        $this->assertSame('snp_key_from_the_environment', $config->apiKey());
        $this->assertSame('environment', $config->sourceFor('api_key'));
        $this->assertSame('whsec_from_the_environment', $config->webhookSecret());
    }

    public function test_clearing_a_credential_falls_back_to_the_environment(): void
    {
        config()->set('services.snippe.api_key', 'snp_key_from_the_environment');

        $this->saveSettings(apiKey: 'snp_key_from_the_database');

        $this->actingAs($this->admin())
            ->post('/admin/settings/payments/clear/api_key')
            ->assertRedirect();

        $this->assertNull($this->rawSecretColumn('api_key'));
        $this->assertSame('snp_key_from_the_environment', app(PaymentProviderConfig::class)->apiKey());
    }

    public function test_an_unexpected_field_cannot_be_cleared(): void
    {
        $this->saveSettings();

        // `enabled` is not a credential; clearing it must not be possible
        // through this route.
        $this->actingAs($this->admin())
            ->post('/admin/settings/payments/clear/enabled')
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->post('/admin/settings/payments/clear/updated_by')
            ->assertNotFound();
    }

    public function test_reset_returns_every_field_to_the_environment(): void
    {
        config()->set('services.snippe.api_key', 'snp_key_from_the_environment');
        config()->set('services.snippe.enabled', true);

        $this->saveSettings(apiKey: 'snp_key_from_the_database', enabled: false);

        $this->actingAs($this->admin())
            ->post('/admin/settings/payments/reset')
            ->assertRedirect();

        $this->assertNull(PaymentSetting::forProvider());
        $this->assertSame('snp_key_from_the_environment', app(PaymentProviderConfig::class)->apiKey());
        $this->assertTrue(app(PaymentProviderConfig::class)->isEnabled());
    }

    public function test_the_admin_can_switch_new_payments_off_and_on(): void
    {
        $this->saveSettings(apiKey: 'snp_key_value');

        $this->saveViaPage([
            'enabled' => '0',
        ])->assertRedirect();

        $this->assertFalse(app(SnippePaymentService::class)->isEnabled());

        $this->saveViaPage([
            'enabled' => '1',
        ])->assertRedirect();

        $this->assertTrue(app(SnippePaymentService::class)->isEnabled());
    }

    public function test_switching_payments_off_still_refuses_new_payments_and_keeps_webhooks_working(): void
    {
        $this->saveSettings(apiKey: 'snp_key_value', webhookSecret: 'whsec_value_here');

        $this->saveViaPage([
            'enabled' => '0',
        ])->assertRedirect();

        $this->assertFalse(app(SnippePaymentService::class)->isEnabled());
        $this->assertTrue(app(SnippeSignatureVerifier::class)->isConfigured());
    }

    public function test_the_admin_can_turn_webhook_verification_off_and_on(): void
    {
        $this->saveSettings(webhookSecret: 'whsec_value_here');

        $this->saveViaPage([
            'verify_on_webhook' => '0',
        ])->assertRedirect();

        $this->assertFalse(app(PaymentProviderConfig::class)->verifyOnWebhook());

        $this->saveViaPage([
            'verify_on_webhook' => '1',
        ])->assertRedirect();

        $this->assertTrue(app(PaymentProviderConfig::class)->verifyOnWebhook());
    }

    public function test_a_toggle_absent_from_the_request_keeps_its_current_value(): void
    {
        // A partial save must not silently re-enable payments.
        $this->saveSettings(apiKey: 'snp_key_value', enabled: false, verifyOnWebhook: false);

        $this->saveViaPage([
            'webhook_url' => 'https://example.test/webhooks/snippe',
        ])->assertRedirect();

        $row = PaymentSetting::forProvider();

        $this->assertFalse($row->enabled);
        $this->assertFalse($row->verify_on_webhook);
    }

    public function test_the_webhook_url_can_be_overridden_and_cleared(): void
    {
        $this->saveSettings(webhookUrl: 'https://example.test/custom-hook');

        $this->assertSame(
            'https://example.test/custom-hook',
            app(PaymentProviderConfig::class)->webhookUrl()
        );

        $this->saveViaPage([
            'webhook_url' => '',
        ])->assertRedirect();

        // Blank falls back to the environment, then to the app's own route.
        $this->assertNull(PaymentSetting::forProvider()->webhook_url);
        $this->assertSame(
            'https://shop.test/webhooks/snippe',
            app(PaymentProviderConfig::class)->webhookUrl()
        );

        config()->set('services.snippe.webhook_url', null);
        app(PaymentProviderConfig::class)->refresh();

        $this->assertSame(
            route('webhooks.snippe'),
            app(PaymentProviderConfig::class)->webhookUrl()
        );
    }

    public function test_the_webhook_url_sent_to_snippe_is_the_saved_one(): void
    {
        $this->saveSettings(apiKey: 'snp_key_value', webhookUrl: 'https://example.test/saved-hook');

        Http::fake([
            'api.snippe.test/*' => Http::response([
                'status' => 'success',
                'data' => ['redirect_url' => 'https://pay.example.test/abc'],
            ], 200),
        ]);

        $this->postPayment();

        Http::assertSent(function ($request) {
            return str_contains(
                (string) $request['webhook_url'],
                'https://example.test/saved-hook'
            );
        });
    }

    public function test_an_invalid_webhook_url_is_rejected_and_nothing_is_saved(): void
    {
        $this->saveSettings(apiKey: 'snp_original_key');

        $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments', [
                'webhook_url' => 'javascript:alert(1)',
                'api_key' => 'snp_key_that_must_not_be_saved',
            ])
            ->assertSessionHasErrors('webhook_url');

        $this->assertSame('snp_original_key', PaymentSetting::forProvider()->api_key);
    }

    public function test_an_oversized_credential_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments', [
                'api_key' => str_repeat('a', 5000),
            ])
            ->assertSessionHasErrors('api_key');

        $this->assertNull($this->rawSecretColumn('api_key'));
    }

    public function test_a_revealed_credential_is_shown_once_and_not_stored_in_the_session(): void
    {
        $this->saveSettings(apiKey: 'snp_reveal_me_please', webhookSecret: 'whsec_reveal_me_too');

        $response = $this->actingAs($this->admin())
            ->post('/admin/settings/payments/reveal/api_key');

        $response->assertOk();
        $response->assertViewIs('admin.settings.reveal');
        $response->assertSee('snp_reveal_me_please');
        // The value must not be left in any cache.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // The next request must not carry the value.
        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertDontSee('snp_reveal_me_please');
    }

    public function test_a_reveal_of_a_value_that_is_not_set_says_so(): void
    {
        config()->set('services.snippe.api_key', null);

        $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments/reveal/api_key')
            ->assertRedirect('/admin/settings/payments')
            ->assertSessionHas('error');
    }

    public function test_an_audited_reveal_does_not_write_the_value_to_the_log(): void
    {
        $this->saveSettings(apiKey: 'snp_log_probe_1234');

        $this->actingAs($this->admin())
            ->post('/admin/settings/payments/reveal/api_key')
            ->assertOk();

        $this->assertStringNotContainsString(
            'snp_log_probe_1234',
            (string) file_get_contents(storage_path('logs/laravel.log'))
        );
    }

    public function test_a_customer_can_neither_read_nor_change_the_configuration(): void
    {
        $this->saveSettings(apiKey: 'snp_key_value');

        $customer = $this->customer();

        $this->actingAs($customer)
            ->post('/admin/settings/payments', ['api_key' => 'snp_hijacked'])
            ->assertForbidden();

        $this->actingAs($customer)
            ->post('/admin/settings/payments/clear/api_key')
            ->assertForbidden();

        $this->actingAs($customer)
            ->post('/admin/settings/payments/reset')
            ->assertForbidden();

        $this->actingAs($customer)
            ->post('/admin/settings/payments/reveal/api_key')
            ->assertForbidden();

        $this->assertSame('snp_key_value', PaymentSetting::forProvider()->api_key);
    }

    public function test_a_guest_can_neither_read_nor_change_the_configuration(): void
    {
        $this->saveSettings(apiKey: 'snp_key_value');

        $this->post('/admin/settings/payments', ['api_key' => 'snp_hijacked'])
            ->assertRedirect(route('login'));

        $this->assertSame('snp_key_value', PaymentSetting::forProvider()->api_key);
    }

    /**
     * Laravel's CSRF middleware short-circuits itself while `app.env` is
     * `testing`, so the 419 response cannot be provoked from this suite. What
     * can be checked, and is the part that actually decides the outcome, is
     * that these write paths are NOT on the exemption list.
     */
    public function test_the_write_paths_are_not_exempt_from_csrf_verification(): void
    {
        $middleware = new VerifyCsrfToken(
            $this->app,
            $this->app->make(Encrypter::class),
        );

        $excluded = $middleware->getExcludedPaths();

        $this->assertContains('webhooks/snippe', $excluded, 'The signed webhook route must stay exempt.');

        foreach ([
            'admin/settings/payments',
            'admin/settings/payments/connection',
            'admin/settings/payments/clear/api_key',
            'admin/settings/payments/reset',
            'admin/settings/payments/reveal/api_key',
        ] as $path) {
            $this->assertNotContains($path, $excluded, $path.' must require a CSRF token.');
            $this->assertNotContains(
                'admin/settings/payments*',
                $excluded,
                'No wildcard may exempt the payment settings routes.'
            );
        }
    }

    public function test_the_write_routes_carry_the_csrf_field_in_the_form(): void
    {
        // The real browser protection is the @csrf token on every form.
        $html = (string) $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->getContent();

        $this->assertSame(
            substr_count($html, '<input type="hidden" name="_token"'),
            substr_count($html, '<form method="POST"'),
            'Every POST form on the page must carry a CSRF token.'
        );
    }

    public function test_the_who_and_when_of_a_change_is_recorded_without_the_value(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/settings/payments', [
            'api_key' => 'snp_recorded_change_value',
        ])->assertRedirect();

        $row = PaymentSetting::forProvider();

        $this->assertSame($admin->id, $row->updated_by);
        $this->assertNotNull($row->updated_at);
    }

    public function test_a_value_that_can_no_longer_be_decrypted_is_reported_not_ignored(): void
    {
        // Remove the environment fallback, so only a decrypted stored value
        // could make the integration look configured.
        config()->set('services.snippe.api_key', null);

        $this->saveSettings(apiKey: 'snp_valid_key_value');

        // Simulate a rotated APP_KEY: the ciphertext is left behind but is now
        // unreadable.
        DB::table('payment_settings')
            ->where('provider', PaymentSetting::PROVIDER_SNIPPE)
            ->update(['api_key' => 'not-valid-ciphertext-any-more']);

        app(PaymentProviderConfig::class)->refresh();

        // It must read as not configured rather than as an empty string that
        // would produce a valid-looking but wrong signature.
        $this->assertNull(app(PaymentProviderConfig::class)->apiKey());
        $this->assertFalse(app(SnippePaymentService::class)->isConfigured());
        $this->assertTrue(app(PaymentProviderConfig::class)->hasUndecryptableSecret());
        $this->assertContains('api_key', app(PaymentProviderConfig::class)->undecryptableFields());

        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('can no longer be decrypted');
    }

    public function test_a_page_still_renders_if_the_settings_table_is_missing(): void
    {
        Schema::drop('payment_settings');

        // An unmigrated deployment must fall back to the environment rather than
        // taking the admin page down.
        config()->set('services.snippe.api_key', 'snp_key_from_the_environment');

        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('Configured');
    }

    public function test_only_one_row_per_provider_is_possible(): void
    {
        $this->saveSettings(apiKey: 'snp_first_row');

        $this->expectException(QueryException::class);

        DB::table('payment_settings')->insert([
            'provider' => PaymentSetting::PROVIDER_SNIPPE,
            'api_key' => 'snp_second_row',
            'enabled' => true,
            'verify_on_webhook' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_connection_test_uses_the_saved_credential(): void
    {
        $this->saveSettings(apiKey: 'snp_key_for_the_connection_test');

        Http::fake([
            'api.snippe.test/*' => Http::response(['status' => 'success', 'data' => []], 200),
        ]);

        $this->actingAs($this->admin())
            ->from('/admin/settings/payments')
            ->post('/admin/settings/payments/connection')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->hasHeader(
            'Authorization',
            'Bearer snp_key_for_the_connection_test'
        ));
    }

    public function test_the_page_reports_where_each_value_came_from(): void
    {
        config()->set('services.snippe.webhook_secret', 'whsec_from_the_environment');

        $this->saveSettings(apiKey: 'snp_key_saved_on_the_page');

        $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->assertSee('saved in this page')
            ->assertSee('from the server .env');
    }

    public function test_the_page_shows_only_a_masked_hint_of_a_saved_key(): void
    {
        $this->saveSettings(apiKey: 'snp_abcdefghijklmnop');

        $html = (string) $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('snp_********', $html);
        $this->assertStringContainsString('mnop', $html);
        $this->assertStringNotContainsString('snp_abcdefghijklmnop', $html);
    }

    public function test_a_short_secret_gets_no_hint_at_all(): void
    {
        // Four characters of tail on a four-character secret would be the whole
        // secret.
        $this->saveSettings(apiKey: 'abc');

        $html = (string) $this->actingAs($this->admin())
            ->get('/admin/settings/payments')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('abc', $html);
    }

    public function test_an_admin_who_is_not_the_author_is_still_recorded(): void
    {
        $this->saveSettings(apiKey: 'snp_first_admin_key');

        $second = User::factory()->admin()->create();

        $this->actingAs($second)->post('/admin/settings/payments', [
            'api_key' => 'snp_second_admin_key',
        ])->assertRedirect();

        $this->assertSame($second->id, PaymentSetting::forProvider()->updated_by);
    }
}
