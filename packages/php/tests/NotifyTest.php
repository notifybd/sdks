<?php

declare(strict_types=1);

namespace NotifyBd\Tests;

use NotifyBd\Exception\ApiConnectionException;
use NotifyBd\Exception\AuthenticationException;
use NotifyBd\Exception\ConflictException;
use NotifyBd\Exception\RateLimitException;
use NotifyBd\Exception\UnprocessableException;
use NotifyBd\Exception\ValidationException;
use NotifyBd\Notify;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotifyTest extends TestCase
{
    private const ACCEPTED = [
        'id' => '018f0000-0000-7000-8000-000000000001',
        'status' => 'accepted',
        'reference' => null,
        'createdAt' => '2026-01-01T00:00:00.000Z',
    ];

    private const SEND = ['to' => '01712345678', 'body' => 'Your code is 1', 'messageType' => 'otp', 'senderId' => 'Shop'];

    /** @param list<\NotifyBd\Http\Response|\Throwable> $queue */
    private function client(array $queue, int $maxRetries = 2): array
    {
        $transport = new FakeTransport($queue);
        $notify = new Notify([
            'apiKey' => 'nk_test',
            'baseUrl' => 'https://api.test/v1',
            'maxRetries' => $maxRetries,
            'transport' => $transport,
            'sleeper' => static function (int $microseconds): void {
            },
        ]);

        return [$notify, $transport];
    }

    public function testRequiresAnApiKey(): void
    {
        putenv('NOTIFY_API_KEY');
        $this->expectException(\InvalidArgumentException::class);
        new Notify();
    }

    public function testSendsWithAuthUserAgentAndIdempotencyKey(): void
    {
        [$notify, $transport] = $this->client([FakeTransport::json(202, self::ACCEPTED)]);

        $result = $notify->messages->send(self::SEND);

        $this->assertSame(self::ACCEPTED['id'], $result->id);
        $request = $transport->requests[0];
        $this->assertSame('https://api.test/v1/messages', $request['url']);
        $this->assertSame('POST', $request['method']);
        $this->assertSame('Bearer nk_test', $request['headers']['Authorization']);
        $this->assertStringStartsWith('notify-php/', $request['headers']['User-Agent']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $request['headers']['Idempotency-Key']);
        $this->assertSame(self::SEND, json_decode((string) $request['body'], true));
    }

    public function testRetriesReuseOneIdempotencyKey(): void
    {
        [$notify, $transport] = $this->client([
            FakeTransport::json(503, ['error' => ['code' => 'UPSTREAM_UNAVAILABLE', 'message' => 'x']]),
            FakeTransport::json(202, self::ACCEPTED),
        ]);

        $notify->messages->send(self::SEND);

        $this->assertCount(2, $transport->requests);
        $this->assertSame(
            $transport->requests[0]['headers']['Idempotency-Key'],
            $transport->requests[1]['headers']['Idempotency-Key'],
        );
    }

    public function testUsesTheCallersIdempotencyKey(): void
    {
        [$notify, $transport] = $this->client([FakeTransport::json(202, self::ACCEPTED)]);
        $notify->messages->send(self::SEND, 'order-42');
        $this->assertSame('order-42', $transport->requests[0]['headers']['Idempotency-Key']);
    }

    public function testNetworkErrorsAreRetriedThenReported(): void
    {
        [$notify, $transport] = $this->client([
            new ApiConnectionException('down'),
            new ApiConnectionException('still down'),
        ], 1);

        try {
            $notify->messages->send(self::SEND);
            $this->fail('Expected ApiConnectionException');
        } catch (ApiConnectionException) {
            $this->assertCount(2, $transport->requests);
        }
    }

    /** @return iterable<string, array{int, string, class-string}> */
    public static function errorMapping(): iterable
    {
        yield '400' => [400, 'VALIDATION_ERROR', ValidationException::class];
        yield '401' => [401, 'UNAUTHENTICATED', AuthenticationException::class];
        yield '409' => [409, 'CONFLICT', ConflictException::class];
        yield '422' => [422, 'POLICY_REJECTION', UnprocessableException::class];
    }

    /** @param class-string<\Throwable> $type */
    #[DataProvider('errorMapping')]
    public function testMapsErrorsAndDoesNotRetryThem(int $status, string $code, string $type): void
    {
        [$notify, $transport] = $this->client([
            FakeTransport::json($status, ['error' => ['code' => $code, 'message' => 'nope', 'requestId' => 'req-1']]),
        ]);

        try {
            $notify->messages->send(self::SEND);
            $this->fail('Expected ' . $type);
        } catch (\Throwable $error) {
            $this->assertInstanceOf($type, $error);
            $this->assertSame($status, $error->status);
            $this->assertSame($code, $error->errorCode);
            $this->assertSame('req-1', $error->requestId);
            $this->assertCount(1, $transport->requests);
        }
    }

    public function testRateLimitExposesRetryAfter(): void
    {
        [$notify] = $this->client([
            FakeTransport::json(429, ['error' => ['code' => 'RATE_LIMITED', 'message' => 'slow']], ['retry-after' => '7']),
        ], 0);

        try {
            $notify->messages->send(self::SEND);
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $error) {
            $this->assertSame(7.0, $error->retryAfter);
        }
    }

    public function testListAllFollowsCursors(): void
    {
        $message = [
            'id' => 'm', 'businessId' => 'b', 'senderId' => 's', 'body' => 'x', 'messageType' => 'otp',
            'status' => 'sent', 'toE164Hash' => 'h', 'reference' => null, 'channel' => 'app',
            'failureReason' => null, 'createdAt' => 't', 'updatedAt' => 't', 'sentAt' => null,
            'deliveredAt' => null, 'failedAt' => null, 'readAt' => null, 'futureField' => 1,
        ];
        [$notify, $transport] = $this->client([
            FakeTransport::json(200, ['data' => [$message, $message], 'nextCursor' => 'c1']),
            FakeTransport::json(200, ['data' => [$message], 'nextCursor' => null]),
        ]);

        $count = 0;
        foreach ($notify->messages->listAll(2) as $_) {
            $count++;
        }

        $this->assertSame(3, $count);
        $this->assertSame('https://api.test/v1/messages?limit=2', $transport->requests[0]['url']);
        $this->assertSame('https://api.test/v1/messages?limit=2&cursor=c1', $transport->requests[1]['url']);
    }

    public function testSenders(): void
    {
        $sender = ['id' => 's1', 'value' => 'Shop', 'status' => 'pending', 'approvedAt' => null, 'createdAt' => 't'];
        [$notify] = $this->client([
            FakeTransport::json(201, $sender),
            FakeTransport::json(200, [$sender]),
        ]);

        $this->assertSame('pending', $notify->senders->create('Shop')->status);
        $this->assertCount(1, $notify->senders->list());
    }
}
