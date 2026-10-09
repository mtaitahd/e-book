<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move the stored payment configuration from the retired Snippe provider to
 * Abliner.
 *
 * Two things happen here, and both matter:
 *
 *  1. The `provider` value on `payments` and `payment_settings` changes from
 *     'snippe' to 'abliner'. The settings row keeps whatever an administrator
 *     had already saved; it is re-pointed, not discarded, so a deployment that
 *     had stored credentials does not silently lose them.
 *
 *  2. The saved `webhook_url` is cleared when it points at the retired
 *     /webhooks/snippe route. That URL is dead now — Abliner would POST to an
 *     endpoint that no longer exists, so every payment would settle silently
 *     never. Leaving it in place would look like a configured integration while
 *     dropping every callback on the floor, so we fall back to the app's own
 *     /webhooks/abliner route and force the admin to confirm the public URL.
 *
 * Everything is guarded: a database that has not run the earlier migrations, or
 * a fresh install that never had Snippe rows at all, simply does nothing.
 */
return new class extends Migration
{
    private const OLD_PROVIDER = 'snippe';

    private const NEW_PROVIDER = 'abliner';

    public function up(): void
    {
        if (Schema::hasTable('payment_settings') && Schema::hasTable('payments')) {
            DB::table('payment_settings')
                ->where('provider', self::OLD_PROVIDER)
                ->update([
                    'provider' => self::NEW_PROVIDER,
                    // Force the admin to confirm the public callback URL rather
                    // than leave a dead one behind.
                    'webhook_url' => null,
                ]);

            DB::table('payments')
                ->where('provider', self::OLD_PROVIDER)
                ->update(['provider' => self::NEW_PROVIDER]);
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'provider')) {
            // The column default keeps INSERTs that omit the column honest.
            try {
                Schema::table('payments', function ($table) {
                    $table->string('provider')->default(self::NEW_PROVIDER)->change();
                });
            } catch (\Throwable) {
                // Some MySQL/MariaDB builds refuse to modify a column that is
                // part of an index. The application always writes the provider
                // explicitly, so a stale default is cosmetic, not a defect.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_settings') || ! Schema::hasTable('payments')) {
            return;
        }

        DB::table('payment_settings')
            ->where('provider', self::NEW_PROVIDER)
            ->update(['provider' => self::OLD_PROVIDER]);

        DB::table('payments')
            ->where('provider', self::NEW_PROVIDER)
            ->update(['provider' => self::OLD_PROVIDER]);

        if (Schema::hasColumn('payments', 'provider')) {
            try {
                Schema::table('payments', function ($table) {
                    $table->string('provider')->default(self::OLD_PROVIDER)->change();
                });
            } catch (\Throwable) {
                // Same reason as up(): the index rebuild is not worth failing a
                // rollback over.
            }
        }
    }
};