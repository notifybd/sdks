<?php
/**
 * Settings storage.
 *
 * The API key and webhook secret can be defined as constants in wp-config.php, which keeps them out
 * of the database and takes precedence over the settings screen:
 *
 *     define( 'NOTIFY_BD_API_KEY', 'nk_live_...' );
 *     define( 'NOTIFY_BD_WEBHOOK_SECRET', 'whsec_...' );
 *
 * @package Notify_BD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads the plugin's settings, with wp-config.php constants taking precedence.
 */
class Notify_BD_Options {

	const OPTION          = 'notify_bd_settings';
	const DEFAULT_API_URL = 'https://api.notify.example/v1';

	/**
	 * All stored settings, with defaults filled in.
	 *
	 * @return array<string,string>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		return wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			array(
				'api_key'        => '',
				'webhook_secret' => '',
				'sender_id'      => '',
				'base_url'       => '',
			)
		);
	}

	/**
	 * The API key: the NOTIFY_BD_API_KEY constant if defined, otherwise the stored setting.
	 *
	 * @return string
	 */
	public static function api_key() {
		return defined( 'NOTIFY_BD_API_KEY' ) ? (string) NOTIFY_BD_API_KEY : self::all()['api_key'];
	}

	/**
	 * The webhook signing secret: the constant if defined, otherwise the stored setting.
	 *
	 * @return string
	 */
	public static function webhook_secret() {
		return defined( 'NOTIFY_BD_WEBHOOK_SECRET' ) ? (string) NOTIFY_BD_WEBHOOK_SECRET : self::all()['webhook_secret'];
	}

	/**
	 * The default sender ID used when a send does not name one.
	 *
	 * @return string
	 */
	public static function sender_id() {
		return self::all()['sender_id'];
	}

	/**
	 * The API base URL: the NOTIFY_BD_BASE_URL constant, the stored setting, or the production default.
	 *
	 * @return string
	 */
	public static function base_url() {
		$url = defined( 'NOTIFY_BD_BASE_URL' ) ? (string) NOTIFY_BD_BASE_URL : self::all()['base_url'];

		return '' !== $url ? untrailingslashit( $url ) : self::DEFAULT_API_URL;
	}

	/**
	 * True when the value comes from wp-config.php and cannot be edited on screen.
	 *
	 * @param string $key Option key (api_key, webhook_secret or base_url).
	 * @return bool
	 */
	public static function is_locked( $key ) {
		$constants = array(
			'api_key'        => 'NOTIFY_BD_API_KEY',
			'webhook_secret' => 'NOTIFY_BD_WEBHOOK_SECRET',
			'base_url'       => 'NOTIFY_BD_BASE_URL',
		);

		return isset( $constants[ $key ] ) && defined( $constants[ $key ] );
	}
}
