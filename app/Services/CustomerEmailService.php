<?php

namespace App\Services;

use App\Mail\PaymentUnsuccessfulMail;
use App\Mail\PurchaseReceiptMail;
use App\Models\EmailNotification;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Delivers the Stage 9 customer emails and keeps the delivery ledger.
 *
 * Two hard rules shape this class:
 *
 *  1. Exactly one email per (order, type). The claim is taken inside a
 *     short transaction under a row lock, and the unique index on
 *     (order_id, type) is the backstop. A duplicate webhook, a page refresh
 *     or two concurrent confirmations therefore never send twice.
 *  2. A failed email is recorded and swallowed. It must never propagate back
 *     into the payment or purchase flow, because the money is already correct
 *     and the customer already owns the book. A later confirmation of the same
 *     order retries a previously failed row.
 *
 * Delivery is synchronous by design. QUEUE_CONNECTION is `sync` in this
 * deployment and a shared/XAMPP host has no guaranteed worker, so queueing
 * would leave receipts sitting unsent with no error surfaced. A failed row is
 * recorded and re-sendable, which is the retry path.
 */
class CustomerEmailService
{
    public function sendPurchaseReceiptFor(Order $order): ?EmailNotification
    {
        $order = $order->fresh(['user', 'items.book.authors']);

        if ($order === null || ! $order->isPaid() || $order->items->isEmpty()) {
            return null;
        }

        $recipient = $this->recipient($order);

        if ($recipient === null) {
            Log::warning('email.skipped', [
                'order_id' => $order->id,
                'type' => EmailNotification::TYPE_PURCHASE_RECEIPT,
                'reason' => 'no_recipient',
            ]);

            return null;
        }

        // A free claim also runs through the paid-order flow but has no
        // payment. It gets no receipt: there is no payment to confirm.
        $payment = $order->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->latest('id')
            ->first();

        if ($payment === null) {
            return null;
        }

        return $this->deliver(
            order: $order,
            payment: $payment,
            type: EmailNotification::TYPE_PURCHASE_RECEIPT,
            recipient: $recipient,
            mailable: new PurchaseReceiptMail($order, $payment),
        );
    }

    public function sendUnsuccessfulNoticeFor(Payment $payment): ?EmailNotification
    {
        $payment = $payment->fresh(['order.user', 'order.items']);

        if ($payment === null || $payment->order === null || $payment->isCompleted()) {
            return null;
        }

        // If the order has since been paid, a late failure event must not tell
        // the customer their money is gone.
        if ($payment->order->isPaid()) {
            return null;
        }

        $type = EmailNotification::UNSUCCESSFUL_TYPES[$payment->status] ?? null;

        if ($type === null) {
            return null;
        }

        $recipient = $this->recipient($payment->order);

        if ($recipient === null) {
            return null;
        }

        return $this->deliver(
            order: $payment->order,
            payment: $payment,
            type: $type,
            recipient: $recipient,
            mailable: new PaymentUnsuccessfulMail($payment),
        );
    }

    /**
     * Claim the slot, send outside any transaction so a slow SMTP handshake
     * never holds a lock, then record the outcome.
     */
    private function deliver(
        Order $order,
        ?Payment $payment,
        string $type,
        string $recipient,
        $mailable,
    ): ?EmailNotification {
        $notification = $this->claim($order, $payment, $type, $recipient);

        if ($notification === null) {
            return null;
        }

        try {
            Mail::to($recipient)->send($mailable);

            $notification->forceFill([
                'status' => EmailNotification::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
            ])->save();

            Log::info('email.sent', [
                'order_id' => $order->id,
                'payment_id' => $payment?->id,
                'type' => $type,
                'recipient' => $recipient,
                'attempts' => $notification->attempts,
            ]);
        } catch (Throwable $exception) {
            $this->recordFailure($notification, $exception);
        }

        return $notification;
    }

    /**
     * Reserve the (order, type) slot, or return null when the email has
     * already been delivered (or a concurrent claim won the race).
     */
    private function claim(
        Order $order,
        ?Payment $payment,
        string $type,
        string $recipient,
    ): ?EmailNotification {
        return DB::transaction(function () use ($order, $payment, $type, $recipient) {
            $existing = EmailNotification::query()
                ->where('order_id', $order->id)
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->isSent()) {
                    return null;
                }

                // A previous attempt failed. Reopening the row is the safe
                // retry: the unique index still prevents a second copy.
                $existing->forceFill([
                    'status' => EmailNotification::STATUS_PENDING,
                    'attempts' => $existing->attempts + 1,
                    'error_message' => null,
                ])->save();

                return $existing;
            }

            try {
                return EmailNotification::create([
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'payment_id' => $payment?->id,
                    'type' => $type,
                    'recipient' => $recipient,
                    'status' => EmailNotification::STATUS_PENDING,
                    'attempts' => 1,
                ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }

                return null;
            }
        });
    }

    /**
     * Record the failure for diagnosis and for a later retry, but never rethrow:
     * the payment and the purchase are already committed and correct.
     */
    private function recordFailure(EmailNotification $notification, Throwable $exception): void
    {
        $notification->forceFill([
            'status' => EmailNotification::STATUS_FAILED,
            'error_message' => $this->describe($exception),
        ])->save();

        Log::error('email.failed', [
            'order_id' => $notification->order_id,
            'payment_id' => $notification->payment_id,
            'type' => $notification->type,
            'recipient' => $notification->recipient,
            'attempts' => $notification->attempts,
            'exception' => $exception::class,
        ]);
    }

    /**
     * A short, redacted description for the ledger. URL user-info is stripped
     * so an SMTP DSN can never persist a mailbox password.
     */
    private function describe(Throwable $exception): string
    {
        $message = $exception->getMessage();

        $message = (string) preg_replace('#(?<=://)[^/\s@]+@#', '', $message);
        $message = (string) preg_replace('/\s+/', ' ', $message);

        return Str::limit(class_basename($exception).': '.$message, 500, '');
    }

    private function recipient(Order $order): ?string
    {
        $email = trim((string) ($order->user->email ?? ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        foreach (['UNIQUE constraint failed', 'Duplicate entry', 'UNIQUE', 'Integrity constraint violation'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        foreach ((array) $exception->errorInfo as $info) {
            if (is_string($info) && in_array($info, ['23000', '19', '1062'], true)) {
                return true;
            }
        }

        return false;
    }
}
