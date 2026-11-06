<?php
/**
 * Reads the browser, system and device from a User-Agent string.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A small, dependency-free User-Agent reader.
 *
 * Browsers share less and less in the User-Agent. Chrome on Android hides the phone
 * model there, so the page script also sends the "client hints" the browser offers
 * (model, real system version). Safari on iPhone never tells the exact model.
 */
final class BHTK_UA {

	/**
	 * Whether the User-Agent belongs to a crawler, a monitor or a script.
	 *
	 * @param string $ua User-Agent.
	 * @return bool
	 */
	public static function is_bot( $ua ) {
		$ua = trim( (string) $ua );
		if ( '' === $ua ) {
			return true;
		}
		return (bool) preg_match(
			'/bot\b|bot[\/;\s)]|crawl|spider|slurp|mediapartners|facebookexternalhit|embedly|outbrain|vkshare|w3c_validator|whatsapp\/|preview|lighthouse|pagespeed|gtmetrix|headless|phantomjs|prerender|python-requests|python-urllib|aiohttp|curl\/|wget\/|libwww|go-http-client|java\/|httpclient|scrapy|axios\/|node-fetch|postmanruntime|monitor|uptime|pingdom|datadog|newrelic|ahrefs|semrush|mj12|dotbot|petalbot|bytespider/i',
			$ua
		);
	}

