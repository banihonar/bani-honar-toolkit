<?php
/**
 * Visitor statistics: admin screens.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints the "Visitor Stats" tab: overview, visit log and settings.
 */
final class BHTK_Visits_Page {

	const PER_PAGE = 30;

	/**
	 * Calendar setting of the module.
	 *
	 * @var string
	 */
	private static $mode = 'auto';

	/**
	 * Current view: overview, log or settings.
	 *
	 * @var string
	 */
	private static $view = 'overview';

	/**
	 * Current date range.
	 *
	 * @var array
	 */
	private static $range = array();

	/**
	 * Current filters.
	 *
	 * @var array
	 */
	private static $filters = array();

	/**
	 * Print the tab.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to open this page.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}

		$plugin  = BHTK_Plugin::instance();
		$modules = $plugin->get_modules();
		if ( ! isset( $modules['visitor-stats'] ) ) {
			return;
		}
		$module   = $modules['visitor-stats'];
		$settings = $module->get_settings();
		$on       = $plugin->is_enabled( 'visitor-stats' );
		$admin    = BHTK_Admin::instance();

		// Only reading and paging, so no nonce is needed.
		$get = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		self::$mode    = $settings['calendar'];
		self::$view    = isset( $get['view'] ) && in_array( $get['view'], array( 'overview', 'log', 'blocked', 'settings' ), true ) ? $get['view'] : 'overview';
		self::$range   = BHTK_Visits::resolve_range( $get );
		self::$filters = BHTK_Visits::read_filters( $get );

		$has_table = BHTK_Visits::table_exists();
		if ( $has_table && 'all' === self::$range['key'] ) {
			$first = BHTK_Visits::first_day();
			if ( '' !== $first ) {
				self::$range['from'] = $first;
			}
		}

		$admin->render_header( BHTK_Module_Visitor_Stats::PAGE );
		$admin->render_flash();
		$admin->render_module_switch( $module, BHTK_Module_Visitor_Stats::PAGE, self::$view );

		if ( ! $on ) {
			echo '<div class="bhtk-alert bhtk-alert--warn">';
			if ( $has_table ) {
				esc_html_e( 'Counting is off, so new visits are not recorded. The visits already saved are still shown below.', 'bani-honar-toolkit' );
			} else {
				esc_html_e( 'Counting is off. Switch it on above to start recording visits.', 'bani-honar-toolkit' );
			}
			echo '</div>';
		}

		self::subnav();

		if ( 'settings' === self::$view ) {
			self::render_settings( $module, $has_table );
		} elseif ( 'blocked' === self::$view ) {
			self::render_blocked( $settings, $on, $get );
		} elseif ( ! $has_table || 0 === BHTK_Visits::count_all() ) {
			self::render_empty( $on, $settings );
		} else {
			self::range_bar();
			self::chips();
			if ( 'log' === self::$view ) {
				self::render_log();
			} else {
				self::render_overview();
			}
		}

		$admin->render_footer();
	}

	// ---------------------------------------------------------------------
	// Small helpers.
	// ---------------------------------------------------------------------

	/**
	 * Address of this tab with the current range and filters, changed by $args.
	 *
	 * @param array $args Values to change. A null or empty value removes the parameter.
	 * @return string
	 */
	private static function url( $args = array() ) {
		$base = array(
			'page'  => BHTK_Module_Visitor_Stats::PAGE,
			'view'  => self::$view,
			'range' => self::$range['key'],
		);
		if ( 'custom' === self::$range['key'] ) {
			$base['from'] = self::$range['from'];
			$base['to']   = self::$range['to'];
		}
		$merged = array_merge( $base, self::$filters, $args );
		foreach ( $merged as $key => $value ) {
			if ( null === $value || '' === $value ) {
				unset( $merged[ $key ] );
			} else {
				// add_query_arg() does not encode values, and filter values can hold any character.
				$merged[ $key ] = rawurlencode( (string) $value );
			}
		}
		return add_query_arg( $merged, admin_url( 'admin.php' ) );
	}

