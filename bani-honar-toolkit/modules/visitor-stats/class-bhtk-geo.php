<?php
/**
 * Visitor IP address and country.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds the visitor's IP address and country.
 *
 * The country is read, in this order, from: the headers that CDNs and servers add
 * (Cloudflare, ArvanCloud and others), an earlier visit from the same IP, and, if
 * the owner allows it, an online lookup service. Developers can replace the lookup
 * with their own (for example a local MaxMind database) through the
 * `bhtk_geo_lookup` filter.
 */
final class BHTK_Geo {

	/**
	 * Online lookup providers: id => array( label, url template with {ip}, parser ).
	 *
	 * @return array
	 */
	public static function providers() {
		return array(
			'ipwho'  => array(
				'label' => 'ipwho.is',
				'url'   => 'https://ipwho.is/{ip}',
				'parse' => 'ipwho',
			),
			'geojs'  => array(
				'label' => 'geojs.io',
				'url'   => 'https://get.geojs.io/v1/ip/country/{ip}.json',
				'parse' => 'geojs',
			),
		);
	}

	/**
	 * Valid, compact form of an IP address.
	 *
	 * @param string $ip Candidate.
	 * @return string Empty when it is not an IP address.
	 */
	public static function normalize( $ip ) {
		$ip = trim( (string) $ip );
		// Remove a port such as 1.2.3.4:5678 or [::1]:80.
		if ( preg_match( '/^\[([0-9a-f:.]+)\](?::\d+)?$/i', $ip, $m ) ) {
			$ip = $m[1];
		} elseif ( preg_match( '/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $m ) ) {
			$ip = $m[1];
		}
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		// IPv4 inside IPv6 (::ffff:1.2.3.4) is the same visitor as 1.2.3.4.
		if ( 0 === stripos( $ip, '::ffff:' ) && false !== filter_var( substr( $ip, 7 ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return substr( $ip, 7 );
		}
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the address was validated above.
		return false === $packed ? '' : (string) inet_ntop( $packed );
	}

	/**
	 * Whether an address is a public one (not private, loopback or reserved).
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function is_public( $ip ) {
		return false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * IP address of the visitor.
	 *
	 * @param string $source auto (look at proxy headers first) or remote_addr (trust only the connection).
	 * @param array  $server Server variables. Defaults to $_SERVER.
	 * @return string Empty when no valid address was found.
	 */
	public static function client_ip( $source = 'auto', $server = null ) {
		if ( null === $server ) {
			$server = $_SERVER; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is validated as an IP address below.
		}

		if ( 'remote_addr' !== $source ) {
			foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_REAL_IP' ) as $header ) {
				if ( ! empty( $server[ $header ] ) ) {
					$ip = self::normalize( (string) $server[ $header ] );
					if ( '' !== $ip ) {
						return $ip;
					}
				}
			}
			if ( ! empty( $server['HTTP_X_FORWARDED_FOR'] ) ) {
				$first_valid = '';
				foreach ( explode( ',', (string) $server['HTTP_X_FORWARDED_FOR'] ) as $part ) {
					$ip = self::normalize( $part );
					if ( '' === $ip ) {
						continue;
					}
					if ( self::is_public( $ip ) ) {
						return $ip;
					}
					if ( '' === $first_valid ) {
						$first_valid = $ip;
					}
				}
				if ( '' !== $first_valid && ( empty( $server['REMOTE_ADDR'] ) || ! self::is_public( self::normalize( (string) $server['REMOTE_ADDR'] ) ) ) ) {
					return $first_valid;
				}
			}
		}

		return isset( $server['REMOTE_ADDR'] ) ? self::normalize( (string) $server['REMOTE_ADDR'] ) : '';
	}

	/**
	 * Every address a request can be judged by: the one found in proxy headers and the direct connection.
	 *
	 * Used for blocking. Headers can be written by the visitor when the site is reached directly, so a block must also
	 * hold when the header says something else. Behind a CDN the direct connection is the CDN, which is never in the list.
	 *
	 * @param string     $source auto or remote_addr.
	 * @param array|null $server Server variables. Defaults to $_SERVER.
	 * @return string[] Valid addresses, without duplicates.
	 */
	public static function candidate_ips( $source = 'auto', $server = null ) {
		if ( null === $server ) {
			$server = $_SERVER; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is validated as an IP address.
		}
		$out    = array();
		$client = self::client_ip( $source, $server );
		if ( '' !== $client ) {
			$out[] = $client;
		}
		$direct = isset( $server['REMOTE_ADDR'] ) ? self::normalize( (string) $server['REMOTE_ADDR'] ) : '';
		if ( '' !== $direct && ! in_array( $direct, $out, true ) ) {
			$out[] = $direct;
		}
		return $out;
	}

	/**
	 * Hide the last part of an address: the last number of an IPv4 address, the last 80 bits of an IPv6 one.
	 *
	 * @param string $ip IP address.
	 * @return string
	 */
	public static function anonymize( $ip ) {
		$bin = @inet_pton( (string) $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an invalid address returns false and is handled.
		if ( false === $bin ) {
			return '';
		}
		if ( 4 === strlen( $bin ) ) {
			$bin[3] = "\0";
		} else {
			$bin = substr( $bin, 0, 6 ) . str_repeat( "\0", 10 );
		}
		return (string) inet_ntop( $bin );
	}

	/**
	 * Whether an address is on a list of addresses. IPv4 ranges such as 192.0.2.0/24 are allowed.
	 *
	 * @param string $ip   IP address.
	 * @param string $list One entry per line.
	 * @return bool
	 */
	public static function in_list( $ip, $list ) {
		$ip = self::normalize( $ip );
		if ( '' === $ip || '' === trim( (string) $list ) ) {
			return false;
		}
		foreach ( preg_split( '/[\r\n,]+/', (string) $list ) as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}
			if ( false !== strpos( $entry, '/' ) ) {
				list( $base, $bits ) = array_pad( explode( '/', $entry, 2 ), 2, '' );
				$bits = (int) $bits;
				if ( false !== filter_var( $base, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && $bits >= 0 && $bits <= 32 ) {
					$mask = 0 === $bits ? 0 : ( -1 << ( 32 - $bits ) ) & 0xFFFFFFFF;
					if ( ( ip2long( $ip ) & $mask ) === ( ip2long( $base ) & $mask ) ) {
						return true;
					}
				}
				continue;
			}
			if ( self::normalize( $entry ) === $ip ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Clean a list of addresses typed by the owner: valid addresses and IPv4 ranges only.
	 *
	 * @param string $text Text with one entry per line.
	 * @return string
	 */
	public static function clean_list( $text ) {
		$out = array();
		foreach ( preg_split( '/[\r\n,]+/', (string) $text ) as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}
			if ( preg_match( '#^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$#', $entry, $m ) && false !== filter_var( $m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && (int) $m[2] <= 32 ) {
				$out[] = $m[1] . '/' . (int) $m[2];
			} elseif ( '' !== self::normalize( $entry ) ) {
				$out[] = self::normalize( $entry );
			}
		}
		return implode( "\n", array_slice( array_values( array_unique( $out ) ), 0, 100 ) );
	}

	/**
	 * Country code that the server or a CDN already put in the request.
	 *
	 * @param array $server Server variables. Defaults to $_SERVER.
	 * @return string Two capital letters, or empty.
	 */
	public static function country_from_headers( $server = null ) {
		if ( null === $server ) {
			$server = $_SERVER; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the value is checked against the country list below.
		}
		$keys = array(
			'HTTP_CF_IPCOUNTRY',               // Cloudflare.
			'HTTP_AR_REAL_COUNTRY',            // ArvanCloud.
			'HTTP_CLOUDFRONT_VIEWER_COUNTRY',  // Amazon CloudFront.
			'HTTP_X_VERCEL_IP_COUNTRY',        // Vercel.
			'HTTP_X_APPENGINE_COUNTRY',        // Google App Engine.
			'HTTP_X_COUNTRY_CODE',
			'HTTP_X_GEO_COUNTRY',
			'HTTP_GEOIP_COUNTRY_CODE',
			'GEOIP_COUNTRY_CODE',              // Apache and Nginx GeoIP modules.
			'REDIRECT_GEOIP_COUNTRY_CODE',
		);
		foreach ( $keys as $key ) {
			if ( empty( $server[ $key ] ) || ! is_string( $server[ $key ] ) ) {
				continue;
			}
			$code = strtoupper( trim( $server[ $key ] ) );
			if ( BHTK_Countries::exists( $code ) ) {
				return $code;
			}
		}
		return '';
	}

	/**
	 * Ask an online service for the country of an IP address.
	 *
	 * Only public addresses are sent. When every service fails, lookups pause for ten minutes
	 * so a broken connection never slows the site down.
	 *
	 * @param string $ip       Public IP address.
	 * @param string $provider auto or a provider id.
	 * @return string Country code or empty.
	 */
	public static function lookup_online( $ip, $provider = 'auto' ) {
		$ip = self::normalize( $ip );
		if ( '' === $ip || ! self::is_public( $ip ) ) {
			return '';
		}

		/**
		 * Look up the country yourself, for example in a local database. Return a two-letter
		 * code to skip the online services, or null to continue.
		 *
		 * @param string|null $country Null.
		 * @param string      $ip      Public IP address.
		 */
		$custom = apply_filters( 'bhtk_geo_lookup', null, $ip );
		if ( is_string( $custom ) && BHTK_Countries::exists( $custom ) ) {
			return strtoupper( $custom );
		}

		if ( get_transient( 'bhtk_geo_down' ) ) {
			return '';
		}

		$all = self::providers();
		$ids = ( 'auto' !== $provider && isset( $all[ $provider ] ) ) ? array( $provider ) : array_keys( $all );

		foreach ( $ids as $id ) {
			$url      = str_replace( '{ip}', rawurlencode( $ip ), $all[ $id ]['url'] );
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 4,
					'redirection' => 1,
					'user-agent'  => 'BaniHonarToolkit/' . BHTK_VERSION,
					'headers'     => array( 'Accept' => 'application/json, text/plain' ),
				)
			);
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}
			$code = self::parse_response( $all[ $id ]['parse'], (string) wp_remote_retrieve_body( $response ) );
			if ( '' !== $code ) {
				return $code;
			}
		}

		set_transient( 'bhtk_geo_down', 1, 10 * MINUTE_IN_SECONDS );
		return '';
	}

	/**
	 * Read the country out of a lookup service's answer.
	 *
	 * @param string $parser Parser name.
	 * @param string $body   Response body.
	 * @return string Country code or empty.
	 */
	public static function parse_response( $parser, $body ) {
		$code = '';
		$json = json_decode( $body, true );
		if ( is_array( $json ) ) {
			if ( 'ipwho' === $parser && ! empty( $json['success'] ) && isset( $json['country_code'] ) ) {
				$code = strtoupper( (string) $json['country_code'] );
			} elseif ( 'geojs' === $parser ) {
				// The country endpoint answers with an object, the bulk one with a list of objects.
				$row  = isset( $json[0] ) && is_array( $json[0] ) ? $json[0] : $json;
				$code = isset( $row['country'] ) ? strtoupper( (string) $row['country'] ) : '';
			}
		}
		return BHTK_Countries::exists( $code ) ? $code : '';
	}
}
