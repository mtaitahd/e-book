<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 10 reporting index.
 *
 * Every revenue figure in the new report is scoped by
 * `status = 'paid' AND paid_at BETWEEN ...`. The existing schema indexes
 * `orders.status` on its own and `orders.paid_at` not at all, so as soon as
 * the store has a meaningful order history the database would have to read
 * every row ever written to answer a "revenue in this month" question.
 *
 * This adds a composite `(status, paid_at)` index, which serves that exact
 * predicate: it is the leading-column lookup on status with the date range
 * resolved inside the index, so no separate single-column index on paid_at is
 * needed.
 *
 * Incremental and additive only: no data is rewritten and no column is
 * dropped. Removing it again is just a `dropIndex`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'paid_at'], 'orders_status_paid_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_paid_at_index');
        });
    }
};
