<?php

namespace Tests\Feature\Book;

use App\Models\Author;
use App\Models\Book;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A book is either free or priced, and the two kinds of customer go down
 * different paths: a free book lands straight in the library, a paid one is
 * sold through the cart. These tests pin both paths down, including the edges
 * where a book changes kind after it has already been seen.
 */
class FreeBookPricingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function customer(): User
    {
        return User::factory()->customer()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function bookPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'A Perfectly Ordinary Book',
            'description' => 'Nothing special.',
            'author_ids' => [Author::factory()->create()->id],
            'status' => Book::STATUS_PUBLISHED,
            'book_format' => Book::FORMAT_PDF,
        ], $overrides);
    }

    /*
    |---------------------------------------------------------------------------
    | Admin: choosing free or paid
    |---------------------------------------------------------------------------
    */

    public function test_admin_can_create_a_paid_book_with_a_price(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload([
                'title' => 'Paid Book With A Price',
                'pricing_type' => Book::PRICING_PAID,
                'price' => '4500.50',
            ]))
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success');

        $book = Book::where('slug', 'paid-book-with-a-price')->firstOrFail();

        $this->assertTrue($book->isPaid());
        $this->assertFalse($book->isFree());
        $this->assertSame('4500.50', $book->price);
        $this->assertSame('4500.50', $book->salePrice());
    }

    public function test_admin_can_create_a_free_book_without_entering_a_price(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload([
                'title' => 'Free Book No Price',
                'pricing_type' => Book::PRICING_FREE,
            ]))
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success');

        $book = Book::where('slug', 'free-book-no-price')->firstOrFail();

        $this->assertTrue($book->isFree());
        $this->assertSame('0.00', $book->price);
        $this->assertSame('0.00', $book->salePrice());
    }

    public function test_a_free_book_stores_zero_even_if_a_price_was_typed(): void
    {
        $this->actingAs($this->admin())->post(route('admin.books.store'), $this->bookPayload([
            'title' => 'Free But A Price Was Typed',
            'pricing_type' => Book::PRICING_FREE,
            'price' => '9999',
        ]));

        $book = Book::where('slug', 'free-but-a-price-was-typed')->firstOrFail();

        // A book advertised as free must never be able to contribute money to
        // an order, whatever the form had lying around in the price box.
        $this->assertTrue($book->isFree());
        $this->assertSame('0.00', $book->price);
        $this->assertSame('0.00', $book->salePrice());
    }

    public function test_a_paid_book_must_be_given_a_price(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload([
                'title' => 'Paid But No Price',
                'pricing_type' => Book::PRICING_PAID,
            ]))
            ->assertSessionHasErrors('price');

        $this->assertDatabaseMissing('books', ['slug' => 'paid-but-no-price']);
    }

    public function test_a_paid_book_cannot_be_given_a_price_of_zero(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload([
                'title' => 'Paid But Price Zero',
                'pricing_type' => Book::PRICING_PAID,
                'price' => '0',
            ]))
            ->assertSessionHasErrors('price');

        $this->assertDatabaseMissing('books', ['slug' => 'paid-but-price-zero']);
    }

    public function test_the_pricing_type_must_be_a_known_value(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload([
                'title' => 'Nonsense Pricing Type',
                'pricing_type' => 'rent-a-book',
                'price' => '100',
            ]))
            ->assertSessionHasErrors('pricing_type');

        $this->assertDatabaseMissing('books', ['slug' => 'nonsense-pricing-type']);
    }

    public function test_a_book_with_a_price_and_no_pricing_choice_is_still_paid(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload([
                'title' => 'Legacy Request With Only A Price',
                'price' => '2500',
            ]))
            ->assertRedirect(route('admin.books.index'));

        $this->assertTrue(Book::where('slug', 'legacy-request-with-only-a-price')->firstOrFail()->isPaid());
    }

    public function test_admin_can_switch_a_paid_book_to_free(): void
    {
        $book = Book::factory()->priced(7500)->published()->create([
            'title' => 'Was Expensive',
            'slug' => 'was-expensive',
        ]);
        $author = Author::factory()->create();
        $book->authors()->attach($author);

        $this->actingAs($this->admin())
            ->put(route('admin.books.update', $book), [
                'title' => $book->title,
                'pricing_type' => Book::PRICING_FREE,
                'author_ids' => [$author->id],
                'status' => Book::STATUS_PUBLISHED,
            ])
            ->assertRedirect(route('admin.books.index'));

        $book->refresh();

        $this->assertTrue($book->isFree());
        $this->assertSame('0.00', $book->price);
    }

    public function test_admin_can_switch_a_free_book_to_paid_with_a_price(): void
    {
        $book = Book::factory()->free()->published()->create([
            'title' => 'Was Free',
            'slug' => 'was-free',
        ]);
        $author = Author::factory()->create();
        $book->authors()->attach($author);

        $this->actingAs($this->admin())
            ->put(route('admin.books.update', $book), [
                'title' => $book->title,
                'pricing_type' => Book::PRICING_PAID,
                'price' => '3200',
                'author_ids' => [$author->id],
                'status' => Book::STATUS_PUBLISHED,
            ])
            ->assertRedirect(route('admin.books.index'));

        $book->refresh();

        $this->assertTrue($book->isPaid());
        $this->assertSame('3200.00', $book->price);
    }

    public function test_the_book_form_offers_a_free_or_paid_choice(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.books.create'))
            ->assertOk()
            ->assertSee('name="pricing_type"', false)
            ->assertSee('value="free"', false)
            ->assertSee('value="paid"', false)
            ->assertSee('name="price"', false);
    }

    /*
    |---------------------------------------------------------------------------
    | Storefront: how a free book is presented
    |---------------------------------------------------------------------------
    */

    public function test_the_storefront_shows_free_instead_of_a_zero_price(): void
    {
        $currency = (string) config('shop.currency');
        $free = Book::factory()->free()->published()->create(['title' => 'Free To Read Book']);
        $paid = Book::factory()->priced(5000)->published()->create(['title' => 'Sold For Money Book']);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('Free To Read Book')
            ->assertSee('Sold For Money Book')
            ->assertSee('Free', false);

        // Signed in and not yet an owner, a free book offers the claim button
        // and carries no amount at all: not a zero, not a currency code.
        $this->actingAs($this->customer())->get(route('books.show', $free))
            ->assertOk()
            ->assertSee('Add to my library')
            ->assertDontSee($currency)
            ->assertDontSee('0.00')
            ->assertDontSee('Add to cart');

        $this->get(route('books.show', $paid))
            ->assertOk()
            ->assertSee($currency)
            ->assertSee('Add to cart')
            ->assertDontSee('Add to my library');
    }

    public function test_a_guest_is_asked_to_sign_in_to_claim_a_free_book(): void
    {
        $book = Book::factory()->free()->published()->create();

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('Sign in')
            ->assertDontSee('Add to cart');
    }

    /*
    |---------------------------------------------------------------------------
    | Claiming: free books skip the cart and the payment
    |---------------------------------------------------------------------------
    */

    public function test_claiming_a_free_book_puts_it_straight_into_the_library(): void
    {
        $book = Book::factory()->free()->published()->create(['title' => 'Claimed For Nothing']);
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post(route('books.claim-free', $book))
            ->assertRedirect();

        $purchase = Purchase::where('user_id', $customer->id)->where('book_id', $book->id)->firstOrFail();

        // The entitlement is real: it is backed by a paid order worth nothing,
        // which is what every reader and download endpoint authorises against.
        $this->assertSame('0.00', $purchase->amount);
        $this->assertTrue($purchase->order->isPaid());
        $this->assertSame('0.00', $purchase->order->total);

        $this->actingAs($customer)
            ->get(route('account.purchases.index'))
            ->assertOk()
            ->assertSee('Claimed For Nothing');
    }

    public function test_claiming_twice_does_not_duplicate_the_entitlement(): void
    {
        $book = Book::factory()->free()->published()->create();
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('books.claim-free', $book));
        $this->actingAs($customer)->post(route('books.claim-free', $book));

        $this->assertSame(1, Purchase::where('user_id', $customer->id)->where('book_id', $book->id)->count());
        $this->assertSame(1, Order::where('user_id', $customer->id)->count());
    }

    public function test_claiming_a_book_leaves_the_cart_alone(): void
    {
        $book = Book::factory()->free()->published()->create();
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('books.claim-free', $book));

        $this->actingAs($customer)->get(route('cart.show'))->assertOk();
        $this->assertSame([], session('cart.items', []));
    }

    public function test_a_guest_cannot_claim_a_free_book(): void
    {
        $book = Book::factory()->free()->published()->create();

        // Guests are bounced to the storefront sign-in, not given the book.
        $this->post(route('books.claim-free', $book))->assertRedirect();

        $this->assertSame(0, Purchase::count());
    }

    public function test_a_paid_book_cannot_be_claimed_for_free(): void
    {
        $book = Book::factory()->priced(5000)->published()->create();
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('books.claim-free', $book))->assertNotFound();

        $this->assertSame(0, Purchase::count());
    }

    public function test_an_unpublished_book_cannot_be_claimed(): void
    {
        $book = Book::factory()->free()->create();

        $this->actingAs($this->customer())->post(route('books.claim-free', $book))->assertNotFound();

        $this->assertSame(0, Purchase::count());
    }

    /*
    |---------------------------------------------------------------------------
    | The cart: a free book is never sellable
    |---------------------------------------------------------------------------
    */

    public function test_a_free_book_cannot_be_added_to_the_cart(): void
    {
        $book = Book::factory()->free()->published()->create();

        $this->actingAs($this->customer())
            ->post(route('cart.add'), ['book_id' => $book->id])
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('error');

        $this->get(route('cart.show'))->assertOk()->assertSee('Your cart is empty');
    }

    public function test_a_book_that_turns_free_after_being_carted_is_rejected_at_checkout(): void
    {
        $book = Book::factory()->priced(5000)->published()->create(['title' => 'Suddenly Free']);
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('cart.add'), ['book_id' => $book->id]);
        $this->actingAs($customer)->get(route('cart.show'))->assertOk()->assertSee('Suddenly Free');

        // The admin gives it away after it was already in someone's cart.
        $book->update(['pricing_type' => Book::PRICING_FREE, 'price' => 0]);

        $this->actingAs($customer)
            ->post(route('checkout.store'))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('error');

        // No order for nothing, and the free book is taken back out.
        $this->assertSame(0, Order::count());
        $this->actingAs($customer)->get(route('cart.show'))->assertOk()->assertSee('Your cart is empty');
    }

    public function test_a_paid_book_still_checks_out_normally(): void
    {
        $book = Book::factory()->priced(5000)->published()->create();
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('cart.add'), ['book_id' => $book->id]);
        $this->actingAs($customer)->post(route('checkout.store'))->assertRedirect();

        $order = Order::where('user_id', $customer->id)->firstOrFail();

        $this->assertSame('5000.00', $order->total);
        $this->assertSame('5000.00', $order->items->first()->unit_price);
    }
}
