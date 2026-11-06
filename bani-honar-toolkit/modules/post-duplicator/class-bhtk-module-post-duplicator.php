<?php
/**
 * Module: Post Duplicator.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copy posts, pages and any other content with one click, and edit live content safely through a draft.
 */
final class BHTK_Module_Post_Duplicator extends BHTK_Module {

	const PAGE = 'bhtk-clone';

	/**
	 * Module id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'post-duplicator';
	}

	/**
	 * Module name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Post Duplicator', 'bani-honar-toolkit' );
	}

	/**
	 * Short description for the dashboard.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Duplicate posts, pages and custom content with one click or in bulk, and edit live pages safely through a draft that you publish when it is ready.', 'bani-honar-toolkit' );
	}

	/**
	 * Dashboard icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-admin-page';
	}

	/**
	 * Settings. The copy options come from the engine, so both always agree.
	 *
	 * @return array
	 */
	public function default_settings() {
		$copy = BHTK_Cloner::defaults();
		unset( $copy['origin_meta'], $copy['parent_id'], $copy['meta_exclude'], $copy['tax_exclude'], $copy['title_template'] );

		return array_merge(
			$copy,
			array(
				'who'            => 'cap',
				'types_off'      => array(),
				'show_row'       => 1,
				'show_bulk'      => 1,
				'show_bar'       => 1,
				'show_box'       => 1,
				'safe_edit'      => 1,
				'title_template' => '',
				'meta_exclude'   => '',
				'tax_exclude'    => '',
			)
		);
	}

	/**
	 * Names of the copy tick-boxes, in the order they are shown.
	 *
	 * @return array key => array( label, help ).
	 */
	public function copy_fields() {
		return array(
			'copy_content'    => array( __( 'Content', 'bani-honar-toolkit' ), '' ),
			'copy_excerpt'    => array( __( 'Excerpt', 'bani-honar-toolkit' ), '' ),
			'copy_thumbnail'  => array( __( 'Featured image', 'bani-honar-toolkit' ), '' ),
			'copy_taxonomies' => array( __( 'Categories, tags and other taxonomies', 'bani-honar-toolkit' ), '' ),
			'copy_meta'       => array( __( 'Custom fields', 'bani-honar-toolkit' ), __( 'Page builders such as Elementor keep their layout in custom fields. Leave this on for them.', 'bani-honar-toolkit' ) ),
			'copy_template'   => array( __( 'Page template', 'bani-honar-toolkit' ), '' ),
			'copy_format'     => array( __( 'Post format', 'bani-honar-toolkit' ), '' ),
			'copy_menu_order' => array( __( 'Order number', 'bani-honar-toolkit' ), '' ),
			'copy_comments'   => array( __( 'Comments', 'bani-honar-toolkit' ), '' ),
			'copy_children'   => array( __( 'Child pages', 'bani-honar-toolkit' ), __( 'For pages with sub-pages: the sub-pages are copied too, as children of the copy (up to 100 posts).', 'bani-honar-toolkit' ) ),
			'copy_password'   => array( __( 'Password', 'bani-honar-toolkit' ), '' ),
			'copy_slug'       => array( __( 'Address (slug)', 'bani-honar-toolkit' ), __( 'A number is added when the address is taken.', 'bani-honar-toolkit' ) ),
			'copy_date'       => array( __( 'Publish date', 'bani-honar-toolkit' ), '' ),
			'copy_author'     => array( __( 'Author', 'bani-honar-toolkit' ), __( 'Off: the copy belongs to the person who copies.', 'bani-honar-toolkit' ) ),
			'keep_status'     => array( __( 'Status', 'bani-honar-toolkit' ), __( 'Off: the copy is always a draft. People who may not publish get "pending review" instead.', 'bani-honar-toolkit' ) ),
		);
	}

