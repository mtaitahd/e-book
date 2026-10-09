<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();

            // NULL for PDF bookmarks, set for online (HTML) bookmarks.
            $table->foreignId('chapter_id')
                ->nullable()
                ->constrained('book_chapters')
                ->cascadeOnDelete();

            $table->unsignedInteger('page')->default(1);
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->string('label')->nullable();
            $table->timestamps();

            // A bookmark must be unique per entitlement *and* position. The
            // chapter id alone cannot express that because SQL treats NULLs as
            // distinct, which would let a PDF book collect the same page twice.
            // position_key is a deterministic string the model builds:
            // "c12:p3" for an online chapter, "p3" for a PDF page.
            $table->string('position_key', 40);
            $table->unique(['purchase_id', 'position_key']);
            $table->index(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_bookmarks');
    }
};
