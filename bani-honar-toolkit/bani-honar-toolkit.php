<?php
/**
 * Plugin Name:       Bani Honar Toolkit
 * Description:       A multi-purpose, modular and extensible WordPress plugin: link shortener, QR code generator, page shield, visitor statistics, post duplicator, local profile pictures and more.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Bani Honar Advertising Agency
 * Author URI:        https://banihonarpars.ir
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bani-honar-toolkit
 * Domain Path:       /languages
 *
 * @package BaniHonarToolkit
 *
 * Bani Honar Toolkit
 * Copyright (C) Bani Honar Advertising Agency (https://banihonarpars.ir)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any
 * later version. It is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY. See license.txt.
 *
 * The names "Bani Honar", "Bani Honar Pars" and the logo are brand marks of Bani Honar Advertising Agency and are
 * not licensed with the code. If you publish a modified version, give it another name and logo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			// Shown only to people who can act on it, and only because the plugin cannot start.
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>Bani Honar Toolkit requires PHP 7.4 or newer.</p></div>';
			}
		}
	);
	return;
}

define( 'BHTK_VERSION', '1.0.0' );
define( 'BHTK_FILE', __FILE__ );
define( 'BHTK_PATH', plugin_dir_path( __FILE__ ) );
define( 'BHTK_URL', plugin_dir_url( __FILE__ ) );
define( 'BHTK_AUTHOR_URL', 'https://banihonarpars.ir' );

require_once BHTK_PATH . 'includes/class-bhtk-i18n.php';
require_once BHTK_PATH . 'includes/class-bhtk-calendar.php';
require_once BHTK_PATH . 'includes/class-bhtk-module.php';
require_once BHTK_PATH . 'includes/class-bhtk-contact.php';
require_once BHTK_PATH . 'includes/class-bhtk-privacy.php';
require_once BHTK_PATH . 'includes/class-bhtk-plugin.php';
require_once BHTK_PATH . 'includes/class-bhtk-migrator.php';
require_once BHTK_PATH . 'includes/class-bhtk-admin.php';
require_once BHTK_PATH . 'modules/link-shortener/class-bhtk-links.php';
require_once BHTK_PATH . 'modules/link-shortener/class-bhtk-links-page.php';
require_once BHTK_PATH . 'modules/link-shortener/class-bhtk-module-link-shortener.php';
require_once BHTK_PATH . 'modules/qr-generator/class-bhtk-module-qr-generator.php';
require_once BHTK_PATH . 'modules/code-shield/class-bhtk-module-code-shield.php';
require_once BHTK_PATH . 'modules/local-avatars/class-bhtk-module-local-avatars.php';
require_once BHTK_PATH . 'modules/visitor-stats/class-bhtk-countries.php';
require_once BHTK_PATH . 'modules/visitor-stats/class-bhtk-ua.php';
require_once BHTK_PATH . 'modules/visitor-stats/class-bhtk-referrer.php';
require_once BHTK_PATH . 'modules/visitor-stats/class-bhtk-geo.php';
require_once BHTK_PATH . 'modules/visitor-stats/class-bhtk-visits.php';
require_once BHTK_PATH . 'modules/visitor-stats/class-bhtk-blocklist.php';
require_once BHTK_PATH . 'modules/visitor-stats/class-bhtk-visits-page.php';
require_once BHTK_PATH . 'modules/visitor-stats/class-bhtk-module-visitor-stats.php';
require_once BHTK_PATH . 'modules/post-duplicator/class-bhtk-cloner.php';
require_once BHTK_PATH . 'modules/post-duplicator/class-bhtk-clone-ui.php';
require_once BHTK_PATH . 'modules/post-duplicator/class-bhtk-module-post-duplicator.php';

register_activation_hook( __FILE__, array( 'BHTK_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BHTK_Plugin', 'deactivate' ) );

BHTK_Plugin::instance()->run();
