<?php

declare(strict_types=1);

namespace NotifyBd\Model;

final class MessageEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly string $messageId,
        public readonly string $businessId,
        public readonly ?string $toE164Hash,
        public readonly ?string $channel,
        public readonly string $status,
        public readonly ?string $errorCode,
        public readonly string $correlationId,
        public readonly string $occurredAt,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['eventId'],
            $data['eventType'],
            $data['messageId'],
            $data['businessId'],
            $data['toE164Hash'] ?? null,
            $data['channel'] ?? null,
            $data['status'],
            $data['errorCode'] ?? null,
            $data['correlationId'],
            $data['occurredAt'],
        );
    }
}
