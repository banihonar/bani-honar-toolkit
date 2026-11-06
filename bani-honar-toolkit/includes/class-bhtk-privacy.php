<?php
/**
 * Privacy: suggested text for the privacy policy page and the WordPress personal data tools.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects the toolkit to the privacy tools of WordPress (Tools > Export / Erase Personal Data).
 */
final class BHTK_Privacy {

	const PER_PAGE = 100;

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'add_eraser' ) );
	}

	/**
	 * Suggested text for the privacy policy (Settings > Privacy > Policy Guide).
	 *
	 * @return void
	 */
	public static function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text  = '<h3>' . esc_html__( 'Visitor statistics', 'bani-honar-toolkit' ) . '</h3>';
		$text .= '<p>' . esc_html__( 'Only when the Visitor Stats module is switched on: for every page a visitor opens, this website stores the date and time, the IP address (or the address with its last part removed, if that option is chosen), the country, the page address and title, the website the visitor came from, campaign tags, the browser and device details sent by the browser, the screen size, language and time zone. If the visitor is logged in, the user account is stored too, and so are words typed into the search box of this website. No cookies are set. The data is deleted automatically after the retention period chosen by the site owner.', 'bani-honar-toolkit' ) . '</p>';
		$text .= '<p>' . esc_html__( 'If the option "Find the country online" is switched on, the visitor IP address is sent once to a country lookup service (ipwho.is or GeoJS) to find the country. It is off unless the site owner switches it on.', 'bani-honar-toolkit' ) . '</p>';
		$text .= '<h3>' . esc_html__( 'Profile pictures', 'bani-honar-toolkit' ) . '</h3>';
		$text .= '<p>' . esc_html__( 'Pictures that users upload are stored on this website only. No picture and no e-mail address is sent to Gravatar.', 'bani-honar-toolkit' ) . '</p>';
		$text .= '<h3>' . esc_html__( 'Short links', 'bani-honar-toolkit' ) . '</h3>';
		$text .= '<p>' . esc_html__( 'A short link stores its destination, an optional note, the number of clicks and the time of the last click. Visitors who use a short link are not identified or logged.', 'bani-honar-toolkit' ) . '</p>';

		wp_add_privacy_policy_content( 'Bani Honar Toolkit', wp_kses_post( $text ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function add_exporter( $exporters ) {
		$exporters['bani-honar-toolkit'] = array(
			'exporter_friendly_name' => __( 'Bani Honar Toolkit: visitor statistics', 'bani-honar-toolkit' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function add_eraser( $erasers ) {
		$erasers['bani-honar-toolkit'] = array(
			'eraser_friendly_name' => __( 'Bani Honar Toolkit: visitor statistics', 'bani-honar-toolkit' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * The user behind an e-mail address.
	 *
	 * @param string $email E-mail address.
	 * @return int User id or 0.
	 */
	private static function user_id( $email ) {
		$user = is_email( $email ) ? get_user_by( 'email', $email ) : false;
		return $user ? (int) $user->ID : 0;
	}

	/**
	 * Personal data export: the visits made while the person was logged in.
	 *
	 * @param string $email E-mail address.
	 * @param int    $page  Page, starting at 1.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		$page    = max( 1, (int) $page );
		$user_id = self::user_id( $email );
		$rows    = $user_id ? BHTK_Visits::rows_of_user( $user_id, self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE ) : array();
		$items   = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'group_id'    => 'bhtk-visits',
				'group_label' => __( 'Website visits', 'bani-honar-toolkit' ),
				'item_id'     => 'bhtk-visit-' . (int) $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Date and time (UTC)', 'bani-honar-toolkit' ),
						'value' => (string) $row['visited_at'],
					),
					array(
						'name'  => __( 'IP address', 'bani-honar-toolkit' ),
						'value' => (string) $row['ip'],
					),
					array(
						'name'  => __( 'Page', 'bani-honar-toolkit' ),
						'value' => (string) $row['path'],
					),
					array(
						'name'  => __( 'Referrer', 'bani-honar-toolkit' ),
						'value' => (string) $row['referrer'],
					),
					array(
						'name'  => __( 'User agent', 'bani-honar-toolkit' ),
						'value' => (string) $row['user_agent'],
					),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => count( $rows ) < self::PER_PAGE,
		);
	}

	/**
	 * Personal data eraser: removes the visits made while the person was logged in.
	 *
	 * @param string $email E-mail address.
	 * @param int    $page  Page, starting at 1.
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) {
		unset( $page );
		$user_id = self::user_id( $email );
		$removed = $user_id ? BHTK_Visits::delete_user_rows( $user_id ) : 0;
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
