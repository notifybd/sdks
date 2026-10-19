<?php

declare(strict_types=1);

namespace NotifyBd\Http;

use NotifyBd\Exception\ApiConnectionException;
use NotifyBd\Exception\ApiException;
use NotifyBd\Exception\NotifyException;
use NotifyBd\Version;

/**
 * Builds requests, retries what is safe to retry (network errors, 408, 429, 5xx, honouring
 * Retry-After) and turns error responses into typed exceptions.
 *
 * @internal
 */
final class Requestor
{
    private const RETRY_STATUSES = [408, 429, 500, 502, 503, 504];

    /** @var callable(int): void */
    private $sleeper;

    /**
     * @param array{name?: string, version?: string}|null $appInfo
     * @param (callable(int): void)|null $sleeper receives microseconds; for tests
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly float $timeout,
        private readonly int $maxRetries,
        private readonly Transport $transport,
        private readonly ?array $appInfo = null,
        ?callable $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     * @return mixed decoded JSON (null for an empty body)
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        array $headers = [],
        ?bool $retryable = null,
    ): mixed {
        $retryable ??= in_array($method, ['GET', 'DELETE'], true);
        $url = rtrim($this->baseUrl, '/') . $path;
        $query = array_filter($query, static fn ($value) => $value !== null);
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        $encoded = $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $allHeaders = array_merge($this->headers($encoded !== null), $headers);

        for ($attempt = 0;; $attempt++) {
            try {
                $response = $this->transport->send($method, $url, $allHeaders, $encoded, $this->timeout);
            } catch (ApiConnectionException $error) {
                if ($retryable && $attempt < $this->maxRetries) {
                    ($this->sleeper)($this->backoff($attempt));
                    continue;
                }
                throw $error;
            }

            if ($response->status >= 200 && $response->status < 300) {
                return $response->body === '' ? null : json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
            }

            if ($retryable && $attempt < $this->maxRetries && in_array($response->status, self::RETRY_STATUSES, true)) {
                ($this->sleeper)($this->retryDelay($response, $attempt));
                continue;
            }

            throw $this->toException($response);
        }
    }

    /** @return array<string, string> */
    private function headers(bool $hasBody): array
    {
        $agent = 'notify-php/' . Version::VERSION;
        if ($this->appInfo !== null && isset($this->appInfo['name'])) {
            $agent .= ' ' . $this->appInfo['name'] . (isset($this->appInfo['version']) ? '/' . $this->appInfo['version'] : '');
        }

        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => $agent,
        ] + ($hasBody ? ['Content-Type' => 'application/json'] : []);
    }

    private function toException(Response $response): ApiException
    {
        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $decoded = null;
        }
        $error = is_array($decoded) && isset($decoded['error']) && is_array($decoded['error']) ? $decoded['error'] : [];
        $retryAfter = $response->header('retry-after');

        return ApiException::forStatus(
            $response->status,
            is_string($error['code'] ?? null) ? $error['code'] : 'UNKNOWN',
            is_string($error['message'] ?? null) ? $error['message'] : 'Request failed with status ' . $response->status,
            is_string($error['requestId'] ?? null) ? $error['requestId'] : $response->header('x-request-id'),
            is_array($error['details'] ?? null) ? $error['details'] : null,
            $retryAfter !== null && is_numeric($retryAfter) ? (float) $retryAfter : null,
        );
    }

    private function backoff(int $attempt): int
    {
        $ceiling = (int) min(8_000_000, 500_000 * (2 ** $attempt));

        return intdiv($ceiling, 2) + random_int(0, intdiv($ceiling, 2));
    }

    private function retryDelay(Response $response, int $attempt): int
    {
        $header = $response->header('retry-after');
        if ($header !== null && is_numeric($header) && (float) $header > 0) {
            return (int) (min((float) $header, 30.0) * 1_000_000);
        }

        return $this->backoff($attempt);
    }
}
