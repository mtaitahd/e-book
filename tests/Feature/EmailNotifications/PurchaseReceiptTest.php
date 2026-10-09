<?php

namespace Tests\Feature\EmailNotifications;

use App\Mail\PurchaseReceiptMail;
use App\Models\Book;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;

class PurchaseReceiptTest extends EmailTestCase
{
    public function test_receipt_is_sent_once_for_a_genuinely_paid_order(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru', '15000.00');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);

        Mail::assertSent(PurchaseReceiptMail::class, 1);

        $log = $this->receiptFor($order);
        $this->assertNotNull($log);
        $this->assertTrue($log->isSent());
        $this->assertNotNull($log->sent_at);
        $this->assertSame(1, $log->attempts);
    }

    public function test_receipt_subject_identifies_the_order(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);

        $this->assertSame(
            'Payment successful — Order '.$order->order_number,
            $this->sentSubject(),
        );
    }

    public function test_receipt_states_order_payment_and_amount_details(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '27000.00', 'qty' => 1]]);
        $this->completedPayment($order, 'SNIP-REF-778811');

        $this->emails()->sendPurchaseReceiptFor($order);

        $html = $this->sentHtml();

        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString('SNIP-REF-778811', $html);
        $this->assertStringContainsString('PAID', $html);
        $this->assertStringContainsString('TZS', $html);
        $this->assertStringContainsString('TZS 27,000', $html);
        $this->assertStringContainsString('Amount paid', $html);
        $this->assertStringContainsString('Purchase date', $html);
    }

    public function test_receipt_lists_every_purchased_book_with_its_prices(): void
    {
        $first = $this->bookWithAuthor('Habari za Uhuru', '15000.00', 'Juma Kasege');
        $second = $this->bookWithAuthor('Utenzi wa Mvita', '12000.00', 'Asha Mwakalinga');

        $order = $this->paidOrder($this->customer(), [
            ['book' => $first, 'unit_price' => '15000.00', 'qty' => 1],
            ['book' => $second, 'unit_price' => '12000.00', 'qty' => 1],
        ]);
        $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);

        $html = $this->sentHtml();

        foreach (['Habari za Uhuru', 'Utenzi wa Mvita'] as $title) {
            $this->assertStringContainsString($title, $html);
        }

        foreach (['Juma Kasege', 'Asha Mwakalinga'] as $author) {
            $this->assertStringContainsString($author, $html);
        }

        $this->assertStringContainsString('TZS 15,000', $html);
        $this->assertStringContainsString('TZS 12,000', $html);
        $this->assertStringContainsString('TZS 27,000', $html);
        $this->assertStringContainsString('What you bought', $html);
    }

    public function test_receipt_multiplies_line_prices_by_quantity(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru', '15000.00');

        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 2]]);
        $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);

        $html = $this->sentHtml();

        // 2 x 15,000.00 = 30,000.00, and the receipt renders it once as the
        // unit price and once as the line subtotal.
        $this->assertStringContainsString('30,000', $html);
    }

    public function test_receipt_links_to_my_books_and_the_order(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);

        $html = $this->sentHtml();

        $this->assertStringContainsString(route('account.purchases.index'), $html);
        $this->assertStringContainsString(route('account.orders.show', $order), $html);
        $this->assertStringContainsString('View my books', $html);
    }

    public function test_receipt_is_addressed_to_the_customer_who_paid(): void
    {
        $customer = $this->customer(['email' => 'juma@example.test', 'name' => 'Juma Ali']);
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($customer, [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);

        Mail::assertSent(PurchaseReceiptMail::class, function (PurchaseReceiptMail $mail) {
            return $mail->hasTo('juma@example.test');
        });

        $this->assertStringContainsString('Juma Ali', $this->sentHtml());
    }

    public function test_receipt_inherits_the_sender_from_configuration(): void
    {
        config()->set('mail.from.address', 'no-reply@ebs.test');
        config()->set('mail.from.name', 'E-Book Store');

        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);

        // No sender is baked into the mailable, so the deployment decides it
        // through MAIL_FROM_ADDRESS / MAIL_FROM_NAME and no address is ever
        // hardcoded in the source.
        Mail::assertSent(PurchaseReceiptMail::class, function (PurchaseReceiptMail $mail) {
            return $mail->envelope()->from === null;
        });

        $this->assertSame('no-reply@ebs.test', config('mail.from.address'));
        $this->assertSame('E-Book Store', config('mail.from.name'));
    }

    public function test_receipt_is_not_sent_for_an_unpaid_order(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->makeOrder($this->customer(), '15000.00');
        $this->addItem($order, $book, '15000.00');
        $this->pendingPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order->fresh());

        Mail::assertNothingSent();
        $this->assertNull($this->receiptFor($order));
    }

    public function test_receipt_is_not_sent_for_a_free_order_that_has_no_payment(): void
    {
        // Free claims also run through the paid-order funnel but never take a
        // payment, so there is no payment to confirm in a receipt.
        $book = $this->publishedBook(['pricing_type' => Book::PRICING_FREE, 'price' => 0]);
        $order = $this->makeOrder($this->customer(), '0.00');
        $this->addItem($order, $book, '0.00');
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        $this->emails()->sendPurchaseReceiptFor($order->fresh());

        Mail::assertNothingSent();
        $this->assertNull($this->receiptFor($order));
    }

    public function test_receipt_carries_no_attachments(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);

        $attachments = null;

        Mail::assertSent(PurchaseReceiptMail::class, function (PurchaseReceiptMail $mail) use (&$attachments) {
            $attachments = $mail->attachments;

            return true;
        });

        $this->assertSame([], $attachments);
    }

    public function test_receipt_never_exposes_private_ebook_paths(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $this->assertSame('books/private-secret.pdf', $book->file_path);

        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);

        $html = $this->sentHtml();

        $this->assertStringNotContainsString('private-secret.pdf', $html);
        $this->assertStringNotContainsString('storage/app', $html);
    }

    public function test_receipt_never_exposes_credentials_or_provider_secrets(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $payment = $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);

        $html = $this->sentHtml();

        $this->assertStringNotContainsString('test-webhook-secret', $html);
        $this->assertStringNotContainsString('test-api-key', $html);
        $this->assertStringNotContainsString($payment->idempotency_key, $html);
    }

    public function test_receipt_uses_table_markup_so_it_renders_in_every_client(): void
    {
        $book = $this->bookWithAuthor('Habari za Uhuru');
        $order = $this->paidOrder($this->customer(), [['book' => $book, 'unit_price' => '15000.00', 'qty' => 1]]);
        $this->completedPayment($order);

        $this->emails()->sendPurchaseReceiptFor($order);

        $html = $this->sentHtml();

        $this->assertStringContainsString('<table', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick=', $html);
    }
}
