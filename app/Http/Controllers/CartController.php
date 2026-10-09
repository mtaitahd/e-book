<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Services\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Display the shopping cart.
     */
    public function show(CartService $cart): View
    {
        return view('cart.show', [
            'lines' => $cart->lines(),
            'total' => $cart->total(),
            'count' => $cart->count(),
        ]);
    }

    /**
     * Add a published, paid book to the cart.
     *
     * A free book is not purchasable, so the service refuses it and the
     * customer is told why instead of being sent down a checkout they cannot
     * complete.
     */
    public function add(Request $request, CartService $cart): RedirectResponse
    {
        $book = Book::published()->findOrFail($request->integer('book_id'));

        try {
            $cart->add($book);
        } catch (\InvalidArgumentException $exception) {
            return redirect()->route('books.show', $book)
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('books.show', $book)
            ->with('success', 'Book added to your cart.');
    }

    /**
     * Remove a book from the cart.
     */
    public function remove(Request $request, CartService $cart): RedirectResponse
    {
        $cart->remove($request->integer('book_id'));

        return redirect()->route('cart.show')
            ->with('success', 'Book removed from your cart.');
    }

    /**
     * Empty the cart.
     */
    public function clear(CartService $cart): RedirectResponse
    {
        $cart->clear();

        return redirect()->route('books.index')
            ->with('success', 'Your cart has been cleared.');
    }
}