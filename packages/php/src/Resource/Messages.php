<?php

declare(strict_types=1);

namespace NotifyBd\Resource;

use NotifyBd\Http\Requestor;
use NotifyBd\Model\DeliveryEvent;
use NotifyBd\Model\Message;
use NotifyBd\Model\MessageEvent;
use NotifyBd\Model\Page;
use NotifyBd\Model\SendMessageResponse;

final class Messages
{
    /** @internal */
    public function __construct(private readonly Requestor $requestor)
    {
    }

    /**
     * Submits a message: app first, SMS if the app cannot be reached. Returns once it is accepted
     * (HTTP 202); delivery is asynchronous.
     *
     * Every call carries an idempotency key (yours, or a generated one) so the SDK can retry
     * network errors and 5xx responses without ever sending the message twice.
     *
     * @param array{to: string, body: string, messageType: 'otp'|'transactional', senderId: string, reference?: string} $params
     */
    public function send(array $params, ?string $idempotencyKey = null): SendMessageResponse
    {
        /** @var array<string, mixed> $data */
        $data = $this->requestor->request(
            'POST',
            '/messages',
            body: $params,
            headers: ['Idempotency-Key' => $idempotencyKey ?? self::uuid()],
            retryable: true,
        );

        return SendMessageResponse::fromArray($data);
    }

    public function get(string $id): Message
    {
        /** @var array<string, mixed> $data */
        $data = $this->requestor->request('GET', '/messages/' . rawurlencode($id));

        return Message::fromArray($data);
    }

    /**
     * One page of messages, newest first.
     *
     * @return Page<Message>
     */
    public function list(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('/messages', Message::fromArray(...), $limit, $cursor);
    }

    /**
     * Every message, newest first, fetching pages as you iterate.
     *
     * @return \Generator<int, Message>
     */
    public function listAll(?int $limit = null): \Generator
    {
        $cursor = null;
        do {
            $page = $this->list($limit, $cursor);
            yield from $page->data;
            $cursor = $page->nextCursor;
        } while ($cursor !== null);
    }

    /**
     * The message's lifecycle events, newest first.
     *
     * @return Page<MessageEvent>
     */
    public function events(string $id, ?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('/messages/' . rawurlencode($id) . '/events', MessageEvent::fromArray(...), $limit, $cursor);
    }

    /**
     * The message's delivery attempts (app push, SMS), newest first.
     *
     * @return Page<DeliveryEvent>
     */
    public function deliveryEvents(string $id, ?int $limit = null, ?string $cursor = null): Page
    {
        return $this->page('/messages/' . rawurlencode($id) . '/delivery-events', DeliveryEvent::fromArray(...), $limit, $cursor);
    }

    /**
     * @template T
     * @param callable(array<string, mixed>): T $build
     * @return Page<T>
     */
    private function page(string $path, callable $build, ?int $limit, ?string $cursor): Page
    {
        /** @var array{data: list<array<string, mixed>>, nextCursor?: string|null} $wire */
        $wire = $this->requestor->request('GET', $path, ['limit' => $limit, 'cursor' => $cursor]);

        return new Page(array_map($build, $wire['data']), $wire['nextCursor'] ?? null);
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
