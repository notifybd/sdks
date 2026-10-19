<?php

declare(strict_types=1);

namespace NotifyBd\Model;

/** The JSON body Notify POSTs to your endpoint when a message reaches a final status. */
final class WebhookEvent
{
    public function __construct(
        public readonly string $eventId,
        /** message.delivered | message.failed */
        public readonly string $eventName,
        public readonly int $eventVersion,
        public readonly string $occurredAt,
        public readonly string $correlationId,
        public readonly string $messageId,
        public readonly string $businessId,
        /** delivered | failed */
        public readonly string $status,
        public readonly ?string $channel,
        public readonly ?string $reason,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $payload = $data['payload'];

        return new self(
            $data['eventId'],
            $data['eventName'],
            $data['eventVersion'],
            $data['occurredAt'],
            $data['correlationId'],
            $payload['messageId'],
            $payload['businessId'],
            $payload['status'],
            $payload['channel'] ?? null,
            $payload['reason'] ?? null,
        );
    }
}
