<?php

namespace Tests\Feature\Settings;

class PaymentSettingsAccessTest extends PaymentSettingsTestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/admin/settings/payments');

        $response->assertRedirect(route('login'));
    }

    public function test_a_customer_cannot_reach_the_payment_settings_page(): void
    {
        $response = $this->actingAs($this->customer())->get('/admin/settings/payments');

        $response->assertForbidden();
    }

    /**
     * The administrator can now set, rotate and clear credentials from the page,
     * so every write must sit behind the same guard as the rest of the admin
     * area: the `admin` middleware, and a POST so it cannot be triggered by a
     * link or a prefetch.
     */
    public function test_every_payment_settings_write_route_is_a_post_behind_the_admin_middleware(): void
    {
        $writes = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'settings/payments'))
            ->filter(fn ($route) => $route->methods() !== ['GET', 'HEAD'])
            ->filter(fn ($route) => $route->uri() !== 'admin/settings/payments/reveal/{field}')
            ->values();

        $this->assertGreaterThan(0, $writes->count(), 'Expected at least one write route.');

        foreach ($writes as $route) {
            $this->assertContains('POST', $route->methods(), $route->uri().' must accept POST.');
            $this->assertContains(
                'admin',
                $route->gatherMiddleware(),
                $route->uri().' must be behind the admin middleware.'
            );
        }
    }

    public function test_the_reveal_route_accepts_get_only_to_redirect_away(): void
    {
        // A GET reveal must never render a secret, e.g. from browser history or
        // a prefetching link. It redirects back to the page instead.
        $this->actingAs($this->admin())
            ->get('/admin/settings/payments/reveal/api_key')
            ->assertRedirect();
    }

    public function test_a_guest_is_redirected_away_from_every_write_route(): void
    {
        foreach ([
            '/admin/settings/payments',
            '/admin/settings/payments/connection',
            '/admin/settings/payments/clear/api_key',
            '/admin/settings/payments/reset',
        ] as $uri) {
            $this->post($uri)->assertRedirect(route('login'));
        }
    }

    public function test_a_customer_is_forbidden_on_every_write_route(): void
    {
        $customer = $this->customer();

        foreach ([
            '/admin/settings/payments',
            '/admin/settings/payments/connection',
            '/admin/settings/payments/clear/api_key',
            '/admin/settings/payments/reset',
        ] as $uri) {
            $this->actingAs($customer)->post($uri)->assertForbidden();
        }
    }

    public function test_a_customer_cannot_run_the_connection_test(): void
    {
        $response = $this->actingAs($this->customer())
            ->post('/admin/settings/payments/connection');

        $response->assertForbidden();
    }

    public function test_an_admin_can_open_the_payment_settings_page(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/settings/payments');

        $response->assertOk();
        $response->assertViewIs('admin.settings.payments');
    }

    public function test_the_page_is_reachable_from_the_admin_sidebar(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.settings.payments'), false);
    }

    public function test_the_settings_section_is_labelled_in_the_sidebar(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/settings/payments');

        $response->assertSee('Payment Settings');
        $response->assertSee('Settings', false);
    }

    public function test_the_connection_test_route_does_not_leak_through_a_get(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/settings/payments/connection')
            ->assertMethodNotAllowed();
    }

    public function test_the_settings_routes_are_named(): void
    {
        $this->assertSame(
            'http://localhost/admin/settings/payments',
            route('admin.settings.payments')
        );

        $this->assertSame(
            'http://localhost/admin/settings/payments/connection',
            route('admin.settings.payments.connection')
        );
    }

    public function test_the_page_does_not_live_outside_the_admin_prefix(): void
    {
        // The same page must not be reachable without the admin middleware.
        $this->actingAs($this->customer())->get('/settings/payments')->assertNotFound();
    }

    public function test_the_management_routes_are_named(): void
    {
        $this->assertSame(
            'http://localhost/admin/settings/payments',
            route('admin.settings.payments.update')
        );

        $this->assertSame(
            'http://localhost/admin/settings/payments/clear/api_key',
            route('admin.settings.payments.clear', ['field' => 'api_key'])
        );

        $this->assertSame(
            'http://localhost/admin/settings/payments/reset',
            route('admin.settings.payments.reset')
        );

        $this->assertSame(
            'http://localhost/admin/settings/payments/reveal/webhook_secret',
            route('admin.settings.payments.reveal', ['field' => 'webhook_secret'])
        );
    }
}
