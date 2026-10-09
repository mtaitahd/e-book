<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-initiated payout from the store's Abliner wallet.
 *
 * The row is created BEFORE the provider is called, so a timeout or a
 * half-answered request still leaves an auditable record of what was asked for,
 * by whom, and what the provider eventually said. The row id also seeds the
 * idempotency key, so retrying the same row can never send the money twice.
 *
 * @property int $id
 * @property int|null $requested_by
 * @property string $method
 * @property string $recipient
 * @property string|null $bank_code
 * @property string|null $account_name
 * @property int $amount
 * @property int|null $fee
 * @property string $currency
 * @property string $reference
 * @property string $status
 * @property string|null $provider_reference
 * @property string|null $external_reference
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $provider_payload
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $settled_at
 */
class Withdrawal extends Model
{
    use HasFactory;

    public const PROVIDER_ABLINER = 'abliner';

    public const METHOD_MOBILE = 'mobile';

    public const METHOD_BANK = 'bank';

    /**
     * @var list<string>
     */
    public const METHODS = [
        self::METHOD_MOBILE,
        self::METHOD_BANK,
    ];

    /**
     * Bank codes the provider documents for a bank payout.
     *
     * @var list<string>
     */
    public const BANK_CODES = [
        'CRDB',
        'NMB',
        'NBC',
        'EQUITY',
        'EXIM',
        'STANBIC',
        'DTB',
        'AKIBA',
        'AZANIA',
        'BOA',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVERSED = 'reversed';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_REVERSED,
    ];

    protected $fillable = [
        'provider',
        'requested_by',
        'method',
        'recipient',
        'bank_code',
        'account_name',
        'amount',
        'fee',
        'currency',
        'reference',
        'status',
        'provider_reference',
        'external_reference',
        'failure_reason',
        'provider_payload',
        'paid_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee' => 'integer',
            'provider_payload' => 'array',
            'paid_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isMobile(): bool
    {
        return $this->method === self::METHOD_MOBILE;
    }

    public function isBank(): bool
    {
        return $this->method === self::METHOD_BANK;
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

    public function isSettled(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_REVERSED,
        ], true);
    }

    /**
     * The destination, formatted the way an operator would read it aloud.
     */
    public function destinationLabel(): string
    {
        if ($this->isBank()) {
            return trim(($this->bank_code ?? '').' · '.$this->account_name.' · '.$this->recipient);
        }

        return (string) $this->recipient;
    }
}