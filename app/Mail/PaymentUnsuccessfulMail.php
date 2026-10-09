<?php

namespace App\Mail;

use App\Models\Payment;
use App\Support\ReceiptFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Friendly notice that a payment did not go through.
 *
 * Deliberately says nothing about why the provider rejected the payment beyond
 * a safe, static sentence: the internal failure_reason may contain raw upstream
 * API responses, and neither those nor any secret may reach the customer's
 * inbox. The only action offered is a retry of the same order.
 */
class PaymentUnsuccessfulMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Payment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment failed — Order '.$this->orderNumber(),
        );
    }

    public function content(): Content
    {
        $order = $this->payment->order;

        return new Content(
            view: 'emails.payment-unsuccessful',
            text: 'emails.payment-unsuccessful-text',
            with: [
                'customerName' => ReceiptFormatter::customerName($order),
                'orderNumber' => (string) $order->order_number,
                'orderTotal' => ReceiptFormatter::money((string) $order->total, (string) $order->currency),
                'failedOn' => $this->failedOn(),
                'reason' => $this->reason(),
                'isVoided' => $this->payment->status === Payment::STATUS_VOIDED,
                'isExpired' => $this->payment->status === Payment::STATUS_EXPIRED,
                'retryUrl' => route('account.orders.payments.show', $order),
                'orderUrl' => route('account.orders.show', $order),
            ],
        );
    }

    private function orderNumber(): string
    {
        return (string) ($this->payment->order?->order_number ?? '');
    }

    private function failedOn(): string
    {
        $moment = $this->payment->updated_at ?? $this->payment->created_at;

        return $moment?->timezone(config('app.timezone'))->format('j F Y \a\t H:i')
            ?? now()->timezone(config('app.timezone'))->format('j F Y \a\t H:i');
    }

    /**
     * A safe, static explanation. Never interpolates the stored provider
     * failure_reason, which is an internal diagnostic that can embed raw
     * upstream responses.
     */
    private function reason(): string
    {
        return match ($this->payment->status) {
            Payment::STATUS_VOIDED => 'This payment was voided before it completed, so your order was not charged.',
            Payment::STATUS_EXPIRED => 'This payment expired before it completed, so your order was not charged.',
            default => 'Your payment could not be completed, so your order has not been charged.',
        };
    }
}
