<?php

namespace Tests\Feature\Book;

use App\Models\Book;
use App\Models\BookChapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two places chapter text is shown without a paid order: an admin preview,
 * and the public free sample. Both are deliberately narrow, and both are checked
 * here from the outside.
 */
class ChapterPreviewTest extends TestCase
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

    private function book(array $attributes = []): Book
    {
        return Book::factory()->published()->create(array_merge([
            'book_format' => Book::FORMAT_ONLINE,
            'file_path' => null,
            'file_type' => null,
        ], $attributes));
    }

    /* ------------------------------------------------------------------ *
     * Admin preview
     * ------------------------------------------------------------------ */

    public function test_admin_can_preview_a_chapter(): void
    {
        $book = $this->book();
        $chapter = BookChapter::factory()->create([
            'book_id' => $book->id,
            'content' => '<h2>Chapter</h2><p>Preview body.</p>',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.books.chapters.preview', [$book, $chapter]))
            ->assertOk()
            ->assertViewIs('admin.books.chapters.preview')
            ->assertSee('Preview body.', false);
    }

    public function test_admin_preview_is_sanitised(): void
    {
        $book = $this->book();
        $chapter = BookChapter::factory()->create(['book_id' => $book->id]);

        $chapter->forceFill([
            'content' => '<p>Safe</p><script>alert(1)</script><img src=x onerror=alert(2)>',
        ])->save();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.books.chapters.preview', [$book, $chapter]));

        $response->assertOk();

        $html = (string) $response->viewData('html');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('<p>Safe</p>', $html);
    }

    public function test_a_customer_cannot_use_the_admin_preview(): void
    {
        $book = $this->book();
        $chapter = BookChapter::factory()->create(['book_id' => $book->id]);

        $this->actingAs($this->customer())
            ->get(route('admin.books.chapters.preview', [$book, $chapter]))
            ->assertForbidden();
    }

    public function test_guests_cannot_use_the_admin_preview(): void
    {
        $book = $this->book();
        $chapter = BookChapter::factory()->create(['book_id' => $book->id]);

        $this->get(route('admin.books.chapters.preview', [$book, $chapter]))
            ->assertRedirect(route('login'));
    }

    public function test_admin_cannot_preview_a_chapter_through_the_wrong_book(): void
    {
        $book = $this->book();
        $otherBook = $this->book();
        $chapter = BookChapter::factory()->create(['book_id' => $otherBook->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.books.chapters.preview', [$book, $chapter]))
            ->assertNotFound();
    }

    /* ------------------------------------------------------------------ *
     * Public free sample
     * ------------------------------------------------------------------ */

    public function test_a_visitor_can_read_a_free_chapter(): void
    {
        $book = $this->book();
        $chapter = BookChapter::factory()->create([
            'book_id' => $book->id,
            'is_free' => true,
            'content' => '<p>Sample text.</p>',
        ]);

        $this->get(route('books.preview', [$book, $chapter]))
            ->assertOk()
            ->assertViewIs('books.preview')
            ->assertSee('Sample text.', false);
    }

    public function test_a_paid_chapter_is_not_publicly_readable(): void
    {
        $book = $this->book();
        $free = BookChapter::factory()->create(['book_id' => $book->id, 'is_free' => true]);
        $paid = BookChapter::factory()->create(['book_id' => $book->id, 'is_free' => false]);

        $this->get(route('books.preview', [$book, $paid]))->assertNotFound();
        $this->get(route('books.preview', [$book, $free]))->assertOk();
    }

    public function test_a_free_chapter_of_an_unpublished_book_is_not_readable(): void
    {
        $book = Book::factory()->create([
            'book_format' => Book::FORMAT_ONLINE,
            'status' => Book::STATUS_DRAFT,
        ]);
        $chapter = BookChapter::factory()->create(['book_id' => $book->id, 'is_free' => true]);

        $this->get(route('books.preview', [$book, $chapter]))->assertNotFound();
    }

    public function test_a_free_chapter_of_a_pdf_only_book_is_not_readable(): void
    {
        $book = $this->book([
            'book_format' => Book::FORMAT_PDF,
            'file_path' => 'ebooks/pdf-only.pdf',
            'file_type' => 'pdf',
        ]);
        $chapter = BookChapter::factory()->create(['book_id' => $book->id, 'is_free' => true]);

        $this->get(route('books.preview', [$book, $chapter]))->assertNotFound();
    }

    public function test_a_free_chapter_of_another_book_is_not_readable(): void
    {
        $book = $this->book();
        $otherBook = $this->book();
        $foreign = BookChapter::factory()->create(['book_id' => $otherBook->id, 'is_free' => true]);

        $this->get(route('books.preview', [$book, $foreign]))->assertNotFound();
    }

    public function test_the_public_preview_is_sanitised(): void
    {
        $book = $this->book();
        $chapter = BookChapter::factory()->create(['book_id' => $book->id, 'is_free' => true]);

        $chapter->forceFill([
            'content' => '<p>Free</p><script>alert(document.cookie)</script>',
        ])->save();

        $response = $this->get(route('books.preview', [$book, $chapter]));

        $response->assertOk();

        $html = (string) $response->viewData('html');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('<p>Free</p>', $html);
    }

    public function test_buying_from_the_preview_adds_the_book_to_the_cart(): void
    {
        $book = $this->book();
        $chapter = BookChapter::factory()->create(['book_id' => $book->id, 'is_free' => true]);

        $this->post(route('cart.add'), ['book_id' => $book->id])
            ->assertRedirect(route('books.show', $book));

        $this->get(route('cart.show'))->assertOk()->assertSee($book->title);

        // Sanity: the preview page itself offers the same action.
        $this->get(route('books.preview', [$book, $chapter]))->assertOk();
    }
}
