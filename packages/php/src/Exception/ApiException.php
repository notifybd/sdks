<?php

declare(strict_types=1);

namespace NotifyBd\Exception;

/** The API answered with an error status. */
class ApiException extends NotifyException
{
    /**
     * @param list<array{path?: string, message?: string}>|null $details
     */
    public function __construct(
        public readonly int $status,
        /** Stable machine-readable code, e.g. VALIDATION_ERROR or RATE_LIMITED. */
        public readonly string $errorCode,
        string $message,
        /** Quote this when contacting support. */
        public readonly ?string $requestId = null,
        public readonly ?array $details = null,
        /** Seconds the API asked us to wait (429 only). */
        public readonly ?float $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }

    /**
     * @param list<array{path?: string, message?: string}>|null $details
     */
    public static function forStatus(
        int $status,
        string $errorCode,
        string $message,
        ?string $requestId = null,
        ?array $details = null,
        ?float $retryAfter = null,
    ): self {
        $class = match (true) {
            $status === 400 => ValidationException::class,
            $status === 401 => AuthenticationException::class,
            $status === 402 => InsufficientCreditsException::class,
            $status === 403 => PermissionDeniedException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 422 => UnprocessableException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($status, $errorCode, $message, $requestId, $details, $retryAfter);
    }
}
