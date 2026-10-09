<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookChapter;
use App\Services\FreeBookService;
use App\Services\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookController extends Controller
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * List the publicly available books, optionally filtered by a search term.
     */
    public function index(Request $request): View
    {
        $query = Book::published()->with(['authors', 'categories']);

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('authors', fn ($author) => $author->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('categories', fn ($category) => $category->where('name', 'like', "%{$search}%"));
            });
        }

        if ($format = strtolower(trim((string) $request->query('format')))) {
            if (in_array($format, ['pdf', 'epub', 'mobi'], true)) {
                $query->where('file_type', $format);
            }
        }

        $books = $query
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('books.index', compact('books'));
    }

    /**
     * Display a single published book by slug.
     */
    public function show(Book $book): View
    {
        abort_unless($book->isPublished(), 404);

        $book->load(['authors', 'categories']);

        return view('books.show', compact('book'));
    }

    /**
     * Give a free book to the signed-in customer.
     *
     * Free books never go through the cart or a payment: there is nothing to
     * pay. Claiming one records a zero-amount order and the purchase that every
     * reader and download endpoint authorises against, so the book lands in the
     * customer's library immediately.
     */
    public function claimFree(Request $request, Book $book, FreeBookService $freeBooks): RedirectResponse
    {
        abort_unless($book->isPublished(), 404);
        abort_unless($book->isFree(), 404);

        $purchase = $freeBooks->claim($book, $request->user());

        return redirect()->route('account.purchases.show', $purchase)
            ->with('success', 'Added to your library. Enjoy!');
    }

    /**
     * Public free preview of a single chapter.
     *
     * This is the one place chapter text is served without a purchase, so it is
     * deliberately narrow: the book must be published, the chapter must belong to
     * that book, and the chapter must have been flagged is_free by an admin. Any
     * other chapter is a 404 rather than a "not allowed" page, so the URL space
     * gives nothing away.
     */
    public function previewChapter(Book $book, BookChapter $chapter): View
    {
        abort_unless($book->isPublished(), 404);
        abort_unless($book->isOnlineFormat(), 404);
        abort_unless((int) $chapter->book_id === (int) $book->id, 404);
        abort_unless($chapter->is_free, 404);

        return view('books.preview', [
            'book' => $book->load('authors'),
            'chapter' => $chapter,
            'html' => $this->sanitizer->sanitize($chapter->content),
        ]);
    }

    /**
     * JSON feed of the newest published books for the storefront header menu.
     */
    public function trending(): JsonResponse
    {
        $books = Book::published()
            ->with('authors')
            ->latest('published_at')
            ->take(5)
            ->get();

        return response()->json([
            'books' => $books->map(fn (Book $book) => [
                'title' => $book->title,
                'url' => route('books.show', $book),
                'cover' => $book->cover_image ? asset('storage/'.$book->cover_image) : null,
                'authors' => $book->authors->pluck('name')->join(', '),
            ]),
        ]);
    }
}
