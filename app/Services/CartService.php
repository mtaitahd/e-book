<?php

namespace App\Services;

use App\Models\Book;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Session-based shopping cart.
 *
 * Only stores book ids in the session; no prices, titles or client
 * quantities are ever trusted from the browser. Quantities are always 1 and
 * a book can appear at most once per cart, so duplicate additions are merged.
 */
class CartService
{
    protected string $key = 'cart.items';

    /**
     * Distinct book ids currently in the cart.
     *
     * @return Collection<int, int>
     */
    public function ids(): Collection
    {
        return collect(session()->get($this->key, []))
            ->filter(fn ($id) => is_numeric($id))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Cart lines with the current database price for each book.
     *
     * Prices come from Book::salePrice(), so a book that has been made free
     * contributes 0.00 even if the cart was filled before that change.
     *
     * @return Collection<int, array{book: Book, unit_price: string, subtotal: string}>
     */
    public function lines(): Collection
    {
        $ids = $this->ids();

        $books = Book::with(['authors', 'categories'])
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');

        return $ids->map(function (int $id) use ($books) {
            $book = $books->get($id);

            if ($book === null) {
                return null;
            }

            $price = $book->salePrice();

            return [
                'book' => $book,
                'unit_price' => $price,
                'subtotal' => $price,
            ];
        })->filter()->values();
    }

    /**
     * Server-calculated cart total as a decimal string.
     */
    public function total(): string
    {
        $cents = $this->lines()->reduce(
            fn (int $carry, array $line) => $carry + Money::toCents($line['unit_price']),
            0
        );

        return Money::fromCents($cents);
    }

    public function count(): int
    {
        return $this->ids()->count();
    }

    public function isEmpty(): bool
    {
        return $this->ids()->isEmpty();
    }

    public function add(Book $book): void
    {
        if (! $book->isPublished()) {
            throw new \InvalidArgumentException('This book is not available for purchase.');
        }

        // There is nothing to pay for a free book, so it never enters the paid
        // checkout. It is claimed straight into the library instead.
        if ($book->isFree()) {
            throw new \InvalidArgumentException('This book is free. Add it to your library instead of the cart.');
        }

        if ($this->ids()->contains($book->id)) {
            return;
        }

        session()->push($this->key, $book->id);
    }

    public function remove(int $bookId): void
    {
        session()->put(
            $this->key,
            $this->ids()->reject(fn (int $id) => $id === $bookId)->values()->all()
        );
    }

    public function clear(): void
    {
        session()->forget($this->key);
    }
}