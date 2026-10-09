<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RendersFormForModal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookChapterRequest;
use App\Models\Book;
use App\Models\BookChapter;
use App\Services\HtmlSanitizer;
use App\Support\Slugs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin management of native-reader chapters.
 *
 * Chapter HTML is sanitized by {@see BookChapterRequest} before it is stored,
 * because the reader renders it unescaped. Nothing here trusts a raw title or
 * a client-supplied slug.
 */
class BookChapterController extends Controller
{
    use RendersFormForModal;

    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    /**
     * List a book's chapters in reading order.
     */
    public function index(Book $book): View
    {
        return view('admin.books.chapters.index', [
            'book' => $book,
            'chapters' => $book->chapters()->get(),
        ]);
    }

    public function create(Request $request, Book $book): View
    {
        return $this->renderForm($request, 'admin.books.chapters.create', 'admin.books.chapters._form_page', [
            'book' => $book,
            'chapter' => new BookChapter(['position' => $this->nextPosition($book)]),
        ]);
    }

    public function store(BookChapterRequest $request, Book $book): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $book->chapters()->create([
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($book, $validated),
            // Already sanitized by the form request; sanitizing again is cheap
            // and keeps this invariant true even if the request is bypassed.
            'content' => $this->sanitizer->sanitize($validated['content']),
            'position' => $validated['position'] ?? $this->nextPosition($book),
            'is_free' => (bool) ($validated['is_free'] ?? false),
        ]);

        session()->flash('success', 'Chapter created successfully.');

        return $request->expectsJson()
            ? response()->json(['message' => 'Chapter created successfully.'])
            : redirect()->route('admin.books.chapters.index', $book);
    }

    public function edit(Request $request, Book $book, BookChapter $chapter): View
    {
        $this->assertBelongsTo($book, $chapter);

        return $this->renderForm($request, 'admin.books.chapters.edit', 'admin.books.chapters._form_page', [
            'book' => $book,
            'chapter' => $chapter,
        ]);
    }

    public function update(BookChapterRequest $request, Book $book, BookChapter $chapter): RedirectResponse|JsonResponse
    {
        $this->assertBelongsTo($book, $chapter);

        $validated = $request->validated();

        $chapter->update([
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($book, $validated, $chapter->id),
            'content' => $this->sanitizer->sanitize($validated['content']),
            'position' => $validated['position'] ?? $chapter->position,
            'is_free' => (bool) ($validated['is_free'] ?? false),
        ]);

        session()->flash('success', 'Chapter updated successfully.');

        return $request->expectsJson()
            ? response()->json(['message' => 'Chapter updated successfully.'])
            : redirect()->route('admin.books.chapters.index', $book);
    }

    public function destroy(Book $book, BookChapter $chapter): RedirectResponse
    {
        $this->assertBelongsTo($book, $chapter);

        $chapter->delete();

        session()->flash('success', 'Chapter deleted successfully.');

        return redirect()->route('admin.books.chapters.index', $book);
    }

    /**
     * Persist a new reading order for the whole book in one transaction.
     */
    public function reorder(Request $request, Book $book): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:book_chapters,id'],
        ]);

        $owned = $book->chapters()->pluck('id')->all();
        $submitted = array_map('intval', $validated['order']);

        // Never let a crafted request reorder somebody else's chapter, and
        // never let a partial list silently drop the rest of the book.
        if (count($submitted) !== count($owned) || array_diff($submitted, $owned) !== []) {
            return back()->with('error', 'That chapter order is no longer valid. Please reload and try again.');
        }

        DB::transaction(function () use ($book, $submitted): void {
            foreach ($submitted as $index => $chapterId) {
                $book->chapters()->whereKey($chapterId)->update(['position' => $index + 1]);
            }
        });

        session()->flash('success', 'Chapter order updated.');

        return redirect()->route('admin.books.chapters.index', $book);
    }

    /**
     * A nested route like /books/{book}/chapters/{chapter} must never let an
     * admin edit a chapter that belongs to a different book.
     */
    private function assertBelongsTo(Book $book, BookChapter $chapter): void
    {
        abort_if((int) $chapter->book_id !== (int) $book->id, 404);
    }

    private function uniqueSlug(Book $book, array $validated, ?int $ignoreId = null): string
    {
        $requested = $validated['slug'] ?? null;

        $base = is_string($requested) && trim($requested) !== ''
            ? $requested
            : $validated['title'];

        return Slugs::uniqueScoped(
            $base,
            BookChapter::class,
            fn ($query) => $query->where('book_id', $book->id),
            $ignoreId,
        );
    }

    private function nextPosition(Book $book): int
    {
        return ((int) $book->chapters()->max('position')) + 1;
    }
}
