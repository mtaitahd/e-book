<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function orderFor(User $user, string $orderNumber = null): Order
    {
        $book = Book::factory()->published()->create(['price' => '750.00']);

        return Order::create([
            'user_id' => $user->id,
            'order_number' => $orderNumber ?? 'EBS-19990101-0001',
            'subtotal' => '750.00',
            'total' => '750.00',
            'currency' => 'KES',
            'status' => Order::STATUS_PENDING,
        ])->load('items');
    }

    public function test_guest_cannot_access_customer_orders(): void
    {
        $user = User::factory()->customer()->create();
        $order = $this->orderFor($user);

        $this->from(route('cart.show'))->get(route('account.orders.index'))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('auth_intended', route('account.orders.index'));

        $this->from(route('cart.show'))->get(route('account.orders.show', $order))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('auth_intended', route('account.orders.show', $order));
    }

    public function test_customer_sees_only_their_own_orders_in_list(): void
    {
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();

        $ownerOrder = $this->orderFor($owner, 'EBS-20000101-0101');
        $this->orderFor($other, 'EBS-20000101-0202');

        $this->actingAs($owner)
            ->get(route('account.orders.index'))
            ->assertOk()
            ->assertSee('EBS-20000101-0101')
            ->assertDontSee('EBS-20000101-0202');
    }

    public function test_customer_can_view_their_own_order_detail(): void
    {
        $user = User::factory()->customer()->create();
        $book = Book::factory()->published()->create(['title' => 'Own Detail Book', 'price' => '650.00']);
        $order = $this->orderFor($user, 'EBS-20000202-0303');

        $order->items()->create([
            'book_id' => $book->id,
            'quantity' => 1,
            'unit_price' => '650.00',
            'subtotal' => '650.00',
        ]);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('EBS-20000202-0303')
            ->assertSee('Own Detail Book');
    }

    public function test_customer_cannot_view_another_users_order(): void
    {
        $user = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $order = $this->orderFor($other, 'EBS-20000303-0404');

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertNotFound();
    }

    public function test_order_detail_shows_items_and_status(): void
    {
        $user = User::factory()->customer()->create();
        $order = $this->orderFor($user, 'EBS-20000404-0505');
        $book = Book::factory()->published()->create(['title' => 'Ordered Detail Book', 'price' => '899.00']);

        $order->items()->create([
            'book_id' => $book->id,
            'quantity' => 1,
            'unit_price' => '899.00',
            'subtotal' => '899.00',
        ]);

        $this->actingAs($user)
            ->get(route('account.orders.show', $order))
            ->assertOk()
            ->assertSee('EBS-20000404-0505')
            ->assertSee('Ordered Detail Book')
            ->assertSee('899.00')
            ->assertSee('pending')
            ->assertSee('Payment status');
    }
}