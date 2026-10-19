<?php

declare(strict_types=1);

namespace NotifyBd\Tests;

use NotifyBd\Http\Response;
use NotifyBd\Http\Transport;

/** Replays queued responses (or throws queued exceptions) and records every request. */
final class FakeTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];

    /** @param list<Response|\Throwable> $queue */
    public function __construct(private array $queue)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): Response
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException('FakeTransport has no response queued');
        }
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    /** @param array<string, mixed>|list<mixed>|null $json @param array<string, string> $headers */
    public static function json(int $status, array|null $json, array $headers = []): Response
    {
        return new Response($status, $headers, $json === null ? '' : json_encode($json, JSON_THROW_ON_ERROR));
    }
}
