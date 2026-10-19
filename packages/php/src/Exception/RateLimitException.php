<?php

declare(strict_types=1);

namespace NotifyBd\Exception;

/** 429: slow down. $retryAfter says for how long. */
class RateLimitException extends ApiException
{
}
