<?php
/**
 * The engine of the Post Duplicator module: copies a post, and merges an edited draft back into a live post.
 *
 * Nothing here prints anything or redirects, so every step can be tested on its own.
 *
 * @package BaniHonarToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copies posts safely.
 *
 * Why it is built this way:
 * - Text goes through wp_slash() before wp_insert_post() and add_post_meta(), because WordPress removes one level
 *   of slashes there. Without it, backslashes in block markup and in page-builder data (Elementor) are damaged.
 * - Terms are copied by id, in their saved order, and the default category is cleared first.
 * - Cache data of page builders, edit locks and anything that must stay unique are never copied.
 * - Copies of children and comments have a hard limit, so one click can never run away.
 */
final class BHTK_Cloner {

	/** On a copy: id of the post it was copied from. */
	const META_ORIGIN = '_bhtk_clone_of';
	/** On a safe-edit draft: id of the live post it will be merged into. */
	const META_FORK = '_bhtk_fork_of';
	/** On a live post: id of its open safe-edit draft. */
	const META_OPEN = '_bhtk_open_fork';
	/** On a safe-edit draft: when it was made (UTC). */
	const META_FORK_TIME = '_bhtk_fork_time';

	/** The most posts one action may create (the post, its children). */
	const MAX_POSTS = 100;
	/** The most comments copied for one post. */
	const MAX_COMMENTS = 1000;
	/** How deep children are followed. */
	const MAX_DEPTH = 5;

	/**
	 * Default behaviour. The module settings override these.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'title_template'  => '',
			'copy_content'    => 1,
			'copy_excerpt'    => 1,
			'copy_thumbnail'  => 1,
			'copy_taxonomies' => 1,
			'copy_meta'       => 1,
			'copy_template'   => 1,
			'copy_format'     => 1,
			'copy_menu_order' => 1,
			'copy_comments'   => 0,
			'copy_children'   => 0,
			'copy_password'   => 0,
			'copy_slug'       => 0,
			'copy_date'       => 0,
			'copy_author'     => 0,
			'keep_status'     => 0,
			'meta_exclude'    => array(),
			'tax_exclude'     => array(),
			'origin_meta'     => 1,
			'parent_id'       => 0,
		);
	}

	/**
	 * Standard title pattern.
	 *
	 * @return string
	 */
	public static function default_title_template() {
		return __( '{title} (Copy)', 'bani-honar-toolkit' );
	}

	/**
	 * Post types that must never be copied by a generic copier.
	 *
	 * WooCommerce keeps product and order data in extra tables, templates and menus belong to the theme.
	 *
	 * @return string[]
	 */
	public static function denied_types() {
		return array(
			'attachment', 'revision', 'nav_menu_item', 'customize_changeset', 'oembed_cache', 'user_request',
			'wp_global_styles', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_font_family', 'wp_font_face',
			'product', 'product_variation', 'shop_order', 'shop_order_refund', 'shop_order_placehold', 'shop_coupon', 'shop_subscription',
			'scheduled-action', 'acf-field', 'acf-field-group', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page',
		);
	}

	/**
	 * Post types that can be copied at all: they have an editing screen and are not on the denied list.
	 *
	 * @return array slug => label.
	 */
	public static function eligible_types() {
		$out    = array();
		$denied = self::denied_types();
		foreach ( (array) get_post_types( array( 'show_ui' => true ), 'objects' ) as $slug => $object ) {
			if ( in_array( $slug, $denied, true ) ) {
				continue;
			}
			$out[ $slug ] = isset( $object->labels->name ) && '' !== $object->labels->name ? $object->labels->name : $slug;
		}
		/**
		 * Change the post types the duplicator can work with.
		 *
		 * @param array $types slug => label.
		 */
		return (array) apply_filters( 'bhtk_clone_types', $out );
	}

