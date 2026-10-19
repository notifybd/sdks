<?php

declare(strict_types=1);

namespace NotifyBd\Exception;

/** The request never produced an HTTP response (DNS, connection reset, timeout). */
class ApiConnectionException extends NotifyException
{
}
