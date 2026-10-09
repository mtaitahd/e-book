<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookChapter;
use App\Models\Purchase;
use App\Models\ReadingBookmark;
use App\Models\ReadingProgress;
use App\Services\HtmlSanitizer;
use App\Services\PurchaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * The native (HTML) e-book reader.
 *
 * Every response here is gated the same way the PDF reader is: the route
 * requires authentication, PurchasePolicy enforces that the purchase belongs
 * to the current user, and the order must be genuinely paid. Chapter content is
 * only ever served for a chapter that belongs to the purchased book, and the
 * stored HTML was already reduced to the sanitizer's allowlist when the admin
 * saved it.
 *
 * No CSRF exemption is added for any of these routes.
 */
class OnlineReadingController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * Render the reader shell. The chapter HTML itself is fetched separately
     * so the page stays small and pagination can be recalculated whenever the
     * font, theme or viewport changes.
     */
    public function read(Purchase $purchase): View|RedirectResponse
    {
        $this->authorize('readOnline', $purchase);

        $this->purchases->createFromPaidOrder($purchase->order);

        if (! $purchase->order->isPaid()) {
            abort(403, 'This purchase is not associated with a paid order.');
        }

        $book = $purchase->book;

        if (! $book->hasOnlineReading()) {
            return redirect()->route('account.purchases.show', $purchase)
                ->with('error', 'This e-book does not have online chapters yet.');
        }

        $purchase->loadMissing('book.authors');

        return view('account.purchases.online-reader', [
            'purchase' => $purchase,
        ]);
    }

    /**
     * Everything the reader needs to boot in one request: chapter list,
     * resume point and saved bookmarks.
     */
    public function manifest(Purchase $purchase): JsonResponse
    {
        $this->authorize('readOnline', $purchase);

        $this->purchases->createFromPaidOrder($purchase->order);

        if (! $purchase->order->isPaid()) {
            abort(403, 'This purchase is not associated with a paid order.');
        }

        $book = $purchase->book;

        if (! $book->hasOnlineReading()) {
            abort(404, 'This e-book does not have online chapters yet.');
        }

        $chapters = $book->chapters()->get(['id', 'title', 'position', 'is_free']);

        $progress = ReadingProgress::where('purchase_id', $purchase->id)->first();

        return response()->json([
            'book' => [
                'id' => $book->id,
                'title' => $book->title,
                'format' => $book->format(),
                'authors' => $book->authors->pluck('name'),
                'has_pdf' => $book->hasPdfFile(),
            ],
            'chapters' => $chapters->map(fn (BookChapter $chapter) => [
                'id' => $chapter->id,
                'title' => $chapter->title,
                'position' => $chapter->position,
                'is_free' => (bool) $chapter->is_free,
            ])->values(),
            'progress' => $progress === null ? null : [
                'chapter_id' => $progress->chapter_id,
                'page' => (int) $progress->current_page,
                'percent' => (float) $progress->progress_percent,
            ],
            'bookmarks' => $this->bookmarksFor($purchase),
            'urls' => [
                'chapter' => route('account.purchases.online.chapter', ['purchase' => $purchase, 'chapter' => '__CHAPTER__']),
                'progress' => route('account.purchases.online.progress', $purchase),
                'bookmark' => route('account.purchases.online.bookmarks.store', $purchase),
            ],
        ]);
    }

    /**
     * Serve one chapter's sanitized HTML.
     *
     * The chapter must belong to the purchased book, so a valid id from a
     * different book cannot be read through somebody else's purchase.
     */
    public function chapter(Purchase $purchase, int $chapter): JsonResponse
    {
        $this->authorize('readOnline', $purchase);

        $this->purchases->createFromPaidOrder($purchase->order);

        if (! $purchase->order->isPaid()) {
            abort(403, 'This purchase is not associated with a paid order.');
        }

        $book = $purchase->book;

        $model = BookChapter::query()
            ->where('book_id', $book->id)
            ->whereKey($chapter)
            ->first();

        if ($model === null) {
            abort(404, 'That chapter does not exist in this e-book.');
        }

        return response()->json([
            'id' => $model->id,
            'title' => $model->title,
            'position' => (int) $model->position,
            // Sanitised again on the way out. The admin request already cleaned
            // this on the way in; re-running the allowlist is cheap for a
            // chapter-sized document and means a row inserted by a seeder or a
            // console can never reach the reader with raw markup.
            'html' => $this->sanitizer->sanitize($model->content),
        ]);
    }

    /**
     * Store the reader's resume point.
     */
    public function progress(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('readOnline', $purchase);

        $this->purchases->createFromPaidOrder($purchase->order);

        if (! $purchase->order->isPaid()) {
            abort(403, 'This purchase is not associated with a paid order.');
        }

        $validated = $request->validate([
            'chapter_id' => ['nullable', 'integer', $this->chapterRule($purchase)],
            'page' => ['required', 'integer', 'min:1', 'max:100000'],
            'percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        ReadingProgress::updateOrCreate(
            ['purchase_id' => $purchase->id],
            [
                'user_id' => $purchase->user_id,
                'book_id' => $purchase->book_id,
                'chapter_id' => $validated['chapter_id'] ?? null,
                'current_page' => (int) $validated['page'],
                'progress_percent' => round((float) ($validated['percent'] ?? 0), 2),
            ],
        );

        return response()->json(['saved' => true]);
    }

    /**
     * Add a bookmark, or return the existing one if that exact position was
     * already saved.
     */
    public function storeBookmark(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorize('bookmark', $purchase);

        $this->purchases->createFromPaidOrder($purchase->order);

        if (! $purchase->order->isPaid()) {
            abort(403, 'This purchase is not associated with a paid order.');
        }

        $validated = $request->validate([
            'chapter_id' => ['nullable', 'integer', $this->chapterRule($purchase)],
            'page' => ['required', 'integer', 'min:1', 'max:100000'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        $chapterId = $validated['chapter_id'] ?? null;
        $page = (int) $validated['page'];
        $positionKey = ReadingBookmark::positionKeyFor($chapterId === null ? null : (int) $chapterId, $page);

        $bookmark = ReadingBookmark::updateOrCreate(
            [
                'purchase_id' => $purchase->id,
                'position_key' => $positionKey,
            ],
            [
                'user_id' => $purchase->user_id,
                'book_id' => $purchase->book_id,
                'chapter_id' => $chapterId,
                'page' => $page,
                'label' => $validated['label'] ?? null,
            ],
        );

        return response()->json([
            'saved' => true,
            'bookmark' => $this->presentBookmark($bookmark),
            'bookmarks' => $this->bookmarksFor($purchase),
        ]);
    }

    /**
     * Remove a bookmark belonging to this purchase.
     */
    public function destroyBookmark(Purchase $purchase, ReadingBookmark $bookmark): JsonResponse
    {
        $this->authorize('bookmark', $purchase);

        // Ownership of the *purchase* is not enough: the bookmark id must also
        // belong to it, or a customer could delete another customer's row.
        if ((int) $bookmark->purchase_id !== (int) $purchase->id) {
            abort(404, 'That bookmark does not exist.');
        }

        $this->purchases->createFromPaidOrder($purchase->order);

        if (! $purchase->order->isPaid()) {
            abort(403, 'This purchase is not associated with a paid order.');
        }

        $bookmark->delete();

        return response()->json([
            'deleted' => true,
            'bookmarks' => $this->bookmarksFor($purchase),
        ]);
    }

    /**
     * A chapter id is only valid when it belongs to the purchased book.
     */
    private function chapterRule(Purchase $purchase): Exists
    {
        return Rule::exists('book_chapters', 'id')->where(
            fn ($query) => $query->where('book_id', $purchase->book_id)
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bookmarksFor(Purchase $purchase): array
    {
        return ReadingBookmark::where('purchase_id', $purchase->id)
            ->orderBy('chapter_id')
            ->orderBy('page')
            ->get()
            ->map(fn (ReadingBookmark $bookmark) => $this->presentBookmark($bookmark))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentBookmark(ReadingBookmark $bookmark): array
    {
        return [
            'id' => (int) $bookmark->id,
            'chapter_id' => $bookmark->chapter_id === null ? null : (int) $bookmark->chapter_id,
            'page' => (int) $bookmark->page,
            'percent' => (float) $bookmark->progress_percent,
            'label' => $bookmark->label,
        ];
    }
}
