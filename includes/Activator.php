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

		// Webhook dispatcher cron — every-minute schedule. The
		// `perform_every_minute` schedule is normally added to
		// wp_get_schedules() by Dispatcher::register_schedule() on the
		// `cron_schedules` filter. That filter is registered inside
		// Plugin::init(), which runs on `plugins_loaded` — and
		// `plugins_loaded` does NOT fire during plugin activation
		// requests. So we add the same filter inline here, just for
		// the activation tick, so wp_schedule_event sees the schedule
		// it needs.
		add_filter(
			'cron_schedules',
			static function ( array $schedules ): array {
				if ( ! isset( $schedules[ Webhooks\Dispatcher::CRON_SCHEDULE ] ) ) {
					$schedules[ Webhooks\Dispatcher::CRON_SCHEDULE ] = [
						'interval' => 60,
						'display'  => __( 'Every Minute (PerForm)', 'perform-forms' ),
					];
				}
				return $schedules;
			}
		);

		if ( ! wp_next_scheduled( Webhooks\Dispatcher::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, Webhooks\Dispatcher::CRON_SCHEDULE, Webhooks\Dispatcher::CRON_HOOK );
		}
	}
}
