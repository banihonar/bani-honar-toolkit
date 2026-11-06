<?php
/**
 * Visitor statistics: data layer.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

/**
 * Stores page views in a custom table and answers the questions the screens ask.
 *
 * A "view" is one page opened. A "visit" is the first page a visitor opens in a
 * browser session (flag is_entry). A "visitor" is one person, told apart by a hash
 * of the IP address and the browser.
 */
final class BHTK_Visits {

	/**
	 * Filter name in the address => column. Column names are fixed, never taken from the request.
	 */
	const FILTERS = array(
		'country'  => 'country',
		'rtype'    => 'ref_type',
		'source'   => 'ref_name',
		'campaign' => 'utm_campaign',
		'dtype'    => 'device_type',
		'brand'    => 'device_brand',
		'model'    => 'device_model',
		'os'       => 'os',
		'browser'  => 'browser',
		'path'     => 'path',
		'ip'       => 'ip',
		'search'   => 'search_term',
		'user'     => 'user_id',
	);

	/**
	 * Version of the table layout. Raised when columns are added.
	 */
	const DB_VERSION = '2';

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bhtk_visits';
	}

	/**
	 * Create or update the table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = esc_sql( self::table() );
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  visited_at datetime NOT NULL,
  visit_day date NOT NULL,
  session_id varchar(24) NOT NULL DEFAULT '',
  visitor_hash char(40) NOT NULL DEFAULT '',
  is_entry tinyint(1) unsigned NOT NULL DEFAULT 0,
  ip varchar(45) NOT NULL DEFAULT '',
  country char(2) NOT NULL DEFAULT '',
  path varchar(255) NOT NULL DEFAULT '',
  title varchar(190) NOT NULL DEFAULT '',
  referrer varchar(500) NOT NULL DEFAULT '',
  ref_type varchar(12) NOT NULL DEFAULT '',
  ref_name varchar(60) NOT NULL DEFAULT '',
  ref_host varchar(120) NOT NULL DEFAULT '',
  utm_source varchar(60) NOT NULL DEFAULT '',
  utm_medium varchar(60) NOT NULL DEFAULT '',
  utm_campaign varchar(100) NOT NULL DEFAULT '',
  device_type varchar(10) NOT NULL DEFAULT '',
  device_brand varchar(40) NOT NULL DEFAULT '',
  device_model varchar(80) NOT NULL DEFAULT '',
  device_code varchar(60) NOT NULL DEFAULT '',
  os varchar(30) NOT NULL DEFAULT '',
  os_version varchar(20) NOT NULL DEFAULT '',
  browser varchar(40) NOT NULL DEFAULT '',
  browser_version varchar(20) NOT NULL DEFAULT '',
  screen varchar(11) NOT NULL DEFAULT '',
  lang varchar(16) NOT NULL DEFAULT '',
  tz varchar(48) NOT NULL DEFAULT '',
  user_agent varchar(512) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  search_term varchar(120) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY visit_day (visit_day),
  KEY visited_at (visited_at),
  KEY visitor_time (visitor_hash,visited_at),
  KEY session_id (session_id),
  KEY ip (ip),
  KEY country (country)
) {$charset};";

		dbDelta( $sql );
		update_option( 'bhtk_visits_db', self::DB_VERSION, true );
	}

	/**
	 * Add columns that newer versions need to a table made by an older version.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'bhtk_visits_db', '' ) === self::DB_VERSION ) {
			return;
		}
		if ( self::table_exists() ) {
			self::install();
		} else {
			update_option( 'bhtk_visits_db', self::DB_VERSION, true );
		}
	}

	/**
	 * Whether the table exists.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		static $exists = null;
		if ( null === $exists ) {
			$table  = esc_sql( self::table() );
			$exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table );
		}
		return $exists;
	}

	/**
	 * Today's date in the site time zone.
	 *
	 * @return string Y-m-d.
	 */
	public static function today() {
		return ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );
	}

	/**
	 * Add a view.
	 *
	 * @param array $row Column => value. Unknown columns are ignored.
	 * @return int Row id, 0 on failure.
	 */
	public static function insert( $row ) {
		global $wpdb;

		$limits = array(
			'session_id'      => 24,
			'visitor_hash'    => 40,
			'ip'              => 45,
			'country'         => 2,
			'path'            => 255,
			'title'           => 190,
			'referrer'        => 500,
			'ref_type'        => 12,
			'ref_name'        => 60,
			'ref_host'        => 120,
			'utm_source'      => 60,
			'utm_medium'      => 60,
			'utm_campaign'    => 100,
			'device_type'     => 10,
			'device_brand'    => 40,
			'device_model'    => 80,
			'device_code'     => 60,
			'os'              => 30,
			'os_version'      => 20,
			'browser'         => 40,
			'browser_version' => 20,
			'screen'          => 11,
			'lang'            => 16,
			'tz'              => 48,
			'user_agent'      => 512,
			'search_term'     => 120,
		);

		$data    = array(
			'visited_at' => gmdate( 'Y-m-d H:i:s' ),
			'visit_day'  => self::today(),
			'is_entry'   => empty( $row['is_entry'] ) ? 0 : 1,
		);
		$formats = array( '%s', '%s', '%d' );

		foreach ( $limits as $column => $max ) {
			$data[ $column ] = isset( $row[ $column ] ) ? mb_substr( (string) $row[ $column ], 0, $max ) : '';
			$formats[]       = '%s';
		}

		$data['user_id'] = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
		$formats[]       = '%d';

		if ( false === $wpdb->insert( self::table(), $data, $formats ) ) {
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Fill the country of rows that were saved without one.
	 *
	 * @param int    $id      Row to fill.
	 * @param string $country Country code.
	 * @param string $ip      Stored IP address; other empty rows of the same address are filled too.
	 * @return void
	 */
	public static function set_country( $id, $country, $ip = '' ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$wpdb->update( $table, array( 'country' => $country ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
		if ( '' !== $ip ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET country = %s WHERE ip = %s AND country = ''", $country, $ip ) );
		}
	}

	/**
	 * Country of an earlier visit from the same address.
	 *
	 * @param string $ip Stored IP address.
	 * @return string Country code or empty.
	 */
	public static function known_country( $ip ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$code  = $wpdb->get_var( $wpdb->prepare( "SELECT country FROM {$table} WHERE ip = %s AND country <> '' ORDER BY id DESC LIMIT 1", $ip ) );
		return is_string( $code ) ? $code : '';
	}

	/**
	 * How many views one visitor sent in the last seconds. Used to stop floods.
	 *
	 * @param string $hash    Visitor hash.
	 * @param int    $seconds Time window.
	 * @return int
	 */
	public static function recent_count( $hash, $seconds ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE visitor_hash = %s AND visited_at >= %s", $hash, gmdate( 'Y-m-d H:i:s', time() - (int) $seconds ) ) );
	}

	/**
	 * Addresses (newest first) of recent views that still have no country.
	 *
	 * @param int $days  How many days back to look.
	 * @param int $limit Maximum number of addresses.
	 * @return string[]
	 */
	public static function ips_without_country( $days, $limit ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$since = gmdate( 'Y-m-d H:i:s', time() - ( max( 1, (int) $days ) * DAY_IN_SECONDS ) );
		$rows  = $wpdb->get_col( $wpdb->prepare( "SELECT ip FROM {$table} WHERE country = '' AND ip <> '' AND visited_at >= %s GROUP BY ip ORDER BY MAX(id) DESC LIMIT %d", $since, (int) $limit ) );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Visitors who sent a view in the last minutes.
	 *
	 * @param int $minutes Minutes.
	 * @return int
	 */
	public static function online_now( $minutes = 5 ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT visitor_hash) FROM {$table} WHERE visited_at >= %s", gmdate( 'Y-m-d H:i:s', time() - ( (int) $minutes * 60 ) ) ) );
	}

	/**
	 * Oldest day with data.
	 *
	 * @return string Y-m-d or empty.
	 */
	public static function first_day() {
		global $wpdb;
		$table = esc_sql( self::table() );
		$day   = $wpdb->get_var( "SELECT MIN(visit_day) FROM {$table}" );
		return is_string( $day ) ? $day : '';
	}

	/**
	 * Number of stored views.
	 *
	 * @return int
	 */
	public static function count_all() {
		global $wpdb;
		$table = esc_sql( self::table() );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Delete views older than a number of days.
	 *
	 * @param int $days Keep this many days. 0 keeps everything.
	 * @return void
	 */
	public static function purge_older_than( $days ) {
		global $wpdb;
		$days = (int) $days;
		if ( $days < 1 ) {
			return;
		}
		$table  = esc_sql( self::table() );
		$cutoff = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '-' . $days . ' days' )->format( 'Y-m-d' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE visit_day < %s", $cutoff ) );
	}

	/**
	 * Views of one member, newest first, for the personal data export.
	 *
	 * @param int $user_id User id.
	 * @param int $limit   Rows.
	 * @param int $offset  First row.
	 * @return array[]
	 */
	public static function rows_of_user( $user_id, $limit = 100, $offset = 0 ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( $user_id < 1 || ! self::table_exists() ) {
			return array();
		}
		$table = esc_sql( self::table() );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, visited_at, ip, country, path, referrer, user_agent FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", $user_id, (int) $limit, (int) $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete the views of one member (personal data eraser).
	 *
	 * @param int $user_id User id.
	 * @return int Number of rows removed.
	 */
	public static function delete_user_rows( $user_id ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( $user_id < 1 || ! self::table_exists() ) {
			return 0;
		}
		$table = esc_sql( self::table() );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE user_id = %d", $user_id ) );
	}

	/**
	 * Keep the views of a deleted member, but forget who the member was.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public static function forget_user( $user_id ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( $user_id < 1 || ! self::table_exists() ) {
			return;
		}
		$table = esc_sql( self::table() );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET user_id = 0 WHERE user_id = %d", $user_id ) );
	}

	/**
	 * Delete every stored view.
	 *
	 * @return void
	 */
	public static function clear() {
		global $wpdb;
		$table = esc_sql( self::table() );
		$wpdb->query( "DELETE FROM {$table}" );
	}

	/**
	 * Read the filters from a request.
	 *
	 * @param array $src Usually $_GET.
	 * @return array Filter name => value, only for filters that are set.
	 */
	public static function read_filters( $src ) {
		$out = array();
		foreach ( array_keys( self::FILTERS ) as $key ) {
			if ( ! isset( $src[ $key ] ) || ! is_scalar( $src[ $key ] ) ) {
				continue;
			}
			$value = mb_substr( sanitize_text_field( wp_unslash( (string) $src[ $key ] ) ), 0, 255 );
			if ( 'country' === $key ) {
				$value = '-' === $value ? '-' : strtoupper( preg_replace( '/[^A-Za-z]/', '', $value ) );
				$value = ( '-' === $value || BHTK_Countries::exists( $value ) ) ? $value : '';
			} elseif ( 'rtype' === $key ) {
				$value = in_array( $value, array( 'direct', 'internal', 'search', 'social', 'referral', 'campaign' ), true ) ? $value : '';
			} elseif ( 'user' === $key ) {
				$value = (string) absint( $value );
				$value = '0' === $value ? '' : $value;
			} elseif ( 'dtype' === $key ) {
				$value = in_array( $value, array( 'desktop', 'mobile', 'tablet', 'tv', 'console', 'unknown' ), true ) ? $value : '';
			}
			if ( '' !== $value ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Read the date range from a request.
	 *
	 * @param array $src Usually $_GET.
	 * @return array key, from, to (Y-m-d in the site time zone).
	 */
	public static function resolve_range( $src ) {
		$allowed = array( 'today', 'yesterday', '7', '30', '90', '365', 'all', 'custom' );
		$key     = isset( $src['range'] ) && is_scalar( $src['range'] ) ? sanitize_key( (string) $src['range'] ) : '30';
		$key     = in_array( $key, $allowed, true ) ? $key : '30';

		$now   = new DateTimeImmutable( 'now', wp_timezone() );
		$today = $now->format( 'Y-m-d' );
		$from  = $today;
		$to    = $today;

		switch ( $key ) {
			case 'yesterday':
				$from = $now->modify( '-1 day' )->format( 'Y-m-d' );
				$to   = $from;
				break;
			case '7':
			case '30':
			case '90':
			case '365':
				$from = $now->modify( '-' . ( (int) $key - 1 ) . ' days' )->format( 'Y-m-d' );
				break;
			case 'all':
				$from = '2000-01-01';
				break;
			case 'custom':
				$a = isset( $src['from'] ) && is_scalar( $src['from'] ) ? (string) $src['from'] : '';
				$b = isset( $src['to'] ) && is_scalar( $src['to'] ) ? (string) $src['to'] : '';
				if ( self::valid_day( $a ) && self::valid_day( $b ) && $a <= $b ) {
					$from = $a;
					$to   = $b;
				} else {
					$key  = '30';
					$from = $now->modify( '-29 days' )->format( 'Y-m-d' );
				}
				break;
		}

		return array(
			'key'  => $key,
			'from' => $from,
			'to'   => $to,
		);
	}

	/**
	 * Whether a string is a real Y-m-d date.
	 *
	 * @param string $day Candidate.
	 * @return bool
	 */
	private static function valid_day( $day ) {
		return (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $day, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * WHERE clause for a range and filters.
	 *
	 * @param array $range   Range from resolve_range().
	 * @param array $filters Filters from read_filters().
	 * @param bool  $entries Only first pages of visits.
	 * @return array SQL and its arguments.
	 */
	private static function where( $range, $filters, $entries = false ) {
		global $wpdb;
		$sql  = ' WHERE visit_day BETWEEN %s AND %s';
		$args = array( $range['from'], $range['to'] );

		foreach ( self::FILTERS as $key => $column ) {
			if ( ! isset( $filters[ $key ] ) || '' === $filters[ $key ] ) {
				continue;
			}
			$value = $filters[ $key ];
			if ( 'ip' === $key ) {
				$sql   .= " AND {$column} LIKE %s";
				$args[] = $wpdb->esc_like( $value ) . '%';
				continue;
			}
			if ( 'country' === $key && '-' === $value ) {
				$value = '';
			}
			$sql   .= " AND {$column} = %s";
			$args[] = $value;
		}
		if ( $entries ) {
			$sql .= ' AND is_entry = 1';
		}
		return array( $sql, $args );
	}

	/**
	 * Run a prepared query.
	 *
	 * @param string $sql  SQL with placeholders.
	 * @param array  $args Arguments.
	 * @return string Prepared SQL.
	 */
	private static function prepare( $sql, $args ) {
		global $wpdb;
		return $args ? $wpdb->prepare( $sql, $args ) : $sql;
	}

	/**
	 * Totals for the tiles.
	 *
	 * @param array $range   Range.
	 * @param array $filters Filters.
	 * @return array views, visitors, visits, countries.
	 */
	public static function summary( $range, $filters ) {
		global $wpdb;
		$table                = esc_sql( self::table() );
		list( $where, $args ) = self::where( $range, $filters );

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where comes from self::where(): fixed fragments with %s placeholders and column names from the FILTERS list; its values are bound by prepare().
		$row = $wpdb->get_row(
			self::prepare(
				"SELECT COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors, COALESCE(SUM(is_entry), 0) AS visits, COUNT(DISTINCT CASE WHEN country <> '' THEN country END) AS countries FROM {$table}{$where}",
				$args
			),
			ARRAY_A
		);

		return array(
			'views'     => $row ? (int) $row['views'] : 0,
			'visitors'  => $row ? (int) $row['visitors'] : 0,
			'visits'    => $row ? (int) $row['visits'] : 0,
			'countries' => $row ? (int) $row['countries'] : 0,
		);
	}

	/**
	 * Views, visitors and visits for each day.
	 *
	 * @param array $range   Range.
	 * @param array $filters Filters.
	 * @return array Y-m-d => array( views, visitors, visits ).
	 */
	public static function daily( $range, $filters ) {
		global $wpdb;
		$table                = esc_sql( self::table() );
		list( $where, $args ) = self::where( $range, $filters );

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where comes from self::where(): fixed fragments with %s placeholders and column names from the FILTERS list; its values are bound by prepare().
		$rows = $wpdb->get_results(
			self::prepare(
				"SELECT visit_day, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors, COALESCE(SUM(is_entry), 0) AS visits FROM {$table}{$where} GROUP BY visit_day ORDER BY visit_day ASC",
				$args
			),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row['visit_day'] ] = array(
				'views'    => (int) $row['views'],
				'visitors' => (int) $row['visitors'],
				'visits'   => (int) $row['visits'],
			);
		}
		return $out;
	}

	/**
	 * Most common values of a column, counted in visits (first pages).
	 *
	 * @param string $column      One of the filter columns.
	 * @param array  $range       Range.
	 * @param array  $filters     Filters.
	 * @param int    $limit       Rows.
	 * @param bool   $with_empty  Count visits that have no value too.
	 * @return array label => count, most common first.
	 */
	public static function top( $column, $range, $filters, $limit = 10, $with_empty = false ) {
		global $wpdb;
		if ( ! in_array( $column, self::FILTERS, true ) ) {
			return array();
		}
		$column               = esc_sql( $column ); // Already one of a fixed list of column names.
		$table                = esc_sql( self::table() );
		list( $where, $args ) = self::where( $range, $filters, true );
		if ( ! $with_empty ) {
			$where .= " AND {$column} <> ''";
		}
		$args[] = (int) $limit;

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where comes from self::where(): fixed fragments with %s placeholders and column names from the FILTERS list; its values are bound by prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$column} AS label, COUNT(*) AS c FROM {$table}{$where} GROUP BY {$column} ORDER BY c DESC, label ASC LIMIT %d", $args ), ARRAY_A );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['label'] ] = (int) $row['c'];
		}
		return $out;
	}

	/**
	 * Number of stored page views for each of some addresses.
	 *
	 * @param array $ips Addresses.
	 * @return array ip => count.
	 */
	public static function counts_for_ips( $ips ) {
		global $wpdb;
		$ips = array_values( array_unique( array_filter( array_map( 'strval', (array) $ips ) ) ) );
		if ( ! $ips || ! self::table_exists() ) {
			return array();
		}
		$table = esc_sql( self::table() );
		$marks = implode( ',', array_fill( 0, count( $ips ), '%s' ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT ip, COUNT(*) AS c FROM {$table} WHERE ip IN ({$marks}) GROUP BY ip", $ips ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- only %s placeholders are built.
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['ip'] ] = (int) $row['c'];
		}
		return $out;
	}

	/**
	 * Words people typed into the search box of the website.
	 *
	 * @param array $range   Range.
	 * @param array $filters Filters.
	 * @param int   $limit   Rows.
	 * @return array term => number of searches.
	 */
	public static function searches( $range, $filters, $limit = 10 ) {
		global $wpdb;
		$table                = esc_sql( self::table() );
		list( $where, $args ) = self::where( $range, $filters );
		$args[]               = (int) $limit;

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where comes from self::where(): fixed fragments with %s placeholders and column names from the FILTERS list; its values are bound by prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT search_term AS label, COUNT(*) AS c FROM {$table}{$where} AND search_term <> '' GROUP BY search_term ORDER BY c DESC, label ASC LIMIT %d", $args ), ARRAY_A );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['label'] ] = (int) $row['c'];
		}
		return $out;
	}

	/**
	 * Sources of visits: type and name together.
	 *
	 * @param array $range   Range.
	 * @param array $filters Filters.
	 * @param int   $limit   Rows.
	 * @return array Rows with type, name, count.
	 */
	public static function sources( $range, $filters, $limit = 10 ) {
		global $wpdb;
		$table                = esc_sql( self::table() );
		list( $where, $args ) = self::where( $range, $filters, true );
		$args[]               = (int) $limit;

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where comes from self::where(): fixed fragments with %s placeholders and column names from the FILTERS list; its values are bound by prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ref_type, ref_name, COUNT(*) AS c FROM {$table}{$where} GROUP BY ref_type, ref_name ORDER BY c DESC, ref_name ASC LIMIT %d", $args ), ARRAY_A );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'type'  => (string) $row['ref_type'],
				'name'  => (string) $row['ref_name'],
				'count' => (int) $row['c'],
			);
		}
		return $out;
	}

	/**
	 * Phone and tablet models, counted in visits.
	 *
	 * @param array $range   Range.
	 * @param array $filters Filters.
	 * @param int   $limit   Rows.
	 * @return array Rows with brand, model, count.
	 */
	public static function models( $range, $filters, $limit = 10 ) {
		global $wpdb;
		$table                = esc_sql( self::table() );
		list( $where, $args ) = self::where( $range, $filters, true );
		$where               .= " AND device_type IN ('mobile', 'tablet') AND device_model <> ''";
		$args[]               = (int) $limit;

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where comes from self::where(): fixed fragments with %s placeholders and column names from the FILTERS list; its values are bound by prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT device_brand, device_model, COUNT(*) AS c FROM {$table}{$where} GROUP BY device_brand, device_model ORDER BY c DESC, device_model ASC LIMIT %d", $args ), ARRAY_A );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'brand' => (string) $row['device_brand'],
				'model' => (string) $row['device_model'],
				'count' => (int) $row['c'],
			);
		}
		return $out;
	}

	/**
	 * Most viewed pages.
	 *
	 * @param array $range   Range.
	 * @param array $filters Filters.
	 * @param int   $limit   Rows.
	 * @return array Rows with path, title, views, visitors.
	 */
	public static function pages( $range, $filters, $limit = 10 ) {
		global $wpdb;
		$table                = esc_sql( self::table() );
		list( $where, $args ) = self::where( $range, $filters );
		$args[]               = (int) $limit;

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where comes from self::where(): fixed fragments with %s placeholders and column names from the FILTERS list; its values are bound by prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT path, MAX(title) AS title, COUNT(*) AS views, COUNT(DISTINCT visitor_hash) AS visitors FROM {$table}{$where} GROUP BY path ORDER BY views DESC, path ASC LIMIT %d", $args ), ARRAY_A );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'path'     => (string) $row['path'],
				'title'    => (string) $row['title'],
				'views'    => (int) $row['views'],
				'visitors' => (int) $row['visitors'],
			);
		}
		return $out;
	}

	/**
	 * One page of the visit log, newest first.
	 *
	 * @param array $range    Range.
	 * @param array $filters  Filters.
	 * @param int   $paged    Page number.
	 * @param int   $per_page Rows per page.
	 * @return array Rows and the total number of matching rows.
	 */
	public static function log( $range, $filters, $paged, $per_page ) {
		global $wpdb;
		$table                = esc_sql( self::table() );
		list( $where, $args ) = self::where( $range, $filters );

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where comes from self::where(): fixed fragments with %s placeholders and column names from the FILTERS list; its values are bound by prepare().
		$total = (int) $wpdb->get_var( self::prepare( "SELECT COUNT(*) FROM {$table}{$where}", $args ) );

		$per_page = max( 1, (int) $per_page );
		$args[]   = $per_page;
		$args[]   = max( 0, ( (int) $paged - 1 ) * $per_page );
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where comes from self::where(): fixed fragments with %s placeholders and column names from the FILTERS list; $args holds one value for each of them plus the two paging numbers.
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table}{$where} ORDER BY id DESC LIMIT %d OFFSET %d", $args ) );

		return array( is_array( $rows ) ? $rows : array(), $total );
	}

	/**
	 * Rows for the CSV export, in chunks so memory stays small.
	 *
	 * @param array $range   Range.
	 * @param array $filters Filters.
	 * @param int   $after   Return rows with an id lower than this (0 for the newest).
	 * @param int   $limit   Rows.
	 * @return array
	 */
	public static function export_chunk( $range, $filters, $after, $limit ) {
		global $wpdb;
		$table                = esc_sql( self::table() );
		list( $where, $args ) = self::where( $range, $filters );
		if ( $after > 0 ) {
			$where .= ' AND id < %d';
			$args[] = (int) $after;
		}
		$args[] = (int) $limit;
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where comes from self::where(): fixed fragments with %s placeholders and column names from the FILTERS list; its values are bound by prepare().
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table}{$where} ORDER BY id DESC LIMIT %d", $args ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
