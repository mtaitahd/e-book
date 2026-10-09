<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookChapter;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Purchase\PurchaseTestCase;

/**
 * The two explicit book types, end to end.
 *
 * Creating each type, the conditional form each one is shown, the uploads the
 * server insists on (or refuses), where each save sends the admin afterwards,
 * and the safety rails around changing an existing book from one type to the
 * other - including the promise that switching never deletes chapters or PDFs.
 */
class BookTypeWorkflowTest extends PurchaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function author(): Author
    {
        return Author::factory()->create();
    }

    private function pdfUpload(): UploadedFile
    {
        return UploadedFile::fake()->create('workflow-book.pdf', 256, 'application/pdf');
    }

    /**
     * The common shape of a book save. Type and file are added per test.
     */
    private function payload(Author $author, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Type Workflow Specimen',
            'description' => 'Pins the two-type book workflow.',
            'author_ids' => [$author->id],
            'category_ids' => [],
            'status' => 'draft',
            'pricing_type' => 'free',
        ], $overrides);
    }

    private function storedBook(string $title = 'Type Workflow Specimen'): Book
    {
        return Book::where('title', $title)->firstOrFail();
    }

    /* ------------------------------------------------------------------ *
     * The form
     * ------------------------------------------------------------------ */

    public function test_the_create_form_offers_both_book_types(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.books.create'));

        $response->assertOk();
        $response->assertSee('Book Type', false);
        $response->assertSee('name="type"', false);
        $response->assertSee('value="ebook"', false);
        $response->assertSee('value="pdf"', false);
        $response->assertSee('data-book-type-choice', false);

        // Both conditional blocks exist on creation; the PDF upload is the
        // one that starts required, because a PDF book needs its file.
        $response->assertSee('data-book-type-block="ebook"', false);
        $response->assertSee('data-book-type-block="pdf"', false);
        $response->assertSee('data-book-pdf-create="1"', false);
        $response->assertSee('data-book-pdf-existing="0"', false);
    }

    public function test_the_edit_form_shows_only_the_controls_matching_the_books_type(): void
    {
        $admin = $this->admin();

        $ebook = Book::factory()->online()->create([
            'title' => 'Chapter Built Book',
            'file_path' => null,
            'file_type' => null,
        ]);
        BookChapter::factory()->create(['book_id' => $ebook->id, 'position' => 1]);

        $pdf = Book::factory()->create([
            'title' => 'Uploaded PDF Book',
            'book_format' => Book::FORMAT_PDF,
            'file_path' => 'ebooks/existing.pdf',
            'file_type' => 'pdf',
        ]);

        // The chapter book keeps its chapter management and the confirmation
        // box that names the type it would be leaving behind.
        $this->actingAs($admin)->get(route('admin.books.edit', $ebook))
            ->assertOk()
            ->assertSee('data-book-type-original="ebook"', false)
            ->assertSee('Manage Chapters', false);

        // The PDF book shows its stored file and never offers chapters.
        $this->actingAs($admin)->get(route('admin.books.edit', $pdf))
            ->assertOk()
            ->assertSee('data-book-type-original="pdf"', false)
            ->assertSee('data-book-pdf-existing="1"', false)
            ->assertSee('Current PDF:', false)
            ->assertDontSee('Manage Chapters', false);
    }

    /* ------------------------------------------------------------------ *
     * Creating each type
     * ------------------------------------------------------------------ */

    public function test_creating_an_ebook_sends_the_admin_to_chapter_management(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->payload($this->author(), [
                'type' => Book::TYPE_EBOOK,
            ]))
            ->assertRedirect(route('admin.books.chapters.index', $this->storedBook()))
            ->assertSessionHas(
                'success',
                'Book created successfully. Add its first chapter to start building the e-book.'
            );

        $this->assertDatabaseHas('books', [
            'title' => 'Type Workflow Specimen',
            'type' => Book::TYPE_EBOOK,
            'book_format' => Book::FORMAT_ONLINE,
            'file_path' => null,
            'file_type' => null,
        ]);
    }

    public function test_creating_a_pdf_book_sends_the_admin_to_the_file_controls(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->payload($this->author(), [
                'type' => Book::TYPE_PDF,
                'ebook_file' => $this->pdfUpload(),
            ]))
            ->assertRedirect(route('admin.books.edit', $this->storedBook()))
            ->assertSessionHas(
                'success',
                'Book created successfully. Its PDF is ready for secure reading and download.'
            );

        $book = $this->storedBook();
        $this->assertSame(Book::TYPE_PDF, $book->type());
        $this->assertSame(Book::FORMAT_PDF, $book->book_format);
        $this->assertSame('pdf', $book->file_type);
        $this->assertNotNull($book->file_path);
        Storage::disk('local')->assertExists($book->file_path);
    }

    public function test_saving_from_the_modal_returns_the_same_redirect(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.books.store'), $this->payload($this->author(), [
                'title' => 'Modal Specimen Book',
                'type' => Book::TYPE_EBOOK,
            ]));

        $response->assertOk()->assertJsonStructure(['message', 'redirect']);

        $book = Book::where('title', 'Modal Specimen Book')->firstOrFail();

        $response->assertJson([
            'message' => 'Book created successfully. Add its first chapter to start building the e-book.',
            'redirect' => route('admin.books.chapters.index', $book),
        ]);
    }

    /* ------------------------------------------------------------------ *
     * The uploads each type insists on
     * ------------------------------------------------------------------ */

    public function test_creating_an_ebook_refuses_an_uploaded_file(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->payload($this->author(), [
                'type' => Book::TYPE_EBOOK,
                'ebook_file' => $this->pdfUpload(),
            ]))
            ->assertSessionHasErrors(['ebook_file']);

        $errors = session('errors')->first('ebook_file');
        $this->assertStringContainsString('does not use an uploaded file', $errors);

        $this->assertDatabaseCount('books', 0);
    }

    public function test_creating_a_pdf_book_requires_its_file(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->payload($this->author(), [
                'type' => Book::TYPE_PDF,
            ]))
            ->assertSessionHasErrors(['ebook_file']);

        $this->assertDatabaseCount('books', 0);
    }

    public function test_an_ebook_cannot_be_published_before_its_first_chapter(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->payload($this->author(), [
                'type' => Book::TYPE_EBOOK,
                'status' => Book::STATUS_PUBLISHED,
            ]))
            ->assertSessionHas('error', 'Add at least one chapter before publishing an online book.');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_a_request_without_a_type_keeps_the_legacy_behaviour(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->payload($this->author(), [
                'title' => 'Legacy Caller Book',
            ]))
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book created successfully.');

        // No type was named, so the model classifies it from its format.
        $this->assertDatabaseHas('books', [
            'title' => 'Legacy Caller Book',
            'type' => Book::TYPE_PDF,
            'book_format' => Book::FORMAT_PDF,
        ]);
    }

    /* ------------------------------------------------------------------ *
     * Editing within one's own type
     * ------------------------------------------------------------------ */

    public function test_editing_a_pdf_book_without_a_new_file_keeps_the_stored_one(): void
    {
        $book = Book::factory()->create([
            'title' => 'Keeper Of Its File',
            'book_format' => Book::FORMAT_PDF,
            'file_path' => 'ebooks/keeper.pdf',
            'file_type' => 'pdf',
        ]);
        Storage::disk('local')->put('ebooks/keeper.pdf', "%PDF-1.4\nkept\n%%EOF\n");

        $this->actingAs($this->admin())
            ->put(route('admin.books.update', $book), $this->payload($this->author(), [
                'title' => 'Keeper Of Its File',
                'type' => Book::TYPE_PDF,
            ]))
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book updated successfully.');

        $book->refresh();
        $this->assertSame('ebooks/keeper.pdf', $book->file_path);
        $this->assertSame(Book::TYPE_PDF, $book->type());
        $this->assertSame(Book::FORMAT_PDF, $book->book_format);
        Storage::disk('local')->assertExists('ebooks/keeper.pdf');
    }

    /* ------------------------------------------------------------------ *
     * Changing the type
     * ------------------------------------------------------------------ */

    public function test_switching_type_requires_the_confirmation_checkbox(): void
    {
        $book = Book::factory()->online()->create([
            'title' => 'Unconfirmed Switch',
            'file_path' => null,
            'file_type' => null,
        ]);
        BookChapter::factory()->create(['book_id' => $book->id, 'position' => 1]);

        $this->actingAs($this->admin())
            ->put(route('admin.books.update', $book), $this->payload($this->author(), [
                'title' => 'Unconfirmed Switch',
                'type' => Book::TYPE_PDF,
                'ebook_file' => $this->pdfUpload(),
            ]))
            ->assertSessionHasErrors(['confirm_type_change']);

        $book->refresh();
        $this->assertSame(Book::TYPE_EBOOK, $book->type());
        $this->assertSame(Book::FORMAT_ONLINE, $book->book_format);
    }

    public function test_switching_an_ebook_to_pdf_without_a_file_is_refused(): void
    {
        $book = Book::factory()->online()->create([
            'title' => 'Fileless Switch',
            'file_path' => null,
            'file_type' => null,
        ]);
        BookChapter::factory()->create(['book_id' => $book->id, 'position' => 1]);

        $this->actingAs($this->admin())
            ->put(route('admin.books.update', $book), $this->payload($this->author(), [
                'title' => 'Fileless Switch',
                'type' => Book::TYPE_PDF,
                'confirm_type_change' => 1,
            ]))
            ->assertSessionHasErrors(['ebook_file']);

        $book->refresh();
        $this->assertSame(Book::TYPE_EBOOK, $book->type());
        $this->assertSame(1, $book->chapters()->count());
    }

    public function test_switching_an_ebook_to_pdf_keeps_its_chapters(): void
    {
        $book = Book::factory()->online()->create([
            'title' => 'Keeps Its Chapters',
            'file_path' => null,
            'file_type' => null,
        ]);
        $chapter = BookChapter::factory()->create(['book_id' => $book->id, 'position' => 1]);

        $this->actingAs($this->admin())
            ->put(route('admin.books.update', $book), $this->payload($this->author(), [
                'title' => 'Keeps Its Chapters',
                'type' => Book::TYPE_PDF,
                'confirm_type_change' => 1,
                'ebook_file' => $this->pdfUpload(),
            ]))
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book updated successfully.');

        $book->refresh();
        $this->assertSame(Book::TYPE_PDF, $book->type());
        $this->assertSame(Book::FORMAT_PDF, $book->book_format);
        $this->assertSame('pdf', $book->file_type);
        $this->assertNotNull($book->file_path);

        // The promise: nothing written for the old type is ever deleted.
        $this->assertDatabaseHas('book_chapters', ['id' => $chapter->id, 'book_id' => $book->id]);
        $this->assertSame(1, $book->chapters()->count());
    }

    public function test_switching_a_pdf_to_ebook_keeps_its_uploaded_file(): void
    {
        $book = Book::factory()->create([
            'title' => 'Keeps Its PDF',
            'book_format' => Book::FORMAT_PDF,
            'file_path' => 'ebooks/kept-after-switch.pdf',
            'file_type' => 'pdf',
        ]);
        Storage::disk('local')->put('ebooks/kept-after-switch.pdf', "%PDF-1.4\nkept\n%%EOF\n");

        $this->actingAs($this->admin())
            ->put(route('admin.books.update', $book), $this->payload($this->author(), [
                'title' => 'Keeps Its PDF',
                'type' => Book::TYPE_EBOOK,
                'confirm_type_change' => 1,
            ]))
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book updated successfully.');

        $book->refresh();
        $this->assertSame(Book::TYPE_EBOOK, $book->type());
        $this->assertSame(Book::FORMAT_ONLINE, $book->book_format);
        $this->assertSame('ebooks/kept-after-switch.pdf', $book->file_path);
        Storage::disk('local')->assertExists('ebooks/kept-after-switch.pdf');
    }

    public function test_a_book_people_own_can_never_change_type(): void
    {
        $owner = User::factory()->create();
        $book = Book::factory()->online()->create([
            'title' => 'Owned Chapter Book',
            'file_path' => null,
            'file_type' => null,
        ]);
        BookChapter::factory()->create(['book_id' => $book->id, 'position' => 1]);

        $order = $this->paidOrder($owner, [['book' => $book, 'unit_price' => '1000.00']]);
        $this->service()->createFromPaidOrder($order);
        $this->assertSame(
            1,
            Purchase::where('user_id', $owner->id)->where('book_id', $book->id)->count()
        );

        // Even with the confirmation ticked and a PDF in hand, ownership wins.
        $this->actingAs($this->admin())
            ->put(route('admin.books.update', $book), $this->payload($this->author(), [
                'title' => 'Owned Chapter Book',
                'type' => Book::TYPE_PDF,
                'confirm_type_change' => 1,
                'ebook_file' => $this->pdfUpload(),
            ]))
            ->assertSessionHasErrors(['type']);

        $errors = session('errors')->first('type');
        $this->assertStringContainsString('owned by 1 customer', $errors);

        $book->refresh();
        $this->assertSame(Book::TYPE_EBOOK, $book->type());
        $this->assertSame(Book::FORMAT_ONLINE, $book->book_format);
        $this->assertSame(1, $book->chapters()->count());
    }

    /* ------------------------------------------------------------------ *
     * Legacy dual-format books
     * ------------------------------------------------------------------ */

    public function test_editing_a_legacy_dual_format_book_never_demotes_its_readers(): void
    {
        $book = Book::factory()->both()->create([
            'title' => 'Legacy Both Format Book',
            'file_path' => 'ebooks/legacy-both.pdf',
            'file_type' => 'pdf',
        ]);
        BookChapter::factory()->create(['book_id' => $book->id, 'position' => 1]);

        // A legacy dual-format book is classified when it is saved, so its
        // stored type reads PDF while the format still offers both readers.
        // The form must keep its chapter editor either way (format allows it)
        // and must never demote the format on a plain metadata edit.
        $this->actingAs($this->admin())->get(route('admin.books.edit', $book))
            ->assertOk()
            ->assertSee('data-book-type-original="pdf"', false)
            ->assertSee('Manage Chapters', false);

        $this->actingAs($this->admin())
            ->put(route('admin.books.update', $book), $this->payload($this->author(), [
                'title' => 'Legacy Both Format Book',
                'type' => Book::TYPE_PDF,
            ]))
            ->assertRedirect(route('admin.books.index'))
            ->assertSessionHas('success', 'Book updated successfully.');

        $book->refresh();
        $this->assertSame(Book::FORMAT_BOTH, $book->book_format);
        $this->assertSame(Book::TYPE_EBOOK, $book->type());
        $this->assertSame('ebooks/legacy-both.pdf', $book->file_path);
        $this->assertSame('pdf', $book->file_type);
        $this->assertSame(1, $book->chapters()->count());
    }

    /* ------------------------------------------------------------------ *
     * Storefront
     * ------------------------------------------------------------------ */

    public function test_the_storefront_labels_each_book_with_its_type(): void
    {
        $ebook = Book::factory()->published()->online()->create([
            'title' => 'Storefront Chapter Book',
            'file_path' => null,
            'file_type' => null,
        ]);
        BookChapter::factory()->create(['book_id' => $ebook->id, 'position' => 1]);

        $pdf = Book::factory()->published()->create([
            'title' => 'Storefront PDF Book',
            'book_format' => Book::FORMAT_PDF,
            'file_path' => 'ebooks/storefront.pdf',
            'file_type' => 'pdf',
        ]);

        $this->get(route('books.show', $ebook))
            ->assertOk()
            ->assertSee('<span class="pill">E-Book</span>', false);

        $this->get(route('books.show', $pdf))
            ->assertOk()
            ->assertSee('<span class="pill">PDF</span>', false);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('<span class="pill">E-Book</span>', false)
            ->assertSee('<span class="pill">PDF</span>', false);
    }
}
