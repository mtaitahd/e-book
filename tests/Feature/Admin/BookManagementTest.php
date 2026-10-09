<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookChapter;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_books(): void
    {
        $this->get(route('admin.books.index'))->assertRedirect(route('login'));
        $this->get(route('admin.books.create'))->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_admin_books(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get(route('admin.books.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.books.create'))->assertForbidden();

        $this->actingAs($customer)
            ->post(route('admin.books.store'), ['title' => 'Nope', 'price' => '1', 'status' => 'draft'])
            ->assertForbidden();
    }

    public function test_admin_can_view_books(): void
    {
        $book = Book::factory()->published()->create(['title' => 'Admin List Visible Book']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.books.index'))
            ->assertOk()
            ->assertSee('Admin List Visible Book');
    }

    public function test_admin_books_page_is_paginated_and_does_not_load_every_record(): void
    {
        Book::factory()->count(35)->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.books.index'));

        $response->assertOk();
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $response->viewData('books'));
        $this->assertLessThan(35, $response->viewData('books')->count());
    }

    public function test_admin_can_create_a_book(): void
    {
        $admin = User::factory()->admin()->create();
        $author = Author::factory()->create(['name' => 'Ruth Kimeu']);
        $category = Category::factory()->create(['name' => 'Programming']);

        $this->actingAs($admin)
            ->post(route('admin.books.store'), [
                'title' => 'Learning Laravel for Beginners',
                'description' => 'A friendly introduction.',
                'price' => '1450.75',
                'author_ids' => [$author->id],
                'category_ids' => [$category->id],
                'publisher' => 'E-Book Press',
                'status' => 'published',
                'published_at' => '2026-09-20',
                'file_type' => 'pdf',
            ])
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book created successfully.');

        $this->assertDatabaseHas('books', [
            'title' => 'Learning Laravel for Beginners',
            'slug' => 'learning-laravel-for-beginners',
            'price' => 1450.75,
            'status' => 'published',
            'publisher' => 'E-Book Press',
        ]);

        $book = Book::where('slug', 'learning-laravel-for-beginners')->first();
        $this->assertNotNull($book);
        $this->assertTrue($book->authors->contains($author));
        $this->assertTrue($book->categories->contains($category));
    }

    public function test_book_validation_works(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.books.store'), [
                'title' => '',
                'price' => 'not-a-number',
                'status' => 'invalid-status',
            ])
            ->assertSessionHasErrors(['title', 'price', 'status']);

        $this->assertDatabaseCount('books', 0);
    }

    public function test_duplicate_title_creates_a_unique_slug(): void
    {
        $admin = User::factory()->admin()->create();
        $author = Author::factory()->create();

        $payload = ['title' => 'Modern Web Development', 'price' => 1000, 'author_ids' => [$author->id], 'status' => 'draft'];

        $this->actingAs($admin)->post(route('admin.books.store'), $payload)->assertRedirect(route('admin.books.index'));
        $this->actingAs($admin)->post(route('admin.books.store'), $payload)->assertRedirect(route('admin.books.index'));

        $this->assertDatabaseHas('books', ['slug' => 'modern-web-development']);
        $this->assertDatabaseHas('books', ['slug' => 'modern-web-development-2']);

        $this->assertSame(2, Book::where('title', 'Modern Web Development')->count());
        $this->assertSame(2, Book::whereIn('slug', ['modern-web-development', 'modern-web-development-2'])->distinct()->count('slug'));
    }

    public function test_admin_can_edit_a_book(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->create(['title' => 'Old Title', 'slug' => 'old-title', 'status' => 'draft']);
        $author = Author::factory()->create();
        $book->authors()->attach($author);

        $this->actingAs($admin)
            ->put(route('admin.books.update', $book), [
                'title' => 'New Improved Title',
                'description' => 'Rewritten description.',
                'price' => '2500.00',
                'author_ids' => [$author->id],
                'category_ids' => [],
                'status' => 'published',
                'file_type' => 'epub',
            ])
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book updated successfully.');

        $book->refresh();

        $this->assertSame('New Improved Title', $book->title);
        $this->assertSame('new-improved-title', $book->slug);
        $this->assertSame('2500.00', $book->price);
        $this->assertTrue($book->isPublished());
        $this->assertNotNull($book->published_at);
    }

    public function test_draft_book_is_not_public(): void
    {
        $draft = Book::factory()->create(['title' => 'Secret Draft Title']);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertDontSee('Secret Draft Title');

        $this->get(route('books.show', $draft))->assertNotFound();
    }

    public function test_published_book_is_public(): void
    {
        $published = Book::factory()->published()->create(['title' => 'Public Published Title']);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('Public Published Title');

        $this->get(route('books.show', $published))
            ->assertOk()
            ->assertSee('Public Published Title');
    }

    public function test_archived_book_is_not_public(): void
    {
        $admin = User::factory()->admin()->create();
        $book = Book::factory()->published()->create(['title' => 'To Be Archived Title']);

        $this->actingAs($admin)
            ->delete(route('admin.books.destroy', $book))
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book archived successfully.');

        $this->assertDatabaseHas('books', ['id' => $book->id, 'status' => 'archived']);

$this->get(route('books.index'))->assertDontSee('To Be Archived Title');
        $this->get(route('books.show', $book))->assertNotFound();
    }

    public function test_admin_can_permanently_delete_a_book_without_sales_history(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $author = Author::factory()->create();
        $category = Category::factory()->create();

        Storage::disk('local')->put('ebooks/to-delete.pdf', '%PDF-test');
        Storage::disk('public')->put('covers/to-delete.jpg', 'cover');

        $book = Book::factory()->create([
            'title' => 'Temporary Book',
            'file_path' => 'ebooks/to-delete.pdf',
            'cover_image' => 'covers/to-delete.jpg',
            'status' => Book::STATUS_ARCHIVED,
        ]);
        $book->authors()->attach($author);
        $book->categories()->attach($category);
        BookChapter::factory()->create(['book_id' => $book->id, 'title' => 'Chapter One']);

        $this->actingAs($admin)
            ->delete(route('admin.books.force-destroy', $book))
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book permanently deleted.');

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('book_chapters', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('author_book', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('book_category', ['book_id' => $book->id]);
        Storage::disk('local')->assertMissing('ebooks/to-delete.pdf');
        Storage::disk('public')->assertMissing('covers/to-delete.jpg');
    }

    public function test_sold_book_cannot_be_permanently_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        $book = Book::factory()->create(['title' => 'Sold Book']);

        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => Order::generateNumber(),
            'subtotal' => '1000.00',
            'total' => '1000.00',
            'currency' => 'KES',
            'status' => Order::STATUS_PENDING,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'book_id' => $book->id,
            'quantity' => 1,
            'unit_price' => '1000.00',
            'subtotal' => '1000.00',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.books.force-destroy', $book))
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('books', ['id' => $book->id, 'title' => 'Sold Book']);
        $this->assertDatabaseHas('order_items', ['book_id' => $book->id]);
    }
}
