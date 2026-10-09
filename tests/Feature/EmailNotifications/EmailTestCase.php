<?php

namespace Tests\Feature\EmailNotifications;

use App\Models\Author;
use App\Models\Book;
use App\Models\EmailNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CustomerEmailService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Purchase\PurchaseTestCase;

/**
 * Base for email content, secrecy and ledger tests.
 *
 * These exercise CustomerEmailService directly, because the guarantee under
 * test is what the mail says and how it is recorded - not when the deferred
 * trigger runs. The after-commit trigger itself is covered separately in
 * AfterCommit\EmailTriggerTest, which needs real transaction commits.
 */
abstract class EmailTestCase extends PurchaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    protected function emails(): CustomerEmailService
    {
        return app(CustomerEmailService::class);
    }

    /**
     * A published book with one author, priced explicitly so the receipt's
     * arithmetic is deterministic.
     */
    protected function bookWithAuthor(string $title, string $price = '15000.00', ?string $authorName = 'Juma Kasege'): Book
    {
        $book = $this->publishedBook(['title' => $title, 'price' => $price, 'file_path' => 'books/private-secret.pdf']);

        $book->authors()->attach(Author::factory()->create(['name' => $authorName])->id);

        return $book->fresh();
    }

    protected function completedPayment(Order $order, string $reference = 'SNIP-RECEIPT-001'): Payment
    {
        // Reuse a payment the test already opened, mirroring the real flow
        // where Snippe completes the payment that was initiated at checkout.
        $payment = $order->payments()->where('status', Payment::STATUS_PENDING)->latest('id')->first()
            ?? $this->pendingPayment($order, $reference);

        $payment->update([
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => now(),
        ]);

        return $payment->fresh();
    }

    protected function receiptFor(Order $order): ?EmailNotification
    {
        return EmailNotification::query()
            ->where('order_id', $order->id)
            ->where('type', EmailNotification::TYPE_PURCHASE_RECEIPT)
            ->first();
    }

    /**
     * Rendered HTML of the single sent message, for content assertions.
     */
    protected function sentHtml(): string
    {
        $mailable = null;

        Mail::assertSent(function (Mailable $sent) use (&$mailable) {
            $mailable = $sent;

            return true;
        });

        return $mailable->render();
    }

    protected function sentSubject(): string
    {
        $subject = null;

        Mail::assertSent(function (Mailable $sent) use (&$subject) {
            $subject = $sent->envelope()->subject;

            return true;
        });

        return (string) $subject;
    }
}
