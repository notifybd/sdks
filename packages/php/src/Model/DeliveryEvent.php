<?php

declare(strict_types=1);

namespace NotifyBd\Model;

final class DeliveryEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly string $messageId,
        public readonly string $businessId,
        public readonly ?string $channel,
        /** The push provider or SMS carrier. */
        public readonly ?string $provider,
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
            $data['channel'] ?? null,
            $data['provider'] ?? null,
            $data['status'],
            $data['errorCode'] ?? null,
            $data['correlationId'],
            $data['occurredAt'],
        );
    }
}
