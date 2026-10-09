<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a filterable, read-only list of all orders.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');

        if ($status !== null && ! in_array($status, Order::STATUSES, true)) {
            $status = null;
        }

        return $this->listOrders($status);
    }

    /**
     * Orders still awaiting payment. Its own page so the sidebar can link
     * directly at the queue that needs attention.
     */
    public function pending(): View
    {
        return $this->listOrders(Order::STATUS_PENDING);
    }

    /**
     * Orders that have been paid. Its own page for the same reason.
     */
    public function paid(): View
    {
        return $this->listOrders(Order::STATUS_PAID);
    }

    /**
     * Display a single order (read-only).
     */
    public function show(Order $order): View
    {
        $order->load(['user', 'items.book', 'payments', 'purchases.book']);

        return view('admin.orders.show', ['order' => $order]);
    }

    /**
     * Delete a single order. Only pending orders are ever removed: a paid order
     * owns purchases and reading history, so it stays for the customer.
     */
    public function destroy(Order $order): RedirectResponse
    {
        if (! $order->isPending()) {
            return redirect()->back()
                ->with('error', 'Only pending orders can be deleted.');
        }

        $number = $order->order_number;
        $order->delete();

        return redirect()->back()
            ->with('success', 'Order '.$number.' has been deleted.');
    }

    /**
     * Delete a batch of orders from the pending queue.
     *
     * Only rows that are actually still pending are deleted, so a paid order
     * that happened to be selected is left untouched. The database cascades
     * the order's items and payments.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->input('ids');

        if (! is_array($ids)) {
            return redirect()->back()
                ->with('error', 'Select at least one pending order to delete.');
        }

        $ids = array_values(array_unique(array_filter($ids, 'is_numeric')));

        if ($ids === []) {
            return redirect()->back()
                ->with('error', 'Select at least one pending order to delete.');
        }

        $deleted = Order::query()
            ->whereIn('id', $ids)
            ->where('status', Order::STATUS_PENDING)
            ->delete();

        if ($deleted === 0) {
            return redirect()->back()
                ->with('error', 'No pending orders were deleted.');
        }

        return redirect()->back()
            ->with('success', $deleted.' pending order'.($deleted === 1 ? '' : 's').' deleted.');
    }

    /**
     * The shared list, used by all three sales pages.
     */
    private function listOrders(?string $status): View
    {
        $orders = Order::query()
            ->with(['user', 'items'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            // Keep the dedicated pages' URLs when paging, otherwise moving to
            // page 2 would silently drop the /pending or /paid prefix.
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => Order::STATUSES,
            'currentStatus' => $status,
            'statusLinks' => $this->statusLinks(),
        ]);
    }

    /**
     * Where each status filter lives. Pending and paid have dedicated pages;
     * the rest fall back to a query string on the main list.
     *
     * @return array<string, string> keyed by status, plus 'all'
     */
    private function statusLinks(): array
    {
        $links = ['all' => route('admin.orders.index')];

        foreach (Order::STATUSES as $status) {
            $links[$status] = match ($status) {
                Order::STATUS_PENDING => route('admin.orders.pending'),
                Order::STATUS_PAID => route('admin.orders.paid'),
                default => route('admin.orders.index', ['status' => $status]),
            };
        }

        return $links;
    }
}
