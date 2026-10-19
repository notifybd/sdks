<?php

declare(strict_types=1);

namespace NotifyBd\Model;

final class Message
{
    public function __construct(
        public readonly string $id,
        public readonly string $businessId,
        public readonly string $senderId,
        public readonly string $body,
        /** otp | transactional */
        public readonly string $messageType,
        /** received | queued | sent | delivered | failed */
        public readonly string $status,
        /** HMAC of the recipient. The number itself is never returned. */
        public readonly string $toE164Hash,
        public readonly ?string $reference,
        /** app | sms once a channel was chosen */
        public readonly ?string $channel,
        public readonly ?string $failureReason,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $sentAt,
        public readonly ?string $deliveredAt,
        public readonly ?string $failedAt,
        /** First time the recipient opened it in the app. */
        public readonly ?string $readAt,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['businessId'],
            $data['senderId'],
            $data['body'],
            $data['messageType'],
            $data['status'],
            $data['toE164Hash'],
            $data['reference'] ?? null,
            $data['channel'] ?? null,
            $data['failureReason'] ?? null,
            $data['createdAt'],
            $data['updatedAt'],
            $data['sentAt'] ?? null,
            $data['deliveredAt'] ?? null,
            $data['failedAt'] ?? null,
            $data['readAt'] ?? null,
        );
    }
}
