<?php
/**
 * Visitor statistics module.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts visits to the website and shows how many, where from, from which IP and
 * country, and on which device.
 *
 * A small script on every page sends one request per page opened. Because the script
 * runs in the visitor's browser, visits are counted even when a cache plugin serves
 * the page without running PHP, and most robots (which do not run scripts) are left out.
 */
final class BHTK_Module_Visitor_Stats extends BHTK_Module {

	const PAGE   = 'bhtk-visits';
	const ACTION = 'bhtk_hit';
	const CRON   = 'bhtk_visits_maintenance';

	/**
	 * The export and delete buttons must work even while counting is switched off.
	 */
	public function __construct() {
		add_action( 'admin_post_bhtk_visits_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_bhtk_visits_clear', array( $this, 'handle_clear' ) );
		add_action( 'admin_post_bhtk_visits_block', array( $this, 'handle_block' ) );
		add_action( 'admin_post_bhtk_visits_unblock', array( $this, 'handle_unblock' ) );
		add_action( 'admin_init', array( 'BHTK_Visits', 'maybe_upgrade' ) );
		// A deleted member stays out of the log, even while counting is switched off.
		add_action( 'delete_user', array( 'BHTK_Visits', 'forget_user' ) );
	}

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'visitor-stats';
	}

	/**
	 * Module name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Visitor Stats', 'bani-honar-toolkit' );
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'See every visit to your website: how many, where from, IP address, country and the name of the device.', 'bani-honar-toolkit' );
	}

	/**
	 * Dashboard icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-chart-area';
	}

	/**
	 * It stores visitor data, so the owner switches it on on purpose.
	 *
	 * @return bool
	 */
	public function is_default_enabled() {
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
	 * Default settings.
	 *
	 * @return array
	 */
	public function default_settings() {
		return array(
			'ignore_admins' => 1,
			'ignore_users'  => 0,
			'respect_dnt'   => 0,
			'anonymize_ip'  => 0,
			'ip_source'     => 'auto',
			'geo_online'    => 0,
			'geo_provider'  => 'auto',
			'retention'     => 365,
			'exclude_ips'   => '',
			'calendar'      => 'auto',
		);
	}

	/**
	 * Clean the submitted settings.
	 *
	 * @param array $raw Submitted values.
	 * @return array
	 */
	public function sanitize_settings( $raw ) {
		$flag = static function ( $key ) use ( $raw ) {
			return empty( $raw[ $key ] ) ? 0 : 1;
		};
		$pick = static function ( $key, $allowed, $default ) use ( $raw ) {
			$value = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_key( (string) $raw[ $key ] ) : $default;
			return in_array( $value, $allowed, true ) ? $value : $default;
		};

		return array(
			'ignore_admins' => $flag( 'ignore_admins' ),
			'ignore_users'  => $flag( 'ignore_users' ),
			'respect_dnt'   => $flag( 'respect_dnt' ),
			'anonymize_ip'  => $flag( 'anonymize_ip' ),
			'ip_source'     => $pick( 'ip_source', array( 'auto', 'remote_addr' ), 'auto' ),
			'geo_online'    => $flag( 'geo_online' ),
			'geo_provider'  => $pick( 'geo_provider', array_merge( array( 'auto' ), array_keys( BHTK_Geo::providers() ) ), 'auto' ),
			'retention'     => isset( $raw['retention'] ) ? max( 0, min( 3650, (int) $raw['retention'] ) ) : 365,
			'exclude_ips'   => isset( $raw['exclude_ips'] ) ? BHTK_Geo::clean_list( (string) $raw['exclude_ips'] ) : '',
			'calendar'      => $pick( 'calendar', array( 'auto', 'jalali', 'gregorian' ), 'auto' ),
		);
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
				'title'    => __( 'Visitor Stats', 'bani-honar-toolkit' ),
				'callback' => array( 'BHTK_Visits_Page', 'render' ),
			),
		);
	}

	/**
	 * Create the table and schedule the daily clean-up.
	 *
	 * @return void
	 */
	public function install() {
		BHTK_Visits::install();
		$this->schedule();
	}

	/**
	 * Stop the scheduled clean-up. Collected data stays.
	 *
	 * @return void
	 */
	public function on_disable() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Make sure the clean-up is scheduled.
	 *
	 * @return void
	 */
	private function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON );
		}
	}

	/**
	 * Hooks while the module is on.
	 *
	 * @return void
	 */
	public function boot() {
		// Blocked addresses are turned away before anything else is loaded.
		add_action( 'init', array( 'BHTK_Blocklist', 'enforce' ), 0 );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle_hit' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle_hit' ) );
		add_action( self::CRON, array( $this, 'maintenance' ) );
		$this->schedule();

		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		}
	}

	/**
	 * Whether the current page should carry the counting script.
	 *
	 * @return bool
	 */
	private function should_track_page() {
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
		if ( is_user_logged_in() && ( $s['ignore_users'] || ( $s['ignore_admins'] && current_user_can( BHTK_Plugin::capability() ) ) ) ) {
			return false;
		}

		/**
		 * Filter whether the current page is counted.
		 *
		 * @param bool $track True to count the page.
		 */
		return (bool) apply_filters( 'bhtk_visits_track_page', true );
	}

	/**
	 * Add the counting script.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! $this->should_track_page() ) {
			return;
		}
		$s = $this->get_settings();

		wp_enqueue_script( 'bhtk-visits', BHTK_URL . 'assets/js/visits.js', array(), BHTK_VERSION, true );
		wp_add_inline_script(
			'bhtk-visits',
			'window.bhtkVisits = ' . wp_json_encode(
				array(
					'url'    => admin_url( 'admin-ajax.php' ),
					'action' => self::ACTION,
					'dnt'    => (bool) $s['respect_dnt'],
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Answer the counting request. The browser does not wait for an answer.
	 *
	 * @return void
	 */
	public function handle_hit() {
		$pending = null;
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$pending = $this->record_hit();
		}

		nocache_headers();
		status_header( 204 );

		if ( $pending ) {
			// Let the browser go, then look up the country of a new address without keeping anyone waiting.
			ignore_user_abort( true );
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 20 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- best effort.
			}
			header( 'Connection: close' );
			header( 'Content-Length: 0' );
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			} else {
				flush();
			}
			$this->finish_country( $pending );
		}
		exit;
	}

	/**
	 * Validate and store one view.
	 *
	 * @return array|null Details for the country lookup that still has to run, or null.
	 */
	private function record_hit() {
		$s  = $this->get_settings();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 ) : '';

		if ( BHTK_UA::is_bot( $ua ) ) {
			return null;
		}
		if ( $s['respect_dnt'] && isset( $_SERVER['HTTP_DNT'] ) && '1' === $_SERVER['HTTP_DNT'] ) {
			return null;
		}
		if ( is_user_logged_in() && ( $s['ignore_users'] || ( $s['ignore_admins'] && current_user_can( BHTK_Plugin::capability() ) ) ) ) {
			return null;
		}

		$ip = BHTK_Geo::client_ip( $s['ip_source'] );
		if ( '' === $ip || BHTK_Geo::in_list( $ip, $s['exclude_ips'] ) ) {
			return null;
		}

		$post = static function ( $key, $max = 255 ) {
			// The counting request comes from visitors who are not logged in, so there is no nonce to check; every value is cleaned and cut to length.
			return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? mb_substr( sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ), 0, $max ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		};

		// Page address: decoded so Persian addresses stay readable, control characters removed.
		$path = isset( $_POST['p'] ) && is_scalar( $_POST['p'] ) ? rawurldecode( wp_unslash( (string) $_POST['p'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$path = preg_replace( '/[\x00-\x1F\x7F]/', '', $path );
		if ( '' === $path || '/' !== $path[0] || ! mb_check_encoding( $path, 'UTF-8' ) ) {
			return null;
		}
		$path = mb_substr( $path, 0, 255 );

		// Campaign tags and the few query values that identify a page on sites without pretty permalinks.
		$query = array();
		parse_str( ltrim( $post( 'q', 400 ), '?' ), $query );
		$utm = static function ( $key, $max ) use ( $query ) {
			return isset( $query[ $key ] ) && is_string( $query[ $key ] ) ? mb_substr( sanitize_text_field( $query[ $key ] ), 0, $max ) : '';
		};
		$utm_source   = $utm( 'utm_source', 60 );
		$utm_medium   = $utm( 'utm_medium', 60 );
		$utm_campaign = $utm( 'utm_campaign', 100 );

		if ( '/' === $path ) {
			$keep = array();
			foreach ( array( 'p', 'page_id', 'cat', 'tag', 'post_type', 'product' ) as $name ) {
				if ( isset( $query[ $name ] ) && is_string( $query[ $name ] ) && preg_match( '/^[A-Za-z0-9_-]{1,60}$/', $query[ $name ] ) ) {
					$keep[ $name ] = $query[ $name ];
				}
			}
			if ( $keep ) {
				$path = '/?' . http_build_query( $keep );
			}
		}

		$referrer = BHTK_Referrer::clean_url( isset( $_POST['r'] ) && is_scalar( $_POST['r'] ) ? wp_unslash( (string) $_POST['r'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$source    = BHTK_Referrer::classify( $referrer, $site_host, $utm_source, $utm_medium, $ua );

		// Session: the browser keeps a random id while the tab is open.
		$sid      = $post( 'sid', 24 );
		$is_entry = 1;
		if ( preg_match( '/^[a-z0-9]{8,24}$/i', $sid ) ) {
			$is_entry = '1' === $post( 'n', 1 ) ? 1 : 0;
		} else {
			$sid = substr( md5( $ip . '|' . $ua . '|' . microtime( true ) ), 0, 16 );
		}

		// Words typed into the search box of this website (?s=word, or the pretty search address).
		$term = '';
		if ( isset( $query['s'] ) && is_string( $query['s'] ) ) {
			$term = $query['s'];
		} else {
			$base = isset( $GLOBALS['wp_rewrite']->search_base ) ? (string) $GLOBALS['wp_rewrite']->search_base : 'search';
			if ( preg_match( '#^/' . preg_quote( $base, '#' ) . '/([^/?]+)#u', $path, $found ) ) {
				$term = $found[1];
			}
		}
		$term = trim( (string) preg_replace( '/\s+/u', ' ', sanitize_text_field( $term ) ) );
		$term = mb_strtolower( mb_substr( $term, 0, 120 ) );

		// A ceiling for the whole site, so a flood of made-up visitors can never fill the database.
		/**
		 * The most visits stored per minute on the whole site. Use 0 for no limit.
		 *
		 * @param int $max Default 600.
		 */
		$max_per_minute = (int) apply_filters( 'bhtk_visits_max_per_minute', 600 );
		if ( $max_per_minute > 0 ) {
			$bucket = 'bhtk_rate_' . gmdate( 'YmdHi' );
			$used   = (int) get_transient( $bucket );
			if ( $used >= $max_per_minute ) {
				return null;
			}
			set_transient( $bucket, $used + 1, 2 * MINUTE_IN_SECONDS );
		}

		$hash = sha1( $ip . '|' . $ua . '|' . wp_salt( 'auth' ) );
		if ( BHTK_Visits::recent_count( $hash, 60 ) >= 40 ) {
			return null;
		}

		$width  = (int) $post( 'sw', 5 );
		$height = (int) $post( 'sh', 5 );
		$dpr    = (float) $post( 'dpr', 6 );
		$hints  = array(
			'model'            => $post( 'hm', 60 ),
			'platform'         => $post( 'hp', 20 ),
			'platform_version' => $post( 'hv', 20 ),
			'mobile'           => '1' === $post( 'hmob', 1 ),
			'touch'            => (int) $post( 'tp', 3 ),
			'width'            => $width,
			'height'           => $height,
			'dpr'              => $dpr,
		);
		$device = BHTK_UA::parse( $ua, $hints );

		$screen = ( $width >= 100 && $width <= 20000 && $height >= 100 && $height <= 20000 ) ? $width . 'x' . $height : '';

		// Country: CDN headers, an earlier visit from the same address, then (after the answer is sent) an online lookup.
		$stored_ip = $s['anonymize_ip'] ? BHTK_Geo::anonymize( $ip ) : $ip;
		$country   = BHTK_Geo::country_from_headers();
		if ( '' === $country && ! $s['anonymize_ip'] ) {
			$country = BHTK_Visits::known_country( $stored_ip );
		}
		if ( '' === $country ) {
			$cached  = get_transient( 'bhtk_gc_' . md5( $ip ) );
			$country = is_string( $cached ) ? $cached : '';
		}

		/**
		 * Set the country of a visit yourself.
		 *
		 * @param string $country Two-letter code or an empty string.
		 * @param string $ip      Visitor IP address.
		 */
		$country = (string) apply_filters( 'bhtk_visit_country', $country, $ip );
		$country = BHTK_Countries::exists( $country ) ? strtoupper( $country ) : '';

		$id = BHTK_Visits::insert(
			array(
				'session_id'      => $sid,
				'visitor_hash'    => $hash,
				'is_entry'        => $is_entry,
				'ip'              => $stored_ip,
				'country'         => $country,
				'path'            => $path,
				'title'           => $post( 't', 190 ),
				'referrer'        => $referrer,
				'ref_type'        => $source['type'],
				'ref_name'        => $source['name'],
				'ref_host'        => $source['host'],
				'utm_source'      => $utm_source,
				'utm_medium'      => $utm_medium,
				'utm_campaign'    => $utm_campaign,
				'device_type'     => $device['type'],
				'device_brand'    => $device['brand'],
				'device_model'    => $device['model'],
				'device_code'     => $device['code'],
				'os'              => $device['os'],
				'os_version'      => $device['os_version'],
				'browser'         => $device['browser'],
				'browser_version' => $device['browser_version'],
				'screen'          => $screen,
				'lang'            => $post( 'l', 16 ),
				'tz'              => $post( 'tz', 48 ),
				'user_agent'      => $ua,
				'search_term'     => $term,
				'user_id'         => is_user_logged_in() ? get_current_user_id() : 0,
			)
		);

		if ( $id && '' === $country && $s['geo_online'] && BHTK_Geo::is_public( $ip ) ) {
			return array(
				'id'        => $id,
				'ip'        => $ip,
				'stored_ip' => $s['anonymize_ip'] ? '' : $stored_ip,
				'provider'  => $s['geo_provider'],
			);
		}
		return null;
	}

	/**
	 * Look up the country of a new address and save it.
	 *
	 * @param array $pending Details returned by record_hit().
	 * @return void
	 */
	private function finish_country( $pending ) {
		$country = BHTK_Geo::lookup_online( $pending['ip'], $pending['provider'] );
		if ( '' === $country ) {
			return;
		}
		set_transient( 'bhtk_gc_' . md5( $pending['ip'] ), $country, DAY_IN_SECONDS );
		BHTK_Visits::set_country( $pending['id'], $country, $pending['stored_ip'] );
	}

	/**
	 * Hourly work: delete old views and fill in countries that could not be found earlier.
	 *
	 * @return void
	 */
	public function maintenance() {
		$s = $this->get_settings();
		BHTK_Visits::purge_older_than( $s['retention'] );
		BHTK_Blocklist::purge();

		if ( ! $s['geo_online'] || $s['anonymize_ip'] || get_transient( 'bhtk_geo_down' ) ) {
			return;
		}
		$deadline = time() + 20;
		foreach ( BHTK_Visits::ips_without_country( 3, 20 ) as $ip ) {
			if ( time() > $deadline ) {
				break;
			}
			$country = BHTK_Geo::lookup_online( $ip, $s['geo_provider'] );
			if ( '' !== $country ) {
				BHTK_Visits::set_country( 0, $country, $ip );
			}
		}
	}

	/**
	 * Download the visit log as a CSV file that Excel opens correctly.
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'bhtk_visits_export', '_bhtk_nonce' );

		if ( ! BHTK_Visits::table_exists() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
			exit;
		}

		$post    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above; the values are cleaned by the functions below.
		$range   = BHTK_Visits::resolve_range( $post );
		$filters = BHTK_Visits::read_filters( $post );
		if ( 'all' === $range['key'] ) {
			$first         = BHTK_Visits::first_day();
			$range['from'] = '' !== $first ? $first : $range['to'];
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="bani-honar-visits-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming a download.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- byte order mark so Excel reads Persian text.
		fputcsv(
			$out,
			array(
				__( 'Date and time', 'bani-honar-toolkit' ),
				__( 'IP address', 'bani-honar-toolkit' ),
				__( 'Country code', 'bani-honar-toolkit' ),
				__( 'Country', 'bani-honar-toolkit' ),
				__( 'Source type', 'bani-honar-toolkit' ),
				__( 'Source', 'bani-honar-toolkit' ),
				__( 'Referrer', 'bani-honar-toolkit' ),
				__( 'Page', 'bani-honar-toolkit' ),
				__( 'Page title', 'bani-honar-toolkit' ),
				__( 'Campaign source', 'bani-honar-toolkit' ),
				__( 'Campaign medium', 'bani-honar-toolkit' ),
				__( 'Campaign name', 'bani-honar-toolkit' ),
				__( 'Device type', 'bani-honar-toolkit' ),
				__( 'Brand', 'bani-honar-toolkit' ),
				__( 'Model', 'bani-honar-toolkit' ),
				__( 'Model code', 'bani-honar-toolkit' ),
				__( 'System', 'bani-honar-toolkit' ),
				__( 'System version', 'bani-honar-toolkit' ),
				__( 'Browser', 'bani-honar-toolkit' ),
				__( 'Browser version', 'bani-honar-toolkit' ),
				__( 'Screen', 'bani-honar-toolkit' ),
				__( 'Language', 'bani-honar-toolkit' ),
				__( 'Time zone', 'bani-honar-toolkit' ),
				__( 'Logged-in user', 'bani-honar-toolkit' ),
				__( 'Search on the site', 'bani-honar-toolkit' ),
				__( 'First page of a visit', 'bani-honar-toolkit' ),
				__( 'User agent', 'bani-honar-toolkit' ),
			)
		);

		$after = 0;
		$sent  = 0;
		while ( $sent < 100000 ) {
			$rows = BHTK_Visits::export_chunk( $range, $filters, $after, 1000 );
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $row ) {
				$after = (int) $row['id'];
				$when  = ( new DateTimeImmutable( $row['visited_at'] . ' UTC' ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
				fputcsv(
					$out,
					array_map(
						array( __CLASS__, 'csv_cell' ),
						array(
							$when,
							$row['ip'],
							$row['country'],
							'' !== $row['country'] ? BHTK_Countries::name( $row['country'] ) : '',
							$row['ref_type'],
							$row['ref_name'],
							$row['referrer'],
							$row['path'],
							$row['title'],
							$row['utm_source'],
							$row['utm_medium'],
							$row['utm_campaign'],
							$row['device_type'],
							$row['device_brand'],
							$row['device_model'],
							$row['device_code'],
							$row['os'],
							$row['os_version'],
							$row['browser'],
							$row['browser_version'],
							$row['screen'],
							$row['lang'],
							$row['tz'],
							self::user_label( $row['user_id'] ),
							$row['search_term'],
							$row['is_entry'] ? '1' : '0',
							$row['user_agent'],
						)
					)
				);
				++$sent;
			}
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Login name of a member, for the CSV file.
	 *
	 * @param int|string $user_id User id.
	 * @return string
	 */
	private static function user_label( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return '';
		}
		$user = get_userdata( $user_id );
		return $user ? $user->user_login : '#' . $user_id;
	}

	/**
	 * Stop a spreadsheet from running a cell as a formula.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Delete every stored visit.
	 *
	 * @return void
	 */
	public function handle_clear() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'bhtk_visits_clear', '_bhtk_nonce' );

		if ( BHTK_Visits::table_exists() ) {
			BHTK_Visits::clear();
		}
		BHTK_Admin::flash( 'success', __( 'All stored visits were deleted.', 'bani-honar-toolkit' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&view=settings' ) );
		exit;
	}

	/**
	 * Block an address.
	 *
	 * @return void
	 */
	public function handle_block() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'bhtk_visits_block', '_bhtk_nonce' );

		$post   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$target = isset( $post['target'] ) && is_scalar( $post['target'] ) ? (string) $post['target'] : '';
		$note   = isset( $post['note'] ) && is_scalar( $post['note'] ) ? (string) $post['note'] : '';
		$length = isset( $post['duration'] ) && is_scalar( $post['duration'] ) ? sanitize_key( (string) $post['duration'] ) : 'forever';
		$s      = $this->get_settings();
		$result = BHTK_Blocklist::add( $target, $note, $length, BHTK_Geo::candidate_ips( $s['ip_source'] ) );

		$messages = array(
			'invalid' => __( 'That is not a valid IP address. Type one address, or an IPv4 range such as 198.51.100.0/24.', 'bani-honar-toolkit' ),
			'private' => __( 'Private and reserved addresses cannot be blocked.', 'bani-honar-toolkit' ),
			'own'     => __( 'That would block your own address, so it was not saved.', 'bani-honar-toolkit' ),
			'full'    => __( 'The list is full. Remove some addresses first.', 'bani-honar-toolkit' ),
		);
		if ( 'ok' === $result ) {
			BHTK_Admin::flash( 'success', __( 'The address is blocked now.', 'bani-honar-toolkit' ) );
		} else {
			BHTK_Admin::flash( 'error', $messages[ $result ] );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'view' => 'blocked' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Unblock an address.
	 *
	 * @return void
	 */
	public function handle_unblock() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'bhtk_visits_unblock', '_bhtk_nonce' );

		$target = isset( $_POST['target'] ) && is_scalar( $_POST['target'] ) ? BHTK_Blocklist::clean_target( sanitize_text_field( wp_unslash( (string) $_POST['target'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		if ( '' !== $target ) {
			BHTK_Blocklist::remove( $target );
			BHTK_Admin::flash( 'success', __( 'The address is unblocked.', 'bani-honar-toolkit' ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'view' => 'blocked' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
