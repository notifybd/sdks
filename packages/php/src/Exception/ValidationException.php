<?php

declare(strict_types=1);

namespace NotifyBd\Exception;

/** 400: the request failed validation. See $details for the offending fields. */
class ValidationException extends ApiException
{
}
