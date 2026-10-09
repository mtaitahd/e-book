<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;

/**
 * Presentation helpers for customer email.
 *
 * The arithmetic always comes from App\Support\Money (integer-only). The
 * rendering is local to email because receipts are shown currency-first
 * ("TZS 27,000") whereas the storefront renders it currency-last. Money itself
 * is deliberately left untouched.
 */
final class ReceiptFormatter
{
    /**
     * Render a stored decimal-2 amount the way a receipt should read, e.g.
     * "TZS 27,000".
     */
    public static function money(string $amount, string $currency): string
    {
        $whole = Money::toWholeInt($amount);

        $digits = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', (string) abs($whole));

        return ($whole < 0 ? '-' : '').$currency.' '.$digits;
    }

    /**
     * A customer-facing name. Falls back to the email local part, then to
     * "Customer", so a receipt never greets somebody as "Hello ,".
     */
    public static function customerName(Order $order): string
    {
        $name = trim((string) ($order->user->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($order->user->email ?? ''));
        $local = strstr($email, '@', true);

        return $local !== false && $local !== '' ? $local : 'Customer';
    }

    /**
     * One receipt line per purchased book: title, author, quantity, unit price
     * and item subtotal. Amounts are snapshotted from the order item, never
     * recomputed from the book's current price.
     *
     * @return list<array{title: string, author: string, quantity: int, unit_price: string, subtotal: string, cover_url: ?string}>
     */
    public static function lines(Order $order): array
    {
        return $order->items->map(function (OrderItem $item) use ($order): array {
            $book = $item->book;

            return [
                'title' => (string) ($book?->title ?? 'Book no longer available'),
                'author' => self::bookAuthor($book),
                'quantity' => (int) $item->quantity,
                'unit_price' => self::money((string) $item->unit_price, (string) $order->currency),
                'subtotal' => self::money((string) $item->subtotal, (string) $order->currency),
                'cover_url' => self::coverUrl($book),
            ];
        })->all();
    }

    private static function bookAuthor($book): string
    {
        $authors = $book?->authors ?? collect();

        $name = trim($authors->pluck('name')->filter()->implode(', '));

        return $name !== '' ? $name : 'E-Book';
    }

    /**
     * Covers are a progressive enhancement only: the receipt is fully readable
     * when the image is blocked, and no private ebook path is ever exposed —
     * this is the public cover path used by the storefront card.
     */
    private static function coverUrl($book): ?string
    {
        $cover = trim((string) ($book?->cover_image ?? ''));

        return $cover !== '' ? asset('storage/'.$cover) : null;
    }

    /**
     * The provider reference is the customer's proof of payment with the
     * mobile money operator. Never a secret, but also never the internal
     * idempotency key or webhook secret.
     */
    public static function paymentReference(?Payment $payment): ?string
    {
        $reference = trim((string) ($payment?->provider_reference ?? ''));

        return $reference !== '' ? $reference : null;
    }
}
