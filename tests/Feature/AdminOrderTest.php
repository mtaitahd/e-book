<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    private function orderFor(User $user, string $orderNumber, string $status = Order::STATUS_PENDING): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'order_number' => $orderNumber,
            'subtotal' => '900.00',
            'total' => '900.00',
            'currency' => 'KES',
            'status' => $status,
        ]);
    }

    public function test_guest_cannot_access_admin_orders(): void
    {
        $this->get(route('admin.orders.index'))->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_admin_orders(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get(route('admin.orders.index'))->assertForbidden();
        $this->assertSame(403, $this->actingAs($customer)->get(route('admin.orders.index'))->status());
    }

    public function test_admin_can_view_orders_list(): void
    {
        $customer = User::factory()->customer()->create();
        $this->orderFor($customer, 'EBS-19950505-1111');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('EBS-19950505-1111');
    }

    public function test_admin_can_filter_orders_by_status(): void
    {
        $customer = User::factory()->customer()->create();
        $this->orderFor($customer, 'EBS-19960606-1111', Order::STATUS_PENDING);
        $this->orderFor($customer, 'EBS-19960707-2222', Order::STATUS_PAID);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index', ['status' => 'paid']))
            ->assertOk()
            ->assertSee('EBS-19960707-2222')
            ->assertDontSee('EBS-19960606-1111');
    }

    public function test_admin_can_view_order_detail_readonly(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->orderFor($customer, 'EBS-19980808-3333');
        $book = Book::factory()->published()->create(['title' => 'Admin Visible Item', 'price' => '400.00']);

        $order->items()->create([
            'book_id' => $book->id,
            'quantity' => 1,
            'unit_price' => '400.00',
            'subtotal' => '400.00',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('EBS-19980808-3333')
            ->assertSee('Admin Visible Item')
            ->assertDontSee('Mark as paid');
    }

    public function test_admin_orders_index_is_paginated(): void
    {
        $customer = User::factory()->customer()->create();

        foreach (range(1, 20) as $index) {
            $this->orderFor($customer, sprintf('EBS-19990909-%04d', $index));
        }

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index'));

        $response->assertOk();
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $response->viewData('orders'));
        $this->assertLessThan(20, $response->viewData('orders')->count());
    }

    public function test_admin_pending_page_shows_bulk_delete_controls(): void
    {
        $customer = User::factory()->customer()->create();
        $this->orderFor($customer, 'EBS-19991010-1111');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.orders.pending'))
            ->assertOk()
            ->assertSee('Delete selected')
            ->assertSee('orderSelectAll');

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertDontSee('bulkDeleteForm');
    }

    public function test_admin_can_bulk_delete_pending_orders_only(): void
    {
        $customer = User::factory()->customer()->create();

        $pendingA = $this->orderFor($customer, 'EBS-19991111-1111');
        $pendingB = $this->orderFor($customer, 'EBS-19991111-2222');

        $paid = $this->orderFor($customer, 'EBS-19991111-3333', Order::STATUS_PAID);

        $book = Book::factory()->published()->create(['title' => 'Bulk Delete Item', 'price' => '400.00']);
        $pendingA->items()->create([
            'book_id' => $book->id,
            'quantity' => 1,
            'unit_price' => '400.00',
            'subtotal' => '400.00',
        ]);
        $pendingA->payments()->create([
            'provider' => 'abliner',
            'payment_type' => 'mobile',
            'idempotency_key' => 'tmp-bulk-delete-1',
            'amount' => 90000,
            'currency' => 'TZS',
            'status' => 'pending',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.orders.bulk-destroy'), [
                'ids' => [$pendingA->id, $pendingB->id, $paid->id],
            ])
            ->assertSessionHas('success', '2 pending orders deleted.');

        $this->assertDatabaseMissing('orders', ['id' => $pendingA->id]);
        $this->assertDatabaseMissing('orders', ['id' => $pendingB->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $pendingA->id]);
        $this->assertDatabaseMissing('payments', ['order_id' => $pendingA->id]);

        $this->assertDatabaseHas('orders', ['id' => $paid->id]);
    }

    public function test_bulk_delete_requires_a_selection(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->orderFor($customer, 'EBS-19991112-1111');

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.orders.bulk-destroy'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_admin_can_delete_a_single_pending_order(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->orderFor($customer, 'EBS-19991113-1111');

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.orders.destroy', $order))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_a_paid_order_cannot_be_deleted(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->orderFor($customer, 'EBS-19991114-1111', Order::STATUS_PAID);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.orders.destroy', $order))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_order_deletion_is_admin_only(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->orderFor($customer, 'EBS-19991115-1111');

        $this->delete(route('admin.orders.destroy', $order))->assertRedirect(route('login'));
        $this->actingAs($customer)->delete(route('admin.orders.destroy', $order))->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
}