	/**
	 * Read a User-Agent.
	 *
	 * @param string $ua    User-Agent.
	 * @param array  $hints Optional client hints from the browser: model, platform, platform_version, mobile (bool), touch (int), width, height, dpr.
	 * @return array type, brand, model, code, os, os_version, browser, browser_version.
	 */
	public static function parse( $ua, $hints = array() ) {
		$ua    = trim( (string) $ua );
		$hints = is_array( $hints ) ? $hints : array();

		$r = array(
			'type'            => 'desktop',
			'brand'           => '',
			'model'           => '',
			'code'            => '',
			'os'              => '',
			'os_version'      => '',
			'browser'         => '',
			'browser_version' => '',
		);

		if ( self::is_bot( $ua ) ) {
			$r['type']    = 'bot';
			$r['browser'] = 'Bot';
			return $r;
		}

		$touch = isset( $hints['touch'] ) ? (int) $hints['touch'] : 0;

		// ---- System ----
		$is_ipad = (bool) preg_match( '/iPad/', $ua ) || ( preg_match( '/Macintosh/', $ua ) && $touch > 1 );

		if ( preg_match( '/Windows Phone/i', $ua ) ) {
			$r['os'] = 'Windows Phone';
		} elseif ( preg_match( '/Windows NT ([\d.]+)/', $ua, $m ) ) {
			$r['os']         = 'Windows';
			$r['os_version'] = self::windows_version( $m[1], isset( $hints['platform_version'] ) ? $hints['platform_version'] : '' );
		} elseif ( $is_ipad ) {
			$r['os'] = 'iPadOS';
			if ( preg_match( '/CPU OS (\d+)[_.](\d+)/', $ua, $m ) ) {
				$r['os_version'] = $m[1] . '.' . $m[2];
			}
		} elseif ( preg_match( '/iPhone|iPod/', $ua ) ) {
			$r['os'] = 'iOS';
			if ( preg_match( '/OS (\d+)[_.](\d+)/', $ua, $m ) ) {
				$r['os_version'] = $m[1] . '.' . $m[2];
			}
		} elseif ( preg_match( '/Android/i', $ua ) ) {
			$r['os'] = 'Android';
			if ( preg_match( '/Android[ \/]?([\d.]+)/i', $ua, $m ) ) {
				$r['os_version'] = self::major_minor( $m[1] );
			}
			// Chrome freezes the Android version in the User-Agent; the client hint has the real one.
			if ( isset( $hints['platform'], $hints['platform_version'] ) && 'Android' === $hints['platform'] && preg_match( '/^\d+(\.\d+)*$/', (string) $hints['platform_version'] ) ) {
				$r['os_version'] = self::major_minor( $hints['platform_version'] );
			}
		} elseif ( preg_match( '/CrOS/', $ua ) ) {
			$r['os'] = 'ChromeOS';
		} elseif ( preg_match( '/Mac OS X|Macintosh/', $ua ) ) {
			$r['os'] = 'macOS';
			// The User-Agent always says 10.15.7; only the client hint is real.
			if ( isset( $hints['platform'], $hints['platform_version'] ) && 'macOS' === $hints['platform'] && preg_match( '/^(\d+)(\.\d+)*$/', (string) $hints['platform_version'], $m ) ) {
				$r['os_version'] = $m[1];
			}
		} elseif ( preg_match( '/Tizen/i', $ua ) ) {
			$r['os'] = 'Tizen';
		} elseif ( preg_match( '/Web0S|webOS/i', $ua ) ) {
			$r['os'] = 'webOS';
		} elseif ( preg_match( '/PlayStation/i', $ua ) ) {
			$r['os'] = 'PlayStation';
		} elseif ( preg_match( '/Xbox/i', $ua ) ) {
			$r['os'] = 'Xbox';
		} elseif ( preg_match( '/Ubuntu|Fedora|Debian|SUSE|Arch Linux|Linux Mint/i', $ua, $m ) ) {
			$r['os'] = ucfirst( strtolower( $m[0] ) );
			if ( 'Suse' === $r['os'] ) {
				$r['os'] = 'openSUSE';
			}
		} elseif ( preg_match( '/Linux|X11|CrKey/', $ua ) ) {
			$r['os'] = 'Linux';
		}

		// ---- Device type ----
		if ( preg_match( '/SMART-TV|SmartTV|Tizen|Web0S|webOS|HbbTV|AppleTV|GoogleTV|Android TV|BRAVIA|NetCast|CrKey|Roku/i', $ua ) ) {
			$r['type'] = 'tv';
		} elseif ( preg_match( '/PlayStation|Xbox|Nintendo/i', $ua ) ) {
			$r['type'] = 'console';
		} elseif ( $is_ipad || preg_match( '/Tablet|Kindle|Silk\/|PlayBook|SM-[TX]\d{3}/i', $ua ) || ( 'Android' === $r['os'] && ! preg_match( '/Mobile/i', $ua ) && empty( $hints['mobile'] ) ) ) {
			$r['type'] = 'tablet';
		} elseif ( ! empty( $hints['mobile'] ) || preg_match( '/Mobi|iPhone|iPod|Windows Phone|BlackBerry|Opera Mini|IEMobile/i', $ua ) ) {
			$r['type'] = 'mobile';
		}

		// ---- Device brand and model ----
		self::read_device( $ua, $hints, $r, $is_ipad );

		// ---- Browser ----
		self::read_browser( $ua, $r );

		return $r;
	}

