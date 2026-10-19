=== Notify for WordPress ===
Contributors: notifybd
Tags: sms, otp, notifications, bangladesh, webhook
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send OTP and transactional messages through Notify: delivered to the recipient's app first, with SMS as a fallback.

== Description ==

Notify delivers OTP and transactional messages to the recipient's mobile app first and falls back to SMS only when the app cannot be reached, which keeps messaging costs down.

This plugin connects your WordPress site to Notify:

* **Send from code** with `notify_bd_send( $to, $body, $args )`, from your theme or another plugin.
* **Settings screen** for your API key, default sender ID and webhook secret, plus a test-message form.
* **Delivery status webhooks**: a signed endpoint that verifies every call and fires WordPress actions (`notify_bd_message_delivered`, `notify_bd_message_failed`, `notify_bd_message_status`).
* **Safe retries**: every send carries an idempotency key, so a retry never sends a message twice.

= Example =

    $result = notify_bd_send( '01712345678', 'Your code is 123456', array( 'type' => 'otp' ) );
    if ( is_wp_error( $result ) ) {
        error_log( $result->get_error_message() );
    }

= Reacting to delivery status =

    add_action( 'notify_bd_message_failed', function ( $payload ) {
        error_log( 'Message ' . $payload['messageId'] . ' failed: ' . $payload['reason'] );
    } );

= Keeping secrets out of the database =

    define( 'NOTIFY_BD_API_KEY', 'nk_live_...' );
    define( 'NOTIFY_BD_WEBHOOK_SECRET', 'whsec_...' );

== External services ==

This plugin sends the recipient number, message text and sender ID you provide to the Notify API (the endpoint set under Settings → Notify) so that Notify can deliver the message. It sends data only when your code, or the test form, asks it to send a message. See Notify's terms of service and privacy policy on your Notify account for how that data is handled.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/notify-bd`, or install it from the Plugins screen.
2. Activate it.
3. Go to Settings → Notify and enter your API key and default sender ID.
4. To receive delivery status, add the webhook URL shown on that screen as an endpoint in the Notify portal, then paste the signing secret back into the settings.

== Frequently Asked Questions ==

= Does it send marketing messages? =
No. Notify carries OTP and transactional messages only; promotional content is rejected by the API.

= Where do I get an API key? =
In the Notify portal, under API keys. Grant it the scopes to send and read messages.

== Changelog ==

= 0.1.0 =
* First release.
