<?php
/**
 * Country names (English and Persian) and flags.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ISO 3166-1 alpha-2 country codes with their names.
 */
final class BHTK_Countries {

	/**
	 * Code => array( English name, Persian name ).
	 *
	 * @return array
	 */
	private static function data() {
		static $data = null;
		if ( null !== $data ) {
			return $data;
		}
		$data = array(
			'AD' => array( 'Andorra', 'آندورا' ),
			'AE' => array( 'United Arab Emirates', 'امارات متحده عربی' ),
			'AF' => array( 'Afghanistan', 'افغانستان' ),
			'AG' => array( 'Antigua and Barbuda', 'آنتیگوا و باربودا' ),
			'AI' => array( 'Anguilla', 'آنگویلا' ),
			'AL' => array( 'Albania', 'آلبانی' ),
			'AM' => array( 'Armenia', 'ارمنستان' ),
			'AO' => array( 'Angola', 'آنگولا' ),
			'AQ' => array( 'Antarctica', 'جنوبگان' ),
			'AR' => array( 'Argentina', 'آرژانتین' ),
			'AS' => array( 'American Samoa', 'ساموآی آمریکا' ),
			'AT' => array( 'Austria', 'اتریش' ),
			'AU' => array( 'Australia', 'استرالیا' ),
			'AW' => array( 'Aruba', 'آروبا' ),
			'AX' => array( 'Åland Islands', 'جزایر اولند' ),
			'AZ' => array( 'Azerbaijan', 'جمهوری آذربایجان' ),
			'BA' => array( 'Bosnia and Herzegovina', 'بوسنی و هرزگوین' ),
			'BB' => array( 'Barbados', 'باربادوس' ),
			'BD' => array( 'Bangladesh', 'بنگلادش' ),
			'BE' => array( 'Belgium', 'بلژیک' ),
			'BF' => array( 'Burkina Faso', 'بورکینافاسو' ),
			'BG' => array( 'Bulgaria', 'بلغارستان' ),
			'BH' => array( 'Bahrain', 'بحرین' ),
			'BI' => array( 'Burundi', 'بوروندی' ),
			'BJ' => array( 'Benin', 'بنین' ),
			'BL' => array( 'Saint Barthélemy', 'سن بارتلمی' ),
			'BM' => array( 'Bermuda', 'برمودا' ),
			'BN' => array( 'Brunei', 'برونئی' ),
			'BO' => array( 'Bolivia', 'بولیوی' ),
			'BQ' => array( 'Caribbean Netherlands', 'هلند کارائیب' ),
			'BR' => array( 'Brazil', 'برزیل' ),
			'BS' => array( 'Bahamas', 'باهاما' ),
			'BT' => array( 'Bhutan', 'بوتان' ),
			'BV' => array( 'Bouvet Island', 'جزیره بووه' ),
			'BW' => array( 'Botswana', 'بوتسوانا' ),
			'BY' => array( 'Belarus', 'بلاروس' ),
			'BZ' => array( 'Belize', 'بلیز' ),
			'CA' => array( 'Canada', 'کانادا' ),
			'CC' => array( 'Cocos (Keeling) Islands', 'جزایر کوکوس' ),
			'CD' => array( 'DR Congo', 'جمهوری دموکراتیک کنگو' ),
			'CF' => array( 'Central African Republic', 'جمهوری آفریقای مرکزی' ),
			'CG' => array( 'Congo', 'کنگو' ),
			'CH' => array( 'Switzerland', 'سوئیس' ),
			'CI' => array( 'Côte d’Ivoire', 'ساحل عاج' ),
			'CK' => array( 'Cook Islands', 'جزایر کوک' ),
			'CL' => array( 'Chile', 'شیلی' ),
			'CM' => array( 'Cameroon', 'کامرون' ),
			'CN' => array( 'China', 'چین' ),
			'CO' => array( 'Colombia', 'کلمبیا' ),
			'CR' => array( 'Costa Rica', 'کاستاریکا' ),
			'CU' => array( 'Cuba', 'کوبا' ),
			'CV' => array( 'Cape Verde', 'کیپ ورد' ),
			'CW' => array( 'Curaçao', 'کوراسائو' ),
			'CX' => array( 'Christmas Island', 'جزیره کریسمس' ),
			'CY' => array( 'Cyprus', 'قبرس' ),
			'CZ' => array( 'Czechia', 'چک' ),
			'DE' => array( 'Germany', 'آلمان' ),
			'DJ' => array( 'Djibouti', 'جیبوتی' ),
			'DK' => array( 'Denmark', 'دانمارک' ),
			'DM' => array( 'Dominica', 'دومینیکا' ),
			'DO' => array( 'Dominican Republic', 'جمهوری دومینیکن' ),
			'DZ' => array( 'Algeria', 'الجزایر' ),
			'EC' => array( 'Ecuador', 'اکوادور' ),
			'EE' => array( 'Estonia', 'استونی' ),
			'EG' => array( 'Egypt', 'مصر' ),
			'EH' => array( 'Western Sahara', 'صحرای غربی' ),
			'ER' => array( 'Eritrea', 'اریتره' ),
			'ES' => array( 'Spain', 'اسپانیا' ),
			'ET' => array( 'Ethiopia', 'اتیوپی' ),
			'FI' => array( 'Finland', 'فنلاند' ),
			'FJ' => array( 'Fiji', 'فیجی' ),
			'FK' => array( 'Falkland Islands', 'جزایر فالکلند' ),
			'FM' => array( 'Micronesia', 'میکرونزی' ),
			'FO' => array( 'Faroe Islands', 'جزایر فارو' ),
			'FR' => array( 'France', 'فرانسه' ),
			'GA' => array( 'Gabon', 'گابن' ),
			'GB' => array( 'United Kingdom', 'بریتانیا' ),
			'GD' => array( 'Grenada', 'گرنادا' ),
			'GE' => array( 'Georgia', 'گرجستان' ),
			'GF' => array( 'French Guiana', 'گویان فرانسه' ),
			'GG' => array( 'Guernsey', 'گرنزی' ),
			'GH' => array( 'Ghana', 'غنا' ),
			'GI' => array( 'Gibraltar', 'جبل‌الطارق' ),
			'GL' => array( 'Greenland', 'گرینلند' ),
			'GM' => array( 'Gambia', 'گامبیا' ),
			'GN' => array( 'Guinea', 'گینه' ),
			'GP' => array( 'Guadeloupe', 'گوادلوپ' ),
			'GQ' => array( 'Equatorial Guinea', 'گینه استوایی' ),
			'GR' => array( 'Greece', 'یونان' ),
			'GS' => array( 'South Georgia and the South Sandwich Islands', 'جورجیای جنوبی و جزایر ساندویچ جنوبی' ),
			'GT' => array( 'Guatemala', 'گواتمالا' ),
			'GU' => array( 'Guam', 'گوام' ),
			'GW' => array( 'Guinea-Bissau', 'گینه بیسائو' ),
			'GY' => array( 'Guyana', 'گویان' ),
			'HK' => array( 'Hong Kong', 'هنگ‌کنگ' ),
			'HM' => array( 'Heard Island and McDonald Islands', 'جزیره هرد و جزایر مک‌دونالد' ),
			'HN' => array( 'Honduras', 'هندوراس' ),
			'HR' => array( 'Croatia', 'کرواسی' ),
			'HT' => array( 'Haiti', 'هائیتی' ),
			'HU' => array( 'Hungary', 'مجارستان' ),
			'ID' => array( 'Indonesia', 'اندونزی' ),
			'IE' => array( 'Ireland', 'ایرلند' ),
			'IL' => array( 'Israel', 'اسرائیل' ),
			'IM' => array( 'Isle of Man', 'جزیره من' ),
			'IN' => array( 'India', 'هند' ),
			'IO' => array( 'British Indian Ocean Territory', 'قلمرو اقیانوس هند بریتانیا' ),
			'IQ' => array( 'Iraq', 'عراق' ),
			'IR' => array( 'Iran', 'ایران' ),
			'IS' => array( 'Iceland', 'ایسلند' ),
			'IT' => array( 'Italy', 'ایتالیا' ),
			'JE' => array( 'Jersey', 'جرسی' ),
			'JM' => array( 'Jamaica', 'جامائیکا' ),
			'JO' => array( 'Jordan', 'اردن' ),
			'JP' => array( 'Japan', 'ژاپن' ),
			'KE' => array( 'Kenya', 'کنیا' ),
			'KG' => array( 'Kyrgyzstan', 'قرقیزستان' ),
			'KH' => array( 'Cambodia', 'کامبوج' ),
			'KI' => array( 'Kiribati', 'کیریباتی' ),
			'KM' => array( 'Comoros', 'کومور' ),
			'KN' => array( 'Saint Kitts and Nevis', 'سنت کیتس و نویس' ),
			'KP' => array( 'North Korea', 'کره شمالی' ),
			'KR' => array( 'South Korea', 'کره جنوبی' ),
			'KW' => array( 'Kuwait', 'کویت' ),
			'KY' => array( 'Cayman Islands', 'جزایر کیمن' ),
			'KZ' => array( 'Kazakhstan', 'قزاقستان' ),
			'LA' => array( 'Laos', 'لائوس' ),
			'LB' => array( 'Lebanon', 'لبنان' ),
			'LC' => array( 'Saint Lucia', 'سنت لوسیا' ),
			'LI' => array( 'Liechtenstein', 'لیختن‌اشتاین' ),
			'LK' => array( 'Sri Lanka', 'سریلانکا' ),
			'LR' => array( 'Liberia', 'لیبریا' ),
			'LS' => array( 'Lesotho', 'لسوتو' ),
			'LT' => array( 'Lithuania', 'لیتوانی' ),
			'LU' => array( 'Luxembourg', 'لوکزامبورگ' ),
			'LV' => array( 'Latvia', 'لتونی' ),
			'LY' => array( 'Libya', 'لیبی' ),
			'MA' => array( 'Morocco', 'مراکش' ),
			'MC' => array( 'Monaco', 'موناکو' ),
			'MD' => array( 'Moldova', 'مولداوی' ),
			'ME' => array( 'Montenegro', 'مونته‌نگرو' ),
			'MF' => array( 'Saint Martin', 'سن مارتن' ),
			'MG' => array( 'Madagascar', 'ماداگاسکار' ),
			'MH' => array( 'Marshall Islands', 'جزایر مارشال' ),
			'MK' => array( 'North Macedonia', 'مقدونیه شمالی' ),
			'ML' => array( 'Mali', 'مالی' ),
			'MM' => array( 'Myanmar', 'میانمار' ),
			'MN' => array( 'Mongolia', 'مغولستان' ),
			'MO' => array( 'Macao', 'ماکائو' ),
			'MP' => array( 'Northern Mariana Islands', 'جزایر ماریانای شمالی' ),
			'MQ' => array( 'Martinique', 'مارتینیک' ),
			'MR' => array( 'Mauritania', 'موریتانی' ),
			'MS' => array( 'Montserrat', 'مونتسرات' ),
			'MT' => array( 'Malta', 'مالت' ),
			'MU' => array( 'Mauritius', 'موریس' ),
			'MV' => array( 'Maldives', 'مالدیو' ),
			'MW' => array( 'Malawi', 'مالاوی' ),
			'MX' => array( 'Mexico', 'مکزیک' ),
			'MY' => array( 'Malaysia', 'مالزی' ),
			'MZ' => array( 'Mozambique', 'موزامبیک' ),
			'NA' => array( 'Namibia', 'نامیبیا' ),
			'NC' => array( 'New Caledonia', 'کالدونیای جدید' ),
			'NE' => array( 'Niger', 'نیجر' ),
			'NF' => array( 'Norfolk Island', 'جزیره نورفولک' ),
			'NG' => array( 'Nigeria', 'نیجریه' ),
			'NI' => array( 'Nicaragua', 'نیکاراگوئه' ),
			'NL' => array( 'Netherlands', 'هلند' ),
			'NO' => array( 'Norway', 'نروژ' ),
			'NP' => array( 'Nepal', 'نپال' ),
			'NR' => array( 'Nauru', 'نائورو' ),
			'NU' => array( 'Niue', 'نیوئه' ),
			'NZ' => array( 'New Zealand', 'نیوزیلند' ),
			'OM' => array( 'Oman', 'عمان' ),
			'PA' => array( 'Panama', 'پاناما' ),
			'PE' => array( 'Peru', 'پرو' ),
			'PF' => array( 'French Polynesia', 'پولینزی فرانسه' ),
			'PG' => array( 'Papua New Guinea', 'پاپوا گینه نو' ),
			'PH' => array( 'Philippines', 'فیلیپین' ),
			'PK' => array( 'Pakistan', 'پاکستان' ),
			'PL' => array( 'Poland', 'لهستان' ),
			'PM' => array( 'Saint Pierre and Miquelon', 'سن پیر و میکلن' ),
			'PN' => array( 'Pitcairn Islands', 'جزایر پیت‌کرن' ),
			'PR' => array( 'Puerto Rico', 'پورتوریکو' ),
			'PS' => array( 'Palestine', 'فلسطین' ),
			'PT' => array( 'Portugal', 'پرتغال' ),
			'PW' => array( 'Palau', 'پالائو' ),
			'PY' => array( 'Paraguay', 'پاراگوئه' ),
			'QA' => array( 'Qatar', 'قطر' ),
			'RE' => array( 'Réunion', 'رئونیون' ),
			'RO' => array( 'Romania', 'رومانی' ),
			'RS' => array( 'Serbia', 'صربستان' ),
			'RU' => array( 'Russia', 'روسیه' ),
			'RW' => array( 'Rwanda', 'رواندا' ),
			'SA' => array( 'Saudi Arabia', 'عربستان سعودی' ),
			'SB' => array( 'Solomon Islands', 'جزایر سلیمان' ),
			'SC' => array( 'Seychelles', 'سیشل' ),
			'SD' => array( 'Sudan', 'سودان' ),
			'SE' => array( 'Sweden', 'سوئد' ),
			'SG' => array( 'Singapore', 'سنگاپور' ),
			'SH' => array( 'Saint Helena', 'سنت هلنا' ),
			'SI' => array( 'Slovenia', 'اسلوونی' ),
			'SJ' => array( 'Svalbard and Jan Mayen', 'سوالبارد و یان ماین' ),
			'SK' => array( 'Slovakia', 'اسلواکی' ),
			'SL' => array( 'Sierra Leone', 'سیرالئون' ),
			'SM' => array( 'San Marino', 'سن مارینو' ),
			'SN' => array( 'Senegal', 'سنگال' ),
			'SO' => array( 'Somalia', 'سومالی' ),
			'SR' => array( 'Suriname', 'سورینام' ),
			'SS' => array( 'South Sudan', 'سودان جنوبی' ),
			'ST' => array( 'São Tomé and Príncipe', 'سائوتومه و پرنسیپ' ),
			'SV' => array( 'El Salvador', 'السالوادور' ),
			'SX' => array( 'Sint Maarten', 'سینت مارتن' ),
			'SY' => array( 'Syria', 'سوریه' ),
			'SZ' => array( 'Eswatini', 'اسواتینی' ),
			'TC' => array( 'Turks and Caicos Islands', 'جزایر تورکس و کایکوس' ),
			'TD' => array( 'Chad', 'چاد' ),
			'TF' => array( 'French Southern Territories', 'قلمروهای جنوبی فرانسه' ),
			'TG' => array( 'Togo', 'توگو' ),
			'TH' => array( 'Thailand', 'تایلند' ),
			'TJ' => array( 'Tajikistan', 'تاجیکستان' ),
			'TK' => array( 'Tokelau', 'توکلائو' ),
			'TL' => array( 'Timor-Leste', 'تیمور شرقی' ),
			'TM' => array( 'Turkmenistan', 'ترکمنستان' ),
			'TN' => array( 'Tunisia', 'تونس' ),
			'TO' => array( 'Tonga', 'تونگا' ),
			'TR' => array( 'Türkiye', 'ترکیه' ),
			'TT' => array( 'Trinidad and Tobago', 'ترینیداد و توباگو' ),
			'TV' => array( 'Tuvalu', 'تووالو' ),
			'TW' => array( 'Taiwan', 'تایوان' ),
			'TZ' => array( 'Tanzania', 'تانزانیا' ),
			'UA' => array( 'Ukraine', 'اوکراین' ),
			'UG' => array( 'Uganda', 'اوگاندا' ),
			'UM' => array( 'U.S. Outlying Islands', 'جزایر کوچک خارجی ایالات متحده' ),
			'US' => array( 'United States', 'ایالات متحده' ),
			'UY' => array( 'Uruguay', 'اروگوئه' ),
			'UZ' => array( 'Uzbekistan', 'ازبکستان' ),
			'VA' => array( 'Vatican City', 'واتیکان' ),
			'VC' => array( 'Saint Vincent and the Grenadines', 'سنت وینسنت و گرنادین' ),
			'VE' => array( 'Venezuela', 'ونزوئلا' ),
			'VG' => array( 'British Virgin Islands', 'جزایر ویرجین بریتانیا' ),
			'VI' => array( 'U.S. Virgin Islands', 'جزایر ویرجین ایالات متحده' ),
			'VN' => array( 'Vietnam', 'ویتنام' ),
			'VU' => array( 'Vanuatu', 'وانواتو' ),
			'WF' => array( 'Wallis and Futuna', 'والیس و فوتونا' ),
			'WS' => array( 'Samoa', 'ساموآ' ),
			'XK' => array( 'Kosovo', 'کوزوو' ),
			'YE' => array( 'Yemen', 'یمن' ),
			'YT' => array( 'Mayotte', 'مایوت' ),
			'ZA' => array( 'South Africa', 'آفریقای جنوبی' ),
			'ZM' => array( 'Zambia', 'زامبیا' ),
			'ZW' => array( 'Zimbabwe', 'زیمبابوه' ),
		);
		return $data;
	}

