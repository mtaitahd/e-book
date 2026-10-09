<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('TZS');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();

            // The same paid order item can never yield duplicate entitlements —
            // this is the final safety net against racing/double webhooks.
            $table->unique('order_item_id');
            $table->unique(['user_id', 'order_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};