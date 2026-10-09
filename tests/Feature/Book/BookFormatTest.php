<?php

namespace Tests\Feature\Book;

use App\Models\Book;
use App\Models\BookChapter;
use App\Models\ReadingBookmark;
use App\Models\ReadingProgress;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Purchase\PurchaseTestCase;

class BookFormatTest extends PurchaseTestCase
{
    public function test_native_reader_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('books', 'book_format'));
        $this->assertTrue(Schema::hasColumn('reading_progress', 'chapter_id'));
        $this->assertTrue(Schema::hasColumn('reading_progress', 'progress_percent'));
        $this->assertTrue(Schema::hasTable('book_chapters'));
        $this->assertTrue(Schema::hasTable('reading_bookmarks'));
    }

    public function test_default_format_is_pdf_and_backfill_safe(): void
    {
        $book = Book::factory()->create();

        $this->assertSame('pdf', $book->book_format);
        $this->assertSame(Book::FORMAT_PDF, $book->format());
        $this->assertTrue($book->isPdfFormat());
        $this->assertFalse($book->isOnlineFormat());
        $this->assertFalse($book->hasOnlineReading());
    }

    public function test_unknown_format_falls_back_to_pdf(): void
    {
        $book = Book::factory()->create(['book_format' => 'totally-bogus']);

        $this->assertSame(Book::FORMAT_PDF, $book->format());
    }

    public function test_chapters_ordering_and_online_detection(): void
    {
        $book = Book::factory()->online()->published()->create();

        $second = BookChapter::factory()->for($book)->create(['position' => 2, 'title' => 'Second']);
        $first = BookChapter::factory()->for($book)->create(['position' => 1, 'title' => 'First']);

        $book->refresh();

        $this->assertTrue($book->hasOnlineReading());
        $this->assertFalse($book->hasPdfFile());
        $this->assertSame(
            ['First', 'Second'],
            $book->chapters->pluck('title')->all(),
        );
        $this->assertSame($second->id, $book->chapters->last()->id);
        $this->assertSame($first->id, $book->chapters->first()->id);
    }

    public function test_free_chapter_lookup(): void
    {
        $book = Book::factory()->online()->published()->create();
        BookChapter::factory()->for($book)->create(['position' => 1]);
        $free = BookChapter::factory()->for($book)->free()->create(['position' => 2]);

        $book->refresh();

        $this->assertTrue($free->is_free);
        $this->assertSame($free->id, $book->freeChapter()->id);
    }

    public function test_both_format_supports_both_modes(): void
    {
        $book = Book::factory()->both()->published()->create(['file_path' => 'ebooks/x.pdf']);
        BookChapter::factory()->for($book)->create();

        $this->assertTrue($book->isPdfFormat());
        $this->assertTrue($book->isOnlineFormat());
        $this->assertTrue($book->hasPdfFile());
        $this->assertTrue($book->hasOnlineReading());
        $this->assertSame('PDF & Online', $book->formatLabel());
    }

    public function test_progress_accepts_chapter_and_percent(): void
    {
        $user = User::factory()->customer()->create();
        $book = Book::factory()->online()->published()->create();
        $chapter = BookChapter::factory()->for($book)->create();
        $order = $this->paidOrder($user, [['book' => $book]]);
        app(PurchaseService::class)->createFromPaidOrder($order);
        $purchase = $order->purchases()->firstOrFail();

        $progress = ReadingProgress::create([
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'book_id' => $book->id,
            'chapter_id' => $chapter->id,
            'current_page' => 4,
            'progress_percent' => 37.5,
        ]);

        $this->assertSame($chapter->id, $progress->chapter->id);
        $this->assertSame(4, $progress->current_page);
        $this->assertSame('37.50', $progress->progress_percent);
    }

    public function test_position_key_is_unique_per_purchase(): void
    {
        $this->assertSame('p7', ReadingBookmark::positionKeyFor(null, 7));
        $this->assertSame('c12:p7', ReadingBookmark::positionKeyFor(12, 7));
    }

    public function test_a_pdf_position_can_only_be_bookmarked_once_per_purchase(): void
    {
        $user = User::factory()->customer()->create();
        $book = $this->publishedBook();
        $order = $this->paidOrder($user, [['book' => $book]]);
        app(PurchaseService::class)->createFromPaidOrder($order);
        $purchase = $order->purchases()->firstOrFail();

        $attributes = [
            'user_id' => $user->id,
            'purchase_id' => $purchase->id,
            'book_id' => $book->id,
            'chapter_id' => null,
            'page' => 7,
            'progress_percent' => 10,
            'position_key' => ReadingBookmark::positionKeyFor(null, 7),
        ];

        ReadingBookmark::create($attributes);

        // chapter_id is NULL here, which SQL would otherwise treat as distinct
        // and happily store a second, duplicate bookmark.
        $this->expectException(QueryException::class);
        ReadingBookmark::create($attributes);
    }
}
