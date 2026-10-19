<?php

declare(strict_types=1);

namespace NotifyBd\Exception;

/** 401: the API key is missing, invalid or revoked. */
class AuthenticationException extends ApiException
{
}