	/**
	 * Whether a code is a real country code.
	 *
	 * @param string $code Two-letter code.
	 * @return bool
	 */
	public static function exists( $code ) {
		$data = self::data();
		return is_string( $code ) && isset( $data[ strtoupper( $code ) ] );
	}

	/**
	 * Country name.
	 *
	 * @param string      $code Two-letter code.
	 * @param string|null $lang fa or en. Defaults to the toolkit language.
	 * @return string The code itself when the country is not known.
	 */
	public static function name( $code, $lang = null ) {
		$code = strtoupper( (string) $code );
		$data = self::data();
		if ( ! isset( $data[ $code ] ) ) {
			return $code;
		}
		$lang = ( null === $lang ) ? BHTK_I18n::lang() : $lang;
		return 'fa' === $lang ? $data[ $code ][1] : $data[ $code ][0];
	}

	/**
	 * Flag emoji for a country code. Some systems, such as Windows, show the two letters instead.
	 *
	 * @param string $code Two-letter code.
	 * @return string Empty when the code is not valid.
	 */
	public static function flag( $code ) {
		$code = strtoupper( (string) $code );
		if ( ! preg_match( '/^[A-Z]{2}$/', $code ) ) {
			return '';
		}
		$out = '';
		for ( $i = 0; $i < 2; $i++ ) {
			// Regional indicator letters U+1F1E6 to U+1F1FF.
			$out .= "\xF0\x9F\x87" . chr( 0xA6 + ord( $code[ $i ] ) - 65 );
		}
		return $out;
	}
}
