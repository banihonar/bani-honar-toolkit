<?php
/**
 * Page Shield module: deterrents against casual copying of the page.
 *
 * Everything here is a deterrent. A browser has to receive the HTML, CSS and
 * JavaScript of a page, so a determined person can always read them. PHP code
 * never reaches the browser and stays private.
 *
 * Only options that have no effect on search engines are offered. The module id
 * stays "code-shield" so settings saved by earlier builds keep working.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocks the right-click menu, the usual developer shortcuts and image dragging,
 * and hides WordPress version details.
 */
final class BHTK_Module_Code_Shield extends BHTK_Module {

	const PAGE = 'bhtk-shield';

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'code-shield';
	}

	/**
	 * Module name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Page Shield', 'bani-honar-toolkit' );
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Make casual copying of your pages harder: blocked right-click menu, developer shortcuts and image dragging, and hidden WordPress version details. No effect on search engines.', 'bani-honar-toolkit' );
	}

	/**
	 * Dashboard icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-shield';
	}

	/**
	 * It changes what visitors see, so it starts switched off.
	 *
	 * @return bool
	 */
	public function is_default_enabled() {
		return false;
	}

	/**
	 * Default settings. Everything starts switched off: the owner chooses, knowing what each option does.
	 *
	 * @return array
	 */
	public function default_settings() {
		return array(
			'menu'      => 0,
			'keys'      => 0,
			'drag'      => 0,
			'hide_info' => 0,
			'admins'    => 0,
		);
	}

	/**
	 * Clean the submitted settings. Only the known on/off options are kept.
	 *
	 * @param array $raw Submitted values.
	 * @return array
	 */
	public function sanitize_settings( $raw ) {
		$out = array();
		foreach ( array_keys( $this->default_settings() ) as $key ) {
			$out[ $key ] = empty( $raw[ $key ] ) ? 0 : 1;
		}
		return $out;
	}

	/**
	 * The settings live on the module's own tab, not on the general Settings screen.
	 *
	 * @return bool
	 */
	public function has_settings() {
		return false;
	}

	/**
	 * The tab stays in the menu while the module is off, because its on/off switch is on that tab.
	 *
	 * @return bool
	 */
	public function always_show_pages() {
		return true;
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
				'title'    => __( 'Page Shield', 'bani-honar-toolkit' ),
				'callback' => array( $this, 'render_page' ),
			),
		);
	}

	/**
	 * The Page Shield tab: on/off switch and settings.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to open this page.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}

		$admin = BHTK_Admin::instance();
		$on    = BHTK_Plugin::instance()->is_enabled( $this->get_id() );

		$admin->render_header( self::PAGE );
		$admin->render_flash();
		$admin->render_module_switch( $this, self::PAGE );

		if ( $on ) {
			echo '<div class="bhtk-alert bhtk-alert--warn">';
			esc_html_e( 'You are logged in as an administrator, so you will not see the protection on your own site unless "Also protect logged-in administrators" is ticked. To test it, open your site in a private window or another browser.', 'bani-honar-toolkit' );
			echo '</div>';
		} else {
			echo '<div class="bhtk-alert bhtk-alert--warn">';
			esc_html_e( 'Page Shield is off. You can still change the settings below; they are saved and start working when you switch it on.', 'bani-honar-toolkit' );
			echo '</div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bhtk_save_module" />
			<input type="hidden" name="bhtk_module" value="<?php echo esc_attr( $this->get_id() ); ?>" />
			<input type="hidden" name="bhtk_page" value="<?php echo esc_attr( self::PAGE ); ?>" />
			<?php wp_nonce_field( 'bhtk_save_module_' . $this->get_id(), '_bhtk_nonce' ); ?>

			<section class="bhtk-card <?php echo $on ? '' : 'is-dim'; ?>">
				<h2 class="bhtk-card__title"><?php esc_html_e( 'Settings', 'bani-honar-toolkit' ); ?></h2>
				<?php $this->render_settings(); ?>
			</section>

			<p><button type="submit" class="bhtk-btn bhtk-btn--primary bhtk-btn--inline"><?php esc_html_e( 'Save settings', 'bani-honar-toolkit' ); ?></button></p>
		</form>
		<?php
		$admin->render_footer();
	}

	/**
	 * Settings fields.
	 *
	 * @return void
	 */
	public function render_settings() {
		$s = $this->get_settings();

		$safe = array(
			'menu'      => array( __( 'Block the right-click menu', 'bani-honar-toolkit' ), __( 'The menu still works inside text fields.', 'bani-honar-toolkit' ) ),
			'keys'      => array( __( 'Block developer shortcuts', 'bani-honar-toolkit' ), __( 'F12, Ctrl+U (view source), Ctrl+S (save page), Ctrl+Shift+I / J / C.', 'bani-honar-toolkit' ) ),
			'drag'      => array( __( 'Block dragging images out of the page', 'bani-honar-toolkit' ), '' ),
			'hide_info' => array( __( 'Hide WordPress version details', 'bani-honar-toolkit' ), __( 'Removes the generator tag and a few other tags from the page head.', 'bani-honar-toolkit' ) ),
			'admins'    => array( __( 'Also protect logged-in administrators', 'bani-honar-toolkit' ), __( 'Off by default so you can inspect your own site. The Elementor editor is never protected.', 'bani-honar-toolkit' ) ),
		);
		?>
		<div class="bhtk-notice-box">
			<?php esc_html_e( 'Please note: these are deterrents, not a lock. A browser must receive the HTML, CSS and JavaScript of a page, so a determined person can always read them (for example with view-source: in the address bar, or with a script). Your PHP code never reaches the browser and stays private. Never put passwords or secret keys in page code.', 'bani-honar-toolkit' ); ?>
		</div>

		<h3 class="bhtk-sub"><?php esc_html_e( 'Options without any effect on search engines', 'bani-honar-toolkit' ); ?> <span class="bhtk-risk bhtk-risk--none"><?php esc_html_e( 'No SEO effect', 'bani-honar-toolkit' ); ?></span></h3>
		<div class="bhtk-form bhtk-form--settings">
			<?php foreach ( $safe as $key => $info ) : ?>
				<div class="bhtk-field bhtk-field--full">
					<label class="bhtk-check">
						<input type="checkbox" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?> />
						<?php echo esc_html( $info[0] ); ?>
					</label>
					<?php if ( '' !== $info[1] ) : ?>
						<small><?php echo esc_html( $info[1] ); ?></small>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php
	}

	/**
	 * Front-end hooks.
	 *
	 * @return void
	 */
	public function boot() {
		if ( is_admin() ) {
			return;
		}
		$s = $this->get_settings();

		if ( $s['hide_info'] ) {
			remove_action( 'wp_head', 'wp_generator' );
			remove_action( 'wp_head', 'rsd_link' );
			remove_action( 'wp_head', 'wlwmanifest_link' );
			remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
			add_filter( 'the_generator', '__return_empty_string' );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Whether the current request should be protected.
	 *
	 * @return bool
	 */
	private function is_active() {
		if ( is_admin() || is_feed() || is_customize_preview() || is_preview() || wp_doing_ajax() ) {
			return false;
		}
		// The Elementor editor and its preview frame must stay untouched.
		if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['elementor_library'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return false;
		}

		$s = $this->get_settings();
		if ( empty( $s['admins'] ) && is_user_logged_in() && current_user_can( BHTK_Plugin::capability() ) ) {
			return false;
		}

		/**
		 * Filter whether the page shield is active on the current request.
		 *
		 * @param bool $active True to protect the page.
		 */
		return (bool) apply_filters( 'bhtk_shield_active', true );
	}

	/**
	 * Add the shield script.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! $this->is_active() ) {
			return;
		}
		$s = $this->get_settings();
		if ( ! $s['menu'] && ! $s['keys'] && ! $s['drag'] ) {
			return;
		}

		wp_enqueue_script( 'bhtk-shield', BHTK_URL . 'assets/js/shield.js', array(), BHTK_VERSION, true );

		$config = array(
			'menu' => (bool) $s['menu'],
			'keys' => (bool) $s['keys'],
			'drag' => (bool) $s['drag'],
		);
		wp_add_inline_script( 'bhtk-shield', 'window.bhtkShield = ' . wp_json_encode( $config ) . ';', 'before' );
	}
}
