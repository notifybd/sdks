<?php
/**
 * Removes the plugin's data when it is deleted from the Plugins screen.
 *
 * @package Notify_BD
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'notify_bd_settings' );
delete_option( 'notify_bd_recent_events' );
