<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Abliner\AblinerPaymentService;
use App\Services\PurchaseService;
use Illuminate\Contracts\View\View;

class OrderController extends Controller
{
    /**
     * Display the authenticated user's orders.
     */
    public function index(): View
    {
        $orders = auth()->user()->orders()
            ->with('items')
            ->latest()
            ->paginate(10);

        return view('account.orders.index', ['orders' => $orders]);
    }

    /**
     * Display a single order owned by the authenticated user.
     */
    public function show(Order $order, AblinerPaymentService $payments): View
    {
        $order = auth()->user()->orders()
            ->with(['items.book'])
            ->findOrFail($order->id);

        // Surface entitlements for already-confirmed paid orders (e.g. paid
        // before Stage 8 shipped). Idempotent.
        if ($order->isPaid()) {
            app(PurchaseService::class)->createFromPaidOrder($order);
        }

        // One-tap payment: the last number + network this customer paid with,
        // offered only while a new payment could actually start.
        $quickPay = $order->currency === config('shop.currency') && $payments->isAvailable()
            ? $this->quickPayDetails($order)
            : null;

        return view('account.orders.show', ['order' => $order, 'quickPay' => $quickPay]);
    }

    /**
     * The phone + network of the customer's most recent mobile payment, so an
     * unpaid order can be paid in one tap without reopening the dialog.
     *
     * Prefers the current order's own attempts, then the customer's last
     * mobile payment across all their orders. Older rows have no payment
     * phone of their own (it was pushed to the account number back then), so
     * those fall back to the account contact.
     *
     * @return array{phone: string, network: string}|null
     */
    private function quickPayDetails(Order $order): ?array
    {
        $payment = $order->payments()
            ->where('payment_type', Payment::TYPE_MOBILE)
            ->whereNotNull('channel_provider')
            ->latest('id')
            ->first();

        $payment ??= Payment::query()
            ->whereIn('order_id', auth()->user()->orders()->select('id'))
            ->where('payment_type', Payment::TYPE_MOBILE)
            ->whereNotNull('channel_provider')
            ->latest('id')
            ->first();

        if ($payment === null) {
            return null;
        }

        $phone = $payment->phone ?: auth()->user()->phone;

        if (blank($phone) || blank($payment->channel_provider)) {
            return null;
        }

        return [
            'phone' => (string) $phone,
            'network' => (string) $payment->channel_provider,
        ];
    }
}