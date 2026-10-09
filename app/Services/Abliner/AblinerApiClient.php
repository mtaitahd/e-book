<?php

namespace App\Services\Abliner;

use App\Settings\PaymentProviderConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single HTTP door to the Abliner REST API.
 *
 * Everything provider-specific that is NOT business logic lives here: the base
 * URL, the bearer token, request signing, the retry policy and the mapping
 * from a provider error body to a message a human can read. The payment,
 * control-number, wallet and withdrawal services all go through this one
 * client, so there is exactly one answer to "how do we authenticate" and
 * "how do we retry".
 *
 * Retry safety: every write we issue carries an Idempotency-Key, so replaying
 * a timed-out request cannot charge a customer twice. The documented 429
 * Retry-After is honoured (capped) instead of a fixed guess.
 */
class AblinerApiClient
{
    public const API_PREFIX = '/api/v1';

    public const REQUEST_TIMEOUT_SECONDS = 20;

    public const CONNECT_TIMEOUT_SECONDS = 8;

    public const MAX_ATTEMPTS = 3;

    public const MAX_RETRY_AFTER_SECONDS = 5;

    public function __construct(
        private readonly PaymentProviderConfig $config,
        private readonly AblinerSignatureVerifier $signer,
    ) {}

    public function baseUrl(): string
    {
        return rtrim((string) $this->config->baseUrl(), '/');
    }

    public function apiUrl(): string
    {
        return $this->baseUrl().self::API_PREFIX;
    }

    public function isConfigured(): bool
    {
        return $this->config->hasApiKey();
    }

    /**
     * A read. Never signed, never retried more than once on transport failure
     * because reads have no side effect.
     *
     * @return array{0: bool, 1: int, 2: array}
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, null, $query);
    }

    /**
     * A read that is performed EXACTLY once, with no retry at all.
     *
     * Used by the admin "Test connection" button. Retrying a diagnostic turns a
     * broken network into request spam against the provider's rate limit, and
     * the answer the admin needs ("is this key accepted?") is the same either
     * way.
     *
     * @return array{0: bool, 1: int, 2: array}
     */
    public function getOnce(string $path, array $query = []): array
    {
        if (! $this->isConfigured()) {
            return [false, 0, [
                'code' => 'not_configured',
                'message' => 'The payment provider is not configured on this server.',
            ]];
        }

        $url = $this->apiUrl().'/'.ltrim($path, '/');

        try {
            $response = $this->request('GET', $url, ['Accept' => 'application/json'], null, $query);
        } catch (ConnectionException) {
            return [false, 0, [
                'code' => 'connection_failed',
                'message' => 'The payment provider could not be reached.',
            ]];
        }

        return [$response->successful(), $response->status(), $this->decode($response)];
    }

    /**
     * A write. The body is JSON-encoded exactly once here, signed over those
     * exact bytes, and then handed to the transport unchanged.
     *
     * @return array{0: bool, 1: int, 2: array}
     */
    public function post(string $path, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->send('POST', $path, $payload, [], $idempotencyKey);
    }

    /**
     * Perform one request with a bounded retry loop.
     *
     * @return array{0: bool, 1: int, 2: array}
     */
    private function send(
        string $method,
        string $path,
        ?array $payload,
        array $query,
        ?string $idempotencyKey = null,
    ): array {
        if (! $this->isConfigured()) {
            return [false, 0, [
                'code' => 'not_configured',
                'message' => 'The payment provider is not configured on this server.',
            ]];
        }

        $body = null;
        $headers = ['Accept' => 'application/json'];

        if ($payload !== null) {
            // JSON_UNESCAPED_SLASHES / UNESCAPED_UNICODE keep the encoded form
            // readable, but the only requirement is that we sign the very
            // string we send, which is why it is built once and reused.
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($body === false) {
                return [false, 0, [
                    'code' => 'invalid_json',
                    'message' => 'The request body could not be encoded.',
                ]];
            }

            $headers['Content-Type'] = 'application/json';

            if ($this->signer->isConfigured()) {
                $headers += $this->signer->signRequestBody($body);
            }
        }

        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $url = $this->apiUrl().'/'.ltrim($path, '/');
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $this->request($method, $url, $headers, $body, $query);
            } catch (ConnectionException $exception) {
                $this->log('connection_failed', [
                    'path' => '/'.ltrim($path, '/'),
                    'method' => $method,
                    'attempt' => $attempt,
                    'exception' => $exception::class,
                ]);

                if ($attempt >= self::MAX_ATTEMPTS) {
                    return [false, 0, [
                        'code' => 'connection_failed',
                        'message' => 'The payment provider could not be reached.',
                    ]];
                }

                usleep(500 * 1000);

                continue;
            }

