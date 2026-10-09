<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->customer()->create();
    }

    public function test_guest_can_view_empty_cart(): void
    {
        $this->get(route('cart.show'))
            ->assertOk()
            ->assertSee('Your cart is empty');
    }

    public function test_cannot_add_unpublished_book_to_cart(): void
    {
        $book = Book::factory()->create(['title' => 'Draft Only Book']);

        $this->actingAs($this->user())
            ->post(route('cart.add'), ['book_id' => $book->id])
            ->assertNotFound();

        $this->get(route('cart.show'))->assertDontSee('Draft Only Book');
    }

    public function test_adding_a_book_displays_it_in_cart(): void
    {
        $book = Book::factory()->published()->create(['title' => 'Wanted Cart Book']);

        $this->actingAs($this->user())
            ->post(route('cart.add'), ['book_id' => $book->id])
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success');

        $this->get(route('cart.show'))
            ->assertOk()
            ->assertSee('Wanted Cart Book');
    }

    public function test_duplicate_additions_do_not_duplicate_lines(): void
    {
        $book = Book::factory()->published()->create(['title' => 'Single Line Book']);
        $user = $this->user();

        foreach (range(1, 3) as $index) {
            $this->actingAs($user)
                ->post(route('cart.add'), ['book_id' => $book->id])
                ->assertRedirect(route('books.show', $book));
        }

        $response = $this->actingAs($user)->get(route('cart.show'));

        $response->assertSee('Single Line Book');
        $this->assertSame(1, $response->viewData('lines')->count());
    }

    public function test_removing_a_book_leaves_other_lines_untouched(): void
    {
        $first = Book::factory()->published()->create(['title' => 'Keep Me Book']);
        $second = Book::factory()->published()->create(['title' => 'Drop Me Book']);

        $user = $this->user();

        $this->actingAs($user)->post(route('cart.add'), ['book_id' => $first->id]);
        $this->actingAs($user)->post(route('cart.add'), ['book_id' => $second->id]);

        $this->actingAs($user)
            ->post(route('cart.remove'), ['book_id' => $second->id])
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('success');

        $this->get(route('cart.show'))
            ->assertSee('Keep Me Book')
            ->assertDontSee('Drop Me Book');
    }

    public function test_clearing_the_cart_empties_it(): void
    {
        $book = Book::factory()->published()->create(['title' => 'Clear Me Book']);

        $user = $this->user();

        $this->actingAs($user)->post(route('cart.add'), ['book_id' => $book->id]);
        $this->actingAs($user)->post(route('cart.show'))->assertSee('Clear Me Book');

        $this->actingAs($user)
            ->post(route('cart.clear'))
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('success');

        $this->get(route('cart.show'))->assertSee('Your cart is empty');
    }
}