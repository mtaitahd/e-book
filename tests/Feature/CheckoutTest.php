<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->customer()->create();
    }

    private function addToCart(User $user, Book $book): void
    {
        $this->actingAs($user)->post(route('cart.add'), ['book_id' => $book->id]);
    }

    public function test_guest_is_redirected_from_checkout(): void
    {
        // Guests are sent back to the storefront, where the auth modal opens and
        // resumes checkout after sign-in -- not to the administrator login page.
        $this->from(route('cart.show'))->get(route('checkout.show'))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('auth_intended', route('checkout.show'));

        $this->from(route('cart.show'))->post(route('checkout.store'))
            ->assertRedirect(route('cart.show'));
    }

    public function test_checkout_with_empty_cart_redirects_to_cart(): void
    {
        $this->actingAs($this->user())
            ->get(route('checkout.show'))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('error');
    }

    public function test_checkout_page_shows_cart_lines_and_total(): void
    {
        $book = Book::factory()->published()->create(['title' => 'Checkout Preview Book', 'price' => '1200.50']);
        $user = $this->user();

        $this->addToCart($user, $book);

        $this->actingAs($user)
            ->get(route('checkout.show'))
            ->assertOk()
            ->assertSee('Checkout Preview Book')
            ->assertSee('1,200.50 TZS');
    }

    public function test_placing_order_creates_pending_order_with_correct_amounts(): void
    {
        $book = Book::factory()->published()->create(['price' => '1250.25']);
        $user = $this->user();

        $this->addToCart($user, $book);

        $this->actingAs($user)
            ->post(route('checkout.store'))
            ->assertRedirect();

        $order = $user->orders()->latest()->first();

        $this->assertNotNull($order);
        $this->assertTrue($order->isPending());
        $this->assertSame('1250.25', $order->subtotal);
        $this->assertSame('1250.25', $order->total);
        $this->assertSame('TZS', $order->currency);
        $this->assertMatchesRegularExpression('/^EBS-\d{8}-\d{4}$/', $order->order_number);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
        ]);
    }

    public function test_placing_order_creates_order_items_with_price_snapshot(): void
    {
        $first = Book::factory()->published()->create(['title' => 'Snapshot A', 'price' => '500.00']);
        $second = Book::factory()->published()->create(['title' => 'Snapshot B', 'price' => '750.50']);
        $user = $this->user();

        $this->addToCart($user, $first);
        $this->addToCart($user, $second);

        $this->actingAs($user)->post(route('checkout.store'))->assertRedirect();

        $order = $user->orders()->latest()->first();
        $this->assertSame(2, $order->items()->count());

        $this->assertEquals(2, OrderItem::where('order_id', $order->id)->sum('quantity'));
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'book_id' => $first->id,
            'unit_price' => 500.00,
            'subtotal' => 500.00,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'book_id' => $second->id,
            'unit_price' => 750.50,
            'subtotal' => 750.50,
        ]);
    }

    public function test_placing_order_clears_cart_and_flashes_reference(): void
    {
        $book = Book::factory()->published()->create(['title' => 'Cleared After Order']);
        $user = $this->user();

        $this->addToCart($user, $book);

        $this->actingAs($user)
            ->post(route('checkout.store'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $order = $user->orders()->latest()->first();

        $this->assertStringContainsString($order->order_number, session('success'));

        $this->get(route('cart.show'))->assertSee('Your cart is empty');
    }

    public function test_checkout_blocks_unavailable_books_without_creating_orders(): void
    {
        $book = Book::factory()->published()->create();
        $user = $this->user();

        $this->addToCart($user, $book);

        $book->update(['status' => Book::STATUS_ARCHIVED]);

        $this->actingAs($user)
            ->post(route('checkout.store'))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        $this->get(route('cart.show'))->assertSee('Your cart is empty');
    }
}