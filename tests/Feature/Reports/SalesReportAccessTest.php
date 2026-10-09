<?php

namespace Tests\Feature\Reports;

use App\Models\Order;
use App\Models\User;

/**
 * The report reuses the existing admin area. It must not create a second,
 * weaker way into privileged pages.
 */
class SalesReportAccessTest extends SalesReportTestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.reports.sales'))->assertRedirect(route('login'));
    }

    public function test_customers_cannot_open_the_report(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->get(route('admin.reports.sales'))
            ->assertForbidden();
    }

    public function test_customers_cannot_export_the_report(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->get(route('admin.reports.sales.export'))
            ->assertForbidden();
    }

    public function test_admins_can_open_the_report(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.sales'))
            ->assertOk();
    }

    public function test_admins_can_export_the_report(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.sales.export'))
            ->assertOk();
    }

    public function test_the_report_is_not_reachable_by_customer_account_urls(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->get('/admin/reports/sales')
            ->assertForbidden();
    }

    public function test_the_sidebar_links_to_the_report(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Sales Reports')
            ->assertSee(route('admin.reports.sales'), false);
    }

    public function test_the_report_routes_live_inside_the_admin_prefix(): void
    {
        $this->assertSame('admin/reports/sales', ltrim(route('admin.reports.sales', [], false), '/'));
        $this->assertSame('admin/reports/sales/export', ltrim(route('admin.reports.sales.export', [], false), '/'));
    }

    public function test_an_unprivileged_user_cannot_reach_report_data_via_the_export(): void
    {
        $user = $this->customer();
        $this->paidOrder($user, '5000.00', 'TZS', '2026-05-10 09:00:00');

        $response = $this->actingAs(User::factory()->customer()->create())
            ->get(route('admin.reports.sales.export', ['range' => 'all_time']));

        $response->assertForbidden();
        $this->assertStringNotContainsString('5,000.00', $response->getContent() ?: '');
    }

    /**
     * A paid order belonging to someone else must not be exposed by viewing
     * the report as a different admin either.
     */
    public function test_report_only_aggregates_and_never_exposes_password_hashes(): void
    {
        $user = $this->customer();
        $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00');

        $html = $this->actingAs($this->admin())
            ->get($this->customUrl('2026-05-01', '2026-05-31'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('$2y$', $html ?: '');
    }

    public function test_existing_admin_pages_still_reject_customors(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->get(route('admin.orders.index'))
            ->assertForbidden();
    }

    public function test_the_report_does_not_change_any_order_state(): void
    {
        $user = $this->customer();
        $order = $this->paidOrder($user, '1000.00', 'TZS', '2026-05-10 09:00:00');
        $pending = $this->order($user, '900.00', 'TZS', '2026-05-10 10:00:00', Order::STATUS_PENDING);

        $this->actingAs($this->admin())->get($this->customUrl('2026-05-01', '2026-05-31'))->assertOk();
        $this->actingAs($this->admin())->get(route('admin.reports.sales.export', ['range' => 'all_time']))->assertOk();

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING, $pending->fresh()->status);
    }
}
