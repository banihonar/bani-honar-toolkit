<?php
/**
 * Admin menu, dashboard and settings screens.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything the toolkit shows in the WordPress admin area.
 */
final class BHTK_Admin {

	const SLUG          = 'bani-honar-toolkit';
	const SETTINGS_SLUG = 'bhtk-settings';

	/**
	 * Single instance.
	 *
	 * @var BHTK_Admin|null
	 */
	private static $instance = null;

	/**
	 * Tabs shown under the header, slug => label.
	 *
	 * @var array
	 */
	private $tabs = array();

	/**
	 * Get the single instance.
	 *
	 * @return BHTK_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook into WordPress.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'legacy_notice' ) );
		add_action( 'admin_post_bhtk_save_modules', array( $this, 'handle_save_modules' ) );
		add_action( 'admin_post_bhtk_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_bhtk_toggle_module', array( $this, 'handle_toggle_module' ) );
		add_action( 'admin_post_bhtk_save_module', array( $this, 'handle_save_module' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BHTK_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Add the toolkit menu and the pages of the enabled modules.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap  = BHTK_Plugin::capability();
		$icon = include BHTK_PATH . 'includes/menu-icon.php';

		add_menu_page( 'Bani Honar Toolkit', 'Bani Honar Toolkit', $cap, self::SLUG, array( $this, 'render_dashboard' ), $icon, 58 );

		$this->tabs[ self::SLUG ] = __( 'Dashboard', 'bani-honar-toolkit' );
		add_submenu_page( self::SLUG, __( 'Dashboard', 'bani-honar-toolkit' ), __( 'Dashboard', 'bani-honar-toolkit' ), $cap, self::SLUG, array( $this, 'render_dashboard' ) );

		$plugin = BHTK_Plugin::instance();
		foreach ( $plugin->get_modules() as $id => $module ) {
			// A module that is off keeps its tab only when the tab carries its on/off switch.
			if ( ! $plugin->is_enabled( $id ) && ! $module->always_show_pages() ) {
				continue;
			}
			foreach ( $module->admin_pages() as $page ) {
				if ( empty( $page['slug'] ) || empty( $page['callback'] ) || ! is_callable( $page['callback'] ) ) {
					continue;
				}
				$title = isset( $page['title'] ) ? $page['title'] : $module->get_name();

				$this->tabs[ $page['slug'] ] = $title;
				add_submenu_page( self::SLUG, $title, $title, $cap, $page['slug'], $page['callback'] );
			}
		}

		$this->tabs[ self::SETTINGS_SLUG ] = __( 'Settings', 'bani-honar-toolkit' );
		add_submenu_page( self::SLUG, __( 'Settings', 'bani-honar-toolkit' ), __( 'Settings', 'bani-honar-toolkit' ), $cap, self::SETTINGS_SLUG, array( $this, 'render_settings' ) );

		$this->tabs[ BHTK_Contact::SLUG ] = __( 'Contact us', 'bani-honar-toolkit' );
		add_submenu_page( self::SLUG, __( 'Contact us', 'bani-honar-toolkit' ), __( 'Contact us', 'bani-honar-toolkit' ), $cap, BHTK_Contact::SLUG, array( 'BHTK_Contact', 'render' ) );
	}

	/**
	 * Load the toolkit CSS and JS on its own screens and where a module asks for it.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function assets( $hook_suffix ) {
		$needed = $this->is_toolkit_screen();

		if ( ! $needed ) {
			foreach ( BHTK_Plugin::instance()->get_enabled_modules() as $module ) {
				if ( $module->needs_assets( $hook_suffix ) ) {
					$needed = true;
					break;
				}
			}
		}
		if ( ! $needed ) {
			return;
		}

		wp_enqueue_style( 'bhtk-admin', BHTK_URL . 'assets/css/admin.css', array(), BHTK_VERSION );
		wp_enqueue_script( 'bhtk-admin', BHTK_URL . 'assets/js/admin.js', array(), BHTK_VERSION, true );
		wp_localize_script( 'bhtk-admin', 'bhtkAdmin', BHTK_I18n::js_strings() );
	}

	/**
	 * Whether the current screen is one of ours.
	 *
	 * @return bool
	 */
	private function is_toolkit_screen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return self::SLUG === $page || 0 === strpos( $page, 'bhtk-' );
	}

	/**
	 * Quick link on the plugins list.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$mine = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ) . '">' . esc_html__( 'Settings', 'bani-honar-toolkit' ) . '</a>',
		);
		return array_merge( $mine, $links );
	}

	/**
	 * Warn when the old BHAC plugin is still active next to this one.
	 *
	 * @return void
	 */
	public function legacy_notice() {
		if ( ! $this->is_toolkit_screen() || ! class_exists( 'BHAC_Links' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		esc_html_e( 'The older "BHAC" plugin is still active. Your links and settings were copied into Bani Honar Toolkit, so please deactivate the old plugin to avoid conflicts.', 'bani-honar-toolkit' );
		echo '</p></div>';
	}

	/**
	 * Print the opening of a toolkit screen.
	 *
	 * @param string $active Slug of the current tab.
	 * @return void
	 */
	public function render_header( $active ) {
		?>
		<div class="wrap bhtk-wrap" dir="<?php echo esc_attr( BHTK_I18n::dir() ); ?>">
		<hr class="wp-header-end" />

		<header class="bhtk-hero">
			<span class="bhtk-hero__logo"><img src="<?php echo esc_url( BHTK_URL . 'assets/img/logo.svg' ); ?>" alt="" width="52" height="35" /></span>
			<div class="bhtk-hero__text">
				<h1>Bani Honar Toolkit</h1>
				<p><?php esc_html_e( 'A multi-purpose, modular and extensible plugin for WordPress.', 'bani-honar-toolkit' ); ?></p>
			</div>
			<span class="bhtk-chip bhtk-ltr">v<?php echo esc_html( BHTK_VERSION ); ?></span>
		</header>

		<nav class="bhtk-tabs">
			<?php foreach ( $this->tabs as $slug => $label ) : ?>
				<a class="<?php echo $slug === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Print the closing of a toolkit screen.
	 *
	 * @return void
	 */
	public function render_footer() {
		?>
		<footer class="bhtk-footer">
			<?php
			printf(
				/* translators: %s: link to the author website */
				esc_html__( 'Made by %s', 'bani-honar-toolkit' ),
				'<a href="' . esc_url( BHTK_AUTHOR_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Bani Honar Advertising Agency', 'bani-honar-toolkit' ) . '</a>'
			);
			?>
		</footer>
		</div>
		<?php
	}

	/**
	 * Remember a message for the next screen load.
	 *
	 * @param string $type    success or error.
	 * @param string $message Message, already translated. "@settings_saved" is translated when shown.
	 * @param string $copy    Optional text shown with a copy button, such as a new short link.
	 * @return void
	 */
	public static function flash( $type, $message, $copy = '' ) {
		set_transient(
			'bhtk_flash_' . get_current_user_id(),
			array(
				'type' => 'error' === $type ? 'error' : 'success',
				'msg'  => (string) $message,
				'copy' => (string) $copy,
			),
			120
		);
	}

	/**
	 * Print and clear the remembered message.
	 *
	 * @return void
	 */
	public function render_flash() {
		$key   = 'bhtk_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( ! is_array( $flash ) ) {
			return;
		}
		delete_transient( $key );

		$message = ( '@settings_saved' === $flash['msg'] ) ? __( 'Settings saved.', 'bani-honar-toolkit' ) : $flash['msg'];
		?>
		<div class="bhtk-alert bhtk-alert--<?php echo esc_attr( $flash['type'] ); ?>">
			<span><?php echo esc_html( $message ); ?></span>
			<?php if ( '' !== $flash['copy'] ) : ?>
				<code class="bhtk-short"><?php echo esc_html( preg_replace( '#^https?://#i', '', $flash['copy'] ) ); ?></code>
				<button type="button" class="bhtk-btn bhtk-btn--gold bhtk-btn--sm" data-copy="<?php echo esc_attr( $flash['copy'] ); ?>"><?php esc_html_e( 'Copy link', 'bani-honar-toolkit' ); ?></button>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Redirect back to where a form was sent from, then stop.
	 *
	 * @param string $default_page Page slug used when no valid return address was posted.
	 * @return void
	 */
	public static function back( $default_page ) {
		$target = admin_url( 'admin.php?page=' . $default_page );
		// The caller has already verified the nonce of the form.
		if ( isset( $_POST['bhtk_back'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$posted = esc_url_raw( wp_unslash( $_POST['bhtk_back'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '' !== $posted ) {
				$target = $posted;
			}
		}
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Dashboard: one card per module with an on/off switch.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to open this page.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		$plugin = BHTK_Plugin::instance();

		$this->render_header( self::SLUG );
		$this->render_flash();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bhtk_save_modules" />
			<?php wp_nonce_field( 'bhtk_save_modules', '_bhtk_nonce' ); ?>

			<h2 class="bhtk-section-title"><?php esc_html_e( 'Modules', 'bani-honar-toolkit' ); ?></h2>
			<div class="bhtk-modules">
				<?php foreach ( $plugin->get_modules() as $id => $module ) : ?>
					<?php
					$on    = $plugin->is_enabled( $id );
					$pages = ( $on || $module->always_show_pages() ) ? $module->admin_pages() : array();
					?>
					<article class="bhtk-module <?php echo $on ? 'is-on' : ''; ?>">
						<span class="bhtk-module__icon dashicons <?php echo esc_attr( $module->get_icon() ); ?>" aria-hidden="true"></span>
						<div class="bhtk-module__body">
							<h3><?php echo esc_html( $module->get_name() ); ?></h3>
							<p><?php echo esc_html( $module->get_description() ); ?></p>
							<div class="bhtk-module__foot">
								<label class="bhtk-switch">
									<input type="checkbox" name="bhtk_modules[<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( $on ); ?> />
									<span class="bhtk-switch__track" aria-hidden="true"></span>
									<span class="bhtk-switch__label"><?php esc_html_e( 'Enabled', 'bani-honar-toolkit' ); ?></span>
								</label>
								<?php if ( ! empty( $pages[0]['slug'] ) ) : ?>
									<a class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $pages[0]['slug'] ) ); ?>"><?php esc_html_e( 'Open', 'bani-honar-toolkit' ); ?></a>
								<?php elseif ( $on && $module->has_settings() ) : ?>
									<a class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ); ?>"><?php esc_html_e( 'Settings', 'bani-honar-toolkit' ); ?></a>
								<?php endif; ?>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>

			<p><button type="submit" class="bhtk-btn bhtk-btn--primary bhtk-btn--inline"><?php esc_html_e( 'Save modules', 'bani-honar-toolkit' ); ?></button></p>
		</form>

		<section class="bhtk-card bhtk-card--soft">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'Suggestions, criticism, problems or a new module?', 'bani-honar-toolkit' ); ?></h2>
			<p><?php esc_html_e( 'Write to the IT team. Every message is read.', 'bani-honar-toolkit' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . BHTK_Contact::SLUG ) ); ?>"><?php esc_html_e( 'Contact us', 'bani-honar-toolkit' ); ?></a></p>
		</section>

		<section class="bhtk-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'For developers', 'bani-honar-toolkit' ); ?></h2>
			<p><?php esc_html_e( 'The toolkit is modular. To add your own module, extend the BHTK_Module class and register it on the bhtk_register_modules action. It then gets a card on this screen, a switch, and its own settings.', 'bani-honar-toolkit' ); ?></p>
			<pre class="bhtk-code bhtk-ltr"><code><?php echo esc_html( "add_action( 'bhtk_register_modules', function ( \$plugin ) {\n\t\$plugin->register_module( new My_Module() );\n} );" ); ?></code></pre>
		</section>
		<?php
		$this->render_footer();
	}

	/**
	 * Save the module switches.
	 *
	 * @return void
	 */
	public function handle_save_modules() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'bhtk_save_modules', '_bhtk_nonce' );

		$ids = array();
		if ( isset( $_POST['bhtk_modules'] ) && is_array( $_POST['bhtk_modules'] ) ) {
			$ids = array_map( 'sanitize_key', array_keys( wp_unslash( $_POST['bhtk_modules'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- keys are sanitized here.
		}
		BHTK_Plugin::instance()->set_enabled_modules( $ids );

		self::flash( 'success', '@settings_saved' );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	/**
	 * Big on/off switch for one module, printed at the top of the module's own tab.
	 * The form is sent as soon as the switch is flipped.
	 *
	 * @param BHTK_Module $module Module whose switch is shown.
	 * @param string      $page   Slug of the tab the switch is on, used to come back to it.
	 * @param string      $view   Optional view inside that tab.
	 * @return void
	 */
	public function render_module_switch( $module, $page, $view = '' ) {
		$id = $module->get_id();
		$on = BHTK_Plugin::instance()->is_enabled( $id );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bhtk-power <?php echo $on ? 'is-on' : 'is-off'; ?>">
			<input type="hidden" name="action" value="bhtk_toggle_module" />
			<input type="hidden" name="bhtk_module" value="<?php echo esc_attr( $id ); ?>" />
			<input type="hidden" name="bhtk_page" value="<?php echo esc_attr( $page ); ?>" />
			<input type="hidden" name="bhtk_view" value="<?php echo esc_attr( $view ); ?>" />
			<?php wp_nonce_field( 'bhtk_toggle_module_' . $id, '_bhtk_nonce' ); ?>

			<span class="bhtk-power__icon dashicons <?php echo esc_attr( $module->get_icon() ); ?>" aria-hidden="true"></span>
			<div class="bhtk-power__text">
				<strong><?php echo esc_html( $module->get_name() ); ?></strong>
				<span class="bhtk-power__state">
					<?php
					if ( $on ) {
						esc_html_e( 'On - running on your site', 'bani-honar-toolkit' );
					} else {
						esc_html_e( 'Off - nothing of this module runs on your site', 'bani-honar-toolkit' );
					}
					?>
				</span>
				<small><?php echo esc_html( $module->get_description() ); ?></small>
			</div>
			<label class="bhtk-switch bhtk-switch--lg">
				<input type="checkbox" name="bhtk_state" value="1" <?php checked( $on ); ?> data-autosubmit="1" />
				<span class="bhtk-switch__track" aria-hidden="true"></span>
				<span class="bhtk-switch__label"><?php echo $on ? esc_html__( 'On', 'bani-honar-toolkit' ) : esc_html__( 'Off', 'bani-honar-toolkit' ); ?></span>
			</label>
			<noscript><button type="submit" class="bhtk-btn bhtk-btn--gold bhtk-btn--sm"><?php esc_html_e( 'Apply', 'bani-honar-toolkit' ); ?></button></noscript>
		</form>
		<?php
	}

	/**
	 * Address of a toolkit tab, optionally with one of its views.
	 *
	 * @param string $page Tab slug.
	 * @param string $view Optional view.
	 * @return string
	 */
	private static function tab_url( $page, $view = '' ) {
		$page = sanitize_key( $page );
		$view = sanitize_key( $view );
		$args = array( 'page' => '' !== $page ? $page : self::SLUG );
		if ( '' !== $view ) {
			$args['view'] = $view;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Switch one module on or off from its own tab.
	 *
	 * @return void
	 */
	public function handle_toggle_module() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		$id = isset( $_POST['bhtk_module'] ) ? sanitize_key( wp_unslash( $_POST['bhtk_module'] ) ) : '';
		check_admin_referer( 'bhtk_toggle_module_' . $id, '_bhtk_nonce' );

		$plugin  = BHTK_Plugin::instance();
		$modules = $plugin->get_modules();
		if ( ! isset( $modules[ $id ] ) ) {
			wp_die( esc_html__( 'This module does not exist.', 'bani-honar-toolkit' ), '', array( 'response' => 400 ) );
		}

		$on = ! empty( $_POST['bhtk_state'] );
		$plugin->set_module_enabled( $id, $on );

		self::flash(
			'success',
			sprintf(
				/* translators: %s: module name */
				$on ? __( '%s is now on.', 'bani-honar-toolkit' ) : __( '%s is now off.', 'bani-honar-toolkit' ),
				$modules[ $id ]->get_name()
			)
		);

		$page = isset( $_POST['bhtk_page'] ) ? sanitize_key( wp_unslash( $_POST['bhtk_page'] ) ) : self::SLUG;
		$view = isset( $_POST['bhtk_view'] ) ? sanitize_key( wp_unslash( $_POST['bhtk_view'] ) ) : '';
		wp_safe_redirect( self::tab_url( $page, $view ) );
		exit;
	}

	/**
	 * Save the settings of one module from its own tab.
	 *
	 * @return void
	 */
	public function handle_save_module() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		$id = isset( $_POST['bhtk_module'] ) ? sanitize_key( wp_unslash( $_POST['bhtk_module'] ) ) : '';
		check_admin_referer( 'bhtk_save_module_' . $id, '_bhtk_nonce' );

		$modules = BHTK_Plugin::instance()->get_modules();
		if ( ! isset( $modules[ $id ] ) ) {
			wp_die( esc_html__( 'This module does not exist.', 'bani-honar-toolkit' ), '', array( 'response' => 400 ) );
		}

		$raw = array();
		if ( isset( $_POST['bhtk_mod'][ $id ] ) && is_array( $_POST['bhtk_mod'][ $id ] ) ) {
			$raw = wp_unslash( $_POST['bhtk_mod'][ $id ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the module sanitizes its own values.
		}
		$modules[ $id ]->save_settings( $raw );

		self::flash( 'success', '@settings_saved' );

		$page = isset( $_POST['bhtk_page'] ) ? sanitize_key( wp_unslash( $_POST['bhtk_page'] ) ) : self::SLUG;
		$view = isset( $_POST['bhtk_view'] ) ? sanitize_key( wp_unslash( $_POST['bhtk_view'] ) ) : '';
		wp_safe_redirect( self::tab_url( $page, $view ) );
		exit;
	}

	/**
	 * Settings screen: general options and one card per module that has settings.
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to open this page.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		$settings = BHTK_Plugin::settings();

		$this->render_header( self::SETTINGS_SLUG );
		$this->render_flash();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bhtk_save_settings" />
			<?php wp_nonce_field( 'bhtk_save_settings', '_bhtk_nonce' ); ?>

			<section class="bhtk-card">
				<h2 class="bhtk-card__title"><?php esc_html_e( 'General', 'bani-honar-toolkit' ); ?></h2>
				<div class="bhtk-form bhtk-form--settings">
					<div class="bhtk-field">
						<label for="bhtk_language"><?php esc_html_e( 'Plugin language', 'bani-honar-toolkit' ); ?></label>
						<select id="bhtk_language" name="bhtk_language">
							<option value="auto" <?php selected( $settings['language'], 'auto' ); ?>><?php esc_html_e( 'Follow the WordPress language', 'bani-honar-toolkit' ); ?></option>
							<option value="fa" <?php selected( $settings['language'], 'fa' ); ?>><?php esc_html_e( 'Persian (فارسی)', 'bani-honar-toolkit' ); ?></option>
							<option value="en" <?php selected( $settings['language'], 'en' ); ?>><?php esc_html_e( 'English', 'bani-honar-toolkit' ); ?></option>
						</select>
						<small><?php esc_html_e( 'This changes the language of the toolkit screens only, not of your whole site.', 'bani-honar-toolkit' ); ?></small>
					</div>

					<div class="bhtk-field bhtk-field--full">
						<label class="bhtk-check">
							<input type="checkbox" name="bhtk_delete_data" value="1" <?php checked( $settings['delete_data'], 1 ); ?> />
							<?php esc_html_e( 'Delete all toolkit data when the plugin is deleted', 'bani-honar-toolkit' ); ?>
						</label>
						<small><?php esc_html_e( 'Off by default, so deleting the plugin by accident does not remove your short links or profile pictures.', 'bani-honar-toolkit' ); ?></small>
					</div>
				</div>
			</section>

			<?php foreach ( BHTK_Plugin::instance()->get_enabled_modules() as $module ) : ?>
				<?php if ( $module->has_settings() ) : ?>
					<section class="bhtk-card">
						<h2 class="bhtk-card__title"><?php echo esc_html( $module->get_name() ); ?></h2>
						<?php $module->render_settings(); ?>
					</section>
				<?php endif; ?>
			<?php endforeach; ?>

			<p><button type="submit" class="bhtk-btn bhtk-btn--primary bhtk-btn--inline"><?php esc_html_e( 'Save settings', 'bani-honar-toolkit' ); ?></button></p>
		</form>
		<?php
		$this->render_footer();
	}

	/**
	 * Save the general settings and the settings of every enabled module.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'bhtk_save_settings', '_bhtk_nonce' );

		$settings                = BHTK_Plugin::settings();
		$language                = isset( $_POST['bhtk_language'] ) ? sanitize_key( wp_unslash( $_POST['bhtk_language'] ) ) : 'auto';
		$settings['language']    = in_array( $language, array( 'auto', 'fa', 'en' ), true ) ? $language : 'auto';
		$settings['delete_data'] = empty( $_POST['bhtk_delete_data'] ) ? 0 : 1;
		BHTK_Plugin::update_settings( $settings );

		$raw_modules = array();
		if ( isset( $_POST['bhtk_mod'] ) && is_array( $_POST['bhtk_mod'] ) ) {
			$raw_modules = wp_unslash( $_POST['bhtk_mod'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every module sanitizes its own values.
		}
		foreach ( BHTK_Plugin::instance()->get_enabled_modules() as $id => $module ) {
			if ( $module->has_settings() ) {
				$module->save_settings( isset( $raw_modules[ $id ] ) && is_array( $raw_modules[ $id ] ) ? $raw_modules[ $id ] : array() );
			}
		}

		self::flash( 'success', '@settings_saved' );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) );
		exit;
	}
}
