<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns the Abliner collection flow needs on an existing `payments` row.
 *
 *  customer_reference  What WE sent as `reference`. The provider echoes it back
 *                       as `customer_reference` on the synchronous response and
 *                       on every webhook, which gives us a second, order-scoped
 *                       way to match an incoming event when the short network
 *                       reference is not echoed.
 *
 *  control_number      A ClickPesa BillPay control number. Stored separately
 *                       from external_reference because it is the number the
 *                       CUSTOMER dials, and it has to be shown to them.
 *
 *  payment_url         The hosted Abliner card page. A card payment leaves our
 *                       site, so the URL has to survive on the row: a customer
 *                       who closed the tab needs a way back in.
 *
 * Both lookup columns are indexed because both are matched on during webhook
 * reconciliation, which is the hot path for every payment that settles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'customer_reference')) {
                $table->string('customer_reference')->nullable()->after('external_reference');
            }

            if (! Schema::hasColumn('payments', 'control_number')) {
                $table->string('control_number')->nullable()->after('customer_reference');
            }

            if (! Schema::hasColumn('payments', 'payment_url')) {
                $table->text('payment_url')->nullable()->after('control_number');
            }
        });

        // external_reference predates this migration, so it is only indexed when
        // it is actually there.
        if (Schema::hasColumn('payments', 'external_reference')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index('external_reference', 'payments_external_reference_index');
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->index('customer_reference', 'payments_customer_reference_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            foreach (['payments_customer_reference_index', 'payments_external_reference_index'] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable) {
                    // The index was never created, so there is nothing to undo.
                }
            }

            foreach (['payment_url', 'control_number', 'customer_reference'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};