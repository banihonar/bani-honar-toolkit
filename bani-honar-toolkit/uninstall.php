<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Data is removed only when "Delete all toolkit data" is ticked in the settings.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// The hourly clean-up of the visitor statistics is always removed.
wp_clear_scheduled_hook( 'bhtk_visits_maintenance' );

$bhtk_settings = get_option( 'bhtk_settings', array() );

if ( is_array( $bhtk_settings ) && ! empty( $bhtk_settings['delete_data'] ) ) {
	global $wpdb;

	// Custom table of the link shortener.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bhtk_links" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Custom table of the visitor statistics.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bhtk_visits" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Options.
	delete_option( 'bhtk_settings' );
	delete_option( 'bhtk_mod_link_shortener' );
	delete_option( 'bhtk_mod_local_avatars' );
	delete_option( 'bhtk_mod_code_shield' );
	delete_option( 'bhtk_mod_visitor_stats' );
	delete_option( 'bhtk_mod_post_duplicator' );
	delete_transient( 'bhtk_geo_down' );
	delete_option( 'bhtk_blocked_ips' );
	delete_option( 'bhtk_visits_db' );
	delete_option( 'bhtk_version' );
	delete_option( 'bhtk_migrated_from_bhac' );

	// Marks the Post Duplicator put on posts (which post is a copy of which).
	foreach ( array( '_bhtk_clone_of', '_bhtk_fork_of', '_bhtk_open_fork', '_bhtk_fork_time' ) as $bhtk_key ) {
		delete_post_meta_by_key( $bhtk_key );
	}

	// Profile pictures, current and legacy storage.
	delete_metadata( 'user', 0, 'bhtk_avatar', '', true );
	delete_metadata( 'user', 0, 'bhp_local_avatar', '', true );

	$bhtk_uploads = wp_upload_dir( null, false );
	foreach ( array( 'bhtk-avatars', 'bhp-avatars' ) as $bhtk_subdir ) {
		$bhtk_dir = $bhtk_uploads['basedir'] . '/' . $bhtk_subdir;
		if ( ! is_dir( $bhtk_dir ) ) {
			continue;
		}
		foreach ( (array) glob( $bhtk_dir . '/*' ) as $bhtk_file ) {
			if ( is_file( $bhtk_file ) ) {
				wp_delete_file( $bhtk_file );
			}
		}
		rmdir( $bhtk_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- the folder is empty and inside the uploads directory.
	}
}