            $decoded = $this->decode($response);

            if ($response->successful()) {
                return [true, $response->status(), $decoded];
            }

            $retryable = $this->isRetryable($response->status(), $decoded);

            if (! $retryable || $attempt >= self::MAX_ATTEMPTS) {
                return [false, $response->status(), $decoded];
            }

            $delayMs = $this->backoffMilliseconds($response, $decoded);

            $this->log('retry', [
                'path' => '/'.ltrim($path, '/'),
                'method' => $method,
                'attempt' => $attempt,
                'status_code' => $response->status(),
                'code' => $decoded['code'] ?? null,
                'retry_after_ms' => $delayMs,
            ]);

            usleep($delayMs * 1000);
        }
    }

    private function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        array $query,
    ): Response {
        $request = \Illuminate\Support\Facades\Http::withHeaders($headers)
            ->withToken((string) $this->config->apiKey())
            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
            ->timeout(self::REQUEST_TIMEOUT_SECONDS);

        if ($query !== []) {
            return $request->get($url, $query);
        }

        if ($method === 'GET') {
            return $request->get($url);
        }

        // withBody, not asJson(): the payload was already encoded above and the
        // signature was computed over exactly these bytes.
        return $request->withBody((string) $body, 'application/json')->post($url);
    }

    /**
     * @return array<mixed>
     */
    private function decode(Response $response): array
    {
        $decoded = $response->json();

        if (is_array($decoded)) {
            return $decoded;
        }

        // Abliner documents every response as JSON, but a proxy or a PHP
        // warning page in front of it would not be. Keep a short, safe excerpt
        // so the admin-facing log has something to work with.
        $raw = trim((string) $response->body());

        return [
            'code' => 'unexpected_response',
            'message' => $raw === '' ? 'The provider returned an empty response.' : substr($raw, 0, 200),
        ];
    }

    /**
     * The provider tells us directly with `retryable`. Trust that first, then
     * fall back to the documented status codes (429 rate limit, 500/502/503
     * upstream hiccups) so an older gateway without the field still retries.
     */
    private function isRetryable(int $status, array $body): bool
    {
        if (array_key_exists('retryable', $body)) {
            return filter_var($body['retryable'], FILTER_VALIDATE_BOOL);
        }

        return in_array($status, [429, 500, 502, 503], true);
    }

    /**
     * Honour the documented Retry-After header on a 429, capped so a hostile
     * or misconfigured value cannot stall a customer request.
     */
    private function backoffMilliseconds(Response $response, array $body): int
    {
        $retryAfter = $response->header('Retry-After')
            ?? (isset($body['retry_after']) ? (string) $body['retry_after'] : null);

        if (filled($retryAfter) && is_numeric($retryAfter)) {
            $seconds = max(0, (int) $retryAfter);

            return min($seconds, self::MAX_RETRY_AFTER_SECONDS) * 1000;
        }

        return $response->status() === 429 ? 1000 : 500;
    }

    /**
     * Turn a provider error into a sentence a customer or an admin can act on.
     *
     * We branch on the machine-readable `code`, never on the message text,
     * which the provider explicitly reserves the right to reword.
     */
    public function friendlyError(int $status, array $body): string
    {
        $code = $this->errorCode($body);
        $detail = $this->errorMessage($body);

        $message = match ($code) {
            'idempotency_key_required' => 'The payment request was rejected because it carried no idempotency key.',
            'webhook_secret_required' => 'The store has no Abliner webhook secret, so no payment prompt can be sent. An administrator must generate one on the Abliner API Keys page.',
            'invalid_request_signature' => 'The store could not sign the payment request. Check that the Abliner webhook secret is correct.',
            'unauthorized' => 'The store credentials were rejected by the payment provider.',
            'insufficient_funds' => 'The store wallet does not have enough balance for this request.',
            'amount_out_of_range' => 'The amount is outside the range the payment provider accepts.',
            'invalid_phone' => 'That mobile number was rejected. Check the number and try again.',
            'unsupported_request' => 'That payment method is not available right now. Please choose another one.',
            'idempotency_conflict' => 'This payment was already started with a different amount. Please refresh the page and try again.',
            'request_in_progress' => 'This payment is still being processed. Please wait a few seconds and refresh.',
            'validation_failed' => 'The payment request was rejected because a value was missing or invalid.',
            'invalid_json' => 'The payment request could not be read by the payment provider.',
            'not_found' => 'That payment request could not be found or no longer exists.',
            'internal_error' => 'The payment provider had a problem completing this request. Nothing has been charged; please try again.',
            'account_suspended' => 'This store cannot accept payments right now. Please contact support.',
            'provider_error' => 'The payment network did not answer. We have not charged you; please try again.',
            'gateway_not_configured' => 'That payment rail is temporarily switched off by the provider. Please try another method.',
            default => match ($status) {
                400 => 'The payment request could not be processed. Please try again.',
                401, 403 => 'Payments are temporarily unavailable. Please try again later.',
                402 => 'The store wallet does not have enough balance to complete this request.',
                404 => 'The payment request could not be found or no longer exists.',
                409 => 'This payment conflicts with a previous attempt. Please refresh and try again.',
                422 => 'The payment request could not be processed. Please check the details and try again.',
                429 => 'We are receiving too many payment requests right now. Please wait a moment and retry.',
                500, 502, 503 => 'Payments are temporarily unavailable. Please try again shortly.',
                0 => 'The payment provider could not be reached. Please try again.',
                default => 'The payment request could not be completed. Please try again.',
            },
        };

        if ($code !== '' || $detail !== '') {
            $message .= ' ('.trim($code.' '.$detail).')';
        }

        return $message;
    }

    public function errorCode(array $body): string
    {
        $code = $body['code'] ?? null;

        return is_string($code) && trim($code) !== '' ? trim($code) : '';
    }

    public function errorMessage(array $body): string
    {
        foreach (['message', 'description', 'detail'] as $key) {
            $value = $body[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * The request field the provider blamed, when it named one. Reported so an
     * operator can see that, for example, `phone` — not `amount` — was what
     * Abliner rejected, without ever logging the value itself.
     */
    public function errorField(array $body): ?string
    {
        $field = $body['field'] ?? null;

        // Some validation errors are keyed by the code instead of naming a
        // single field, e.g. {"errors": {"invalid_phone": "phone"}}.
        if (blank($field)) {
            $field = $body['errors'][$this->errorCode($body)] ?? null;
        }

        if (is_string($field) && trim($field) !== '') {
            return trim($field);
        }

        return null;
    }

    /**
     * Build an exception for a failed call, so callers never have to repeat
     * the retryable/retryAfter plumbing.
     */
    public function exception(int $status, array $body, ?Response $response = null): AblinerApiException
    {
        $retryAfter = $response?->header('Retry-After');

        return new AblinerApiException(
            $this->friendlyError($status, $body),
            $status,
            $body,
            $this->isRetryable($status, $body),
            filled($retryAfter) && is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }

    private function log(string $event, array $context): void
    {
        Log::channel('stack')->info("abliner.{$event}", array_filter(
            $context,
            fn ($value) => $value !== null
        ));
    }

    /**
     * Never let a transport exception escape an admin-facing page.
     */
    public function guardAgainstUnexpected(Throwable $exception): AblinerApiException
    {
        $this->log('unexpected_error', ['exception' => $exception::class]);

        return new AblinerApiException(
            'The payment provider could not be reached. Please try again.',
            0,
            ['code' => 'connection_failed'],
            true,
        );
    }
}