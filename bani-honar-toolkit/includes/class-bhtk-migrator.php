<?php
/**
 * Moves data from the earlier "BHAC" plugin (versions 1.x).
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-time, non-destructive import of links and settings created by BHAC 1.x.
 * The old table and options are left untouched.
 */
final class BHTK_Migrator {

	/**
	 * Run the import once.
	 *
	 * @return void
	 */
	public static function run() {
		if ( get_option( 'bhtk_migrated_from_bhac' ) ) {
			return;
		}

		self::migrate_settings();
		self::migrate_links();

		update_option( 'bhtk_migrated_from_bhac', 1, false );
	}

	/**
	 * Copy the shortener and general settings.
	 *
	 * @return void
	 */
	private static function migrate_settings() {
		$legacy = get_option( 'bhac_settings', null );
		if ( ! is_array( $legacy ) ) {
			return;
		}

		if ( false === get_option( 'bhtk_mod_link_shortener', false ) ) {
			update_option(
				'bhtk_mod_link_shortener',
				array(
					'length'   => isset( $legacy['length'] ) ? (int) $legacy['length'] : 6,
					'redirect' => isset( $legacy['redirect'] ) ? (int) $legacy['redirect'] : 302,
					'prefix'   => isset( $legacy['prefix'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $legacy['prefix'] ) : '',
				),
				false
			);
		}

		if ( ! empty( $legacy['delete_data'] ) ) {
			$general                = BHTK_Plugin::settings();
			$general['delete_data'] = 1;
			BHTK_Plugin::update_settings( $general );
		}
	}

	/**
	 * Copy the links table, only when the new table is still empty.
	 *
	 * @return void
	 */
	private static function migrate_links() {
		global $wpdb;

		$legacy_table = esc_sql( $wpdb->prefix . 'bhac_links' );
		$new_table    = esc_sql( BHTK_Links::table() );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy_table ) ) );
		if ( $found !== $legacy_table ) {
			return;
		}

		$existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$new_table}" );
		if ( $existing > 0 ) {
			return;
		}

		$wpdb->query(
			"INSERT IGNORE INTO {$new_table} (code, code_hash, target, note, clicks, created_by, created_at, last_click)
			SELECT code, code_hash, target, note, clicks, created_by, created_at, last_click FROM {$legacy_table}"
		);
		// phpcs:enable
	}
}
