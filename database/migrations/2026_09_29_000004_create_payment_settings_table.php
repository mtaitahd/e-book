<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 10.1 admin-managed payment provider settings.
 *
 * Before this table the Snippe credentials could only be supplied by editing
 * `.env` by hand and reloading the app, which is not something an ordinary
 * store owner can do. This gives the admin a page to set, rotate, enable and
 * disable the integration.
 *
 * The two credential columns are NEVER plaintext:
 *  - `api_key` and `webhook_secret` are written through the model's
 *    `encrypted` cast, so what lands in the column is an authenticated
 *    AES-256-CBC ciphertext produced with the application APP_KEY.
 *  - The column is a `text` rather than a `string` because ciphertext is always
 *    longer than its plaintext; a `varchar(255)` would silently truncate a
 *    longer secret.
 *
 * Environment values remain supported and act as the fallback for any field
 * left null here, so an existing deployment that already has `.env`
 * credentials keeps working untouched after migrating.
 *
 * Exactly one row is ever used (provider = 'snippe'), enforced by a unique
 * index, so the table stays a singleton rather than becoming a settings table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();

            // One row per provider. Today that is only 'snippe'.
            $table->string('provider', 32)->unique();

            // Encrypted at rest via the model's `encrypted` cast.
            $table->text('api_key')->nullable();
            $table->text('webhook_secret')->nullable();

            // Not secret, so stored in the clear.
            $table->string('webhook_url')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('verify_on_webhook')->default(true);

            // Who last changed the credentials, for accountability. Never the
            // values themselves.
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
