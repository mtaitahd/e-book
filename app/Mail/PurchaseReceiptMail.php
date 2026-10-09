<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Payment;
use App\Support\ReceiptFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Purchase receipt for a genuinely paid order.
 *
 * Rendered from the order snapshot only, so a later price edit or book
 * change can never rewrite what the customer was actually charged. Contains no
 * attachment and no private file URL: reading and downloading stay behind the
 * existing authenticated My Books routes.
 */
class PurchaseReceiptMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public ?Payment $payment = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment successful — Order '.$this->order->order_number,
        );
    }

    public function content(): Content
    {
        $order = $this->order->loadMissing(['user', 'items.book.authors']);

        return new Content(
            view: 'emails.purchase-receipt',
            with: [
                'customerName' => ReceiptFormatter::customerName($order),
                'orderNumber' => (string) $order->order_number,
                'statusLabel' => strtoupper((string) $order->status),
                'currency' => (string) $order->currency,
                'subtotal' => ReceiptFormatter::money((string) $order->subtotal, (string) $order->currency),
                'total' => ReceiptFormatter::money((string) $order->total, (string) $order->currency),
                'amountPaid' => ReceiptFormatter::money((string) $order->total, (string) $order->currency),
                'purchasedOn' => $this->purchasedOn(),
                'paymentReference' => ReceiptFormatter::paymentReference($this->payment),
                'lines' => ReceiptFormatter::lines($order),
                'myBooksUrl' => route('account.purchases.index'),
                'orderUrl' => route('account.orders.show', $order),
            ],
        );
    }

    /**
     * The order's own stored timestamp, falling back to the payment's
     * successful moment and finally to now, so the receipt is never blank.
     */
    private function purchasedOn(): string
    {
        $moment = $this->order->created_at ?? $this->payment?->paid_at;

        return $moment?->timezone(config('app.timezone'))->format('j F Y \a\t H:i')
            ?? now()->timezone(config('app.timezone'))->format('j F Y \a\t H:i');
    }
}
