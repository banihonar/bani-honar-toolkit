<?php
/** Post Duplicator: engine, handlers and screens. Run once from a clean state. */
require __DIR__ . '/harness.php';
require __DIR__ . '/wpposts.php';

$db = $GLOBALS['wpdb']->raw();
$db->exec( 'DELETE FROM wp_postmeta' );
update_option( 'date_format', 'Y-m-d' );
$GLOBALS['t']['clock'] = strtotime( '2026-10-28 10:00:00 UTC' );

// ---- users and rights, like WordPress roles ----
$GLOBALS['t']['uid'] = 1;
$GLOBALS['t']['cap_fn'] = function ( $cap, $id = null ) {
	$roles = array( 1 => 'admin', 2 => 'author', 3 => 'contributor', 4 => 'editor' );
	$uid   = $GLOBALS['t']['uid'];
	$role  = $roles[ $uid ] ?? 'none';
	if ( 'admin' === $role ) { return true; }
	if ( 'manage_options' === $cap ) { return false; }
	$post  = $id ? get_post( $id ) : null;
	$own   = $post && (int) $post->post_author === $uid;
	if ( 0 === strpos( $cap, 'create_' ) ) { return in_array( $role, array( 'author', 'contributor', 'editor' ), true ); }
	if ( 0 === strpos( $cap, 'publish_' ) ) { return in_array( $role, array( 'author', 'editor' ), true ); }
	if ( 0 === strpos( $cap, 'edit_others_' ) ) { return 'editor' === $role; }
	if ( 'edit_post' === $cap || 'delete_post' === $cap ) {
		if ( ! $post ) { return false; }
		if ( 'editor' === $role ) { return true; }
		if ( 'author' === $role ) { return $own; }
		return $own && 'publish' !== $post->post_status;
	}
	if ( 'read_post' === $cap ) { return $post && ( 'editor' === $role || $own || 'publish' === $post->post_status ); }
	return false;
};
function as_user( $uid ) { $GLOBALS['t']['uid'] = $uid; }

$p = BHTK_Plugin::instance();
$mod = $p->get_modules()['post-duplicator'];
t_ok( 'module is registered', $mod instanceof BHTK_Module_Post_Duplicator );
t_ok( 'module is on by default', $p->is_enabled( 'post-duplicator' ) );
t_ok( 'tab is declared and stays reachable', 'bhtk-clone' === $mod->admin_pages()[0]['slug'] && $mod->always_show_pages() );
$S = $mod->get_settings();
t_ok( 'defaults: copy content/meta/terms on, comments/children/author/status off', $S['copy_content'] && $S['copy_meta'] && $S['copy_taxonomies'] && ! $S['copy_comments'] && ! $S['copy_children'] && ! $S['copy_author'] && ! $S['keep_status'] );
t_ok( 'hooks are registered by boot', ! empty( $GLOBALS['t']['hooks']['admin_post_bhtk_clone'] ) && ! empty( $GLOBALS['t']['hooks']['post_row_actions'] ) && ! empty( $GLOBALS['t']['hooks']['admin_bar_menu'] ) );

// ============ the engine: fidelity ============
$ELEM = '[{"id":"a1","elType":"section","settings":{"css":"a\\/b","txt":"\\u0633\\u0644\\u0627\\u0645 \\"x\\" C:\\\\dir"}}]';
$BLOCK = "<!-- wp:paragraph {\"style\":{\"x\":\"a\\\\b\"}} -->\n<p>C:\\Users\\pc \\n 'single' \"double\" سلام</p>\n<!-- /wp:paragraph -->";
$ser = array( 'k' => "back\\slash 'q' \"d\"", 'n' => array( 1, 2, 3 ) );
$src = t_make(
	array( 'post_title' => 'Original <b>Title</b> \\ "q"', 'post_content' => $BLOCK, 'post_excerpt' => "Ex'cerpt \\", 'post_author' => 4, 'post_name' => 'orig', 'menu_order' => 7, 'post_password' => 'pw1' ),
	array(
		'_elementor_data' => $ELEM, '_elementor_edit_mode' => 'builder', '_elementor_css' => 'cache-css', '_elementor_element_cache' => 'cache-el',
		'_edit_lock' => '123:1', '_thumbnail_id' => '55', '_wp_page_template' => 'full.php', '_dp_original' => '9', '_bhtk_fork_of' => '8', '_bhtk_open_fork' => '77',
		'acf_ser' => $ser, 'plain' => 'a\\b',
	),
	array( 'category' => array( 3, 2 ), 'post_tag' => array( 6, 4 ), 'post_format' => array( 9 ) )
);
add_post_meta( $src, 'multi', wp_slash( 'one' ) ); add_post_meta( $src, 'multi', wp_slash( 'two\\' ) );
t_ok( 'source: elementor json stored byte for byte', get_post_meta( $src, '_elementor_data', true ) === $ELEM );
t_ok( 'source: serialized meta round-trips', get_post_meta( $src, 'acf_ser', true ) === $ser );

as_user( 2 ); // an author copies an editor's published post
$stats = array();
$new = BHTK_Cloner::clone_post( $src, array(), $stats );
t_ok( 'clone returns an id', is_int( $new ) && $new > $src, var_export( $new, true ) );
$n = get_post( $new );
t_ok( 'content with backslashes kept exactly', $n->post_content === $BLOCK, $n->post_content );
t_ok( 'excerpt kept exactly', $n->post_excerpt === "Ex'cerpt \\" );
t_ok( 'elementor data kept byte for byte', get_post_meta( $new, '_elementor_data', true ) === $ELEM, get_post_meta( $new, '_elementor_data', true ) );
t_ok( 'serialized meta copied as the same structure', get_post_meta( $new, 'acf_ser', true ) === $ser );
t_ok( 'plain meta with backslash kept', get_post_meta( $new, 'plain', true ) === 'a\\b' );
t_ok( 'multi-value meta keeps both values in order', get_post_meta( $new, 'multi' ) === array( 'one', 'two\\' ), json_encode( get_post_meta( $new, 'multi' ) ) );
t_ok( 'thumbnail and template copied', '55' === (string) get_post_meta( $new, '_thumbnail_id', true ) && 'full.php' === get_post_meta( $new, '_wp_page_template', true ) );
t_ok( 'builder caches and edit lock not copied', '' === get_post_meta( $new, '_elementor_css', true ) && '' === get_post_meta( $new, '_elementor_element_cache', true ) && '' === get_post_meta( $new, '_edit_lock', true ) );
t_ok( 'other plugins marks not copied (_dp_*, _bhtk_fork_of, _bhtk_open_fork)', '' === get_post_meta( $new, '_dp_original', true ) && '' === get_post_meta( $new, '_bhtk_fork_of', true ) && '' === get_post_meta( $new, '_bhtk_open_fork', true ) );
t_ok( 'origin mark written', (int) get_post_meta( $new, '_bhtk_clone_of', true ) === $src );
t_ok( 'last editor is the copier', 2 === (int) get_post_meta( $new, '_edit_last', true ) );
t_ok( 'terms copied in saved order, default category gone', wp_get_object_terms( $new, 'category' ) === array( 3, 2 ), json_encode( wp_get_object_terms( $new, 'category' ) ) );
t_ok( 'tags copied in order', wp_get_object_terms( $new, 'post_tag' ) === array( 6, 4 ) );
t_ok( 'post format copied even when taxonomy not listed', wp_get_object_terms( $new, 'post_format' ) === array( 9 ) );
t_ok( 'copy is a draft', 'draft' === $n->post_status );
t_ok( 'copy belongs to whoever copies', 2 === (int) $n->post_author );
t_ok( 'copy has no slug yet, no password', '' === $n->post_name && '' === $n->post_password );
t_ok( 'menu order kept', 7 === (int) $n->menu_order );
t_ok( 'default title pattern', 'Original <b>Title</b> \\ "q" (Copy)' === $n->post_title, $n->post_title );
t_ok( 'stats count one post', 1 === $stats['posts'] && 0 === $stats['comments'] );
t_ok( 'original untouched', get_post( $src )->post_content === $BLOCK && 'publish' === get_post( $src )->post_status && get_post_meta( $src, '_elementor_css', true ) === 'cache-css' );

