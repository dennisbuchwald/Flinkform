<?php
/**
 * Persistence for form submissions.
 *
 * Thin wrapper around $wpdb that owns reads and writes against the
 * `{prefix}_perform_submissions` table. Every input value flows through
 * prepared statements (via $wpdb->prepare or $wpdb->insert/update's
 * format arrays); identifiers and ORDER BY clauses are gated through
 * allow-lists.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Submissions;

use PerForm\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Read/write access to the submissions table.
 */
final class Repository {

	/**
	 * Allowed status values. Centralised so admin code can validate input
	 * against the same set the DB enforces.
	 */
	public const STATUSES = [ 'unread', 'read' ];

	/**
	 * Allowed sortable columns for admin list views.
	 */
	private const SORTABLE_COLUMNS = [ 'id', 'form_id', 'created_at', 'status' ];

	/**
	 * Insert a new submission row.
	 *
	 * @param string               $form_id UUID of the form.
	 * @param array<string, mixed> $data    Submission payload (any JSON-serialisable shape).
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public function save( string $form_id, array $data ) {
		global $wpdb;

		$json = wp_json_encode( $data );
		if ( ! is_string( $json ) ) {
			return false;
		}

		$inserted = $wpdb->insert(
			Schema::table_name(),
			[
				'form_id'    => $form_id,
				'data'       => $json,
				'created_at' => current_time( 'mysql', true ),
				'status'     => 'unread',
			],
			[ '%s', '%s', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch a single submission by ID. `data` is decoded back to an array.
	 *
	 * @param int $id Submission row ID.
	 * @return array{id: int, form_id: string, data: array<string, mixed>, created_at: string, status: string}|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$table = Schema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from controlled source.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, form_id, data, created_at, status FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->hydrate( $row );
	}

	/**
	 * Page through submissions, applying filters and sort order.
	 *
	 * @param array{form_id?: string, status?: string, date_from?: string, date_to?: string, search?: string} $filters
	 * @param int                                                                                              $page Page number (1-based).
	 * @param int                                                                                              $per_page Items per page.
	 * @param string                                                                                           $orderby Column to sort by.
	 * @param string                                                                                           $order ASC|DESC.
	 * @return array<int, array{id: int, form_id: string, data: array<string, mixed>, created_at: string, status: string}>
	 */
	public function find_paginated( array $filters, int $page, int $per_page, string $orderby = 'created_at', string $order = 'DESC' ): array {
		global $wpdb;

		$table       = Schema::table_name();
		[ $where, $args ] = $this->build_where( $filters );
		$orderby_sql = $this->sanitize_orderby( $orderby );
		$order_sql   = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';
		$offset      = max( 0, ( $page - 1 ) * $per_page );

		$args[] = $per_page;
		$args[] = $offset;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and ORDER BY validated above; values prepared.
		$sql  = "SELECT id, form_id, data, created_at, status FROM {$table} {$where} ORDER BY {$orderby_sql} {$order_sql} LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	/**
	 * Count submissions matching the given filters. Used for pagination.
	 *
	 * @param array{form_id?: string, status?: string, date_from?: string, date_to?: string, search?: string} $filters
	 * @return int
	 */
	public function count( array $filters = [] ): int {
		global $wpdb;

		$table             = Schema::table_name();
		[ $where, $args ]  = $this->build_where( $filters );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from controlled source.
		$sql = "SELECT COUNT(*) FROM {$table} {$where}";
		if ( empty( $args ) ) {
			$count = (int) $wpdb->get_var( $sql );
		} else {
			$count = (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
		}
		// phpcs:enable

		return $count;
	}

	/**
	 * Delete a single submission.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete( Schema::table_name(), [ 'id' => $id ], [ '%d' ] );
		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Delete a list of submissions. Returns the count actually removed.
	 *
	 * @param array<int, int> $ids
	 * @return int
	 */
	public function delete_many( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$table        = Schema::table_name();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name controlled; placeholders prepared.
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );

		return false === $deleted ? 0 : (int) $deleted;
	}

	/**
	 * Update a single submission's read/unread status.
	 *
	 * @param int    $id
	 * @param string $status One of self::STATUSES.
	 * @return bool
	 */
	public function update_status( int $id, string $status ): bool {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}

		global $wpdb;

		$updated = $wpdb->update(
			Schema::table_name(),
			[ 'status' => $status ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);

		return false !== $updated;
	}

	/**
	 * Bulk-update status. Returns the number of rows affected.
	 *
	 * @param array<int, int> $ids
	 * @param string          $status
	 * @return int
	 */
	public function update_status_many( array $ids, string $status ): int {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return 0;
		}

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		global $wpdb;

		$table        = Schema::table_name();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$args         = array_merge( [ $status ], $ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name controlled; placeholders prepared.
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s WHERE id IN ({$placeholders})", $args ) );

		return false === $updated ? 0 : (int) $updated;
	}

	/**
	 * Distinct form UUIDs present in the table. Used to populate the
	 * admin "filter by form" dropdown without bringing the whole table.
	 *
	 * @return array<int, string>
	 */
	public function distinct_form_ids(): array {
		global $wpdb;

		$table = Schema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from controlled source.
		$rows = $wpdb->get_col( "SELECT DISTINCT form_id FROM {$table} ORDER BY form_id ASC" );

		return is_array( $rows ) ? array_values( array_filter( array_map( 'strval', $rows ) ) ) : [];
	}

	/**
	 * Compose a WHERE clause + prepared args from the filter set.
	 *
	 * @param array{form_id?: string, status?: string, date_from?: string, date_to?: string, search?: string} $filters
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function build_where( array $filters ): array {
		$clauses = [];
		$args    = [];

		if ( ! empty( $filters['form_id'] ) && is_string( $filters['form_id'] ) ) {
			$clauses[] = 'form_id = %s';
			$args[]    = $filters['form_id'];
		}

		if ( ! empty( $filters['status'] ) && in_array( $filters['status'], self::STATUSES, true ) ) {
			$clauses[] = 'status = %s';
			$args[]    = $filters['status'];
		}

		if ( ! empty( $filters['date_from'] ) && is_string( $filters['date_from'] ) ) {
			$clauses[] = 'created_at >= %s';
			$args[]    = $filters['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) && is_string( $filters['date_to'] ) ) {
			$clauses[] = 'created_at <= %s';
			$args[]    = $filters['date_to'] . ' 23:59:59';
		}

		if ( ! empty( $filters['search'] ) && is_string( $filters['search'] ) ) {
			// LIKE against the JSON blob. Fine for the data volumes the
			// MVP is built for — a FULLTEXT index can come in a later
			// phase once dataset size justifies it.
			global $wpdb;
			$clauses[] = 'data LIKE %s';
			$args[]    = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		}

		if ( empty( $clauses ) ) {
			return [ '', [] ];
		}

		return [ 'WHERE ' . implode( ' AND ', $clauses ), $args ];
	}

	/**
	 * Allow-list ORDER BY column names to keep them out of any SQL string
	 * we interpolate directly.
	 *
	 * @param string $orderby
	 * @return string
	 */
	private function sanitize_orderby( string $orderby ): string {
		return in_array( $orderby, self::SORTABLE_COLUMNS, true ) ? $orderby : 'created_at';
	}

	/**
	 * Decode a raw $wpdb row into the canonical shape used by callers.
	 *
	 * @param array<string, mixed> $row
	 * @return array{id: int, form_id: string, data: array<string, mixed>, created_at: string, status: string}
	 */
	private function hydrate( array $row ): array {
		$decoded = json_decode( (string) ( $row['data'] ?? '' ), true );
		if ( ! is_array( $decoded ) ) {
			$decoded = [];
		}

		return [
			'id'         => (int) ( $row['id'] ?? 0 ),
			'form_id'    => (string) ( $row['form_id'] ?? '' ),
			'data'       => $decoded,
			'created_at' => (string) ( $row['created_at'] ?? '' ),
			'status'     => (string) ( $row['status'] ?? 'unread' ),
		];
	}
}
