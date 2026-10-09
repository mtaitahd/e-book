<?php

namespace Tests\Feature\Reader;

use App\Models\Book;
use App\Models\BookChapter;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\ReadingBookmark;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Purchase\PurchaseTestCase;

/**
 * The native HTML reader's trust model.
 *
 * Every endpoint is reachable only through a purchase, and the purchase decides
 * everything: an authenticated owner, a genuinely paid order, and a chapter
 * that belongs to that purchase's book. Nothing the client sends can widen that.
 */
class OnlineReadingTest extends PurchaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function onlineBook(array $attributes = []): Book
    {
        return $this->publishedBook(array_merge([
            'book_format' => Book::FORMAT_ONLINE,
            'file_path' => null,
            'file_type' => null,
        ], $attributes));
    }

    /**
     * A PDF book that genuinely has a readable file on the (faked) disk, so the
     * PDF.js path renders instead of redirecting to the purchase page.
     */
    private function pdfBook(array $attributes = []): Book
    {
        $book = $this->publishedBook(array_merge([
            'book_format' => Book::FORMAT_PDF,
            'file_path' => 'ebooks/reader.pdf',
            'file_type' => 'pdf',
        ], $attributes));

        Storage::disk('local')->put($book->file_path, "%PDF-1.4\nreader content\n%%EOF\n");

        return $book;
    }

    private function chapter(Book $book, array $attributes = []): BookChapter
    {
        return BookChapter::factory()->create(array_merge(['book_id' => $book->id], $attributes));
    }

    /**
     * A paid, reconciled purchase of the given book for the given user.
     */
    private function purchaseFor(User $user, Book $book): Purchase
    {
        $order = $this->paidOrder($user, [['book' => $book, 'unit_price' => '1000.00']]);
        $this->service()->createFromPaidOrder($order);

        return Purchase::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->firstOrFail();
    }

    /* ------------------------------------------------------------------ *
     * Reader page
     * ------------------------------------------------------------------ */

    public function test_owner_can_open_the_native_reader(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $this->chapter($book, ['title' => 'The Opening', 'position' => 1]);
        $purchase = $this->purchaseFor($user, $book);

        $response = $this->actingAs($user)
            ->get(route('account.purchases.read', $purchase));

        $response->assertOk();
        $response->assertViewIs('account.purchases.online-reader');
        $response->assertSee('The Opening');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        $this->from(route('cart.show'))->get(route('account.purchases.read', $purchase))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('auth_intended', route('account.purchases.read', $purchase));
    }

    public function test_another_customer_cannot_open_the_reader(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $book = $this->onlineBook();
        $this->chapter($book);
        $purchase = $this->purchaseFor($owner, $book);

        $this->actingAs($intruder)
            ->get(route('account.purchases.read', $purchase))
            ->assertForbidden();
    }

    public function test_unpaid_order_never_reaches_the_reader(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $this->chapter($book);
        $order = $this->makeOrder($user);
        $this->addItem($order, $book);

        $purchase = Purchase::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'order_item_id' => $order->items()->first()->id,
            'book_id' => $book->id,
            'amount' => '1000.00',
            'currency' => 'TZS',
            'purchased_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('account.purchases.read', $purchase))
            ->assertForbidden();
    }

    public function test_a_book_without_chapters_falls_back_to_the_purchase_page(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $purchase = $this->purchaseFor($user, $book);

        $this->actingAs($user)
            ->get(route('account.purchases.read', $purchase))
            ->assertRedirect(route('account.purchases.show', $purchase))
            ->assertSessionHas('error');
    }

    public function test_pdf_only_books_still_use_the_pdf_reader(): void
    {
        $user = User::factory()->create();
        $book = $this->pdfBook();
        $purchase = $this->purchaseFor($user, $book);

        $this->actingAs($user)
            ->get(route('account.purchases.read', $purchase))
            ->assertOk()
            ->assertViewIs('account.purchases.reader');
    }

    /* ------------------------------------------------------------------ *
     * Manifest
     * ------------------------------------------------------------------ */

    public function test_manifest_lists_chapters_and_resume_point(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook(['title' => 'Dune']);
        $first = $this->chapter($book, ['title' => 'Chapter One', 'position' => 1]);
        $second = $this->chapter($book, ['title' => 'Chapter Two', 'position' => 2, 'is_free' => true]);
        $purchase = $this->purchaseFor($user, $book);

        ReadingProgress::create([
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'book_id' => $book->id,
            'chapter_id' => $second->id,
            'current_page' => 7,
            'progress_percent' => 12.5,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('account.purchases.online.manifest', $purchase));

        $response->assertOk()
            ->assertJsonPath('book.title', 'Dune')
            ->assertJsonPath('book.format', Book::FORMAT_ONLINE)
            ->assertJsonPath('book.has_pdf', false)
            ->assertJsonCount(2, 'chapters')
            ->assertJsonPath('chapters.0.id', $first->id)
            ->assertJsonPath('chapters.1.is_free', true)
            ->assertJsonPath('progress.chapter_id', $second->id)
            ->assertJsonPath('progress.page', 7);
    }

    public function test_manifest_is_not_reachable_by_another_customer(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $book = $this->onlineBook();
        $this->chapter($book);
        $purchase = $this->purchaseFor($owner, $book);

        $this->actingAs($intruder)
            ->getJson(route('account.purchases.online.manifest', $purchase))
            ->assertForbidden();
    }

    public function test_guest_cannot_fetch_the_manifest(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        $this->getJson(route('account.purchases.online.manifest', $purchase))
            ->assertUnauthorized();
    }

    /* ------------------------------------------------------------------ *
     * Chapter content
     * ------------------------------------------------------------------ */

    public function test_owner_can_fetch_chapter_html(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book, [
            'title' => 'Arrakis',
            'content' => '<h2>Arrakis</h2><p>The desert planet.</p>',
        ]);
        $purchase = $this->purchaseFor($user, $book);

        $this->actingAs($user)
            ->getJson(route('account.purchases.online.chapter', [$purchase, $chapter->id]))
            ->assertOk()
            ->assertJsonPath('id', $chapter->id)
            ->assertJsonPath('title', 'Arrakis')
            ->assertJsonPath('html', '<h2>Arrakis</h2><p>The desert planet.</p>');
    }

    public function test_a_chapter_from_another_book_is_not_readable_through_this_purchase(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $this->chapter($book);

        $otherBook = $this->onlineBook();
        $foreignChapter = $this->chapter($otherBook, ['content' => '<p>Secret of another title.</p>']);

        $purchase = $this->purchaseFor($user, $book);

        $this->actingAs($user)
            ->getJson(route('account.purchases.online.chapter', [$purchase, $foreignChapter->id]))
            ->assertNotFound()
            ->assertJsonMissing(['html' => '<p>Secret of another title.</p>']);
    }

    public function test_another_customer_cannot_fetch_chapter_html(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book, ['content' => '<p>Paid text.</p>']);
        $purchase = $this->purchaseFor($owner, $book);

        $this->actingAs($intruder)
            ->getJson(route('account.purchases.online.chapter', [$purchase, $chapter->id]))
            ->assertForbidden();
    }

    public function test_stored_markup_is_sanitised_again_on_read(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        // Bypasses the admin request on purpose, e.g. a seeder or a console.
        $chapter->forceFill([
            'content' => '<p>Safe</p><script>alert(document.cookie)</script><img src=x onerror=alert(1)>',
        ])->save();

        $response = $this->actingAs($user)
            ->getJson(route('account.purchases.online.chapter', [$purchase, $chapter->id]));

        $response->assertOk();

        $html = $response->json('html');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert(', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('<p>Safe</p>', $html);
    }

    public function test_missing_chapter_returns_not_found(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        $this->actingAs($user)
            ->getJson(route('account.purchases.online.chapter', [$purchase, 999999]))
            ->assertNotFound();
    }

    /* ------------------------------------------------------------------ *
     * Progress
     * ------------------------------------------------------------------ */

    public function test_progress_is_saved_for_the_owning_purchase(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        $this->actingAs($user)
            ->postJson(route('account.purchases.online.progress', $purchase), [
                'chapter_id' => $chapter->id,
                'page' => 4,
                'percent' => 33.25,
            ])
            ->assertOk()
            ->assertJsonPath('saved', true);

        $this->assertDatabaseHas('reading_progress', [
            'purchase_id' => $purchase->id,
            'chapter_id' => $chapter->id,
            'current_page' => 4,
        ]);
    }

    public function test_progress_is_updated_not_duplicated_on_a_second_save(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $first = $this->chapter($book, ['position' => 1]);
        $second = $this->chapter($book, ['position' => 2]);
        $purchase = $this->purchaseFor($user, $book);

        $this->actingAs($user)->postJson(route('account.purchases.online.progress', $purchase), [
            'chapter_id' => $first->id, 'page' => 2, 'percent' => 10,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('account.purchases.online.progress', $purchase), [
            'chapter_id' => $second->id, 'page' => 9, 'percent' => 40,
        ])->assertOk();

        $this->assertSame(1, ReadingProgress::where('purchase_id', $purchase->id)->count());
        $this->assertDatabaseHas('reading_progress', [
            'purchase_id' => $purchase->id,
            'chapter_id' => $second->id,
            'current_page' => 9,
        ]);
    }

    public function test_progress_rejects_a_chapter_from_another_book(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $this->chapter($book);

        $otherBook = $this->onlineBook();
        $foreign = $this->chapter($otherBook);

        $purchase = $this->purchaseFor($user, $book);

        $this->actingAs($user)
            ->postJson(route('account.purchases.online.progress', $purchase), [
                'chapter_id' => $foreign->id, 'page' => 1, 'percent' => 1,
            ])
            ->assertJsonValidationErrors('chapter_id');

        $this->assertSame(0, ReadingProgress::where('purchase_id', $purchase->id)->count());
    }

    public function test_progress_cannot_be_written_by_another_customer(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book);
        $purchase = $this->purchaseFor($owner, $book);

        $this->actingAs($intruder)
            ->postJson(route('account.purchases.online.progress', $purchase), [
                'chapter_id' => $chapter->id, 'page' => 1, 'percent' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, ReadingProgress::where('purchase_id', $purchase->id)->count());
    }

    public function test_progress_requires_authentication(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        $this->postJson(route('account.purchases.online.progress', $purchase), [
            'chapter_id' => $chapter->id, 'page' => 1, 'percent' => 1,
        ])->assertUnauthorized();

        $this->assertSame(0, ReadingProgress::where('purchase_id', $purchase->id)->count());
    }

    /* ------------------------------------------------------------------ *
     * Bookmarks
     * ------------------------------------------------------------------ */

    public function test_owner_can_save_and_list_a_bookmark(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        $response = $this->actingAs($user)
            ->postJson(route('account.purchases.online.bookmarks.store', $purchase), [
                'chapter_id' => $chapter->id,
                'page' => 5,
                'label' => 'Key passage',
            ]);

        $response->assertOk()->assertJsonPath('saved', true)->assertJsonCount(1, 'bookmarks');

        $this->assertDatabaseHas('reading_bookmarks', [
            'purchase_id' => $purchase->id,
            'chapter_id' => $chapter->id,
            'page' => 5,
            'label' => 'Key passage',
        ]);
    }

    public function test_saving_the_same_position_twice_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        $this->actingAs($user)->postJson(route('account.purchases.online.bookmarks.store', $purchase), [
            'chapter_id' => $chapter->id, 'page' => 3,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('account.purchases.online.bookmarks.store', $purchase), [
            'chapter_id' => $chapter->id, 'page' => 3, 'label' => 'Named later',
        ])->assertOk();

        $this->assertSame(1, ReadingBookmark::where('purchase_id', $purchase->id)->count());
        $this->assertDatabaseHas('reading_bookmarks', [
            'purchase_id' => $purchase->id, 'page' => 3, 'label' => 'Named later',
        ]);
    }

    public function test_a_bookmark_cannot_be_placed_in_another_books_chapter(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $this->chapter($book);

        $otherBook = $this->onlineBook();
        $foreign = $this->chapter($otherBook);

        $purchase = $this->purchaseFor($user, $book);

        $this->actingAs($user)
            ->postJson(route('account.purchases.online.bookmarks.store', $purchase), [
                'chapter_id' => $foreign->id, 'page' => 1,
            ])
            ->assertJsonValidationErrors('chapter_id');

        $this->assertSame(0, ReadingBookmark::where('purchase_id', $purchase->id)->count());
    }

    public function test_another_customer_cannot_save_a_bookmark(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book);
        $purchase = $this->purchaseFor($owner, $book);

        $this->actingAs($intruder)
            ->postJson(route('account.purchases.online.bookmarks.store', $purchase), [
                'chapter_id' => $chapter->id, 'page' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, ReadingBookmark::where('purchase_id', $purchase->id)->count());
    }

    public function test_owner_can_delete_their_bookmark(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        $bookmark = ReadingBookmark::factory()
            ->forPurchase($purchase, 6, 'Mine', $chapter->id)
            ->create();

        $this->actingAs($user)
            ->deleteJson(route('account.purchases.online.bookmarks.destroy', [$purchase, $bookmark->id]))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('reading_bookmarks', ['id' => $bookmark->id]);
    }

    public function test_a_customer_cannot_delete_another_customers_bookmark(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book);
        $purchase = $this->purchaseFor($owner, $book);

        $bookmark = ReadingBookmark::factory()
            ->forPurchase($purchase, 6, 'Theirs', $chapter->id)
            ->create();

        // The intruder does not own the purchase, so the policy refuses first.
        $this->actingAs($intruder)
            ->deleteJson(route('account.purchases.online.bookmarks.destroy', [$purchase, $bookmark->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('reading_bookmarks', ['id' => $bookmark->id]);
    }

    public function test_a_bookmark_from_another_of_my_own_purchases_is_not_reachable(): void
    {
        $user = User::factory()->create();

        $firstBook = $this->onlineBook();
        $firstChapter = $this->chapter($firstBook);
        $firstPurchase = $this->purchaseFor($user, $firstBook);

        $secondBook = $this->onlineBook();
        $secondChapter = $this->chapter($secondBook);
        $secondPurchase = $this->purchaseFor($user, $secondBook);

        $bookmark = ReadingBookmark::factory()
            ->forPurchase($secondPurchase, 8, 'Second book', $secondChapter->id)
            ->create();

        // Same customer, but the bookmark belongs to a different purchase than
        // the one in the URL: that must be a 404, not a cross-purchase delete.
        $this->actingAs($user)
            ->deleteJson(route('account.purchases.online.bookmarks.destroy', [$firstPurchase, $bookmark->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('reading_bookmarks', ['id' => $bookmark->id]);

        // ...and it is still reachable through the purchase it belongs to.
        $this->actingAs($user)
            ->deleteJson(route('account.purchases.online.bookmarks.destroy', [$secondPurchase, $bookmark->id]))
            ->assertOk();

        $this->assertDatabaseMissing('reading_bookmarks', ['id' => $bookmark->id]);
    }

    public function test_bookmarks_are_private_to_their_purchase(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        ReadingBookmark::factory()->forPurchase($purchase, 2, 'Mine', $chapter->id)->create();

        $this->actingAs($user)
            ->getJson(route('account.purchases.online.manifest', $purchase))
            ->assertOk()
            ->assertJsonCount(1, 'bookmarks')
            ->assertJsonPath('bookmarks.0.label', 'Mine');
    }

    /* ------------------------------------------------------------------ *
     * Dual format
     * ------------------------------------------------------------------ */

    public function test_dual_format_book_prefers_the_native_reader_but_keeps_the_pdf(): void
    {
        $user = User::factory()->create();
        $book = $this->publishedBook([
            'book_format' => Book::FORMAT_BOTH,
            'file_path' => 'ebooks/both.pdf',
            'file_type' => 'pdf',
        ]);
        Storage::disk('local')->put($book->file_path, "%PDF-1.4\nreader content\n%%EOF\n");
        $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        $this->actingAs($user)
            ->get(route('account.purchases.read', $purchase))
            ->assertOk()
            ->assertViewIs('account.purchases.online-reader');

        $this->actingAs($user)
            ->get(route('account.purchases.read-pdf', $purchase))
            ->assertOk()
            ->assertViewIs('account.purchases.reader');
    }

    public function test_read_pdf_is_still_owner_only(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $book = $this->publishedBook([
            'book_format' => Book::FORMAT_BOTH,
            'file_path' => 'ebooks/both.pdf',
            'file_type' => 'pdf',
        ]);
        Storage::disk('local')->put($book->file_path, "%PDF-1.4\nreader content\n%%EOF\n");
        $this->chapter($book);
        $purchase = $this->purchaseFor($owner, $book);

        $this->actingAs($intruder)
            ->get(route('account.purchases.read-pdf', $purchase))
            ->assertForbidden();
    }

    public function test_guest_cannot_open_the_pdf_reader_of_a_dual_format_book(): void
    {
        $user = User::factory()->create();
        $book = $this->publishedBook([
            'book_format' => Book::FORMAT_BOTH,
            'file_path' => 'ebooks/both.pdf',
            'file_type' => 'pdf',
        ]);
        Storage::disk('local')->put($book->file_path, "%PDF-1.4\nreader content\n%%EOF\n");
        $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        $this->from(route('cart.show'))->get(route('account.purchases.read-pdf', $purchase))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('auth_intended', route('account.purchases.read-pdf', $purchase));
    }

    public function test_online_reader_endpoints_are_not_available_for_pdf_only_books(): void
    {
        $user = User::factory()->create();
        $book = $this->publishedBook([
            'book_format' => Book::FORMAT_PDF,
            'file_path' => 'books/only.pdf',
            'file_type' => 'pdf',
        ]);
        $purchase = $this->purchaseFor($user, $book);

        $this->actingAs($user)
            ->getJson(route('account.purchases.online.manifest', $purchase))
            ->assertNotFound();
    }

    public function test_a_refunded_order_loses_reader_access(): void
    {
        $user = User::factory()->create();
        $book = $this->onlineBook();
        $chapter = $this->chapter($book);
        $purchase = $this->purchaseFor($user, $book);

        $purchase->order->update(['status' => Order::STATUS_CANCELLED, 'paid_at' => null]);
        $purchase->order->refresh();

        $this->actingAs($user)
            ->getJson(route('account.purchases.online.chapter', [$purchase, $chapter->id]))
            ->assertForbidden();
    }
}