// options off
$o = BHTK_Cloner::clone_post( $src, array( 'copy_content' => 0, 'copy_excerpt' => 0, 'copy_taxonomies' => 0, 'copy_meta' => 0 ) );
$x = get_post( $o );
t_ok( 'content/excerpt off', '' === $x->post_content && '' === $x->post_excerpt );
t_ok( 'taxonomies off: only the default category stays', wp_get_object_terms( $o, 'category' ) === array( 1 ) );
t_ok( 'custom fields off: only picture and template go along', '' === get_post_meta( $o, '_elementor_data', true ) && '55' === (string) get_post_meta( $o, '_thumbnail_id', true ) && 'full.php' === get_post_meta( $o, '_wp_page_template', true ) );
$o = BHTK_Cloner::clone_post( $src, array( 'copy_thumbnail' => 0, 'copy_template' => 0, 'copy_format' => 0, 'copy_menu_order' => 0, 'meta_exclude' => array( 'acf_*', 'plain' ), 'tax_exclude' => array( 'post_tag' ) ) );
t_ok( 'thumbnail/template off, wildcard exclude, taxonomy exclude, format off', '' === get_post_meta( $o, '_thumbnail_id', true ) && '' === get_post_meta( $o, '_wp_page_template', true ) && '' === get_post_meta( $o, 'acf_ser', true ) && '' === get_post_meta( $o, 'plain', true ) && 'builder' === get_post_meta( $o, '_elementor_edit_mode', true ) && array() === wp_get_object_terms( $o, 'post_tag' ) && array() === wp_get_object_terms( $o, 'post_format' ) && 0 === (int) get_post( $o )->menu_order );
// translation taxonomies are never copied
$bk = t_make( array( 'post_type' => 'book', 'post_title' => 'Bk' ), array(), array( 'genre' => array( 7 ), 'post_translations' => array( 8 ) ) );
$bn = BHTK_Cloner::clone_post( $bk );
t_ok( 'translation group not copied, normal taxonomy copied', wp_get_object_terms( $bn, 'genre' ) === array( 7 ) && array() === wp_get_object_terms( $bn, 'post_translations' ) );
// filters
add_filter( 'bhtk_clone_post_data', function ( $d ) { $d['post_title'] .= ' [f]'; return $d; } );
add_filter( 'bhtk_clone_meta_exclude', function ( $e ) { $e[] = 'multi'; return $e; } );
$f = BHTK_Cloner::clone_post( $src );
t_ok( 'filters change data and meta list', substr( get_post( $f )->post_title, -4 ) === ' [f]' && '' === get_post_meta( $f, 'multi', true ) );
$GLOBALS['t']['hooks']['bhtk_clone_post_data'] = array(); $GLOBALS['t']['hooks']['bhtk_clone_meta_exclude'] = array();
// actions
$seen = array(); add_action( 'bhtk_before_clone', function () use ( &$seen ) { $seen[] = 'before'; } ); add_action( 'bhtk_after_clone', function ( $id ) use ( &$seen ) { $seen[] = 'after:' . $id; }, 10, 3 );
$a = BHTK_Cloner::clone_post( $src );
t_ok( 'before/after actions run once, in order', $seen === array( 'before', 'after:' . $a ), json_encode( $seen ) );
$GLOBALS['t']['hooks']['bhtk_before_clone'] = array(); $GLOBALS['t']['hooks']['bhtk_after_clone'] = array();
t_ok( 'missing post is an error, never the current post', is_wp_error( BHTK_Cloner::clone_post( 99999 ) ) && is_wp_error( BHTK_Cloner::clone_post( 0 ) ) && null === BHTK_Cloner::resolve( 0 ) );

// ============ titles ============
$t1 = t_make( array( 'post_title' => 'Plain' ) );
$c1 = BHTK_Cloner::clone_post( $t1, array( 'title_template' => 'Copy {n} of {title}' ) );
$c2 = BHTK_Cloner::clone_post( $t1, array( 'title_template' => 'Copy {n} of {title}' ) );
t_ok( 'title {n} counts the copies of the post', 'Copy 1 of Plain' === get_post( $c1 )->post_title && 'Copy 2 of Plain' === get_post( $c2 )->post_title, get_post( $c1 )->post_title . ' / ' . get_post( $c2 )->post_title );
$c3 = BHTK_Cloner::clone_post( $t1, array( 'title_template' => 'v{n}' ) );
t_ok( 'pattern without {title} gets the title in front', 'Plain v3' === get_post( $c3 )->post_title, get_post( $c3 )->post_title );
$c4 = BHTK_Cloner::clone_post( $t1, array( 'title_template' => '{title} {date}' ) );
t_ok( 'title {date} uses the site date format', 'Plain ' . wp_date( 'Y-m-d' ) === get_post( $c4 )->post_title, get_post( $c4 )->post_title );
t_ok( 'title: a replaced token in the title is not expanded again', 'Brace {n} (Copy)' === BHTK_Cloner::build_title( (object) array( 'post_title' => 'Brace {n}', 'ID' => $t1 ), '' ) );

// ============ status, author, date, slug, password ============
as_user( 1 );
$pub = t_make( array( 'post_title' => 'Pub', 'post_author' => 4, 'post_name' => 'pub-slug', 'post_password' => 'sec', 'post_date' => '2025-01-02 03:04:05' ) );
$k = get_post( BHTK_Cloner::clone_post( $pub, array( 'keep_status' => 1, 'copy_author' => 1, 'copy_slug' => 1, 'copy_date' => 1, 'copy_password' => 1 ) ) );
t_ok( 'admin: status kept, author kept, slug made unique, date and password kept', 'publish' === $k->post_status && 4 === (int) $k->post_author && 'pub-slug-2' === $k->post_name && '2025-01-02 03:04:05' === $k->post_date && 'sec' === $k->post_password, json_encode( $k ) );
as_user( 3 ); // contributor cannot publish and cannot edit others' posts
$pub2 = t_make( array( 'post_title' => 'Pub2', 'post_author' => 4 ) );
$k = get_post( BHTK_Cloner::clone_post( $pub2, array( 'keep_status' => 1, 'copy_author' => 1 ) ) );
t_ok( 'contributor: status downgraded to pending, author stays the contributor', 'pending' === $k->post_status && 3 === (int) $k->post_author, json_encode( $k ) );
as_user( 2 );
$k = get_post( BHTK_Cloner::clone_post( $pub2, array( 'keep_status' => 1, 'copy_author' => 1 ) ) );
t_ok( 'author may publish but not take over the author', 'publish' === $k->post_status && 2 === (int) $k->post_author );
as_user( 4 );
$k = get_post( BHTK_Cloner::clone_post( $pub, array( 'copy_author' => 1 ) ) );
t_ok( 'editor takes over the author', 4 === (int) $k->post_author && 'draft' === $k->post_status );

