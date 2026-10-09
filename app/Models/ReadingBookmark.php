<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's saved position inside a book they own.
 *
 * Bookmarks are always reached through a purchase, so the PurchasePolicy keeps
 * them private to their owner exactly like progress and the PDF stream.
 */
class ReadingBookmark extends Model
{
    /** @use HasFactory<ReadingBookmarkFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'purchase_id',
        'book_id',
        'chapter_id',
        'page',
        'progress_percent',
        'label',
        'position_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'progress_percent' => 'decimal:2',
        ];
    }

    /**
     * Deterministic identity of a bookmarked position, unique per purchase.
     *
     * SQL treats NULLs as distinct, so a unique index on (chapter_id, page)
     * would happily store the same PDF page several times. Building the key in
     * PHP instead keeps the "one bookmark per position" rule in MySQL *and*
     * SQLite, and gives the reader a stable value to upsert against.
     */
    public static function positionKeyFor(?int $chapterId, int $page): string
    {
        return $chapterId === null
            ? 'p'.$page
            : 'c'.$chapterId.':p'.$page;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(BookChapter::class);
    }
}
