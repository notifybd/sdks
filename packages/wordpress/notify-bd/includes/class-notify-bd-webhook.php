<?php
/**
 * Receives Notify's signed delivery-status webhooks.
 *
 * Endpoint: POST {site}/wp-json/notify-bd/v1/webhook. Register it in the Notify portal with the
 * signing secret you then enter under Settings → Notify. Authentication is the signature itself, so
 * the route is public in the WordPress permission sense.
 *
 * @package Notify_BD
 */

defined( 'ABSPATH' ) || exit;

/**
 * The REST route that receives and verifies Notify's webhooks.
 */
class Notify_BD_Webhook {

	const ROUTE_NAMESPACE = 'notify-bd/v1';
	const ROUTE           = '/webhook';
	const RECENT_OPTION   = 'notify_bd_recent_events';
	const RECENT_LIMIT    = 20;

	/**
	 * Hooks the route registration into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * The public URL to register in the Notify portal.
	 *
	 * @return string
	 */
	public static function url() {
		return rest_url( self::ROUTE_NAMESPACE . self::ROUTE );
	}

	/**
	 * Registers POST /notify-bd/v1/webhook.
	 *
	 * @return void
	 */
	public function register_route() {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				// Public by design: the HMAC signature authenticates the caller.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verifies the signature, ignores repeats and fires the status actions.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		$secret = Notify_BD_Options::webhook_secret();
		if ( '' === $secret ) {
			return new WP_Error( 'notify_bd_no_secret', 'Webhook signing secret is not configured.', array( 'status' => 503 ) );
		}

		$raw    = $request->get_body();
		$result = Notify_BD_Signature::verify( $raw, $request->get_header( 'x_notify_signature' ), $secret );
		if ( true !== $result ) {
			return new WP_Error( 'notify_bd_bad_signature', 'Invalid signature.', array( 'status' => 400 ) );
		}

		$event = json_decode( $raw, true );
		if ( ! is_array( $event ) || empty( $event['eventId'] ) || empty( $event['eventName'] ) || ! isset( $event['payload'] ) || ! is_array( $event['payload'] ) ) {
			return new WP_Error( 'notify_bd_bad_payload', 'Invalid event.', array( 'status' => 400 ) );
		}

		// Deliveries are at-least-once: act on each event id only once.
		$seen_key = 'notify_bd_seen_' . md5( (string) $event['eventId'] );
		if ( false === get_transient( $seen_key ) ) {
			set_transient( $seen_key, 1, DAY_IN_SECONDS );
			$this->remember( $event );

			/**
			 * Fires once for each delivery-status event (message.delivered or message.failed).
			 *
			 * @param array<string,mixed> $payload   messageId, status, channel, reason.
			 * @param array<string,mixed> $event     The full event, including eventId and occurredAt.
			 */
			do_action( 'notify_bd_message_status', $event['payload'], $event );

			if ( 'message.failed' === $event['eventName'] ) {
				do_action( 'notify_bd_message_failed', $event['payload'], $event );
			} elseif ( 'message.delivered' === $event['eventName'] ) {
				do_action( 'notify_bd_message_delivered', $event['payload'], $event );
			}
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Keeps the last few events for the settings screen.
	 *
	 * @param array<string,mixed> $event Event.
	 */
	private function remember( array $event ) {
		$recent = get_option( self::RECENT_OPTION, array() );
		$recent = is_array( $recent ) ? $recent : array();
		array_unshift(
			$recent,
			array(
				'at'      => isset( $event['occurredAt'] ) ? sanitize_text_field( $event['occurredAt'] ) : '',
				'name'    => sanitize_text_field( $event['eventName'] ),
				'message' => isset( $event['payload']['messageId'] ) ? sanitize_text_field( $event['payload']['messageId'] ) : '',
				'channel' => isset( $event['payload']['channel'] ) ? sanitize_text_field( (string) $event['payload']['channel'] ) : '',
				'reason'  => isset( $event['payload']['reason'] ) ? sanitize_text_field( (string) $event['payload']['reason'] ) : '',
			)
		);
		update_option( self::RECENT_OPTION, array_slice( $recent, 0, self::RECENT_LIMIT ), false );
	}
}
