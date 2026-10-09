<?php

namespace App\Models;

use App\Services\PurchaseService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
        self::STATUS_CANCELLED,
        self::STATUS_FAILED,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'order_number',
        'subtotal',
        'total',
        'currency',
        'status',
        'paid_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Whenever an order transitions to PAID (via a confirmed Abliner callback,
     * a customer refresh, or any other legitimate path) the corresponding
     * purchase entitlements are created. Creation is idempotent and guarded
     * by database unique constraints, so double-delivered webhooks and
     * racing requests can never create duplicate ownership records.
     */
    protected static function booted(): void
    {
        static::saved(function (Order $order) {
            if ($order->wasChanged('status') && $order->isPaid()) {
                app(PurchaseService::class)->createFromPaidOrder($order);
            }
        });
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Bootstrap-badge colour used when rendering this order's status.
     */
    public function statusBadge(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'success',
            self::STATUS_CANCELLED => 'secondary',
            self::STATUS_FAILED => 'danger',
            default => 'warning',
        };
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * A unique human-readable order reference, e.g. "EBS-20260929-0042".
     *
     * The column is uniquely indexed, so the value is retried on the (very
     * unlikely) chance of a collision rather than trusted blindly.
     */
    public static function generateNumber(): string
    {
        do {
            $number = sprintf(
                'EBS-%s-%04d',
                now()->format('Ymd'),
                random_int(0, 9999)
            );
        } while (self::where('order_number', $number)->exists());

        return $number;
    }
}