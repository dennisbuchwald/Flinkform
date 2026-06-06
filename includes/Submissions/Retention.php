<?php
/**
 * Submission retention / auto-purge (GDPR Art. 5(1)(e) — storage limitation).
 *
 * Each form may set a retention period (the `retentionDays` block attribute,
 * 0 = keep forever). A daily cron deletes submissions older than that period.
 *
 * Deletion runs through `Submissions\Repository::delete_many()`, so the
 * `perffo_submissions_deleted` action fires and any related PerForm Pro
 * webhook delivery rows are cascade-deleted too — no orphaned personal data.
 *
 * The per-form retention values are read from the form index (`Forms\Indexer`),
 * which already tracks every form; no separate store is maintained.
 *
 * @package PerForm
 * @since 0.2.7
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace PerForm\Submissions;

use PerForm\Forms\Indexer;

defined( 'ABSPATH' ) || exit;

/**
 * Cron-driven retention purge.
 */
final class Retention {

	/**
	 * Daily cron hook that runs the purge.
	 */
	public const CRON_HOOK = 'perffo_purge_submissions';

	/**
	 * Max rows deleted per form per run — keeps a single cron tick bounded on
	 * a backlog; the daily schedule drains any remainder over subsequent runs.
	 */
	private const PER_FORM_CAP = 1000;

	/**
	 * Hook the cron callback. Scheduling lives in Activator + the
	 * Plugin::init self-heal (so a file-only update still schedules it).
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, [ $this, 'purge' ] );
	}

	/**
	 * Delete submissions older than each form's retention period.
	 *
	 * @return void
	 */
	public function purge(): void {
		$forms = ( new Indexer() )->all();
		$repo  = new Repository();

		foreach ( $forms as $form ) {
			$days = isset( $form['retention_days'] ) ? (int) $form['retention_days'] : 0;
			if ( $days < 1 ) {
				continue; // 0 (or unset) = keep forever.
			}

			$form_id = isset( $form['form_id'] ) ? (string) $form['form_id'] : '';
			if ( '' === $form_id ) {
				continue;
			}

			// created_at is stored in GMT (current_time('mysql', true)); match it.
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
			$ids    = $repo->find_ids_older_than( $form_id, $cutoff, self::PER_FORM_CAP );

			if ( ! empty( $ids ) ) {
				// delete_many() fires perffo_submissions_deleted → Pro cascade.
				$repo->delete_many( $ids );
			}
		}
	}
}
