<?php

namespace Tests\Feature\Book;

use App\Models\Book;
use App\Models\BookChapter;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Purchase\PurchaseTestCase;

/**
 * How the three formats are presented on the storefront and in the customer's
 * library. The read route dispatches by format, so the pages only have to
 * decide which label and which link to show.
 */
class BookFormatPresentationTest extends PurchaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function user(): User
    {
        return User::factory()->customer()->create();
    }

    private function pdfBook(array $attributes = []): Book
    {
        $book = Book::factory()->published()->create(array_merge([
            'book_format' => Book::FORMAT_PDF,
            'file_path' => 'ebooks/presented.pdf',
            'file_type' => 'pdf',
        ], $attributes));

        Storage::disk('local')->put($book->file_path, "%PDF-1.4\ncontent\n%%EOF\n");

        return $book;
    }

    private function onlineBook(array $attributes = []): Book
    {
        $book = Book::factory()->published()->create(array_merge([
            'book_format' => Book::FORMAT_ONLINE,
            'file_path' => null,
            'file_type' => null,
        ], $attributes));

        BookChapter::factory()->create(['book_id' => $book->id]);

        return $book;
    }

    private function dualBook(array $attributes = []): Book
    {
        $book = Book::factory()->published()->create(array_merge([
            'book_format' => Book::FORMAT_BOTH,
            'file_path' => 'ebooks/dual.pdf',
            'file_type' => 'pdf',
        ], $attributes));

        Storage::disk('local')->put($book->file_path, "%PDF-1.4\ncontent\n%%EOF\n");
        BookChapter::factory()->create(['book_id' => $book->id]);

        return $book;
    }

    private function purchase(User $user, Book $book): Purchase
    {
        $order = $this->paidOrder($user, [['book' => $book]]);

        $this->service()->createFromPaidOrder($order);

        return Purchase::where('user_id', $user->id)->where('book_id', $book->id)->firstOrFail();
    }

    /* ------------------------------------------------------------------ *
     * Storefront book page
     * ------------------------------------------------------------------ */

    public function test_storefront_shows_the_reading_format(): void
    {
        $this->get(route('books.show', $this->onlineBook()))->assertOk()->assertSee('Online');
        $this->get(route('books.show', $this->pdfBook()))->assertOk()->assertSee('PDF');
        $this->get(route('books.show', $this->dualBook()))->assertOk()->assertSee('PDF &amp; Online', false);
    }

    public function test_storefront_offers_a_free_sample_when_a_free_chapter_exists(): void
    {
        $book = $this->onlineBook();
        $free = BookChapter::factory()->create(['book_id' => $book->id, 'is_free' => true]);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee(route('books.preview', [$book, $free]), false);
    }

    public function test_storefront_hides_the_sample_link_without_a_free_chapter(): void
    {
        $book = $this->onlineBook();
        BookChapter::factory()->create(['book_id' => $book->id, 'is_free' => false]);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertDontSee('Read a free sample');
    }

    public function test_owner_sees_read_online_for_an_online_book(): void
    {
        $user = $this->user();
        $book = $this->onlineBook();
        $purchase = $this->purchase($user, $book);

        $this->actingAs($user)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('Read online')
            ->assertSee(route('account.purchases.read', $purchase), false)
            ->assertDontSee('Add to cart');
    }

    public function test_owner_of_a_dual_book_gets_both_the_reader_and_the_download(): void
    {
        $user = $this->user();
        $book = $this->dualBook();
        $purchase = $this->purchase($user, $book);

        $this->actingAs($user)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertSee(route('account.purchases.read', $purchase), false)
            ->assertSee(route('account.purchases.download', $purchase), false);
    }

    public function test_owner_of_a_pdf_book_still_sees_read_now_and_download(): void
    {
        $user = $this->user();
        $book = $this->pdfBook();
        $purchase = $this->purchase($user, $book);

        $this->actingAs($user)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('Read now')
            ->assertSee(route('account.purchases.download', $purchase), false);
    }

    /* ------------------------------------------------------------------ *
     * Library
     * ------------------------------------------------------------------ */

    public function test_library_offers_online_reading(): void
    {
        $user = $this->user();
        $purchase = $this->purchase($user, $this->onlineBook());

        $this->actingAs($user)
            ->get(route('account.purchases.index'))
            ->assertOk()
            ->assertSee('Read online')
            ->assertSee(route('account.purchases.read', $purchase), false);
    }

    public function test_purchase_page_reports_reading_progress(): void
    {
        $user = $this->user();
        $book = $this->onlineBook();
        $chapter = $book->chapters()->first();
        $purchase = $this->purchase($user, $book);

        // Untouched book: no progress note at all.
        $this->actingAs($user)
            ->get(route('account.purchases.show', $purchase))
            ->assertOk()
            ->assertDontSee('through this book');

        $purchase->readingProgress()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'chapter_id' => $chapter->id,
            'current_page' => 1,
            'progress_percent' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('account.purchases.show', $purchase))
            ->assertOk()
            ->assertSee('You have started reading this book');

        $purchase->readingProgress()->update([
            'current_page' => 3,
            'progress_percent' => 40.0,
        ]);

        $this->actingAs($user)
            ->get(route('account.purchases.show', $purchase))
            ->assertOk()
            ->assertSee('You are 40% through this book');
    }

    public function test_a_book_with_no_readable_content_says_so_instead_of_offering_a_dead_link(): void
    {
        $user = $this->user();
        $book = Book::factory()->published()->create([
            'book_format' => Book::FORMAT_ONLINE,
            'file_path' => null,
            'file_type' => null,
        ]);
        $purchase = $this->purchase($user, $book);

        $this->actingAs($user)
            ->get(route('account.purchases.show', $purchase))
            ->assertOk()
            ->assertSee('This book is not available for reading yet');
    }
}
