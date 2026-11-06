<?php
/**
 * The "Contact us" screen: suggestions, criticism, problem reports and module requests for the IT team.
 *
 * Nothing is sent from the website. Every card offers three ways to write: Gmail in a new tab, the e-mail program of the
 * computer (mailto:) and a button that copies the whole message, because many computers have no e-mail program set up.
 * The person reads and may change the message before sending. No data is collected and no request leaves the server.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact screen.
 */
final class BHTK_Contact {

	const SLUG = 'bhtk-contact';

	/**
	 * Address of the IT team.
	 *
	 * @return string
	 */
	public static function email() {
		return defined( 'BHTK_CONTACT_EMAIL' ) ? (string) BHTK_CONTACT_EMAIL : 'it@banihonarpars.ir';
	}

	/**
	 * The kinds of message, key => label, short help and the first lines of the e-mail.
	 *
	 * @return array
	 */
	public static function kinds() {
		$hello = __( 'Hello IT team,', 'bani-honar-toolkit' );

		return array(
			'suggestion' => array(
				'icon'    => 'dashicons-lightbulb',
				'label'   => __( 'Suggestion', 'bani-honar-toolkit' ),
				'help'    => __( 'An idea to make an existing module better.', 'bani-honar-toolkit' ),
				'subject' => __( 'Suggestion', 'bani-honar-toolkit' ),
				'body'    => array( $hello, '', __( 'My suggestion:', 'bani-honar-toolkit' ), '', '' ),
			),
			'criticism'  => array(
				'icon'    => 'dashicons-megaphone',
				'label'   => __( 'Criticism', 'bani-honar-toolkit' ),
				'help'    => __( 'Tell us honestly what bothered you. We read every message.', 'bani-honar-toolkit' ),
				'subject' => __( 'Criticism', 'bani-honar-toolkit' ),
				'body'    => array( $hello, '', __( 'My criticism:', 'bani-honar-toolkit' ), '', '' ),
			),
			'problem'    => array(
				'icon'    => 'dashicons-warning',
				'label'   => __( 'Report a problem', 'bani-honar-toolkit' ),
				'help'    => __( 'Something is broken or looks wrong. The technical details shown below are added to your e-mail, so we can find the cause faster.', 'bani-honar-toolkit' ),
				'subject' => __( 'Problem report', 'bani-honar-toolkit' ),
				'body'    => array( $hello, '', __( 'The problem:', 'bani-honar-toolkit' ), '', __( 'What I did before it happened:', 'bani-honar-toolkit' ), '', __( 'What I expected:', 'bani-honar-toolkit' ), '', '' ),
			),
			'module'     => array(
				'icon'    => 'dashicons-plus-alt',
				'label'   => __( 'Request a module', 'bani-honar-toolkit' ),
				'help'    => __( 'Describe the feature you need and how you would use it.', 'bani-honar-toolkit' ),
				'subject' => __( 'Module request', 'bani-honar-toolkit' ),
				'body'    => array( $hello, '', __( 'The module I need:', 'bani-honar-toolkit' ), '', __( 'What it should do:', 'bani-honar-toolkit' ), '', '' ),
			),
			'security'   => array(
				'icon'    => 'dashicons-shield-alt',
				'label'   => __( 'Report a security problem', 'bani-honar-toolkit' ),
				'help'    => __( 'Found a security hole? Please do not post it in public. Write to us privately and we will fix it as fast as we can.', 'bani-honar-toolkit' ),
				'subject' => __( 'Security', 'bani-honar-toolkit' ),
				'body'    => array( $hello, '', __( 'I found a security problem:', 'bani-honar-toolkit' ), '', __( 'How to repeat it:', 'bani-honar-toolkit' ), '', '' ),
			),
		);
	}

	/**
	 * Technical details that help to find a problem. No site address, no user data, no visitor data.
	 *
	 * @return array label => value.
	 */
	public static function details() {
		$active = array();
		foreach ( BHTK_Plugin::instance()->get_modules() as $id => $module ) {
			if ( BHTK_Plugin::instance()->is_enabled( $id ) ) {
				$active[] = $id;
			}
		}
		return array(
			'Bani Honar Toolkit' => BHTK_VERSION,
			'WordPress'          => (string) get_bloginfo( 'version' ),
			'PHP'                => PHP_VERSION,
			'Language'           => BHTK_I18n::lang(),
			'Active modules'     => $active ? implode( ', ', $active ) : '-',
		);
	}

