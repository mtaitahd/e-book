<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised when a payment ends without success (failed, voided or expired).
 *
 * This is purely an observation of the status the existing Abliner flow already
 * wrote. No payment handling is added or changed: the event only lets the
 * customer be told, in friendly language, that their payment did not go
 * through and where to retry.
 */
class PaymentUnsuccessful
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Payment $payment,
    ) {}
}
