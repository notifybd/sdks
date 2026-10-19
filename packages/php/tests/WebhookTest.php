<?php

declare(strict_types=1);

namespace NotifyBd\Tests;

use NotifyBd\Exception\SignatureVerificationException;
use NotifyBd\Webhook;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function fixture(): array
    {
        $path = __DIR__ . '/../../../spec/fixtures/webhook-signature.json';

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function sharedVectors(): iterable
    {
        foreach (self::fixture()['cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    /** @param array<string, mixed> $case */
    #[DataProvider('sharedVectors')]
    public function testSharedVectors(array $case): void
    {
        $fixture = self::fixture();
        $run = fn () => Webhook::constructEvent(
            $case['rawBody'] ?? $fixture['rawBody'],
            $case['header'],
            $fixture['secret'],
            $fixture['toleranceSeconds'],
            $case['now'],
        );

        if ($case['valid']) {
            $this->assertSame('message.delivered', $run()->eventName);

            return;
        }

        try {
            $run();
            $this->fail('Expected a SignatureVerificationException');
        } catch (SignatureVerificationException $error) {
            $this->assertSame($case['reason'], $error->reason);
        }
    }

    public function testParsesTheEventFields(): void
    {
        $fixture = self::fixture();
        $case = $fixture['cases'][0];
        $event = Webhook::constructEvent($fixture['rawBody'], $case['header'], $fixture['secret'], 300, $case['now']);

        $this->assertSame('delivered', $event->status);
        $this->assertSame('app', $event->channel);
        $this->assertNull($event->reason);
    }

    public function testRejectsAMissingHeader(): void
    {
        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent(self::fixture()['rawBody'], null, self::fixture()['secret']);
    }
}