	/**
	 * Fill the brand and model.
	 *
	 * @param string $ua      User-Agent.
	 * @param array  $hints   Client hints.
	 * @param array  $r       Result, changed in place.
	 * @param bool   $is_ipad Whether the device is an iPad.
	 * @return void
	 */
	private static function read_device( $ua, $hints, &$r, $is_ipad ) {
		if ( $is_ipad ) {
			$r['brand'] = 'Apple';
			$r['model'] = 'iPad';
			return;
		}
		if ( 'iOS' === $r['os'] ) {
			$r['brand'] = 'Apple';
			$r['model'] = preg_match( '/iPod/', $ua ) ? 'iPod touch' : 'iPhone';
			if ( 'iPhone' === $r['model'] ) {
				$guess = self::iphone_guess( isset( $hints['width'] ) ? (int) $hints['width'] : 0, isset( $hints['height'] ) ? (int) $hints['height'] : 0, isset( $hints['dpr'] ) ? (float) $hints['dpr'] : 0 );
				if ( '' !== $guess ) {
					$r['model'] = $guess;
				}
			}
			return;
		}
		if ( 'macOS' === $r['os'] ) {
			$r['brand'] = 'Apple';
			$r['model'] = 'Mac';
			return;
		}
		if ( 'ChromeOS' === $r['os'] ) {
			$r['model'] = 'Chromebook';
			return;
		}
		if ( 'Android' !== $r['os'] ) {
			// Windows and every Linux flavour on a computer.
			if ( 'desktop' === $r['type'] && '' !== $r['os'] ) {
				$r['model'] = 'PC';
			}
			return;
		}

		// Model from the client hint first, then from the User-Agent.
		$model = '';
		if ( ! empty( $hints['model'] ) && is_string( $hints['model'] ) ) {
			$model = trim( $hints['model'] );
		}
		if ( '' === $model && preg_match( '/\(([^)]*Android[^)]*)\)/i', $ua, $m ) ) {
			foreach ( array_map( 'trim', explode( ';', $m[1] ) ) as $part ) {
				if ( '' === $part || preg_match( '/^(Linux|U|wv|Mobile|Tablet|Android[\s\d.]*|[a-z]{2}([-_][a-z]{2})?|K)$/i', $part ) ) {
					continue;
				}
				$model = trim( preg_replace( '/\s+Build\/.*$/i', '', $part ) );
				break;
			}
		}
		$model = mb_substr( preg_replace( '/[^\p{L}\p{N} ._+\-()\/]/u', '', $model ), 0, 60 );
		if ( '' === $model || 'K' === $model ) {
			return;
		}

		$known = self::brand_for_model( $model );
		$r['brand'] = $known['brand'];
		$r['model'] = $known['name'];
		if ( $known['name'] !== $model ) {
			$r['code'] = $model;
		}
	}

	/**
	 * Brand and a friendly name for an Android model string.
	 *
	 * @param string $model Raw model such as SM-S918B.
	 * @return array brand and name. The name is the raw model when no friendly name is known.
	 */
	public static function brand_for_model( $model ) {
		$out = array(
			'brand' => '',
			'name'  => $model,
		);

		// Samsung.
		if ( preg_match( '/^(SM-|GT-|SC-|SCH-|SGH-|SHV-|SPH-)/i', $model ) || 0 === stripos( $model, 'Samsung' ) ) {
			$out['brand'] = 'Samsung';
			if ( preg_match( '/^SM-([A-Z]\d{3})/i', $model, $m ) ) {
				$names = self::samsung_names();
				$key   = strtoupper( $m[1] );
				if ( isset( $names[ $key ] ) ) {
					$out['name'] = 'Galaxy ' . $names[ $key ];
				}
			}
			return $out;
		}

		$brands = array(
			'Google'   => '/^(Pixel|Nexus)\b/i',
			'Xiaomi'   => '/^(Redmi|POCO|Xiaomi|Mi\b|MI\b|MIX\b|M\d{4}[A-Z]\d[A-Z0-9]{1,3}$|2\d{3}[A-Z0-9]{5,8}$)/',
			'Honor'    => '/^HONOR/i',
			'Huawei'   => '/^(HUAWEI|[A-Z]{3,4}-(L\d{2}|AL\d{2}|TL\d{2}|AN\d{2}|NX\d|LX\d|N\d{2})[A-Z]?$)/',
			'Realme'   => '/^(realme|RMX\d{4})/i',
			'OnePlus'  => '/^(OnePlus|(LE|IN|KB|GM|HD|NE|BE|DN)\d{4}$)/',
			'Oppo'     => '/^(OPPO|CPH\d{4}|PD\d{4}|PCA|PEM|PGJ)/i',
			'Vivo'     => '/^(vivo|V\d{4}[A-Z]{0,2}$)/i',
			'Motorola' => '/^(moto|motorola|XT\d{4})/i',
			'Nokia'    => '/^(Nokia|TA-\d{4})/i',
			'Sony'     => '/^(Xperia|XQ-|SO-\d|SOV\d|SOG\d)/i',
			'LG'       => '/^(LG|LM-|LGM-|VS\d)/i',
			'Lenovo'   => '/^(Lenovo|TB-|TB\d)/i',
			'Asus'     => '/^(ASUS|ZenFone|ZS\d{3})/i',
			'Tecno'    => '/^TECNO/i',
			'Infinix'  => '/^Infinix/i',
			'itel'     => '/^itel/i',
			'Nothing'  => '/^Nothing/i',
			'ZTE'      => '/^ZTE/i',
			'HTC'      => '/^HTC/i',
			'Alcatel'  => '/^Alcatel/i',
		);
		foreach ( $brands as $brand => $pattern ) {
			if ( preg_match( $pattern, $model ) ) {
				$out['brand'] = $brand;
				// Names that already contain the brand: "Redmi Note 11", "Pixel 7 Pro", "POCO X3".
				if ( preg_match( '/^(Redmi|POCO|Pixel|Xiaomi|Mi |MI |realme|OnePlus|HUAWEI|HONOR|vivo|OPPO|Nokia|Lenovo|TECNO|Infinix|itel|ASUS|ZTE|HTC)\s*/i', $model ) ) {
					$trimmed = trim( preg_replace( '/^(Xiaomi|HUAWEI|HONOR|OnePlus|OPPO|vivo|ASUS|ZTE|HTC|TECNO|Infinix|itel|Lenovo|Nokia)\s+/i', '', $model ) );
					if ( '' !== $trimmed ) {
						$out['name'] = $trimmed;
					}
				}
				return $out;
			}
		}
		return $out;
	}

