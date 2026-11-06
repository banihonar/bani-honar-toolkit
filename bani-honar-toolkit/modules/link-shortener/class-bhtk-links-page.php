<?php
/**
 * Link shortener: admin screen.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints the "Link Shortener" screen.
 */
final class BHTK_Links_Page {

	const PER_PAGE = 20;

	/**
	 * Print the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( BHTK_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to open this page.', 'bani-honar-toolkit' ), '', array( 'response' => 403 ) );
		}

		$settings = BHTK_Links::settings();
		$admin    = BHTK_Admin::instance();

		// Searching and paging only read data, so no nonce is needed.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		list( $rows, $total ) = BHTK_Links::query( $search, $paged, self::PER_PAGE );
		$stats                = BHTK_Links::stats();
		$host                 = wp_parse_url( home_url(), PHP_URL_HOST );
		$post_url             = admin_url( 'admin-post.php' );
		$here                 = add_query_arg( array() );
		$qr_on                = BHTK_Plugin::instance()->is_enabled( 'qr-generator' );

		$admin->render_header( BHTK_Module_Link_Shortener::PAGE );
		$admin->render_flash();

		if ( ! get_option( 'permalink_structure' ) ) {
			echo '<div class="bhtk-alert bhtk-alert--warn">';
			printf(
				/* translators: %s: link format such as ?bhtk=Code */
				esc_html__( 'Permalinks are off in your site, so short links look like %s. For shorter links, choose a structure such as "Post name" in Settings > Permalinks.', 'bani-honar-toolkit' ),
				'<span class="bhtk-ltr">?bhtk=Code</span>'
			);
			echo '</div>';
		}
		?>

		<p class="bhtk-lead">
			<?php esc_html_e( 'Shorten long links with your own domain, for example:', 'bani-honar-toolkit' ); ?>
			<span class="bhtk-ltr bhtk-lead__sample">banihonar.com/Ab33</span>
		</p>

		<section class="bhtk-stats">
			<div class="bhtk-stat">
				<span class="bhtk-stat__label"><?php esc_html_e( 'Links', 'bani-honar-toolkit' ); ?></span>
				<strong class="bhtk-stat__value"><?php echo esc_html( number_format_i18n( $stats['links'] ) ); ?></strong>
			</div>
			<div class="bhtk-stat">
				<span class="bhtk-stat__label"><?php esc_html_e( 'Total clicks', 'bani-honar-toolkit' ); ?></span>
				<strong class="bhtk-stat__value"><?php echo esc_html( number_format_i18n( $stats['clicks'] ) ); ?></strong>
			</div>
			<div class="bhtk-stat">
				<span class="bhtk-stat__label"><?php esc_html_e( 'Your domain', 'bani-honar-toolkit' ); ?></span>
				<strong class="bhtk-stat__value bhtk-stat__value--sm bhtk-ltr"><?php echo esc_html( $host ); ?></strong>
			</div>
		</section>

		<section class="bhtk-card">
			<h2 class="bhtk-card__title"><?php esc_html_e( 'Create a short link', 'bani-honar-toolkit' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="bhtk-form">
				<input type="hidden" name="action" value="bhtk_link_create" />
				<?php wp_nonce_field( 'bhtk_link_create', '_bhtk_nonce' ); ?>

				<div class="bhtk-field bhtk-field--full">
					<label for="bhtk_url"><?php esc_html_e( 'Long address (destination)', 'bani-honar-toolkit' ); ?></label>
					<input type="text" id="bhtk_url" name="bhtk_url" class="bhtk-ltr" inputmode="url" autocomplete="off" placeholder="https://example.com/very/long/page" required />
				</div>

				<div class="bhtk-field">
					<label for="bhtk_length"><?php esc_html_e( 'Number of characters', 'bani-honar-toolkit' ); ?></label>
					<input type="number" id="bhtk_length" name="bhtk_length" min="1" max="<?php echo absint( BHTK_Links::MAX_LEN ); ?>" value="<?php echo absint( $settings['length'] ); ?>" />
					<div class="bhtk-lens" role="group" aria-label="<?php esc_attr_e( 'Suggested lengths', 'bani-honar-toolkit' ); ?>">
						<?php foreach ( array( 1, 4, 6, 8, 10, 12 ) as $n ) : ?>
							<button type="button" class="bhtk-lens__btn" data-len="<?php echo absint( $n ); ?>"><?php echo absint( $n ); ?></button>
						<?php endforeach; ?>
					</div>
					<small><?php esc_html_e( 'Lowercase and uppercase English letters plus numbers, picked at random.', 'bani-honar-toolkit' ); ?></small>
				</div>

				<div class="bhtk-field">
					<label for="bhtk_custom"><?php esc_html_e( 'Custom code', 'bani-honar-toolkit' ); ?> <em><?php esc_html_e( '(optional)', 'bani-honar-toolkit' ); ?></em></label>
					<input type="text" id="bhtk_custom" name="bhtk_custom" class="bhtk-ltr" maxlength="64" pattern="[A-Za-z0-9]*" autocomplete="off" placeholder="Sale2026" />
					<small><?php esc_html_e( 'If you fill this in, the number of characters is ignored.', 'bani-honar-toolkit' ); ?></small>
				</div>

				<div class="bhtk-field">
					<label for="bhtk_note"><?php esc_html_e( 'Note', 'bani-honar-toolkit' ); ?> <em><?php esc_html_e( '(optional)', 'bani-honar-toolkit' ); ?></em></label>
					<input type="text" id="bhtk_note" name="bhtk_note" maxlength="190" placeholder="<?php esc_attr_e( 'For example: autumn TV banner', 'bani-honar-toolkit' ); ?>" />
				</div>

				<div class="bhtk-field bhtk-field--action">
					<button type="submit" class="bhtk-btn bhtk-btn--primary"><?php esc_html_e( 'Create short link', 'bani-honar-toolkit' ); ?></button>
				</div>
			</form>
		</section>

		<section class="bhtk-card">
			<div class="bhtk-card__head">
				<h2 class="bhtk-card__title"><?php esc_html_e( 'Your links', 'bani-honar-toolkit' ); ?></h2>
				<form method="get" class="bhtk-search">
					<input type="hidden" name="page" value="<?php echo esc_attr( BHTK_Module_Link_Shortener::PAGE ); ?>" />
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search code, destination or note', 'bani-honar-toolkit' ); ?>" />
					<button type="submit" class="bhtk-btn bhtk-btn--ghost"><?php esc_html_e( 'Search', 'bani-honar-toolkit' ); ?></button>
				</form>
			</div>

			<?php if ( ! $rows ) : ?>
				<div class="bhtk-empty">
					<?php
					if ( '' !== $search ) {
						esc_html_e( 'Nothing found.', 'bani-honar-toolkit' );
					} else {
						esc_html_e( 'You have not created any links yet. Create the first one with the form above.', 'bani-honar-toolkit' );
					}
					?>
				</div>
			<?php else : ?>
				<table class="bhtk-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Short link', 'bani-honar-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Destination', 'bani-honar-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Clicks', 'bani-honar-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Date', 'bani-honar-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bani-honar-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$row_id  = absint( $row->id );
						$short   = BHTK_Links::short_url( $row->code );
						$display = preg_replace( '#^https?://#i', '', $short );
						?>
						<tr>
							<td data-label="<?php esc_attr_e( 'Short link', 'bani-honar-toolkit' ); ?>">
								<code class="bhtk-short"><?php echo esc_html( $display ); ?></code>
							</td>
							<td data-label="<?php esc_attr_e( 'Destination', 'bani-honar-toolkit' ); ?>">
								<a class="bhtk-target bhtk-ltr" href="<?php echo esc_url( $row->target ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( $row->target ); ?>"><?php echo esc_html( $row->target ); ?></a>
								<?php if ( '' !== $row->note ) : ?>
									<span class="bhtk-note"><?php echo esc_html( $row->note ); ?></span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Clicks', 'bani-honar-toolkit' ); ?>">
								<strong><?php echo esc_html( number_format_i18n( (int) $row->clicks ) ); ?></strong>
								<?php if ( $row->last_click ) : ?>
									<span class="bhtk-note">
										<?php
										printf(
											/* translators: %s: date and time of the last click */
											esc_html__( 'Last: %s', 'bani-honar-toolkit' ),
											esc_html( wp_date( 'Y/m/d H:i', strtotime( $row->last_click . ' UTC' ) ) )
										);
										?>
									</span>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Date', 'bani-honar-toolkit' ); ?>"><?php echo esc_html( wp_date( 'Y/m/d H:i', strtotime( $row->created_at . ' UTC' ) ) ); ?></td>
							<td data-label="<?php esc_attr_e( 'Actions', 'bani-honar-toolkit' ); ?>" class="bhtk-actions">
								<button type="button" class="bhtk-btn bhtk-btn--gold bhtk-btn--sm" data-copy="<?php echo esc_attr( $short ); ?>"><?php esc_html_e( 'Copy', 'bani-honar-toolkit' ); ?></button>
								<?php if ( $qr_on ) : ?>
									<a class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" href="<?php echo esc_url( BHTK_Module_QR_Generator::page_url( $short, 'link' ) ); ?>"><?php esc_html_e( 'QR', 'bani-honar-toolkit' ); ?></a>
								<?php endif; ?>
								<button type="button" class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" data-toggle-edit="<?php echo absint( $row_id ); ?>"><?php esc_html_e( 'Edit', 'bani-honar-toolkit' ); ?></button>
								<form method="post" action="<?php echo esc_url( $post_url ); ?>" data-confirm="<?php esc_attr_e( 'Delete this short link? Links you have already shared will stop working.', 'bani-honar-toolkit' ); ?>">
									<input type="hidden" name="action" value="bhtk_link_delete" />
									<input type="hidden" name="bhtk_id" value="<?php echo absint( $row_id ); ?>" />
									<input type="hidden" name="bhtk_back" value="<?php echo esc_url( $here ); ?>" />
									<input type="hidden" name="_bhtk_nonce" value="<?php echo esc_attr( wp_create_nonce( 'bhtk_link_delete_' . $row_id ) ); ?>" />
									<button type="submit" class="bhtk-btn bhtk-btn--danger bhtk-btn--sm"><?php esc_html_e( 'Delete', 'bani-honar-toolkit' ); ?></button>
								</form>
							</td>
						</tr>
						<tr class="bhtk-row-edit" id="bhtk-edit-<?php echo absint( $row_id ); ?>" hidden>
							<td colspan="5">
								<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="bhtk-inline">
									<input type="hidden" name="action" value="bhtk_link_update" />
									<input type="hidden" name="bhtk_id" value="<?php echo absint( $row_id ); ?>" />
									<input type="hidden" name="bhtk_back" value="<?php echo esc_url( $here ); ?>" />
									<input type="hidden" name="_bhtk_nonce" value="<?php echo esc_attr( wp_create_nonce( 'bhtk_link_update_' . $row_id ) ); ?>" />
									<label><?php esc_html_e( 'Destination', 'bani-honar-toolkit' ); ?>
										<input type="text" name="bhtk_url" class="bhtk-ltr" value="<?php echo esc_attr( $row->target ); ?>" required />
									</label>
									<label><?php esc_html_e( 'Note', 'bani-honar-toolkit' ); ?>
										<input type="text" name="bhtk_note" maxlength="190" value="<?php echo esc_attr( $row->note ); ?>" />
									</label>
									<button type="submit" class="bhtk-btn bhtk-btn--primary bhtk-btn--sm"><?php esc_html_e( 'Save', 'bani-honar-toolkit' ); ?></button>
									<button type="button" class="bhtk-btn bhtk-btn--ghost bhtk-btn--sm" data-toggle-edit="<?php echo absint( $row_id ); ?>"><?php esc_html_e( 'Cancel', 'bani-honar-toolkit' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php
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
			<?php endif; ?>
		</section>
		<?php
		$admin->render_footer();
	}
}
