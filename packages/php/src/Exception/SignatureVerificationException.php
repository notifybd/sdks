<?php

declare(strict_types=1);

namespace NotifyBd\Exception;

/** A webhook failed verification. */
class SignatureVerificationException extends NotifyException
{
    public const MALFORMED_HEADER = 'malformed_header';
    public const TIMESTAMP_OUTSIDE_TOLERANCE = 'timestamp_outside_tolerance';
    public const SIGNATURE_MISMATCH = 'signature_mismatch';
    public const INVALID_PAYLOAD = 'invalid_payload';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