	/**
	 * Friendly names for the Samsung model families (the four characters after SM-).
	 *
	 * @return array
	 */
	private static function samsung_names() {
		return array(
			// S series.
			'G950' => 'S8', 'G955' => 'S8+', 'G960' => 'S9', 'G965' => 'S9+',
			'G970' => 'S10e', 'G973' => 'S10', 'G975' => 'S10+', 'G977' => 'S10 5G',
			'G980' => 'S20', 'G981' => 'S20 5G', 'G985' => 'S20+', 'G986' => 'S20+ 5G', 'G988' => 'S20 Ultra',
			'G780' => 'S20 FE', 'G781' => 'S20 FE 5G',
			'G991' => 'S21 5G', 'G996' => 'S21+ 5G', 'G998' => 'S21 Ultra 5G', 'G990' => 'S21 FE 5G',
			'S901' => 'S22', 'S906' => 'S22+', 'S908' => 'S22 Ultra',
			'S911' => 'S23', 'S916' => 'S23+', 'S918' => 'S23 Ultra', 'S711' => 'S23 FE',
			'S921' => 'S24', 'S926' => 'S24+', 'S928' => 'S24 Ultra', 'S721' => 'S24 FE',
			'S931' => 'S25', 'S936' => 'S25+', 'S938' => 'S25 Ultra',
			// Note.
			'N950' => 'Note8', 'N960' => 'Note9', 'N970' => 'Note10', 'N975' => 'Note10+', 'N976' => 'Note10+ 5G',
			'N980' => 'Note20', 'N981' => 'Note20 5G', 'N985' => 'Note20 Ultra', 'N986' => 'Note20 Ultra 5G',
			// Z Flip and Fold.
			'F700' => 'Z Flip', 'F707' => 'Z Flip 5G', 'F711' => 'Z Flip3', 'F721' => 'Z Flip4', 'F731' => 'Z Flip5', 'F741' => 'Z Flip6',
			'F900' => 'Fold', 'F907' => 'Fold 5G', 'F916' => 'Z Fold2', 'F926' => 'Z Fold3', 'F936' => 'Z Fold4', 'F946' => 'Z Fold5', 'F956' => 'Z Fold6',
			// A series.
			'A015' => 'A01', 'A022' => 'A02', 'A025' => 'A02s', 'A035' => 'A03', 'A037' => 'A03s',
			'A045' => 'A04', 'A047' => 'A04s', 'A055' => 'A05', 'A057' => 'A05s', 'A065' => 'A06',
			'A105' => 'A10', 'A107' => 'A10s', 'A115' => 'A11', 'A125' => 'A12', 'A127' => 'A12', 'A135' => 'A13', 'A136' => 'A13 5G',
			'A145' => 'A14', 'A146' => 'A14 5G', 'A155' => 'A15', 'A156' => 'A15 5G',
			'A205' => 'A20', 'A207' => 'A20s', 'A215' => 'A21', 'A217' => 'A21s', 'A225' => 'A22', 'A226' => 'A22 5G',
			'A235' => 'A23', 'A236' => 'A23 5G', 'A245' => 'A24', 'A256' => 'A25 5G',
			'A305' => 'A30', 'A307' => 'A30s', 'A315' => 'A31', 'A325' => 'A32', 'A326' => 'A32 5G',
			'A336' => 'A33 5G', 'A346' => 'A34 5G', 'A356' => 'A35 5G',
			'A505' => 'A50', 'A507' => 'A50s', 'A515' => 'A51', 'A516' => 'A51 5G',
			'A525' => 'A52', 'A526' => 'A52 5G', 'A528' => 'A52s 5G', 'A536' => 'A53 5G', 'A546' => 'A54 5G', 'A556' => 'A55 5G',
			'A705' => 'A70', 'A715' => 'A71', 'A716' => 'A71 5G', 'A725' => 'A72', 'A736' => 'A73 5G',
			// M series.
			'M115' => 'M11', 'M127' => 'M12', 'M135' => 'M13', 'M215' => 'M21', 'M225' => 'M22', 'M236' => 'M23 5G',
			'M315' => 'M31', 'M317' => 'M31s', 'M325' => 'M32', 'M336' => 'M33 5G', 'M346' => 'M34 5G',
			'M515' => 'M51', 'M526' => 'M52 5G', 'M536' => 'M53 5G',
		);
	}

