<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RendersFormForModal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookRequest;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Support\Slugs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BookController extends Controller
{
    use RendersFormForModal;

    /**
     * List books with pagination and an optional status filter.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');

        $books = Book::query()
            ->with(['authors', 'categories'])
            ->withCount('chapters')
            ->when(in_array($status, Book::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.books.index', [
            'books' => $books,
            'currentStatus' => in_array($status, Book::STATUSES, true) ? $status : null,
        ]);
    }

    /**
     * Show the create-book form.
     */
    public function create(Request $request): View
    {
        return $this->renderForm($request, 'admin.books.create', 'admin.books._form_page', [
            'book' => null,
            'authors' => Author::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'stats' => $this->catalogueStats(),
        ]);
    }

    /**
     * Store a newly created book.
     *
     * The submitted book type decides the rest of the workflow: an E-Book is
     * chapter-based and is sent straight to chapter management, while a PDF
     * book must arrive with its file and lands back on the form where that
     * file can be viewed or replaced. A request with no type keeps the
     * historical behaviour, including its redirect.
     */
    public function store(BookRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $coverPath = $request->hasFile('cover_image')
            ? $request->file('cover_image')->store('covers', 'public')
            : null;

        $type = $request->submittedType();

        $filePath = null;
        $fileType = null;
        if ($request->hasFile('ebook_file')) {
            $file = $request->file('ebook_file');
            $filePath = $file->store('ebooks', 'local');
            $fileType = $request->input('file_type')
                ?? strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        }

        // The type pins the file's meaning: a PDF book's file is always a
        // PDF, and an E-Book never carries one at all (validation already
        // refuses an upload for it - this keeps the stored row honest too).
        if ($type === Book::TYPE_PDF) {
            $fileType = $filePath === null ? null : 'pdf';
        } elseif ($type === Book::TYPE_EBOOK) {
            $filePath = null;
            $fileType = null;
        }

        $status = $validated['status'] ?? Book::STATUS_DRAFT;
        $publishedAt = $request->date('published_at');
        if ($status === Book::STATUS_PUBLISHED && $publishedAt === null) {
            $publishedAt = now();
        }

        $bookFormat = $request->resolvedFormat();

        // A native book with no chapters has nothing to read, so it cannot be
        // published yet. A book being created has no chapters by definition.
        if ($status === Book::STATUS_PUBLISHED && $this->publishBlockedByMissingChapters($bookFormat, null)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'errors' => ['book_format' => ['Add at least one chapter before publishing an online book.']],
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', 'Add at least one chapter before publishing an online book.');
        }

        $data = [
            'title' => $validated['title'],
            'slug' => Slugs::unique($validated['title'], Book::class),
            'description' => $validated['description'] ?? null,
            'price' => $request->resolvedPrice(),
            'pricing_type' => $request->resolvedPricingType(),
            'cover_image' => $coverPath,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'book_format' => $bookFormat,
            'publisher' => $validated['publisher'] ?? null,
            'published_at' => $publishedAt,
            'status' => $status,
        ];

        // A caller that named no type gets the one its format classifies as,
        // decided by the model rather than forced here.
        if ($type !== null) {
            $data['type'] = $type;
        }

        $book = Book::create($data);

        $book->authors()->sync($validated['author_ids']);
        $book->categories()->sync($validated['category_ids'] ?? []);

        // Say whether the cover actually landed, so an administrator is never
        // left guessing whether their upload was saved.
        $message = 'Book created successfully.'
            .($coverPath ? ' Cover image uploaded.' : '');

        // Each type continues where its own workflow starts: chapters for an
        // E-Book, the file controls for a PDF. Everything else stays on the
        // list, exactly as before.
        $destination = route('admin.books.index');

        if ($type === Book::TYPE_EBOOK) {
            $destination = route('admin.books.chapters.index', $book);
            $message = 'Book created successfully. Add its first chapter to start building the e-book.'
                .($coverPath ? ' Cover image uploaded.' : '');
        } elseif ($type === Book::TYPE_PDF) {
            $destination = route('admin.books.edit', $book);
            $message = 'Book created successfully. Its PDF is ready for secure reading and download.'
                .($coverPath ? ' Cover image uploaded.' : '');
        }

        session()->flash('success', $message);

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'redirect' => $destination])
            : redirect()->to($destination);
    }


    /**
     * Show the edit-book form.
     */
    public function edit(Request $request, Book $book): View
    {
        $book->load(['authors', 'categories']);

        return $this->renderForm($request, 'admin.books.edit', 'admin.books._form_page', [
            'book' => $book,
            'authors' => Author::orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'stats' => $this->catalogueStats($book),
        ]);
    }

    /**
     * Update the given book.
     *
     * Switching the type is allowed only through BookRequest's own safety
     * rails (no owners, explicit confirmation), and it never deletes
     * anything: the chapters and the uploaded file both stay where they are,
     * so a book can be switched back without losing its content.
     */
    public function update(BookRequest $request, Book $book): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $type = $request->submittedType();

        $coverPath = $book->cover_image;
        $coverUploaded = false;
        if ($request->hasFile('cover_image')) {
            if ($coverPath && Storage::disk('public')->exists($coverPath)) {
                Storage::disk('public')->delete($coverPath);
            }
            $coverPath = $request->file('cover_image')->store('covers', 'public');
            $coverUploaded = true;
        }

        $filePath = $book->file_path;
        $fileType = $validated['file_type'] ?? $book->file_type;
        if ($request->hasFile('ebook_file')) {
            if ($filePath && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }
            $file = $request->file('ebook_file');
            $filePath = $file->store('ebooks', 'local');
            $fileType = $request->input('file_type') ?? strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        }

        if ($type === Book::TYPE_PDF && $filePath !== null && $filePath !== '') {
            $fileType = 'pdf';
        }

        $slug = $book->slug;
        if ($validated['title'] !== $book->title) {
            $slug = Slugs::unique($validated['title'], Book::class, $book->id);
        }

        $status = $validated['status'];
        $publishedAt = $request->date('published_at');
        if ($status === Book::STATUS_PUBLISHED && $publishedAt === null) {
            $publishedAt = now();
        }

        $bookFormat = $request->resolvedFormat();

        if ($status === Book::STATUS_PUBLISHED && $this->publishBlockedByMissingChapters($bookFormat, $book)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'errors' => ['book_format' => ['Add at least one chapter before publishing an online book.']],
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', 'Add at least one chapter before publishing an online book.');
        }

        $data = [
            'title' => $validated['title'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'price' => $request->resolvedPrice(),
            'pricing_type' => $request->resolvedPricingType(),
            'cover_image' => $coverPath,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'book_format' => $bookFormat,
            'publisher' => $validated['publisher'] ?? null,
            'published_at' => $publishedAt,
            'status' => $status,
        ];

        if ($type !== null) {
            $data['type'] = $type;
        }

        $book->update($data);

        $book->authors()->sync($validated['author_ids']);
        $book->categories()->sync($validated['category_ids'] ?? []);

        session()->flash('success', 'Book updated successfully.'
            .($coverUploaded ? ' Cover image replaced.' : ''));

        return $request->expectsJson()
            ? response()->json([
                'message' => 'Book updated successfully.'.($coverUploaded ? ' Cover image replaced.' : ''),
                'redirect' => route('admin.books.index'),
            ])
            : redirect()->route('admin.books.index');
    }


    /**
     * Archive a book. Books are never hard-deleted so future order and
     * purchase history records can always resolve the originating title.
     */
    public function destroy(Book $book): RedirectResponse
    {
        $book->update(['status' => Book::STATUS_ARCHIVED]);

        session()->flash('success', 'Book archived successfully.');

        return redirect()->route('admin.books.index');
    }

    /**
     * Permanently delete a book for real.
     *
     * Only books with no order or purchase history can be removed this way:
     * the `order_items` and `purchases` rows hold the book_id under RESTRICT
     * foreign keys, because a paid order's history must always resolve the
     * item it sold. A sold book is refused here — an administrator can only
     * archive it, which keeps customer libraries and sales records intact.
     *
     * Everything else is cleaned up: chapters, reading progress, bookmarks
     * and author/category links cascade with the row, the upload/cover files
     * are removed from disk, and download logs have their book reference
     * nulled by the database.
     */
    public function forceDestroy(Book $book): RedirectResponse
    {
        $ordered = $book->orderItems()->count();
        $owned = $book->purchases()->count();

        if ($ordered > 0 || $owned > 0) {
            session()->flash('error', 'This book cannot be permanently deleted because it has '
                .$owned.' purchase'.($owned === 1 ? '' : 's')
                .'. Archive it instead to keep order history intact.');

            return redirect()->route('admin.books.index');
        }

        $book->authors()->detach();
        $book->categories()->detach();

        if ($book->file_path && Storage::disk('local')->exists($book->file_path)) {
            Storage::disk('local')->delete($book->file_path);
        }

        if ($book->cover_image && Storage::disk('public')->exists($book->cover_image)) {
            Storage::disk('public')->delete($book->cover_image);
        }

        $book->chapters()->delete();
        $book->delete();

        session()->flash('success', 'Book permanently deleted.');

        return redirect()->route('admin.books.index');
    }

    /**
     * An online (or dual-format) book needs at least one chapter before it can
     * be published, otherwise a customer would buy a book the reader cannot
     * open. A null book means "not created yet", which has no chapters.
     */
    private function publishBlockedByMissingChapters(string $format, ?Book $book): bool
    {
        if (! in_array($format, [Book::FORMAT_ONLINE, Book::FORMAT_BOTH], true)) {
            return false;
        }

        return $book === null || ! $book->chapters()->exists();
    }

    /**
     * How many customers already own this book, shown above the form so the
     * admin can see the reach of a change before making it.
     */
    private function catalogueStats(?Book $book = null): array
    {
        return [
            'owners' => $book === null ? 0 : $book->purchases()->count(),
        ];
    }
}
