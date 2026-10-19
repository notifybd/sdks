<?php
/**
 * A small client for the Notify REST API, built on the WordPress HTTP API so it honours the site's
 * proxy and SSL settings and needs no Composer dependencies.
 *
 * @package Notify_BD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Client for the Notify REST API.
 */
class Notify_BD_Client {

	const MAX_RETRIES = 2;

	/**
	 * The API key sent as a bearer token.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * The API base URL, without a trailing slash.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Creates a client; both arguments default to the saved settings.
	 *
	 * @param string|null $api_key  API key.
	 * @param string|null $base_url API base URL.
	 */
	public function __construct( $api_key = null, $base_url = null ) {
		$this->api_key  = null === $api_key ? Notify_BD_Options::api_key() : $api_key;
		$this->base_url = null === $base_url ? Notify_BD_Options::base_url() : untrailingslashit( $base_url );
	}

	/**
	 * Submits a message. Returns once it is accepted (HTTP 202); delivery is asynchronous.
	 *
	 * Every call carries an idempotency key (the caller's, or a generated one), so a retry after a
	 * network error or a 5xx can never send the message twice.
	 *
	 * @param array<string,string> $params          to, body, messageType, senderId, reference (optional).
	 * @param string|null          $idempotency_key Reuse the same key to safely repeat a send.
	 * @return array<string,mixed>|WP_Error The accepted envelope, or a WP_Error.
	 */
	public function send( array $params, $idempotency_key = null ) {
		return $this->request(
			'POST',
			'/messages',
			$params,
			array( 'Idempotency-Key' => $idempotency_key ? $idempotency_key : wp_generate_uuid4() ),
			true
		);
	}

	/**
	 * Fetches a message by id.
	 *
	 * @param string $id Message id.
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_message( $id ) {
		return $this->request( 'GET', '/messages/' . rawurlencode( $id ) );
	}

	/**
	 * Sends one request, retrying what is safe to retry.
	 *
	 * @param string                   $method    HTTP method.
	 * @param string                   $path      Path below the base URL.
	 * @param array<string,mixed>|null $body      JSON body.
	 * @param array<string,string>     $headers   Extra headers.
	 * @param bool|null                $retryable Whether a retry is safe; defaults to GET only.
	 * @return array<string,mixed>|WP_Error
	 */
	private function request( $method, $path, $body = null, array $headers = array(), $retryable = null ) {
		if ( '' === $this->api_key ) {
			return new WP_Error( 'notify_bd_no_api_key', __( 'Notify is not configured: add an API key in Settings → Notify.', 'notify-bd' ) );
		}

		$retryable = null === $retryable ? 'GET' === $method : $retryable;
		$args      = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array_merge(
				array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'notify-wordpress/' . NOTIFY_BD_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
				),
				$headers
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		for ( $attempt = 0; ; $attempt++ ) {
			$response = wp_remote_request( $this->base_url . $path, $args );

			if ( is_wp_error( $response ) ) {
				if ( $retryable && $attempt < self::MAX_RETRIES ) {
					usleep( $this->backoff( $attempt ) );
					continue;
				}

				return new WP_Error( 'notify_bd_connection', $response->get_error_message() );
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$data   = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $status >= 200 && $status < 300 ) {
				return is_array( $data ) ? $data : array();
			}

			if ( $retryable && $attempt < self::MAX_RETRIES && in_array( $status, array( 408, 429, 500, 502, 503, 504 ), true ) ) {
				usleep( $this->retry_delay( $response, $attempt ) );
				continue;
			}

			return $this->error( $status, $data, $response );
		}
	}

	/**
	 * Builds a WP_Error from an API error response.
	 *
	 * @param int        $status   HTTP status.
	 * @param mixed      $data     Decoded body.
	 * @param array|null $response WordPress HTTP response.
	 * @return WP_Error
	 */
	private function error( $status, $data, $response ) {
		$error      = is_array( $data ) && isset( $data['error'] ) && is_array( $data['error'] ) ? $data['error'] : array();
		$code       = isset( $error['code'] ) && is_string( $error['code'] ) ? strtolower( $error['code'] ) : 'unknown';
		$message    = isset( $error['message'] ) && is_string( $error['message'] ) ? $error['message'] : sprintf( 'Request failed with status %d', $status );
		$request_id = isset( $error['requestId'] ) ? $error['requestId'] : wp_remote_retrieve_header( $response, 'x-request-id' );

		return new WP_Error(
			'notify_bd_' . $code,
			$message,
			array(
				'status'     => $status,
				'request_id' => $request_id,
				'details'    => isset( $error['details'] ) ? $error['details'] : null,
			)
		);
	}

	/**
	 * Jittered exponential delay before a retry, in microseconds.
	 *
	 * @param int $attempt Zero-based attempt number.
	 * @return int
	 */
	private function backoff( $attempt ) {
		$ceiling = (int) min( 4000000, 500000 * pow( 2, $attempt ) );

		return (int) ( $ceiling / 2 + wp_rand( 0, (int) ( $ceiling / 2 ) ) );
	}

	/**
	 * Delay before retrying a response: Retry-After when present (capped), otherwise backoff.
	 *
	 * @param array $response WordPress HTTP response.
	 * @param int   $attempt  Zero-based attempt number.
	 * @return int Microseconds.
	 */
	private function retry_delay( $response, $attempt ) {
		$header = wp_remote_retrieve_header( $response, 'retry-after' );
		if ( is_numeric( $header ) && (float) $header > 0 ) {
			// A web request must not hang: cap the wait well below PHP's time limit.
			return (int) ( min( (float) $header, 5.0 ) * 1000000 );
		}

		return $this->backoff( $attempt );
	}
}
