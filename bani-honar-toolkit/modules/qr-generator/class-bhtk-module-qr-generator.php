<?php
/**
 * QR code generator module.
 *
 * The code is drawn in the browser, so nothing is sent to an outside service
 * and nothing is stored on the server.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a link or any text into a QR code that can be downloaded as PNG or SVG.
 */
final class BHTK_Module_QR_Generator extends BHTK_Module {

	const PAGE     = 'bhtk-qr';
	const MAX_TEXT = 1000;

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'qr-generator';
	}

	/**
	 * Module name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'QR Code Generator', 'bani-honar-toolkit' );
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Turn a link or any text into a QR code and download it as PNG or SVG.', 'bani-honar-toolkit' );
	}

	/**
	 * Dashboard icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-screenoptions';
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function default_settings() {
		return array(
			'size'   => 512,
			'level'  => 'M',
			'margin' => 2,
		);
	}

	/**
	 * Clean the submitted settings.
	 *
	 * @param array $raw Submitted values.
	 * @return array
	 */
	public function sanitize_settings( $raw ) {
		$size  = isset( $raw['size'] ) ? (int) $raw['size'] : 512;
		$level = isset( $raw['level'] ) ? strtoupper( sanitize_key( $raw['level'] ) ) : 'M';
		return array(
			'size'   => in_array( $size, array( 256, 512, 1024, 2048 ), true ) ? $size : 512,
			'level'  => in_array( $level, array( 'L', 'M', 'Q', 'H' ), true ) ? $level : 'M',
			'margin' => isset( $raw['margin'] ) ? max( 0, min( 10, (int) $raw['margin'] ) ) : 2,
		);
	}

	/**
	 * This module has settings.
	 *
	 * @return bool
	 */
	public function has_settings() {
		return true;
	}

	/**
	 * Settings fields.
	 *
	 * @return void
	 */
	public function render_settings() {
		$s = $this->get_settings();
		?>
		<div class="bhtk-form bhtk-form--settings">
			<div class="bhtk-field">
				<label for="bhtk_qr_def_size"><?php esc_html_e( 'Default image size', 'bani-honar-toolkit' ); ?></label>
				<select id="bhtk_qr_def_size" name="<?php echo esc_attr( $this->field_name( 'size' ) ); ?>">
					<?php foreach ( array( 256, 512, 1024, 2048 ) as $px ) : ?>
						<option value="<?php echo absint( $px ); ?>" <?php selected( (int) $s['size'], $px ); ?>><?php echo esc_html( $px . ' × ' . $px ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="bhtk-field">
				<label for="bhtk_qr_def_level"><?php esc_html_e( 'Default error correction', 'bani-honar-toolkit' ); ?></label>
				<select id="bhtk_qr_def_level" name="<?php echo esc_attr( $this->field_name( 'level' ) ); ?>">
					<?php foreach ( $this->levels() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['level'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="bhtk-field">
				<label for="bhtk_qr_def_margin"><?php esc_html_e( 'Default white border', 'bani-honar-toolkit' ); ?></label>
				<input type="number" id="bhtk_qr_def_margin" name="<?php echo esc_attr( $this->field_name( 'margin' ) ); ?>" min="0" max="10" value="<?php echo absint( $s['margin'] ); ?>" />
				<small><?php esc_html_e( 'Measured in QR squares. At least 2 is recommended so phones can read the code.', 'bani-honar-toolkit' ); ?></small>
			</div>
		</div>
		<?php
	}

	/**
	 * Error correction levels.
	 *
	 * @return array
	 */
	private function levels() {
		return array(
			'L' => __( 'L - Low (smallest code)', 'bani-honar-toolkit' ),
			'M' => __( 'M - Medium (recommended)', 'bani-honar-toolkit' ),
			'Q' => __( 'Q - Quarter', 'bani-honar-toolkit' ),
			'H' => __( 'H - High (best for printing)', 'bani-honar-toolkit' ),
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
				'title'    => __( 'QR Code', 'bani-honar-toolkit' ),
				'callback' => array( $this, 'render_page' ),
			),
		);
	}

	/**
	 * Load the scripts on the QR page only.
	 *
	 * @return void
	 */
	public function boot_admin() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Address of the QR page for a given link or text.
	 *
	 * @param string $text Text to put in the code.
	 * @param string $mode link or text.
	 * @return string
	 */
	public static function page_url( $text, $mode = 'link' ) {
		return add_query_arg(
			array(
				'page'      => self::PAGE,
				'bhtk_mode' => 'text' === $mode ? 'text' : 'link',
				'bhtk_text' => $text,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Values passed in the address, for example from the short link list.
	 *
	 * @return array Keys mode and text.
	 */
	private function incoming() {
		// Nothing is saved or changed by these values; they only pre-fill the form.
		$mode = isset( $_GET['bhtk_mode'] ) ? sanitize_key( wp_unslash( $_GET['bhtk_mode'] ) ) : 'link'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$text = isset( $_GET['bhtk_text'] ) ? sanitize_textarea_field( wp_unslash( $_GET['bhtk_text'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array(
			'mode' => 'text' === $mode ? 'text' : 'link',
			'text' => function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_TEXT ) : substr( $text, 0, self::MAX_TEXT ),
		);
	}

	/**
	 * Enqueue the QR library and the page script.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		unset( $hook_suffix );
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE !== $page ) {
			return;
		}

		wp_enqueue_script( 'bhtk-qrcode', BHTK_URL . 'assets/js/vendor/qrcode.js', array(), '2.0.4', true );
		wp_enqueue_script( 'bhtk-qr', BHTK_URL . 'assets/js/qr.js', array( 'bhtk-qrcode' ), BHTK_VERSION, true );

		$in = $this->incoming();
		$s  = $this->get_settings();
		wp_localize_script(
			'bhtk-qr',
			'bhtkQr',
			array(
				'mode'     => $in['mode'],
				'text'     => $in['text'],
				'size'     => (int) $s['size'],
				'level'    => $s['level'],
				'margin'   => (int) $s['margin'],
				'maxText'  => self::MAX_TEXT,
				'filename' => 'bani-honar-qr',
				'i18n'     => array(
					'empty'       => __( 'Type a link or some text to see the QR code.', 'bani-honar-toolkit' ),
					'tooLong'     => __( 'This text is too long for a QR code at this error-correction level. Shorten it or choose a lower level.', 'bani-honar-toolkit' ),
					'lowContrast' => __( 'The colors are too close or inverted. Some phones may not be able to scan this code. Use a dark code on a light background.', 'bani-honar-toolkit' ),
					'httpAdded'   => __( 'https:// was added in front of the address so phones open it as a link.', 'bani-honar-toolkit' ),
					'copiedImage' => __( 'Image copied ✓', 'bani-honar-toolkit' ),
					'copyFailed'  => __( 'Your browser could not copy the image. Download it instead.', 'bani-honar-toolkit' ),
				),
			)
		);
	}

	/**
	 * Short links the user already made, for the quick-pick list.
	 *
	 * @return array Short URL => label.
	 */
	private function short_links() {
		$plugin = BHTK_Plugin::instance();
		if ( ! $plugin->is_enabled( 'link-shortener' ) || ! class_exists( 'BHTK_Links' ) ) {
			return array();
		}
		list( $rows ) = BHTK_Links::query( '', 1, 50 );
		$list         = array();
		foreach ( $rows as $row ) {
			$url          = BHTK_Links::short_url( $row->code );
			$list[ $url ] = preg_replace( '#^https?://#i', '', $url ) . ( '' !== $row->note ? ' - ' . $row->note : '' );
		}
		return $list;
	}

	/**
	 * The QR code screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to open this page.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		$admin  = BHTK_Admin::instance();
		$in     = $this->incoming();
		$s      = $this->get_settings();
		$shorts = $this->short_links();

		$admin->render_header( self::PAGE );
		?>
		<section class="bhtk-card bhtk-qr" id="bhtk-qr">
			<div class="bhtk-qr__form">
				<h2 class="bhtk-card__title"><?php esc_html_e( 'Create a QR code', 'bani-honar-toolkit' ); ?></h2>

				<div class="bhtk-seg" role="tablist">
					<button type="button" role="tab" class="bhtk-seg__btn <?php echo 'link' === $in['mode'] ? 'is-on' : ''; ?>" data-qr-mode="link"><?php esc_html_e( 'Link', 'bani-honar-toolkit' ); ?></button>
					<button type="button" role="tab" class="bhtk-seg__btn <?php echo 'text' === $in['mode'] ? 'is-on' : ''; ?>" data-qr-mode="text"><?php esc_html_e( 'Text', 'bani-honar-toolkit' ); ?></button>
				</div>

				<div class="bhtk-field" data-qr-pane="link" <?php echo 'link' === $in['mode'] ? '' : 'hidden'; ?>>
					<label for="bhtk_qr_link"><?php esc_html_e( 'Link address', 'bani-honar-toolkit' ); ?></label>
					<input type="text" id="bhtk_qr_link" class="bhtk-ltr" inputmode="url" autocomplete="off" placeholder="https://banihonar.com/Ab33" value="<?php echo 'link' === $in['mode'] ? esc_attr( $in['text'] ) : ''; ?>" />
					<?php if ( $shorts ) : ?>
						<label for="bhtk_qr_pick" class="bhtk-qr__pick-label"><?php esc_html_e( 'Or pick one of your short links', 'bani-honar-toolkit' ); ?></label>
						<select id="bhtk_qr_pick" class="bhtk-ltr">
							<option value=""><?php esc_html_e( '- Choose -', 'bani-honar-toolkit' ); ?></option>
							<?php foreach ( $shorts as $url => $label ) : ?>
								<option value="<?php echo esc_attr( $url ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</div>

				<div class="bhtk-field" data-qr-pane="text" <?php echo 'text' === $in['mode'] ? '' : 'hidden'; ?>>
					<label for="bhtk_qr_text"><?php esc_html_e( 'Text', 'bani-honar-toolkit' ); ?></label>
					<textarea id="bhtk_qr_text" rows="5" maxlength="<?php echo absint( self::MAX_TEXT ); ?>" placeholder="<?php esc_attr_e( 'Any text, a phone number, an address...', 'bani-honar-toolkit' ); ?>"><?php echo 'text' === $in['mode'] ? esc_textarea( $in['text'] ) : ''; ?></textarea>
					<small><?php esc_html_e( 'Up to 1000 characters. Persian text works too.', 'bani-honar-toolkit' ); ?></small>
				</div>

				<div class="bhtk-qr__opts">
					<div class="bhtk-field">
						<label for="bhtk_qr_size"><?php esc_html_e( 'Image size', 'bani-honar-toolkit' ); ?></label>
						<select id="bhtk_qr_size">
							<?php foreach ( array( 256, 512, 1024, 2048 ) as $px ) : ?>
								<option value="<?php echo absint( $px ); ?>" <?php selected( (int) $s['size'], $px ); ?>><?php echo esc_html( $px . ' × ' . $px ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="bhtk-field">
						<label for="bhtk_qr_level"><?php esc_html_e( 'Error correction', 'bani-honar-toolkit' ); ?></label>
						<select id="bhtk_qr_level">
							<?php foreach ( $this->levels() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['level'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="bhtk-field">
						<label for="bhtk_qr_margin"><?php esc_html_e( 'White border', 'bani-honar-toolkit' ); ?></label>
						<input type="number" id="bhtk_qr_margin" min="0" max="10" value="<?php echo absint( $s['margin'] ); ?>" />
					</div>
					<div class="bhtk-field">
						<label for="bhtk_qr_fg"><?php esc_html_e( 'Code color', 'bani-honar-toolkit' ); ?></label>
						<input type="color" id="bhtk_qr_fg" value="#000000" />
					</div>
					<div class="bhtk-field">
						<label for="bhtk_qr_bg"><?php esc_html_e( 'Background color', 'bani-honar-toolkit' ); ?></label>
						<input type="color" id="bhtk_qr_bg" value="#ffffff" />
					</div>
				</div>
			</div>

			<div class="bhtk-qr__preview">
				<div class="bhtk-qr__frame">
					<canvas id="bhtk-qr-canvas" width="512" height="512" role="img" aria-label="<?php esc_attr_e( 'QR code preview', 'bani-honar-toolkit' ); ?>"></canvas>
					<p class="bhtk-qr__msg" id="bhtk-qr-msg" aria-live="polite"></p>
				</div>
				<div class="bhtk-qr__actions">
					<button type="button" class="bhtk-btn bhtk-btn--primary bhtk-btn--sm" id="bhtk-qr-png" disabled><?php esc_html_e( 'Download PNG', 'bani-honar-toolkit' ); ?></button>
					<button type="button" class="bhtk-btn bhtk-btn--gold bhtk-btn--sm" id="bhtk-qr-svg" disabled><?php esc_html_e( 'Download SVG', 'bani-honar-toolkit' ); ?></button>
					<button type="button" class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" id="bhtk-qr-copy" disabled><?php esc_html_e( 'Copy image', 'bani-honar-toolkit' ); ?></button>
				</div>
				<p class="bhtk-note"><?php esc_html_e( 'The code is created in your browser. Nothing is uploaded or stored.', 'bani-honar-toolkit' ); ?></p>
			</div>
		</section>
		<?php
		$admin->render_footer();
	}
}
