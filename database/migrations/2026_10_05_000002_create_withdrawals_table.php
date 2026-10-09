<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-initiated payouts from the store's Abliner wallet.
 *
 * Every write path in the application still ends up in one of these rows: the
 * store can reconcile against a provider statement by listing what it asked
 * for, who asked for it, and what the provider replied.
 *
 * `provider_reference` is indexed but NOT unique, because a payout that timed
 * out may legitimately never receive one; the unique constraint that protects
 * against double-sending is the `ebs-wd-{id}` idempotency key derived from the
 * primary key in the service.
 *
 * `settled_at` records when the payout reached a final state, which is not the
 * same as `paid_at` (the provider's own completion time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();

            $table->string('provider', 32)->default('abliner');

            // Who pressed the button. nullOnDelete so removing an operator
            // account never deletes the financial record of a payout.
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('method', 16)->default('mobile');
            $table->string('recipient', 64);
            $table->string('bank_code', 16)->nullable();
            $table->string('account_name')->nullable();

            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->nullable();
            $table->string('currency', 8)->default('TZS');

            // What WE sent as `reference`, echoed back on the webhook.
            $table->string('reference');

            $table->string('status', 16)->default('pending');
            $table->string('provider_reference')->nullable();
            $table->string('external_reference')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('provider_payload')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};