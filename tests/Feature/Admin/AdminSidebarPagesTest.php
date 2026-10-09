<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Tests\Feature\Payment\PaymentTestCase;

/**
 * Every sidebar entry in the admin area is a page of its own, reachable
 * directly, and reachable by nobody else.
 */
class AdminSidebarPagesTest extends PaymentTestCase
{
    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * An order in a given state. There is no OrderFactory, so this goes through
     * the same helpers the payment tests use.
     */
    private function order(string $status, string $number): Order
    {
        $order = $this->makeOrder($this->customer(), '1500.00');

        $order->update([
            'order_number' => $number,
            'status' => $status,
            'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);

        return $order->fresh();
    }

    /**
     * Each sidebar destination, with a phrase its own page must contain.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sidebarPages(): array
    {
        return [
            'dashboard' => ['admin.dashboard', 'Dashboard'],
            'books' => ['admin.books.index', 'Books'],
            'authors' => ['admin.authors.index', 'Authors'],
            'categories' => ['admin.categories.index', 'Categories'],
            'all orders' => ['admin.orders.index', 'Orders'],
            'pending orders' => ['admin.orders.pending', 'Pending Orders'],
            'paid orders' => ['admin.orders.paid', 'Paid Orders'],
            'payment settings' => ['admin.settings.payments', 'Payment Settings'],
        ];
    }

    /**
     * @dataProvider sidebarPages
     */
    public function test_admin_can_open_each_sidebar_page(string $routeName, string $expectedText): void
    {
        // Every section has entries behind it, so the pages all have rows to show.
        Author::factory()->create();
        Category::factory()->create();
        Book::factory()->create();

        $this->actingAs($this->admin())
            ->get(route($routeName))
            ->assertOk()
            ->assertSee($expectedText);
    }

    /**
     * @dataProvider sidebarPages
     */
    public function test_a_customer_cannot_open_any_admin_page(string $routeName): void
    {
        $this->actingAs($this->customer())
            ->get(route($routeName))
            ->assertForbidden();
    }

    /**
     * @dataProvider sidebarPages
     */
    public function test_guests_are_sent_to_the_admin_login(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    /* ------------------------------------------------------------------ *
     * Sidebar itself
     * ------------------------------------------------------------------ */

    public function test_sidebar_links_to_every_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.books.index'), false)
            ->assertSee(route('admin.authors.index'), false)
            ->assertSee(route('admin.categories.index'), false)
            ->assertSee(route('admin.orders.index'), false)
            ->assertSee(route('admin.orders.pending'), false)
            ->assertSee(route('admin.orders.paid'), false)
            ->assertSee(route('admin.settings.payments'), false);
    }

    public function test_dashboard_no_longer_repeats_the_catalogue_and_sales_cards(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Manage your catalogue from the side menu')
            ->assertDontSee('Catalogue management')
            ->assertDontSee('Sales &amp; revenue', false)
            ->assertDontSee('Sales &amp; revenue');
    }

    public function test_dashboard_still_shows_its_live_count_cards(): void
    {
        Book::factory()->count(3)->create();

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Total books')
            ->assertSee('3');
    }

    public function test_sidebar_links_carry_no_count_badges(): void
    {
        // The sidebar used to trail a count on every link, which meant seven
        // COUNT queries on every admin page. The badges are gone; this guards
        // against them creeping back in.
        $this->order(Order::STATUS_PENDING, 'ORD-BADGE-1');
        $this->order(Order::STATUS_PENDING, 'ORD-BADGE-2');
        $this->order(Order::STATUS_PAID, 'ORD-BADGE-3');

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();

        $response->assertDontSee('badge-warning', false);
        $response->assertDontSee('badge-success', false);
        $response->assertDontSee('adminCounts', false);
        $this->assertCount(3, Order::all());
    }

    /* ------------------------------------------------------------------ *
     * The dedicated sales pages really do filter
     * ------------------------------------------------------------------ */

    public function test_pending_page_lists_only_pending_orders(): void
    {
        $this->order(Order::STATUS_PENDING, 'ORD-PENDING-1');
        $this->order(Order::STATUS_PAID, 'ORD-PAID-1');

        $this->actingAs($this->admin())
            ->get(route('admin.orders.pending'))
            ->assertOk()
            ->assertSee('ORD-PENDING-1')
            ->assertDontSee('ORD-PAID-1');
    }

    public function test_paid_page_lists_only_paid_orders(): void
    {
        $this->order(Order::STATUS_PENDING, 'ORD-PENDING-2');
        $this->order(Order::STATUS_PAID, 'ORD-PAID-2');

        $this->actingAs($this->admin())
            ->get(route('admin.orders.paid'))
            ->assertOk()
            ->assertSee('ORD-PAID-2')
            ->assertDontSee('ORD-PENDING-2');
    }

    public function test_all_orders_page_shows_both(): void
    {
        $this->order(Order::STATUS_PENDING, 'ORD-PENDING-3');
        $this->order(Order::STATUS_PAID, 'ORD-PAID-3');

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('ORD-PENDING-3')
            ->assertSee('ORD-PAID-3');
    }

    public function test_status_filter_still_works_on_the_all_orders_page(): void
    {
        $this->order(Order::STATUS_PENDING, 'ORD-PENDING-4');
        $this->order(Order::STATUS_FAILED, 'ORD-FAILED-4');

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index', ['status' => 'failed']))
            ->assertOk()
            ->assertSee('ORD-FAILED-4')
            ->assertDontSee('ORD-PENDING-4');
    }

    public function test_pills_point_at_the_dedicated_pages(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.orders.pending').'"', false)
            ->assertSee('href="'.route('admin.orders.paid').'"', false);
    }

    public function test_invalid_status_falls_back_to_all_orders(): void
    {
        $this->order(Order::STATUS_PENDING, 'ORD-ANY-5');

        $this->actingAs($this->admin())
            ->get(route('admin.orders.index', ['status' => 'nonsense']))
            ->assertOk()
            ->assertSee('ORD-ANY-5');
    }
}