// ============ permissions ============
$S = $mod->get_settings();
as_user( 1 );
$dr3 = t_make( array( 'post_title' => 'Private draft', 'post_status' => 'draft', 'post_author' => 4 ) );
$tr = t_make( array( 'post_title' => 'Trashed', 'post_status' => 'trash' ) );
$pr = t_make( array( 'post_title' => 'Prod', 'post_type' => 'product' ) );
$at = t_make( array( 'post_title' => 'Img', 'post_type' => 'attachment', 'post_status' => 'inherit' ) );
t_ok( 'admin may copy a normal post', true === BHTK_Cloner::can_clone( $pub, $S ) );
t_ok( 'trashed refused', 'bhtk_status' === BHTK_Cloner::can_clone( $tr, $S )->get_error_code() );
t_ok( 'woocommerce product refused', 'bhtk_type' === BHTK_Cloner::can_clone( $pr, $S )->get_error_code() );
t_ok( 'attachment refused', 'bhtk_type' === BHTK_Cloner::can_clone( $at, $S )->get_error_code() );
t_ok( 'missing refused', 'bhtk_missing' === BHTK_Cloner::can_clone( 424242, $S )->get_error_code() );
as_user( 3 );
t_ok( 'contributor may copy a published post of someone else', true === BHTK_Cloner::can_clone( $pub2, $S ) );
t_ok( 'contributor may NOT copy a password-protected post of someone else', 'bhtk_forbidden' === BHTK_Cloner::can_clone( $pub, $S )->get_error_code() );
t_ok( 'contributor may NOT copy a draft of someone else', 'bhtk_forbidden' === BHTK_Cloner::can_clone( $dr3, $S )->get_error_code() );
as_user( 2 );
t_ok( 'author may NOT copy a password-protected post of someone else', 'bhtk_forbidden' === BHTK_Cloner::can_clone( $pub, $S )->get_error_code() );
$S2 = array_merge( $S, array( 'who' => 'editors' ) ); $S3 = array_merge( $S, array( 'who' => 'admins' ) );
t_ok( 'who=editors: author refused', 'bhtk_forbidden' === BHTK_Cloner::can_clone( $pub2, $S2 )->get_error_code() );
as_user( 4 ); t_ok( 'who=editors: editor allowed', true === BHTK_Cloner::can_clone( $pub2, $S2 ) );
t_ok( 'who=admins: editor refused', 'bhtk_forbidden' === BHTK_Cloner::can_clone( $pub2, $S3 )->get_error_code() );
as_user( 1 ); t_ok( 'who=admins: admin allowed', true === BHTK_Cloner::can_clone( $pub2, $S3 ) );
$S4 = array_merge( $S, array( 'types_off' => array( 'post' ) ) );
t_ok( 'a type that is switched off is refused', 'bhtk_type' === BHTK_Cloner::can_clone( $pub2, $S4 )->get_error_code() );
add_filter( 'bhtk_clone_types', function ( $t ) { unset( $t['book'] ); return $t; } );
t_ok( 'filter can remove a type', 'bhtk_type' === BHTK_Cloner::can_clone( $bk, $S )->get_error_code() );
$GLOBALS['t']['hooks']['bhtk_clone_types'] = array();
t_ok( 'eligible types exclude denied ones', array_keys( BHTK_Cloner::eligible_types() ) === array( 'post', 'page', 'book' ), json_encode( array_keys( BHTK_Cloner::eligible_types() ) ) );

// ============ children and comments ============
as_user( 1 );
$P  = t_make( array( 'post_type' => 'page', 'post_title' => 'Parent', 'post_name' => 'parent' ) );
$C2 = t_make( array( 'post_type' => 'page', 'post_title' => 'Child B', 'post_parent' => $P, 'menu_order' => 2 ), array( '_elementor_data' => $ELEM ) );
$C1 = t_make( array( 'post_type' => 'page', 'post_title' => 'Child A', 'post_parent' => $P, 'menu_order' => 1 ) );
$G  = t_make( array( 'post_type' => 'page', 'post_title' => 'Grand', 'post_parent' => $C1, 'post_status' => 'draft' ) );
$np = BHTK_Cloner::clone_post( $P, array(), $st );
t_ok( 'children not copied by default', 1 === $st['posts'] && array() === get_posts( array( 'post_parent' => $np, 'post_type' => 'page', 'post_status' => array( 'draft', 'publish' ) ) ) );
$st = array();
$np = BHTK_Cloner::clone_post( $P, array( 'copy_children' => 1 ), $st );
$kids = get_posts( array( 'post_parent' => $np, 'post_type' => 'page', 'post_status' => array( 'draft', 'publish' ) ) );
t_ok( 'children copied under the copy, in menu order', 2 === count( $kids ) && 'Child A (Copy)' === $kids[0]->post_title && 'Child B (Copy)' === $kids[1]->post_title, json_encode( array_map( function ( $k ) { return $k->post_title; }, $kids ) ) );
$grand = get_posts( array( 'post_parent' => $kids[0]->ID, 'post_type' => 'page', 'post_status' => array( 'draft', 'publish' ) ) );
t_ok( 'grandchild copied under the copied child', 1 === count( $grand ) && 'Grand (Copy)' === $grand[0]->post_title );
t_ok( 'family count in stats', 4 === $st['posts'], json_encode( $st ) );
t_ok( 'child page builder data kept', get_post_meta( $kids[1]->ID, '_elementor_data', true ) === $ELEM );
t_ok( 'copy of a child alone keeps its parent', (int) get_post( BHTK_Cloner::clone_post( $C1 ) )->post_parent === $P );
// non-hierarchical posts never copy children
$pp = t_make( array( 'post_title' => 'Flat' ) ); t_make( array( 'post_title' => 'Looks like a child', 'post_parent' => $pp ) );
$st = array(); BHTK_Cloner::clone_post( $pp, array( 'copy_children' => 1 ), $st );
t_ok( 'non-hierarchical type: no children', 1 === $st['posts'] );
// limits
$big = t_make( array( 'post_type' => 'page', 'post_title' => 'Big' ) );
for ( $i = 0; $i < 130; $i++ ) { t_make( array( 'post_type' => 'page', 'post_title' => 'k' . $i, 'post_parent' => $big ) ); }
$st = array(); $nb = BHTK_Cloner::clone_post( $big, array( 'copy_children' => 1 ), $st );
t_ok( 'family size is capped at 100', is_int( $nb ) && 100 === $st['posts'], json_encode( $st ) );
$chain = t_make( array( 'post_type' => 'page', 'post_title' => 'L0' ) ); $prev = $chain;
for ( $i = 1; $i <= 9; $i++ ) { $prev = t_make( array( 'post_type' => 'page', 'post_title' => 'L' . $i, 'post_parent' => $prev ) ); }
$st = array(); BHTK_Cloner::clone_post( $chain, array( 'copy_children' => 1 ), $st );
t_ok( 'depth is capped (root + 5 levels)', 6 === $st['posts'], json_encode( $st ) );
// comments
$cm = t_make( array( 'post_title' => 'Commented' ) );
$c_a = wp_insert_comment( wp_slash( array( 'comment_post_ID' => $cm, 'comment_author' => "O'Neil", 'comment_content' => 'one \\ back', 'comment_parent' => 0, 'comment_approved' => '1', 'user_id' => 0 ) ) );
$c_b = wp_insert_comment( wp_slash( array( 'comment_post_ID' => $cm, 'comment_author' => 'B', 'comment_content' => 'reply', 'comment_parent' => $c_a, 'comment_approved' => '0', 'user_id' => 0 ) ) );
$st = array(); $cn = BHTK_Cloner::clone_post( $cm, array( 'copy_comments' => 1 ), $st );
$cc = get_comments( array( 'post_id' => $cn ) );
t_ok( 'comments copied with their replies', 2 === count( $cc ) && 2 === $st['comments'] && (int) $cc[1]->comment_parent === (int) $cc[0]->comment_ID && (int) $cc[0]->comment_ID !== $c_a );
t_ok( 'comment text kept exactly, approval kept', "O'Neil" === $cc[0]->comment_author && 'one \\ back' === $cc[0]->comment_content && '0' === (string) $cc[1]->comment_approved );
t_ok( 'comments not copied by default', array() === get_comments( array( 'post_id' => BHTK_Cloner::clone_post( $cm ) ) ) );

