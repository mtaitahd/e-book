<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_belongs_to_many_categories_and_vice_versa(): void
    {
        $book = Book::factory()->published()->create();
        $category = Category::factory()->create();

        $book->categories()->attach($category);

        $this->assertTrue($book->categories()->where('categories.id', $category->id)->exists());
        $this->assertTrue($category->books()->where('books.id', $book->id)->exists());
    }

    public function test_book_belongs_to_many_authors_and_vice_versa(): void
    {
        $book = Book::factory()->published()->create();
        $author = Author::factory()->create();

        $book->authors()->attach($author);

        $this->assertTrue($book->authors()->where('authors.id', $author->id)->exists());
        $this->assertTrue($author->books()->where('books.id', $book->id)->exists());
    }

    public function test_book_uses_slug_as_route_key(): void
    {
        $book = Book::factory()->published()->create(['slug' => 'modern-web-development']);

        $this->assertSame('modern-web-development', $book->getRouteKey());
        $this->get(route('books.show', $book))->assertOk()->assertSee($book->title);
    }

    public function test_published_book_is_listed_but_draft_book_is_not(): void
    {
        $published = Book::factory()->published()->create(['title' => 'Visible Published Title']);
        $draft = Book::factory()->create(['title' => 'Hidden Draft Title']);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('Visible Published Title')
            ->assertDontSee('Hidden Draft Title');
    }

    public function test_draft_book_page_returns_not_found(): void
    {
        $draft = Book::factory()->create();

        $this->get(route('books.show', $draft))->assertNotFound();
    }

    public function test_category_page_lists_only_published_books(): void
    {
        $category = Category::factory()->create(['slug' => 'programming']);
        $published = Book::factory()->published()->create(['title' => 'Category Published Title']);
        $draft = Book::factory()->create(['title' => 'Category Hidden Draft']);

        $published->categories()->attach($category);
        $draft->categories()->attach($category);

        $this->get(route('categories.show', $category))
            ->assertOk()
            ->assertSee('Category Published Title')
            ->assertDontSee('Category Hidden Draft');
    }

    public function test_home_page_lists_only_published_books(): void
    {
        Book::factory()->published()->create(['title' => 'Home Visible Title']);
        Book::factory()->create(['title' => 'Home Hidden Draft']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Home Visible Title')
            ->assertDontSee('Home Hidden Draft');
    }

    public function test_book_price_is_stored_and_read_as_a_fixed_decimal(): void
    {
        $book = Book::factory()->create(['price' => '125.50']);

        $this->assertSame('125.50', $book->price);
        $this->assertDatabaseHas('books', ['id' => $book->id, 'price' => 125.50]);
    }

    public function test_published_scope_filters_books(): void
    {
        $published = Book::factory()->published()->create();
        Book::factory()->create();
        Book::factory()->archived()->create();

        $this->assertSame(1, Book::published()->count());
        $this->assertTrue($published->isPublished());
    }

    public function test_author_has_many_books_through_pivot(): void
    {
        $author = Author::factory()->create();
        $bookA = Book::factory()->published()->create();
        $bookB = Book::factory()->published()->create();

        $author->books()->attach([$bookA->id, $bookB->id]);

        $this->assertCount(2, $author->books);
        $this->assertTrue($author->books->contains('id', $bookA->id));
        $this->assertTrue($author->books->contains('id', $bookB->id));
    }
}