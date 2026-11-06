<?php
/**
 * Post Duplicator: buttons, links and the handlers behind them.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything the user clicks.
 */
final class BHTK_Clone_UI {

	/**
	 * Module.
	 *
	 * @var BHTK_Module_Post_Duplicator
	 */
	private $module;

	/**
	 * Settings, read once per request.
	 *
	 * @var array
	 */
	private $s;

	/**
	 * Constructor.
	 *
	 * @param BHTK_Module_Post_Duplicator $module The module.
	 */
	public function __construct( $module ) {
		$this->module = $module;
		$this->s      = $module->get_settings();
	}

	/**
	 * Hook everything that is switched on.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_bhtk_clone', array( $this, 'handle_clone' ) );
		add_action( 'admin_post_bhtk_republish', array( $this, 'handle_republish' ) );
		add_action( 'admin_post_bhtk_discard', array( $this, 'handle_discard' ) );
		add_action( 'admin_post_bhtk_detach', array( $this, 'handle_detach' ) );

		add_action( 'admin_notices', array( $this, 'print_notice' ) );
		add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );
		add_action( 'trashed_post', array( $this, 'forget_fork' ) );
		add_action( 'before_delete_post', array( $this, 'forget_fork' ) );

		if ( $this->s['show_row'] ) {
			add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
			add_filter( 'page_row_actions', array( $this, 'row_actions' ), 10, 2 );
		}
		if ( $this->s['show_bulk'] ) {
			// Post types are registered by now, so every list can get the bulk action.
			add_action( 'admin_init', array( $this, 'register_bulk' ) );
		}
		if ( $this->s['show_bar'] ) {
			add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 90 );
		}
		if ( $this->s['show_box'] ) {
			add_action( 'add_meta_boxes', array( $this, 'add_box' ), 10, 2 );
		}
	}

	// ---------------------------------------------------------------------
	// Links.
	// ---------------------------------------------------------------------

	/**
	 * Address of an action, with its nonce.
	 *
	 * @param string $action  clone, republish, discard or detach.
	 * @param int    $post_id Post the action is about.
	 * @param array  $extra   More query values (mode, force).
	 * @return string
	 */
	public static function action_url( $action, $post_id, $extra = array() ) {
		$args = array_merge(
			array(
				'action' => 'bhtk_' . $action,
				'post'   => (int) $post_id,
			),
			$extra
		);
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'bhtk_' . $action . '_' . (int) $post_id, '_bhtk_nonce' );
	}

	/**
	 * The "Duplicate" link and the other links for one post, as arrays of label => url.
	 *
	 * @param WP_Post $post    The post.
	 * @param string  $context list or edit. In the editor the copy is opened right away.
	 * @return array Each item: key, label, url, confirm (optional).
	 */
	private function links_for( $post, $context ) {
		$links = array();

		if ( BHTK_Cloner::is_fork( $post ) ) {
			$links[] = array(
				'key'     => 'republish',
				'label'   => __( 'Publish into the live page', 'bani-honar-toolkit' ),
				'url'     => self::action_url( 'republish', $post->ID ),
				'confirm' => $this->republish_warning( $post ),
			);
			$links[] = array(
				'key'     => 'discard',
				'label'   => __( 'Discard draft', 'bani-honar-toolkit' ),
				'url'     => self::action_url( 'discard', $post->ID ),
				'confirm' => __( 'Throw this draft away? The live page is not changed.', 'bani-honar-toolkit' ),
			);
			return $links;
		}

		if ( true === BHTK_Cloner::can_clone( $post, $this->s ) ) {
			$links[] = array(
				'key'   => 'clone',
				'label' => 'edit' === $context ? __( 'Duplicate and edit the copy', 'bani-honar-toolkit' ) : __( 'Duplicate', 'bani-honar-toolkit' ),
				'url'   => self::action_url( 'clone', $post->ID, array( 'mode' => 'edit' === $context ? 'edit' : 'clone' ) ),
			);
		}
		if ( true === BHTK_Cloner::can_fork( $post, $this->s ) ) {
			$open = BHTK_Cloner::open_fork( $post->ID );
			if ( $open ) {
				$edit = get_edit_post_link( $open->ID, 'raw' );
				if ( $edit ) {
					$links[] = array(
						'key'   => 'open',
						'label' => __( 'Open the draft', 'bani-honar-toolkit' ),
						'url'   => $edit,
					);
				}
			} else {
				$links[] = array(
					'key'   => 'fork',
					'label' => __( 'Edit safely', 'bani-honar-toolkit' ),
					'url'   => self::action_url( 'clone', $post->ID, array( 'mode' => 'fork' ) ),
				);
			}
		}
		return $links;
	}

	/**
	 * Warning shown before a draft replaces the live page.
	 *
	 * @param WP_Post $fork The draft.
	 * @return string
	 */
	private function republish_warning( $fork ) {
		$live_id = (int) get_post_meta( $fork->ID, BHTK_Cloner::META_FORK, true );
		$live    = $live_id ? get_post( $live_id ) : null;
		$text    = __( 'Put this draft live? The live page will be replaced by the draft, and the draft is removed.', 'bani-honar-toolkit' );
		if ( $live && ! post_type_supports( $live->post_type, 'revisions' ) ) {
			$text .= ' ' . __( 'This type of content keeps no revisions, so the old text cannot be restored afterwards.', 'bani-honar-toolkit' );
		}
		return $text;
	}

	// ---------------------------------------------------------------------
	// Where the links appear.
	// ---------------------------------------------------------------------

	/**
	 * Links under the titles in the lists.
	 *
	 * @param array   $actions Existing links.
	 * @param WP_Post $post    The post.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( ! $post instanceof WP_Post ) {
			return $actions;
		}
		foreach ( $this->links_for( $post, 'list' ) as $link ) {
			$confirm = isset( $link['confirm'] ) ? ' onclick="return confirm(' . esc_attr( wp_json_encode( $link['confirm'] ) ) . ');"' : '';
			$actions[ 'bhtk_' . $link['key'] ] = sprintf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( $link['url'] ),
				$confirm,
				esc_html( $link['label'] )
			);
		}
		return $actions;
	}

	/**
	 * Bulk action in every list.
	 *
	 * @return void
	 */
	public function register_bulk() {
		foreach ( array_keys( BHTK_Cloner::enabled_types( $this->s ) ) as $type ) {
			add_filter( "bulk_actions-edit-{$type}", array( $this, 'bulk_menu' ) );
			add_filter( "handle_bulk_actions-edit-{$type}", array( $this, 'bulk_handle' ), 10, 3 );
		}
	}

	/**
	 * Add "Duplicate" to the bulk actions menu.
	 *
	 * @param array $actions Menu.
	 * @return array
	 */
	public function bulk_menu( $actions ) {
		$actions['bhtk_clone'] = __( 'Duplicate', 'bani-honar-toolkit' );
		return $actions;
	}

	/**
	 * Copy the ticked posts.
	 *
	 * WordPress has checked the bulk nonce and the user's right to use the list before this runs.
	 *
	 * @param string $redirect Where to go next.
	 * @param string $action   Chosen action.
	 * @param array  $ids      Ticked post ids.
	 * @return string
	 */
	public function bulk_handle( $redirect, $action, $ids ) {
		if ( 'bhtk_clone' !== $action ) {
			return $redirect;
		}
		$done   = 0;
		$failed = 0;
		$opts   = $this->module->clone_options();
		foreach ( array_slice( array_map( 'absint', (array) $ids ), 0, BHTK_Cloner::MAX_POSTS ) as $id ) {
			if ( true !== BHTK_Cloner::can_clone( $id, $this->s ) ) {
				++$failed;
				continue;
			}
			$stats  = array();
			$new_id = BHTK_Cloner::clone_post( $id, $opts, $stats );
			if ( is_wp_error( $new_id ) ) {
				++$failed;
			} else {
				$done += max( 1, (int) $stats['posts'] );
			}
		}
		self::notice(
			$failed && ! $done ? 'error' : ( $failed ? 'warning' : 'success' ),
			sprintf(
				/* translators: 1: number of copies made, 2: number of posts that could not be copied */
				__( '%1$s copies were made. %2$s posts could not be copied.', 'bani-honar-toolkit' ),
				number_format_i18n( $done ),
				number_format_i18n( $failed )
			)
		);
		return $redirect;
	}

	/**
	 * Menu in the admin bar.
	 *
	 * @param WP_Admin_Bar $bar The bar.
	 * @return void
	 */
	public function admin_bar( $bar ) {
		$post = null;
		if ( is_admin() ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && 'post' === $screen->base && 'add' !== $screen->action && ! empty( $GLOBALS['post'] ) ) {
				$post = $GLOBALS['post'];
			}
		} elseif ( is_singular() ) {
			$post = get_queried_object();
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$links = $this->links_for( $post, 'edit' );
		if ( ! $links ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'bhtk-dup',
				'title' => '<span class="ab-icon dashicons dashicons-admin-page" style="font:normal 20px/1 dashicons;margin-top:2px"></span><span class="ab-label">' . esc_html__( 'Duplicate', 'bani-honar-toolkit' ) . '</span>',
				'href'  => $links[0]['url'],
			)
		);
		foreach ( $links as $link ) {
			$meta = array();
			if ( isset( $link['confirm'] ) ) {
				$meta['onclick'] = 'return confirm(' . wp_json_encode( $link['confirm'] ) . ');';
			}
			$bar->add_node(
				array(
					'id'     => 'bhtk-dup-' . $link['key'],
					'parent' => 'bhtk-dup',
					'title'  => esc_html( $link['label'] ),
					'href'   => $link['url'],
					'meta'   => $meta,
				)
			);
		}
	}

	/**
	 * Box in the editor.
	 *
	 * @param string       $post_type Post type.
	 * @param WP_Post|null $post      The post.
	 * @return void
	 */
	public function add_box( $post_type, $post = null ) {
		if ( ! $post instanceof WP_Post || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( ! isset( BHTK_Cloner::enabled_types( $this->s )[ $post_type ] ) ) {
			return;
		}
		add_meta_box( 'bhtk_clone_box', __( 'Duplicate', 'bani-honar-toolkit' ), array( $this, 'render_box' ), $post_type, 'side', 'default' );
	}

	/**
	 * Content of the editor box.
	 *
	 * @param WP_Post $post The post.
	 * @return void
	 */
	public function render_box( $post ) {
		if ( BHTK_Cloner::is_fork( $post ) ) {
			$live_id = (int) get_post_meta( $post->ID, BHTK_Cloner::META_FORK, true );
			$live    = $live_id ? get_post( $live_id ) : null;
			if ( ! $live ) {
				echo '<p>' . esc_html__( 'The live page is gone, so this draft has nothing to publish into.', 'bani-honar-toolkit' ) . '</p>';
				printf( '<p><a class="button" href="%s">%s</a></p>', esc_url( self::action_url( 'detach', $post->ID ) ), esc_html__( 'Keep as a normal post', 'bani-honar-toolkit' ) );
				return;
			}
			echo '<p>';
			printf(
				/* translators: %s: title of the live page */
				esc_html__( 'This is a safe-edit draft of "%s". Visitors still see the live page.', 'bani-honar-toolkit' ),
				'<strong>' . esc_html( $live->post_title ) . '</strong>'
			);
			echo '</p>';
			if ( BHTK_Cloner::has_conflict( $post, $live ) ) {
				echo '<p style="color:#b32d2e">' . esc_html__( 'Careful: the live page was changed after this draft was made. Publishing will ask before it overwrites those changes.', 'bani-honar-toolkit' ) . '</p>';
			}
			printf(
				'<p><a class="button button-primary" href="%1$s" onclick="return confirm(%2$s);">%3$s</a></p>',
				esc_url( self::action_url( 'republish', $post->ID ) ),
				esc_attr( wp_json_encode( $this->republish_warning( $post ) ) ),
				esc_html__( 'Publish into the live page', 'bani-honar-toolkit' )
			);
			printf(
				'<p><a href="%1$s" onclick="return confirm(%2$s);">%3$s</a></p>',
				esc_url( self::action_url( 'discard', $post->ID ) ),
				esc_attr( wp_json_encode( __( 'Throw this draft away? The live page is not changed.', 'bani-honar-toolkit' ) ) ),
				esc_html__( 'Discard draft', 'bani-honar-toolkit' )
			);
			return;
		}

		$shown = false;
		foreach ( $this->links_for( $post, 'edit' ) as $link ) {
			$shown = true;
			printf( '<p><a class="button" href="%1$s">%2$s</a></p>', esc_url( $link['url'] ), esc_html( $link['label'] ) );
			if ( 'fork' === $link['key'] ) {
				echo '<p class="description">' . esc_html__( 'Work on a hidden draft while visitors keep seeing this page. Publish it when it is ready.', 'bani-honar-toolkit' ) . '</p>';
			}
			if ( 'open' === $link['key'] ) {
				echo '<p class="description">' . esc_html__( 'A safe-edit draft of this page is open.', 'bani-honar-toolkit' ) . '</p>';
			}
		}
		$origin = (int) get_post_meta( $post->ID, BHTK_Cloner::META_ORIGIN, true );
		$source = $origin ? get_post( $origin ) : null;
		if ( $source && current_user_can( 'edit_post', $source->ID ) ) {
			echo '<p class="description">' . esc_html__( 'Copied from:', 'bani-honar-toolkit' ) . ' <a href="' . esc_url( (string) get_edit_post_link( $source->ID, 'raw' ) ) . '">' . esc_html( $source->post_title ) . '</a></p>';
		}
		if ( ! $shown && ! $source ) {
			echo '<p class="description">' . esc_html__( 'You cannot duplicate this post.', 'bani-honar-toolkit' ) . '</p>';
		}
	}

	/**
	 * Labels next to the title in the lists.
	 *
	 * @param array   $states States.
	 * @param WP_Post $post   The post.
	 * @return array
	 */
	public function post_states( $states, $post ) {
		if ( BHTK_Cloner::is_fork( $post ) ) {
			$states['bhtk_fork'] = __( 'Safe-edit draft', 'bani-honar-toolkit' );
		} elseif ( (int) get_post_meta( $post->ID, BHTK_Cloner::META_OPEN, true ) > 0 && BHTK_Cloner::open_fork( $post->ID ) ) {
			$states['bhtk_open'] = __( 'Draft in progress', 'bani-honar-toolkit' );
		}
		return $states;
	}

	/**
	 * A draft that is trashed or deleted by hand must not block a new one.
	 *
	 * @param int $post_id Post.
	 * @return void
	 */
	public function forget_fork( $post_id ) {
		$live_id = (int) get_post_meta( $post_id, BHTK_Cloner::META_FORK, true );
		if ( $live_id && (int) get_post_meta( $live_id, BHTK_Cloner::META_OPEN, true ) === (int) $post_id ) {
			delete_post_meta( $live_id, BHTK_Cloner::META_OPEN );
		}
	}

	// ---------------------------------------------------------------------
	// Notices.
	// ---------------------------------------------------------------------

	/**
	 * Keep a message for the next admin screen.
	 *
	 * @param string $type    success, warning or error.
	 * @param string $message Plain text.
	 * @param array  $links   Each item: label, url.
	 * @return void
	 */
	public static function notice( $type, $message, $links = array() ) {
		set_transient(
			'bhtk_clone_notice_' . get_current_user_id(),
			array(
				'type'  => in_array( $type, array( 'success', 'warning', 'error' ), true ) ? $type : 'success',
				'msg'   => (string) $message,
				'links' => (array) $links,
			),
			120
		);
	}

	/**
	 * Show and forget the message.
	 *
	 * @return void
	 */
	public function print_notice() {
		$key  = 'bhtk_clone_notice_' . get_current_user_id();
		$note = get_transient( $key );
		if ( ! is_array( $note ) ) {
			return;
		}
		delete_transient( $key );

		echo '<div class="notice notice-' . esc_attr( $note['type'] ) . ' is-dismissible"><p>' . esc_html( $note['msg'] );
		foreach ( (array) $note['links'] as $link ) {
			if ( isset( $link['label'], $link['url'] ) ) {
				echo ' <a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a>';
			}
		}
		echo '</p></div>';
	}

	// ---------------------------------------------------------------------
	// Handlers.
	// ---------------------------------------------------------------------

	/**
	 * Leave with a message.
	 *
	 * @param string $type    Notice type.
	 * @param string $message Message.
	 * @param string $url     Where to go.
	 * @param array  $links   Links for the message.
	 * @return void
	 */
	private function leave( $type, $message, $url, $links = array() ) {
		self::notice( $type, $message, $links );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Where to go back to when an action ends.
	 *
	 * @param WP_Post|null $post Post the action was about.
	 * @return string
	 */
	private function back_url( $post ) {
		$back = wp_get_referer();
		if ( $back ) {
			return $back;
		}
		$type = $post ? $post->post_type : 'post';
		return admin_url( 'post' === $type ? 'edit.php' : 'edit.php?post_type=' . rawurlencode( $type ) );
	}

	/**
	 * Duplicate (mode clone or edit) or start a safe edit (mode fork).
	 *
	 * @return void
	 */
	public function handle_clone() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked on the next line.
		check_admin_referer( 'bhtk_clone_' . $id, '_bhtk_nonce' );

		$mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'clone'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mode = in_array( $mode, array( 'clone', 'edit', 'fork' ), true ) ? $mode : 'clone';
		$post = BHTK_Cloner::resolve( $id );
		$back = $this->back_url( $post );

		if ( 'fork' === $mode ) {
			$allowed = BHTK_Cloner::can_fork( $post, $this->s );
			if ( is_wp_error( $allowed ) ) {
				$this->leave( 'error', $allowed->get_error_message(), $back );
			}
			$new_id = BHTK_Cloner::fork( $post );
			if ( is_wp_error( $new_id ) ) {
				$data = $new_id->get_error_data();
				if ( 'bhtk_fork_exists' === $new_id->get_error_code() && isset( $data['fork_id'] ) ) {
					$this->leave( 'warning', $new_id->get_error_message(), (string) get_edit_post_link( (int) $data['fork_id'], 'raw' ) );
				}
				$this->leave( 'error', $new_id->get_error_message(), $back );
			}
			$this->leave(
				'success',
				__( 'The safe-edit draft is ready. Visitors still see the live page; press "Publish into the live page" when you are done.', 'bani-honar-toolkit' ),
				(string) get_edit_post_link( $new_id, 'raw' )
			);
		}

		$allowed = BHTK_Cloner::can_clone( $post, $this->s );
		if ( is_wp_error( $allowed ) ) {
			$this->leave( 'error', $allowed->get_error_message(), $back );
		}
		$stats  = array();
		$new_id = BHTK_Cloner::clone_post( $post, $this->module->clone_options(), $stats );
		if ( is_wp_error( $new_id ) ) {
			$this->leave( 'error', $new_id->get_error_message(), $back );
		}

		$edit = (string) get_edit_post_link( $new_id, 'raw' );
		$msg  = $stats['posts'] > 1
			? sprintf(
				/* translators: %s: number of posts that were created */
				__( 'The copy was made, with %s posts in all.', 'bani-honar-toolkit' ),
				number_format_i18n( $stats['posts'] )
			)
			: __( 'The copy was made.', 'bani-honar-toolkit' );
		$this->leave(
			'success',
			$msg,
			'edit' === $mode ? $edit : $back,
			'edit' === $mode ? array() : array(
				array(
					'label' => __( 'Edit the copy', 'bani-honar-toolkit' ),
					'url'   => $edit,
				),
			)
		);
	}

	/**
	 * Put a safe-edit draft into the live page.
	 *
	 * @return void
	 */
	public function handle_republish() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked on the next line.
		check_admin_referer( 'bhtk_republish_' . $id, '_bhtk_nonce' );

		$force  = ! empty( $_GET['force'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$fork   = BHTK_Cloner::resolve( $id );
		$edit   = $fork ? (string) get_edit_post_link( $fork->ID, 'raw' ) : admin_url( 'edit.php' );
		$result = BHTK_Cloner::republish( $fork, $force );

		if ( is_wp_error( $result ) ) {
			$links = array();
			if ( 'bhtk_conflict' === $result->get_error_code() ) {
				$links[] = array(
					'label' => __( 'Publish anyway', 'bani-honar-toolkit' ),
					'url'   => self::action_url( 'republish', $id, array( 'force' => 1 ) ),
				);
			}
			if ( 'bhtk_orphan' === $result->get_error_code() ) {
				$links[] = array(
					'label' => __( 'Keep as a normal post', 'bani-honar-toolkit' ),
					'url'   => self::action_url( 'detach', $id ),
				);
			}
			$this->leave( 'bhtk_conflict' === $result->get_error_code() ? 'warning' : 'error', $result->get_error_message(), $edit, $links );
		}

		$live  = get_post( $result );
		$links = array();
		if ( $live && 'publish' === $live->post_status ) {
			$links[] = array(
				'label' => __( 'View', 'bani-honar-toolkit' ),
				'url'   => (string) get_permalink( $live ),
			);
		}
		$msg = __( 'The draft is live now.', 'bani-honar-toolkit' );
		if ( $live && post_type_supports( $live->post_type, 'revisions' ) ) {
			$msg .= ' ' . __( 'The previous text is saved in the revisions of the page.', 'bani-honar-toolkit' );
		}
		$this->leave( 'success', $msg, (string) get_edit_post_link( $result, 'raw' ), $links );
	}

	/**
	 * Throw a safe-edit draft away.
	 *
	 * @return void
	 */
	public function handle_discard() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked on the next line.
		check_admin_referer( 'bhtk_discard_' . $id, '_bhtk_nonce' );

		$live_id = (int) get_post_meta( $id, BHTK_Cloner::META_FORK, true );
		$result  = BHTK_Cloner::discard( $id );
		if ( is_wp_error( $result ) ) {
			$this->leave( 'error', $result->get_error_message(), admin_url( 'edit.php' ) );
		}
		$this->leave(
			'success',
			__( 'The draft was discarded. The live page was not changed.', 'bani-honar-toolkit' ),
			$live_id && get_post( $live_id ) ? (string) get_edit_post_link( $live_id, 'raw' ) : admin_url( 'edit.php' )
		);
	}

	/**
	 * Keep a draft whose live page is gone as a normal post.
	 *
	 * @return void
	 */
	public function handle_detach() {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked on the next line.
		check_admin_referer( 'bhtk_detach_' . $id, '_bhtk_nonce' );

		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			$this->leave( 'error', __( 'You are not allowed to edit this post.', 'bani-honar-toolkit' ), admin_url( 'edit.php' ) );
		}
		BHTK_Cloner::detach( $id );
		$this->leave( 'success', __( 'This post is a normal post now.', 'bani-honar-toolkit' ), (string) get_edit_post_link( $id, 'raw' ) );
	}
}
