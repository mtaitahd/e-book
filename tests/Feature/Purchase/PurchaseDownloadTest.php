<?php

namespace Tests\Feature\Purchase;

use App\Models\Book;
use App\Models\DownloadLog;
use Illuminate\Support\Facades\Storage;

class PurchaseDownloadTest extends PurchaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function storeFile(Book $book, string $path = 'ebooks/sample.pdf', string $content = "%PDF-1.4\ntest\n%%EOF\n"): void
    {
        $book->update(['file_path' => $path]);
        Storage::disk('local')->put($path, $content);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $user = $this->customer();
        $book = $this->publishedBook(['title' => 'Guest Book']);
        $this->storeFile($book);
        $purchase = $this->paidOrder($user, [['book' => $book]])->purchases()->firstOrFail();

        $this->from(route('cart.show'))->get(route('account.purchases.download', $purchase))
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('auth_intended', route('account.purchases.download', $purchase));

        $this->assertSame(0, DownloadLog::count());
    }

    public function test_other_customer_is_forbidden_and_no_log_is_written(): void
    {
        $owner = $this->customer();
        $intruder = $this->customer(['email' => 'intruder@example.com', 'first_name' => 'Intruder', 'last_name' => 'User']);
        $book = $this->publishedBook(['title' => 'Private Book']);
        $this->storeFile($book);
        $purchase = $this->paidOrder($owner, [['book' => $book]])->purchases()->firstOrFail();

        $this->actingAs($intruder)
            ->get(route('account.purchases.download', $purchase))
            ->assertForbidden();

        $this->assertSame(0, DownloadLog::count());
    }

    public function test_owner_can_download_pdf_with_clean_name_and_mime(): void
    {
        $user = $this->customer();
        $book = $this->publishedBook(['title' => 'Data Skills for Africa']);
        $this->storeFile($book);
        $purchase = $this->paidOrder($user, [['book' => $book]])->purchases()->firstOrFail();

        $this->actingAs($user)
            ->get(route('account.purchases.download', $purchase))
            ->assertOk()
            ->assertDownload('Data-Skills-for-Africa.pdf')
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_epub_download_uses_epub_mime(): void
    {
        $user = $this->customer();
        $book = $this->publishedBook(['title' => 'Epub Guide', 'file_type' => 'epub']);
        $this->storeFile($book, 'ebooks/sample.epub', 'some epub bytes');
        $purchase = $this->paidOrder($user, [['book' => $book]])->purchases()->firstOrFail();

        $this->actingAs($user)
            ->get(route('account.purchases.download', $purchase))
            ->assertOk()
            ->assertDownload('Epub-Guide.epub')
            ->assertHeader('Content-Type', 'application/epub+zip');
    }

    public function test_every_download_is_logged(): void
    {
        $user = $this->customer();
        $book = $this->publishedBook(['title' => 'Logged Book']);
        $this->storeFile($book);
        $purchase = $this->paidOrder($user, [['book' => $book]])->purchases()->firstOrFail();

        $this->actingAs($user)->get(route('account.purchases.download', $purchase))->assertOk();
        $this->actingAs($user)->get(route('account.purchases.download', $purchase))->assertOk();

        $this->assertSame(2, DownloadLog::count());
        $this->assertDatabaseHas('download_logs', [
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'book_id' => $book->id,
        ]);
    }

    public function test_missing_file_redirects_with_friendly_error_and_no_path_leakage(): void
    {
        $user = $this->customer();
        $book = $this->publishedBook(['title' => 'Ghost Book', 'file_path' => 'ebooks/ghost.pdf']);
        $purchase = $this->paidOrder($user, [['book' => $book]])->purchases()->firstOrFail();

        $response = $this->actingAs($user)
            ->get(route('account.purchases.download', $purchase))
            ->assertRedirect(route('account.purchases.index'));

        $response->assertSessionHas('error');
        $this->assertStringContainsString('temporarily unavailable', session('error'));
        $this->assertStringNotContainsString('ghost.pdf', session('error'));

        $this->assertSame(0, DownloadLog::count());
    }

    public function test_no_file_path_redirects_with_friendly_error(): void
    {
        $user = $this->customer();
        $book = $this->publishedBook(['title' => 'No File Yet']);
        $purchase = $this->paidOrder($user, [['book' => $book]])->purchases()->firstOrFail();

        $this->actingAs($user)
            ->get(route('account.purchases.download', $purchase))
            ->assertRedirect(route('account.purchases.index'));
    }

    public function test_archived_book_remains_downloadable_for_owner(): void
    {
        $user = $this->customer();
        $book = \App\Models\Book::factory()->archived()->create(['title' => 'Archived Evergreen']);
        $this->storeFile($book);
        $purchase = $this->paidOrder($user, [['book' => $book]])->purchases()->firstOrFail();

        $this->actingAs($user)
            ->get(route('account.purchases.download', $purchase))
            ->assertOk()
            ->assertDownload('Archived-Evergreen.pdf');
    }

    public function test_index_surfaces_only_own_purchases(): void
    {
        $owner = $this->customer();
        $other = $this->customer(['email' => 'other@example.com', 'first_name' => 'Other', 'last_name' => 'User']);
        $mine = $this->publishedBook(['title' => 'Mine Only']);
        $theirs = $this->publishedBook(['title' => 'Not Mine']);

        $myPurchase = $this->paidOrder($owner, [['book' => $mine]])->purchases()->firstOrFail();
        $this->paidOrder($other, [['book' => $theirs]])->purchases()->firstOrFail();

        $this->actingAs($owner)
            ->get(route('account.purchases.index'))
            ->assertOk()
            ->assertSee('Mine Only')
            ->assertDontSee('Not Mine');

        $this->assertTrue($myPurchase->book->is($mine));
    }

    public function test_show_page_is_forbidden_for_others(): void
    {
        $owner = $this->customer();
        $intruder = $this->customer(['email' => 'peek@example.com', 'first_name' => 'Peek', 'last_name' => 'User']);
        $book = $this->publishedBook(['title' => 'Locked Book']);
        $purchase = $this->paidOrder($owner, [['book' => $book]])->purchases()->firstOrFail();

        $this->actingAs($intruder)
            ->get(route('account.purchases.show', $purchase))
            ->assertForbidden();
    }
}