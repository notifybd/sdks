<?php

declare(strict_types=1);

namespace NotifyBd\Exception;

/** 5xx: a problem on our side. Safe to retry. */
class ServerException extends ApiException
{
}
