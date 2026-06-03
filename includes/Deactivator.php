<?php
/**
 * Plugin deactivation handler.
 *
 * Runs when the plugin is deactivated. Deliberately a no-op: deactivation
 * must not destroy user data — that contract belongs to `uninstall.php`,
 * which only fires when the user explicitly deletes the plugin.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm;

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
		// No-op. Submission data must survive deactivation — that contract
		// belongs to uninstall.php. The only runtime state the plugin used to
		// clear here was the webhook dispatcher cron, which now lives with
		// PerForm Pro and is cleared by Pro's own deactivation handler.
		//
		// Intentionally left empty.
	}
}