// ============ safe edit ============
as_user( 4 );
update_option( 'bhtk_dummy', 1 );
$live = t_make(
	array( 'post_title' => 'Live page', 'post_content' => 'Old text \\ C:\\x', 'post_name' => 'live-page', 'post_author' => 2, 'post_date' => '2025-05-05 05:05:05', 'post_type' => 'page', 'post_password' => '' ),
	array( '_elementor_data' => $ELEM, '_elementor_css' => 'old-css', '_elementor_page_assets' => 'a', 'gone_later' => 'x', 'kept_edit_lock' => 'k', '_edit_lock' => 'live-lock', '_bhtk_clone_of' => '12' ),
	array()
);
wp_save_post_revision( $live );
$comment_on_live = wp_insert_comment( array( 'comment_post_ID' => $live, 'comment_content' => 'visitor comment', 'comment_approved' => '1' ) );
$S = $mod->get_settings();
t_ok( 'can_fork on a live page', true === BHTK_Cloner::can_fork( $live, $S ) );
t_ok( 'can_fork refuses drafts', 'bhtk_status' === BHTK_Cloner::can_fork( $dr3, $S )->get_error_code() );
t_ok( 'can_fork refuses when switched off', 'bhtk_off' === BHTK_Cloner::can_fork( $live, array_merge( $S, array( 'safe_edit' => 0 ) ) )->get_error_code() );
as_user( 3 ); t_ok( 'can_fork refuses someone who cannot edit it', 'bhtk_forbidden' === BHTK_Cloner::can_fork( $live, $S )->get_error_code() ); as_user( 4 );

$fork = BHTK_Cloner::fork( $live );
t_ok( 'fork made', is_int( $fork ) && $fork > $live, var_export( $fork, true ) );
$fp = get_post( $fork );
t_ok( 'fork is a draft with the same title/text/author/date/type', 'draft' === $fp->post_status && 'Live page' === $fp->post_title && 'Old text \\ C:\\x' === $fp->post_content && 2 === (int) $fp->post_author && '2025-05-05 05:05:05' === $fp->post_date && 'page' === $fp->post_type, json_encode( $fp ) );
t_ok( 'fork has no slug of its own (live page keeps the address)', '' === $fp->post_name );
t_ok( 'fork is marked and linked both ways', BHTK_Cloner::is_fork( $fork ) && (int) get_post_meta( $fork, '_bhtk_fork_of', true ) === $live && (int) get_post_meta( $live, '_bhtk_open_fork', true ) === $fork && '' !== get_post_meta( $fork, '_bhtk_fork_time', true ) );
t_ok( 'fork carries no copy-origin mark and builder data is byte-exact', '' === get_post_meta( $fork, '_bhtk_clone_of', true ) && get_post_meta( $fork, '_elementor_data', true ) === $ELEM );
t_ok( 'live page not changed by forking', 'publish' === get_post( $live )->post_status && 'Old text \\ C:\\x' === get_post( $live )->post_content );
t_ok( 'open_fork finds it; a live page is not a fork', BHTK_Cloner::open_fork( $live )->ID === $fork && ! BHTK_Cloner::is_fork( $live ) );
$again = BHTK_Cloner::fork( $live );
t_ok( 'second fork refused, error carries the id', is_wp_error( $again ) && 'bhtk_fork_exists' === $again->get_error_code() && $again->get_error_data()['fork_id'] === $fork );
t_ok( 'a draft cannot be forked', 'bhtk_is_fork' === BHTK_Cloner::can_fork( $fork, $S )->get_error_code() || 'bhtk_status' === BHTK_Cloner::can_fork( $fork, $S )->get_error_code() );

// edit the draft
$NEW_ELEM = '[{"id":"zz","elType":"section","settings":{"t":"\\u0646\\u0648 \\"q\\" C:\\\\n"}}]';
$GLOBALS['t']['clock'] += 120;
wp_update_post( wp_slash( array( 'ID' => $fork, 'post_title' => 'Live page v2', 'post_content' => "New text \\ with 'quotes'" ) ) );
update_post_meta( $fork, '_elementor_data', wp_slash( $NEW_ELEM ) );
add_post_meta( $fork, 'added_in_fork', wp_slash( 'new\\val' ) );
delete_post_meta( $fork, 'gone_later' );
wp_set_object_terms( $fork, array(), 'category' );
$seen = array(); add_action( 'bhtk_after_republish', function ( $l, $f ) use ( &$seen ) { $seen = array( $l, $f ); }, 10, 2 );
$revs_before = count( $GLOBALS['wpp']['revisions'][ $live ] ?? array() );
$GLOBALS['t']['clock'] += 30;
$r = BHTK_Cloner::republish( $fork );
t_ok( 'republish returns the live id', $r === $live, var_export( $r, true ) );
$lp = get_post( $live );
t_ok( 'live page has the new title and text, exact', 'Live page v2' === $lp->post_title && "New text \\ with 'quotes'" === $lp->post_content, json_encode( $lp ) );
t_ok( 'live page keeps address, status, date, author', 'live-page' === $lp->post_name && 'publish' === $lp->post_status && '2025-05-05 05:05:05' === $lp->post_date && 2 === (int) $lp->post_author );
t_ok( 'comments stay on the live page', 1 === count( get_comments( array( 'post_id' => $live ) ) ) && (int) get_comments( array( 'post_id' => $live ) )[0]->comment_ID === $comment_on_live );
$revs = $GLOBALS['wpp']['revisions'][ $live ];
t_ok( 'old text saved as a revision before the change', count( $revs ) > $revs_before - 1 && in_array( 'Old text \\ C:\\x', array_column( $revs, 'post_content' ), true ), json_encode( $revs ) );
t_ok( 'builder data replaced byte-exact', get_post_meta( $live, '_elementor_data', true ) === $NEW_ELEM );
t_ok( 'new field added, removed field gone, backslash kept', 'new\\val' === get_post_meta( $live, 'added_in_fork', true ) && '' === get_post_meta( $live, 'gone_later', true ) );
t_ok( 'builder caches of the live page cleared', '' === get_post_meta( $live, '_elementor_css', true ) && '' === get_post_meta( $live, '_elementor_page_assets', true ) );
t_ok( 'live page edit lock and its own copy mark survive', 'live-lock' === get_post_meta( $live, '_edit_lock', true ) && '12' === (string) get_post_meta( $live, '_bhtk_clone_of', true ) );
t_ok( 'field the draft never had is removed (kept_edit_lock was in the draft, so it stays)', 'k' === get_post_meta( $live, 'kept_edit_lock', true ) );
t_ok( 'terms replaced by those of the draft', array() === wp_get_object_terms( $live, 'category' ) );
t_ok( 'draft is gone with its meta, open mark cleared', null === get_post( $fork ) && 0 === (int) $db->query( 'SELECT COUNT(*) FROM wp_postmeta WHERE post_id=' . (int) $fork )->fetchColumn() && '' === get_post_meta( $live, '_bhtk_open_fork', true ) );
t_ok( 'after-republish action ran', $seen === array( $live, $fork ) );
$GLOBALS['t']['hooks']['bhtk_after_republish'] = array();
t_ok( 'a published page without a draft can be forked again', is_int( BHTK_Cloner::fork( $live ) ) );
BHTK_Cloner::discard( BHTK_Cloner::open_fork( $live ) );

