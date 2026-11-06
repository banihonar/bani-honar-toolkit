<?php
/**
 * Posts, meta, terms and comments for the test harness, with the same slash handling as WordPress:
 * wp_insert_post(), add_post_meta() and wp_insert_comment() all remove one level of slashes.
 */

class WP_Post {
	public $ID = 0; public $post_author = 0; public $post_date = ''; public $post_date_gmt = ''; public $post_content = ''; public $post_title = '';
	public $post_excerpt = ''; public $post_status = 'draft'; public $comment_status = 'open'; public $ping_status = 'open'; public $post_password = '';
	public $post_name = ''; public $post_parent = 0; public $menu_order = 0; public $post_type = 'post'; public $post_modified = ''; public $post_modified_gmt = '';
	public $post_content_filtered = '';
	public function __construct( $a = array() ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}

$GLOBALS['wpp'] = array( 'posts' => array(), 'rel' => array(), 'terms' => array(), 'comments' => array(), 'revisions' => array(), 'next' => 100, 'next_comment' => 500, 'next_term' => 10,
	'types' => array(
		'post'    => array( 'label' => 'Posts', 'hier' => false, 'supports' => array( 'editor', 'revisions', 'post-formats', 'thumbnail' ), 'tax' => array( 'category', 'post_tag' ) ),
		'page'    => array( 'label' => 'Pages', 'hier' => true, 'supports' => array( 'editor', 'revisions', 'page-attributes' ), 'tax' => array() ),
		'book'    => array( 'label' => 'Books', 'hier' => false, 'supports' => array( 'editor' ), 'tax' => array( 'genre', 'post_translations' ) ),
		'product' => array( 'label' => 'Products', 'hier' => false, 'supports' => array( 'editor' ), 'tax' => array() ),
		'attachment' => array( 'label' => 'Media', 'hier' => false, 'supports' => array(), 'tax' => array() ),
	),
);
$GLOBALS['wpp']['terms'] = array(
	'category' => array( 1 => array( 'Uncategorized', 0 ), 2 => array( 'News', 0 ), 3 => array( 'Sport', 2 ) ),
	'post_tag' => array( 4 => array( 'alpha', 0 ), 5 => array( 'beta', 0 ), 6 => array( 'gamma', 0 ) ),
	'genre'    => array( 7 => array( 'Drama', 0 ) ),
	'post_translations' => array( 8 => array( 'pll_abc', 0 ) ),
	'post_format' => array( 9 => array( 'post-format-gallery', 0 ) ),
);

function t_pdo() { return $GLOBALS['wpdb']->raw(); }
function wp_slash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_slash', $v ); } return is_string( $v ) ? addslashes( $v ) : $v; }
function is_serialized( $d ) { return is_string( $d ) && preg_match( '/^(a|O|s|i|b|d):/', $d ) && false !== @unserialize( $d ) || 'b:0;' === $d; }
function maybe_serialize( $d ) { return ( is_array( $d ) || is_object( $d ) ) ? serialize( $d ) : $d; }
function maybe_unserialize( $d ) { return is_serialized( $d ) ? unserialize( $d ) : $d; }

function get_post( $p = null ) { if ( $p instanceof WP_Post ) { return $p; } if ( empty( $p ) ) { return $GLOBALS['post'] ?? null; } return isset( $GLOBALS['wpp']['posts'][ (int) $p ] ) ? clone $GLOBALS['wpp']['posts'][ (int) $p ] : null; }
function get_post_type_object( $t ) {
	if ( ! isset( $GLOBALS['wpp']['types'][ $t ] ) ) { return null; }
	return (object) array( 'labels' => (object) array( 'name' => $GLOBALS['wpp']['types'][ $t ]['label'] ), 'cap' => (object) array( 'create_posts' => 'create_' . $t, 'publish_posts' => 'publish_' . $t, 'edit_others_posts' => 'edit_others_' . $t ) );
}
function get_post_types( $args = array(), $out = 'names' ) { $r = array(); foreach ( $GLOBALS['wpp']['types'] as $k => $v ) { $r[ $k ] = get_post_type_object( $k ); } return 'names' === $out ? array_keys( $r ) : $r; }
function post_type_supports( $t, $f ) { return in_array( $f, $GLOBALS['wpp']['types'][ $t ]['supports'] ?? array(), true ); }
function is_post_type_hierarchical( $t ) { return ! empty( $GLOBALS['wpp']['types'][ $t ]['hier'] ); }
function get_object_taxonomies( $t ) { return $GLOBALS['wpp']['types'][ $t ]['tax'] ?? array(); }

function t_now( $gmt = false ) { return $gmt ? gmdate( 'Y-m-d H:i:s', $GLOBALS['t']['clock'] ?? time() ) : gmdate( 'Y-m-d H:i:s', $GLOBALS['t']['clock'] ?? time() ); }
function current_time( $type, $gmt = 0 ) { return 'timestamp' === $type ? ( $GLOBALS['t']['clock'] ?? time() ) : t_now(); }
function t_unique_slug( $slug, $type, $self = 0 ) {
	$try = $slug; $i = 2;
	$taken = function ( $s ) use ( $type, $self ) { foreach ( $GLOBALS['wpp']['posts'] as $p ) { if ( $p->ID !== $self && $p->post_name === $s && $p->post_type === $type ) { return true; } } return false; };
	while ( $taken( $try ) ) { $try = $slug . '-' . $i++; }
	return $try;
}
function t_slugify( $s ) { return trim( preg_replace( '/[^a-z0-9\x{0600}-\x{06FF}]+/u', '-', mb_strtolower( $s ) ), '-' ); }

function wp_insert_post( $postarr, $wp_error = false ) {
	$a = wp_unslash( $postarr ); // WordPress removes one level of slashes here.
	$now = t_now();
	$d = array_merge( array( 'post_author' => 1, 'post_content' => '', 'post_content_filtered' => '', 'post_title' => '', 'post_excerpt' => '', 'post_status' => 'draft', 'post_type' => 'post',
		'comment_status' => 'open', 'ping_status' => 'open', 'post_password' => '', 'post_name' => '', 'post_parent' => 0, 'menu_order' => 0,
		'post_date' => $now, 'post_date_gmt' => '', 'post_modified' => $now, 'post_modified_gmt' => $now ), $a );
	if ( empty( $a['post_date_gmt'] ) ) { $d['post_date_gmt'] = in_array( $d['post_status'], array( 'draft', 'pending', 'auto-draft' ), true ) ? '0000-00-00 00:00:00' : $d['post_date']; }
	if ( '' === $d['post_name'] && ! in_array( $d['post_status'], array( 'draft', 'pending', 'auto-draft' ), true ) ) { $d['post_name'] = t_slugify( $d['post_title'] ); }
	if ( '' !== $d['post_name'] ) { $d['post_name'] = t_unique_slug( $d['post_name'], $d['post_type'] ); }
	if ( ! isset( $GLOBALS['wpp']['types'][ $d['post_type'] ] ) ) { return $wp_error ? new WP_Error( 'invalid_post_type', 'Invalid post type.' ) : 0; }
	$id = $GLOBALS['wpp']['next']++;
	$d['ID'] = $id;
	$GLOBALS['wpp']['posts'][ $id ] = new WP_Post( $d );
	if ( 'post' === $d['post_type'] ) { $GLOBALS['wpp']['rel'][ $id ]['category'] = array( 1 ); } // the default category
	do_action( 'wp_insert_post', $id );
	return $id;
}
function wp_update_post( $postarr, $wp_error = false ) {
	$a = wp_unslash( $postarr );
	$id = (int) ( $a['ID'] ?? 0 );
	if ( ! isset( $GLOBALS['wpp']['posts'][ $id ] ) ) { return $wp_error ? new WP_Error( 'invalid_post', 'Invalid post.' ) : 0; }
	if ( isset( $GLOBALS['t']['fail_update'] ) ) { return new WP_Error( 'db_update_error', 'Could not update.' ); }
	foreach ( $a as $k => $v ) { if ( 'ID' !== $k ) { $GLOBALS['wpp']['posts'][ $id ]->$k = $v; } }
	$GLOBALS['wpp']['posts'][ $id ]->post_modified_gmt = t_now();
	$GLOBALS['wpp']['posts'][ $id ]->post_modified = t_now();
	wp_save_post_revision( $id );
	return $id;
}
function wp_save_post_revision( $id ) {
	$p = $GLOBALS['wpp']['posts'][ $id ] ?? null;
	if ( ! $p || ! post_type_supports( $p->post_type, 'revisions' ) ) { return null; }
	$revs = $GLOBALS['wpp']['revisions'][ $id ] ?? array(); $last = $revs ? end( $revs ) : null;
	if ( $last && $last['post_content'] === $p->post_content && $last['post_title'] === $p->post_title ) { return null; }
	$GLOBALS['wpp']['revisions'][ $id ][] = array( 'post_content' => $p->post_content, 'post_title' => $p->post_title );
	return count( $GLOBALS['wpp']['revisions'][ $id ] );
}
function wp_delete_post( $id, $force = false ) {
	$p = get_post( $id ); if ( ! $p ) { return false; }
	unset( $GLOBALS['wpp']['posts'][ $id ], $GLOBALS['wpp']['rel'][ $id ] );
	t_pdo()->exec( 'DELETE FROM wp_postmeta WHERE post_id=' . (int) $id );
	do_action( 'before_delete_post', $id );
	return $p;
}
function get_posts( $args = array() ) {
	$out = array();
	foreach ( $GLOBALS['wpp']['posts'] as $p ) {
		if ( isset( $args['post_parent'] ) && (int) $p->post_parent !== (int) $args['post_parent'] ) { continue; }
		if ( isset( $args['post_type'] ) && 'any' !== $args['post_type'] && ! in_array( $p->post_type, (array) $args['post_type'], true ) ) { continue; }
		if ( isset( $args['post_status'] ) && ! in_array( $p->post_status, (array) $args['post_status'], true ) ) { continue; }
		if ( isset( $args['meta_key'] ) && '' === (string) get_post_meta( $p->ID, $args['meta_key'], true ) ) { continue; }
		$out[] = clone $p;
	}
	usort( $out, function ( $a, $b ) { return array( $a->menu_order, $a->ID ) <=> array( $b->menu_order, $b->ID ); } );
	return array_slice( $out, 0, (int) ( $args['posts_per_page'] ?? 100 ) );
}

// ---- meta (stored in SQLite so that wpdb queries see it) ----
function get_post_meta( $id, $key = '', $single = false ) {
	$q = t_pdo()->prepare( 'SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id=? ' . ( '' !== $key ? 'AND meta_key=? ' : '' ) . 'ORDER BY meta_id' );
	$q->execute( '' !== $key ? array( $id, $key ) : array( $id ) );
	$rows = $q->fetchAll( PDO::FETCH_ASSOC );
	if ( '' === $key ) { $out = array(); foreach ( $rows as $r ) { $out[ $r['meta_key'] ][] = $r['meta_value']; } return $out; } // raw, as WordPress returns it
	$vals = array_map( function ( $r ) { return maybe_unserialize( $r['meta_value'] ); }, $rows );
	return $single ? ( $vals[0] ?? '' ) : $vals;
}
function add_post_meta( $id, $key, $value, $unique = false ) {
	$value = maybe_serialize( wp_unslash( $value ) ); // one level of slashes is removed
	if ( $unique && get_post_meta( $id, $key ) ) { return false; }
	$q = t_pdo()->prepare( 'INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?,?,?)' ); $q->execute( array( $id, $key, $value ) );
	return (int) t_pdo()->lastInsertId();
}
function update_post_meta( $id, $key, $value ) {
	delete_post_meta( $id, $key ); return add_post_meta( $id, $key, $value );
}
function delete_post_meta( $id, $key ) { $q = t_pdo()->prepare( 'DELETE FROM wp_postmeta WHERE post_id=? AND meta_key=?' ); $q->execute( array( $id, $key ) ); return true; }
function delete_post_meta_by_key( $key ) { $q = t_pdo()->prepare( 'DELETE FROM wp_postmeta WHERE meta_key=?' ); $q->execute( array( $key ) ); return true; }

// ---- terms ----
function wp_get_object_terms( $id, $tax, $args = array() ) {
	if ( ! isset( $GLOBALS['wpp']['terms'][ $tax ] ) ) { return new WP_Error( 'invalid_taxonomy', 'Invalid taxonomy.' ); }
	return $GLOBALS['wpp']['rel'][ $id ][ $tax ] ?? array();
}
function wp_set_object_terms( $id, $terms, $tax ) {
	$ids = array(); foreach ( (array) $terms as $t ) { $ids[] = is_int( $t ) ? $t : 0; }
	$GLOBALS['wpp']['rel'][ $id ][ $tax ] = array_values( array_filter( $ids ) );
	return $ids;
}

// ---- comments ----
function get_comments( $a = array() ) {
	$out = array(); foreach ( $GLOBALS['wpp']['comments'] as $c ) { if ( (int) $c->comment_post_ID === (int) $a['post_id'] ) { $out[] = clone $c; } }
	usort( $out, function ( $x, $y ) { return $x->comment_ID <=> $y->comment_ID; } ); return array_slice( $out, 0, (int) ( $a['number'] ?? 100 ) );
}
function wp_insert_comment( $d ) {
	$d = array_merge( array( 'comment_author' => '', 'comment_author_email' => '', 'comment_author_url' => '', 'comment_author_IP' => '', 'comment_date' => t_now(), 'comment_date_gmt' => t_now(), 'comment_content' => '', 'comment_karma' => 0, 'comment_approved' => '1', 'comment_agent' => '', 'comment_type' => 'comment', 'comment_parent' => 0, 'user_id' => 0 ), wp_unslash( $d ) ); $d['comment_ID'] = $GLOBALS['wpp']['next_comment']++; $GLOBALS['wpp']['comments'][ $d['comment_ID'] ] = (object) $d; return $d['comment_ID'];
}

// ---- screens and links ----
function get_edit_post_link( $id, $ctx = 'display' ) { return get_post( $id ) ? admin_url( 'post.php?post=' . (int) $id . '&action=edit' ) : null; }
function get_permalink( $p ) { $p = get_post( $p ); return $p ? 'https://example.test/?p=' . $p->ID : false; }
function add_meta_box( $id, $title, $cb, $type, $ctx = 'advanced', $prio = 'default' ) { $GLOBALS['t']['boxes'][ $type ][ $id ] = array( $title, $cb, $ctx ); }
function delete_post_meta_noop() {}

/** Make a post quickly for tests. */
function t_make( $a = array(), $meta = array(), $terms = array() ) {
	$id = wp_insert_post( wp_slash( array_merge( array( 'post_title' => 'Hello', 'post_content' => 'Body', 'post_status' => 'publish', 'post_type' => 'post' ), $a ) ) );
	foreach ( $meta as $k => $v ) { add_post_meta( $id, $k, wp_slash( $v ) ); }
	foreach ( $terms as $tax => $ids ) { wp_set_object_terms( $id, $ids, $tax ); }
	return $id;
}
