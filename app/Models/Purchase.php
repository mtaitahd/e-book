<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A confirmed ownership record: the customer (user) permanently owns a book
 * because it was included in a paid order.
 *
 * Purchases are only ever created from a legitimately paid order via
 * App\Services\PurchaseService. They are never fabricated by changing a
 * payment/order status by hand.
 */
class Purchase extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'order_id',
        'order_item_id',
        'book_id',
        'amount',
        'currency',
        'purchased_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'purchased_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * This purchase's single resume point. The unique purchase_id constraint
     * means a customer can only ever have one saved position per purchase,
     * across the PDF reader and the native reader alike.
     */
    public function readingProgress(): HasOne
    {
        return $this->hasOne(ReadingProgress::class);
    }

    /**
     * The reader's saved positions for this purchase.
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(ReadingBookmark::class)->orderBy('chapter_id')->orderBy('page');
    }
}
