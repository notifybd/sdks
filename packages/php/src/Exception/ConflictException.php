<?php

declare(strict_types=1);

namespace NotifyBd\Exception;

/** 409: the idempotency key was already used with a different request. */
class ConflictException extends ApiException
{
}
