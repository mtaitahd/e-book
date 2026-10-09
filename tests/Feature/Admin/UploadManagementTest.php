<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function authorId(): int
    {
        return Author::factory()->create()->id;
    }

    private function pdfFile(): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n" . str_repeat('0', 4096);

        return UploadedFile::fake()->createWithContent('sample-book.pdf', $content);
    }

    private function phpFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('evil.php', "<?php echo 'pwned'; ?>");
    }

    public function test_valid_cover_upload_works(): void
    {
        Storage::fake('public');

        $payload = [
            'title' => 'Covered Book',
            'price' => '999.99',
            'author_ids' => [$this->authorId()],
            'status' => 'draft',
            'cover_image' => UploadedFile::fake()->image('cover.png', 200, 260),
        ];

        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $payload)
            ->assertRedirect(route('admin.books.index'))
            // A successful upload is confirmed back, so nobody is left guessing
            // whether the cover was saved.
            ->assertSessionHas('success', 'Book created successfully. Cover image uploaded.');

        $book = Book::where('slug', 'covered-book')->first();
        $this->assertNotNull($book->cover_image);
        $this->assertStringStartsWith('covers/', $book->cover_image);
        Storage::disk('public')->assertExists($book->cover_image);
    }

    public function test_the_book_form_offers_live_cover_feedback(): void
    {
        // Selecting a cover used to be a silent no-op: the custom-file input only
        // echoed the name back, with no preview and no indication of whether the
        // file was even acceptable. The form now carries a target for the preview
        // script, and the script ships with the admin layout.
        $this->actingAs($this->admin())
            ->get(route('admin.books.create'))
            ->assertOk()
            ->assertSee('coverImageFeedback', false)
            ->assertSee('id="cover_image"', false);

        $this->assertFileExists(public_path('assets/admin/js/cover-preview.js'));

        $script = (string) file_get_contents(public_path('assets/admin/js/cover-preview.js'));

        // The client-side limits mirror the server rule in BookRequest, and the
        // change listener is delegated so it also fires for the XHR-loaded form.
        $this->assertStringContainsString('2048', $script);
        $this->assertStringContainsString("['image/jpeg', 'image/png', 'image/webp']", $script);
        $this->assertStringContainsString("input.id === 'cover_image'", $script);
        $this->assertStringContainsString("document.addEventListener('change'", $script);
    }

    public function test_invalid_cover_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), [
                'title' => 'Bad Cover Book',
                'price' => '100',
                'author_ids' => [$this->authorId()],
                'status' => 'draft',
                'cover_image' => UploadedFile::fake()->create('not-an-image.txt', 100),
            ])
            ->assertSessionHasErrors('cover_image');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_valid_ebook_upload_works(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), [
                'title' => 'Digital Only Book',
                'price' => '1999.00',
                'author_ids' => [$this->authorId()],
                'status' => 'published',
                'ebook_file' => $this->pdfFile(),
            ])
            ->assertRedirect(route('admin.books.index'));

        $book = Book::where('slug', 'digital-only-book')->first();
        $this->assertNotNull($book);
        $this->assertNotNull($book->file_path);
        $this->assertStringStartsWith('ebooks/', $book->file_path);
        $this->assertSame('pdf', $book->file_type);
        Storage::disk('local')->assertExists($book->file_path);
    }

    public function test_invalid_executable_upload_is_rejected(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), [
                'title' => 'Hacker Book',
                'price' => '100',
                'author_ids' => [$this->authorId()],
                'status' => 'draft',
                'ebook_file' => $this->phpFile(),
            ])
            ->assertSessionHasErrors('ebook_file');

        $this->assertDatabaseCount('books', 0);
    }

    public function test_ebook_file_is_not_publicly_exposed(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), [
                'title' => 'Private File Book',
                'price' => '500',
                'author_ids' => [$this->authorId()],
                'status' => 'published',
                'ebook_file' => $this->pdfFile(),
            ])
            ->assertRedirect(route('admin.books.index'));

        $book = Book::where('slug', 'private-file-book')->first();

        Storage::disk('local')->assertExists($book->file_path);
        Storage::disk('public')->assertMissing($book->file_path);

        $response = $this->get(route('books.show', $book));
        $response->assertOk();
        $response->assertDontSee($book->file_path);
        $response->assertDontSee('ebooks/sample-book');
    }
}