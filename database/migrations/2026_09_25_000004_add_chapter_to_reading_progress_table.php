<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_progress', function (Blueprint $table) {
            // The existing table stores exactly one resume point per
            // entitlement (unique on purchase_id), so the native reader extends
            // that same row instead of creating a second progress concept.
            //
            // chapter_id is NULL for PDF books, which keeps every existing row
            // valid and keeps the existing PDF.js progress flow untouched.
            $table->foreignId('chapter_id')
                ->nullable()
                ->after('book_id')
                ->constrained('book_chapters')
                ->nullOnDelete();

            // How far through the chapter the reader had got, 0.00 - 100.00.
            // Used to restore the scroll position for reflowed/paginated HTML.
            $table->decimal('progress_percent', 5, 2)->default(0)->after('current_page');
        });
    }

    public function down(): void
    {
        Schema::table('reading_progress', function (Blueprint $table) {
            $table->dropForeign(['chapter_id']);
            $table->dropColumn(['chapter_id', 'progress_percent']);
        });
    }
};