	/**
	 * Meta keys that are never copied. A star stands for any characters.
	 *
	 * @return string[]
	 */
	public static function default_meta_exclude() {
		return array(
			'_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_wp_desired_post_slug',
			'_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_trash_meta_comments_status',
			'_pingme', '_encloseme', '_bhtk_*', '_dp_*',
			// Page builder caches: they are made again the first time the copy is viewed.
			'_elementor_css', '_elementor_element_cache', '_elementor_page_assets', '_elementor_controls_usage',
		);
	}

	/**
	 * Taxonomies that are never copied: copying them would put the copy in the translation group of the original.
	 *
	 * @return string[]
	 */
	public static function default_tax_exclude() {
		return array( 'post_translations', 'term_translations' );
	}

	/**
	 * Turn an id or a post into a post. Never falls back to the post of the current page.
	 *
	 * @param WP_Post|int|null $post Id or post.
	 * @return WP_Post|null
	 */
	public static function resolve( $post ) {
		if ( $post instanceof WP_Post ) {
			return $post;
		}
		return is_numeric( $post ) && (int) $post > 0 ? get_post( (int) $post ) : null;
	}

	// ---------------------------------------------------------------------
	// Permissions.
	// ---------------------------------------------------------------------

	/**
	 * May the current user copy this post?
	 *
	 * @param WP_Post|int $post     The post.
	 * @param array       $settings Module settings (who, types_off).
	 * @return true|WP_Error
	 */
	public static function can_clone( $post, $settings ) {
		$post = self::resolve( $post );
		if ( ! $post ) {
			return new WP_Error( 'bhtk_missing', __( 'That post does not exist.', 'bani-honar-toolkit' ) );
		}
		$types = self::enabled_types( $settings );
		if ( ! isset( $types[ $post->post_type ] ) ) {
			return new WP_Error( 'bhtk_type', __( 'This type of content cannot be duplicated.', 'bani-honar-toolkit' ) );
		}
		if ( in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) {
			return new WP_Error( 'bhtk_status', __( 'A post in the trash cannot be duplicated.', 'bani-honar-toolkit' ) );
		}
		$object = get_post_type_object( $post->post_type );
		if ( ! $object || ! current_user_can( $object->cap->create_posts ) ) {
			return new WP_Error( 'bhtk_forbidden', __( 'You are not allowed to create this type of content.', 'bani-honar-toolkit' ) );
		}
		$who = isset( $settings['who'] ) ? $settings['who'] : 'cap';
		if ( 'admins' === $who && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'bhtk_forbidden', __( 'Only administrators may duplicate content.', 'bani-honar-toolkit' ) );
		}
		if ( 'editors' === $who && ! current_user_can( $object->cap->edit_others_posts ) ) {
			return new WP_Error( 'bhtk_forbidden', __( 'Only editors and administrators may duplicate content.', 'bani-honar-toolkit' ) );
		}
		// The content of a protected post is only for people who may edit it.
		if ( '' !== $post->post_password && ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'bhtk_forbidden', __( 'You are not allowed to copy this post.', 'bani-honar-toolkit' ) );
		}
		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'bhtk_forbidden', __( 'You are not allowed to copy this post.', 'bani-honar-toolkit' ) );
		}
		return true;
	}

	/**
	 * May the current user start a safe edit of this post?
	 *
	 * @param WP_Post|int $post     The post.
	 * @param array       $settings Module settings.
	 * @return true|WP_Error
	 */
	public static function can_fork( $post, $settings ) {
		$post = self::resolve( $post );
		if ( ! $post ) {
			return new WP_Error( 'bhtk_missing', __( 'That post does not exist.', 'bani-honar-toolkit' ) );
		}
		if ( empty( $settings['safe_edit'] ) ) {
			return new WP_Error( 'bhtk_off', __( 'Safe edit is switched off.', 'bani-honar-toolkit' ) );
		}
		$types = self::enabled_types( $settings );
		if ( ! isset( $types[ $post->post_type ] ) ) {
			return new WP_Error( 'bhtk_type', __( 'This type of content cannot be duplicated.', 'bani-honar-toolkit' ) );
		}
		if ( ! in_array( $post->post_status, array( 'publish', 'private', 'future' ), true ) ) {
			return new WP_Error( 'bhtk_status', __( 'Safe edit is for posts that are already published.', 'bani-honar-toolkit' ) );
		}
		if ( self::is_fork( $post ) ) {
			return new WP_Error( 'bhtk_is_fork', __( 'This is already a safe-edit draft.', 'bani-honar-toolkit' ) );
		}
		$object = get_post_type_object( $post->post_type );
		if ( ! $object || ! current_user_can( $object->cap->create_posts ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'bhtk_forbidden', __( 'You are not allowed to edit this post.', 'bani-honar-toolkit' ) );
		}
		return true;
	}

	/**
	 * Post types the duplicator is switched on for.
	 *
	 * @param array $settings Module settings.
	 * @return array slug => label.
	 */
	public static function enabled_types( $settings ) {
		$off = isset( $settings['types_off'] ) && is_array( $settings['types_off'] ) ? $settings['types_off'] : array();
		return array_diff_key( self::eligible_types(), array_flip( $off ) );
	}

	// ---------------------------------------------------------------------
	// Copying.
	// ---------------------------------------------------------------------

	/**
	 * Copy a post.
	 *
	 * @param WP_Post|int $post  The post to copy.
	 * @param array       $opts  Options; see defaults().
	 * @param array|null  $stats Filled with 'posts' (how many were created) and 'comments'. Pass the same array down to children.
	 * @param int         $depth How deep in a family of posts this call is.
	 * @return int|WP_Error Id of the new post.
	 */
	public static function clone_post( $post, $opts = array(), &$stats = null, $depth = 0 ) {
		$post = self::resolve( $post );
		if ( ! $post ) {
			return new WP_Error( 'bhtk_missing', __( 'That post does not exist.', 'bani-honar-toolkit' ) );
		}
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}
		$stats = wp_parse_args(
			$stats,
			array(
				'posts'    => 0,
				'comments' => 0,
			)
		);
		if ( $stats['posts'] >= self::MAX_POSTS ) {
			return new WP_Error( 'bhtk_limit', __( 'Too many posts for one action.', 'bani-honar-toolkit' ) );
		}

		$o      = wp_parse_args( $opts, self::defaults() );
		$object = get_post_type_object( $post->post_type );

		// Status: always a draft, unless the owner asked to keep it and the user may publish.
		$status = 'draft';
		if ( $o['keep_status'] ) {
			$status = $post->post_status;
			if ( in_array( $status, array( 'publish', 'future', 'private' ), true ) && $object && ! current_user_can( $object->cap->publish_posts ) ) {
				$status = 'pending';
			}
		}

		// Author: the person who copies, unless the owner asked to keep the author and the user may do that.
		$author = (int) get_current_user_id();
		if ( ( $o['copy_author'] && $object && current_user_can( $object->cap->edit_others_posts ) ) || 0 === $author ) {
			$author = (int) $post->post_author;
		}

		$data = array(
			'post_author'           => $author,
			'post_content'          => $o['copy_content'] ? $post->post_content : '',
			'post_content_filtered' => $o['copy_content'] ? $post->post_content_filtered : '',
			'post_title'            => self::build_title( $post, $o['title_template'] ),
			'post_excerpt'          => $o['copy_excerpt'] ? $post->post_excerpt : '',
			'post_status'           => $status,
			'post_type'             => $post->post_type,
			'comment_status'        => $post->comment_status,
			'ping_status'           => $post->ping_status,
			'post_password'         => $o['copy_password'] ? $post->post_password : '',
			'post_name'             => $o['copy_slug'] ? $post->post_name : '',
			'post_parent'           => $o['parent_id'] ? (int) $o['parent_id'] : (int) $post->post_parent,
			'menu_order'            => $o['copy_menu_order'] ? (int) $post->menu_order : 0,
		);
		if ( $o['copy_date'] ) {
			$data['post_date']     = $post->post_date;
			$data['post_date_gmt'] = $post->post_date_gmt;
		}

		/**
		 * Change the data of the new post before it is saved.
		 *
		 * @param array   $data The data, not slashed.
		 * @param WP_Post $post The post that is copied.
		 * @param array   $o    The options.
		 */
		$data = (array) apply_filters( 'bhtk_clone_post_data', $data, $post, $o );

		/**
		 * Runs just before a post is copied.
		 *
		 * @param WP_Post $post The post that is copied.
		 * @param array   $o    The options.
		 */
		do_action( 'bhtk_before_clone', $post, $o );

		$new_id = wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		$new_id = (int) $new_id;
		++$stats['posts'];

		if ( $o['copy_taxonomies'] ) {
			self::copy_terms( $post, $new_id, $o );
		}
		self::copy_meta( $post->ID, $new_id, $o );
		if ( $o['origin_meta'] ) {
			update_post_meta( $new_id, self::META_ORIGIN, $post->ID );
		}
		update_post_meta( $new_id, '_edit_last', get_current_user_id() );

		if ( $o['copy_comments'] ) {
			$stats['comments'] += self::copy_comments( $post->ID, $new_id );
		}

		if ( $o['copy_children'] && $depth < self::MAX_DEPTH && is_post_type_hierarchical( $post->post_type ) ) {
			$child_opts              = $o;
			$child_opts['parent_id'] = $new_id;
			foreach ( self::children( $post ) as $child ) {
				if ( $stats['posts'] >= self::MAX_POSTS ) {
					break;
				}
				if ( ! current_user_can( 'read_post', $child->ID ) ) {
					continue;
				}
				self::clone_post( $child, $child_opts, $stats, $depth + 1 );
			}
		}

		/**
		 * Runs after a post was copied completely.
		 *
		 * @param int     $new_id Id of the copy.
		 * @param WP_Post $post   The post that was copied.
		 * @param array   $o      The options.
		 */
		do_action( 'bhtk_after_clone', $new_id, $post, $o );

		return $new_id;
	}

	/**
	 * Title of the copy.
	 *
	 * Tokens: {title}, {date} (today) and {n} (the number of this copy of the post).
	 *
	 * @param WP_Post $post     The original.
	 * @param string  $template Pattern; empty uses the standard one.
	 * @return string
	 */
	public static function build_title( $post, $template ) {
		$template = '' === trim( (string) $template ) ? self::default_title_template() : (string) $template;
		if ( false === strpos( $template, '{title}' ) ) {
			$template = '{title} ' . $template;
		}
		$replace = array(
			'{title}' => $post->post_title,
			'{date}'  => wp_date( get_option( 'date_format' ) ),
		);
		if ( false !== strpos( $template, '{n}' ) ) {
			$replace['{n}'] = (string) ( self::copies_of( $post->ID ) + 1 );
		}
		return trim( strtr( $template, $replace ) );
	}

	/**
	 * How many copies of a post exist already.
	 *
	 * @param int $post_id Original.
	 * @return int
	 */
	public static function copies_of( $post_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", self::META_ORIGIN, (string) (int) $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Direct children of the same type, in their order.
	 *
	 * @param WP_Post $post Parent.
	 * @return WP_Post[]
	 */
	private static function children( $post ) {
		return get_posts(
			array(
				'post_parent'      => $post->ID,
				'post_type'        => $post->post_type,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'   => self::MAX_POSTS,
				'orderby'          => 'menu_order ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
			)
		);
	}

	/**
	 * Copy the terms of a post.
	 *
	 * @param WP_Post $from   Source.
	 * @param int     $to     Target post id.
	 * @param array   $o      Options (tax_exclude, copy_format).
	 * @return void
	 */
	public static function copy_terms( $from, $to, $o ) {
		$taxonomies = get_object_taxonomies( $from->post_type );
		// Some plugins add post format support without registering the taxonomy.
		if ( post_type_supports( $from->post_type, 'post-formats' ) && ! in_array( 'post_format', $taxonomies, true ) ) {
			$taxonomies[] = 'post_format';
		}
		$exclude = array_merge( self::default_tax_exclude(), (array) $o['tax_exclude'] );
		if ( empty( $o['copy_format'] ) ) {
			$exclude[] = 'post_format';
		}
		/**
		 * Taxonomies that are not copied.
		 *
		 * @param string[] $exclude Taxonomy names.
		 */
		$exclude = (array) apply_filters( 'bhtk_clone_tax_exclude', $exclude );

		foreach ( array_diff( $taxonomies, $exclude ) as $taxonomy ) {
			$ids = wp_get_object_terms(
				$from->ID,
				$taxonomy,
				array(
					'fields'  => 'ids',
					'orderby' => 'term_order',
				)
			);
			if ( is_wp_error( $ids ) ) {
				continue;
			}
			// Also runs with an empty list: that removes the default category WordPress adds to a new post.
			wp_set_object_terms( $to, array_map( 'intval', $ids ), $taxonomy );
		}
	}

	/**
	 * Which meta keys may be copied.
	 *
	 * @param array $keys All keys of the post.
	 * @param array $o    Options.
	 * @return string[]
	 */
	public static function meta_keys_to_copy( $keys, $o ) {
		$exclude = array_merge( self::default_meta_exclude(), (array) $o['meta_exclude'] );
		if ( empty( $o['copy_thumbnail'] ) ) {
			$exclude[] = '_thumbnail_id';
		}
		if ( empty( $o['copy_template'] ) ) {
			$exclude[] = '_wp_page_template';
		}
		/**
		 * Meta keys that are not copied. A star stands for any characters.
		 *
		 * @param string[] $exclude Keys.
		 */
		$exclude = (array) apply_filters( 'bhtk_clone_meta_exclude', $exclude );

		$only = null;
		if ( empty( $o['copy_meta'] ) ) {
			// Custom fields are off: only the picture and the template may still go along.
			$only = array( '_thumbnail_id', '_wp_page_template' );
		}

		$out = array();
		foreach ( $keys as $key ) {
			$key = (string) $key;
			if ( null !== $only && ! in_array( $key, $only, true ) ) {
				continue;
			}
			if ( self::matches_any( $key, $exclude ) ) {
				continue;
			}
			$out[] = $key;
		}
		return $out;
	}

	/**
	 * Whether a key matches one of the patterns.
	 *
	 * @param string   $key      Meta key.
	 * @param string[] $patterns Exact keys or keys with stars.
	 * @return bool
	 */
	private static function matches_any( $key, $patterns ) {
		foreach ( $patterns as $pattern ) {
			$pattern = (string) $pattern;
			if ( '' === $pattern ) {
				continue;
			}
			if ( false === strpos( $pattern, '*' ) ) {
				if ( $pattern === $key ) {
					return true;
				}
				continue;
			}
			$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#';
			if ( preg_match( $regex, $key ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Copy custom fields.
	 *
	 * @param int   $from Source post id.
	 * @param int   $to   Target post id.
	 * @param array $o    Options.
	 * @return void
	 */
	public static function copy_meta( $from, $to, $o ) {
		$all = get_post_meta( $from );
		if ( ! is_array( $all ) || ! $all ) {
			return;
		}
		foreach ( self::meta_keys_to_copy( array_keys( $all ), $o ) as $key ) {
			// One level of slashes is removed again by add_post_meta(); this keeps backslashes in JSON and block markup intact.
			delete_post_meta( $to, $key );
			foreach ( (array) $all[ $key ] as $value ) {
				add_post_meta( $to, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}
	}

	/**
	 * Copy the comments of a post, with their replies.
	 *
	 * @param int $from Source post id.
	 * @param int $to   Target post id.
	 * @return int Number of comments copied.
	 */
	public static function copy_comments( $from, $to ) {
		$comments = get_comments(
			array(
				'post_id' => $from,
				'status'  => 'all',
				'orderby' => 'comment_ID',
				'order'   => 'ASC',
				'number'  => self::MAX_COMMENTS,
			)
		);
		$map   = array();
		$count = 0;
		foreach ( (array) $comments as $comment ) {
			$parent = (int) $comment->comment_parent;
			$new_id = wp_insert_comment(
				wp_slash(
					array(
						'comment_post_ID'      => $to,
						'comment_author'       => $comment->comment_author,
						'comment_author_email' => $comment->comment_author_email,
						'comment_author_url'   => $comment->comment_author_url,
						'comment_author_IP'    => $comment->comment_author_IP,
						'comment_date'         => $comment->comment_date,
						'comment_date_gmt'     => $comment->comment_date_gmt,
						'comment_content'      => $comment->comment_content,
						'comment_karma'        => $comment->comment_karma,
						'comment_approved'     => $comment->comment_approved,
						'comment_agent'        => $comment->comment_agent,
						'comment_type'         => $comment->comment_type,
						'comment_parent'       => $parent && isset( $map[ $parent ] ) ? $map[ $parent ] : 0,
						'user_id'              => $comment->user_id,
					)
				)
			);
			if ( $new_id ) {
				$map[ (int) $comment->comment_ID ] = (int) $new_id;
				++$count;
			}
		}
		return $count;
	}

	// ---------------------------------------------------------------------
	// Safe edit: a draft of a live post, merged back when it is ready.
	// ---------------------------------------------------------------------

	/**
	 * Whether a post is a safe-edit draft.
	 *
	 * @param WP_Post|int $post Post.
	 * @return bool
	 */
	public static function is_fork( $post ) {
		$post = self::resolve( $post );
		return $post && (int) get_post_meta( $post->ID, self::META_FORK, true ) > 0;
	}

	/**
	 * The open safe-edit draft of a live post, if there is one.
	 *
	 * @param int $post_id Live post.
	 * @return WP_Post|null
	 */
	public static function open_fork( $post_id ) {
		$fork_id = (int) get_post_meta( $post_id, self::META_OPEN, true );
		if ( $fork_id < 1 ) {
			return null;
		}
		$fork = get_post( $fork_id );
		if ( ! $fork || 'trash' === $fork->post_status || (int) get_post_meta( $fork_id, self::META_FORK, true ) !== (int) $post_id ) {
			delete_post_meta( $post_id, self::META_OPEN );
			return null;
		}
		return $fork;
	}

	/**
	 * Make a safe-edit draft of a live post.
	 *
	 * Visitors keep seeing the live post while the draft is being edited.
	 *
	 * @param WP_Post|int $post The live post.
	 * @return int|WP_Error Id of the draft. When a draft is open already, the error carries its id in the data (fork_id).
	 */
	public static function fork( $post ) {
		$post = self::resolve( $post );
		if ( ! $post ) {
			return new WP_Error( 'bhtk_missing', __( 'That post does not exist.', 'bani-honar-toolkit' ) );
		}
		$existing = self::open_fork( $post->ID );
		if ( $existing ) {
			return new WP_Error( 'bhtk_fork_exists', __( 'There is a safe-edit draft of this post already.', 'bani-honar-toolkit' ), array( 'fork_id' => $existing->ID ) );
		}

		$opts = array_merge(
			self::defaults(),
			array(
				'title_template'  => '{title}',
				'copy_author'     => 1,
				'copy_date'       => 1,
				'copy_slug'       => 0,
				'copy_password'   => 1,
				'copy_children'   => 0,
				'copy_comments'   => 0,
				'keep_status'     => 0,
				'origin_meta'     => 0,
				'meta_exclude'    => array(),
				'tax_exclude'     => array(),
			)
		);
		// The draft must be a draft, with the date of the live post kept for later.
		$stats  = array();
		$fork_id = self::clone_post( $post, $opts, $stats );
		if ( is_wp_error( $fork_id ) ) {
			return $fork_id;
		}

		update_post_meta( $fork_id, self::META_FORK, $post->ID );
		update_post_meta( $fork_id, self::META_FORK_TIME, current_time( 'mysql', true ) );
		update_post_meta( $post->ID, self::META_OPEN, $fork_id );
		return $fork_id;
	}

	/**
	 * Whether the live post was changed after the draft was made.
	 *
	 * @param WP_Post $fork Draft.
	 * @param WP_Post $live Live post.
	 * @return bool
	 */
	public static function has_conflict( $fork, $live ) {
		$made = (string) get_post_meta( $fork->ID, self::META_FORK_TIME, true );
		if ( '' === $made ) {
			return false;
		}
		// A few seconds of tolerance: saving the draft right after making it must not count.
		return strtotime( $live->post_modified_gmt . ' UTC' ) > strtotime( $made . ' UTC' ) + 5;
	}

	/**
	 * Put the draft into the live post and remove the draft.
	 *
	 * The live post keeps its address, id, date, status, author and comments. The text before the change is saved as a
	 * revision, so it can be restored from the editor when the post type keeps revisions.
	 *
	 * @param WP_Post|int $fork  The draft.
	 * @param bool        $force Go on even though the live post was changed after the draft was made.
	 * @return int|WP_Error Id of the live post.
	 */
	public static function republish( $fork, $force = false ) {
		$fork = self::resolve( $fork );
		if ( ! $fork ) {
			return new WP_Error( 'bhtk_missing', __( 'That post does not exist.', 'bani-honar-toolkit' ) );
		}
		$live_id = (int) get_post_meta( $fork->ID, self::META_FORK, true );
		if ( $live_id < 1 ) {
			return new WP_Error( 'bhtk_not_fork', __( 'This is not a safe-edit draft.', 'bani-honar-toolkit' ) );
		}
		$live = get_post( $live_id );
		if ( ! $live || in_array( $live->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return new WP_Error( 'bhtk_orphan', __( 'The live post is gone, so there is nothing to publish into. You can keep the draft as a normal post.', 'bani-honar-toolkit' ) );
		}
		if ( ! current_user_can( 'edit_post', $fork->ID ) || ! current_user_can( 'edit_post', $live->ID ) ) {
			return new WP_Error( 'bhtk_forbidden', __( 'You are not allowed to edit this post.', 'bani-honar-toolkit' ) );
		}
		if ( ! $force && self::has_conflict( $fork, $live ) ) {
			return new WP_Error( 'bhtk_conflict', __( 'The live post was changed after this draft was made. Publishing now would overwrite those changes.', 'bani-honar-toolkit' ) );
		}

		// Save the text that is live now, so the change can be undone from the revisions.
		if ( post_type_supports( $live->post_type, 'revisions' ) && function_exists( 'wp_save_post_revision' ) ) {
			wp_save_post_revision( $live->ID );
		}

		$data = array(
			'ID'                    => $live->ID,
			'post_title'            => $fork->post_title,
			'post_content'          => $fork->post_content,
			'post_content_filtered' => $fork->post_content_filtered,
			'post_excerpt'          => $fork->post_excerpt,
			'comment_status'        => $fork->comment_status,
			'ping_status'           => $fork->ping_status,
			'post_password'         => $fork->post_password,
			'menu_order'            => $fork->menu_order,
		);
		// A new parent is taken over, but a post can never become its own parent.
		if ( (int) $fork->post_parent !== (int) $live->ID ) {
			$data['post_parent'] = (int) $fork->post_parent;
		}
		/**
		 * Change the data that is put into the live post.
		 *
		 * @param array   $data The data, not slashed.
		 * @param WP_Post $fork The draft.
		 * @param WP_Post $live The live post.
		 */
		$data = (array) apply_filters( 'bhtk_republish_data', $data, $fork, $live );

		$result = wp_update_post( wp_slash( $data ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$o = self::defaults();
		self::copy_terms( $fork, $live->ID, $o );
		self::replace_meta( $fork->ID, $live->ID, $o );

		// Page builder caches of the live post are made again on the next view.
		foreach ( array( '_elementor_css', '_elementor_element_cache', '_elementor_page_assets' ) as $cache_key ) {
			delete_post_meta( $live->ID, $cache_key );
		}

		delete_post_meta( $live->ID, self::META_OPEN );
		wp_delete_post( $fork->ID, true );

		/**
		 * Runs after a draft was merged into its live post.
		 *
		 * @param int $live_id Live post.
		 * @param int $fork_id The draft that was removed.
		 */
		do_action( 'bhtk_after_republish', $live->ID, $fork->ID );

		return (int) $live->ID;
	}

	/**
	 * Replace the custom fields of the live post with those of the draft.
	 *
	 * New values are written before old keys are removed, so the live post is never left without content.
	 *
	 * @param int   $from Draft.
	 * @param int   $to   Live post.
	 * @param array $o    Options.
	 * @return void
	 */
	private static function replace_meta( $from, $to, $o ) {
		$source = get_post_meta( $from );
		$source = is_array( $source ) ? $source : array();
		$wanted = self::meta_keys_to_copy( array_keys( $source ), $o );
		foreach ( $wanted as $key ) {
			delete_post_meta( $to, $key );
			foreach ( (array) $source[ $key ] as $value ) {
				add_post_meta( $to, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}
		// Fields that the draft no longer has are removed, except the ones that are never copied.
		$current = get_post_meta( $to );
		$current = is_array( $current ) ? $current : array();
		$keep    = array_merge( $wanted, array_diff( array_keys( $current ), self::meta_keys_to_copy( array_keys( $current ), $o ) ) );
		foreach ( array_diff( array_keys( $current ), $keep ) as $key ) {
			delete_post_meta( $to, $key );
		}
	}

	/**
	 * Throw a safe-edit draft away.
	 *
	 * @param WP_Post|int $fork Draft.
	 * @return true|WP_Error
	 */
	public static function discard( $fork ) {
		$fork = self::resolve( $fork );
		if ( ! $fork ) {
			return new WP_Error( 'bhtk_missing', __( 'That post does not exist.', 'bani-honar-toolkit' ) );
		}
		if ( ! self::is_fork( $fork ) ) {
			return new WP_Error( 'bhtk_not_fork', __( 'This is not a safe-edit draft.', 'bani-honar-toolkit' ) );
		}
		if ( ! current_user_can( 'delete_post', $fork->ID ) ) {
			return new WP_Error( 'bhtk_forbidden', __( 'You are not allowed to delete this post.', 'bani-honar-toolkit' ) );
		}
		$live_id = (int) get_post_meta( $fork->ID, self::META_FORK, true );
		wp_delete_post( $fork->ID, true );
		if ( $live_id && (int) get_post_meta( $live_id, self::META_OPEN, true ) === (int) $fork->ID ) {
			delete_post_meta( $live_id, self::META_OPEN );
		}
		return true;
	}

	/**
	 * Turn a draft whose live post is gone into a normal post.
	 *
	 * @param WP_Post|int $fork Draft.
	 * @return void
	 */
	public static function detach( $fork ) {
		$fork = self::resolve( $fork );
		if ( $fork ) {
			delete_post_meta( $fork->ID, self::META_FORK );
			delete_post_meta( $fork->ID, self::META_FORK_TIME );
		}
	}
}