	/**
	 * Clean the submitted settings.
	 *
	 * @param array $raw Submitted values.
	 * @return array
	 */
	public function sanitize_settings( $raw ) {
		$flag = static function ( $key ) use ( $raw ) {
			return empty( $raw[ $key ] ) ? 0 : 1;
		};

		$out = array();
		foreach ( array_keys( $this->copy_fields() ) as $key ) {
			$out[ $key ] = $flag( $key );
		}
		foreach ( array( 'show_row', 'show_bulk', 'show_bar', 'show_box', 'safe_edit' ) as $key ) {
			$out[ $key ] = $flag( $key );
		}

		$who         = isset( $raw['who'] ) && is_scalar( $raw['who'] ) ? sanitize_key( (string) $raw['who'] ) : 'cap';
		$out['who']  = in_array( $who, array( 'cap', 'editors', 'admins' ), true ) ? $who : 'cap';

		// Ticked boxes are the types that are on. The ones not ticked are remembered, so types added later start switched on.
		$on  = isset( $raw['types'] ) && is_array( $raw['types'] ) ? array_map( 'sanitize_key', array_map( 'strval', $raw['types'] ) ) : array();
		$all = array_keys( BHTK_Cloner::eligible_types() );
		$out['types_off'] = array_values( array_diff( $all, $on ) );

		$template = isset( $raw['title_template'] ) && is_scalar( $raw['title_template'] ) ? sanitize_text_field( (string) $raw['title_template'] ) : '';
		$out['title_template'] = mb_substr( $template, 0, 120 );

		$meta = array();
		foreach ( preg_split( '/[\r\n,]+/', isset( $raw['meta_exclude'] ) && is_scalar( $raw['meta_exclude'] ) ? (string) $raw['meta_exclude'] : '' ) as $key ) {
			$key = trim( $key );
			if ( '' !== $key && preg_match( '/^[A-Za-z0-9_\-\*]{1,80}$/', $key ) ) {
				$meta[] = $key;
			}
		}
		$out['meta_exclude'] = implode( "\n", array_slice( array_values( array_unique( $meta ) ), 0, 100 ) );

		$tax = array();
		foreach ( preg_split( '/[\r\n,]+/', isset( $raw['tax_exclude'] ) && is_scalar( $raw['tax_exclude'] ) ? (string) $raw['tax_exclude'] : '' ) as $key ) {
			$key = strtolower( trim( $key ) );
			if ( '' !== $key && preg_match( '/^[a-z0-9_\-]{1,32}$/', $key ) ) {
				$tax[] = $key;
			}
		}
		$out['tax_exclude'] = implode( "\n", array_slice( array_values( array_unique( $tax ) ), 0, 50 ) );

		return $out;
	}

	/**
	 * The settings live on the module's own tab.
	 *
	 * @return bool
	 */
	public function has_settings() {
		return false;
	}

	/**
	 * The tab stays reachable while the module is off, because its switch is on the tab.
	 *
	 * @return bool
	 */
	public function always_show_pages() {
		return true;
	}

	/**
	 * Admin pages.
	 *
	 * @return array
	 */
	public function admin_pages() {
		return array(
			array(
				'slug'     => self::PAGE,
				'title'    => __( 'Post Duplicator', 'bani-honar-toolkit' ),
				'callback' => array( $this, 'render_page' ),
			),
		);
	}

	/**
	 * The options the engine uses for a normal copy, built from the settings.
	 *
	 * @return array
	 */
	public function clone_options() {
		$s    = $this->get_settings();
		$opts = array();
		foreach ( array_keys( $this->copy_fields() ) as $key ) {
			$opts[ $key ] = (int) $s[ $key ];
		}
		$opts['title_template'] = (string) $s['title_template'];
		$opts['meta_exclude']   = array_filter( array_map( 'trim', explode( "\n", (string) $s['meta_exclude'] ) ) );
		$opts['tax_exclude']    = array_filter( array_map( 'trim', explode( "\n", (string) $s['tax_exclude'] ) ) );
		return $opts;
	}

	/**
	 * Start the interface: buttons, links, handlers.
	 *
	 * @return void
	 */
	public function boot() {
		$ui = new BHTK_Clone_UI( $this );
		$ui->register();
	}

