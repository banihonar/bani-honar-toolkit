<?php
/**
 * Base class for toolkit modules.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every feature of the toolkit is a module. Extend this class and register
 * the instance on the `bhtk_register_modules` action to add your own.
 */
abstract class BHTK_Module {

	/**
	 * Unique module id, lowercase letters, numbers and dashes.
	 *
	 * @return string
	 */
	abstract public function get_id();

	/**
	 * Translated module name.
	 *
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Translated one-sentence description.
	 *
	 * @return string
	 */
	abstract public function get_description();

	/**
	 * Dashicons class used on the dashboard card.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-admin-plugins';
	}

	/**
	 * Whether the module is on after a fresh install.
	 *
	 * @return bool
	 */
	public function is_default_enabled() {
		return true;
	}

	/**
	 * Default values of the module settings.
	 *
	 * @return array
	 */
	public function default_settings() {
		return array();
	}

	/**
	 * Name of the option that stores the module settings.
	 *
	 * @return string
	 */
	public function option_name() {
		return 'bhtk_mod_' . str_replace( '-', '_', $this->get_id() );
	}

	/**
	 * Saved settings merged with the defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		$saved = get_option( $this->option_name(), array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $this->default_settings() );
	}

	/**
	 * Validate raw submitted values. Override in your module.
	 *
	 * @param array $raw Unsanitized values submitted for this module.
	 * @return array Clean settings.
	 */
	public function sanitize_settings( $raw ) {
		unset( $raw );
		return $this->get_settings();
	}

	/**
	 * Sanitize and store the module settings.
	 *
	 * @param array $raw Unsanitized values submitted for this module.
	 * @return void
	 */
	public function save_settings( $raw ) {
		update_option( $this->option_name(), $this->sanitize_settings( is_array( $raw ) ? $raw : array() ), false );
	}

	/**
	 * Whether the module has settings to show on the Settings screen.
	 *
	 * @return bool
	 */
	public function has_settings() {
		return false;
	}

	/**
	 * Print the settings fields. Use field_name() for the input names.
	 *
	 * @return void
	 */
	public function render_settings() {}

	/**
	 * Name attribute for a settings input.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public function field_name( $key ) {
		return 'bhtk_mod[' . $this->get_id() . '][' . $key . ']';
	}

	/**
	 * Admin sub pages added to the toolkit menu.
	 *
	 * Each page is an array with the keys slug, title and callback.
	 *
	 * @return array
	 */
	public function admin_pages() {
		return array();
	}

	/**
	 * Whether the tab of this module stays in the toolkit menu while the module is off.
	 *
	 * Use it for modules whose own screen carries the on/off switch, so the module
	 * can be switched back on from there.
	 *
	 * @return bool
	 */
	public function always_show_pages() {
		return false;
	}

	/**
	 * Whether the toolkit admin CSS and JS are needed on another admin screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return bool
	 */
	public function needs_assets( $hook_suffix ) {
		unset( $hook_suffix );
		return false;
	}

	/**
	 * Runs on every request while the module is enabled.
	 *
	 * @return void
	 */
	public function boot() {}

	/**
	 * Runs in the admin area while the module is enabled.
	 *
	 * @return void
	 */
	public function boot_admin() {}

	/**
	 * Create tables or options. Runs on activation, on upgrade and when the module is switched on.
	 *
	 * @return void
	 */
	public function install() {}

	/**
	 * Runs when the module is switched off or the plugin is deactivated. Stop scheduled work here.
	 * Saved data is kept.
	 *
	 * @return void
	 */
	public function on_disable() {}
}
