<?php
/**
 * Public API for themes and other plugins.
 *
 * @package Notify_BD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends a message through Notify: to the recipient's app first, by SMS if the app cannot be reached.
 *
 *     $result = notify_bd_send( '01712345678', 'Your code is 123456', array( 'type' => 'otp' ) );
 *     if ( is_wp_error( $result ) ) {
 *         error_log( $result->get_error_message() );
 *     }
 *
 * @param string               $to   Recipient, in any common Bangladeshi format.
 * @param string               $body Message text (up to 1000 characters). Marketing content is rejected.
 * @param array<string,string> $args {
 *     Optional.
 *
 *     @type string $type            'otp' or 'transactional'. Default 'transactional'.
 *     @type string $sender_id       An approved sender ID. Default: the one in Settings → Notify.
 *     @type string $reference       Your own reference (e.g. an order id), up to 128 characters.
 *     @type string $idempotency_key Reuse to make a repeated call safe.
 * }
 * @return array<string,mixed>|WP_Error The accepted envelope (`id`, `status`, ...) or a WP_Error whose
 *                                      data holds `status` and `request_id`.
 */
function notify_bd_send( $to, $body, array $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'type'            => 'transactional',
			'sender_id'       => Notify_BD_Options::sender_id(),
			'reference'       => '',
			'idempotency_key' => '',
		)
	);

	$params = array(
		'to'          => (string) $to,
		'body'        => (string) $body,
		'messageType' => 'otp' === $args['type'] ? 'otp' : 'transactional',
		'senderId'    => (string) $args['sender_id'],
	);
	if ( '' !== $args['reference'] ) {
		$params['reference'] = (string) $args['reference'];
	}
	if ( '' === $params['senderId'] ) {
		return new WP_Error( 'notify_bd_no_sender', __( 'No sender ID. Pass sender_id or set a default in Settings → Notify.', 'notify-bd' ) );
	}

	/**
	 * Filters the request before it is sent.
	 *
	 * @param array<string,string> $params The Notify API request body.
	 */
	$params = apply_filters( 'notify_bd_send_params', $params );

	$result = ( new Notify_BD_Client() )->send( $params, '' !== $args['idempotency_key'] ? $args['idempotency_key'] : null );

	if ( is_wp_error( $result ) ) {
		/**
		 * Fires when a send is refused or fails.
		 *
		 * @param WP_Error             $error  The error.
		 * @param array<string,string> $params The request body.
		 */
		do_action( 'notify_bd_send_failed', $result, $params );
	} else {
		/**
		 * Fires when Notify accepted a message for delivery.
		 *
		 * @param array<string,mixed>  $result The accepted envelope.
		 * @param array<string,string> $params The request body.
		 */
		do_action( 'notify_bd_message_accepted', $result, $params );
	}

	return $result;
}
