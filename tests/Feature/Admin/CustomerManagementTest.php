<?php

namespace Tests\Feature\Admin;

use App\Models\Book;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_view_the_customer_list(): void
    {
        $customer = User::factory()->customer()->create([
            'name' => 'Jane Shopper',
            'email' => 'jane@example.com',
            'phone' => '255713111222',
        ]);
        $hidden = User::factory()->admin()->create(['name' => 'Secret Admin']);

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Jane Shopper')
            ->assertSee('jane@example.com')
            ->assertSee('255713111222')
            ->assertDontSee('Secret Admin');
    }

    public function test_admin_can_delete_a_customer_who_never_engaged(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Throwaway']);

        $this->actingAs($this->admin())
            ->delete(route('admin.customers.destroy', $customer))
            ->assertRedirect(route('admin.customers.index'))
            ->assertSessionHas('success', 'Customer deleted successfully.');

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
    }

    public function test_customer_with_order_history_cannot_be_deleted(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Ordered Once']);

        Order::create([
            'user_id' => $customer->id,
            'order_number' => 'EBS-19990101-0001',
            'subtotal' => '1500.00',
            'total' => '1500.00',
            'currency' => 'TZS',
            'status' => Order::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.customers.destroy', $customer))
            ->assertRedirect(route('admin.customers.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $customer->id]);
    }

    public function test_customer_with_a_purchase_cannot_be_deleted(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Paid For Books']);
        $book = Book::factory()->published()->create(['price' => '1500.00']);

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'EBS-19990202-0002',
            'subtotal' => '1500.00',
            'total' => '1500.00',
            'currency' => 'TZS',
            'status' => Order::STATUS_PAID,
        ]);

        $order->items()->create([
            'book_id' => $book->id,
            'quantity' => 1,
            'unit_price' => '1500.00',
            'subtotal' => '1500.00',
        ]);

        Purchase::create([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'order_item_id' => $order->items()->firstOrFail()->id,
            'book_id' => $book->id,
            'amount' => 1500,
            'currency' => 'TZS',
            'purchased_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.customers.destroy', $customer))
            ->assertRedirect(route('admin.customers.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $customer->id]);
    }

    public function test_administrator_account_cannot_be_deleted_from_the_customers_list(): void
    {
        $target = $this->admin();

        $this->actingAs($target)
            ->delete(route('admin.customers.destroy', $target))
            ->assertRedirect(route('admin.customers.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_customer_cannot_access_admin_customers(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();

        $this->actingAs($customer)->get(route('admin.customers.index'))->assertForbidden();
        $this->actingAs($customer)->delete(route('admin.customers.destroy', $other))->assertForbidden();
    }
}