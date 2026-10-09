<?php

namespace App\Listeners;

use App\Events\PaymentUnsuccessful;
use App\Services\CustomerEmailService;
use Illuminate\Support\Facades\DB;

/**
 * Tells the customer when a payment failed, voided or expired.
 *
 * Also after-commit, for the same reason as the receipt: the notice should
 * describe a status the database has actually kept.
 */
class SendPaymentUnsuccessfulNotice
{
    public function __construct(
        private readonly CustomerEmailService $emails,
    ) {}

    public function handle(PaymentUnsuccessful $event): void
    {
        $payment = $event->payment;
        $emails = $this->emails;

        DB::afterCommit(function () use ($payment, $emails) {
            $emails->sendUnsuccessfulNoticeFor($payment);
        });
    }
}
