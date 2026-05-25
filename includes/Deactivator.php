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
		// User data (submissions, webhooks, deliveries) must survive
		// deactivation — that contract belongs to uninstall.php only.
		//
		// Cron schedules, on the other hand, are runtime state. They
		// must clear on deactivation or WordPress keeps firing into a
		// hook with no registered callback — wastes ticks, and on
		// re-activation we'd schedule a second event alongside the
		// orphaned one. Clear unconditionally; activator re-schedules.
		wp_clear_scheduled_hook( Webhooks\Dispatcher::CRON_HOOK );
	}
}
