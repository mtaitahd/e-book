<?php

namespace App\Services\Abliner;

use App\Settings\PaymentProviderConfig;

/**
 * HMAC-SHA256 signing for Abliner, in both directions.
 *
 * Abliner uses ONE secret (the dashboard's "personal secret", whsec_...) for
 * two separate jobs:
 *
 *  1. INBOUND  — every webhook they POST to us carries
 *                `x-webhook-timestamp` (unix seconds) and `x-webhook-signature`,
 *                the hex HMAC-SHA256 of "{timestamp}.{rawBody}".
 *  2. OUTBOUND — every POST we make to them (POST /api/v1/deposits and the
 *                other write endpoints) must carry `x-abliner-timestamp` and
 *                `x-abliner-signature` computed the same way over the exact
 *                body we send. Without it a key created from 5 Oct 2026 is
 *                rejected with 401 invalid_request_signature and no payment
 *                prompt is sent.
 *
 * The body must never be decoded and re-encoded between signing and sending,
 * otherwise the HMAC will not match. signRequestBody() exists so the caller
 * can hand the SAME string to the transport.
 */
class AblinerSignatureVerifier
{
    public const FRESHNESS_SECONDS = 300; // 5 minutes

    public const HEADER_TIMESTAMP = 'x-webhook-timestamp';

    public const HEADER_SIGNATURE = 'x-webhook-signature';

    /**
     * Stable across webhook retries, so it is the correct de-duplication key.
     */
    public const HEADER_EVENT_ID = 'x-webhook-id';

    public const REQUEST_HEADER_TIMESTAMP = 'x-abliner-timestamp';

    public const REQUEST_HEADER_SIGNATURE = 'x-abliner-signature';

    public function __construct(
        private readonly PaymentProviderConfig $config,
        private readonly int $freshnessWindow = self::FRESHNESS_SECONDS,
    ) {}

    /**
     * Verify an inbound webhook request. Returns true only when:
     *  - a shared secret is available
     *  - both headers are present and non-empty
     *  - the timestamp is within the freshness window
     *  - the HMAC-SHA256 signature (hash_equals) matches the raw payload
     */
    public function verify(?string $timestamp, ?string $signature, string $rawBody): bool
    {
        // Read through the resolver on every call rather than holding the
        // secret from boot, so an admin rotating the credential takes effect
        // immediately and can never leave two components signing with
        // different secrets.
        $secret = $this->config->webhookSecret();

        if (blank($secret)) {
            return false;
        }

        if ($timestamp === null || $signature === null || $timestamp === '' || $signature === '') {
            return false;
        }

        if (! $this->isFresh($timestamp)) {
            return false;
        }

        $expected = $this->hmac($timestamp, $rawBody, (string) $secret);

        return hash_equals($expected, $signature);
    }

    public function isFresh(string $timestamp): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        $time = (int) $timestamp;

        return abs(now()->timestamp - $time) <= $this->freshnessWindow;
    }

    public function isConfigured(): bool
    {
        return $this->config->hasWebhookSecret();
    }

    public function freshnessWindow(): int
    {
        return $this->freshnessWindow;
    }

    /**
     * The signature headers for an outbound write request.
     *
     * Returns an empty array when no secret is configured: Abliner then
     * answers with a clear webhook_secret_required / unauthorized rather than
     * us sending a signature that cannot possibly match.
     *
     * @return array<string, string>
     */
    public function signRequestBody(string $rawBody, ?int $timestamp = null): array
    {
        $secret = $this->config->webhookSecret();

        if (blank($secret)) {
            return [];
        }

        $ts = (string) ($timestamp ?? now()->timestamp);

        return [
            self::REQUEST_HEADER_TIMESTAMP => $ts,
            self::REQUEST_HEADER_SIGNATURE => $this->hmac($ts, $rawBody, (string) $secret),
        ];
    }

    private function hmac(string $timestamp, string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }
}