<?php

namespace App\Models;

use App\Services\Abliner\AblinerWebhookService;
use App\Services\PurchaseService;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'event_type',
        'provider_reference',
        'payload_hash',
        'received_at',
        'processed_at',
        'status',
        'failure_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Downstream reaction to a confirmed PAID order.
     *
     * The Abliner webhook service writes the order's PAID status through a
     * query-builder update inside its own transaction (their logic is
     * intentionally untouched), so the Order model's own saved-event never
     * fires on that path. Recording this event's PROCESSED state is the final
     * thing we do after a successful transaction.completed, so we reconcile
     * purchase entitlements from here — idempotently, only for completed
     * events whose order is genuinely paid, and inside the same transaction.
     * Everything else (failed/withdrawal events/ignored/order still pending) is
     * a no-op.
     */
    protected static function booted(): void
    {
        static::saved(function (WebhookEvent $event) {
            if ($event->status !== self::STATUS_PROCESSED) {
                return;
            }

            // Payout callbacks settle a withdrawal, never a purchase.
            if ($event->event_type !== AblinerWebhookService::EVENT_TRANSACTION_COMPLETED) {
                return;
            }

            if ($event->provider_reference === null || $event->provider_reference === '') {
                return;
            }

            $order = Payment::where('provider_reference', $event->provider_reference)
                ->with('order')
                ->first()?->order;

            if ($order !== null && $order->isPaid()) {
                app(PurchaseService::class)->createFromPaidOrder($order);
            }
        });
    }
}