<?php

declare(strict_types=1);

namespace NotifyBd\Resource;

use NotifyBd\Http\Requestor;
use NotifyBd\Model\SenderId;

final class Senders
{
    /** @internal */
    public function __construct(private readonly Requestor $requestor)
    {
    }

    /** Requests a sender ID. It stays `pending` until an operator approves it. */
    public function create(string $value): SenderId
    {
        /** @var array<string, mixed> $data */
        $data = $this->requestor->request('POST', '/senders', body: ['value' => $value]);

        return SenderId::fromArray($data);
    }

    /** @return list<SenderId> */
    public function list(): array
    {
        /** @var list<array<string, mixed>> $data */
        $data = $this->requestor->request('GET', '/senders');

        return array_map(SenderId::fromArray(...), $data);
    }
}
