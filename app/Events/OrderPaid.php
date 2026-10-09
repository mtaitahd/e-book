<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised by the paid-order flow once purchase entitlements have been granted.
 *
 * This is the single existing funnel through which a customer gains ownership
 * of a book (App\Services\PurchaseService::createFromPaidOrder, reached from
 * the confirmed Abliner callback and from the customer's own status refresh), so
 * it is also the correct and only place a purchase receipt is triggered from.
 *
 * The event carries the order, never a pre-rendered message: listeners decide
 * for themselves whether the order is actually payable (a free order has no
 * payment) and they must defer delivery until the transaction commits.
 */
class OrderPaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}
}