	/**
	 * Remove the copy marks from posts when all toolkit data is deleted.
	 *
	 * @return void
	 */
	public static function delete_marks() {
		foreach ( array( BHTK_Cloner::META_ORIGIN, BHTK_Cloner::META_FORK, BHTK_Cloner::META_OPEN, BHTK_Cloner::META_FORK_TIME ) as $key ) {
			delete_post_meta_by_key( $key );
		}
	}

	// ---------------------------------------------------------------------
	// The tab.
	// ---------------------------------------------------------------------

	/**
	 * The Post Duplicator tab: switch, settings and the list of open drafts.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to open this page.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}

		$admin = BHTK_Admin::instance();
		$on    = BHTK_Plugin::instance()->is_enabled( $this->get_id() );

		$admin->render_header( self::PAGE );
		$admin->render_flash();
		$admin->render_module_switch( $this, self::PAGE );

		if ( ! $on ) {
			echo '<div class="bhtk-alert bhtk-alert--warn">' . esc_html__( 'Post Duplicator is off. You can still change the settings below; they are saved and start working when you switch it on.', 'bani-honar-toolkit' ) . '</div>';
		}
		?>
		<section class="bhtk-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'How it works', 'bani-honar-toolkit' ); ?></h2>
			<ul class="bhtk-list">
				<li><strong><?php esc_html_e( 'Duplicate', 'bani-honar-toolkit' ); ?></strong> - <?php esc_html_e( 'a link under every title in the lists, in the bulk actions menu, in the admin bar and in the editor. The copy is a draft.', 'bani-honar-toolkit' ); ?></li>
				<li><strong><?php esc_html_e( 'Edit safely', 'bani-honar-toolkit' ); ?></strong> - <?php esc_html_e( 'for a page that is already live: you work on a hidden draft, visitors keep seeing the current page, and one click puts your changes live. The address, date and comments stay as they are, and the old text is saved as a revision.', 'bani-honar-toolkit' ); ?></li>
				<li><strong><?php esc_html_e( 'Safe by design', 'bani-honar-toolkit' ); ?></strong> - <?php esc_html_e( 'backslashes in blocks and Elementor data are kept exactly, edit locks and page-builder caches are not copied, and if the live page was changed after you started the draft you are asked before anything is overwritten.', 'bani-honar-toolkit' ); ?></li>
			</ul>
		</section>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="bhtk_save_module" />
			<input type="hidden" name="bhtk_module" value="<?php echo esc_attr( $this->get_id() ); ?>" />
			<input type="hidden" name="bhtk_page" value="<?php echo esc_attr( self::PAGE ); ?>" />
			<?php wp_nonce_field( 'bhtk_save_module_' . $this->get_id(), '_bhtk_nonce' ); ?>

			<div class="<?php echo $on ? '' : 'is-dim'; ?>">
				<?php $this->render_settings(); ?>
			</div>

			<p><button type="submit" class="bhtk-btn bhtk-btn--primary bhtk-btn--inline"><?php esc_html_e( 'Save settings', 'bani-honar-toolkit' ); ?></button></p>
		</form>

		<?php $this->render_open_drafts(); ?>
		<?php
		$admin->render_footer();
	}

	/**
	 * The settings cards.
	 *
	 * @return void
	 */
	public function render_settings() {
		$s     = $this->get_settings();
		$types = BHTK_Cloner::eligible_types();
		$off   = (array) $s['types_off'];
		?>
		<section class="bhtk-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'Who and where', 'bani-honar-toolkit' ); ?></h2>
			<div class="bhtk-form bhtk-form--settings">
				<div class="bhtk-field bhtk-field--full">
					<label for="bhtk_clone_who"><?php esc_html_e( 'Who may duplicate', 'bani-honar-toolkit' ); ?></label>
					<select id="bhtk_clone_who" name="<?php echo esc_attr( $this->field_name( 'who' ) ); ?>">
						<option value="cap" <?php selected( $s['who'], 'cap' ); ?>><?php esc_html_e( 'Everyone who may create this type of content (recommended)', 'bani-honar-toolkit' ); ?></option>
						<option value="editors" <?php selected( $s['who'], 'editors' ); ?>><?php esc_html_e( 'Editors and administrators', 'bani-honar-toolkit' ); ?></option>
						<option value="admins" <?php selected( $s['who'], 'admins' ); ?>><?php esc_html_e( 'Administrators only', 'bani-honar-toolkit' ); ?></option>
					</select>
					<small><?php esc_html_e( 'Nobody can copy something they are not allowed to read.', 'bani-honar-toolkit' ); ?></small>
				</div>

				<div class="bhtk-field bhtk-field--full">
					<label><?php esc_html_e( 'Content types', 'bani-honar-toolkit' ); ?></label>
					<div class="bhtk-checks-grid">
						<?php foreach ( $types as $slug => $label ) : ?>
							<label class="bhtk-check">
								<input type="checkbox" name="<?php echo esc_attr( $this->field_name( 'types' ) ); ?>[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( ! in_array( $slug, $off, true ) ); ?> />
								<?php echo esc_html( $label ); ?> <span class="bhtk-tag bhtk-ltr"><?php echo esc_html( $slug ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<small><?php esc_html_e( 'Products and orders of WooCommerce are not offered: they keep data in extra tables, so use the duplicate button of WooCommerce for them.', 'bani-honar-toolkit' ); ?></small>
				</div>

				<div class="bhtk-field bhtk-field--full">
					<label><?php esc_html_e( 'Show the buttons in', 'bani-honar-toolkit' ); ?></label>
					<div class="bhtk-checks-grid">
						<?php
						$where = array(
							'show_row'  => __( 'The lists, under each title', 'bani-honar-toolkit' ),
							'show_bulk' => __( 'The bulk actions menu of the lists', 'bani-honar-toolkit' ),
							'show_bar'  => __( 'The admin bar', 'bani-honar-toolkit' ),
							'show_box'  => __( 'A box in the editor', 'bani-honar-toolkit' ),
						);
						foreach ( $where as $key => $label ) :
							?>
							<label class="bhtk-check">
								<input type="checkbox" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</section>

		<section class="bhtk-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'What a copy contains', 'bani-honar-toolkit' ); ?></h2>
			<div class="bhtk-form bhtk-form--settings">
				<div class="bhtk-field bhtk-field--full">
					<div class="bhtk-checks-grid">
						<?php foreach ( $this->copy_fields() as $key => $info ) : ?>
							<label class="bhtk-check" <?php echo '' !== $info[1] ? 'title="' . esc_attr( $info[1] ) . '"' : ''; ?>>
								<input type="checkbox" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="1" <?php checked( ! empty( $s[ $key ] ) ); ?> />
								<?php echo esc_html( $info[0] ); ?>
							</label>
						<?php endforeach; ?>
					</div>
					<small><?php esc_html_e( 'Move the mouse over an option for a short explanation.', 'bani-honar-toolkit' ); ?></small>
				</div>

				<div class="bhtk-field bhtk-field--full">
					<label for="bhtk_clone_title"><?php esc_html_e( 'Title of the copy', 'bani-honar-toolkit' ); ?></label>
					<input type="text" id="bhtk_clone_title" class="bhtk-ltr" name="<?php echo esc_attr( $this->field_name( 'title_template' ) ); ?>" maxlength="120" value="<?php echo esc_attr( $s['title_template'] ); ?>" placeholder="<?php echo esc_attr( BHTK_Cloner::default_title_template() ); ?>" />
					<small><?php esc_html_e( 'Use {title} for the original title, {n} for the number of the copy and {date} for today. Leave empty for the standard pattern.', 'bani-honar-toolkit' ); ?></small>
				</div>
			</div>
		</section>

		<section class="bhtk-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'Edit safely', 'bani-honar-toolkit' ); ?></h2>
			<div class="bhtk-form bhtk-form--settings">
				<div class="bhtk-field bhtk-field--full">
					<label class="bhtk-check">
						<input type="checkbox" name="<?php echo esc_attr( $this->field_name( 'safe_edit' ) ); ?>" value="1" <?php checked( ! empty( $s['safe_edit'] ) ); ?> />
						<?php esc_html_e( 'Offer "Edit safely" for pages that are live', 'bani-honar-toolkit' ); ?>
					</label>
					<small><?php esc_html_e( 'Makes a hidden draft of the live page. Visitors see the live page until you press "Publish into the live page". A draft that is open at the time can be seen below.', 'bani-honar-toolkit' ); ?></small>
				</div>
			</div>
		</section>

		<section class="bhtk-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'Advanced', 'bani-honar-toolkit' ); ?></h2>
			<div class="bhtk-form bhtk-form--settings">
				<div class="bhtk-field">
					<label for="bhtk_clone_meta"><?php esc_html_e( 'Custom fields that are never copied', 'bani-honar-toolkit' ); ?></label>
					<textarea id="bhtk_clone_meta" class="bhtk-ltr" rows="4" name="<?php echo esc_attr( $this->field_name( 'meta_exclude' ) ); ?>"><?php echo esc_textarea( $s['meta_exclude'] ); ?></textarea>
					<small>
						<?php esc_html_e( 'One name per line. A star stands for any characters, for example _my_plugin_*.', 'bani-honar-toolkit' ); ?>
						<?php esc_html_e( 'Always skipped:', 'bani-honar-toolkit' ); ?>
						<span class="bhtk-ltr"><?php echo esc_html( implode( ', ', BHTK_Cloner::default_meta_exclude() ) ); ?></span>
					</small>
				</div>
				<div class="bhtk-field">
					<label for="bhtk_clone_tax"><?php esc_html_e( 'Taxonomies that are never copied', 'bani-honar-toolkit' ); ?></label>
					<textarea id="bhtk_clone_tax" class="bhtk-ltr" rows="4" name="<?php echo esc_attr( $this->field_name( 'tax_exclude' ) ); ?>"><?php echo esc_textarea( $s['tax_exclude'] ); ?></textarea>
					<small>
						<?php esc_html_e( 'One name per line, for example post_tag.', 'bani-honar-toolkit' ); ?>
						<?php esc_html_e( 'Always skipped:', 'bani-honar-toolkit' ); ?>
						<span class="bhtk-ltr"><?php echo esc_html( implode( ', ', BHTK_Cloner::default_tax_exclude() ) ); ?></span>
					</small>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Safe-edit drafts that are open right now.
	 *
	 * @return void
	 */
	private function render_open_drafts() {
		$forks = get_posts(
			array(
				'post_type'        => array_keys( BHTK_Cloner::eligible_types() ),
				'post_status'      => array( 'draft', 'pending', 'private' ),
				'meta_key'         => BHTK_Cloner::META_FORK, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'posts_per_page'   => 30,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'no_found_rows'    => true,
			)
		);
		?>
		<section class="bhtk-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'Safe-edit drafts in progress', 'bani-honar-toolkit' ); ?></h2>
			<?php if ( ! $forks ) : ?>
				<div class="bhtk-empty"><?php esc_html_e( 'No draft is open.', 'bani-honar-toolkit' ); ?></div>
			<?php else : ?>
				<table class="bhtk-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Draft', 'bani-honar-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Live page', 'bani-honar-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Made', 'bani-honar-toolkit' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'bani-honar-toolkit' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $forks as $fork ) : ?>
						<?php
						$live_id = (int) get_post_meta( $fork->ID, BHTK_Cloner::META_FORK, true );
						$live    = $live_id ? get_post( $live_id ) : null;
						$edit    = get_edit_post_link( $fork->ID, 'raw' );
						?>
						<tr>
							<td><?php echo $edit && current_user_can( 'edit_post', $fork->ID ) ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $fork->post_title ) . '</a>' : esc_html( $fork->post_title ); ?></td>
							<td><?php echo $live ? esc_html( $live->post_title ) : '<em>' . esc_html__( 'Missing', 'bani-honar-toolkit' ) . '</em>'; ?></td>
							<td class="bhtk-nowrap bhtk-ltr"><?php echo esc_html( BHTK_Calendar::datetime( (string) get_post_meta( $fork->ID, BHTK_Cloner::META_FORK_TIME, true ), 'auto' ) ); ?></td>
							<td class="bhtk-actions">
								<?php if ( $edit && current_user_can( 'edit_post', $fork->ID ) ) : ?>
									<a class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Open', 'bani-honar-toolkit' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}
}
