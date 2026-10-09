<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the explicit content type every book is classified by.
 *
 * The system has two different content models - a chapter-based E-Book and a
 * single uploaded PDF - and the type decides which admin workflow, which
 * validation rules and which reading experience apply. It is stored, never
 * guessed from whether a file happens to exist on disk.
 *
 * Existing rows are classified by the reader they actually open in today, so
 * nothing changes for a book that is already bought and read:
 *
 *  - `online` / `both` with chapters  -> the native chapter reader opens -> ebook
 *  - everything else (including `both` with no chapters) -> the PDF reader
 *    opens, or the book is an unfinished PDF draft                   -> pdf
 *
 * The chapter check only breaks the tie for the legacy `both` format, where
 * both readers are possible. No file is deleted, no chapter is deleted and
 * `book_format` is left exactly as it is, so downloads, orders, payments and
 * both readers keep working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('type', 10)->default('pdf')->after('book_format');
            $table->index('type');
        });

        $this->classifyExistingBooks();
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }

    /**
     * Give every pre-existing book the type that matches how it is read today.
     */
    private function classifyExistingBooks(): void
    {
        // One query instead of one per book: the set of books that own at
        // least one chapter, which is the only tie-breaker `both` needs.
        $withChapters = DB::table('book_chapters')
            ->distinct()
            ->pluck('book_id')
            ->flip()
            ->all();

        DB::table('books')->orderBy('id')->chunkById(200, function ($books) use ($withChapters) {
            foreach ($books as $book) {
                $format = strtolower(trim((string) $book->book_format));

                $type = match ($format) {
                    'online' => 'ebook',
                    'both' => isset($withChapters[$book->id]) ? 'ebook' : 'pdf',
                    default => 'pdf',
                };

                DB::table('books')->where('id', $book->id)->update(['type' => $type]);
            }
        });
    }
};
