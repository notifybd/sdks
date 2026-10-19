<?php

declare(strict_types=1);

namespace NotifyBd\Http;

use NotifyBd\Exception\ApiConnectionException;

/**
 * Sends one HTTP request. The default is {@see CurlTransport}; implement this to route requests
 * through your own client (a PSR-18 adapter, a proxy, a test double).
 */
interface Transport
{
    /**
     * @param array<string, string> $headers
     * @throws ApiConnectionException when no HTTP response was received
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): Response;
}
