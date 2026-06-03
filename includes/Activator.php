<?php
/**
 * Plugin activation handler.
 *
 * Runs once when the plugin is activated via the WordPress admin. Phase 0
 * is a no-op — Phase 1 will create the `{prefix}_perform_submissions`
 * table here via dbDelta and seed the schema version option for future
 * migrations (see PERFORM_SPEC.md §4.3 / §7).
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm;

defined( 'ABSPATH' ) || exit;

/**
 * Activation routines.
 */
final class Activator {

	/**
	 * Run the activation routine.
	 *
	 * Creates the submissions table via dbDelta and persists the schema
	 * version so future releases can migrate cleanly without re-running
	 * the full CREATE on every activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Database\Schema::create();

		// Daily submission-retention purge (storage limitation). Self-healed in
		// Plugin::init for the file-only update path where this never runs.
		if ( ! wp_next_scheduled( Submissions\Retention::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Submissions\Retention::CRON_HOOK );
		}

		// The webhook dispatcher cron lives with PerForm Pro now — Pro's own
		// activation handler creates the webhook tables and schedules the cron.
		// The free core only owns the submissions table.
	}
}
