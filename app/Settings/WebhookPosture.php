<?php

namespace App\Settings;

/**
 * The security posture of the inbound payment callback, as derived from the
 * running configuration rather than as claimed by it.
 */
final readonly class WebhookPosture
{
    public function __construct(
        public bool $secretPresent,
        public bool $verifyOnWebhook,
        public string $endpoint,
        public int $toleranceSeconds,
    ) {}

    public function verifiesSignatures(): bool
    {
        return $this->secretPresent;
    }

    public function isHardened(): bool
    {
        return $this->secretPresent && $this->verifyOnWebhook;
    }

    public function toleranceLabel(): string
    {
        return $this->toleranceSeconds.' seconds';
    }
}