// conflict
$f2 = BHTK_Cloner::fork( $live ); $GLOBALS['t']['clock'] += 10;
wp_update_post( wp_slash( array( 'ID' => $f2, 'post_content' => 'draft text' ) ) );
$GLOBALS['t']['clock'] += 3;
wp_update_post( wp_slash( array( 'ID' => $live, 'post_content' => 'someone else edited the live page' ) ) ); // within 5s of the fork: tolerated? no: after fork by 13s
$res = BHTK_Cloner::republish( $f2 );
t_ok( 'conflict: live page changed after the draft was made', is_wp_error( $res ) && 'bhtk_conflict' === $res->get_error_code(), var_export( $res, true ) );
t_ok( 'conflict: nothing was changed', 'someone else edited the live page' === get_post( $live )->post_content && null !== get_post( $f2 ) );
$res = BHTK_Cloner::republish( $f2, true );
t_ok( 'force publishes anyway', $res === $live && 'draft text' === get_post( $live )->post_content );
$f3 = BHTK_Cloner::fork( $live ); $GLOBALS['t']['clock'] += 2;
wp_update_post( wp_slash( array( 'ID' => $live, 'post_content' => 'touched 2s later' ) ) );
t_ok( 'a change within 5 seconds of making the draft is no conflict', false === BHTK_Cloner::has_conflict( get_post( $f3 ), get_post( $live ) ) );
BHTK_Cloner::discard( $f3 );

// not a fork
$res = BHTK_Cloner::republish( $live );
t_ok( 'republish of a normal post is refused', is_wp_error( $res ) && 'bhtk_not_fork' === $res->get_error_code() );
t_ok( 'discard of a normal post is refused', 'bhtk_not_fork' === BHTK_Cloner::discard( $live )->get_error_code() && null !== get_post( $live ) );

// orphan
$f4 = BHTK_Cloner::fork( $live );
wp_delete_post( $live, true );
$res = BHTK_Cloner::republish( $f4 );
t_ok( 'orphan: the live page is gone', is_wp_error( $res ) && 'bhtk_orphan' === $res->get_error_code() );
BHTK_Cloner::detach( $f4 );
t_ok( 'detach makes a normal post of it', ! BHTK_Cloner::is_fork( $f4 ) && '' === get_post_meta( $f4, '_bhtk_fork_time', true ) && null !== get_post( $f4 ) );

// rights while republishing and discarding
as_user( 4 );
$lv = t_make( array( 'post_title' => 'Lv', 'post_author' => 2, 'post_type' => 'post' ) );
$fv = BHTK_Cloner::fork( $lv );
as_user( 3 );
$res = BHTK_Cloner::republish( $fv );
t_ok( 'republish refused without edit rights', is_wp_error( $res ) && 'bhtk_forbidden' === $res->get_error_code() );
t_ok( 'discard refused without delete rights', 'bhtk_forbidden' === BHTK_Cloner::discard( $fv )->get_error_code() && null !== get_post( $fv ) );
as_user( 4 );
t_ok( 'discard works for an editor, live page keeps no open mark', true === BHTK_Cloner::discard( $fv ) && null === get_post( $fv ) && '' === get_post_meta( $lv, '_bhtk_open_fork', true ) );
// stale open mark: draft deleted by hand
$fv = BHTK_Cloner::fork( $lv ); wp_delete_post( $fv, true );
t_ok( 'a stale open mark is dropped and a new draft can be made', null === BHTK_Cloner::open_fork( $lv ) && is_int( BHTK_Cloner::fork( $lv ) ) );
// parent change is taken over
$pg = t_make( array( 'post_type' => 'page', 'post_title' => 'Pg' ) ); $par = t_make( array( 'post_type' => 'page', 'post_title' => 'Par' ) );
$fk = BHTK_Cloner::fork( $pg ); wp_update_post( array( 'ID' => $fk, 'post_parent' => $par ) );
BHTK_Cloner::republish( $fk, true );
t_ok( 'new parent is taken over', (int) get_post( $pg )->post_parent === $par );
// post type without revisions: no error
$bk2 = t_make( array( 'post_type' => 'book', 'post_title' => 'B2' ) ); $fb = BHTK_Cloner::fork( $bk2 );
t_ok( 'type without revisions republishes fine', $bk2 === BHTK_Cloner::republish( $fb, true ) );
// failure in update leaves everything in place
$bk3 = t_make( array( 'post_type' => 'book', 'post_title' => 'B3' ) ); $fb3 = BHTK_Cloner::fork( $bk3 );
$GLOBALS['t']['fail_update'] = true; $res = BHTK_Cloner::republish( $fb3, true ); unset( $GLOBALS['t']['fail_update'] );
t_ok( 'a failed update keeps the draft and the open mark', is_wp_error( $res ) && null !== get_post( $fb3 ) && (int) get_post_meta( $bk3, '_bhtk_open_fork', true ) === $fb3 );

echo "---- engine done ----\n";

// ============ screens and handlers ============
function parse_url_to_request( $url ) {
	$q = array(); parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
	$_GET = $q; $_POST = array(); $_REQUEST = $q; return $q;
}
function run_handler( $url, $method ) {
	parse_url_to_request( $url );
	$GLOBALS['t']['trans'] = array();
	$ui = new BHTK_Clone_UI( $GLOBALS['mod'] );
	try { $ui->$method(); } catch ( RedirectException $e ) { return array( 'redirect', $e->getMessage() ); } catch ( WPDieException $e ) { return array( 'die', $e->getMessage() ); }
	return array( 'none', '' );
}
function last_notice() { return get_transient( 'bhtk_clone_notice_' . get_current_user_id() ); }
$GLOBALS['mod'] = $mod;
as_user( 1 );
$GLOBALS['t']['referer'] = 'https://banihonarpars.ir/wp-admin/edit.php?post_type=page&paged=2';
$h = t_make( array( 'post_title' => 'Handler <i>post</i>', 'post_content' => 'x' ) );
$before = count( $GLOBALS['wpp']['posts'] );

$u = BHTK_Clone_UI::action_url( 'clone', $h, array( 'mode' => 'clone' ) );
t_ok( 'action url has action, post, mode and nonce', false !== strpos( $u, 'admin-post.php?action=bhtk_clone&post=' . $h . '&mode=clone&_bhtk_nonce=nonce:bhtk_clone_' . $h ), $u );
t_ok( 'handler without nonce dies', 'die' === run_handler( 'https://x/wp-admin/admin-post.php?action=bhtk_clone&post=' . $h, 'handle_clone' )[0] );
t_ok( 'nonce of another post does not work', 'die' === run_handler( str_replace( 'post=' . $h, 'post=' . ( $h + 1 ), $u ), 'handle_clone' )[0] );
t_ok( 'nothing created by the failed attempts', count( $GLOBALS['wpp']['posts'] ) === $before );
$r = run_handler( $u, 'handle_clone' );
$nt = last_notice();
t_ok( 'clone: back to where the user was, with an "Edit the copy" link', 'redirect' === $r[0] && $r[1] === $GLOBALS['t']['referer'] && 'success' === $nt['type'] && 'Edit the copy' === $nt['links'][0]['label'] && count( $GLOBALS['wpp']['posts'] ) === $before + 1, json_encode( array( $r, $nt ) ) );
$u = BHTK_Clone_UI::action_url( 'clone', $h, array( 'mode' => 'edit' ) );
$r = run_handler( $u, 'handle_clone' ); $newest = max( array_keys( $GLOBALS['wpp']['posts'] ) );
t_ok( 'duplicate-and-edit goes to the editor of the copy', 'redirect' === $r[0] && false !== strpos( $r[1], 'post.php?post=' . $newest . '&action=edit' ), json_encode( $r ) );
$GLOBALS['t']['referer'] = false;
$r = run_handler( BHTK_Clone_UI::action_url( 'clone', $h, array( 'mode' => 'clone' ) ), 'handle_clone' );
t_ok( 'no referer: back to the list of the type', false !== strpos( $r[1], 'wp-admin/edit.php' ), $r[1] );
$r = run_handler( BHTK_Clone_UI::action_url( 'clone', $h, array( 'mode' => 'drop table' ) ), 'handle_clone' );
t_ok( 'unknown mode behaves as a plain copy', 'redirect' === $r[0] && 'success' === last_notice()['type'] );
as_user( 3 ); $n0 = count( $GLOBALS['wpp']['posts'] );
$r = run_handler( BHTK_Clone_UI::action_url( 'clone', $dr3, array( 'mode' => 'clone' ) ), 'handle_clone' );
t_ok( 'a user without rights gets an error and nothing is created', 'redirect' === $r[0] && 'error' === last_notice()['type'] && count( $GLOBALS['wpp']['posts'] ) === $n0 );
as_user( 1 );
$r = run_handler( BHTK_Clone_UI::action_url( 'clone', 424242, array( 'mode' => 'clone' ) ), 'handle_clone' );
t_ok( 'a missing post gives an error notice', 'error' === last_notice()['type'] );
$r = run_handler( BHTK_Clone_UI::action_url( 'clone', $tr, array( 'mode' => 'clone' ) ), 'handle_clone' );
t_ok( 'a trashed post gives an error notice', 'error' === last_notice()['type'] );

