<?php
/**
 * Minimal WordPress stand-in for testing the plugin without WordPress.
 * Database: SQLite through PDO. Options and transients: a JSON file shared between runs.
 */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
set_error_handler( function ( $no, $str, $file, $line ) {
	if ( $no & ( E_DEPRECATED | E_USER_DEPRECATED ) ) {
		fwrite( STDERR, "DEPRECATED: $str at $file:$line\n" );
		return true;
	}
	throw new ErrorException( $str, 0, $no, $file, $line );
} );

define( 'ABSPATH', '/fake-wp/' );
define( 'WP_LANG_DIR', ( getenv( 'T_DIR' ) ?: sys_get_temp_dir() ) . '/wp-lang' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );

$GLOBALS['t'] = array(
	'db'        => getenv( 'T_DIR' ) . '/test.sqlite',
	'opts_file' => getenv( 'T_DIR' ) . '/opts.json',
	'hooks'     => array(),
	'menu'      => array(),
	'scripts'   => array(),
	'can'       => true,
	'logged_in' => false,
	'admin'     => false,
	'http'      => null,
	'lang'      => 'fa',
	'status'    => null,
	'cron'      => array(),
);
$store = is_file( $GLOBALS['t']['opts_file'] ) ? json_decode( file_get_contents( $GLOBALS['t']['opts_file'] ), true ) : array();
$GLOBALS['t']['opts']  = isset( $store['opts'] ) ? $store['opts'] : array();
$GLOBALS['t']['trans'] = isset( $store['trans'] ) ? $store['trans'] : array();
$GLOBALS['t']['cron']  = isset( $store['cron'] ) ? $store['cron'] : array();
register_shutdown_function( function () {
	file_put_contents( $GLOBALS['t']['opts_file'], json_encode( array( 'opts' => $GLOBALS['t']['opts'], 'trans' => $GLOBALS['t']['trans'], 'cron' => $GLOBALS['t']['cron'] ) ) );
} );

class RedirectException extends Exception {}
class WPDieException extends Exception {}
class WP_Error { public $m; public $c; public $d; function __construct( $c = '', $m = '', $d = '' ) { $this->c = $c; $this->m = $m; $this->d = $d; } function get_error_message() { return $this->m; } function get_error_code() { return $this->c; } function get_error_data() { return $this->d; } }
class NOOP_Translations {}

// ---- hooks ----
function add_action( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['t']['hooks'][ $tag ][] = $cb; return true; }
function add_filter( $tag, $cb, $prio = 10, $args = 1 ) { return add_action( $tag, $cb ); }
function do_action( $tag, ...$a ) { foreach ( $GLOBALS['t']['hooks'][ $tag ] ?? array() as $cb ) { call_user_func_array( $cb, $a ); } }
function apply_filters( $tag, $value, ...$a ) { foreach ( $GLOBALS['t']['hooks'][ $tag ] ?? array() as $cb ) { $value = call_user_func_array( $cb, array_merge( array( $value ), $a ) ); } return $value; }
function register_activation_hook() {}
function register_deactivation_hook() {}
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://banihonarpars.ir/wp-content/plugins/bani-honar-toolkit/'; }
function plugin_basename( $f ) { return 'bani-honar-toolkit/bani-honar-toolkit.php'; }

// ---- i18n ----
function __( $s, $d = '' ) { static $c = null; if ( null === $c ) { $f = getenv( 'T_DIR' ) . '/fa_catalog.json'; $c = ( getenv( 'T_FA' ) && is_file( $f ) ) ? json_decode( file_get_contents( $f ), true ) : array(); } return isset( $c[ $s ] ) ? $c[ $s ] : $s; }
function _e( $s, $d = '' ) { echo __( $s ); }
function esc_html__( $s, $d = '' ) { return esc_html( __( $s ) ); }
function esc_html_e( $s, $d = '' ) { echo esc_html( __( $s ) ); }
function esc_attr__( $s, $d = '' ) { return esc_attr( __( $s ) ); }
function esc_attr_e( $s, $d = '' ) { echo esc_attr( __( $s ) ); }
function unload_textdomain() {}
function load_textdomain( $domain = '', $mofile = '' ) { $GLOBALS['t']['mo_loaded'][] = $mofile; return true; }
function determine_locale() { return 'fa_IR'; }
function get_user_locale() { return $GLOBALS['t']['lang'] === 'fa' ? 'fa_IR' : 'en_US'; }
function number_format_i18n( $n, $d = 0 ) { return number_format( $n, $d ); }

