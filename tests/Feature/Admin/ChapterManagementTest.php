<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookChapter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChapterManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function onlineBook(array $attributes = []): Book
    {
        return Book::factory()->online()->create($attributes);
    }

    public function test_guest_cannot_access_chapter_management(): void
    {
        $book = $this->onlineBook();

        $this->get(route('admin.books.chapters.index', $book))->assertRedirect(route('login'));
        $this->get(route('admin.books.chapters.create', $book))->assertRedirect(route('login'));
        $this->post(route('admin.books.chapters.store', $book), [])->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_chapter_management(): void
    {
        $book = $this->onlineBook();
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->get(route('admin.books.chapters.index', $book))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.books.chapters.create', $book))->assertForbidden();
        $this->actingAs($customer)
            ->post(route('admin.books.chapters.store', $book), [
                'title' => 'Sneaky',
                'content' => '<p>gotcha</p>',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_a_chapter(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook(['title' => 'Digital Edition']);

        $this->actingAs($admin)
            ->post(route('admin.books.chapters.store', $book), [
                'title' => 'Chapter One: Asubuhi',
                'content' => '<p>Habari za asubuhi.</p><p>Second paragraph.</p>',
                'is_free' => '1',
            ])
            ->assertRedirect(route('admin.books.chapters.index', $book))
            ->assertSessionHas('success');

        $chapter = $book->chapters()->sole();

        $this->assertSame('Chapter One: Asubuhi', $chapter->title);
        $this->assertSame('chapter-one-asubuhi', $chapter->slug);
        $this->assertStringContainsString('Habari za asubuhi.', $chapter->content);
        $this->assertTrue($chapter->is_free);
        $this->assertSame(1, $chapter->position);
    }

    public function test_chapter_position_defaults_to_the_next_slot(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();

        BookChapter::factory()->for($book)->create(['position' => 1]);
        BookChapter::factory()->for($book)->create(['position' => 2]);

        $this->actingAs($admin)->post(route('admin.books.chapters.store', $book), [
            'title' => 'Third',
            'content' => '<p>Body</p>',
        ]);

        $this->assertSame(3, BookChapter::where('book_id', $book->id)->max('position'));
    }

    public function test_chapter_content_is_sanitized_on_save(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();

        $this->actingAs($admin)->post(route('admin.books.chapters.store', $book), [
            'title' => 'Dangerous',
            'content' => '<p>Safe text</p>'
                .'<script>alert(1)</script>'
                .'<img src=x onerror=alert(2)>'
                .'<a href="javascript:alert(3)">bad link</a>'
                .'<div style="position:fixed" onclick="alert(4)">overlay</div>',
        ]);

        $content = $book->chapters()->sole()->content;

        $this->assertStringContainsString('Safe text', $content);
        $this->assertStringNotContainsString('script', $content);
        $this->assertStringNotContainsString('alert', $content);
        $this->assertStringNotContainsString('onerror', $content);
        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringNotContainsString('javascript:', $content);
        $this->assertStringNotContainsString('style=', $content);
    }

    public function test_chapter_content_is_required(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();

        $this->actingAs($admin)
            ->post(route('admin.books.chapters.store', $book), [
                'title' => 'Empty',
                'content' => '',
            ])
            ->assertSessionHasErrors('content');

        $this->assertSame(0, $book->chapters()->count());
    }

    public function test_chapter_with_only_rejected_markup_is_rejected(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();

        // The allowlist strips this to nothing, so it must not become a
        // permanently empty chapter.
        $this->actingAs($admin)
            ->post(route('admin.books.chapters.store', $book), [
                'title' => 'Only Script',
                'content' => '<script>alert(1)</script>',
            ])
            ->assertSessionHasErrors('content');

        $this->assertSame(0, $book->chapters()->count());
    }

    public function test_slug_must_be_unique_within_the_book_only(): void
    {
        $admin = $this->admin();

        $first = $this->onlineBook(['title' => 'Book One']);
        $second = $this->onlineBook(['title' => 'Book Two']);

        $this->actingAs($admin)->post(route('admin.books.chapters.store', $first), [
            'title' => 'Introduction',
            'content' => '<p>One</p>',
        ]);

        // The same slug in a different book is perfectly fine.
        $this->actingAs($admin)
            ->post(route('admin.books.chapters.store', $second), [
                'title' => 'Introduction',
                'content' => '<p>Two</p>',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('introduction', $first->chapters()->sole()->slug);
        $this->assertSame('introduction', $second->chapters()->sole()->slug);
    }

    public function test_duplicate_slug_inside_one_book_is_rejected(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();

        BookChapter::factory()->for($book)->create(['slug' => 'intro', 'title' => 'Intro']);

        $this->actingAs($admin)
            ->post(route('admin.books.chapters.store', $book), [
                'title' => 'Clash',
                'slug' => 'intro',
                'content' => '<p>Body</p>',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_admin_can_update_a_chapter(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();
        $chapter = BookChapter::factory()->for($book)->create([
            'title' => 'Draft Title',
            'content' => '<p>Old</p>',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.books.chapters.update', [$book, $chapter]), [
                'title' => 'Final Title',
                'content' => '<p>New <strong>body</strong></p>',
            ])
            ->assertRedirect(route('admin.books.chapters.index', $book));

        $chapter->refresh();

        $this->assertSame('Final Title', $chapter->title);
        $this->assertStringContainsString('<strong>body</strong>', $chapter->content);
    }

    public function test_a_chapter_cannot_be_edited_through_the_wrong_book(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();
        $other = $this->onlineBook();
        $chapter = BookChapter::factory()->for($other)->create();

        $this->actingAs($admin)
            ->get(route('admin.books.chapters.edit', [$book, $chapter]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->put(route('admin.books.chapters.update', [$book, $chapter]), [
                'title' => 'Hijacked',
                'content' => '<p>Hijacked</p>',
            ])
            ->assertNotFound();

        $this->assertStringNotContainsString('Hijacked', $chapter->fresh()->content);
    }

    public function test_admin_can_delete_a_chapter(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();
        $chapter = BookChapter::factory()->for($book)->create();

        $this->actingAs($admin)
            ->delete(route('admin.books.chapters.destroy', [$book, $chapter]))
            ->assertRedirect(route('admin.books.chapters.index', $book));

        $this->assertSame(0, $book->chapters()->count());
    }

    public function test_admin_can_reorder_chapters(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();

        $a = BookChapter::factory()->for($book)->create(['position' => 1, 'title' => 'A']);
        $b = BookChapter::factory()->for($book)->create(['position' => 2, 'title' => 'B']);
        $c = BookChapter::factory()->for($book)->create(['position' => 3, 'title' => 'C']);

        $this->actingAs($admin)
            ->post(route('admin.books.chapters.reorder', $book), [
                'order' => [$c->id, $a->id, $b->id],
            ])
            ->assertRedirect(route('admin.books.chapters.index', $book));

        $this->assertSame(['C', 'A', 'B'], $book->chapters()->pluck('title')->all());
    }

    public function test_reorder_rejects_a_chapter_from_another_book(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();
        $other = $this->onlineBook();

        $mine = BookChapter::factory()->for($book)->create();
        $theirs = BookChapter::factory()->for($other)->create(['position' => 7]);
        $originalPosition = $theirs->position;

        $this->actingAs($admin)
            ->post(route('admin.books.chapters.reorder', $book), [
                'order' => [$mine->id, $theirs->id],
            ])
            ->assertSessionHas('error');

        // The foreign chapter must be left exactly where it was.
        $this->assertSame($originalPosition, $theirs->fresh()->position);
    }

    public function test_publishing_an_online_book_without_chapters_is_blocked(): void
    {
        $admin = $this->admin();
        $author = Author::factory()->create();
        Storage::fake('local');

        $this->actingAs($admin)
            ->post(route('admin.books.store'), [
                'title' => 'Chapterless Online Book',
                'price' => '1000',
                'author_ids' => [$author->id],
                'book_format' => Book::FORMAT_ONLINE,
                'status' => Book::STATUS_PUBLISHED,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('books', ['title' => 'Chapterless Online Book']);
    }

    public function test_publishing_an_online_book_with_chapters_is_allowed(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();
        $author = Author::factory()->create();
        BookChapter::factory()->for($book)->create();

        $this->actingAs($admin)
            ->post(route('admin.books.store'), [
                'title' => 'Ready Online Book',
                'price' => '1000',
                'author_ids' => [$author->id],
                'book_format' => Book::FORMAT_ONLINE,
                'status' => Book::STATUS_DRAFT,
            ])
            ->assertRedirect(route('admin.books.index'));

        $this->actingAs($admin)
            ->put(route('admin.books.update', $book), [
                'title' => $book->title,
                'price' => '1000',
                'author_ids' => [$author->id],
                'book_format' => Book::FORMAT_ONLINE,
                'status' => Book::STATUS_PUBLISHED,
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($book->fresh()->isPublished());
    }

    public function test_a_pdf_book_can_still_be_created_without_an_uploaded_file(): void
    {
        $admin = $this->admin();
        $author = Author::factory()->create();

        // The pre-existing admin flow allows the file to be added later, and
        // the reader already degrades gracefully when it is missing.
        $this->actingAs($admin)
            ->post(route('admin.books.store'), [
                'title' => 'PDF Book Without File',
                'price' => '1000',
                'author_ids' => [$author->id],
                'book_format' => Book::FORMAT_PDF,
                'status' => Book::STATUS_DRAFT,
            ])
            ->assertSessionHasNoErrors();

        $book = Book::where('title', 'PDF Book Without File')->sole();

        $this->assertSame(Book::FORMAT_PDF, $book->book_format);
        $this->assertNull($book->file_path);
        $this->assertFalse($book->hasPdfFile());
    }

    public function test_online_format_does_not_require_a_digital_file(): void
    {
        $admin = $this->admin();
        $author = Author::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.books.store'), [
                'title' => 'Online Book Without File',
                'price' => '1000',
                'author_ids' => [$author->id],
                'book_format' => Book::FORMAT_ONLINE,
                'status' => Book::STATUS_DRAFT,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('books', [
            'title' => 'Online Book Without File',
            'book_format' => Book::FORMAT_ONLINE,
        ]);
    }

    public function test_creating_a_book_without_a_format_defaults_to_pdf(): void
    {
        $admin = $this->admin();
        $author = Author::factory()->create();
        Storage::fake('local');

        $this->actingAs($admin)
            ->post(route('admin.books.store'), [
                'title' => 'Legacy Style Post',
                'price' => '1000',
                'author_ids' => [$author->id],
                'status' => Book::STATUS_DRAFT,
                'ebook_file' => UploadedFile::fake()->create('legacy.pdf', 120, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Book::FORMAT_PDF, Book::where('title', 'Legacy Style Post')->sole()->book_format);
    }

    public function test_switching_to_both_formats_stores_the_format_and_file(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();
        $author = Author::factory()->create();
        Storage::fake('local');

        $this->assertNull($book->file_path);

        $this->actingAs($admin)
            ->put(route('admin.books.update', $book), [
                'title' => $book->title,
                'price' => '2500',
                'author_ids' => [$author->id],
                'book_format' => Book::FORMAT_BOTH,
                'status' => Book::STATUS_DRAFT,
                'ebook_file' => UploadedFile::fake()->create('book.pdf', 120, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $fresh = $book->fresh();

        $this->assertSame(Book::FORMAT_BOTH, $fresh->book_format);
        $this->assertNotNull($fresh->file_path);
        $this->assertTrue($fresh->hasPdfFile());
        $this->assertTrue($fresh->isOnlineFormat());
        $this->assertTrue($fresh->isPdfFormat());
    }

    public function test_switching_back_to_online_does_not_require_a_file(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook();
        $author = Author::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.books.update', $book), [
                'title' => $book->title,
                'price' => '2500',
                'author_ids' => [$author->id],
                'book_format' => Book::FORMAT_ONLINE,
                'status' => Book::STATUS_DRAFT,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Book::FORMAT_ONLINE, $book->fresh()->book_format);
    }

    public function test_chapter_index_lists_chapters_in_order(): void
    {
        $admin = $this->admin();
        $book = $this->onlineBook(['title' => 'Ordered Book']);

        BookChapter::factory()->for($book)->create(['title' => 'First', 'position' => 1]);
        BookChapter::factory()->for($book)->create(['title' => 'Second', 'position' => 2]);

        $this->actingAs($admin)
            ->get(route('admin.books.chapters.index', $book))
            ->assertOk()
            ->assertSeeInOrder(['First', 'Second']);
    }
}
