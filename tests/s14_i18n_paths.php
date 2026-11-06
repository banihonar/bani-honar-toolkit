<?php
// Where the Persian translation is read from: the bundled file, or the language pack that WordPress.org delivers.
require __DIR__ . '/harness.php';

$bundled = BHTK_PATH . 'languages/bani-honar-toolkit-fa_IR.mo';
$pack    = WP_LANG_DIR . '/plugins/bani-honar-toolkit-fa_IR.mo';
if ( ! is_dir( dirname( $pack ) ) ) {
	mkdir( dirname( $pack ), 0777, true );
}
if ( file_exists( $pack ) ) {
	unlink( $pack );
}

update_option( 'bhtk_settings', array( 'language' => 'fa' ) );

// 1. Persian chosen, no language pack: the bundled file is used when there is one, otherwise nothing is loaded and English shows.
$GLOBALS['t']['mo_loaded'] = array();
BHTK_I18n::load();
if ( is_readable( $bundled ) ) {
	t_ok( '1 bundled Persian file is used', $GLOBALS['t']['mo_loaded'] === array( $bundled ), json_encode( $GLOBALS['t']['mo_loaded'] ) );
} else {
	t_ok( '1 no bundled file and no language pack: nothing loaded, no error', array() === $GLOBALS['t']['mo_loaded'] );
}

// 2. Language pack from translate.wordpress.org (or copied by the owner) in wp-content/languages/plugins.
file_put_contents( $pack, 'x' );
$GLOBALS['t']['mo_loaded'] = array();
BHTK_I18n::load();
$expected = is_readable( $bundled ) ? $bundled : $pack;
t_ok( '2 ' . ( is_readable( $bundled ) ? 'bundled file wins over the language pack' : 'language pack is used when nothing is bundled' ), $GLOBALS['t']['mo_loaded'] === array( $expected ), json_encode( $GLOBALS['t']['mo_loaded'] ) );
if ( file_exists( $pack ) ) {
	unlink( $pack );
}

// 3. English chosen: no translation file is loaded at all.
update_option( 'bhtk_settings', array( 'language' => 'en' ) );
file_put_contents( $pack, 'x' );
$GLOBALS['t']['mo_loaded'] = array();
BHTK_I18n::load();
t_ok( '3 English loads no translation file', array() === $GLOBALS['t']['mo_loaded'] );
if ( file_exists( $pack ) ) {
	unlink( $pack );
}

// 4. The package for WordPress.org has no .po/.mo files.
if ( getenv( 'T_WPORG' ) ) {
	$found = array();
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( BHTK_PATH, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( preg_match( '/\.(po|mo)$/i', $f->getFilename() ) ) {
			$found[] = $f->getPathname();
		}
	}
	t_ok( '4 WordPress.org build has no .po/.mo files', array() === $found, implode( ', ', $found ) );
	t_ok( '4 the .pot template is still there', is_readable( BHTK_PATH . 'languages/bani-honar-toolkit.pot' ) );
}
