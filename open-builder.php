<?php
/**
 * Plugin Name:       Open Builder
 * Plugin URI:        https://example.com/open-builder
 * Description:       An original, open-source drag-and-drop visual website builder for WordPress. Live editor, responsive controls, global variables, widgets, and a form builder.
 * Version:           1.8.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Open Builder
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       open-builder
 * Domain Path:       /languages
 *
 * Open Builder is original software. It is not affiliated with, derived from, or
 * containing any code from Breakdance, Elementor, or any other commercial builder.
 */

defined( 'ABSPATH' ) || exit;

define( 'OPENB_VERSION', '1.8.0' );
define( 'OPENB_FILE', __FILE__ );
define( 'OPENB_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPENB_URL', plugin_dir_url( __FILE__ ) );
define( 'OPENB_MIN_PHP', '8.0' );

// Hard stop on unsupported PHP rather than fataling on modern syntax.
if ( version_compare( PHP_VERSION, OPENB_MIN_PHP, '<' ) ) {
	add_action( 'admin_notices', function () {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( sprintf(
				/* translators: 1: required PHP version, 2: current PHP version */
				__( 'Open Builder requires PHP %1$s or higher. You are running %2$s.', 'open-builder' ),
				OPENB_MIN_PHP,
				PHP_VERSION
			) )
		);
	} );
	return;
}

require_once OPENB_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, [ '\OpenBuilder\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ '\OpenBuilder\Plugin', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
	\OpenBuilder\Plugin::instance()->boot();
} );
