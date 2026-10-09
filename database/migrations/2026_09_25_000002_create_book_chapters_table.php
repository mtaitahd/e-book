<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('content');

            // Manual ordering. Auto-incrementing ids are the tie-breaker so
            // equal positions never produce an unstable reader order.
            $table->unsignedInteger('position')->default(0);

            // Free chapters are readable without a purchase, which powers the
            // store "Read a free sample" preview link.
            $table->boolean('is_free')->default(false);
            $table->timestamps();

            // A chapter slug only has to be unique inside its own book.
            $table->unique(['book_id', 'slug']);
            $table->index(['book_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_chapters');
    }
};
