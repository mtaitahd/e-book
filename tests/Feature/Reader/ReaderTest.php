<?php

namespace Tests\Feature\Reader;

use App\Models\Book;
use App\Models\DownloadLog;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Purchase\PurchaseTestCase;

class ReaderTest extends PurchaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function storeFile(Book $book, string $path = 'ebooks/readable.pdf', string $content = "%PDF-1.4\nreader content\n%%EOF\n"): void
    {
        $book->update(['file_path' => $path]);
        Storage::disk('local')->put($path, $content);
    }

    private function pdfPurchase(User $user, array $bookAttributes = []): Purchase
    {
        $book = $this->publishedBook(array_merge([
            'title' => 'Readable Book',
            'file_type' => 'pdf',
        ], $bookAttributes));
        $this->storeFile($book);

        return $this->paidOrder($user, [['book' => $book]])->purchases()->firstOrFail();
    }

    private function intruder(): User
    {
        return $this->customer([
            'email' => 'sneaky@example.com',
            'first_name' => 'Sneaky',
            'last_name' => 'User',
        ]);
    }

    public function test_guest_cannot_open_reader(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->from(route('cart.show'))->get(route('account.purchases.read', $purchase))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('auth_intended', route('account.purchases.read', $purchase));
    }

    public function test_guest_cannot_fetch_reader_file(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->from(route('cart.show'))->get(route('account.purchases.reader-file', $purchase))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('auth_intended', route('account.purchases.reader-file', $purchase));
    }

    public function test_customer_without_purchase_cannot_open_reader(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($this->intruder())
            ->get(route('account.purchases.read', $purchase))
            ->assertForbidden();
    }

    public function test_customer_without_purchase_cannot_fetch_reader_file(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($this->intruder())
            ->get(route('account.purchases.reader-file', $purchase))
            ->assertForbidden();
    }

    public function test_customer_with_unpaid_order_cannot_open_reader(): void
    {
        $owner = $this->customer();
        $order = $this->paidOrder($owner, [['book' => $this->publishedBook(['title' => 'Pending Read'])]]);
        $purchase = $order->purchases()->firstOrFail();
        $this->storeFile($purchase->book);

        $order->update(['status' => Order::STATUS_PENDING]);

        $this->actingAs($owner)
            ->get(route('account.purchases.read', $purchase->fresh()))
            ->assertForbidden();
    }

    public function test_customer_with_unpaid_order_cannot_fetch_reader_file(): void
    {
        $owner = $this->customer();
        $order = $this->paidOrder($owner, [['book' => $this->publishedBook(['title' => 'Pending File'])]]);
        $purchase = $order->purchases()->firstOrFail();
        $this->storeFile($purchase->book);

        $order->update(['status' => Order::STATUS_PENDING]);

        $this->actingAs($owner)
            ->get(route('account.purchases.reader-file', $purchase->fresh()))
            ->assertForbidden();
    }

    public function test_owner_can_open_reader_page(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($purchase->user)
            ->get(route('account.purchases.read', $purchase))
            ->assertOk()
            ->assertSee('Readable Book')
            ->assertSee(route('account.purchases.reader-file', $purchase))
            ->assertSee('pdf.min.js')
            ->assertSee('data-initial-page="1"', false);
    }

    public function test_owner_reader_file_has_pdf_content_type(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($purchase->user)
            ->get(route('account.purchases.reader-file', $purchase))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // No Content-Disposition on the reader stream: Chrome treats a
        // download-style disposition on a large fetch() body as empty.
        $this->actingAs($purchase->user)
            ->get(route('account.purchases.reader-file', $purchase))
            ->assertHeaderMissing('Content-Disposition');
    }

    public function test_reader_file_supports_byte_ranges(): void
    {
        $purchase = $this->pdfPurchase($this->customer());
        $path = $purchase->book->file_path;
        $size = Storage::disk('local')->size($path);

        $response = $this->actingAs($purchase->user)
            ->withHeader('Range', 'bytes=0-5')
            ->get(route('account.purchases.reader-file', $purchase))
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-5/'.$size);

        // SecureReaderFile returns a plain (non-streamed) response on purpose:
        // streaming empty bodies through XAMPP was unreliable.
        $this->assertSame(6, strlen((string) $response->getContent()));
    }

    public function test_reader_file_out_of_bounds_range_returns_416(): void
    {
        $purchase = $this->pdfPurchase($this->customer());
        $size = Storage::disk('local')->size($purchase->book->file_path);

        $this->actingAs($purchase->user)
            ->withHeader('Range', 'bytes=999999999-')
            ->get(route('account.purchases.reader-file', $purchase))
            ->assertStatus(416)
            ->assertHeader('Content-Range', 'bytes */'.$size);
    }

    public function test_reader_file_never_exposes_private_filesystem_path(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $response = $this->actingAs($purchase->user)
            ->get(route('account.purchases.reader-file', $purchase))
            ->assertOk();

        $response->assertDontSee('storage/app/private');
        $response->assertDontSee('ebooks/readable.pdf');
        $response->assertDontSee('C:\\xampp');
        $response->assertHeaderMissing('Content-Disposition');
    }

    public function test_foreign_user_cannot_open_reader(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($this->intruder())
            ->get(route('account.purchases.read', $purchase))
            ->assertForbidden();
    }

    public function test_foreign_user_cannot_retrieve_reader_file(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($this->intruder())
            ->get(route('account.purchases.reader-file', $purchase))
            ->assertForbidden();
    }

    public function test_missing_file_redirects_with_friendly_error_and_no_path_leak(): void
    {
        $owner = $this->customer();
        $book = $this->publishedBook(['title' => 'Ghost Reader', 'file_path' => 'ebooks/ghost-reader.pdf']);
        $purchase = $this->paidOrder($owner, [['book' => $book]])->purchases()->firstOrFail();

        $response = $this->actingAs($owner)
            ->get(route('account.purchases.read', $purchase))
            ->assertRedirect(route('account.purchases.show', $purchase));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('unavailable', session('error'));
        $this->assertStringNotContainsString('ghost-reader.pdf', session('error'));
    }

    public function test_missing_file_reader_file_returns_404(): void
    {
        $owner = $this->customer();
        $book = $this->publishedBook(['title' => 'Ghost File', 'file_path' => 'ebooks/ghost-file.pdf']);
        $purchase = $this->paidOrder($owner, [['book' => $book]])->purchases()->firstOrFail();

        $this->actingAs($owner)
            ->get(route('account.purchases.reader-file', $purchase))
            ->assertNotFound();
    }

    public function test_epub_read_redirects_with_pdf_message_but_download_still_works(): void
    {
        $owner = $this->customer();
        $book = $this->publishedBook(['title' => 'Epub Nomad', 'file_type' => 'epub']);
        $this->storeFile($book, 'ebooks/nomad.epub', 'some epub bytes');
        $purchase = $this->paidOrder($owner, [['book' => $book]])->purchases()->firstOrFail();

        $this->actingAs($owner)
            ->get(route('account.purchases.read', $purchase))
            ->assertRedirect(route('account.purchases.show', $purchase));

        $this->assertStringContainsString('PDF', session('error'));

        $this->actingAs($owner)
            ->get(route('account.purchases.reader-file', $purchase))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('account.purchases.download', $purchase))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/epub+zip');
    }

    public function test_existing_pdf_download_still_works_for_owner(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($purchase->user)
            ->get(route('account.purchases.download', $purchase))
            ->assertOk()
            ->assertDownload('Readable-Book.pdf');

        $this->assertSame(1, DownloadLog::count());
    }

    public function test_existing_download_authorization_remains_enforced(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($this->intruder())
            ->get(route('account.purchases.download', $purchase))
            ->assertForbidden();

        $this->assertSame(0, DownloadLog::count());
    }

    public function test_progress_is_saved_for_the_owner(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($purchase->user)
            ->postJson(route('account.purchases.progress', $purchase), ['current_page' => 7])
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertDatabaseHas('reading_progress', [
            'user_id' => $purchase->user_id,
            'purchase_id' => $purchase->id,
            'book_id' => $purchase->book_id,
            'current_page' => 7,
        ]);
    }

    public function test_progress_keeps_a_single_record_per_purchase(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($purchase->user)->postJson(route('account.purchases.progress', $purchase), ['current_page' => 3])->assertOk();
        $this->actingAs($purchase->user)->postJson(route('account.purchases.progress', $purchase), ['current_page' => 9])->assertOk();

        $this->assertSame(1, ReadingProgress::count());
        $this->assertDatabaseHas('reading_progress', ['purchase_id' => $purchase->id, 'current_page' => 9]);
    }

    public function test_foreign_user_cannot_save_progress(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($this->intruder())
            ->postJson(route('account.purchases.progress', $purchase), ['current_page' => 4])
            ->assertForbidden();

        $this->assertSame(0, ReadingProgress::count());
    }

    public function test_progress_rejects_invalid_pages(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        foreach ([0, -2, 'abc', 9999999999] as $invalid) {
            $this->actingAs($purchase->user)
                ->postJson(route('account.purchases.progress', $purchase), ['current_page' => $invalid])
                ->assertStatus(422);
        }

        $this->assertSame(0, ReadingProgress::count());
    }

    public function test_last_page_is_restored_when_reader_opens(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($purchase->user)
            ->postJson(route('account.purchases.progress', $purchase), ['current_page' => 12])
            ->assertOk();

        $this->actingAs($purchase->user)
            ->get(route('account.purchases.read', $purchase))
            ->assertOk()
            ->assertSee('data-initial-page="12"', false);
    }

    public function test_unpaid_order_progress_is_forbidden(): void
    {
        $owner = $this->customer();
        $order = $this->paidOrder($owner, [['book' => $this->publishedBook(['title' => 'Progress Guard'])]]);
        $purchase = $order->purchases()->firstOrFail();
        $this->storeFile($purchase->book);

        $order->update(['status' => Order::STATUS_PENDING]);

        $this->actingAs($owner)
            ->postJson(route('account.purchases.progress', $purchase->fresh()), ['current_page' => 5])
            ->assertForbidden();

        $this->assertSame(0, ReadingProgress::count());
    }

    public function test_index_still_shows_read_now_and_download(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($purchase->user)
            ->get(route('account.purchases.index'))
            ->assertOk()
            ->assertSee('Readable Book')
            ->assertSee('Read Now')
            ->assertSee('Download');
    }
}