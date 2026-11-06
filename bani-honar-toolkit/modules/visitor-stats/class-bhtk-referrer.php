<?php
/**
 * Works out where a visitor came from.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the referring address (and campaign tags) into a source: search engine,
 * social network or messenger, another website, or direct.
 */
final class BHTK_Referrer {

	/**
	 * Host patterns of known sources: pattern => array( name, type ).
	 *
	 * @return array
	 */
	private static function hosts() {
		return array(
			// Search engines.
			'/(^|\.)google\.[a-z.]{2,6}$/'      => array( 'Google', 'search' ),
			'/(^|\.)bing\.com$/'                => array( 'Bing', 'search' ),
			'/(^|\.)yahoo\.[a-z.]{2,6}$/'       => array( 'Yahoo', 'search' ),
			'/(^|\.)duckduckgo\.com$/'          => array( 'DuckDuckGo', 'search' ),
			'/(^|\.)yandex\.[a-z.]{2,6}$/'      => array( 'Yandex', 'search' ),
			'/(^|\.)baidu\.com$/'               => array( 'Baidu', 'search' ),
			'/(^|\.)ecosia\.org$/'              => array( 'Ecosia', 'search' ),
			'/(^|\.)startpage\.com$/'           => array( 'Startpage', 'search' ),
			'/(^|\.)parsijoo\.ir$/'             => array( 'Parsijoo', 'search' ),
			'/(^|\.)zarebin\.ir$/'              => array( 'Zarebin', 'search' ),
			'/(^|\.)ask\.com$/'                 => array( 'Ask', 'search' ),
			// Social networks and messengers.
			'/(^|\.)instagram\.com$/'           => array( 'Instagram', 'social' ),
			'/(^|\.)(facebook\.com|fb\.com|fb\.me)$/' => array( 'Facebook', 'social' ),
			'/^(t\.co)$|(^|\.)(twitter\.com|x\.com)$/' => array( 'X', 'social' ),
			'/(^|\.)(linkedin\.com|lnkd\.in)$/' => array( 'LinkedIn', 'social' ),
			'/(^|\.)(youtube\.com|youtu\.be)$/' => array( 'YouTube', 'social' ),
			'/(^|\.)pinterest\.[a-z.]{2,6}$/'   => array( 'Pinterest', 'social' ),
			'/(^|\.)reddit\.com$/'              => array( 'Reddit', 'social' ),
			'/(^|\.)tiktok\.com$/'              => array( 'TikTok', 'social' ),
			'/(^|\.)(t\.me|telegram\.me|telegram\.org|telegram\.dog)$/' => array( 'Telegram', 'social' ),
			'/(^|\.)(whatsapp\.com|wa\.me)$/'   => array( 'WhatsApp', 'social' ),
			'/(^|\.)eitaa\.com$/'               => array( 'Eitaa', 'social' ),
			'/(^|\.)rubika\.ir$/'               => array( 'Rubika', 'social' ),
			'/(^|\.)(bale\.ai|ble\.ir)$/'       => array( 'Bale', 'social' ),
			'/(^|\.)igap\.net$/'                => array( 'iGap', 'social' ),
			'/(^|\.)gap\.im$/'                  => array( 'Gap', 'social' ),
			'/(^|\.)aparat\.com$/'              => array( 'Aparat', 'social' ),
		);
	}

	/**
	 * Android app package => array( name, type ).
	 *
	 * @return array
	 */
	private static function packages() {
		return array(
			'org.telegram.messenger'                  => array( 'Telegram', 'social' ),
			'org.telegram.messenger.web'              => array( 'Telegram', 'social' ),
			'org.thunderdog.challegram'               => array( 'Telegram', 'social' ),
			'com.instagram.android'                   => array( 'Instagram', 'social' ),
			'com.whatsapp'                            => array( 'WhatsApp', 'social' ),
			'com.facebook.katana'                     => array( 'Facebook', 'social' ),
			'com.facebook.orca'                       => array( 'Facebook', 'social' ),
			'com.twitter.android'                     => array( 'X', 'social' ),
			'com.linkedin.android'                    => array( 'LinkedIn', 'social' ),
			'com.google.android.youtube'              => array( 'YouTube', 'social' ),
			'com.google.android.googlequicksearchbox' => array( 'Google', 'search' ),
			'ir.nasim'                                => array( 'Bale', 'social' ),
			'ir.eitaa.messenger'                      => array( 'Eitaa', 'social' ),
			'ir.resaneh1.iptv'                        => array( 'Rubika', 'social' ),
		);
	}

