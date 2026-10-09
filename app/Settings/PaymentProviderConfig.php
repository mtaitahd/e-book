<?php

namespace App\Settings;

use App\Models\PaymentSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Throwable;

/**
 * The single place the application asks what the payment provider is
 * configured with.
 *
 * Precedence for every field is: the value the admin saved in the database
 * first, then the environment, then a built-in default. A null database value
 * means "not set here", so a deployment that already configures `.env` keeps
 * working with no saved row at all.
 *
 * This exists so there is exactly ONE answer to "which secret do we sign with?"
 * and "which token do we authenticate with?". If the payment service and the
 * webhook verifier each read config themselves, an admin who rotates the
 * credential in the admin page would leave the two disagreeing — a broken
 * integration, or worse, a still-trusted old secret.
 */
class PaymentProviderConfig
{
    /**
     * @var array<string, string>
     */
    private const FIELDS = [
        'api_key' => 'services.abliner.api_key',
        'webhook_secret' => 'services.abliner.webhook_secret',
        'webhook_url' => 'services.abliner.webhook_url',
    ];

    private bool $loaded = false;

    private ?PaymentSetting $row = null;

    /**
     * Fields whose stored value exists but cannot be decrypted, almost always
     * because APP_KEY was rotated. Surfaced to the admin instead of being
     * swallowed.
     *
     * @var list<string>
     */
    private array $undecryptable = [];

    /**
     * Forget everything read so far. Called after an admin saves a change so
     * the very next read in the same request sees the new value.
     */
    public function refresh(): void
    {
        $this->loaded = false;
        $this->row = null;
        $this->undecryptable = [];
    }

    public function row(): ?PaymentSetting
    {
        $this->load();

        return $this->row;
    }

    /**
     * The saved row, created on demand.
     */
    public function rowOrCreate(): PaymentSetting
    {
        $this->load();

        return $this->row ??= PaymentSetting::query()->create([
            'provider' => PaymentSetting::PROVIDER_ABLINER,
        ]);
    }

    public function apiKey(): ?string
    {
        return $this->secret('api_key');
    }

    public function webhookSecret(): ?string
    {
        return $this->secret('webhook_secret');
    }

    public function hasApiKey(): bool
    {
        return filled($this->apiKey());
    }

    public function hasWebhookSecret(): bool
    {
        return filled($this->webhookSecret());
    }

    /**
     * Where a field's value actually comes from, for honest display:
     * 'database', 'environment' or 'default'.
     */
    public function sourceFor(string $field): string
    {
        $this->load();

        if ($this->row !== null && $this->storedValue($field) !== null) {
            return 'database';
        }

        return filled(config(self::FIELDS[$field] ?? '')) ? 'environment' : 'default';
    }

    public function isEnabled(): bool
    {
        $this->load();

        if ($this->row !== null && $this->row->enabled !== null) {
            return (bool) $this->row->enabled;
        }

        return filter_var(config('services.abliner.enabled', true), FILTER_VALIDATE_BOOL);
    }

    public function verifyOnWebhook(): bool
    {
        $this->load();

        if ($this->row !== null && $this->row->verify_on_webhook !== null) {
            return (bool) $this->row->verify_on_webhook;
        }

        return filter_var(config('services.abliner.verify_on_webhook', true), FILTER_VALIDATE_BOOL);
    }

    /**
     * The public callback URL. Falls back to the app's own route, then to
     * APP_URL, so an admin is never shown a blank field to guess at.
     */
    public function webhookUrl(): ?string
    {
        $this->load();

        $stored = $this->row !== null ? $this->row->webhook_url : null;

        if (filled($stored)) {
            return (string) $stored;
        }

        $configured = config('services.abliner.webhook_url');

        if (filled($configured)) {
            return (string) $configured;
        }

        try {
            return route('webhooks.abliner');
        } catch (Throwable) {
            $base = rtrim((string) config('app.url'), '/');

            return $base === '' ? null : $base.'/webhooks/abliner';
        }
    }

    /**
     * The Abliner API host, without the /api/v1 suffix, which the API client
     * appends.
     */
    public function baseUrl(): string
    {
        return rtrim((string) config('services.abliner.base_url', 'https://abliner.net'), '/');
    }

    /**
     * @return list<string>
     */
    public function undecryptableFields(): array
    {
        $this->load();

        return $this->undecryptable;
    }

    public function hasUndecryptableSecret(): bool
    {
        return $this->undecryptableFields() !== [];
    }

    /**
     * A hint of which credential is in use, safe to render and to log.
     *
     * Shows the provider's own key prefix and the last four characters, which
     * is what the Abliner dashboard shows too. Built from the resolved value
     * rather than from the raw row, so an undecryptable value yields an empty
     * hint rather than an exception on the page.
     */
    public function mask(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Abliner API keys start tsl_live_ / tsl_test_, and the webhook signing
        // secret starts whsec_. Showing the prefix tells an operator which of the
        // two credentials they are looking at, without revealing either.
        $prefix = '';

        foreach (['tsl_live_', 'tsl_test_', 'whsec_'] as $known) {
            if (str_starts_with($value, $known)) {
                $prefix = $known;

                break;
            }
        }

        // The last four characters of a short secret would be most of the
        // secret, so reveal nothing below this floor. Real Abliner credentials
        // are far longer.
        if (strlen($value) < 12) {
            return $prefix.str_repeat('*', 12);
        }

        return $prefix.str_repeat('*', 8).substr($value, -4);
    }

    public function apiKeyHint(): string
    {
        return $this->mask($this->apiKey());
    }

    public function webhookSecretHint(): string
    {
        return $this->mask($this->webhookSecret());
    }

    /**
     * Read a secret, database first then environment. An undecryptable value
     * is treated as absent and recorded, never returned as an empty string
     * that would quietly produce a valid-looking but wrong signature.
     */
    private function secret(string $field): ?string
    {
        $this->load();

        $stored = $this->storedValue($field);

        if ($stored !== null) {
            return $stored;
        }

        $fromEnv = config(self::FIELDS[$field] ?? '');

        return filled($fromEnv) ? (string) $fromEnv : null;
    }

    /**
     * The decrypted stored value, or null when nothing usable is stored.
     */
    private function storedValue(string $field): ?string
    {
        if ($this->row === null) {
            return null;
        }

        if (! in_array($field, PaymentSetting::SECRET_FIELDS, true)) {
            $value = $this->row->getAttribute($field);

            return filled($value) ? (string) $value : null;
        }

        $raw = $this->row->getRawOriginal($field);

        if (blank($raw)) {
            return null;
        }

        try {
            $value = $this->row->getAttribute($field);
        } catch (DecryptException) {
            $this->undecryptable[] = $field;

            return null;
        } catch (Throwable) {
            $this->undecryptable[] = $field;

            return null;
        }

        return filled($value) ? (string) $value : null;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        try {
            $this->row = PaymentSetting::forProvider();
        } catch (Throwable) {
            // The settings table may not exist yet (a fresh checkout before
            // migrating, or a migration that has not run). That must not take
            // the storefront down: fall back to the environment alone.
            $this->row = null;
        }
    }
}
