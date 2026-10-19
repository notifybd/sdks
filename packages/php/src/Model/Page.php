<?php

declare(strict_types=1);

namespace NotifyBd\Model;

/** @template T */
final class Page
{
    /** @param list<T> $data */
    public function __construct(
        public readonly array $data,
        /** Pass as `cursor` for the next page; null on the last page. */
        public readonly ?string $nextCursor,
    ) {
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }
}
