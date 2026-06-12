<?php
/**
 * Plugin deactivation handler.
 *
 * Runs when the plugin is deactivated. Deliberately a no-op: deactivation
 * must not destroy user data — that contract belongs to `uninstall.php`,
 * which only fires when the user explicitly deletes the plugin.
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform;

defined( 'ABSPATH' ) || exit;

/**
 * Deactivation routines.
 */
final class Deactivator {

	/**
	 * Run the deactivation routine.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clear the retention-purge cron — runtime state, must not keep firing
		// into a hook with no callback once the plugin is inactive. Submission
		// DATA is untouched (that contract belongs to uninstall.php). The
		// webhook dispatcher cron is cleared by Flinkform Pro's own deactivator.
		wp_clear_scheduled_hook( Submissions\Retention::CRON_HOOK );
	}
}
