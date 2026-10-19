<?php

declare(strict_types=1);

namespace NotifyBd\Exception;

/** 403: the key lacks the scope, or the account is frozen. */
class PermissionDeniedException extends ApiException
{
}