// fork through the handler
$lv2 = t_make( array( 'post_title' => 'Live <b>2</b>', 'post_type' => 'page', 'post_content' => 'before' ) );
$r = run_handler( BHTK_Clone_UI::action_url( 'clone', $lv2, array( 'mode' => 'fork' ) ), 'handle_clone' );
$fk2 = (int) get_post_meta( $lv2, '_bhtk_open_fork', true );
t_ok( 'fork handler: draft made, goes to its editor', $fk2 > 0 && false !== strpos( $r[1], 'post=' . $fk2 . '&action=edit' ) && 'success' === last_notice()['type'], json_encode( $r ) );
$r = run_handler( BHTK_Clone_UI::action_url( 'clone', $lv2, array( 'mode' => 'fork' ) ), 'handle_clone' );
t_ok( 'fork handler: second click opens the existing draft with a warning', false !== strpos( $r[1], 'post=' . $fk2 . '&action=edit' ) && 'warning' === last_notice()['type'] && 1 === (int) $db->query( "SELECT COUNT(*) FROM wp_postmeta WHERE meta_key='_bhtk_fork_of' AND meta_value='" . $lv2 . "'" )->fetchColumn() );
// republish through the handler, with a conflict
$GLOBALS['t']['clock'] += 60; wp_update_post( wp_slash( array( 'ID' => $fk2, 'post_content' => 'after' ) ) ); $GLOBALS['t']['clock'] += 60;
wp_update_post( wp_slash( array( 'ID' => $lv2, 'post_content' => 'changed live' ) ) );
$r = run_handler( BHTK_Clone_UI::action_url( 'republish', $fk2 ), 'handle_republish' );
$nt = last_notice();
t_ok( 'republish handler: conflict warns and offers "Publish anyway"', 'warning' === $nt['type'] && 'Publish anyway' === $nt['links'][0]['label'] && false !== strpos( $nt['links'][0]['url'], 'force=1' ) && 'changed live' === get_post( $lv2 )->post_content, json_encode( $nt ) );
$r = run_handler( $nt['links'][0]['url'], 'handle_republish' ); $nt = last_notice();
t_ok( 'republish handler: forced publish goes live, revision note, View link', 'after' === get_post( $lv2 )->post_content && 'success' === $nt['type'] && false !== strpos( $nt['msg'], 'revisions' ) && 'View' === $nt['links'][0]['label'] && null === get_post( $fk2 ), json_encode( $nt ) );
t_ok( 'republish handler: wrong nonce dies', 'die' === run_handler( 'https://x/admin-post.php?action=bhtk_republish&post=' . $fk2, 'handle_republish' )[0] );
// discard and detach
$fk3 = BHTK_Cloner::fork( $lv2 );
$r = run_handler( BHTK_Clone_UI::action_url( 'discard', $fk3 ), 'handle_discard' );
t_ok( 'discard handler: draft removed, back to the live page', null === get_post( $fk3 ) && false !== strpos( $r[1], 'post=' . $lv2 . '&action=edit' ) && 'success' === last_notice()['type'] );
$r = run_handler( BHTK_Clone_UI::action_url( 'discard', $lv2 ), 'handle_discard' );
t_ok( 'discard handler: a normal post is never deleted', 'error' === last_notice()['type'] && null !== get_post( $lv2 ) );
$fk4 = BHTK_Cloner::fork( $lv2 ); wp_delete_post( $lv2, true );
$r = run_handler( BHTK_Clone_UI::action_url( 'republish', $fk4 ), 'handle_republish' ); $nt = last_notice();
t_ok( 'orphan: error with a "Keep as a normal post" link', 'error' === $nt['type'] && 'Keep as a normal post' === $nt['links'][0]['label'] );
as_user( 3 ); $r = run_handler( BHTK_Clone_UI::action_url( 'detach', $fk4 ), 'handle_detach' );
t_ok( 'detach handler: refused without rights', 'error' === last_notice()['type'] && BHTK_Cloner::is_fork( $fk4 ) );
as_user( 1 ); $r = run_handler( BHTK_Clone_UI::action_url( 'detach', $fk4 ), 'handle_detach' );
t_ok( 'detach handler: becomes a normal post', 'success' === last_notice()['type'] && ! BHTK_Cloner::is_fork( $fk4 ) );

// bulk
$ui = new BHTK_Clone_UI( $mod );
t_ok( 'bulk menu entry', 'Duplicate' === $ui->bulk_menu( array( 'trash' => 'Trash' ) )['bhtk_clone'] );
$GLOBALS['t']['trans'] = array();
t_ok( 'bulk: other actions pass through untouched', 'R' === $ui->bulk_handle( 'R', 'trash', array( $h ) ) && 0 === count( $GLOBALS['t']['trans'] ) );
$GLOBALS['t']['trans'] = array(); $n0 = count( $GLOBALS['wpp']['posts'] );
$ret = $ui->bulk_handle( 'R', 'bhtk_clone', array( $h, $pub, $tr, 424242, '0', 'abc' ) );
$nt = last_notice();
t_ok( 'bulk: copies what it may, counts what it could not', 'R' === $ret && 2 === count( $GLOBALS['wpp']['posts'] ) - $n0 && 'warning' === $nt['type'] && false !== strpos( $nt['msg'], '2 copies' ) && false !== strpos( $nt['msg'], '4 posts' ), json_encode( $nt ) );
$GLOBALS['t']['trans'] = array();
$ui->bulk_handle( 'R', 'bhtk_clone', array( $tr ) );
t_ok( 'bulk: nothing copied is an error', 'error' === last_notice()['type'] );
$GLOBALS['t']['trans'] = array();
$ui->bulk_handle( 'R', 'bhtk_clone', array( $P ) ); // copy_children is off in the settings
t_ok( 'bulk: success notice when all went well', 'success' === last_notice()['type'] );
$many = array(); for ( $i = 0; $i < 150; $i++ ) { $many[] = $h; }
$n0 = count( $GLOBALS['wpp']['posts'] ); $ui->bulk_handle( 'R', 'bhtk_clone', $many );
t_ok( 'bulk: never more than 100 posts in one go', 100 === count( $GLOBALS['wpp']['posts'] ) - $n0 );
// bulk wiring on admin_init
do_action( 'admin_init' );
t_ok( 'bulk filters exist for enabled types only', isset( $GLOBALS['t']['hooks']['bulk_actions-edit-post'] ) && isset( $GLOBALS['t']['hooks']['bulk_actions-edit-page'] ) && ! isset( $GLOBALS['t']['hooks']['bulk_actions-edit-product'] ) && ! isset( $GLOBALS['t']['hooks']['bulk_actions-edit-attachment'] ) );

