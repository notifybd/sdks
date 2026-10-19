<?php
/**
 * Settings → Notify: credentials, default sender ID, webhook URL, a test-message form and the most
 * recent delivery events.
 *
 * @package Notify_BD
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Settings → Notify screen and its test-message handler.
 */
class Notify_BD_Settings {

	const PAGE         = 'notify-bd';
	const GROUP        = 'notify_bd';
	const TEST_ACTION  = 'notify_bd_send_test';
	const CAPABILITY   = 'manage_options';
	const NOTICE_QUERY = 'notify_bd_notice';

	/**
	 * Hooks the screen, the settings and the test-message handler into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( $this, 'send_test' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NOTIFY_BD_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds Settings → Notify.
	 *
	 * @return void
	 */
	public function add_page() {
		add_options_page(
			__( 'Notify', 'notify-bd' ),
			__( 'Notify', 'notify-bd' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( array $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'notify-bd' ) . '</a>' );

		return $links;
	}

	/**
	 * Registers the option and its sanitizer.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			Notify_BD_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * A secret left blank keeps the stored value, so the key is never echoed back into the page.
	 *
	 * @param mixed $input Submitted values.
	 * @return array<string,string>
	 */
	public function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = Notify_BD_Options::all();
		$text    = static function ( $key ) use ( $input ) {
			return isset( $input[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $input[ $key ] ) ) ) : '';
		};

		$base_url = isset( $input['base_url'] ) ? trim( esc_url_raw( wp_unslash( $input['base_url'] ), array( 'https', 'http' ) ) ) : '';

