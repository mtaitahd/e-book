<?php

namespace App\Settings;

use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Services\Abliner\AblinerPaymentService;
use App\Support\Money;

/**
 * A snapshot of how the Abliner integration is configured, plus the editing
 * affordances the settings page needs.
 *
 * Secret values are reduced to a boolean and a masked hint at the point they
 * are read. This object deliberately has no property that can hold a raw
 * credential, so the view cannot leak one even if it is later changed.
 */
final readonly class PaymentProviderStatus
{
    /**
     * @param  array<string, string>  $networks
     */
    public function __construct(
        public string $provider,
        public string $providerKey,
        public bool $enabled,
        public bool $hasApiKey,
        public bool $hasWebhookSecret,
        public bool $verifyOnWebhook,
        public ?string $baseUrl,
        public ?string $webhookUrl,
        public string $currency,
        public int $minimumAmount,
        public int $maximumAmount,
        public array $networks,
        public array $methods,
        public string $apiKeySource,
        public string $webhookSecretSource,
        public string $apiKeyHint,
        public string $webhookSecretHint,
        public ?int $updatedBy,
        public ?string $updatedAt,
        public bool $hasStoredRow,
        public bool $hasUndecryptableSecret,
    ) {}

    public static function fromEnvironment(
        AblinerPaymentService $payments,
        PaymentProviderConfig $config,
    ): self {
        $row = $config->row();

        return new self(
            provider: 'Abliner Mobile Money & Cards',
            providerKey: Payment::PROVIDER_ABLINER,
            enabled: $payments->isEnabled(),
            hasApiKey: $payments->isConfigured(),
            hasWebhookSecret: $payments->hasWebhookSecret(),
            verifyOnWebhook: $config->verifyOnWebhook(),
            baseUrl: $payments->baseUrl(),
            webhookUrl: $config->webhookUrl(),
            currency: AblinerPaymentService::CURRENCY,
            minimumAmount: AblinerPaymentService::MINIMUM_AMOUNT,
            maximumAmount: AblinerPaymentService::MAXIMUM_AMOUNT,
            networks: Payment::NETWORKS,
            methods: Payment::METHODS,
            apiKeySource: $config->sourceFor('api_key'),
            webhookSecretSource: $config->sourceFor('webhook_secret'),
            // Built by the resolver from the resolved value, so an
            // undecryptable credential yields an empty hint instead of
            // throwing on the page.
            apiKeyHint: $config->apiKeyHint(),
            webhookSecretHint: $config->webhookSecretHint(),
            updatedBy: $row?->updated_by,
            updatedAt: $row?->updated_at?->format('d M Y, H:i'),
            hasStoredRow: $row !== null,
            hasUndecryptableSecret: $config->hasUndecryptableSecret(),
        );
    }

    /**
     * A new payment can be started right now.
     */
    public function isAvailable(): bool
    {
        return $this->enabled && $this->hasApiKey;
    }

    /**
     * Money can actually be confirmed: we have the key AND the secret needed to
     * sign our requests and to trust the callback that marks an order paid.
     */
    public function isFullyOperational(): bool
    {
        return $this->isAvailable() && $this->hasWebhookSecret;
    }

    public function minimumAmountLabel(): string
    {
        return Money::formatWhole($this->minimumAmount, $this->currency);
    }

    public function maximumAmountLabel(): string
    {
        return Money::formatWhole($this->maximumAmount, $this->currency);
    }

    public function webhookUrlLabel(): string
    {
        return $this->webhookUrl ?? 'Not set';
    }

    /**
     * True when the URL is being derived from the app's own route rather than
     * read from the environment, so the page can say so.
     */
    public function webhookUrlIsDerived(): bool
    {
        return blank(config('services.abliner.webhook_url'));
    }

    /**
     * Where a field's value is coming from, in words.
     */
    public function sourceLabel(string $source): string
    {
        return match ($source) {
            'database' => 'saved in this page',
            'environment' => 'from the server .env',
            default => 'not set',
        };
    }

    /**
     * A short, non-technical explanation of whatever is blocking payments.
     */
    public function blockingReason(): ?string
    {
        return match (true) {
            $this->hasUndecryptableSecret => 'A stored credential can no longer be decrypted, which usually means the application key changed. Re-enter both credentials below.',
            ! $this->enabled => 'Payments are switched off by the administrator. No new Abliner payment can be started.',
            ! $this->hasApiKey => 'No API key is set, so Abliner cannot be called.',
            // Not merely cosmetic: without the secret we cannot sign our own
            // requests, so Abliner would reject every deposit.
            ! $this->hasWebhookSecret => 'No webhook signing secret is set. Abliner refuses to send a payment prompt without one, and payment callbacks cannot be verified, so orders will never be marked paid automatically.',
            default => null,
        };
    }

    /**
     * The fields an admin is allowed to clear, which drops back to the
     * environment value.
     *
     * @return list<string>
     */
    public function clearableFields(): array
    {
        return PaymentSetting::SECRET_FIELDS;
    }
}