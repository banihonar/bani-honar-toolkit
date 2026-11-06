<?php
/**
 * Link shortener module.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the shortener with the toolkit: hooks, settings, admin page and form handlers.
 */
final class BHTK_Module_Link_Shortener extends BHTK_Module {

	const PAGE = 'bhtk-links';

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'link-shortener';
	}

	/**
	 * Module name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Link Shortener', 'bani-honar-toolkit' );
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Shorten long links with your own domain, choose the code length and track clicks.', 'bani-honar-toolkit' );
	}

	/**
	 * Dashboard icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-admin-links';
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function default_settings() {
		return array(
			'length'   => 6,
			'redirect' => 302,
			'prefix'   => '',
		);
	}

	/**
	 * Clean the submitted settings.
	 *
	 * @param array $raw Submitted values.
	 * @return array
	 */
	public function sanitize_settings( $raw ) {
		$redirect = isset( $raw['redirect'] ) ? (int) $raw['redirect'] : 302;
		return array(
			'length'   => isset( $raw['length'] ) ? max( 1, min( BHTK_Links::MAX_LEN, (int) $raw['length'] ) ) : 6,
			'redirect' => in_array( $redirect, array( 301, 302, 307 ), true ) ? $redirect : 302,
			'prefix'   => isset( $raw['prefix'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $raw['prefix'] ) : '',
		);
	}

	/**
	 * This module has settings.
	 *
	 * @return bool
	 */
	public function has_settings() {
		return true;
	}

	/**
	 * Settings fields.
	 *
	 * @return void
	 */
	public function render_settings() {
		$s    = BHTK_Links::settings();
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		?>
		<div class="bhtk-form bhtk-form--settings">
			<div class="bhtk-field">
				<label for="bhtk_length"><?php esc_html_e( 'Default code length', 'bani-honar-toolkit' ); ?></label>
				<input type="number" id="bhtk_length" name="<?php echo esc_attr( $this->field_name( 'length' ) ); ?>" min="1" max="<?php echo absint( BHTK_Links::MAX_LEN ); ?>" value="<?php echo absint( $s['length'] ); ?>" />
				<small><?php esc_html_e( 'This value is pre-filled when you create a link.', 'bani-honar-toolkit' ); ?></small>
			</div>

			<div class="bhtk-field">
				<label for="bhtk_redirect"><?php esc_html_e( 'Redirect type', 'bani-honar-toolkit' ); ?></label>
				<select id="bhtk_redirect" name="<?php echo esc_attr( $this->field_name( 'redirect' ) ); ?>">
					<option value="302" <?php selected( $s['redirect'], 302 ); ?>><?php esc_html_e( '302 - temporary (recommended, accurate click counts)', 'bani-honar-toolkit' ); ?></option>
					<option value="307" <?php selected( $s['redirect'], 307 ); ?>><?php esc_html_e( '307 - temporary (keeps the request method)', 'bani-honar-toolkit' ); ?></option>
					<option value="301" <?php selected( $s['redirect'], 301 ); ?>><?php esc_html_e( '301 - permanent (browsers cache it, fewer clicks are counted)', 'bani-honar-toolkit' ); ?></option>
				</select>
			</div>

			<div class="bhtk-field">
				<label for="bhtk_prefix"><?php esc_html_e( 'Path prefix (optional)', 'bani-honar-toolkit' ); ?></label>
				<input type="text" id="bhtk_prefix" class="bhtk-ltr" name="<?php echo esc_attr( $this->field_name( 'prefix' ) ); ?>" maxlength="20" value="<?php echo esc_attr( $s['prefix'] ); ?>" placeholder="go" />
				<small>
					<?php
					printf(
						/* translators: %s: example short link with a prefix, for example example.com/go/Ab3xK9 */
						esc_html__( 'With the prefix "go" links look like %s. Links without a prefix always keep working, but if you remove or change a prefix, links already shared with it stop working.', 'bani-honar-toolkit' ),
						'<span class="bhtk-ltr">' . esc_html( $host ) . '/go/Ab3xK9</span>'
					);
					?>
				</small>
			</div>
		</div>
		<?php
	}

	/**
	 * Admin pages of this module.
	 *
	 * @return array
	 */
	public function admin_pages() {
		return array(
			array(
				'slug'     => self::PAGE,
				'title'    => __( 'Link Shortener', 'bani-honar-toolkit' ),
				'callback' => array( 'BHTK_Links_Page', 'render' ),
			),
		);
	}

	/**
	 * Install the table.
	 *
	 * @return void
	 */
	public function install() {
		BHTK_Links::install();
	}

	/**
	 * Front-end hook that performs the redirect.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'template_redirect', array( 'BHTK_Links', 'maybe_redirect' ), 1 );
	}

	/**
	 * Form handlers.
	 *
	 * @return void
	 */
	public function boot_admin() {
		add_action( 'admin_post_bhtk_link_create', array( $this, 'handle_create' ) );
		add_action( 'admin_post_bhtk_link_update', array( $this, 'handle_update' ) );
		add_action( 'admin_post_bhtk_link_delete', array( $this, 'handle_delete' ) );
	}

	/**
	 * Create a link.
	 *
	 * @return void
	 */
	public function handle_create() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'bhtk_link_create', '_bhtk_nonce' );

		$url    = isset( $_POST['bhtk_url'] ) ? wp_unslash( $_POST['bhtk_url'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned by BHTK_Links::normalize_url().
		$length = isset( $_POST['bhtk_length'] ) ? absint( $_POST['bhtk_length'] ) : BHTK_Links::settings()['length'];
		$custom = isset( $_POST['bhtk_custom'] ) ? sanitize_text_field( wp_unslash( $_POST['bhtk_custom'] ) ) : '';
		$note   = isset( $_POST['bhtk_note'] ) ? sanitize_text_field( wp_unslash( $_POST['bhtk_note'] ) ) : '';

		$result = BHTK_Links::create( $url, $length, $custom, $note );

		if ( is_wp_error( $result ) ) {
			BHTK_Admin::flash( 'error', $result->get_error_message() );
		} else {
			$row = BHTK_Links::get( $result );
			BHTK_Admin::flash( 'success', __( 'Short link created:', 'bani-honar-toolkit' ), $row ? BHTK_Links::short_url( $row->code ) : '' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Update a link.
	 *
	 * @return void
	 */
	public function handle_update() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		$id = isset( $_POST['bhtk_id'] ) ? absint( $_POST['bhtk_id'] ) : 0;
		check_admin_referer( 'bhtk_link_update_' . $id, '_bhtk_nonce' );

		$url  = isset( $_POST['bhtk_url'] ) ? wp_unslash( $_POST['bhtk_url'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned by BHTK_Links::normalize_url().
		$note = isset( $_POST['bhtk_note'] ) ? sanitize_text_field( wp_unslash( $_POST['bhtk_note'] ) ) : '';

		$result = BHTK_Links::update( $id, $url, $note );
		if ( is_wp_error( $result ) ) {
			BHTK_Admin::flash( 'error', $result->get_error_message() );
		} else {
			BHTK_Admin::flash( 'success', __( 'Changes saved.', 'bani-honar-toolkit' ) );
		}
		BHTK_Admin::back( self::PAGE );
	}

	/**
	 * Delete a link.
	 *
	 * @return void
	 */
	public function handle_delete() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		$id = isset( $_POST['bhtk_id'] ) ? absint( $_POST['bhtk_id'] ) : 0;
		check_admin_referer( 'bhtk_link_delete_' . $id, '_bhtk_nonce' );

		BHTK_Links::delete( $id );
		BHTK_Admin::flash( 'success', __( 'The link was deleted.', 'bani-honar-toolkit' ) );
		BHTK_Admin::back( self::PAGE );
	}
}
