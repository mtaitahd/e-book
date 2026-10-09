<?php

namespace App\Models;

use App\Events\PaymentUnsuccessful;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const PROVIDER_ABLINER = 'abliner';

    /**
     * The ways a customer can pay. `mobile` is a USSD push, `card` is a hosted
     * Abliner card page we redirect to, and `control_number` is a ClickPesa
     * BillPay control number they pay from any app or bank.
     */
    public const TYPE_MOBILE = 'mobile';

    public const TYPE_CARD = 'card';

    public const TYPE_CONTROL_NUMBER = 'control_number';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_MOBILE,
        self::TYPE_CARD,
        self::TYPE_CONTROL_NUMBER,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_VOIDED,
        self::STATUS_EXPIRED,
    ];

    /**
     * UI-only network options. Abliner routes the request to the best available
     * rail itself, so these are presentation/audit values only
     * (channel_provider) — what the customer chose, not what the provider
     * ultimately used.
     */
    public const NETWORKS = [
        'airtel_money' => 'Airtel Money',
        'mpesa' => 'M-Pesa',
        'mixx_yas' => 'Mixx by Yas',
        'halotel' => 'Halotel / HaloPesa',
    ];

    /**
     * Methods the customer can choose on the payment page. These are exactly
     * the values the Abliner Deposit API (POST /api/v1/deposits) accepts in its
     * `method` field, so this list doubles as the allow-list for that endpoint.
     *
     * Control numbers are deliberately absent. The ClickPesa endpoint the
     * channel depended on is not serving requests, so no control number can be
     * issued or reconciled. `TYPE_CONTROL_NUMBER` and `isControlNumber()` stay
     * in place so payments created before the removal are still labelled and
     * still settled by an incoming webhook; only new ones cannot be started.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const METHODS = [
        self::TYPE_MOBILE => [
            'label' => 'Mobile money',
            'hint' => 'Approve a USSD push on your phone.',
        ],
        self::TYPE_CARD => [
            'label' => 'Card',
            'hint' => 'Pay by Visa or Mastercard on the secure Abliner page.',
        ],
    ];

    /**
     * Labels for the methods that are no longer offered but can still exist on
     * an older payment row, so a customer returning to a half-finished control
     * number payment sees what they were actually paying.
     *
     * @var array<string, string>
     */
    public const RETIRED_METHOD_LABELS = [
        self::TYPE_CONTROL_NUMBER => 'Control number',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'provider',
        'payment_type',
        'phone',
        'provider_reference',
        'external_reference',
        'customer_reference',
        'control_number',
        'payment_url',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'channel_provider',
        'failure_reason',
        'expires_at',
        'paid_at',
        'provider_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * A card payment leaves the site, so the hosted URL we redirect to is part
     * of the row: without it a customer who closed the tab has no way back in.
     */
    public function isCard(): bool
    {
        return $this->payment_type === self::TYPE_CARD;
    }

    public function isMobile(): bool
    {
        return $this->payment_type === self::TYPE_MOBILE;
    }

    public function isControlNumber(): bool
    {
        return $this->payment_type === self::TYPE_CONTROL_NUMBER;
    }

    /**
     * The reference the customer actually reads out or dials, whichever channel
     * they used. Null for a plain USSD push that never got a reference.
     */
    public function customerReference(): ?string
    {
        return $this->control_number ?? $this->external_reference ?? null;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function hasExpiredRequest(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Observe — never alter — the status the existing Abliner flow already
     * wrote. When a payment ends without success we only announce it so the
     * customer is not left guessing. Persistence, status handling and
     * idempotency are untouched.
     */
    protected static function booted(): void
    {
        static::updated(function (self $payment) {
            if (! $payment->wasChanged('status')) {
                return;
            }

            $unsuccessful = [
                self::STATUS_FAILED,
                self::STATUS_VOIDED,
                self::STATUS_EXPIRED,
            ];

            if (in_array($payment->status, $unsuccessful, true)) {
                PaymentUnsuccessful::dispatch($payment);
            }
        });
    }
}