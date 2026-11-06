<?php
/**
 * Link shortener: data layer and redirect.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

/**
 * Stores short links in a custom table and redirects visitors.
 */
final class BHTK_Links {

	const CHARS      = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	const MAX_LEN    = 32;
	const MAX_CUSTOM = 64;

	/**
	 * Codes that must never become short links.
	 *
	 * @var string[]
	 */
	private static $reserved = array(
		'admin',
		'login',
		'feed',
		'rss',
		'atom',
		'robots',
		'sitemap',
		'favicon',
		'index',
		'xmlrpc',
		'embed',
		'comments',
		'trackback',
		'search',
		'author',
		'category',
		'tag',
		'page',
		'wp',
	);

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bhtk_links';
	}

	/**
	 * Module settings with defaults, always valid.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( 'bhtk_mod_link_shortener', array() );
		$s     = wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			array(
				'length'   => 6,
				'redirect' => 302,
				'prefix'   => '',
			)
		);

		$s['length']   = max( 1, min( self::MAX_LEN, (int) $s['length'] ) );
		$s['redirect'] = in_array( (int) $s['redirect'], array( 301, 302, 307 ), true ) ? (int) $s['redirect'] : 302;
		$s['prefix']   = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $s['prefix'] );
		return $s;
	}

	/**
	 * Create or update the table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		// The unique key is the SHA-1 of the code, so "Ab" and "ab" are different links on any database collation.
		$sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  code varchar(64) NOT NULL,
  code_hash char(40) NOT NULL,
  target text NOT NULL,
  note varchar(190) NOT NULL DEFAULT '',
  clicks bigint(20) unsigned NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  last_click datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY code_hash (code_hash),
  KEY code (code)
) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Whether a code has the allowed shape.
	 *
	 * @param mixed $code Code to test.
	 * @return bool
	 */
	public static function valid_code( $code ) {
		return is_string( $code ) && (bool) preg_match( '/^[A-Za-z0-9]{1,' . self::MAX_CUSTOM . '}$/', $code );
	}

	/**
	 * Random code from lowercase letters, uppercase letters and digits.
	 *
	 * @param int $length Number of characters.
	 * @return string
	 */
	public static function random_code( $length ) {
		$length = max( 1, min( self::MAX_CUSTOM, (int) $length ) );
		$max    = strlen( self::CHARS ) - 1;
		$out    = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$out .= self::CHARS[ random_int( 0, $max ) ];
		}
		return $out;
	}

	/**
	 * Whether a code collides with a reserved word or an existing page or post.
	 *
	 * @param string $code Code to test.
	 * @return bool
	 */
	public static function is_reserved( $code ) {
		if ( in_array( strtolower( $code ), self::$reserved, true ) ) {
			return true;
		}
		$types = array_values( get_post_types( array( 'public' => true ) ) );
		return (bool) get_page_by_path( $code, OBJECT, $types );
	}

	/**
	 * Clean and validate a destination URL.
	 *
	 * @param string $url Raw URL.
	 * @return string|WP_Error
	 */
	public static function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return new WP_Error( 'bhtk_url', __( 'Enter the destination address.', 'bani-honar-toolkit' ) );
		}
		if ( ! preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}
		$clean = esc_url_raw( $url, array( 'http', 'https' ) );
		$parts = $clean ? wp_parse_url( $clean ) : false;

		if ( ! $clean || empty( $parts['host'] ) || strlen( $clean ) > 2000 ) {
			return new WP_Error( 'bhtk_url', __( 'The address is not valid. A correct example: https://example.com/page', 'bani-honar-toolkit' ) );
		}
		return $clean;
	}

	/**
	 * Trim a note to the column length.
	 *
	 * @param string $note Raw note.
	 * @return string
	 */
	private static function clean_note( $note ) {
		return mb_substr( sanitize_text_field( (string) $note ), 0, 190 );
	}

	/**
	 * Find a link by its code. The match is case-sensitive.
	 *
	 * @param string $code Short code.
	 * @return object|null
	 */
	public static function get_by_code( $code ) {
		global $wpdb;
		if ( ! self::valid_code( $code ) ) {
			return null;
		}
		$table = esc_sql( self::table() );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code_hash = %s", sha1( $code ) ) );
		return ( $row && hash_equals( (string) $row->code, $code ) ) ? $row : null;
	}

	/**
	 * Find a link by id.
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * Insert a row.
	 *
	 * @param string $code Short code.
	 * @param string $url  Destination.
	 * @param string $note Note.
	 * @return int New id or 0.
	 */
	private static function insert( $code, $url, $note ) {
		global $wpdb;
		$ok = $wpdb->insert(
			self::table(),
			array(
				'code'       => $code,
				'code_hash'  => sha1( $code ),
				'target'     => $url,
				'note'       => $note,
				'clicks'     => 0,
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Create a short link.
	 *
	 * @param string $url    Long destination address.
	 * @param int    $length Length of a random code (1 to 32).
	 * @param string $custom Optional custom code.
	 * @param string $note   Optional note.
	 * @return int|WP_Error New row id or an error.
	 */
	public static function create( $url, $length = 6, $custom = '', $note = '' ) {
		$url = self::normalize_url( $url );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		$note   = self::clean_note( $note );
		$custom = trim( (string) $custom );

		if ( '' !== $custom ) {
			if ( ! self::valid_code( $custom ) ) {
				return new WP_Error( 'bhtk_code', __( 'A custom code may only contain English letters and numbers (up to 64 characters).', 'bani-honar-toolkit' ) );
			}
			if ( self::is_reserved( $custom ) ) {
				return new WP_Error( 'bhtk_code', __( 'This code clashes with a page or address of your site. Choose another code.', 'bani-honar-toolkit' ) );
			}
			if ( self::get_by_code( $custom ) ) {
				return new WP_Error( 'bhtk_code', __( 'This code is already in use.', 'bani-honar-toolkit' ) );
			}
			$id = self::insert( $custom, $url, $note );
			return $id ? $id : new WP_Error( 'bhtk_db', __( 'The link could not be saved. Please try again.', 'bani-honar-toolkit' ) );
		}

		$length = max( 1, min( self::MAX_LEN, (int) $length ) );
		for ( $i = 0; $i < 60; $i++ ) {
			$code = self::random_code( $length );
			if ( self::get_by_code( $code ) || self::is_reserved( $code ) ) {
				continue;
			}
			$id = self::insert( $code, $url, $note );
			if ( $id ) {
				return $id;
			}
		}
		return new WP_Error( 'bhtk_full', __( 'No free code of this length was found. Increase the code length.', 'bani-honar-toolkit' ) );
	}

	/**
	 * Change the destination and note of a link.
	 *
	 * @param int    $id   Row id.
	 * @param string $url  New destination.
	 * @param string $note New note.
	 * @return true|WP_Error
	 */
	public static function update( $id, $url, $note ) {
		global $wpdb;
		$url = self::normalize_url( $url );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		$wpdb->update(
			self::table(),
			array(
				'target' => $url,
				'note'   => self::clean_note( $note ),
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		return true;
	}

	/**
	 * Delete a link.
	 *
	 * @param int $id Row id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * A page of links.
	 *
	 * @param string $search Optional search text.
	 * @param int    $paged  Page number.
	 * @param int    $per    Rows per page.
	 * @return array Array of rows and the total count.
	 */
	public static function query( $search = '', $paged = 1, $per = 20 ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$paged = max( 1, (int) $paged );
		$per   = max( 1, (int) $per );

		if ( '' === $search ) {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per, ( $paged - 1 ) * $per ) );
		} else {
			$like  = '%' . $wpdb->esc_like( $search ) . '%';
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE code LIKE %s OR target LIKE %s OR note LIKE %s", $like, $like, $like ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE code LIKE %s OR target LIKE %s OR note LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $like, $like, $per, ( $paged - 1 ) * $per ) );
		}

		return array( is_array( $rows ) ? $rows : array(), $total );
	}

	/**
	 * Totals for the stat tiles.
	 *
	 * @return array Keys links and clicks.
	 */
	public static function stats() {
		global $wpdb;
		$table = esc_sql( self::table() );
		$row   = $wpdb->get_row(
			"SELECT COUNT(*) AS links, COALESCE(SUM(clicks),0) AS clicks FROM {$table}",
			ARRAY_A
		);
		return array(
			'links'  => $row ? (int) $row['links'] : 0,
			'clicks' => $row ? (int) $row['clicks'] : 0,
		);
	}

	/**
	 * Public short URL of a code.
	 *
	 * @param string $code Short code.
	 * @return string
	 */
	public static function short_url( $code ) {
		global $wp_rewrite;
		$prefix = self::settings()['prefix'];

		if ( ! get_option( 'permalink_structure' ) ) {
			return add_query_arg( 'bhtk', $code, home_url( '/' ) );
		}
		$base = ( $wp_rewrite && $wp_rewrite->using_index_permalinks() ) ? 'index.php/' : '';
		return home_url( '/' . $base . ( '' !== $prefix ? $prefix . '/' : '' ) . $code );
	}

	/**
	 * Redirect when the request is a short link.
	 *
	 * Runs early on template_redirect, and only when WordPress found no page,
	 * so real pages are never hidden by a short link.
	 *
	 * @return void
	 */
	public static function maybe_redirect() {
		global $wp;

		$code = '';

		// Fallback format used when pretty permalinks are off. "bhac" is the name used by version 1.x.
		foreach ( array( 'bhtk', 'bhac' ) as $var ) {
			if ( isset( $_GET[ $var ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public redirect, nothing is changed.
				$candidate = sanitize_text_field( wp_unslash( $_GET[ $var ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( self::valid_code( $candidate ) ) {
					$code = $candidate;
					break;
				}
			}
		}

		if ( '' === $code ) {
			if ( ! is_404() ) {
				return;
			}
			$path   = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';
			$prefix = self::settings()['prefix'];
			if ( '' !== $prefix && 0 === strpos( $path, $prefix . '/' ) ) {
				$path = substr( $path, strlen( $prefix ) + 1 );
			}
			if ( ! self::valid_code( $path ) ) {
				return;
			}
			$code = $path;
		}

		$row = self::get_by_code( $code );
		if ( ! $row ) {
			return;
		}

		self::track( (int) $row->id );

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		wp_redirect( $row->target, self::settings()['redirect'], 'Bani Honar Toolkit' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the destination is an external address chosen by an administrator.
		exit;
	}

	/**
	 * Count a click.
	 *
	 * @param int $id Row id.
	 * @return void
	 */
	private static function track( $id ) {
		global $wpdb;
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( 'HEAD' === $method ) {
			return;
		}
		$table = esc_sql( self::table() );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET clicks = clicks + 1, last_click = %s WHERE id = %d",
				current_time( 'mysql', true ),
				$id
			)
		);
	}
}

// phpcs:enable
