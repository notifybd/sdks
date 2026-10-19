<?php
/**
 * Webhook signature verification.
 *
 * Pure PHP (no WordPress calls) so it can be unit-tested against the vectors shared by every Notify
 * SDK. The header is `t=<unix>,v1=<hex>`; the signature is HMAC-SHA256 of "<t>.<raw body>".
 *
 * @package Notify_BD
 */

defined( 'ABSPATH' ) || defined( 'NOTIFY_BD_TESTING' ) || exit;

class Notify_BD_Signature {

	const MALFORMED_HEADER            = 'malformed_header';
	const TIMESTAMP_OUTSIDE_TOLERANCE = 'timestamp_outside_tolerance';
	const SIGNATURE_MISMATCH          = 'signature_mismatch';

	/**
	 * Checks a webhook's authenticity and freshness.
	 *
	 * @param string      $raw_body  The request body exactly as received.
	 * @param string|null $header    Value of the X-Notify-Signature header.
	 * @param string      $secret    The endpoint's signing secret.
	 * @param int         $tolerance Maximum clock skew / age in seconds (replay protection).
	 * @param int|null    $now       Current unix time; for tests.
	 * @return true|string True when valid, otherwise one of the failure constants above.
	 */
	public static function verify( $raw_body, $header, $secret, $tolerance = 300, $now = null ) {
		$parsed = self::parse( $header );
		if ( null === $parsed ) {
			return self::MALFORMED_HEADER;
		}

		list( $timestamp, $signatures ) = $parsed;
		$now                            = null === $now ? time() : $now;
		if ( abs( $now - $timestamp ) > $tolerance ) {
			return self::TIMESTAMP_OUTSIDE_TOLERANCE;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );

		// More than one v1 appears while a secret is being rotated; any match is enough.
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, strtolower( $signature ) ) ) {
				return true;
			}
		}

		return self::SIGNATURE_MISMATCH;
	}

	/**
	 * @param string|null $header Raw header value.
	 * @return array{0:int,1:string[]}|null
	 */
	private static function parse( $header ) {
		if ( ! is_string( $header ) || '' === $header ) {
			return null;
		}

		$timestamp  = null;
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', $part, 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			$key   = trim( $pair[0] );
			$value = trim( $pair[1] );
			if ( 't' === $key && 1 === preg_match( '/^\d+$/', $value ) ) {
				$timestamp = (int) $value;
			} elseif ( 'v1' === $key && '' !== $value ) {
				$signatures[] = $value;
			}
		}

		return ( null !== $timestamp && array() !== $signatures ) ? array( $timestamp, $signatures ) : null;
	}
}
