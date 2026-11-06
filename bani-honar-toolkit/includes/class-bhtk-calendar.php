<?php
/**
 * Date display: Gregorian or Persian (Jalali).
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats dates for the toolkit screens. The Jalali conversion is done here, so
 * it works the same whether or not a Persian date plugin is installed.
 */
final class BHTK_Calendar {

	/**
	 * Convert a Gregorian date to a Jalali one.
	 *
	 * @param int $gy Gregorian year.
	 * @param int $gm Gregorian month, 1-12.
	 * @param int $gd Gregorian day.
	 * @return int[] Jalali year, month and day.
	 */
	public static function to_jalali( $gy, $gm, $gd ) {
		$gy = (int) $gy;
		$gm = max( 1, min( 12, (int) $gm ) );
		$gd = (int) $gd;

		$offsets = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2     = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days    = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $offsets[ $gm - 1 ];

		$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days %= 12053;
		$jy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy   += intdiv( $days - 1, 365 );
			$days  = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array( $jy, $jm, $jd );
	}

	/**
	 * Persian month name.
	 *
	 * @param int $month Month number, 1-12.
	 * @return string
	 */
	public static function jalali_month_name( $month ) {
		$names = array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
		$month = (int) $month;
		return isset( $names[ $month - 1 ] ) ? $names[ $month - 1 ] : '';
	}

	/**
	 * Whether dates should be shown as Jalali for a setting value.
	 *
	 * @param string $mode auto, jalali or gregorian. Auto means Jalali while the toolkit is in Persian.
	 * @return bool
	 */
	public static function use_jalali( $mode ) {
		if ( 'jalali' === $mode ) {
			return true;
		}
		if ( 'gregorian' === $mode ) {
			return false;
		}
		return 'fa' === BHTK_I18n::lang();
	}

	/**
	 * Split a Y-m-d date into numbers in the chosen calendar.
	 *
	 * @param string $ymd    Date such as 2026-10-26.
	 * @param bool   $jalali Whether to convert to Jalali.
	 * @return int[]|null Year, month, day or null when the date is not valid.
	 */
	public static function parts( $ymd, $jalali ) {
		if ( ! is_string( $ymd ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m ) ) {
			return null;
		}
		$y = (int) $m[1];
		$mo = (int) $m[2];
		$d = (int) $m[3];
		if ( ! checkdate( $mo, $d, $y ) ) {
			return null;
		}
		return $jalali ? self::to_jalali( $y, $mo, $d ) : array( $y, $mo, $d );
	}

	/**
	 * A day as text.
	 *
	 * @param string $ymd    Date such as 2026-10-26 (already in the site time zone).
	 * @param string $mode   Calendar setting.
	 * @param string $style  full (1405/08/04), short (08/04) or long (4 Aban 1405).
	 * @return string
	 */
	public static function day( $ymd, $mode = 'auto', $style = 'full' ) {
		$jalali = self::use_jalali( $mode );
		$p      = self::parts( $ymd, $jalali );
		if ( null === $p ) {
			return (string) $ymd;
		}
		if ( 'short' === $style ) {
			return sprintf( '%02d/%02d', $p[1], $p[2] );
		}
		if ( 'long' === $style && $jalali ) {
			return $p[2] . ' ' . self::jalali_month_name( $p[1] ) . ' ' . $p[0];
		}
		return sprintf( '%04d/%02d/%02d', $p[0], $p[1], $p[2] );
	}

	/**
	 * Name of the month a day belongs to, with the year, for monthly chart labels.
	 *
	 * @param string $ymd  Date.
	 * @param string $mode Calendar setting.
	 * @return string
	 */
	public static function month_label( $ymd, $mode = 'auto' ) {
		$jalali = self::use_jalali( $mode );
		$p      = self::parts( $ymd, $jalali );
		if ( null === $p ) {
			return (string) $ymd;
		}
		if ( $jalali ) {
			return self::jalali_month_name( $p[1] ) . ' ' . $p[0];
		}
		return sprintf( '%04d/%02d', $p[0], $p[1] );
	}

	/**
	 * Key that is the same for every day of one month in the chosen calendar.
	 *
	 * @param string $ymd  Date.
	 * @param string $mode Calendar setting.
	 * @return string
	 */
	public static function month_key( $ymd, $mode = 'auto' ) {
		$p = self::parts( $ymd, self::use_jalali( $mode ) );
		return null === $p ? (string) $ymd : sprintf( '%04d-%02d', $p[0], $p[1] );
	}

	/**
	 * Date and time from a UTC database value, shown in the site time zone.
	 *
	 * @param string $utc   UTC date and time such as 2026-10-26 10:08:00.
	 * @param string $mode  Calendar setting.
	 * @param bool   $secs  Whether to show seconds.
	 * @return string
	 */
	public static function datetime( $utc, $mode = 'auto', $secs = false ) {
		$ts = is_string( $utc ) ? strtotime( $utc . ' UTC' ) : false;
		if ( false === $ts ) {
			return '';
		}
		$dt = ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( wp_timezone() );
		return self::day( $dt->format( 'Y-m-d' ), $mode ) . ' ' . $dt->format( $secs ? 'H:i:s' : 'H:i' );
	}
}
