<?php

declare(strict_types=1);

namespace NotifyBd;

use NotifyBd\Exception\SignatureVerificationException;
use NotifyBd\Model\WebhookEvent;

/**
 * Verifies the `X-Notify-Signature` header (`t=<unix>,v1=<hex>`): HMAC-SHA256 of
 * `"<t>.<raw body>"` with your webhook secret.
 *
 * Pass the RAW request body (`file_get_contents('php://input')`), not a re-encoded one: changing
 * the bytes breaks the signature.
 */
final class Webhook
{
    public const SIGNATURE_HEADER = 'X-Notify-Signature';
    public const DEFAULT_TOLERANCE = 300;

    /**
     * @throws SignatureVerificationException when the header is malformed, the timestamp is
     *         outside the tolerance window (replay protection) or no signature matches
     */
    public static function constructEvent(
        string $rawBody,
        ?string $header,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): WebhookEvent {
        self::verify($rawBody, $header, $secret, $tolerance, $now);

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !isset($decoded['payload']) || !is_array($decoded['payload'])) {
                throw new \UnexpectedValueException('not an event');
            }

            return WebhookEvent::fromArray($decoded);
        } catch (\JsonException | \UnexpectedValueException | \TypeError | \ErrorException) {
            throw new SignatureVerificationException(
                SignatureVerificationException::INVALID_PAYLOAD,
                'The webhook body is not a valid event',
            );
        }
    }

    /** Like constructEvent() but only verifies; throws on failure. */
    public static function verify(
        string $rawBody,
        ?string $header,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): void {
        $parsed = self::parse($header);
        if ($parsed === null) {
            throw new SignatureVerificationException(
                SignatureVerificationException::MALFORMED_HEADER,
                'The X-Notify-Signature header is missing or malformed',
            );
        }
        [$timestamp, $signatures] = $parsed;

        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            throw new SignatureVerificationException(
                SignatureVerificationException::TIMESTAMP_OUTSIDE_TOLERANCE,
                'The webhook timestamp is outside the allowed window',
            );
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        // More than one v1 appears while a secret is being rotated; any match is enough.
        foreach ($signatures as $signature) {
            if (hash_equals($expected, strtolower($signature))) {
                return;
            }
        }

        throw new SignatureVerificationException(
            SignatureVerificationException::SIGNATURE_MISMATCH,
            'No signature matches the payload',
        );
    }

    /** @return array{int, list<string>}|null */
    private static function parse(?string $header): ?array
    {
        if ($header === null || $header === '') {
            return null;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) !== 2) {
                continue;
            }
            $key = trim($pair[0]);
            $value = trim($pair[1]);
            if ($key === 't' && preg_match('/^\d+$/', $value) === 1) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        return $timestamp !== null && $signatures !== [] ? [$timestamp, $signatures] : null;
    }
}
