<?php
/**
 * List of blocked IP addresses and the check that turns those visitors away.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocked IP addresses. Part of the Visitor Stats module: when the module is off, nobody is blocked.
 */
final class BHTK_Blocklist {

	const OPTION = 'bhtk_blocked_ips';
	const MAX    = 500;

	/**
	 * Durations offered in the form: key => seconds (0 = until removed).
	 *
	 * @return array
	 */
	public static function durations() {
		return array(
			'1h'      => HOUR_IN_SECONDS,
			'24h'     => DAY_IN_SECONDS,
			'7d'      => 7 * DAY_IN_SECONDS,
			'30d'     => 30 * DAY_IN_SECONDS,
			'forever' => 0,
		);
	}

	/**
	 * Every saved entry: address or range => note, time, until, by.
	 *
	 * @return array
	 */
	public static function all() {
		$list = get_option( self::OPTION, array() );
		return is_array( $list ) ? $list : array();
	}

	/**
	 * Entries that are still in force.
	 *
	 * @return array
	 */
	public static function active() {
		$now = time();
		$out = array();
		foreach ( self::all() as $target => $entry ) {
			$until = isset( $entry['until'] ) ? (int) $entry['until'] : 0;
			if ( 0 === $until || $until > $now ) {
				$out[ (string) $target ] = $entry;
			}
		}
		return $out;
	}

	/**
	 * Turn what the owner typed into one clean address or IPv4 range.
	 *
	 * @param string $text Typed text.
	 * @return string Empty when it is not an address.
	 */
	public static function clean_target( $text ) {
		$first = trim( (string) strtok( trim( (string) $text ), "\r\n," ) );
		$clean = BHTK_Geo::clean_list( $first );
		return false === strpos( $clean, "\n" ) ? $clean : '';
	}

	/**
	 * Whether a target is a public address or a range of public addresses.
	 *
	 * @param string $target Address or range.
	 * @return bool
	 */
	public static function is_public_target( $target ) {
		$base = false !== strpos( $target, '/' ) ? strstr( $target, '/', true ) : $target;
		return BHTK_Geo::is_public( $base );
	}

	/**
	 * Block an address or range.
	 *
	 * @param string $text     Typed address.
	 * @param string $note     Reason, shown in the list.
	 * @param string $duration Key from durations().
	 * @param string|string[] $my_ip Address (or addresses) of the person blocking; never blocked.
	 * @return string 'ok' or an error code: invalid, private, own, full.
	 */
	public static function add( $text, $note, $duration, $my_ip ) {
		$target = self::clean_target( $text );
		if ( '' === $target ) {
			return 'invalid';
		}
		if ( ! self::is_public_target( $target ) ) {
			return 'private';
		}
		foreach ( (array) $my_ip as $mine ) {
			if ( '' !== (string) $mine && BHTK_Geo::in_list( (string) $mine, $target ) ) {
				return 'own';
			}
		}

		$list = self::all();
		if ( ! isset( $list[ $target ] ) && count( $list ) >= self::MAX ) {
			return 'full';
		}

		$durations = self::durations();
		$seconds   = isset( $durations[ $duration ] ) ? $durations[ $duration ] : 0;

		$list[ $target ] = array(
			'note'  => mb_substr( sanitize_text_field( $note ), 0, 120 ),
			'time'  => time(),
			'until' => $seconds ? time() + $seconds : 0,
			'by'    => get_current_user_id(),
		);
		update_option( self::OPTION, $list, true );
		return 'ok';
	}

	/**
	 * Unblock.
	 *
	 * @param string $target Entry key.
	 * @return void
	 */
	public static function remove( $target ) {
		$list = self::all();
		if ( isset( $list[ $target ] ) ) {
			unset( $list[ $target ] );
			update_option( self::OPTION, $list, true );
		}
	}

	/**
	 * Delete entries that ran out.
	 *
	 * @return void
	 */
	public static function purge() {
		$list   = self::all();
		$active = self::active();
		if ( count( $list ) !== count( $active ) ) {
			update_option( self::OPTION, $active, true );
		}
	}

	/**
	 * Whether an address is blocked right now.
	 *
	 * @param string $ip Address.
	 * @return bool
	 */
	public static function is_blocked( $ip ) {
		$active = self::active();
		return $active && BHTK_Geo::in_list( $ip, implode( "\n", array_keys( $active ) ) );
	}

	/**
	 * Turn a blocked visitor away. Runs at the very start of every request.
	 *
	 * Never stops: administrators, command line runs, or a site that defines BHTK_NO_IP_BLOCK in wp-config.php.
	 *
	 * @return void
	 */
	public static function enforce() {
		if ( ( defined( 'BHTK_NO_IP_BLOCK' ) && BHTK_NO_IP_BLOCK ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$active = self::active();
		if ( ! $active ) {
			return;
		}

		$modules = BHTK_Plugin::instance()->get_modules();
		$source  = isset( $modules['visitor-stats'] ) ? $modules['visitor-stats']->get_settings()['ip_source'] : 'auto';
		$ips     = BHTK_Geo::candidate_ips( $source );
		$list    = implode( "\n", array_keys( $active ) );
		$ip      = '';
		foreach ( $ips as $candidate ) {
			if ( BHTK_Geo::in_list( $candidate, $list ) ) {
				$ip = $candidate;
				break;
			}
		}
		if ( '' === $ip ) {
			return;
		}
		if ( is_user_logged_in() && current_user_can( BHTK_Plugin::capability() ) ) {
			return;
		}
		/**
		 * Let a blocked address through.
		 *
		 * @param bool   $bypass True to allow the request.
		 * @param string $ip     Visitor address.
		 */
		if ( apply_filters( 'bhtk_ip_block_bypass', false, $ip ) ) {
			return;
		}

		nocache_headers();
		wp_die(
			esc_html__( 'Your access to this website has been blocked.', 'bani-honar-toolkit' ),
			esc_html__( 'Access blocked', 'bani-honar-toolkit' ),
			array( 'response' => 403 )
		);
	}
}
