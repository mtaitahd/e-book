<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The last page a customer was reading in a purchased e-book. Records are
 * strictly scoped 1:1 to a purchase the current user owns; the PurchasePolicy
 * block methods keeps any mutation tied to the authenticated owner.
 */
class ReadingProgress extends Model
{
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
        'current_page',
        'progress_percent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_page' => 'integer',
            'progress_percent' => 'decimal:2',
        ];
    }

    /**
     * The chapter this resume point belongs to, or NULL for a PDF book.
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(BookChapter::class);
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
}
