<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // "pdf" is the default so every pre-existing book keeps its current
            // behaviour without a backfill: a book that already has a PDF file
            // continues to read through PDF.js and download as before.
            $table->string('book_format', 20)->default('pdf')->after('file_type');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('book_format');
        });
    }
};
