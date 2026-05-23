<?php
/**
 * Plugin Name:       PerForm
 * Plugin URI:        https://wordpress.org/plugins/perform-forms/
 * Description:       Beautiful, native WordPress forms built for the block editor — fast, accessible, free.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Tested up to:      7.0
 * Requires PHP:      8.1
 * Author:            dbw media
 * Author URI:        https://dbw-media.de
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       perform-forms
 * Domain Path:       /languages
 *
 * @package PerForm
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
define( 'PERFORM_VERSION', '0.1.0' );
define( 'PERFORM_PLUGIN_FILE', __FILE__ );
define( 'PERFORM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PERFORM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PERFORM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Register the PSR-4 autoloader before anything else touches the namespace.
require_once PERFORM_PLUGIN_DIR . 'includes/Autoloader.php';
\PerForm\Autoloader::register();

/**
 * Boot the plugin runtime on `plugins_loaded`.
 *
 * Kept as a thin wrapper around the Plugin singleton so WordPress only sees
 * a plain callable on the hook — easier to unhook in tests and clearer in
 * stack traces than a closure.
 *
 * @return void
 */
function perform_bootstrap(): void {
	\PerForm\Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'perform_bootstrap' );

register_activation_hook( __FILE__, [ \PerForm\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \PerForm\Deactivator::class, 'deactivate' ] );