// ---- escaping and cleaning ----
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return str_replace( '&', '&#038;', (string) $s ); }
function esc_url_raw( $s ) { return (string) $s; }
function wp_kses( $s, $allowed ) { return strip_tags( $s, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
function wp_kses_post( $s ) { return $s; }
function wp_unslash( $v ) { if ( is_array( $v ) ) { return array_map( 'wp_unslash', $v ); } return is_string( $v ) ? stripslashes( $v ) : $v; }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function absint( $n ) { return abs( (int) $n ); }
function checked( $a, $b = true, $e = true ) { $r = ( (string) $a === (string) $b ) ? ' checked="checked"' : ''; if ( $e ) { echo $r; } return $r; }
function selected( $a, $b = true, $e = true ) { $r = ( (string) $a === (string) $b ) ? ' selected="selected"' : ''; if ( $e ) { echo $r; } return $r; }
function wp_json_encode( $d ) { return json_encode( $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }

function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_check_invalid_utf8( $s ) { return $s; }
function wp_date( $f, $ts = null ) { return gmdate( $f, $ts ?? time() ); }
function wp_get_current_user() { return (object) array( 'ID' => 1 ); }
function wp_delete_file( $f ) {}
function wp_upload_dir() { return array( 'basedir' => '/tmp' ); }
function get_userdata( $id = 0 ) { $u = array( 1 => array( 'Sara Ahmadi', 'sara', 'sara@example.com' ), 2 => array( 'Ali <b>', 'ali', 'ali@example.com' ) ); return isset( $u[ (int) $id ] ) ? (object) array( 'display_name' => $u[ (int) $id ][0], 'user_login' => $u[ (int) $id ][1], 'user_email' => $u[ (int) $id ][2] ) : false; }
// ---- urls ----
function admin_url( $p = '' ) { return 'https://banihonarpars.ir/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://banihonarpars.ir' . $p; }
// Like WordPress: values are NOT encoded by add_query_arg.
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) { $new = $args[0]; $url = $args[1] ?? ( $_SERVER['REQUEST_URI'] ?? '/' ); }
	else { $new = array( $args[0] => $args[1] ); $url = $args[2] ?? ( $_SERVER['REQUEST_URI'] ?? '/' ); }
	$base = preg_replace( '/\?.*$/', '', $url );
	$qs   = array();
	if ( false !== strpos( $url, '?' ) ) { parse_str( substr( $url, strpos( $url, '?' ) + 1 ), $qs ); }
	$qs = array_merge( $qs, $new );
	$parts = array();
	foreach ( $qs as $k => $v ) { if ( null === $v ) { continue; } $parts[] = $k . '=' . $v; }
	return $base . ( $parts ? '?' . implode( '&', $parts ) : '' );
}
function paginate_links( $a ) { return '<span class="page-numbers current">' . $a['current'] . '</span> of ' . $a['total']; }
function wp_safe_redirect( $u ) { throw new RedirectException( $u ); }
function wp_die( $m = '', $t = '', $a = array() ) { throw new WPDieException( (string) $m ); }
function status_header( $c ) { $GLOBALS['t']['status'] = $c; }
function nocache_headers() {}

// ---- users and nonces ----
function current_user_can( $c, ...$a ) { return isset( $GLOBALS['t']['cap_fn'] ) ? (bool) call_user_func( $GLOBALS['t']['cap_fn'], $c, ...$a ) : $GLOBALS['t']['can']; }
function is_user_logged_in() { return $GLOBALS['t']['logged_in']; }
function get_current_user_id() { return isset( $GLOBALS['t']['uid'] ) ? $GLOBALS['t']['uid'] : 1; }
function wp_create_nonce( $a ) { return 'nonce:' . $a; }
function wp_nonce_field( $a, $n = '_wpnonce' ) { echo '<input type="hidden" name="' . $n . '" value="nonce:' . $a . '" />'; }
function check_admin_referer( $a, $n = '_wpnonce' ) { if ( ( $_REQUEST[ $n ] ?? $_POST[ $n ] ?? $_GET[ $n ] ?? '' ) !== 'nonce:' . $a ) { throw new WPDieException( 'bad nonce for ' . $a ); } return 1; }
function wp_salt( $s = '' ) { return 'test-salt'; }

// ---- context ----
function is_admin() { return $GLOBALS['t']['admin']; }
function is_feed() { return false; }
function is_customize_preview() { return false; }
function is_preview() { return false; }
function wp_doing_ajax() { return false; }
function is_rtl() { return false; }
function wp_timezone() { return new DateTimeZone( 'Asia/Tehran' ); }
function get_option_permalink() { return '/%postname%/'; }

// ---- options / transients / cron ----
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['t']['opts'] ) ? $GLOBALS['t']['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['t']['opts'][ $k ] = json_decode( json_encode( $v ), true ); return true; }
function delete_option( $k ) { unset( $GLOBALS['t']['opts'][ $k ] ); return true; }
function get_transient( $k ) { $x = $GLOBALS['t']['trans'][ $k ] ?? null; return ( $x && $x['exp'] > time() ) ? $x['v'] : false; }
function set_transient( $k, $v, $ttl = 0 ) { $GLOBALS['t']['trans'][ $k ] = array( 'v' => $v, 'exp' => time() + $ttl ); return true; }
function delete_transient( $k ) { unset( $GLOBALS['t']['trans'][ $k ] ); return true; }
function wp_next_scheduled( $h ) { return $GLOBALS['t']['cron'][ $h ] ?? false; }
function wp_schedule_event( $ts, $rec, $h ) { $GLOBALS['t']['cron'][ $h ] = $ts; return true; }
function wp_clear_scheduled_hook( $h ) { unset( $GLOBALS['t']['cron'][ $h ] ); return 1; }

// ---- assets and menus ----
function wp_enqueue_script( $h, $src = '', ...$r ) { $GLOBALS['t']['scripts'][ $h ] = $src; }
function wp_enqueue_style( $h, $src = '', ...$r ) {}
function wp_add_inline_script( $h, $code, $pos = 'after' ) { $GLOBALS['t']['inline'][ $h ] = $code; }
function wp_localize_script() {}
function add_menu_page( ...$a ) { $GLOBALS['t']['menu'][] = array( 'page', $a[3] ); }
function add_submenu_page( $parent, $title, $label, $cap, $slug, $cb = null ) { $GLOBALS['t']['menu'][] = array( 'sub', $slug, $title ); }

// ---- http ----
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['t']['http_log'][] = $url;
	$f = $GLOBALS['t']['http'];
	return $f ? $f( $url ) : new WP_Error( 'no_http', 'offline' );
}
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }

