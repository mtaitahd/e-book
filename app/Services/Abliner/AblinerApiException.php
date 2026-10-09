<?php

namespace App\Services\Abliner;

use RuntimeException;

/**
 * Raised when an Abliner API call fails at the transport/provider level.
 *
 * This is distinct from a *customer payment failure*: an ApiException means
 * the API request itself did not succeed (auth, validation, insufficient
 * balance, transient outage, idempotency conflict). $transient distinguishes
 * retryable provider issues from request problems that should not be blindly
 * retried.
 *
 * $retryAfterSeconds carries the provider's own Retry-After hint on a 429 so
 * the retry loop can honour the documented rate limit instead of guessing.
 */
class AblinerApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly array $payload = [],
        public readonly bool $transient = false,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public function isTransient(): bool
    {
        return $this->transient;
    }

    /**
     * The provider's machine-readable error code, when it sent one.
     */
    public function errorCode(): string
    {
        $code = $this->payload['code'] ?? null;

        return is_string($code) && trim($code) !== '' ? trim($code) : '';
    }

    public function errorField(): ?string
    {
        $field = $this->payload['field'] ?? null;

        return is_string($field) && trim($field) !== '' ? trim($field) : null;
    }
}