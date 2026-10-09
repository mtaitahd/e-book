<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function checkout(User $user, Book $book): Order
    {
        $this->actingAs($user)->post(route('cart.add'), ['book_id' => $book->id]);
        $this->actingAs($user)->post(route('checkout.store'))->assertRedirect();

        return $user->orders()->latest()->first();
    }

    public function test_order_numbers_are_unique(): void
    {
        $user = User::factory()->customer()->create();
        $book = Book::factory()->published()->create();

        $first = $this->checkout($user, $book);
        $other = User::factory()->customer()->create();
        $secondBook = Book::factory()->published()->create();
        $second = $this->checkout($other, $secondBook);

        $this->assertNotSame($first->order_number, $second->order_number);
        $this->assertSame(2, Order::distinct()->count('order_number'));
    }

    public function test_order_item_prices_are_snapshot_and_unaffected_by_later_edits(): void
    {
        $user = User::factory()->customer()->create();
        $book = Book::factory()->published()->create(['title' => 'Snapshot Price Book', 'price' => '1000.00']);

        $order = $this->checkout($user, $book);

        $item = $order->items()->first();
        $this->assertSame('1000.00', $item->unit_price);

        $book->update(['price' => '9999.99']);

        $item->refresh();
        $this->assertSame('1000.00', $item->unit_price);
    }

    public function test_deleting_an_order_cascades_to_its_items(): void
    {
        $user = User::factory()->customer()->create();
        $book = Book::factory()->published()->create();

        $order = $this->checkout($user, $book);
        $order->delete();

        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }

    public function test_a_book_with_order_items_cannot_be_hard_deleted(): void
    {
        $user = User::factory()->customer()->create();
        $book = Book::factory()->published()->create();

        $order = $this->checkout($user, $book);
        $this->assertSame(1, OrderItem::where('order_id', $order->id)->count());

        $this->expectException(QueryException::class);

        DB::table('books')->where('id', $book->id)->delete();
    }

    public function test_order_total_is_derived_from_item_subtotals(): void
    {
        $user = User::factory()->customer()->create();

        $a = Book::factory()->published()->create(['price' => '300.25']);
        $b = Book::factory()->published()->create(['price' => '450.75']);

        $this->actingAs($user)->post(route('cart.add'), ['book_id' => $a->id]);
        $this->actingAs($user)->post(route('cart.add'), ['book_id' => $b->id]);
        $this->actingAs($user)->post(route('checkout.store'))->assertRedirect();

        $order = $user->orders()->latest()->first();

        $this->assertSame(2, $order->items()->count());
        $this->assertSame('751.00', $order->subtotal);
        $this->assertSame($order->subtotal, $order->total);

        $itemCents = $order->items->reduce(
            fn (int $carry, OrderItem $item) => $carry + (int) round(((float) $item->subtotal) * 100),
            0
        );

        $this->assertSame(75100, $itemCents);
        $this->assertSame('751.00', \App\Support\Money::fromCents($itemCents));
    }
}