// ---- database: SQLite behind the parts of wpdb the plugin uses ----
class T_WPDB {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $postmeta = 'wp_postmeta';
	public $posts = 'wp_posts';
	private $pdo;
	function __construct( $file ) { $this->pdo = new PDO( 'sqlite:' . $file ); $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION ); $this->pdo->exec( 'CREATE TABLE IF NOT EXISTS wp_postmeta (meta_id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, meta_key TEXT, meta_value TEXT)' ); }
	function get_charset_collate() { return ''; }
	function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
		$i = 0;
		$out = preg_replace_callback( '/%[sd]/', function ( $m ) use ( &$i, $args ) {
			if ( ! array_key_exists( $i, $args ) ) { throw new Exception( 'prepare: not enough args for ' . $m[0] ); }
			$v = $args[ $i++ ];
			return '%d' === $m[0] ? (string) (int) $v : $this->pdo->quote( (string) $v );
		}, $sql );
		if ( $i !== count( $args ) ) { throw new Exception( "prepare: $i placeholders but " . count( $args ) . ' args in: ' . $sql ); }
		return $out;
	}
	function query( $sql ) { return $this->pdo->exec( $sql ); }
	function get_var( $sql ) {
		if ( preg_match( "/^SHOW TABLES LIKE '(.+)'$/", $sql, $m ) ) {
			$name = str_replace( '\\', '', $m[1] );
			$r = $this->pdo->query( "SELECT name FROM sqlite_master WHERE type='table' AND name=" . $this->pdo->quote( $name ) )->fetchColumn();
			return false === $r ? null : $r;
		}
		$r = $this->pdo->query( $sql )->fetchColumn();
		return false === $r ? null : $r;
	}
	function get_row( $sql, $out = OBJECT ) { $r = $this->pdo->query( $sql )->fetch( PDO::FETCH_ASSOC ); return $r ? ( ARRAY_A === $out ? $r : (object) $r ) : null; }
	function get_results( $sql, $out = OBJECT ) { $rows = $this->pdo->query( $sql )->fetchAll( PDO::FETCH_ASSOC ); return ARRAY_A === $out ? $rows : array_map( function ( $r ) { return (object) $r; }, $rows ); }
	function get_col( $sql ) { return $this->pdo->query( $sql )->fetchAll( PDO::FETCH_COLUMN ); }
	function insert( $table, $data, $formats = null ) {
		$cols = implode( ',', array_keys( $data ) );
		$vals = array();
		$f = $formats ? array_values( $formats ) : array();
		$i = 0;
		foreach ( $data as $v ) { $vals[] = ( ( $f[ $i ] ?? '%s' ) === '%d' ) ? (string) (int) $v : $this->pdo->quote( (string) $v ); $i++; }
		$this->pdo->exec( "INSERT INTO $table ($cols) VALUES (" . implode( ',', $vals ) . ')' );
		$this->insert_id = (int) $this->pdo->lastInsertId();
		return 1;
	}
	function update( $table, $data, $where, $f = null, $wf = null ) {
		$set = array(); foreach ( $data as $k => $v ) { $set[] = "$k=" . $this->pdo->quote( (string) $v ); }
		$w = array(); foreach ( $where as $k => $v ) { $w[] = "$k=" . $this->pdo->quote( (string) $v ); }
		return $this->pdo->exec( "UPDATE $table SET " . implode( ',', $set ) . ' WHERE ' . implode( ' AND ', $w ) );
	}
	function delete( $table, $where, $wf = null ) {
		$w = array(); foreach ( $where as $k => $v ) { $w[] = "$k=" . $this->pdo->quote( (string) $v ); }
		return $this->pdo->exec( "DELETE FROM $table WHERE " . implode( ' AND ', $w ) );
	}
	function raw() { return $this->pdo; }
}
$GLOBALS['wpdb'] = new T_WPDB( $GLOBALS['t']['db'] );
function dbDelta( $sql ) {
	// Translate the MySQL CREATE TABLE of the plugin into SQLite.
	$sql = str_replace( ' unsigned', '', $sql );
	$sql = preg_replace( '/\bid bigint\(20\) NOT NULL AUTO_INCREMENT,/', 'id INTEGER PRIMARY KEY AUTOINCREMENT,', $sql );
	$sql = preg_replace( '/^\s*(PRIMARY KEY|UNIQUE KEY|KEY)\b.*$/m', '', $sql );
	$sql = preg_replace( '/,\s*\)\s*;?\s*$/', "\n)", $sql );
	$sql = preg_replace( '/,\s*\n\s*\n\s*\)/', "\n)", $sql );
	$sql = preg_replace( '/,(\s*)\)/', '$1)', $sql );
	$sql = str_replace( 'CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $sql );
	$GLOBALS['wpdb']->raw()->exec( $sql );
}
// dbDelta lives in wp-admin/includes/upgrade.php in WordPress; the plugin requires it, so provide an empty file name hook.
if ( ! is_dir( '/fake-wp/wp-admin/includes' ) ) { @mkdir( '/fake-wp/wp-admin/includes', 0777, true ); }
if ( ! is_file( '/fake-wp/wp-admin/includes/upgrade.php' ) ) { @file_put_contents( '/fake-wp/wp-admin/includes/upgrade.php', "<?php\n" ); }

// ---- load the plugin ----
$plugin_dir = getenv( 'PLUGIN_DIR' );
require $plugin_dir . '/bani-honar-toolkit.php';
do_action( 'plugins_loaded' );
do_action( 'init' );

function t_out( callable $fn ) { ob_start(); try { $fn(); } finally { $html = ob_get_clean(); } return $html; }
function t_ok( $name, $cond, $extra = '' ) { static $fails = 0; echo ( $cond ? 'OK   ' : 'FAIL ' ) . $name . ( $cond ? '' : "  $extra" ) . "\n"; if ( ! $cond ) { $GLOBALS['t_fails'] = ( $GLOBALS['t_fails'] ?? 0 ) + 1; } }
function wp_nonce_url( $url, $action, $name = '_wpnonce' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $name . '=nonce:' . $action; }
function wp_get_referer() { return $GLOBALS['t']['referer'] ?? false; }
function esc_sql( $d ) { return is_array( $d ) ? array_map( 'esc_sql', $d ) : addslashes( (string) $d ); }
function get_bloginfo( $k = '' ) { return 'version' === $k ? '6.8.1' : 'Test site'; }