	/**
	 * Names used by campaign tags, lower case => display name.
	 *
	 * @return array
	 */
	private static function campaign_names() {
		return array(
			'google'     => 'Google',
			'instagram'  => 'Instagram',
			'ig'         => 'Instagram',
			'facebook'   => 'Facebook',
			'fb'         => 'Facebook',
			'telegram'   => 'Telegram',
			'tg'         => 'Telegram',
			'whatsapp'   => 'WhatsApp',
			'eitaa'      => 'Eitaa',
			'rubika'     => 'Rubika',
			'bale'       => 'Bale',
			'aparat'     => 'Aparat',
			'linkedin'   => 'LinkedIn',
			'twitter'    => 'X',
			'x'          => 'X',
			'youtube'    => 'YouTube',
			'bing'       => 'Bing',
			'tiktok'     => 'TikTok',
			'newsletter' => 'Newsletter',
			'email'      => 'Email',
			'sms'        => 'SMS',
		);
	}

	/**
	 * Address without the query string and the fragment, so no tokens or search words are kept.
	 *
	 * @param string $url Address.
	 * @return string Empty when it is not an http(s) or android-app address.
	 */
	public static function clean_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || ! preg_match( '#^(https?|android-app)://#i', $url ) ) {
			return '';
		}
		$url = preg_replace( '/[\x00-\x1F\x7F\s]+/', '', $url );
		$url = preg_replace( '/[?#].*$/', '', $url );
		return mb_substr( $url, 0, 500 );
	}

	/**
	 * Host of an address without "www.".
	 *
	 * @param string $url Address.
	 * @return string
	 */
	public static function host( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			return '';
		}
		return preg_replace( '/^www\./i', '', strtolower( $host ) );
	}

	/**
	 * Work out the source of a visit.
	 *
	 * @param string $referrer   Referring address, already cleaned.
	 * @param string $site_host  Host of this website.
	 * @param string $utm_source Campaign source tag.
	 * @param string $utm_medium Campaign medium tag.
	 * @param string $ua         User-Agent, used to recognise in-app browsers that hide the referrer.
	 * @return array type (direct, internal, search, social, referral, campaign), name, host.
	 */
	public static function classify( $referrer, $site_host, $utm_source = '', $utm_medium = '', $ua = '' ) {
		$site_host = preg_replace( '/^www\./i', '', strtolower( (string) $site_host ) );
		$host      = '';
		$name      = '';
		$type      = 'direct';

		$is_app = (bool) preg_match( '#^android-app://#i', (string) $referrer );
		if ( $is_app ) {
			$package = (string) wp_parse_url( $referrer, PHP_URL_HOST );
			$host    = strtolower( $package );
		} elseif ( '' !== $referrer ) {
			$host = self::host( $referrer );
		}

		// Campaign tags win over everything else: the owner tagged the link on purpose.
		if ( '' !== $utm_source || '' !== $utm_medium ) {
			$names = self::campaign_names();
			$key   = strtolower( $utm_source );
			return array(
				'type' => 'campaign',
				'name' => isset( $names[ $key ] ) ? $names[ $key ] : ( '' !== $utm_source ? $utm_source : $utm_medium ),
				'host' => $host,
			);
		}

		if ( '' === $host ) {
			// In-app browsers of Instagram and Facebook send no referrer.
			if ( preg_match( '/Instagram/', (string) $ua ) ) {
				return array(
					'type' => 'social',
					'name' => 'Instagram',
					'host' => '',
				);
			}
			if ( preg_match( '/FB_IAB|FBAN|FBAV/', (string) $ua ) ) {
				return array(
					'type' => 'social',
					'name' => 'Facebook',
					'host' => '',
				);
			}
			return array(
				'type' => 'direct',
				'name' => '',
				'host' => '',
			);
		}

		if ( $is_app ) {
			$packages = self::packages();
			if ( isset( $packages[ $host ] ) ) {
				return array(
					'type' => $packages[ $host ][1],
					'name' => $packages[ $host ][0],
					'host' => $host,
				);
			}
			return array(
				'type' => 'referral',
				'name' => $host,
				'host' => $host,
			);
		}

		if ( '' !== $site_host && ( $host === $site_host || substr( $host, -strlen( '.' . $site_host ) ) === '.' . $site_host ) ) {
			return array(
				'type' => 'internal',
				'name' => '',
				'host' => $host,
			);
		}

		foreach ( self::hosts() as $pattern => $info ) {
			if ( preg_match( $pattern, $host ) ) {
				return array(
					'type' => $info[1],
					'name' => $info[0],
					'host' => $host,
				);
			}
		}

		return array(
			'type' => 'referral',
			'name' => $host,
			'host' => $host,
		);
	}

	/**
	 * Name of a source for display.
	 *
	 * @param string $type Source type.
	 * @param string $name Source name.
	 * @return string
	 */
	public static function label( $type, $name ) {
		if ( 'direct' === $type ) {
			return __( 'Direct (no referrer)', 'bani-honar-toolkit' );
		}
		if ( 'internal' === $type ) {
			return __( 'Inside this website', 'bani-honar-toolkit' );
		}
		$names = array(
			'Google'     => __( 'Google', 'bani-honar-toolkit' ),
			'Bing'       => __( 'Bing', 'bani-honar-toolkit' ),
			'Yahoo'      => __( 'Yahoo', 'bani-honar-toolkit' ),
			'Instagram'  => __( 'Instagram', 'bani-honar-toolkit' ),
			'Facebook'   => __( 'Facebook', 'bani-honar-toolkit' ),
			'X'          => __( 'X (Twitter)', 'bani-honar-toolkit' ),
			'LinkedIn'   => __( 'LinkedIn', 'bani-honar-toolkit' ),
			'YouTube'    => __( 'YouTube', 'bani-honar-toolkit' ),
			'Pinterest'  => __( 'Pinterest', 'bani-honar-toolkit' ),
			'TikTok'     => __( 'TikTok', 'bani-honar-toolkit' ),
			'Telegram'   => __( 'Telegram', 'bani-honar-toolkit' ),
			'WhatsApp'   => __( 'WhatsApp', 'bani-honar-toolkit' ),
			'Eitaa'      => __( 'Eitaa', 'bani-honar-toolkit' ),
			'Rubika'     => __( 'Rubika', 'bani-honar-toolkit' ),
			'Bale'       => __( 'Bale', 'bani-honar-toolkit' ),
			'Aparat'     => __( 'Aparat', 'bani-honar-toolkit' ),
			'Newsletter' => __( 'Newsletter', 'bani-honar-toolkit' ),
			'Email'      => __( 'Email', 'bani-honar-toolkit' ),
			'SMS'        => __( 'SMS', 'bani-honar-toolkit' ),
		);
		return isset( $names[ $name ] ) ? $names[ $name ] : (string) $name;
	}

	/**
	 * Name of a source type for display.
	 *
	 * @param string $type Source type.
	 * @return string
	 */
	public static function type_label( $type ) {
		$types = array(
			'direct'   => __( 'Direct', 'bani-honar-toolkit' ),
			'internal' => __( 'Internal', 'bani-honar-toolkit' ),
			'search'   => __( 'Search engine', 'bani-honar-toolkit' ),
			'social'   => __( 'Social / messenger', 'bani-honar-toolkit' ),
			'referral' => __( 'Other website', 'bani-honar-toolkit' ),
			'campaign' => __( 'Campaign', 'bani-honar-toolkit' ),
		);
		return isset( $types[ $type ] ) ? $types[ $type ] : (string) $type;
	}
}