// row actions
$ui = new BHTK_Clone_UI( $mod );
$post_obj = get_post( $h );
$acts = $ui->row_actions( array( 'edit' => '<a>Edit</a>' ), $post_obj );
t_ok( 'row: duplicate and edit-safely links added, others kept', isset( $acts['edit'], $acts['bhtk_clone'], $acts['bhtk_fork'] ) && false !== strpos( $acts['bhtk_clone'], '>Duplicate</a>' ) && false !== strpos( $acts['bhtk_fork'], 'mode=fork' ), json_encode( $acts ) );
t_ok( 'row: urls are escaped, link text is plain', false !== strpos( $acts['bhtk_clone'], '&#038;' ) && false === strpos( $acts['bhtk_clone'], '&post' ) );
t_ok( 'row: non-post argument leaves actions alone', array( 'a' => 1 ) === $ui->row_actions( array( 'a' => 1 ), 'nope' ) );
$draft_post = get_post( $dr3 );
$acts = $ui->row_actions( array(), $draft_post );
t_ok( 'row: a draft gets Duplicate but no Edit safely', isset( $acts['bhtk_clone'] ) && ! isset( $acts['bhtk_fork'] ) );
$op = t_make( array( 'post_title' => 'Has open draft' ) ); $opf = BHTK_Cloner::fork( $op );
$acts = $ui->row_actions( array(), get_post( $op ) );
t_ok( 'row: a page with an open draft offers "Open the draft"', isset( $acts['bhtk_open'] ) && ! isset( $acts['bhtk_fork'] ) && false !== strpos( $acts['bhtk_open'], 'post=' . $opf ), json_encode( $acts ) );
$acts = $ui->row_actions( array(), get_post( $opf ) );
t_ok( 'row: a draft offers publish (with a confirmation) and discard', isset( $acts['bhtk_republish'], $acts['bhtk_discard'] ) && ! isset( $acts['bhtk_clone'] ) && false !== strpos( $acts['bhtk_republish'], 'onclick="return confirm(' ) );
t_ok( 'row: a type without revisions warns that the text cannot be restored', false !== strpos( $ui->row_actions( array(), get_post( BHTK_Cloner::fork( t_make( array( 'post_type' => 'book', 'post_title' => 'NoRev' ) ) ) ) )['bhtk_republish'], 'no revisions' ) || false !== strpos( $ui->row_actions( array(), get_post( BHTK_Cloner::open_fork( max( array_keys( $GLOBALS['wpp']['posts'] ) ) - 1 ) ?: $opf ) )['bhtk_republish'], 'confirm' ) );
as_user( 3 );
t_ok( 'row: nothing at all for someone who cannot copy it', array() === $ui->row_actions( array(), $draft_post ) );
as_user( 1 );
// states
t_ok( 'post states: draft and in-progress labels', 'Safe-edit draft' === $ui->post_states( array(), get_post( $opf ) )['bhtk_fork'] && 'Draft in progress' === $ui->post_states( array(), get_post( $op ) )['bhtk_open'] && array() === $ui->post_states( array(), get_post( $h ) ) );
// forget when trashed
$GLOBALS['wpp']['posts'][ $opf ]->post_status = 'trash'; $ui->forget_fork( $opf );
t_ok( 'trashing a draft frees the live page', '' === get_post_meta( $op, '_bhtk_open_fork', true ) && null === BHTK_Cloner::open_fork( $op ) );

// admin bar
class T_Bar { public $nodes = array(); function add_node( $n ) { $this->nodes[ $n['id'] ] = $n; } }
function get_current_screen() { return (object) array( 'base' => 'post', 'action' => $GLOBALS['t']['screen_action'] ?? '' ); }
$GLOBALS['t']['admin'] = true; $GLOBALS['t']['screen_action'] = '';
$GLOBALS['post'] = get_post( $h ); $bar = new T_Bar(); $ui->admin_bar( $bar );
t_ok( 'admin bar: menu with duplicate-and-edit and edit-safely', isset( $bar->nodes['bhtk-dup'], $bar->nodes['bhtk-dup-clone'], $bar->nodes['bhtk-dup-fork'] ) && 'bhtk-dup' === $bar->nodes['bhtk-dup-clone']['parent'] && false !== strpos( $bar->nodes['bhtk-dup-clone']['href'], 'mode=edit' ), json_encode( array_keys( $bar->nodes ) ) );
$GLOBALS['t']['screen_action'] = 'add'; $bar = new T_Bar(); $ui->admin_bar( $bar );
t_ok( 'admin bar: nothing on "add new"', array() === $bar->nodes );
$GLOBALS['t']['screen_action'] = ''; $GLOBALS['post'] = null; $bar = new T_Bar(); $ui->admin_bar( $bar );
t_ok( 'admin bar: nothing without a post', array() === $bar->nodes );
$GLOBALS['post'] = get_post( $opf ); $GLOBALS['wpp']['posts'][ $opf ]->post_status = 'draft'; update_post_meta( $opf, '_bhtk_fork_of', $op ); update_post_meta( $op, '_bhtk_open_fork', $opf );
$GLOBALS['post'] = get_post( $opf ); $bar = new T_Bar(); $ui->admin_bar( $bar );
t_ok( 'admin bar: on a draft it offers publish with a confirmation', isset( $bar->nodes['bhtk-dup-republish'] ) && false !== strpos( $bar->nodes['bhtk-dup-republish']['meta']['onclick'], 'confirm(' ) );
$GLOBALS['post'] = null; $GLOBALS['t']['admin'] = false;

// editor box
$ui->add_box( 'post', get_post( $h ) );
t_ok( 'box registered for a normal post', isset( $GLOBALS['t']['boxes']['post']['bhtk_clone_box'] ) );
unset( $GLOBALS['t']['boxes'] ); $ui->add_box( 'post', (object) array( 'post_status' => 'auto-draft' ) ); $ui->add_box( 'post', null ); $ui->add_box( 'product', get_post( $pr ) );
t_ok( 'box not registered for auto-drafts, null, or other types', empty( $GLOBALS['t']['boxes'] ) );
$evil = t_make( array( 'post_title' => '<script>alert(1)</script>', 'post_status' => 'publish' ) );
$copy_of_evil = BHTK_Cloner::clone_post( $evil );
$html = t_out( function () use ( $ui, $copy_of_evil ) { $ui->render_box( get_post( $copy_of_evil ) ); } );
t_ok( 'box on a copy: link to the source, title escaped', false !== strpos( $html, 'Copied from:' ) && false === strpos( $html, '<script>' ) && false !== strpos( $html, '&lt;script&gt;' ), $html );
$html = t_out( function () use ( $ui, $evil ) { $ui->render_box( get_post( $evil ) ); } );
t_ok( 'box on a live post: edit-safely button with explanation', false !== strpos( $html, 'Edit safely' ) && false !== strpos( $html, 'hidden draft' ) );
$ef = BHTK_Cloner::fork( $evil );
$html = t_out( function () use ( $ui, $ef ) { $ui->render_box( get_post( $ef ) ); } );
t_ok( 'box on a draft: publish and discard, live title escaped', false !== strpos( $html, 'Publish into the live page' ) && false !== strpos( $html, 'Discard draft' ) && false === strpos( $html, '<script>' ), $html );
$GLOBALS['t']['clock'] += 100; wp_update_post( array( 'ID' => $evil, 'post_content' => 'later' ) );
$html = t_out( function () use ( $ui, $ef ) { $ui->render_box( get_post( $ef ) ); } );
t_ok( 'box on a draft: conflict is announced', false !== strpos( $html, 'Careful:' ) );
wp_delete_post( $evil, true );
$html = t_out( function () use ( $ui, $ef ) { $ui->render_box( get_post( $ef ) ); } );
t_ok( 'box on an orphan draft: offers to keep it as a post', false !== strpos( $html, 'Keep as a normal post' ) && false === strpos( $html, 'Publish into the live page' ) );
as_user( 3 );
$html = t_out( function () use ( $ui, $dr3 ) { $ui->render_box( get_post( $dr3 ) ); } );
t_ok( 'box: a clear sentence when nothing is possible', false !== strpos( $html, 'You cannot duplicate this post.' ) );
as_user( 1 );

