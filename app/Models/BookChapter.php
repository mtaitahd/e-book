<?php

namespace App\Models;

use App\Services\HtmlSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One native-reader chapter of an e-book.
 *
 * `content` only ever holds HTML that has already been through
 * {@see HtmlSanitizer}; the reader renders it unescaped, so the
 * allowlist in that service is the security boundary for stored content.
 */
class BookChapter extends Model
{
    /** @use HasFactory<BookChapterFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'book_id',
        'title',
        'slug',
        'content',
        'position',
        'is_free',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_free' => 'boolean',
        ];
    }

    /**
     * Build a URL-safe slug from the chapter title, falling back to a stable
     * placeholder when the title contains no usable characters.
     */
    public static function slugFor(string $title): string
    {
        $slug = Str::slug($title);

        return $slug !== '' ? $slug : 'chapter';
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
