<?php

declare(strict_types=1);

namespace NotifyBd\Model;

/** The `202 Accepted` envelope. Delivery happens asynchronously. */
final class SendMessageResponse
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $reference,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['id'], $data['status'], $data['reference'] ?? null, $data['createdAt']);
    }
}
