<?php
/**
 * Plugin Name:       Flinkform - Forms for the Block Editor
 * Plugin URI:        https://flinkform.de/
 * Description:       Block-native form builder for the WordPress Block Editor — theme.json styling, conditional logic, Interactivity API.
 * Version:           1.5.3
 * Requires at least: 6.5
 * Tested up to:      7.0
 * Requires PHP:      8.1
 * Author:            Dennis Buchwald
 * Author URI:        https://www.dennisbuchwald.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flinkform
 * Domain Path:       /languages
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 *
 * Single source of truth for version, paths and the plugin file. Used by
 * every subsystem (block registration, asset enqueueing, activation,
 * uninstall) so a version bump or a relocation only ever happens here.
 */
define( 'FLINKFORM_VERSION', '1.5.1' );
define( 'FLINKFORM_PLUGIN_FILE', __FILE__ );
define( 'FLINKFORM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLINKFORM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FLINKFORM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Register the PSR-4 autoloader before anything else touches the namespace.
require_once FLINKFORM_PLUGIN_DIR . 'includes/Autoloader.php';
\Flinkform\Autoloader::register();

/**
 * Boot the plugin runtime on `plugins_loaded`.
 *
 * Kept as a thin wrapper around the Plugin singleton so WordPress only sees
 * a plain callable on the hook — easier to unhook in tests and clearer in
 * stack traces than a closure.
 *
 * @return void
 */
function flinkform_bootstrap(): void {
	\Flinkform\Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'flinkform_bootstrap' );

register_activation_hook( __FILE__, [ \Flinkform\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \Flinkform\Deactivator::class, 'deactivate' ] );
