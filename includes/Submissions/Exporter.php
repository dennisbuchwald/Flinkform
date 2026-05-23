<?php
/**
 * CSV exporter for submissions.
 *
 * Streams a CSV download for the rows matching a filter set. Field
 * columns are derived dynamically by walking every selected submission
 * — this keeps an export across mixed forms readable, with blank cells
 * for fields that don't apply to a given row.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Submissions;

defined( 'ABSPATH' ) || exit;

/**
 * Builds + streams the CSV download.
 */
final class Exporter {

	/**
	 * Hard cap on rows per export — guards against runaway CSVs while we
	 * don't yet have a chunked/background export pipeline.
	 */
	private const MAX_ROWS = 5000;

	private Repository $repository;

	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Stream a CSV file to the browser for the given filters.
	 *
	 * Calls exit() at the end.
	 *
	 * @param array<string, string> $filters
	 * @return never
	 */
	public function stream( array $filters ): void {
		// Pull rows up to the cap. We iterate paginated to avoid loading
		// the entire table at once on large datasets — Phase 2 sizes are
		// fine, but the pattern scales for later.
		$rows     = $this->collect( $filters );
		$columns  = $this->discover_columns( $rows );
		$filename = 'perform-submissions-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			exit;
		}

		// BOM so Excel opens UTF-8 correctly without prompting for charset.
		fwrite( $out, "\xEF\xBB\xBF" );

		// Header row.
		$header = array_merge(
			[ 'id', 'form_id', 'created_at', 'status' ],
			array_map( static fn( string $col ): string => 'field:' . $col, $columns )
		);
		fputcsv( $out, $header );

		// Data rows.
		foreach ( $rows as $row ) {
			$line = [
				(string) $row['id'],
				(string) $row['form_id'],
				(string) $row['created_at'],
				(string) $row['status'],
			];

			$values_by_name = $this->index_values( $row );
			foreach ( $columns as $col ) {
				$line[] = $values_by_name[ $col ] ?? '';
			}

			fputcsv( $out, $line );
		}

		fclose( $out );
		exit;
	}

	/**
	 * Pull rows in pages until we hit MAX_ROWS or run out.
	 *
	 * @param array<string, string> $filters
	 * @return array<int, array<string, mixed>>
	 */
	private function collect( array $filters ): array {
		$collected = [];
		$page      = 1;
		$per_page  = 500;

		while ( count( $collected ) < self::MAX_ROWS ) {
			$chunk = $this->repository->find_paginated( $filters, $page, $per_page, 'created_at', 'DESC' );
			if ( empty( $chunk ) ) {
				break;
			}
			foreach ( $chunk as $row ) {
				$collected[] = $row;
				if ( count( $collected ) >= self::MAX_ROWS ) {
					break 2;
				}
			}
			if ( count( $chunk ) < $per_page ) {
				break;
			}
			++$page;
		}

		return $collected;
	}

	/**
	 * Walk every collected row and return the union of distinct
	 * field names, preserving first-seen order so the CSV column
	 * layout is stable for repeated exports of the same data.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @return array<int, string>
	 */
	private function discover_columns( array $rows ): array {
		$seen = [];
		foreach ( $rows as $row ) {
			$fields = isset( $row['data']['fields'] ) && is_array( $row['data']['fields'] ) ? $row['data']['fields'] : [];
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$name = isset( $field['name'] ) ? (string) $field['name'] : '';
				if ( '' !== $name && ! isset( $seen[ $name ] ) ) {
					$seen[ $name ] = true;
				}
			}
		}
		return array_keys( $seen );
	}

	/**
	 * Reduce a submission's fields array to a name → value map.
	 *
	 * @param array<string, mixed> $row
	 * @return array<string, string>
	 */
	private function index_values( array $row ): array {
		$indexed = [];
		$fields  = isset( $row['data']['fields'] ) && is_array( $row['data']['fields'] ) ? $row['data']['fields'] : [];
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$name = isset( $field['name'] ) ? (string) $field['name'] : '';
			if ( '' === $name ) {
				continue;
			}
			$indexed[ $name ] = isset( $field['value'] ) ? (string) $field['value'] : '';
		}
		return $indexed;
	}
}