	/**
	 * The details as plain text lines.
	 *
	 * @return string
	 */
	public static function details_text() {
		$lines = array();
		foreach ( self::details() as $label => $value ) {
			$lines[] = $label . ': ' . $value;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Subject line for one kind of message.
	 *
	 * @param string $kind Key from kinds().
	 * @return string Empty for an unknown kind.
	 */
	public static function subject( $kind ) {
		$kinds = self::kinds();
		return isset( $kinds[ $kind ] ) ? '[Bani Honar Toolkit ' . BHTK_VERSION . '] ' . $kinds[ $kind ]['subject'] : '';
	}

	/**
	 * Text of the e-mail for one kind of message.
	 *
	 * @param string $kind Key from kinds().
	 * @return string Empty for an unknown kind.
	 */
	public static function body( $kind ) {
		$kinds = self::kinds();
		if ( ! isset( $kinds[ $kind ] ) ) {
			return '';
		}
		$body = implode( "\n", $kinds[ $kind ]['body'] );
		if ( 'problem' === $kind ) {
			$body .= "\n---\n" . self::details_text() . "\n";
		}
		return $body;
	}

	/**
	 * A mailto: address for one kind of message (opens the e-mail program of the computer).
	 *
	 * @param string $kind Key from kinds().
	 * @return string Empty for an unknown kind.
	 */
	public static function mailto( $kind ) {
		if ( '' === self::subject( $kind ) ) {
			return '';
		}
		return 'mailto:' . self::email() . '?subject=' . rawurlencode( self::subject( $kind ) ) . '&body=' . rawurlencode( self::body( $kind ) );
	}

	/**
	 * A link that opens a new message in Gmail (in the browser, no e-mail program needed).
	 *
	 * @param string $kind Key from kinds().
	 * @return string Empty for an unknown kind.
	 */
	public static function gmail( $kind ) {
		if ( '' === self::subject( $kind ) ) {
			return '';
		}
		return 'https://mail.google.com/mail/?view=cm&fs=1&to=' . rawurlencode( self::email() ) . '&su=' . rawurlencode( self::subject( $kind ) ) . '&body=' . rawurlencode( self::body( $kind ) );
	}

	/**
	 * The whole message as plain text, to copy and paste into any e-mail service.
	 *
	 * @param string $kind Key from kinds().
	 * @return string Empty for an unknown kind.
	 */
	public static function plain( $kind ) {
		if ( '' === self::subject( $kind ) ) {
			return '';
		}
		return 'To: ' . self::email() . "\nSubject: " . self::subject( $kind ) . "\n\n" . self::body( $kind );
	}

	/**
	 * Print the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to open this page.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}
		$admin = BHTK_Admin::instance();
		$admin->render_header( self::SLUG );
		?>
		<section class="bhtk-card bhtk-contact">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'Contact the IT team', 'bani-honar-toolkit' ); ?></h2>
			<p class="bhtk-lead"><?php esc_html_e( 'Your voice makes the toolkit better. Send a suggestion or criticism, report a problem, or ask for a new module. Your message goes to the IT team of Bani Honar Advertising Agency.', 'bani-honar-toolkit' ); ?></p>

			<div class="bhtk-contact__mail">
				<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
				<a class="bhtk-ltr" href="<?php echo esc_url( 'mailto:' . self::email() ); ?>"><?php echo esc_html( self::email() ); ?></a>
				<button type="button" class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" data-copy="<?php echo esc_attr( self::email() ); ?>"><?php esc_html_e( 'Copy', 'bani-honar-toolkit' ); ?></button>
			</div>

			<div class="bhtk-contact__grid">
				<?php foreach ( self::kinds() as $key => $kind ) : ?>
					<article class="bhtk-contact__item bhtk-contact__item--<?php echo esc_attr( $key ); ?>">
						<span class="bhtk-module__icon dashicons <?php echo esc_attr( $kind['icon'] ); ?>" aria-hidden="true"></span>
						<h3><?php echo esc_html( $kind['label'] ); ?></h3>
						<p><?php echo esc_html( $kind['help'] ); ?></p>
						<div class="bhtk-contact__actions">
							<a class="bhtk-btn bhtk-btn--primary bhtk-btn--sm" href="<?php echo esc_url( self::gmail( $key ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Write in Gmail', 'bani-honar-toolkit' ); ?></a>
							<a class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" href="<?php echo esc_url( self::mailto( $key ), array( 'mailto', 'http', 'https' ) ); ?>"><?php esc_html_e( 'Mail app', 'bani-honar-toolkit' ); ?></a>
							<button type="button" class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" data-copy="<?php echo esc_attr( self::plain( $key ) ); ?>"><?php esc_html_e( 'Copy message', 'bani-honar-toolkit' ); ?></button>
						</div>
					</article>
				<?php endforeach; ?>
			</div>

			<p class="bhtk-contact__note"><?php esc_html_e( 'Nothing is sent from your website. "Write in Gmail" opens Gmail in a new tab and "Mail app" opens the e-mail program of your computer, both with a ready subject and text that you can read and change before you send it. If neither works, press "Copy message" and send it from any e-mail service to the address above.', 'bani-honar-toolkit' ); ?></p>
		</section>

		<section class="bhtk-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'Details added to a problem report', 'bani-honar-toolkit' ); ?></h2>
			<p class="bhtk-lead"><?php esc_html_e( 'Only these lines are added, and only to a problem report. They contain no website address, no passwords and no visitor data.', 'bani-honar-toolkit' ); ?></p>
			<textarea class="bhtk-ltr bhtk-contact__details" rows="6" readonly="readonly"><?php echo esc_textarea( self::details_text() ); ?></textarea>
			<p><button type="button" class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" data-copy="<?php echo esc_attr( self::details_text() ); ?>"><?php esc_html_e( 'Copy the details', 'bani-honar-toolkit' ); ?></button></p>
		</section>
		<?php
		$admin->render_footer();
	}
}
