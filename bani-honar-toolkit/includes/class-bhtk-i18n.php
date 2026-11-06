<?php
/**
 * Language handling for the plugin interface.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets the plugin interface switch between Persian and English
 * independently of the WordPress site language.
 */
final class BHTK_I18n {

	const DOMAIN = 'bani-honar-toolkit';

	/**
	 * Language chosen in the plugin settings: auto, fa or en.
	 *
	 * @return string
	 */
	public static function choice() {
		$settings = get_option( 'bhtk_settings', array() );
		$choice   = ( is_array( $settings ) && isset( $settings['language'] ) ) ? $settings['language'] : 'auto';
		return in_array( $choice, array( 'auto', 'fa', 'en' ), true ) ? $choice : 'auto';
	}

	/**
	 * Language actually used by the interface: fa or en.
	 *
	 * @return string
	 */
	public static function lang() {
		$choice = self::choice();
		if ( 'auto' !== $choice ) {
			return $choice;
		}
		return ( 0 === strpos( get_user_locale(), 'fa' ) ) ? 'fa' : 'en';
	}

	/**
	 * Whether the interface is right-to-left.
	 *
	 * @return bool
	 */
	public static function is_rtl() {
		return 'fa' === self::lang();
	}

	/**
	 * Value for a dir attribute.
	 *
	 * @return string
	 */
	public static function dir() {
		return self::is_rtl() ? 'rtl' : 'ltr';
	}

	/**
	 * Load the translations that match the chosen interface language.
	 *
	 * English is the source language, so it needs no file. Persian loads a
	 * Persian .mo file: the one bundled in the plugin's languages folder when
	 * there is one, otherwise the language pack that WordPress downloads from
	 * translate.wordpress.org (or that the owner copied into wp-content/languages/plugins).
	 * A no-op translator is registered for English so that WordPress does not
	 * load a site-locale translation behind our back.
	 *
	 * @return void
	 */
	public static function load() {
		global $l10n;

		unload_textdomain( self::DOMAIN );

		if ( 'fa' === self::lang() ) {
			$candidates = array( BHTK_PATH . 'languages/' . self::DOMAIN . '-fa_IR.mo' );
			if ( defined( 'WP_LANG_DIR' ) ) {
				$candidates[] = WP_LANG_DIR . '/plugins/' . self::DOMAIN . '-fa_IR.mo';
			}
			foreach ( $candidates as $mofile ) {
				if ( is_readable( $mofile ) ) {
					// The file is registered under the current locale so WordPress 6.5+ uses it even when the site language is different.
					load_textdomain( self::DOMAIN, $mofile, determine_locale() );
					break;
				}
			}
		}

		if ( ! isset( $l10n[ self::DOMAIN ] ) ) {
			$l10n[ self::DOMAIN ] = new NOOP_Translations(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * Strings used by the admin JavaScript.
	 *
	 * @return array
	 */
	public static function js_strings() {
		return array(
			'copied'       => __( 'Copied ✓', 'bani-honar-toolkit' ),
			'copyPrompt'   => __( 'Copy this link:', 'bani-honar-toolkit' ),
			'avatarTooBig' => __( 'The image is larger than 2 MB. Please choose a smaller one.', 'bani-honar-toolkit' ),
		);
	}
}
