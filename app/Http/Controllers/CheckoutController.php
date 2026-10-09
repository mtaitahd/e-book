<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Order;
use App\Services\CartService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    /**
     * Display the checkout confirmation view.
     */
    public function create(CartService $cart): View|RedirectResponse
    {
        $lines = $cart->lines();
        $total = $cart->total();

        if ($cart->isEmpty() || $lines->isEmpty()) {
            return redirect()->route('cart.show')
                ->with('error', 'Your cart is empty.');
        }

        return view('checkout.show', [
            'lines' => $lines,
            'total' => $total,
            'count' => $cart->count(),
        ]);
    }

    /**
     * Create an order from the current cart contents.
     *
     * Orders are created in a "pending" state; payment confirmation is a
     * later stage. Prices are snapshot from the database inside the
     * transaction so they can never be influenced by the client.
     */
    public function store(CartService $cart): RedirectResponse
    {
        $ids = $cart->ids();

        if ($ids->isEmpty()) {
            return redirect()->route('cart.show')
                ->with('error', 'Your cart is empty.');
        }

        try {
            $order = DB::transaction(function () use ($cart, $ids) {
                $books = Book::whereIn('id', $ids->all())
                    ->published()
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $missing = $ids->diff($books->keys());

                if ($missing->isNotEmpty()) {
                    foreach ($missing as $bookId) {
                        $cart->remove($bookId);
                    }

                    throw new \RuntimeException('Some books in your cart are no longer available for purchase.');
                }

                // A book that has been made free since it was added contributes
                // nothing, and there is no payment to take for it. Sending the
                // customer to a zero-amount payment prompt is worse than saying
                // so here, so the free book is taken back out of the cart.
                $free = $books->filter(fn (Book $book) => $book->isFree());

                if ($free->isNotEmpty()) {
                    foreach ($free as $book) {
                        $cart->remove($book->id);
                    }

                    throw new \RuntimeException('"'.$free->first()->title.'" is free and is no longer purchasable. '
                        .'Open the book to add it to your library instead.');
                }

                $subtotal = Money::fromCents(
                    $books->reduce(fn (int $carry, Book $book) => $carry + Money::toCents($book->salePrice()), 0)
                );

                if (Money::toCents($subtotal) <= 0) {
                    throw new \RuntimeException('There is nothing to pay for in your cart.');
                }

                $order = Order::create([
                    'user_id' => auth()->id(),
                    'order_number' => Order::generateNumber(),
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                    'currency' => config('shop.currency'),
                    'status' => Order::STATUS_PENDING,
                ]);

                $order->items()->createMany(
                    $books->map(fn (Book $book) => [
                        'book_id' => $book->id,
                        'quantity' => 1,
                        'unit_price' => $book->salePrice(),
                        'subtotal' => $book->salePrice(),
                    ])->values()->all()
                );

                $cart->clear();

                return $order;
            });
        } catch (\RuntimeException $e) {
            return redirect()->route('cart.show')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Checkout failed', ['exception' => $e->getMessage()]);

            return redirect()->route('cart.show')
                ->with('error', 'Something went wrong while placing your order. Please try again.');
        }

        return redirect()->route('account.orders.show', $order)
            ->with('success', "Order created successfully. Payment is pending. Your order reference is {$order->order_number}.");
    }
}