<?php
/**
 * Plugin Name:       Notify for WordPress
 * Plugin URI:        https://github.com/notifybd/sdks/tree/main/packages/wordpress
 * Description:       Send OTP and transactional messages through Notify (app-first, SMS fallback) from WordPress, and receive delivery status webhooks.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Notify
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       notify-bd
 * Domain Path:       /languages
 *
 * @package Notify_BD
 */

defined( 'ABSPATH' ) || exit;

define( 'NOTIFY_BD_VERSION', '0.1.0' );
define( 'NOTIFY_BD_FILE', __FILE__ );
define( 'NOTIFY_BD_DIR', plugin_dir_path( __FILE__ ) );

require_once NOTIFY_BD_DIR . 'includes/class-notify-bd-signature.php';
require_once NOTIFY_BD_DIR . 'includes/class-notify-bd-options.php';
require_once NOTIFY_BD_DIR . 'includes/class-notify-bd-client.php';
require_once NOTIFY_BD_DIR . 'includes/class-notify-bd-webhook.php';
require_once NOTIFY_BD_DIR . 'includes/class-notify-bd-settings.php';
require_once NOTIFY_BD_DIR . 'includes/functions.php';

add_action(
	'plugins_loaded',
	static function () {
		( new Notify_BD_Webhook() )->register();
		if ( is_admin() ) {
			( new Notify_BD_Settings() )->register();
		}
	}
);