	/**
	 * Which iPhones share a screen size. Browsers never reveal the exact iPhone model.
	 *
	 * @param int   $w   Screen width in CSS pixels.
	 * @param int   $h   Screen height in CSS pixels.
	 * @param float $dpr Pixel ratio.
	 * @return string Empty when the size is not recognised.
	 */
	public static function iphone_guess( $w, $h, $dpr ) {
		if ( $w <= 0 || $h <= 0 ) {
			return '';
		}
		$short = min( $w, $h );
		$long  = max( $w, $h );
		$key   = $short . 'x' . $long . '@' . (int) round( $dpr );
		$map   = array(
			'320x568@2'  => 'iPhone 5 / 5s / SE',
			'375x667@2'  => 'iPhone 6 / 7 / 8 / SE 2-3',
			'414x736@3'  => 'iPhone 6 / 7 / 8 Plus',
			'375x812@3'  => 'iPhone X / XS / 11 Pro',
			'414x896@2'  => 'iPhone XR / 11',
			'414x896@3'  => 'iPhone XS Max / 11 Pro Max',
			'360x780@3'  => 'iPhone 12 / 13 mini',
			'390x844@3'  => 'iPhone 12 / 13 / 14',
			'428x926@3'  => 'iPhone 12-14 Pro Max / 14 Plus',
			'393x852@3'  => 'iPhone 14 Pro / 15 / 16',
			'430x932@3'  => 'iPhone 15 Plus / Pro Max / 16 Plus',
			'402x874@3'  => 'iPhone 16 Pro',
			'440x956@3'  => 'iPhone 16 Pro Max',
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	/**
	 * Windows version from the NT number and the optional client hint.
	 *
	 * @param string $nt               NT version such as 10.0.
	 * @param string $platform_version Client hint platform version.
	 * @return string
	 */
	private static function windows_version( $nt, $platform_version ) {
		if ( '10.0' === $nt ) {
			// Windows 11 still says NT 10.0; the client hint tells them apart (13 and up is Windows 11).
			if ( preg_match( '/^(\d+)/', (string) $platform_version, $m ) ) {
				return ( (int) $m[1] >= 13 ) ? '11' : '10';
			}
			return '10/11';
		}
		$map = array(
			'6.3' => '8.1',
			'6.2' => '8',
			'6.1' => '7',
			'6.0' => 'Vista',
			'5.2' => 'XP',
			'5.1' => 'XP',
		);
		return isset( $map[ $nt ] ) ? $map[ $nt ] : $nt;
	}

	/**
	 * Keep the first two numbers of a version.
	 *
	 * @param string $version Version such as 13.0.1.
	 * @return string
	 */
	private static function major_minor( $version ) {
		$parts = explode( '.', (string) $version );
		$out   = $parts[0];
		if ( isset( $parts[1] ) && '0' !== $parts[1] && '' !== $parts[1] ) {
			$out .= '.' . $parts[1];
		}
		return mb_substr( $out, 0, 20 );
	}

	/**
	 * Fill the browser name and version.
	 *
	 * @param string $ua User-Agent.
	 * @param array  $r  Result, changed in place.
	 * @return void
	 */
	private static function read_browser( $ua, &$r ) {
		$rules = array(
			array( '/Instagram[ \/]([\d.]+)/', 'Instagram' ),
			array( '/FB_IAB|FBAN|FBAV\/([\d.]+)/', 'Facebook' ),
			array( '/Edg(?:e|A|iOS)?\/([\d.]+)/', 'Edge' ),
			array( '/OPR\/([\d.]+)|OPiOS\/([\d.]+)|OPT\/([\d.]+)|Opera\/([\d.]+)/', 'Opera' ),
			array( '/SamsungBrowser\/([\d.]+)/', 'Samsung Internet' ),
			array( '/UCBrowser\/([\d.]+)/', 'UC Browser' ),
			array( '/YaBrowser\/([\d.]+)/', 'Yandex Browser' ),
			array( '/Vivaldi\/([\d.]+)/', 'Vivaldi' ),
			array( '/MiuiBrowser\/([\d.]+)/', 'Mi Browser' ),
			array( '/HuaweiBrowser\/([\d.]+)/', 'Huawei Browser' ),
			array( '/DuckDuckGo\/([\d.]+)/', 'DuckDuckGo' ),
			array( '/FxiOS\/([\d.]+)|Firefox\/([\d.]+)/', 'Firefox' ),
			array( '/CriOS\/([\d.]+)/', 'Chrome' ),
			array( '/MSIE ([\d.]+)|Trident\/.*rv:([\d.]+)/', 'Internet Explorer' ),
		);
		foreach ( $rules as $rule ) {
			if ( preg_match( $rule[0], $ua, $m ) ) {
				$r['browser'] = $rule[1];
				$ver          = '';
				foreach ( array_slice( $m, 1 ) as $candidate ) {
					if ( '' !== $candidate ) {
						$ver = $candidate;
						break;
					}
				}
				$r['browser_version'] = self::first_number( $ver );
				return;
			}
		}

		if ( preg_match( '/Android.*; wv\)/', $ua ) && preg_match( '/Chrome\/([\d.]+)/', $ua, $m ) ) {
			$r['browser']         = 'WebView';
			$r['browser_version'] = self::first_number( $m[1] );
			return;
		}
		if ( preg_match( '/Chromium\/([\d.]+)/', $ua, $m ) ) {
			$r['browser']         = 'Chromium';
			$r['browser_version'] = self::first_number( $m[1] );
			return;
		}
		if ( preg_match( '/Chrome\/([\d.]+)/', $ua, $m ) ) {
			$r['browser']         = 'Chrome';
			$r['browser_version'] = self::first_number( $m[1] );
			return;
		}
		if ( preg_match( '/Version\/([\d.]+).*Safari\//', $ua, $m ) || preg_match( '/Safari\/([\d.]+)/', $ua, $m ) ) {
			$r['browser']         = 'Safari';
			$r['browser_version'] = self::first_number( $m[1] );
			return;
		}
		// iPhone apps that show web pages inside themselves send no browser name at all.
		if ( 'iOS' === $r['os'] || 'iPadOS' === $r['os'] ) {
			$r['browser'] = 'WebView';
		}
	}

	/**
	 * First number of a version string.
	 *
	 * @param string $version Version.
	 * @return string
	 */
	private static function first_number( $version ) {
		return preg_match( '/^\d+/', (string) $version, $m ) ? $m[0] : '';
	}
}
