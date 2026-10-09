<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Internal delivery log for customer email notifications.
 *
 * This table is the idempotency ledger for Stage 9 email. Exactly one row may
 * exist per (order_id, type) — enforced by a unique index — so a webhook that
 * is delivered twice, a page refresh, or two concurrent confirmations can
 * never produce a second copy of the same logical email.
 *
 * A row is claimed as `pending` before delivery, then moved to `sent` or
 * `failed`. A `failed` row is re-sendable, so a later confirmation of the same
 * order retries delivery instead of silently giving up.
 */
class EmailNotification extends Model
{
    public const TYPE_PURCHASE_RECEIPT = 'purchase_receipt';

    public const TYPE_PAYMENT_FAILED = 'payment_failed';

    public const TYPE_PAYMENT_VOIDED = 'payment_voided';

    public const TYPE_PAYMENT_EXPIRED = 'payment_expired';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * Payment states the customer should be told about when the payment ends
     * without success. Maps a Payment status to the email type used for it.
     */
    public const UNSUCCESSFUL_TYPES = [
        Payment::STATUS_FAILED => self::TYPE_PAYMENT_FAILED,
        Payment::STATUS_VOIDED => self::TYPE_PAYMENT_VOIDED,
        Payment::STATUS_EXPIRED => self::TYPE_PAYMENT_EXPIRED,
    ];

    protected $fillable = [
        'user_id',
        'order_id',
        'payment_id',
        'type',
        'recipient',
        'status',
        'attempts',
        'sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function scopeForOrder($query, Order $order, string $type)
    {
        return $query->where('order_id', $order->id)->where('type', $type);
    }
}
