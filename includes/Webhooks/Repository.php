<?php
/**
 * Webhook repository — CRUD against `{prefix}_perform_webhooks`.
 *
 * Single point of access to webhook rows. Every method returns plain
 * PHP arrays (not Webhook objects) because every consumer of this data
 * — the REST controller, the dispatcher, the admin log — needs to
 * serialise it back to JSON or feed it to wp_remote_request anyway, so
 * an extra DTO layer would buy nothing.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Webhooks;

use PerForm\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Webhook persistence.
 */
final class Repository {

	private const ALLOWED_METHODS = [ 'POST', 'GET' ];
	private const ALLOWED_FORMATS = [ 'json', 'form' ];

	/**
	 * Insert a webhook configuration.
	 *
	 * @param array<string, mixed> $data Already sanitised input.
	 * @return int|null Inserted row id, or null on failure.
	 */
	public function create( array $data ): ?int {
		global $wpdb;

		$row = $this->normalise( $data );
		$now = current_time( 'mysql', true );

		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( Schema::webhooks_table_name(), $row );
		if ( false === $inserted ) {
			return null;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a webhook by id.
	 *
	 * @param int                  $id   Webhook id.
	 * @param array<string, mixed> $data Already sanitised input.
	 * @return bool True on success.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$row               = $this->normalise( $data );
		$row['updated_at'] = current_time( 'mysql', true );
		unset( $row['created_at'] ); // Never overwrite the creation timestamp.

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update(
			Schema::webhooks_table_name(),
			$row,
			[ 'id' => $id ]
		);

		return false !== $updated;
	}

	/**
	 * Delete a webhook by id. Cascades to its deliveries.
	 *
	 * @param int $id Webhook id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		// Drop the delivery log entries for this webhook first so we
		// don't leave orphan rows behind. Cron-driven retries already
		// guard against missing webhooks, but cleaning up keeps the log
		// query plans honest.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete(
			Schema::webhook_deliveries_table_name(),
			[ 'webhook_id' => $id ]
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->delete( Schema::webhooks_table_name(), [ 'id' => $id ] );

		return false !== $result && $result > 0;
	}

	/**
	 * Fetch a single webhook as a hydrated array.
	 *
	 * @param int $id Webhook id.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$table = Schema::webhooks_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( null === $row ) {
			return null;
		}

		return $this->hydrate( $row );
	}

	/**
	 * Fetch every webhook attached to a form.
	 *
	 * @param string $form_id Form UUID.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_for_form( string $form_id ): array {
		global $wpdb;

		$table = Schema::webhooks_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE form_id = %s ORDER BY id ASC", $form_id ),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	/**
	 * Fetch every active webhook attached to a form. Used by the
	 * dispatcher when a submission lands.
	 *
	 * @param string $form_id Form UUID.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_active_for_form( string $form_id ): array {
		global $wpdb;

		$table = Schema::webhooks_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE form_id = %s AND is_active = 1 ORDER BY id ASC", $form_id ),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( [ $this, 'hydrate' ], $rows );
	}

	/**
	 * Coerce an input array into the column shape `$wpdb->insert/update`
	 * expects. Treats both JSON-string and array inputs for `headers` /
	 * `field_mapping` so the REST controller can pass parsed JSON
	 * directly and the dispatcher can pass arrays.
	 *
	 * @param array<string, mixed> $data Raw input.
	 * @return array<string, mixed>
	 */
	private function normalise( array $data ): array {
		$method = isset( $data['method'] ) && is_string( $data['method'] )
			? strtoupper( $data['method'] )
			: 'POST';
		if ( ! in_array( $method, self::ALLOWED_METHODS, true ) ) {
			$method = 'POST';
		}

		$format = isset( $data['format'] ) && is_string( $data['format'] )
			? strtolower( $data['format'] )
			: 'json';
		if ( ! in_array( $format, self::ALLOWED_FORMATS, true ) ) {
			$format = 'json';
		}

		return [
			'form_id'            => isset( $data['form_id'] ) && is_string( $data['form_id'] ) ? sanitize_text_field( $data['form_id'] ) : '',
			'label'              => isset( $data['label'] ) && is_string( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '',
			'url'                => isset( $data['url'] ) && is_string( $data['url'] ) ? esc_url_raw( $data['url'] ) : '',
			'method'             => $method,
			'format'             => $format,
			'headers'            => $this->encode_assoc( $data['headers'] ?? [] ),
			'field_mapping'      => $this->encode_assoc( $data['field_mapping'] ?? [] ),
			'condition_field'    => isset( $data['condition_field'] ) && is_string( $data['condition_field'] ) ? sanitize_key( $data['condition_field'] ) : '',
			'condition_operator' => isset( $data['condition_operator'] ) && is_string( $data['condition_operator'] ) ? sanitize_text_field( $data['condition_operator'] ) : '',
			'condition_value'    => isset( $data['condition_value'] ) && is_string( $data['condition_value'] ) ? sanitize_text_field( $data['condition_value'] ) : '',
			'is_active'          => ! empty( $data['is_active'] ) ? 1 : 0,
		];
	}

	/**
	 * Encode an associative array into a JSON string suitable for the
	 * `longtext` column. Accepts an already-JSON string as a pass-through
	 * so the REST endpoint can leave the parsed body alone.
	 *
	 * @param mixed $value Input.
	 * @return string
	 */
	private function encode_assoc( $value ): string {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				$value = $decoded;
			} else {
				return '{}';
			}
		}

		if ( ! is_array( $value ) ) {
			return '{}';
		}

		$sanitised = [];
		foreach ( $value as $k => $v ) {
			if ( ! is_scalar( $k ) || ! is_scalar( $v ) ) {
				continue;
			}
			$sanitised[ (string) $k ] = (string) $v;
		}

		return (string) wp_json_encode( $sanitised );
	}

	/**
	 * Take a `$wpdb` result row and produce the consumer-facing shape:
	 * decoded JSON columns, typed booleans, typed ints.
	 *
	 * @param array<string, mixed> $row Raw row from the database.
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$row['id']            = (int) $row['id'];
		$row['is_active']     = ! empty( $row['is_active'] );
		$row['headers']       = $this->decode_assoc( (string) ( $row['headers'] ?? '' ) );
		$row['field_mapping'] = $this->decode_assoc( (string) ( $row['field_mapping'] ?? '' ) );

		return $row;
	}

	/**
	 * Decode a JSON-string column back into an associative array. Always
	 * returns an array — never null — so callers don't have to guard.
	 *
	 * @param string $value Raw JSON.
	 * @return array<string, string>
	 */
	private function decode_assoc( string $value ): array {
		if ( '' === $value ) {
			return [];
		}

		$decoded = json_decode( $value, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return [];
		}

		return $decoded;
	}
}
