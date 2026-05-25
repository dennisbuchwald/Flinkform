<?php
/**
 * Webhook delivery log repository.
 *
 * Owns `{prefix}_perform_webhook_deliveries`. Separate from
 * `Webhooks\Repository` (which owns the configuration table) — the
 * dispatcher reads + writes this one heavily, the configuration
 * repo barely touches it (only on cascade-delete).
 *
 * Lifecycle states stored in the `status` column:
 *
 *   pending     queued, not yet dispatched (next_retry_at = NOW or NULL)
 *   processing  currently being dispatched — claimed by a worker
 *   retrying    dispatched, failed, scheduled for another attempt
 *   success     dispatched, 2xx response
 *   failed      retries exhausted (4 attempts total: 1 initial + 3 retries)
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Webhooks;

use PerForm\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Delivery log persistence.
 */
final class DeliveryRepository {

	/**
	 * Queue a new delivery for asynchronous dispatch.
	 *
	 * @param int      $webhook_id    Webhook id this delivery belongs to.
	 * @param int|null $submission_id Submission id (null for "Send test" runs).
	 * @return int|null Inserted row id, or null on failure.
	 */
	public function enqueue( int $webhook_id, ?int $submission_id ): ?int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$row = [
			'webhook_id'    => $webhook_id,
			'submission_id' => $submission_id,
			'status'        => 'pending',
			'response_code' => null,
			'response_body' => '',
			'attempt'       => 1,
			'next_retry_at' => $now,
			'created_at'    => $now,
			'updated_at'    => $now,
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( Schema::webhook_deliveries_table_name(), $row );
		if ( false === $inserted ) {
			return null;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Atomically claim a delivery for dispatch by transitioning its
	 * status from `pending`/`retrying` to `processing`. Returns true on
	 * successful claim, false if another worker (or a duplicate cron
	 * tick) got there first.
	 *
	 * Optimistic concurrency: WP-Cron can fire two ticks in quick
	 * succession when traffic is high and the every-minute schedule
	 * doesn't deduplicate perfectly. The status-transition predicate
	 * is the contention guard — only one UPDATE can flip the row.
	 *
	 * @param int $delivery_id Delivery row id.
	 * @return bool
	 */
	public function claim( int $delivery_id ): bool {
		global $wpdb;

		$table = Schema::webhook_deliveries_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET status = 'processing', updated_at = %s
				 WHERE id = %d AND status IN ('pending','retrying')",
				current_time( 'mysql', true ),
				$delivery_id
			)
		);

		return is_int( $rows ) && $rows > 0;
	}

	/**
	 * Mark a delivery as successfully delivered.
	 *
	 * @param int    $delivery_id Delivery row id.
	 * @param int    $response_code HTTP status code returned by the receiver.
	 * @param string $response_body Truncated response body for the log.
	 * @return void
	 */
	public function mark_success( int $delivery_id, int $response_code, string $response_body ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			Schema::webhook_deliveries_table_name(),
			[
				'status'        => 'success',
				'response_code' => $response_code,
				'response_body' => $response_body,
				'next_retry_at' => null,
				'updated_at'    => current_time( 'mysql', true ),
			],
			[ 'id' => $delivery_id ]
		);
	}

	/**
	 * Mark a delivery as failed for this attempt and either schedule a
	 * retry or terminally fail the row.
	 *
	 * Retry schedule (exponential-ish):
	 *   attempt 1 fail → retry in  1 min
	 *   attempt 2 fail → retry in  5 min
	 *   attempt 3 fail → retry in 30 min
	 *   attempt 4 fail → terminal failure
	 *
	 * @param int      $delivery_id    Delivery row id.
	 * @param int      $current_attempt 1-based attempt count that just failed.
	 * @param int|null $response_code   HTTP status code, or null when the request never reached the host.
	 * @param string   $response_body   Truncated response body (or the error message when no response).
	 * @return void
	 */
	public function mark_failure( int $delivery_id, int $current_attempt, ?int $response_code, string $response_body ): void {
		global $wpdb;

		$schedule = [
			1 => 60,      //  1 min
			2 => 300,     //  5 min
			3 => 1800,    // 30 min
		];

		if ( isset( $schedule[ $current_attempt ] ) ) {
			$next_status = 'retrying';
			$next_attempt = $current_attempt + 1;
			$next_retry_ts = time() + $schedule[ $current_attempt ];
			$next_retry_at = gmdate( 'Y-m-d H:i:s', $next_retry_ts );
		} else {
			$next_status   = 'failed';
			$next_attempt  = $current_attempt;
			$next_retry_at = null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			Schema::webhook_deliveries_table_name(),
			[
				'status'        => $next_status,
				'response_code' => $response_code,
				'response_body' => $response_body,
				'attempt'       => $next_attempt,
				'next_retry_at' => $next_retry_at,
				'updated_at'    => current_time( 'mysql', true ),
			],
			[ 'id' => $delivery_id ]
		);
	}

	/**
	 * Fetch up to $limit deliveries that are due for dispatch right
	 * now — both initial pending rows and retry rows whose
	 * `next_retry_at` has passed. Ordered by `next_retry_at` so the
	 * oldest backlog goes first.
	 *
	 * @param int $limit Maximum number of rows to return per tick.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_due( int $limit = 25 ): array {
		global $wpdb;

		$table = Schema::webhook_deliveries_table_name();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE status IN ('pending','retrying')
				 AND (next_retry_at IS NULL OR next_retry_at <= %s)
				 ORDER BY next_retry_at ASC
				 LIMIT %d",
				$now,
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	/**
	 * Fetch a single delivery row by id.
	 *
	 * @param int $id Delivery row id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$table = Schema::webhook_deliveries_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Take a `$wpdb` result row and produce the consumer-facing shape:
	 * typed ints + nullable response_code + truncated response_body
	 * already-truncated at the SQL TEXT boundary.
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$row['id']            = (int) $row['id'];
		$row['webhook_id']    = (int) $row['webhook_id'];
		$row['submission_id'] = isset( $row['submission_id'] ) ? (int) $row['submission_id'] : null;
		$row['response_code'] = isset( $row['response_code'] ) ? (int) $row['response_code'] : null;
		$row['attempt']       = (int) $row['attempt'];

		return $row;
	}
}
