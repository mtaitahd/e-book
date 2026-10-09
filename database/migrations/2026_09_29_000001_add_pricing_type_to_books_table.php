<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('pricing_type')->default('paid')->after('price');
        });

        // A book that was never given a real price was, in practice, already
        // being given away. Backfilling from `price` keeps those books free
        // instead of silently turning them into paid items worth nothing.
        DB::table('books')
            ->where('price', '<=', 0)
            ->update(['pricing_type' => 'free']);

        Schema::table('books', function (Blueprint $table) {
            $table->index('pricing_type');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['pricing_type']);
            $table->dropColumn('pricing_type');
        });
    }
};
