<?php
/**
 * Local avatars module.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets every user upload a profile picture from the profile screen,
 * with no Gravatar involved.
 */
final class BHTK_Module_Local_Avatars extends BHTK_Module {

	const META_KEY    = 'bhtk_avatar';
	const LEGACY_META = 'bhp_local_avatar';
	const SUBDIR      = 'bhtk-avatars';
	const LEGACY_DIR  = 'bhp-avatars';
	const MAX_BYTES   = 2097152;
	const SIZE        = 300;

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'local-avatars';
	}

	/**
	 * Module name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Profile Pictures', 'bani-honar-toolkit' );
	}

	/**
	 * Module description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Users upload their own profile picture in WordPress. No Gravatar account is needed.', 'bani-honar-toolkit' );
	}

	/**
	 * Dashboard icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-admin-users';
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function default_settings() {
		return array(
			'use_default_picture' => 1,
		);
	}

	/**
	 * Clean the submitted settings.
	 *
	 * @param array $raw Submitted values.
	 * @return array
	 */
	public function sanitize_settings( $raw ) {
		return array(
			'use_default_picture' => empty( $raw['use_default_picture'] ) ? 0 : 1,
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
			<div class="bhtk-field bhtk-field--full">
				<label class="bhtk-check">
					<input type="checkbox" name="<?php echo esc_attr( $this->field_name( 'use_default_picture' ) ); ?>" value="1" <?php checked( $s['use_default_picture'], 1 ); ?> />
					<?php esc_html_e( 'Show a built-in picture instead of Gravatar for users without a photo', 'bani-honar-toolkit' ); ?>
				</label>
				<small><?php esc_html_e( 'When this is off, WordPress falls back to its normal Gravatar behaviour for these users.', 'bani-honar-toolkit' ); ?></small>
			</div>
		</div>
		<?php
	}

	/**
	 * The profile screens need the toolkit CSS and JS.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return bool
	 */
	public function needs_assets( $hook_suffix ) {
		return in_array( $hook_suffix, array( 'profile.php', 'user-edit.php' ), true );
	}

	/**
	 * Replace the avatar everywhere on the site.
	 *
	 * @return void
	 */
	public function boot() {
		add_filter( 'pre_get_avatar_data', array( $this, 'filter_avatar_data' ), 10, 2 );
		add_action( 'delete_user', array( $this, 'delete_avatar_file_for_user' ) );
	}

	/**
	 * Profile screen hooks.
	 *
	 * @return void
	 */
	public function boot_admin() {
		add_action( 'show_user_profile', array( $this, 'render_field' ) );
		add_action( 'edit_user_profile', array( $this, 'render_field' ) );
		add_action( 'user_edit_form_tag', array( $this, 'form_enctype' ) );
		add_action( 'user_profile_update_errors', array( $this, 'handle_save' ), 10, 3 );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Marker class that hides the core Gravatar row.
	 *
	 * @param string $classes Space separated body classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		return $classes . ' bhtk-avatars-on';
	}

	/**
	 * Relative path stored for a user, from the current or the legacy meta key.
	 *
	 * @param int $user_id User id.
	 * @return string Empty string when there is none or it looks unsafe.
	 */
	private function stored_path( $user_id ) {
		foreach ( array( self::META_KEY, self::LEGACY_META ) as $key ) {
			$rel = get_user_meta( $user_id, $key, true );
			if ( ! is_string( $rel ) || '' === $rel || false !== strpos( $rel, '..' ) ) {
				continue;
			}
			if ( 0 === strpos( $rel, self::SUBDIR . '/' ) || 0 === strpos( $rel, self::LEGACY_DIR . '/' ) ) {
				return $rel;
			}
		}
		return '';
	}

	/**
	 * URL of the uploaded picture of a user.
	 *
	 * @param int $user_id User id.
	 * @return string Empty string when the user has none.
	 */
	public function get_user_avatar_url( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return '';
		}
		$rel = $this->stored_path( $user_id );
		if ( '' === $rel ) {
			return '';
		}
		$uploads = wp_upload_dir( null, false );
		if ( ! file_exists( $uploads['basedir'] . '/' . $rel ) ) {
			return '';
		}
		return $uploads['baseurl'] . '/' . $rel;
	}

	/**
	 * Built-in fallback picture.
	 *
	 * @return string
	 */
	private function default_avatar_url() {
		return BHTK_URL . 'assets/img/default-avatar.svg';
	}

	/**
	 * Work out the user id from what get_avatar() receives.
	 *
	 * @param mixed $id_or_email User id, email, user, post or comment.
	 * @return int
	 */
	private function resolve_user_id( $id_or_email ) {
		if ( is_numeric( $id_or_email ) ) {
			return (int) $id_or_email;
		}
		if ( $id_or_email instanceof WP_User ) {
			return (int) $id_or_email->ID;
		}
		if ( $id_or_email instanceof WP_Post ) {
			return (int) $id_or_email->post_author;
		}
		if ( $id_or_email instanceof WP_Comment ) {
			if ( ! empty( $id_or_email->user_id ) ) {
				return (int) $id_or_email->user_id;
			}
			$id_or_email = $id_or_email->comment_author_email;
		}
		if ( is_object( $id_or_email ) && ! empty( $id_or_email->user_id ) ) {
			return (int) $id_or_email->user_id;
		}
		if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
			return $user ? (int) $user->ID : 0;
		}
		return 0;
	}

	/**
	 * Supply the avatar URL before WordPress asks Gravatar.
	 *
	 * @param array $args        Avatar arguments.
	 * @param mixed $id_or_email User id, email, user, post or comment.
	 * @return array
	 */
	public function filter_avatar_data( $args, $id_or_email ) {
		$url = $this->get_user_avatar_url( $this->resolve_user_id( $id_or_email ) );

		if ( '' !== $url ) {
			$args['url']          = $url;
			$args['found_avatar'] = true;
			return $args;
		}

		$settings = $this->get_settings();
		if ( ! empty( $settings['use_default_picture'] ) ) {
			$args['url']          = $this->default_avatar_url();
			$args['found_avatar'] = true;
		}
		return $args;
	}

	/**
	 * Allow file uploads in the profile form.
	 *
	 * @return void
	 */
	public function form_enctype() {
		echo ' enctype="multipart/form-data"';
	}

	/**
	 * Print the upload card on the profile screen.
	 *
	 * @param WP_User $user User being edited.
	 * @return void
	 */
	public function render_field( $user ) {
		$user_id = (int) $user->ID;
		$url     = $this->get_user_avatar_url( $user_id );
		$preview = '' !== $url ? $url : $this->default_avatar_url();

		wp_nonce_field( 'bhtk_avatar_save_' . $user_id, 'bhtk_avatar_nonce' );
		?>
		<h2 class="bhtk-profile-title" dir="<?php echo esc_attr( BHTK_I18n::dir() ); ?>"><?php esc_html_e( 'Profile picture', 'bani-honar-toolkit' ); ?></h2>
		<div class="bhtk-avatar-card" dir="<?php echo esc_attr( BHTK_I18n::dir() ); ?>">
			<img class="bhtk-avatar-card__img" id="bhtk-avatar-preview" src="<?php echo esc_url( $preview ); ?>" data-original="<?php echo esc_url( $preview ); ?>" width="96" height="96" alt="" />
			<div class="bhtk-avatar-card__body">
				<label class="bhtk-avatar-card__label" for="bhtk_avatar"><?php esc_html_e( 'Choose a new picture', 'bani-honar-toolkit' ); ?></label>
				<input type="file" name="bhtk_avatar" id="bhtk_avatar" accept="image/jpeg,image/png,image/gif,image/webp" />
				<p class="bhtk-avatar-card__hint">
					<?php esc_html_e( 'JPG, PNG, GIF or WebP, up to 2 MB. The picture is cropped to a square automatically. After choosing a file, click the profile update button.', 'bani-honar-toolkit' ); ?>
				</p>
				<?php if ( '' !== $url ) : ?>
					<label class="bhtk-check">
						<input type="checkbox" name="bhtk_avatar_remove" value="1" />
						<?php esc_html_e( 'Remove the current picture', 'bani-honar-toolkit' ); ?>
					</label>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save or remove the picture while the profile is saved.
	 *
	 * @param WP_Error $errors Error collection of the profile update.
	 * @param bool     $update Whether this is an update.
	 * @param object   $user   User being saved.
	 * @return void
	 */
	public function handle_save( $errors, $update, $user ) {
		if ( ! $update || empty( $user->ID ) ) {
			return;
		}
		$user_id = (int) $user->ID;

		if ( ! isset( $_POST['bhtk_avatar_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhtk_avatar_nonce'] ) ), 'bhtk_avatar_save_' . $user_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$has_upload = isset( $_FILES['bhtk_avatar']['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $_FILES['bhtk_avatar']['error'];

		if ( $has_upload ) {
			$result = $this->process_upload( $user_id, $_FILES['bhtk_avatar'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated by wp_handle_upload() and the image editor.
			if ( is_wp_error( $result ) ) {
				$errors->add( 'bhtk_avatar_error', $result->get_error_message() );
				return;
			}
			$this->delete_avatar_file_for_user( $user_id );
			update_user_meta( $user_id, self::META_KEY, $result );
			delete_user_meta( $user_id, self::LEGACY_META );
			return;
		}

		if ( ! empty( $_POST['bhtk_avatar_remove'] ) ) {
			$this->delete_avatar_file_for_user( $user_id );
			delete_user_meta( $user_id, self::META_KEY );
			delete_user_meta( $user_id, self::LEGACY_META );
		}
	}

	/**
	 * Validate, store and resize an uploaded picture.
	 *
	 * @param int   $user_id User id.
	 * @param array $file    One entry of $_FILES.
	 * @return string|WP_Error Relative path of the stored file or an error.
	 */
	private function process_upload( $user_id, $file ) {
		$code = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_INI_SIZE === $code || UPLOAD_ERR_FORM_SIZE === $code ) {
			return new WP_Error( 'bhtk_size', __( 'The file is larger than allowed. The maximum is 2 MB.', 'bani-honar-toolkit' ) );
		}
		if ( UPLOAD_ERR_OK !== $code ) {
			return new WP_Error( 'bhtk_upload', __( 'The upload failed. Please try again.', 'bani-honar-toolkit' ) );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return new WP_Error( 'bhtk_size', __( 'The file is larger than 2 MB.', 'bani-honar-toolkit' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
		);

		$namer = static function ( $dir, $name, $ext ) use ( $user_id ) {
			unset( $dir, $name );
			return 'u' . $user_id . '-' . strtolower( wp_generate_password( 10, false, false ) ) . strtolower( $ext );
		};

		add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		$moved = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				'mimes'                    => $mimes,
				'unique_filename_callback' => $namer,
			)
		);
		remove_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );

		if ( isset( $moved['error'] ) ) {
			return new WP_Error( 'bhtk_type', __( 'Only image files (JPG, PNG, GIF or WebP) are allowed.', 'bani-honar-toolkit' ) );
		}

		// Re-save the image: this proves it is a real image and makes it a small square.
		$editor = wp_get_image_editor( $moved['file'] );
		if ( is_wp_error( $editor ) ) {
			wp_delete_file( $moved['file'] );
			return new WP_Error( 'bhtk_image', __( 'The selected file is not a valid image.', 'bani-honar-toolkit' ) );
		}
		$editor->resize( self::SIZE, self::SIZE, true );
		$saved = $editor->save( $moved['file'], $moved['type'] );
		if ( is_wp_error( $saved ) ) {
			wp_delete_file( $moved['file'] );
			return new WP_Error( 'bhtk_image', __( 'The image could not be processed.', 'bani-honar-toolkit' ) );
		}

		return self::SUBDIR . '/' . basename( $moved['file'] );
	}

	/**
	 * Store pictures in their own uploads folder.
	 *
	 * @param array $dirs Upload directory data.
	 * @return array
	 */
	public function filter_upload_dir( $dirs ) {
		$dirs['subdir'] = '/' . self::SUBDIR;
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
		return $dirs;
	}

	/**
	 * Delete the stored file of a user.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public function delete_avatar_file_for_user( $user_id ) {
		$rel = $this->stored_path( (int) $user_id );
		if ( '' === $rel ) {
			return;
		}
		$uploads = wp_upload_dir( null, false );
		$path    = $uploads['basedir'] . '/' . $rel;
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
