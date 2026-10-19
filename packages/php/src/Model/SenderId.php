<?php

declare(strict_types=1);

namespace NotifyBd\Model;

final class SenderId
{
    public function __construct(
        public readonly string $id,
        public readonly string $value,
        /** pending | approved | rejected */
        public readonly string $status,
        public readonly ?string $approvedAt,
        public readonly string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['id'], $data['value'], $data['status'], $data['approvedAt'] ?? null, $data['createdAt']);
    }
}
