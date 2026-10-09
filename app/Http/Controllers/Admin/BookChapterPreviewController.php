<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookChapter;
use App\Services\HtmlSanitizer;
use Illuminate\Contracts\View\View;

/**
 * Renders a chapter exactly as the reader will show it, so an admin can check
 * their formatting without a paid order.
 *
 * This deliberately sits behind the admin middleware, so "preview" can never be
 * used as a back door to paid content: only a signed-in administrator reaches
 * it. The stored markup is run through the sanitizer again on the way out, so
 * the preview shows the reader's real, sanitized result rather than whatever
 * happens to be in the column.
 */
class BookChapterPreviewController extends Controller
{
    public function __construct(
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    public function show(Book $book, BookChapter $chapter): View
    {
        abort_if((int) $chapter->book_id !== (int) $book->id, 404);

        return view('admin.books.chapters.preview', [
            'book' => $book,
            'chapter' => $chapter,
            'html' => $this->sanitizer->sanitize($chapter->content),
        ]);
    }
}