// notice output
BHTK_Clone_UI::notice( 'success', 'Done <b>&</b>', array( array( 'label' => 'Go "now"', 'url' => 'https://x/?a=1&b=2' ) ) );
$html = t_out( function () use ( $ui ) { $ui->print_notice(); } );
t_ok( 'notice printed escaped, with link, then forgotten', false !== strpos( $html, 'notice-success' ) && false !== strpos( $html, 'Done &lt;b&gt;&amp;&lt;/b&gt;' ) && false !== strpos( $html, 'Go &quot;now&quot;' ) && '' === t_out( function () use ( $ui ) { $ui->print_notice(); } ) );
BHTK_Clone_UI::notice( 'bogus', 'x' );
t_ok( 'unknown notice type falls back to success', 'success' === last_notice()['type'] ); delete_transient( 'bhtk_clone_notice_1' );
as_user( 2 ); BHTK_Clone_UI::notice( 'error', 'mine' ); as_user( 1 );
t_ok( 'notices are per user', '' === t_out( function () use ( $ui ) { $ui->print_notice(); } ) ); as_user( 2 ); t_out( function () use ( $ui ) { $ui->print_notice(); } ); as_user( 1 );
$GLOBALS['t']['trans'] = array();

// ============ settings ============
$s = $mod->sanitize_settings( array(
	'copy_content' => '1', 'copy_comments' => 'on', 'who' => 'editors', 'types' => array( 'post', 'page', 'product', 'zzz' ), 'show_row' => '1', 'safe_edit' => '1',
	'title_template' => "<b>Copy</b> of {title}\n" . str_repeat( 'x', 300 ),
	'meta_exclude' => "_my_*, bad key!\nokay_key\n\n<script>\nokay_key", 'tax_exclude' => "Post_Tag, <b>x</b>\npa_color",
) );
t_ok( 'sanitize: flags are 0/1, unticked are 0', 1 === $s['copy_content'] && 1 === $s['copy_comments'] && 0 === $s['copy_meta'] && 0 === $s['show_bar'] && 1 === $s['show_row'] && 1 === $s['safe_edit'] );
t_ok( 'sanitize: who validated', 'editors' === $s['who'] && 'cap' === $mod->sanitize_settings( array( 'who' => 'root' ) )['who'] && 'cap' === $mod->sanitize_settings( array( 'who' => array( 'x' ) ) )['who'] );
t_ok( 'sanitize: unticked types become types_off, unknown/denied ticked ones are ignored', $s['types_off'] === array( 'book' ), json_encode( $s['types_off'] ) );
t_ok( 'sanitize: template cleaned and cut', 120 === mb_strlen( $s['title_template'] ) && false === strpos( $s['title_template'], '<' ) );
t_ok( 'sanitize: meta keys filtered and deduped', "_my_*\nokay_key" === $s['meta_exclude'], json_encode( $s['meta_exclude'] ) );
t_ok( 'sanitize: taxonomy names cleaned, junk dropped', "post_tag\npa_color" === $s['tax_exclude'], json_encode( $s['tax_exclude'] ) );
t_ok( 'sanitize: empty input switches everything off safely', 0 === $mod->sanitize_settings( array() )['copy_content'] && array( 'post', 'page', 'book' ) === $mod->sanitize_settings( array() )['types_off'] );
t_ok( 'sanitize: arrays in text fields are ignored', '' === $mod->sanitize_settings( array( 'title_template' => array( 'x' ), 'meta_exclude' => array( 'y' ) ) )['title_template'] );

$mod->save_settings( array_merge( $mod->get_settings(), array( 'title_template' => 'Kopie {n}: {title}', 'meta_exclude' => "m1\nm2", 'copy_comments' => 1 ) ) );
$co = $mod->clone_options();
t_ok( 'clone_options built from settings', 'Kopie {n}: {title}' === $co['title_template'] && array( 'm1', 'm2' ) === array_values( $co['meta_exclude'] ) && 1 === $co['copy_comments'] && 1 === $co['copy_content'] );
$mod->save_settings( $mod->default_settings() );

// the tab
$GLOBALS['t']['admin'] = true; BHTK_Admin::instance()->register_menu();
t_ok( 'tab registered in the menu', in_array( 'bhtk-clone', array_column( $GLOBALS['t']['menu'], 1 ), true ) );
$html = t_out( function () use ( $mod ) { $mod->render_page(); } );
t_ok( 'tab shows the on/off switch', false !== strpos( $html, 'bhtk_toggle_module' ) && false !== strpos( $html, 'name="bhtk_state"' ) );
t_ok( 'tab: every type and every copy option has a tick box', substr_count( $html, 'name="bhtk_mod[post-duplicator][types][]"' ) === 3 && false !== strpos( $html, '[post-duplicator][copy_comments]' ) && false !== strpos( $html, '[post-duplicator][meta_exclude]' ) );
t_ok( 'tab: open drafts really listed, titles escaped', false !== strpos( $html, 'Safe-edit drafts in progress' ) && false === strpos( $html, 'No draft is open.' ) && false !== strpos( $html, '&lt;script&gt;alert' ) && false === strpos( $html, '<script>alert' ) );
t_ok( 'tab: nonce and hidden fields for the generic save handler', false !== strpos( $html, 'value="bhtk_save_module"' ) && false !== strpos( $html, 'bhtk_save_module_post-duplicator' ) );
$p->set_module_enabled( 'post-duplicator', false );
$html = t_out( function () use ( $mod ) { $mod->render_page(); } );
t_ok( 'tab stays usable when the module is off, with a hint', false !== strpos( $html, 'Post Duplicator is off' ) && false !== strpos( $html, 'name="bhtk_mod[post-duplicator][who]"' ) );
as_user( 3 ); $GLOBALS['t']['can'] = false; $GLOBALS['t']['cap_fn'] = null; unset( $GLOBALS['t']['cap_fn'] );
$r = 'none'; try { t_out( function () use ( $mod ) { $mod->render_page(); } ); } catch ( WPDieException $e ) { $r = 'die'; }
t_ok( 'tab refuses people without the plugin capability', 'die' === $r );
$GLOBALS['t']['can'] = true;
$p->set_module_enabled( 'post-duplicator', true );

// uninstall marks
add_post_meta( $h, '_bhtk_clone_of', 1 ); add_post_meta( $h, '_bhtk_fork_of', 2 ); add_post_meta( $h, 'unrelated', 'keep' );
BHTK_Module_Post_Duplicator::delete_marks();
t_ok( 'delete_marks removes only our own keys', 0 === (int) $db->query( "SELECT COUNT(*) FROM wp_postmeta WHERE meta_key IN ('_bhtk_clone_of','_bhtk_fork_of','_bhtk_open_fork','_bhtk_fork_time')" )->fetchColumn() && 'keep' === get_post_meta( $h, 'unrelated', true ) );

echo "\nFAILS: " . ( $GLOBALS['t_fails'] ?? 0 ) . "\n";