		return array(
			'api_key'        => '' !== $text( 'api_key' ) ? $text( 'api_key' ) : $current['api_key'],
			'webhook_secret' => '' !== $text( 'webhook_secret' ) ? $text( 'webhook_secret' ) : $current['webhook_secret'],
			'sender_id'      => $text( 'sender_id' ),
			'base_url'       => $base_url,
		);
	}

	/**
	 * Renders Settings → Notify.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$options = Notify_BD_Options::all();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php $this->render_notice(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<?php
					$this->secret_row( 'api_key', __( 'API key', 'notify-bd' ), $options['api_key'], 'NOTIFY_BD_API_KEY', __( 'Create one in the Notify portal under API keys (scopes: send and read messages).', 'notify-bd' ) );
					?>
					<tr>
						<th scope="row"><label for="notify-bd-sender"><?php esc_html_e( 'Default sender ID', 'notify-bd' ); ?></label></th>
						<td>
							<input type="text" id="notify-bd-sender" class="regular-text" name="<?php echo esc_attr( Notify_BD_Options::OPTION ); ?>[sender_id]" value="<?php echo esc_attr( $options['sender_id'] ); ?>" maxlength="32" />
							<p class="description"><?php esc_html_e( 'Must be an approved sender ID on your Notify account.', 'notify-bd' ); ?></p>
						</td>
					</tr>
					<?php
					$this->secret_row( 'webhook_secret', __( 'Webhook signing secret', 'notify-bd' ), $options['webhook_secret'], 'NOTIFY_BD_WEBHOOK_SECRET', __( 'Shown once when you create the webhook endpoint in the portal.', 'notify-bd' ) );
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook URL', 'notify-bd' ); ?></th>
						<td>
							<code><?php echo esc_html( Notify_BD_Webhook::url() ); ?></code>
							<p class="description"><?php esc_html_e( 'Add this URL as a webhook endpoint in the Notify portal to receive delivery status.', 'notify-bd' ); ?></p>
						</td>
					</tr>
					<?php if ( ! Notify_BD_Options::is_locked( 'base_url' ) ) : ?>
					<tr>
						<th scope="row"><label for="notify-bd-base"><?php esc_html_e( 'API base URL', 'notify-bd' ); ?></label></th>
						<td>
							<input type="url" id="notify-bd-base" class="regular-text code" name="<?php echo esc_attr( Notify_BD_Options::OPTION ); ?>[base_url]" value="<?php echo esc_attr( $options['base_url'] ); ?>" placeholder="<?php echo esc_attr( Notify_BD_Options::DEFAULT_API_URL ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave empty to use the production API.', 'notify-bd' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Send a test message', 'notify-bd' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::TEST_ACTION ); ?>" />
				<?php wp_nonce_field( self::TEST_ACTION ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="notify-bd-to"><?php esc_html_e( 'Mobile number', 'notify-bd' ); ?></label></th>
						<td><input type="tel" id="notify-bd-to" class="regular-text" name="to" placeholder="01712345678" required /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Send test', 'notify-bd' ), 'secondary' ); ?>
			</form>

			<?php $this->render_recent(); ?>
		</div>
		<?php
	}

	/**
	 * Prints one password row, or a note when a wp-config.php constant supplies the value.
	 *
	 * @param string $key      Option key.
	 * @param string $label    Row label.
	 * @param string $stored   Stored value (never printed).
	 * @param string $constant wp-config.php constant that overrides it.
	 * @param string $help     Help text.
	 */
	private function secret_row( $key, $label, $stored, $constant, $help ) {
		$id = 'notify-bd-' . $key;
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<?php if ( Notify_BD_Options::is_locked( $key ) ) : ?>
					<p>
						<?php
						/* translators: %s: PHP constant name. */
						echo esc_html( sprintf( __( 'Set by the %s constant in wp-config.php.', 'notify-bd' ), $constant ) );
						?>
					</p>
				<?php else : ?>
					<input type="password" id="<?php echo esc_attr( $id ); ?>" class="regular-text" autocomplete="off" name="<?php echo esc_attr( Notify_BD_Options::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="" placeholder="<?php echo esc_attr( '' !== $stored ? __( '•••••••• saved — leave blank to keep', 'notify-bd' ) : '' ); ?>" />
					<p class="description">
						<?php
						echo esc_html( $help ) . ' ';
						/* translators: %s: PHP constant name. */
						echo esc_html( sprintf( __( 'Or define %s in wp-config.php to keep it out of the database.', 'notify-bd' ), $constant ) );
						?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Handles the test-message form: checks the capability and nonce, then sends.
	 *
	 * @return void
	 */
	public function send_test() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'notify-bd' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::TEST_ACTION );

		$to = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		/* translators: %s: site name. */
		$body   = sprintf( __( 'Test message from %s', 'notify-bd' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$result = notify_bd_send( $to, $body, array( 'type' => 'transactional' ) );

		if ( is_wp_error( $result ) ) {
			$notice = array( 'error', $result->get_error_message() );
		} else {
			/* translators: %s: message id. */
			$notice = array( 'success', sprintf( __( 'Accepted for delivery (message %s).', 'notify-bd' ), (string) ( isset( $result['id'] ) ? $result['id'] : '' ) ) );
		}
		set_transient( 'notify_bd_notice_' . get_current_user_id(), $notice, 60 );

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Shows the result of the last test message, once.
	 *
	 * @return void
	 */
	private function render_notice() {
		$key    = 'notify_bd_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( 'success' === $notice[0] ? 'success' : 'error' ),
			esc_html( $notice[1] )
		);
	}

	/**
	 * Lists the most recent delivery events received by the webhook.
	 *
	 * @return void
	 */
	private function render_recent() {
		$recent = get_option( Notify_BD_Webhook::RECENT_OPTION, array() );
		if ( ! is_array( $recent ) || array() === $recent ) {
			return;
		}
		?>
		<hr />
		<h2><?php esc_html_e( 'Recent delivery events', 'notify-bd' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'notify-bd' ); ?></th>
					<th><?php esc_html_e( 'Event', 'notify-bd' ); ?></th>
					<th><?php esc_html_e( 'Message', 'notify-bd' ); ?></th>
					<th><?php esc_html_e( 'Channel', 'notify-bd' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'notify-bd' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['at'] ); ?></td>
						<td><?php echo esc_html( $row['name'] ); ?></td>
						<td><code><?php echo esc_html( $row['message'] ); ?></code></td>
						<td><?php echo esc_html( $row['channel'] ); ?></td>
						<td><?php echo esc_html( $row['reason'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
