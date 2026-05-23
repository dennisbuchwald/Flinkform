<?php
/**
 * Persistence for form submissions.
 *
 * Thin wrapper around $wpdb that owns the INSERT into the
 * `{prefix}_perform_submissions` table. Always uses prepared values via the
 * $wpdb->insert() format array.
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
	 * Insert a new submission row.
	 *
	 * @param string               $form_id UUID of the form.
	 * @param array<string, mixed> $data    Field data, will be JSON-encoded.
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
}
