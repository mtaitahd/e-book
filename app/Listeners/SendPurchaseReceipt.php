<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\CustomerEmailService;
use Illuminate\Support\Facades\DB;

/**
 * Sends the purchase receipt once the paid-order transaction has committed.
 *
 * DB::afterCommit() defers delivery until the outermost transaction commits
 * and discards the callback on rollback, so a customer is never told they paid
 * for an order that was rolled back. Outside a transaction it runs straight
 * away, which keeps the listener safe to call directly.
 */
class SendPurchaseReceipt
{
    public function __construct(
        private readonly CustomerEmailService $emails,
    ) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $emails = $this->emails;

        DB::afterCommit(function () use ($order, $emails) {
            $emails->sendPurchaseReceiptFor($order);
        });
    }
}
