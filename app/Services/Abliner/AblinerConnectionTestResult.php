<?php

namespace App\Services\Abliner;

/**
 * The outcome of an Abliner connectivity check.
 *
 * This object is deliberately tiny. It carries booleans, a status code, a
 * latency and a pre-written safe message — and nothing else. It has no field
 * for the API key, the Authorization header, the provider's response body or
 * the account balance, so there is nothing here for a view or a JSON response
 * to leak even by accident.
 */
final readonly class AblinerConnectionTestResult
{
    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_UNAUTHORIZED = 'unauthorized';

    public const OUTCOME_UNREACHABLE = 'unreachable';

    public const OUTCOME_UNEXPECTED = 'unexpected';

    public const OUTCOME_MISCONFIGURED = 'misconfigured';

    public const OUTCOME_DISABLED = 'disabled';

    public function __construct(
        public string $outcome,
        public bool $reachable,
        public bool $credentialsAccepted,
        public ?int $statusCode,
        public ?int $latencyMs,
        public string $message,
    ) {}

    public function isSuccess(): bool
    {
        return $this->outcome === self::OUTCOME_SUCCESS;
    }

    /**
     * The provider said the key is not valid for this endpoint.
     */
    public static function unauthorized(?int $latencyMs, ?int $statusCode): self
    {
        return new self(
            self::OUTCOME_UNAUTHORIZED,
            reachable: true,
            credentialsAccepted: false,
            statusCode: $statusCode,
            latencyMs: $latencyMs,
            message: 'Abliner rejected the credentials. Check the API key on the payment settings page.',
        );
    }

    /**
     * DNS, TLS, connection or timeout failure — the host could not be reached.
     */
    public static function unreachable(?int $latencyMs, ?int $statusCode = null): self
    {
        return new self(
            self::OUTCOME_UNREACHABLE,
            reachable: false,
            credentialsAccepted: false,
            statusCode: $statusCode,
            latencyMs: $latencyMs,
            message: 'Unable to connect to Abliner. The API could not be reached from this server.',
        );
    }

    public static function unexpected(?int $latencyMs, ?int $statusCode): self
    {
        return new self(
            self::OUTCOME_UNEXPECTED,
            reachable: true,
            credentialsAccepted: false,
            statusCode: $statusCode,
            latencyMs: $latencyMs,
            message: 'Abliner returned an unexpected response to the connectivity check.',
        );
    }

    public static function misconfigured(): self
    {
        return new self(
            self::OUTCOME_MISCONFIGURED,
            reachable: false,
            credentialsAccepted: false,
            statusCode: null,
            latencyMs: null,
            message: 'No Abliner API key is configured on this server, so the connection cannot be tested.',
        );
    }

    public static function disabled(): self
    {
        return new self(
            self::OUTCOME_DISABLED,
            reachable: false,
            credentialsAccepted: false,
            statusCode: null,
            latencyMs: null,
            message: 'Abliner is disabled on this server, so the connection was not tested.',
        );
    }

    public static function success(int $latencyMs, int $statusCode): self
    {
        return new self(
            self::OUTCOME_SUCCESS,
            reachable: true,
            credentialsAccepted: true,
            statusCode: $statusCode,
            latencyMs: $latencyMs,
            message: 'Abliner connection is reachable and credentials were accepted.',
        );
    }

    public function badge(): string
    {
        return match ($this->outcome) {
            self::OUTCOME_SUCCESS => 'success',
            self::OUTCOME_MISCONFIGURED, self::OUTCOME_DISABLED => 'secondary',
            default => 'danger',
        };
    }
}