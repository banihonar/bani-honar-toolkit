<?php
/**
 * Plugin bootstrap and module registry.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the modules, keeps track of which are enabled and runs upgrades.
 */
final class BHTK_Plugin {

	/**
	 * Single instance.
	 *
	 * @var BHTK_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Registered modules keyed by id.
	 *
	 * @var BHTK_Module[]
	 */
	private $modules = array();

	/**
	 * Whether the built-in modules were registered.
	 *
	 * @var bool
	 */
	private $registered = false;

	/**
	 * Get the single instance.
	 *
	 * @return BHTK_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook everything up.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'plugins_loaded', array( $this, 'register_modules' ) );
		add_action( 'init', array( 'BHTK_I18n', 'load' ), 0 );
		add_action( 'init', array( $this, 'boot_modules' ), 1 );
		add_action( 'init', array( $this, 'maybe_upgrade' ), 2 );

		BHTK_Privacy::register();

		if ( is_admin() ) {
			BHTK_Admin::instance();
		}
	}

	/**
	 * Capability needed to use the toolkit screens.
	 *
	 * @return string
	 */
	public static function capability() {
		return apply_filters( 'bhtk_capability', 'manage_options' );
	}

	/**
	 * General settings with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( 'bhtk_settings', array() );
		$saved = is_array( $saved ) ? $saved : array();
		$s     = wp_parse_args(
			$saved,
			array(
				'language'         => 'auto',
				'delete_data'      => 0,
				'disabled_modules' => array(),
				'enabled_modules'  => array(),
			)
		);

		$s['language']         = in_array( $s['language'], array( 'auto', 'fa', 'en' ), true ) ? $s['language'] : 'auto';
		$s['delete_data']      = empty( $s['delete_data'] ) ? 0 : 1;
		$s['disabled_modules'] = is_array( $s['disabled_modules'] ) ? array_values( array_map( 'sanitize_key', $s['disabled_modules'] ) ) : array();
		$s['enabled_modules']  = is_array( $s['enabled_modules'] ) ? array_values( array_map( 'sanitize_key', $s['enabled_modules'] ) ) : array();
		return $s;
	}

	/**
	 * Store the general settings.
	 *
	 * @param array $settings Clean settings.
	 * @return void
	 */
	public static function update_settings( $settings ) {
		update_option( 'bhtk_settings', $settings, false );
	}

	/**
	 * Register the built-in modules and let other code add theirs.
	 *
	 * @return void
	 */
	public function register_modules() {
		if ( $this->registered ) {
			return;
		}
		$this->registered = true;

		$this->register_module( new BHTK_Module_Link_Shortener() );
		$this->register_module( new BHTK_Module_QR_Generator() );
		$this->register_module( new BHTK_Module_Local_Avatars() );
		$this->register_module( new BHTK_Module_Code_Shield() );
		$this->register_module( new BHTK_Module_Visitor_Stats() );
		$this->register_module( new BHTK_Module_Post_Duplicator() );

		/**
		 * Register your own module.
		 *
		 * @param BHTK_Plugin $plugin The plugin instance. Call $plugin->register_module( $module ).
		 */
		do_action( 'bhtk_register_modules', $this );
	}

	/**
	 * Add a module to the registry.
	 *
	 * @param BHTK_Module $module Module instance.
	 * @return bool True when the module was added.
	 */
	public function register_module( $module ) {
		if ( ! $module instanceof BHTK_Module ) {
			return false;
		}
		$id = sanitize_key( $module->get_id() );
		if ( '' === $id || isset( $this->modules[ $id ] ) ) {
			return false;
		}
		$this->modules[ $id ] = $module;
		return true;
	}

	/**
	 * All registered modules.
	 *
	 * @return BHTK_Module[]
	 */
	public function get_modules() {
		return $this->modules;
	}

	/**
	 * Whether a module is switched on.
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	public function is_enabled( $id ) {
		if ( ! isset( $this->modules[ $id ] ) ) {
			return false;
		}
		$settings = self::settings();
		if ( in_array( $id, $settings['disabled_modules'], true ) ) {
			return false;
		}
		// Modules that are off by default need an explicit switch-on.
		if ( ! $this->modules[ $id ]->is_default_enabled() && ! in_array( $id, $settings['enabled_modules'], true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Modules that are switched on.
	 *
	 * @return BHTK_Module[]
	 */
	public function get_enabled_modules() {
		$enabled = array();
		foreach ( $this->modules as $id => $module ) {
			if ( $this->is_enabled( $id ) ) {
				$enabled[ $id ] = $module;
			}
		}
		return $enabled;
	}

	/**
	 * Switch modules on or off. Modules not listed are switched off.
	 *
	 * @param string[] $enabled_ids Ids of the modules that should be on.
	 * @return void
	 */
	public function set_enabled_modules( $enabled_ids ) {
		$was = array();
		foreach ( array_keys( $this->modules ) as $id ) {
			$was[ $id ] = $this->is_enabled( $id );
		}

		$settings = self::settings();
		$disabled = array();
		$explicit = array();
		foreach ( $this->modules as $id => $module ) {
			if ( in_array( $id, $enabled_ids, true ) ) {
				if ( ! $module->is_default_enabled() ) {
					$explicit[] = $id;
				}
			} else {
				$disabled[] = $id;
			}
		}
		$settings['disabled_modules'] = $disabled;
		$settings['enabled_modules']  = $explicit;
		self::update_settings( $settings );

		foreach ( $this->modules as $id => $module ) {
			$now = $this->is_enabled( $id );
			if ( ! $was[ $id ] && $now ) {
				// Just switched on: it may need its tables.
				$module->install();
			} elseif ( $was[ $id ] && ! $now ) {
				// Just switched off: stop its scheduled work.
				$module->on_disable();
			}
		}
	}

	/**
	 * Switch one module on or off and leave the others as they are.
	 *
	 * @param string $id Module id.
	 * @param bool   $on True to switch on.
	 * @return bool False when the module does not exist.
	 */
	public function set_module_enabled( $id, $on ) {
		if ( ! isset( $this->modules[ $id ] ) ) {
			return false;
		}
		$ids = array_diff( array_keys( $this->get_enabled_modules() ), array( $id ) );
		if ( $on ) {
			$ids[] = $id;
		}
		$this->set_enabled_modules( array_values( $ids ) );
		return true;
	}

	/**
	 * Start the enabled modules.
	 *
	 * @return void
	 */
	public function boot_modules() {
		foreach ( $this->get_enabled_modules() as $module ) {
			$module->boot();
			if ( is_admin() ) {
				$module->boot_admin();
			}
		}
	}

	/**
	 * Run installers when the plugin version changes without a re-activation.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( get_option( 'bhtk_version' ) === BHTK_VERSION ) {
			return;
		}
		$this->run_installers();
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		$plugin = self::instance();
		$plugin->register_modules();
		$plugin->run_installers();
	}

	/**
	 * Plugin deactivation: stop the scheduled work of every module. Data stays.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$plugin = self::instance();
		$plugin->register_modules();
		foreach ( $plugin->get_modules() as $module ) {
			$module->on_disable();
		}
	}

	/**
	 * Create the data of every enabled module and move data from older versions.
	 *
	 * @return void
	 */
	private function run_installers() {
		foreach ( $this->get_enabled_modules() as $module ) {
			$module->install();
		}
		BHTK_Migrator::run();
		update_option( 'bhtk_version', BHTK_VERSION, false );
	}
}