	/**
	 * Name of a member for display.
	 *
	 * @param int|string $user_id User id.
	 * @return string
	 */
	private static function user_name( $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user ) {
			return '#' . absint( $user_id );
		}
		return $user->display_name . ' (' . $user->user_login . ')';
	}

	/**
	 * E-mail address of a member, for the details panel.
	 *
	 * @param int|string $user_id User id.
	 * @return string " <address>" or an empty string.
	 */
	private static function user_email( $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		return $user && '' !== $user->user_email ? ' <' . $user->user_email . '>' : '';
	}

	/**
	 * Number for display.
	 *
	 * @param int $n Number.
	 * @return string
	 */
	private static function num( $n ) {
		return number_format_i18n( (int) $n );
	}

	/**
	 * Name of a device type.
	 *
	 * @param string $type Device type.
	 * @return string
	 */
	public static function type_label( $type ) {
		$types = array(
			'mobile'  => __( 'Mobile phone', 'bani-honar-toolkit' ),
			'tablet'  => __( 'Tablet', 'bani-honar-toolkit' ),
			'desktop' => __( 'Computer', 'bani-honar-toolkit' ),
			'tv'      => __( 'Smart TV', 'bani-honar-toolkit' ),
			'console' => __( 'Game console', 'bani-honar-toolkit' ),
			'bot'     => __( 'Robot', 'bani-honar-toolkit' ),
		);
		return isset( $types[ $type ] ) ? $types[ $type ] : __( 'Unknown', 'bani-honar-toolkit' );
	}

	/**
	 * Dashicon for a device type.
	 *
	 * @param string $type Device type.
	 * @return string
	 */
	private static function type_icon( $type ) {
		$icons = array(
			'mobile'  => 'dashicons-smartphone',
			'tablet'  => 'dashicons-tablet',
			'desktop' => 'dashicons-desktop',
		);
		return isset( $icons[ $type ] ) ? $icons[ $type ] : 'dashicons-desktop';
	}

	/**
	 * Human name of a device: brand and model, or what is known about it.
	 *
	 * @param string $brand Brand.
	 * @param string $model Model.
	 * @param string $os    System.
	 * @param string $type  Device type.
	 * @return string
	 */
	public static function device_label( $brand, $model, $os, $type ) {
		if ( 'PC' === $model ) {
			return trim( $os . ' PC' );
		}
		if ( '' !== $model ) {
			if ( '' !== $brand && 0 !== stripos( $model, $brand ) ) {
				return $brand . ' ' . $model;
			}
			return $model;
		}
		if ( 'mobile' === $type && 'Android' === $os ) {
			return __( 'Android phone (model hidden by the browser)', 'bani-honar-toolkit' );
		}
		return self::type_label( $type );
	}

	/**
	 * Country with flag, as safe HTML.
	 *
	 * @param string $code Country code, empty when unknown.
	 * @return string
	 */
	private static function country_html( $code ) {
		if ( '' === $code ) {
			return '<span class="bhtk-flag" aria-hidden="true">🌐</span> ' . esc_html__( 'Unknown', 'bani-honar-toolkit' );
		}
		return '<span class="bhtk-flag" aria-hidden="true">' . esc_html( BHTK_Countries::flag( $code ) ) . '</span> ' . esc_html( BHTK_Countries::name( $code ) ) . ' <span class="bhtk-tag bhtk-ltr">' . esc_html( $code ) . '</span>';
	}

	// ---------------------------------------------------------------------
	// Navigation.
	// ---------------------------------------------------------------------

	/**
	 * Overview / Visit log / Settings.
	 *
	 * @return void
	 */
	private static function subnav() {
		$items = array(
			'overview' => __( 'Overview', 'bani-honar-toolkit' ),
			'log'      => __( 'Visit log', 'bani-honar-toolkit' ),
			'blocked'  => __( 'Blocked IPs', 'bani-honar-toolkit' ),
			'settings' => __( 'Settings', 'bani-honar-toolkit' ),
		);
		echo '<nav class="bhtk-subtabs">';
		foreach ( $items as $view => $label ) {
			$href = add_query_arg(
				array(
					'page' => BHTK_Module_Visitor_Stats::PAGE,
					'view' => $view,
				),
				admin_url( 'admin.php' )
			);
			printf( '<a class="%s" href="%s">%s</a>', $view === self::$view ? 'is-active' : '', esc_url( $href ), esc_html( $label ) );
		}
		echo '</nav>';
	}

	/**
	 * Date range buttons and the custom range form.
	 *
	 * @return void
	 */
	private static function range_bar() {
		$presets = array(
			'today'     => __( 'Today', 'bani-honar-toolkit' ),
			'yesterday' => __( 'Yesterday', 'bani-honar-toolkit' ),
			'7'         => __( 'Last 7 days', 'bani-honar-toolkit' ),
			'30'        => __( 'Last 30 days', 'bani-honar-toolkit' ),
			'90'        => __( 'Last 90 days', 'bani-honar-toolkit' ),
			'365'       => __( 'Last year', 'bani-honar-toolkit' ),
			'all'       => __( 'All time', 'bani-honar-toolkit' ),
		);
		?>
		<section class="bhtk-range">
			<nav class="bhtk-pills" aria-label="<?php esc_attr_e( 'Date range', 'bani-honar-toolkit' ); ?>">
				<?php foreach ( $presets as $key => $label ) : ?>
					<a class="bhtk-pill <?php echo (string) $key === (string) self::$range['key'] ? 'is-on' : ''; ?>" href="<?php echo esc_url( self::url( array( 'range' => $key, 'from' => null, 'to' => null, 'paged' => null ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<form method="get" class="bhtk-range__custom">
				<input type="hidden" name="page" value="<?php echo esc_attr( BHTK_Module_Visitor_Stats::PAGE ); ?>" />
				<input type="hidden" name="view" value="<?php echo esc_attr( self::$view ); ?>" />
				<input type="hidden" name="range" value="custom" />
				<?php foreach ( self::$filters as $key => $value ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
				<?php endforeach; ?>
				<label><?php esc_html_e( 'From', 'bani-honar-toolkit' ); ?>
					<input type="date" name="from" class="bhtk-ltr" value="<?php echo esc_attr( self::$range['from'] ); ?>" required />
				</label>
				<label><?php esc_html_e( 'To', 'bani-honar-toolkit' ); ?>
					<input type="date" name="to" class="bhtk-ltr" value="<?php echo esc_attr( self::$range['to'] ); ?>" required />
				</label>
				<button type="submit" class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm"><?php esc_html_e( 'Show', 'bani-honar-toolkit' ); ?></button>
			</form>
			<p class="bhtk-range__text">
				<?php
				printf(
					/* translators: 1: first day, 2: last day */
					esc_html__( 'Showing %1$s to %2$s', 'bani-honar-toolkit' ),
					'<strong>' . esc_html( BHTK_Calendar::day( self::$range['from'], self::$mode, 'long' ) ) . '</strong>',
					'<strong>' . esc_html( BHTK_Calendar::day( self::$range['to'], self::$mode, 'long' ) ) . '</strong>'
				);
				?>
			</p>
		</section>
		<?php
	}

	/**
	 * Active filters, each with a link that removes it.
	 *
	 * @return void
	 */
	private static function chips() {
		if ( ! self::$filters ) {
			return;
		}
		$names = array(
			'country'  => __( 'Country', 'bani-honar-toolkit' ),
			'rtype'    => __( 'Source type', 'bani-honar-toolkit' ),
			'source'   => __( 'Source', 'bani-honar-toolkit' ),
			'campaign' => __( 'Campaign', 'bani-honar-toolkit' ),
			'dtype'    => __( 'Device type', 'bani-honar-toolkit' ),
			'brand'    => __( 'Brand', 'bani-honar-toolkit' ),
			'model'    => __( 'Model', 'bani-honar-toolkit' ),
			'os'       => __( 'System', 'bani-honar-toolkit' ),
			'browser'  => __( 'Browser', 'bani-honar-toolkit' ),
			'path'     => __( 'Page', 'bani-honar-toolkit' ),
			'ip'       => __( 'IP address', 'bani-honar-toolkit' ),
			'search'   => __( 'Search on the site', 'bani-honar-toolkit' ),
			'user'     => __( 'Logged-in user', 'bani-honar-toolkit' ),
		);
		echo '<div class="bhtk-chips"><span class="bhtk-chips__label">' . esc_html__( 'Filtered by:', 'bani-honar-toolkit' ) . '</span>';
		foreach ( self::$filters as $key => $value ) {
			if ( 'country' === $key ) {
				$shown = '-' === $value ? __( 'Unknown', 'bani-honar-toolkit' ) : BHTK_Countries::name( $value );
			} elseif ( 'rtype' === $key ) {
				$shown = BHTK_Referrer::type_label( $value );
			} elseif ( 'dtype' === $key ) {
				$shown = self::type_label( $value );
			} elseif ( 'user' === $key ) {
				$shown = self::user_name( $value );
			} else {
				$shown = $value;
			}
			printf(
				'<a class="bhtk-chip-link" href="%s" title="%s"><span>%s:</span> <strong>%s</strong> <span aria-hidden="true">✕</span></a>',
				esc_url( self::url( array( $key => null, 'paged' => null ) ) ),
				esc_attr__( 'Remove this filter', 'bani-honar-toolkit' ),
				esc_html( isset( $names[ $key ] ) ? $names[ $key ] : $key ),
				esc_html( $shown )
			);
		}
		$clear = array( 'paged' => null );
		foreach ( array_keys( self::$filters ) as $key ) {
			$clear[ $key ] = null;
		}
		printf( '<a class="bhtk-chip-link bhtk-chip-link--clear" href="%s">%s</a>', esc_url( self::url( $clear ) ), esc_html__( 'Clear all filters', 'bani-honar-toolkit' ) );
		echo '</div>';
	}

	// ---------------------------------------------------------------------
	// Empty state.
	// ---------------------------------------------------------------------

	/**
	 * Shown while nothing has been recorded yet.
	 *
	 * @param bool  $on       Whether counting is on.
	 * @param array $settings Module settings.
	 * @return void
	 */
	private static function render_empty( $on, $settings ) {
		?>
		<section class="bhtk-card bhtk-empty-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'No visits recorded yet', 'bani-honar-toolkit' ); ?></h2>
			<?php if ( $on ) : ?>
				<p><?php esc_html_e( 'Counting is on. Visits appear here a few seconds after a page of your website is opened.', 'bani-honar-toolkit' ); ?></p>
				<?php if ( $settings['ignore_admins'] ) : ?>
					<p><?php esc_html_e( 'Your own visits are not counted while you are logged in as an administrator. To test, open your website in a private window or on your phone.', 'bani-honar-toolkit' ); ?></p>
				<?php endif; ?>
				<p class="bhtk-note"><?php esc_html_e( 'If a visit never shows up, check that a security or cache plugin is not blocking admin-ajax.php for visitors.', 'bani-honar-toolkit' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Switch the module on with the switch above. From then on every page view is recorded: when, from which IP address and country, where the visitor came from, and on which device.', 'bani-honar-toolkit' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}

	// ---------------------------------------------------------------------
	// Overview.
	// ---------------------------------------------------------------------

	/**
	 * Tiles, chart, breakdowns and the latest visits.
	 *
	 * @return void
	 */
	private static function render_overview() {
		$range   = self::$range;
		$filters = self::$filters;
		$sum     = BHTK_Visits::summary( $range, $filters );
		$visits  = max( 1, $sum['visits'] );
		?>
		<section class="bhtk-stats bhtk-stats--auto">
			<div class="bhtk-stat">
				<span class="bhtk-stat__label"><?php esc_html_e( 'Page views', 'bani-honar-toolkit' ); ?></span>
				<strong class="bhtk-stat__value"><?php echo esc_html( self::num( $sum['views'] ) ); ?></strong>
			</div>
			<div class="bhtk-stat">
				<span class="bhtk-stat__label"><?php esc_html_e( 'Visitors', 'bani-honar-toolkit' ); ?></span>
				<strong class="bhtk-stat__value"><?php echo esc_html( self::num( $sum['visitors'] ) ); ?></strong>
			</div>
			<div class="bhtk-stat">
				<span class="bhtk-stat__label"><?php esc_html_e( 'Visits', 'bani-honar-toolkit' ); ?></span>
				<strong class="bhtk-stat__value"><?php echo esc_html( self::num( $sum['visits'] ) ); ?></strong>
			</div>
			<div class="bhtk-stat">
				<span class="bhtk-stat__label"><?php esc_html_e( 'Countries', 'bani-honar-toolkit' ); ?></span>
				<strong class="bhtk-stat__value"><?php echo esc_html( self::num( $sum['countries'] ) ); ?></strong>
			</div>
			<div class="bhtk-stat bhtk-stat--live">
				<span class="bhtk-stat__label"><?php esc_html_e( 'Online now (last 5 minutes)', 'bani-honar-toolkit' ); ?></span>
				<strong class="bhtk-stat__value"><?php echo esc_html( self::num( BHTK_Visits::online_now( 5 ) ) ); ?></strong>
			</div>
		</section>
		<p class="bhtk-note bhtk-note--tight"><?php esc_html_e( 'Page views: every page opened. Visitors: different people (by IP address and browser). Visits: how many times people arrived. The lists below count visits.', 'bani-honar-toolkit' ); ?></p>

		<section class="bhtk-card">
			<div class="bhtk-card__head">
				<h2 class="bhtk-card__title"><?php esc_html_e( 'Visits over time', 'bani-honar-toolkit' ); ?></h2>
				<span class="bhtk-legend"><i class="bhtk-legend__bar"></i><?php esc_html_e( 'Page views', 'bani-honar-toolkit' ); ?> <i class="bhtk-legend__line"></i><?php esc_html_e( 'Visits', 'bani-honar-toolkit' ); ?></span>
			</div>
			<?php echo self::chart( BHTK_Visits::daily( $range, $filters ), $range ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped inside chart(). ?>
		</section>

		<div class="bhtk-grid">
			<?php
			// Countries.
			$rows = array();
			foreach ( BHTK_Visits::top( 'country', $range, $filters, 10, true ) as $code => $count ) {
				$rows[] = array(
					'label' => self::country_html( (string) $code ),
					'count' => $count,
					'href'  => self::url( array( 'country' => '' === $code ? '-' : $code, 'paged' => null ) ),
				);
			}
			self::card( __( 'Countries', 'bani-honar-toolkit' ), $rows, $visits );

			// Sources.
			$rows = array();
			foreach ( BHTK_Visits::sources( $range, $filters, 10 ) as $row ) {
				$args = array( 'rtype' => $row['type'], 'source' => $row['name'], 'paged' => null );
				$rows[] = array(
					'label' => esc_html( BHTK_Referrer::label( $row['type'], $row['name'] ) ) . ( in_array( $row['type'], array( 'direct', 'internal' ), true ) ? '' : ' <span class="bhtk-tag">' . esc_html( BHTK_Referrer::type_label( $row['type'] ) ) . '</span>' ),
					'count' => $row['count'],
					'href'  => self::url( $args ),
				);
			}
			self::card( __( 'Where visitors came from', 'bani-honar-toolkit' ), $rows, $visits );

			// Device types.
			$rows = array();
			foreach ( BHTK_Visits::top( 'device_type', $range, $filters, 6 ) as $type => $count ) {
				$rows[] = array(
					'label' => '<span class="dashicons ' . esc_attr( self::type_icon( (string) $type ) ) . '" aria-hidden="true"></span> ' . esc_html( self::type_label( (string) $type ) ),
					'count' => $count,
					'href'  => self::url( array( 'dtype' => $type, 'paged' => null ) ),
				);
			}
			self::card( __( 'Device types', 'bani-honar-toolkit' ), $rows, $visits );

			// Phone and tablet models.
			$rows = array();
			foreach ( BHTK_Visits::models( $range, $filters, 10 ) as $row ) {
				$rows[] = array(
					'label' => esc_html( self::device_label( $row['brand'], $row['model'], '', '' ) ),
					'count' => $row['count'],
					'href'  => self::url( array( 'brand' => $row['brand'], 'model' => $row['model'], 'paged' => null ) ),
				);
			}
			self::card( __( 'Phone and tablet models', 'bani-honar-toolkit' ), $rows, $visits );

			// Systems.
			$rows = array();
			foreach ( BHTK_Visits::top( 'os', $range, $filters, 8 ) as $name => $count ) {
				$rows[] = array(
					'label' => esc_html( (string) $name ),
					'count' => $count,
					'href'  => self::url( array( 'os' => $name, 'paged' => null ) ),
				);
			}
			self::card( __( 'Operating systems', 'bani-honar-toolkit' ), $rows, $visits );

			// Browsers.
			$rows = array();
			foreach ( BHTK_Visits::top( 'browser', $range, $filters, 8 ) as $name => $count ) {
				$rows[] = array(
					'label' => esc_html( (string) $name ),
					'count' => $count,
					'href'  => self::url( array( 'browser' => $name, 'paged' => null ) ),
				);
			}
			self::card( __( 'Browsers', 'bani-honar-toolkit' ), $rows, $visits );

			// Campaigns.
			$rows = array();
			foreach ( BHTK_Visits::top( 'utm_campaign', $range, $filters, 8 ) as $name => $count ) {
				$rows[] = array(
					'label' => esc_html( (string) $name ),
					'count' => $count,
					'href'  => self::url( array( 'campaign' => $name, 'paged' => null ) ),
				);
			}
			if ( $rows ) {
				self::card( __( 'Campaigns (utm_campaign)', 'bani-honar-toolkit' ), $rows, $visits );
			}

			// Pages.
			$rows = array();
			foreach ( BHTK_Visits::pages( $range, $filters, 10 ) as $row ) {
				$rows[] = array(
					'label' => '<span class="bhtk-ltr">' . esc_html( $row['path'] ) . '</span>' . ( '' !== $row['title'] ? '<small>' . esc_html( $row['title'] ) . '</small>' : '' ),
					'count' => $row['views'],
					'href'  => self::url( array( 'path' => $row['path'], 'paged' => null ) ),
				);
			}
			self::card( __( 'Most viewed pages', 'bani-honar-toolkit' ), $rows, max( 1, $sum['views'] ) );

			// Words typed into the search box of the website.
			$rows  = array();
			$terms = BHTK_Visits::searches( $range, $filters, 10 );
			foreach ( $terms as $term => $count ) {
				$rows[] = array(
					'label' => esc_html( (string) $term ),
					'count' => $count,
					'href'  => self::url( array( 'view' => 'log', 'search' => $term, 'paged' => null ) ),
				);
			}
			self::card(
				__( 'Searches on your website', 'bani-honar-toolkit' ),
				$rows,
				max( 1, array_sum( $terms ) ),
				'<p class="bhtk-note">' . esc_html__( 'These are the words visitors typed into the search box of your website. Google no longer tells websites what a visitor searched for before arriving; to see those words, connect the site to Google Search Console (free).', 'bani-honar-toolkit' ) . ' <a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer">Google Search Console</a></p>'
			);
			?>
		</div>

		<section class="bhtk-card">
			<div class="bhtk-card__head">
				<h2 class="bhtk-card__title"><?php esc_html_e( 'Latest visits', 'bani-honar-toolkit' ); ?></h2>
				<a class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" href="<?php echo esc_url( self::url( array( 'view' => 'log', 'paged' => null ) ) ); ?>"><?php esc_html_e( 'Open the full visit log', 'bani-honar-toolkit' ); ?></a>
			</div>
			<?php
			list( $rows ) = BHTK_Visits::log( $range, $filters, 1, 10 );
			self::log_table( $rows );
			?>
		</section>
		<?php
	}

	/**
	 * A card with a list of bars.
	 *
	 * @param string $title Card title.
	 * @param array  $rows  Rows with label (safe HTML), count, href.
	 * @param int    $total Number that 100% stands for.
	 * @param string $footer Optional note under the list (safe HTML).
	 * @return void
	 */
	private static function card( $title, $rows, $total, $footer = '' ) {
		echo '<section class="bhtk-card bhtk-card--list"><h2 class="bhtk-card__title">' . esc_html( $title ) . '</h2>';
		if ( ! $rows ) {
			echo '<p class="bhtk-note">' . esc_html__( 'No data yet.', 'bani-honar-toolkit' ) . '</p>' . wp_kses( $footer, self::footer_html() ) . '</section>';
			return;
		}
		echo '<ul class="bhtk-bars">';
		foreach ( $rows as $row ) {
			$percent = min( 100, round( $row['count'] * 100 / $total ) );
			printf(
				'<li><a class="bhtk-bar" href="%1$s"><span class="bhtk-bar__fill" style="width:%2$d%%"></span><span class="bhtk-bar__label">%3$s</span><span class="bhtk-bar__num">%4$s <em>%5$d%%</em></span></a></li>',
				esc_url( $row['href'] ),
				(int) $percent,
				wp_kses( $row['label'], self::allowed_html() ),
				esc_html( self::num( $row['count'] ) ),
				(int) $percent
			);
		}
		echo '</ul>' . wp_kses( $footer, self::footer_html() ) . '</section>';
	}

	/**
	 * Tags allowed in the labels of the bar lists.
	 *
	 * @return array
	 */
	private static function footer_html() {
		return array(
			'p' => array( 'class' => true ),
			'a' => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
		);
	}

	/**
	 * Tags allowed in the labels of the bar lists.
	 *
	 * @return array
	 */
	private static function allowed_html() {
		return array(
			'span'  => array(
				'class'       => true,
				'aria-hidden' => true,
			),
			'small' => array(),
		);
	}

	// ---------------------------------------------------------------------
	// Chart.
	// ---------------------------------------------------------------------

	/**
	 * Step and number of grid lines for a y axis that reaches $max.
	 *
	 * @param int $max Highest value.
	 * @return int[] step, lines.
	 */
	private static function axis( $max ) {
		$max = max( 1, (int) $max );
		$pow = (int) pow( 10, (int) floor( log10( $max ) ) );
		foreach ( array( 1, 2, 5, 10 ) as $mult ) {
			$step  = max( 1, $mult * $pow / 5 );
			$lines = (int) ceil( $max / $step );
			if ( $lines <= 5 ) {
				return array( (int) $step, max( 2, $lines ) );
			}
		}
		return array( $pow * 2, 5 );
	}

	/**
	 * Bar and line chart as inline SVG.
	 *
	 * Page views are bars, visits are the line. Both add up correctly, so long ranges
	 * are grouped by month without changing the numbers.
	 *
	 * @param array $daily Y-m-d => array( views, visitors, visits ).
	 * @param array $range Range.
	 * @return string Safe HTML.
	 */
	private static function chart( $daily, $range ) {
		if ( ! $daily ) {
			return '<div class="bhtk-empty">' . esc_html__( 'No visits in this period.', 'bani-honar-toolkit' ) . '</div>';
		}

		// Every day of the range, also the quiet ones.
		$days = array();
		try {
			$cursor = new DateTimeImmutable( $range['from'] );
			$end    = new DateTimeImmutable( $range['to'] );
		} catch ( Exception $e ) {
			return '';
		}
		$guard = 0;
		while ( $cursor <= $end && $guard < 4000 ) {
			$days[] = $cursor->format( 'Y-m-d' );
			$cursor = $cursor->modify( '+1 day' );
			++$guard;
		}

		$monthly = count( $days ) > 120;
		$buckets = array();
		foreach ( $days as $day ) {
			$row = isset( $daily[ $day ] ) ? $daily[ $day ] : array(
				'views'    => 0,
				'visitors' => 0,
				'visits'   => 0,
			);
			if ( $monthly ) {
				$key = BHTK_Calendar::month_key( $day, self::$mode );
				if ( ! isset( $buckets[ $key ] ) ) {
					$buckets[ $key ] = array(
						'label'    => BHTK_Calendar::month_label( $day, self::$mode ),
						'short'    => BHTK_Calendar::month_label( $day, self::$mode ),
						'views'    => 0,
						'visits'   => 0,
						'visitors' => null,
					);
				}
				$buckets[ $key ]['views']  += $row['views'];
				$buckets[ $key ]['visits'] += $row['visits'];
			} else {
				$buckets[ $day ] = array(
					'label'    => BHTK_Calendar::day( $day, self::$mode, 'long' ),
					'short'    => BHTK_Calendar::day( $day, self::$mode, 'short' ),
					'views'    => $row['views'],
					'visits'   => $row['visits'],
					'visitors' => $row['visitors'],
				);
			}
		}
		$buckets = array_values( $buckets );
		$n       = count( $buckets );
		if ( $n < 1 ) {
			return '';
		}

		$top = 1;
		foreach ( $buckets as $b ) {
			$top = max( $top, $b['views'], $b['visits'] );
		}
		list( $step, $lines ) = self::axis( $top );
		$max                  = $step * $lines;

		$w      = 1000;
		$h      = 300;
		$pl     = 56;
		$pr     = 14;
		$pt     = 14;
		$pb     = 34;
		$plot_w = $w - $pl - $pr;
		$plot_h = $h - $pt - $pb;
		$slot   = $plot_w / $n;
		$bar_w  = max( 2, min( 44, $slot * 0.62 ) );
		$y      = static function ( $v ) use ( $pt, $plot_h, $max ) {
			return $pt + $plot_h - ( $v / $max ) * $plot_h;
		};

		$svg  = '<div class="bhtk-chart" dir="ltr"><svg viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . esc_attr__( 'Chart of page views and visits over time', 'bani-honar-toolkit' ) . '" preserveAspectRatio="xMidYMid meet">';
		for ( $i = 0; $i <= $lines; $i++ ) {
			$yy    = round( $y( $i * $step ), 1 );
			$svg  .= '<line class="bhtk-chart__grid" x1="' . $pl . '" x2="' . ( $w - $pr ) . '" y1="' . $yy . '" y2="' . $yy . '" />';
			$svg  .= '<text class="bhtk-chart__txt" x="' . ( $pl - 8 ) . '" y="' . ( $yy + 4 ) . '" text-anchor="end">' . esc_html( number_format_i18n( $i * $step ) ) . '</text>';
		}

		$points = array();
		foreach ( $buckets as $i => $b ) {
			$cx     = $pl + $slot * $i + $slot / 2;
			$bar_h  = ( $b['views'] / $max ) * $plot_h;
			$svg   .= '<rect class="bhtk-chart__bar" x="' . round( $cx - $bar_w / 2, 1 ) . '" y="' . round( $pt + $plot_h - $bar_h, 1 ) . '" width="' . round( $bar_w, 1 ) . '" height="' . round( $bar_h, 1 ) . '" rx="3" />';
			$points[] = round( $cx, 1 ) . ',' . round( $y( $b['visits'] ), 1 );
		}
		$svg .= '<polyline class="bhtk-chart__line" points="' . esc_attr( implode( ' ', $points ) ) . '" />';
		if ( $n <= 62 ) {
			foreach ( $points as $p ) {
				list( $px, $py ) = explode( ',', $p );
				$svg            .= '<circle class="bhtk-chart__dot" cx="' . $px . '" cy="' . $py . '" r="3.5" />';
			}
		}

		$every = max( 1, (int) ceil( $n / 9 ) );
		foreach ( $buckets as $i => $b ) {
			$cx = $pl + $slot * $i + $slot / 2;
			if ( 0 === $i % $every ) {
				$svg .= '<text class="bhtk-chart__txt" x="' . round( $cx, 1 ) . '" y="' . ( $h - 10 ) . '" text-anchor="middle">' . esc_html( $b['short'] ) . '</text>';
			}
			// Invisible full-height area that shows the numbers when the mouse is over it.
			$tip = $b['label'] . "\n" . __( 'Page views', 'bani-honar-toolkit' ) . ': ' . number_format_i18n( $b['views'] ) . "\n" . __( 'Visits', 'bani-honar-toolkit' ) . ': ' . number_format_i18n( $b['visits'] );
			if ( null !== $b['visitors'] ) {
				$tip .= "\n" . __( 'Visitors', 'bani-honar-toolkit' ) . ': ' . number_format_i18n( $b['visitors'] );
			}
			$svg .= '<rect class="bhtk-chart__hit" x="' . round( $pl + $slot * $i, 1 ) . '" y="' . $pt . '" width="' . round( $slot, 1 ) . '" height="' . $plot_h . '"><title>' . esc_html( $tip ) . '</title></rect>';
		}
		$svg .= '</svg></div>';

		return $svg;
	}

	// ---------------------------------------------------------------------
	// Visit log.
	// ---------------------------------------------------------------------

	/**
	 * The visit log with filters, paging and export.
	 *
	 * @return void
	 */
	private static function render_log() {
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		list( $rows, $total ) = BHTK_Visits::log( self::$range, self::$filters, $paged, self::PER_PAGE );
		?>
		<section class="bhtk-card">
			<div class="bhtk-card__head">
				<h2 class="bhtk-card__title">
					<?php
					printf(
						/* translators: %s: number of visits */
						esc_html__( 'Visit log (%s page views)', 'bani-honar-toolkit' ),
						esc_html( self::num( $total ) )
					);
					?>
				</h2>
				<div class="bhtk-card__tools">
					<form method="get" class="bhtk-search">
						<input type="hidden" name="page" value="<?php echo esc_attr( BHTK_Module_Visitor_Stats::PAGE ); ?>" />
						<input type="hidden" name="view" value="log" />
						<input type="hidden" name="range" value="<?php echo esc_attr( self::$range['key'] ); ?>" />
						<?php if ( 'custom' === self::$range['key'] ) : ?>
							<input type="hidden" name="from" value="<?php echo esc_attr( self::$range['from'] ); ?>" />
							<input type="hidden" name="to" value="<?php echo esc_attr( self::$range['to'] ); ?>" />
						<?php endif; ?>
						<?php foreach ( self::$filters as $key => $value ) : ?>
							<?php if ( 'ip' !== $key ) : ?>
								<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
							<?php endif; ?>
						<?php endforeach; ?>
						<input type="search" class="bhtk-ltr" name="ip" value="<?php echo esc_attr( isset( self::$filters['ip'] ) ? self::$filters['ip'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Search an IP address, for example 5.120.', 'bani-honar-toolkit' ); ?>" />
						<button type="submit" class="bhtk-btn bhtk-btn--ghost"><?php esc_html_e( 'Search', 'bani-honar-toolkit' ); ?></button>
					</form>
					<?php self::export_form( false ); ?>
				</div>
			</div>
			<?php
			self::log_table( $rows );

			$pages = (int) ceil( $total / self::PER_PAGE );
			if ( $pages > 1 ) {
				echo '<nav class="bhtk-pager">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $pages,
							'prev_text' => '&lsaquo;',
							'next_text' => '&rsaquo;',
						)
					)
				);
				echo '</nav>';
			}
			?>
		</section>
		<?php
	}

	/**
	 * Export button with the current range and filters.
	 *
	 * @param bool $everything Ignore the current range and filters and export all visits.
	 * @return void
	 */
	private static function export_form( $everything ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bhtk-inline-form">
			<input type="hidden" name="action" value="bhtk_visits_export" />
			<?php wp_nonce_field( 'bhtk_visits_export', '_bhtk_nonce' ); ?>
			<?php if ( $everything ) : ?>
				<input type="hidden" name="range" value="all" />
			<?php else : ?>
				<input type="hidden" name="range" value="<?php echo esc_attr( self::$range['key'] ); ?>" />
				<input type="hidden" name="from" value="<?php echo esc_attr( self::$range['from'] ); ?>" />
				<input type="hidden" name="to" value="<?php echo esc_attr( self::$range['to'] ); ?>" />
				<?php foreach ( self::$filters as $key => $value ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
				<?php endforeach; ?>
			<?php endif; ?>
			<button type="submit" class="bhtk-btn bhtk-btn--gold"><?php echo $everything ? esc_html__( 'Download all visits (CSV)', 'bani-honar-toolkit' ) : esc_html__( 'Download CSV', 'bani-honar-toolkit' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Table of visits.
	 *
	 * @param array $rows Rows from BHTK_Visits::log().
	 * @return void
	 */
	private static function log_table( $rows ) {
		if ( ! $rows ) {
			echo '<div class="bhtk-empty">' . esc_html__( 'Nothing found for these filters.', 'bani-honar-toolkit' ) . '</div>';
			return;
		}
		?>
		<table class="bhtk-table bhtk-table--visits">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'bani-honar-toolkit' ); ?></th>
					<th><?php esc_html_e( 'IP address', 'bani-honar-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Country', 'bani-honar-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Source', 'bani-honar-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Page', 'bani-honar-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Device', 'bani-honar-toolkit' ); ?></th>
					<th><?php esc_html_e( 'Browser and system', 'bani-honar-toolkit' ); ?></th>
					<th><span class="screen-reader-text"><?php esc_html_e( 'Details', 'bani-honar-toolkit' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<?php
				$id      = absint( $row->id );
				$device  = self::device_label( $row->device_brand, $row->device_model, $row->os, $row->device_type );
				$browser = trim( $row->browser . ' ' . $row->browser_version );
				$system  = trim( $row->os . ' ' . $row->os_version );
				?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Time', 'bani-honar-toolkit' ); ?>" class="bhtk-nowrap bhtk-ltr"><?php echo esc_html( BHTK_Calendar::datetime( $row->visited_at, self::$mode ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'IP address', 'bani-honar-toolkit' ); ?>"><a class="bhtk-ltr bhtk-iplink" href="<?php echo esc_url( self::url( array( 'view' => 'log', 'ip' => $row->ip, 'paged' => null ) ) ); ?>"><?php echo esc_html( $row->ip ); ?></a>
						<?php if ( (int) $row->user_id ) : ?>
							<a class="bhtk-tag" href="<?php echo esc_url( self::url( array( 'view' => 'log', 'user' => (int) $row->user_id, 'paged' => null ) ) ); ?>"><?php echo esc_html( self::user_name( $row->user_id ) ); ?></a>
						<?php endif; ?>
					</td>
					<td data-label="<?php esc_attr_e( 'Country', 'bani-honar-toolkit' ); ?>"><a href="<?php echo esc_url( self::url( array( 'view' => 'log', 'country' => '' === $row->country ? '-' : $row->country, 'paged' => null ) ) ); ?>"><?php echo wp_kses( self::country_html( $row->country ), self::allowed_html() ); ?></a></td>
					<td data-label="<?php esc_attr_e( 'Source', 'bani-honar-toolkit' ); ?>">
						<a href="<?php echo esc_url( self::url( array( 'view' => 'log', 'rtype' => $row->ref_type, 'source' => $row->ref_name, 'paged' => null ) ) ); ?>"><?php echo esc_html( BHTK_Referrer::label( $row->ref_type, $row->ref_name ) ); ?></a>
						<?php if ( 'campaign' === $row->ref_type ) : ?>
							<span class="bhtk-tag"><?php esc_html_e( 'Campaign', 'bani-honar-toolkit' ); ?></span>
						<?php endif; ?>
					</td>
					<td data-label="<?php esc_attr_e( 'Page', 'bani-honar-toolkit' ); ?>">
						<span class="bhtk-ltr bhtk-path"><?php echo esc_html( $row->path ); ?></span>
						<?php if ( $row->is_entry ) : ?>
							<span class="bhtk-tag"><?php esc_html_e( 'Entry', 'bani-honar-toolkit' ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $row->title ) : ?>
							<span class="bhtk-note"><?php echo esc_html( $row->title ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $row->search_term ) : ?>
							<a class="bhtk-tag" href="<?php echo esc_url( self::url( array( 'view' => 'log', 'search' => $row->search_term, 'paged' => null ) ) ); ?>"><?php esc_html_e( 'Search', 'bani-honar-toolkit' ); ?>: <?php echo esc_html( $row->search_term ); ?></a>
						<?php endif; ?>
					</td>
					<td data-label="<?php esc_attr_e( 'Device', 'bani-honar-toolkit' ); ?>">
						<span class="dashicons <?php echo esc_attr( self::type_icon( $row->device_type ) ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $device ); ?>
						<?php if ( '' !== $row->device_code ) : ?>
							<span class="bhtk-note bhtk-ltr"><?php echo esc_html( $row->device_code ); ?></span>
						<?php endif; ?>
					</td>
					<td data-label="<?php esc_attr_e( 'Browser and system', 'bani-honar-toolkit' ); ?>">
						<?php echo esc_html( '' !== $browser ? $browser : '-' ); ?>
						<span class="bhtk-note"><?php echo esc_html( '' !== $system ? $system : '-' ); ?></span>
					</td>
					<td class="bhtk-actions"><button type="button" class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" data-toggle-edit="v<?php echo absint( $id ); ?>"><?php esc_html_e( 'Details', 'bani-honar-toolkit' ); ?></button></td>
				</tr>
				<tr class="bhtk-row-edit" id="bhtk-edit-v<?php echo absint( $id ); ?>" hidden>
					<td colspan="8">
						<dl class="bhtk-dl">
							<?php
							$details = array(
								__( 'Exact time', 'bani-honar-toolkit' )     => BHTK_Calendar::datetime( $row->visited_at, self::$mode, true ),
								__( 'IP address', 'bani-honar-toolkit' )     => $row->ip,
								__( 'Country', 'bani-honar-toolkit' )        => '' !== $row->country ? BHTK_Countries::name( $row->country ) . ' (' . $row->country . ')' : __( 'Unknown', 'bani-honar-toolkit' ),
								__( 'Came from', 'bani-honar-toolkit' )      => '' !== $row->referrer ? $row->referrer : __( 'No referrer', 'bani-honar-toolkit' ),
								__( 'Campaign source', 'bani-honar-toolkit' ) => $row->utm_source,
								__( 'Campaign medium', 'bani-honar-toolkit' ) => $row->utm_medium,
								__( 'Campaign name', 'bani-honar-toolkit' )  => $row->utm_campaign,
								__( 'Device', 'bani-honar-toolkit' )         => $device . ( '' !== $row->device_code ? ' (' . $row->device_code . ')' : '' ),
								__( 'Device type', 'bani-honar-toolkit' )    => self::type_label( $row->device_type ),
								__( 'Screen', 'bani-honar-toolkit' )         => $row->screen,
								__( 'Language', 'bani-honar-toolkit' )       => $row->lang,
								__( 'Time zone of the device', 'bani-honar-toolkit' ) => $row->tz,
								__( 'Logged-in user', 'bani-honar-toolkit' ) => (int) $row->user_id ? self::user_name( $row->user_id ) . self::user_email( $row->user_id ) : '',
								__( 'Search on the site', 'bani-honar-toolkit' ) => $row->search_term,
								__( 'Visit id', 'bani-honar-toolkit' )       => $row->session_id,
								__( 'User agent', 'bani-honar-toolkit' )     => $row->user_agent,
							);
							foreach ( $details as $label => $value ) {
								if ( '' === (string) $value ) {
									continue;
								}
								echo '<div><dt>' . esc_html( $label ) . '</dt><dd class="bhtk-ltr">' . esc_html( $value ) . '</dd></div>';
							}
							?>
						</dl>
						<?php if ( BHTK_Geo::is_public( $row->ip ) && ! BHTK_Blocklist::is_blocked( $row->ip ) ) : ?>
							<p><a class="bhtk-btn bhtk-btn--danger bhtk-btn--sm" href="<?php echo esc_url( add_query_arg( array( 'page' => BHTK_Module_Visitor_Stats::PAGE, 'view' => 'blocked', 'add' => rawurlencode( $row->ip ) ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Block this IP address', 'bani-honar-toolkit' ); ?></a></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	// ---------------------------------------------------------------------
	// Blocked addresses.
	// ---------------------------------------------------------------------

	/**
	 * List of blocked addresses and the form to add one.
	 *
	 * @param array $settings Module settings.
	 * @param bool  $on       Whether the module is on.
	 * @param array $get      Request values.
	 * @return void
	 */
	private static function render_blocked( $settings, $on, $get ) {
		$list    = BHTK_Blocklist::all();
		$now     = time();
		$prefill = isset( $get['add'] ) && is_scalar( $get['add'] ) ? mb_substr( sanitize_text_field( (string) $get['add'] ), 0, 64 ) : '';
		$exact   = array();
		foreach ( array_keys( $list ) as $target ) {
			if ( false === strpos( (string) $target, '/' ) ) {
				$exact[] = (string) $target;
			}
		}
		$counts = BHTK_Visits::counts_for_ips( $exact );

		if ( ! $on ) {
			echo '<div class="bhtk-alert bhtk-alert--warn">' . esc_html__( 'Blocking works only while this module is on. While it is off, nobody is blocked.', 'bani-honar-toolkit' ) . '</div>';
		}
		if ( $settings['anonymize_ip'] ) {
			echo '<div class="bhtk-alert bhtk-alert--warn">' . esc_html__( 'The option that hides the last part of every IP address is on, so the stored addresses are not the real ones. Turn it off in Settings before blocking from the visit log.', 'bani-honar-toolkit' ) . '</div>';
		}
		?>
		<section class="bhtk-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'Block an IP address', 'bani-honar-toolkit' ); ?></h2>
			<p class="bhtk-note"><?php esc_html_e( 'A blocked visitor sees an "Access blocked" page instead of your website, including the login page. Careful: an IP address belongs to a network connection, not to one device. Everyone on the same Wi-Fi shares one address, and mobile operators give the same address to many people. Check the visits of an address in the visit log first, and prefer a temporary block.', 'bani-honar-toolkit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bhtk-form bhtk-form--settings">
				<input type="hidden" name="action" value="bhtk_visits_block" />
				<?php wp_nonce_field( 'bhtk_visits_block', '_bhtk_nonce' ); ?>
				<div class="bhtk-field">
					<label for="bhtk_block_target"><?php esc_html_e( 'IP address or range', 'bani-honar-toolkit' ); ?></label>
					<input type="text" id="bhtk_block_target" name="target" class="bhtk-ltr" maxlength="64" required value="<?php echo esc_attr( $prefill ); ?>" placeholder="203.0.113.57" />
					<small><?php esc_html_e( 'One address, or an IPv4 range such as 198.51.100.0/24.', 'bani-honar-toolkit' ); ?></small>
				</div>
				<div class="bhtk-field">
					<label for="bhtk_block_note"><?php esc_html_e( 'Note (optional)', 'bani-honar-toolkit' ); ?></label>
					<input type="text" id="bhtk_block_note" name="note" maxlength="120" />
				</div>
				<div class="bhtk-field">
					<label for="bhtk_block_for"><?php esc_html_e( 'Block for', 'bani-honar-toolkit' ); ?></label>
					<select id="bhtk_block_for" name="duration">
						<option value="1h"><?php esc_html_e( '1 hour', 'bani-honar-toolkit' ); ?></option>
						<option value="24h"><?php esc_html_e( '24 hours', 'bani-honar-toolkit' ); ?></option>
						<option value="7d"><?php esc_html_e( '7 days', 'bani-honar-toolkit' ); ?></option>
						<option value="30d"><?php esc_html_e( '30 days', 'bani-honar-toolkit' ); ?></option>
						<option value="forever"><?php esc_html_e( 'Until I remove it', 'bani-honar-toolkit' ); ?></option>
					</select>
				</div>
				<p><button type="submit" class="bhtk-btn bhtk-btn--danger bhtk-btn--inline"><?php esc_html_e( 'Block', 'bani-honar-toolkit' ); ?></button></p>
			</form>
		</section>

		<section class="bhtk-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'Blocked addresses', 'bani-honar-toolkit' ); ?></h2>
			<?php if ( ! $list ) : ?>
				<div class="bhtk-empty"><?php esc_html_e( 'No address is blocked.', 'bani-honar-toolkit' ); ?></div>
			<?php else : ?>
				<table class="bhtk-table bhtk-table--visits">
					<thead>
						<tr>
							<th><?php esc_html_e( 'IP address', 'bani-honar-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Note', 'bani-honar-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Blocked since', 'bani-honar-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Blocked until', 'bani-honar-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Stored page views', 'bani-honar-toolkit' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'bani-honar-toolkit' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $list as $target => $entry ) : ?>
						<?php
						$until   = isset( $entry['until'] ) ? (int) $entry['until'] : 0;
						$expired = $until && $until <= $now;
						?>
						<tr<?php echo $expired ? ' class="is-expired"' : ''; ?>>
							<td data-label="<?php esc_attr_e( 'IP address', 'bani-honar-toolkit' ); ?>"><a class="bhtk-ltr bhtk-iplink" href="<?php echo esc_url( add_query_arg( array( 'page' => BHTK_Module_Visitor_Stats::PAGE, 'view' => 'log', 'range' => 'all', 'ip' => rawurlencode( (string) $target ) ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( (string) $target ); ?></a></td>
							<td data-label="<?php esc_attr_e( 'Note', 'bani-honar-toolkit' ); ?>"><?php echo esc_html( isset( $entry['note'] ) ? (string) $entry['note'] : '' ); ?></td>
							<td data-label="<?php esc_attr_e( 'Blocked since', 'bani-honar-toolkit' ); ?>" class="bhtk-nowrap bhtk-ltr"><?php echo esc_html( BHTK_Calendar::datetime( gmdate( 'Y-m-d H:i:s', isset( $entry['time'] ) ? (int) $entry['time'] : $now ), self::$mode ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'Blocked until', 'bani-honar-toolkit' ); ?>" class="bhtk-nowrap bhtk-ltr">
								<?php
								if ( ! $until ) {
									esc_html_e( 'Until I remove it', 'bani-honar-toolkit' );
								} elseif ( $expired ) {
									esc_html_e( 'Expired', 'bani-honar-toolkit' );
								} else {
									echo esc_html( BHTK_Calendar::datetime( gmdate( 'Y-m-d H:i:s', $until ), self::$mode ) );
								}
								?>
							</td>
							<td data-label="<?php esc_attr_e( 'Stored page views', 'bani-honar-toolkit' ); ?>"><?php echo esc_html( isset( $counts[ (string) $target ] ) ? self::num( $counts[ (string) $target ] ) : '-' ); ?></td>
							<td class="bhtk-actions">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bhtk-inline-form">
									<input type="hidden" name="action" value="bhtk_visits_unblock" />
									<input type="hidden" name="target" value="<?php echo esc_attr( (string) $target ); ?>" />
									<?php wp_nonce_field( 'bhtk_visits_unblock', '_bhtk_nonce' ); ?>
									<button type="submit" class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm"><?php esc_html_e( 'Unblock', 'bani-honar-toolkit' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<section class="bhtk-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'Good to know', 'bani-honar-toolkit' ); ?></h2>
			<ul class="bhtk-list">
				<li><?php esc_html_e( 'Blocking stops requests that reach WordPress. A page that a CDN or a cache plugin hands out without asking WordPress is not stopped; for a real attack, block the address in your CDN or firewall too.', 'bani-honar-toolkit' ); ?></li>
				<li><?php esc_html_e( 'If your website is not behind a CDN or proxy, choose "Only the direct connection" in Settings, so that nobody can fake an address and get someone else blocked.', 'bani-honar-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Your own address and administrators who are logged in are never blocked. If you ever lock yourself out, add define( \'BHTK_NO_IP_BLOCK\', true ); to wp-config.php.', 'bani-honar-toolkit' ); ?></li>
			</ul>
		</section>
		<?php
	}

	// ---------------------------------------------------------------------
	// Settings.
	// ---------------------------------------------------------------------

	/**
	 * Settings and data tools.
	 *
	 * @param BHTK_Module $module    The module.
	 * @param bool        $has_table Whether the table exists.
	 * @return void
	 */
	private static function render_settings( $module, $has_table ) {
		$s = $module->get_settings();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bhtk_save_module" />
			<input type="hidden" name="bhtk_module" value="<?php echo esc_attr( $module->get_id() ); ?>" />
			<input type="hidden" name="bhtk_page" value="<?php echo esc_attr( BHTK_Module_Visitor_Stats::PAGE ); ?>" />
			<input type="hidden" name="bhtk_view" value="settings" />
			<?php wp_nonce_field( 'bhtk_save_module_' . $module->get_id(), '_bhtk_nonce' ); ?>

			<section class="bhtk-card">
				<h2 class="bhtk-card__title"><?php esc_html_e( 'What is counted', 'bani-honar-toolkit' ); ?></h2>
				<div class="bhtk-form bhtk-form--settings">
					<?php
					self::check( $module, $s, 'ignore_admins', __( 'Do not count administrators who are logged in', 'bani-honar-toolkit' ), __( 'Recommended, so your own work on the website does not change the numbers.', 'bani-honar-toolkit' ) );
					self::check( $module, $s, 'ignore_users', __( 'Do not count any logged-in user', 'bani-honar-toolkit' ), __( 'Editors, customers and other members are skipped too.', 'bani-honar-toolkit' ) );
					self::check( $module, $s, 'respect_dnt', __( 'Respect the "Do Not Track" browser setting', 'bani-honar-toolkit' ), __( 'Visitors whose browser sends this signal are not counted.', 'bani-honar-toolkit' ) );
					?>
					<div class="bhtk-field bhtk-field--full">
						<label for="bhtk_exclude_ips"><?php esc_html_e( 'IP addresses that are never counted', 'bani-honar-toolkit' ); ?></label>
						<textarea id="bhtk_exclude_ips" class="bhtk-ltr" rows="4" name="<?php echo esc_attr( $module->field_name( 'exclude_ips' ) ); ?>" placeholder="203.0.113.7&#10;198.51.100.0/24"><?php echo esc_textarea( $s['exclude_ips'] ); ?></textarea>
						<small><?php esc_html_e( 'One address per line, for example your office. IPv4 ranges such as 198.51.100.0/24 work too.', 'bani-honar-toolkit' ); ?></small>
					</div>
				</div>
			</section>

			<section class="bhtk-card">
				<h2 class="bhtk-card__title"><?php esc_html_e( 'IP address and country', 'bani-honar-toolkit' ); ?></h2>
				<div class="bhtk-notice-box"><?php esc_html_e( 'An IP address can count as personal data in some countries. If your visitors are protected by such a law, mention the counting in your privacy policy, or tick the option that hides the last part of every address.', 'bani-honar-toolkit' ); ?></div>
				<div class="bhtk-form bhtk-form--settings">
					<?php self::check( $module, $s, 'anonymize_ip', __( 'Hide the last part of every IP address', 'bani-honar-toolkit' ), __( '203.0.113.57 is saved as 203.0.113.0. Countries are then taken from your CDN only, and cannot be looked up online later.', 'bani-honar-toolkit' ) ); ?>

					<div class="bhtk-field">
						<label for="bhtk_ip_source"><?php esc_html_e( 'Where to read the visitor address', 'bani-honar-toolkit' ); ?></label>
						<select id="bhtk_ip_source" name="<?php echo esc_attr( $module->field_name( 'ip_source' ) ); ?>">
							<option value="auto" <?php selected( $s['ip_source'], 'auto' ); ?>><?php esc_html_e( 'Automatic (works behind Cloudflare and other proxies)', 'bani-honar-toolkit' ); ?></option>
							<option value="remote_addr" <?php selected( $s['ip_source'], 'remote_addr' ); ?>><?php esc_html_e( 'Only the direct connection (cannot be faked)', 'bani-honar-toolkit' ); ?></option>
						</select>
						<small><?php esc_html_e( 'If every visitor shows the same address, your site is behind a proxy: use Automatic.', 'bani-honar-toolkit' ); ?></small>
					</div>

					<div class="bhtk-field">
						<label for="bhtk_geo_provider"><?php esc_html_e( 'Online country lookup service', 'bani-honar-toolkit' ); ?></label>
						<select id="bhtk_geo_provider" name="<?php echo esc_attr( $module->field_name( 'geo_provider' ) ); ?>">
							<option value="auto" <?php selected( $s['geo_provider'], 'auto' ); ?>><?php esc_html_e( 'Automatic (tries the services below in turn)', 'bani-honar-toolkit' ); ?></option>
							<?php foreach ( BHTK_Geo::providers() as $id => $provider ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $s['geo_provider'], $id ); ?>><?php echo esc_html( $provider['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<?php self::check( $module, $s, 'geo_online', __( 'Find the country online when the server does not know it (sends visitor IP addresses to an outside service)', 'bani-honar-toolkit' ), __( 'Off by default. The country is first read from your CDN (Cloudflare, ArvanCloud and others) and from earlier visits of the same address. When you switch this on and that fails, the visitor IP address is sent once to the lookup service chosen above (ipwho.is or GeoJS). Mention this in your privacy policy. Left off, every address stays on your server and the country shows as unknown unless your CDN provides it.', 'bani-honar-toolkit' ) ); ?>
				</div>
			</section>

			<section class="bhtk-card">
				<h2 class="bhtk-card__title"><?php esc_html_e( 'Storage and dates', 'bani-honar-toolkit' ); ?></h2>
				<div class="bhtk-form bhtk-form--settings">
					<div class="bhtk-field">
						<label for="bhtk_retention"><?php esc_html_e( 'Keep visits for (days)', 'bani-honar-toolkit' ); ?></label>
						<input type="number" id="bhtk_retention" min="0" max="3650" name="<?php echo esc_attr( $module->field_name( 'retention' ) ); ?>" value="<?php echo absint( $s['retention'] ); ?>" />
						<small><?php esc_html_e( 'Older visits are deleted automatically. 0 keeps everything.', 'bani-honar-toolkit' ); ?></small>
					</div>
					<div class="bhtk-field">
						<label for="bhtk_calendar"><?php esc_html_e( 'Calendar', 'bani-honar-toolkit' ); ?></label>
						<select id="bhtk_calendar" name="<?php echo esc_attr( $module->field_name( 'calendar' ) ); ?>">
							<option value="auto" <?php selected( $s['calendar'], 'auto' ); ?>><?php esc_html_e( 'Automatic (Persian dates when the toolkit is in Persian)', 'bani-honar-toolkit' ); ?></option>
							<option value="jalali" <?php selected( $s['calendar'], 'jalali' ); ?>><?php esc_html_e( 'Persian (Jalali)', 'bani-honar-toolkit' ); ?></option>
							<option value="gregorian" <?php selected( $s['calendar'], 'gregorian' ); ?>><?php esc_html_e( 'Gregorian', 'bani-honar-toolkit' ); ?></option>
						</select>
					</div>
				</div>
			</section>

			<p><button type="submit" class="bhtk-btn bhtk-btn--primary bhtk-btn--inline"><?php esc_html_e( 'Save settings', 'bani-honar-toolkit' ); ?></button></p>
		</form>

		<?php if ( $has_table ) : ?>
			<?php
			$count = BHTK_Visits::count_all();
			$first = BHTK_Visits::first_day();
			?>
			<section class="bhtk-card">
				<h2 class="bhtk-card__title"><?php esc_html_e( 'Your data', 'bani-honar-toolkit' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: 1: number of stored page views, 2: date of the oldest one */
						esc_html__( '%1$s page views are stored, the oldest from %2$s.', 'bani-honar-toolkit' ),
						'<strong>' . esc_html( self::num( $count ) ) . '</strong>',
						'<strong class="bhtk-ltr">' . esc_html( '' !== $first ? BHTK_Calendar::day( $first, self::$mode ) : '-' ) . '</strong>'
					);
					?>
				</p>
				<div class="bhtk-actions-row">
					<?php self::export_form( true ); ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="<?php esc_attr_e( 'Delete ALL stored visits? This cannot be undone.', 'bani-honar-toolkit' ); ?>" class="bhtk-inline-form">
						<input type="hidden" name="action" value="bhtk_visits_clear" />
						<?php wp_nonce_field( 'bhtk_visits_clear', '_bhtk_nonce' ); ?>
						<button type="submit" class="bhtk-btn bhtk-btn--danger"><?php esc_html_e( 'Delete all visits', 'bani-honar-toolkit' ); ?></button>
					</form>
				</div>
			</section>
		<?php endif; ?>
		<?php
	}

	/**
	 * One tick-box field.
	 *
	 * @param BHTK_Module $module Module.
	 * @param array       $s      Settings.
	 * @param string      $key    Setting key.
	 * @param string      $label  Label.
	 * @param string      $help   Help text.
	 * @return void
	 */
	private static function check( $module, $s, $key, $label, $help ) {
		?>
		<div class="bhtk-field bhtk-field--full">
			<label class="bhtk-check">
				<input type="checkbox" name="<?php echo esc_attr( $module->field_name( $key ) ); ?>" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
			<?php if ( '' !== $help ) : ?>
				<small><?php echo esc_html( $help ); ?></small>
			<?php endif; ?>
		</div>
		<?php
	}
}
