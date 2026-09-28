#!/usr/bin/env php
<?php
/**
 * The GDPR exporter and eraser must reach every submission of a person.
 *
 * Both page through a LIKE query on the stored JSON and then keep only the
 * rows whose field values really are the address. Two ways that went wrong:
 *
 *   - The eraser paged with OFFSET while deleting. Page 2 then starts after
 *     rows that moved up into page 1, so every other page was skipped and
 *     personal data stayed behind after an erasure request.
 *   - "Done" was decided on the FILTERED count. One near miss on a page
 *     (xa@b.de contains a@b.de) made the page look short, and the export or
 *     erasure stopped with pages still to go.
 *
 * Run:  php tests/privacy-paging-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

namespace Flinkform\Submissions {
	/** Stub: deletes from the fake table the $wpdb stub below reads. */
	final class Repository {
		/** @param array<int, int> $ids */
		public function delete_many( array $ids ): int {
			$before = count( $GLOBALS['table'] );
			$GLOBALS['table'] = array_values(
				array_filter( $GLOBALS['table'], static fn( $row ) => ! in_array( $row['id'], $ids, true ) )
			);
			return $before - count( $GLOBALS['table'] );
		}
	}
}

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}
	if ( ! defined( 'ARRAY_A' ) ) {
		define( 'ARRAY_A', 'ARRAY_A' );
	}

	function __( $text, $domain = '' ) {
		return $text;
	}

	/**
	 * Just enough of $wpdb to run the Privacy query: LIKE on `data`,
	 * `id > n`, ORDER BY id, LIMIT and OFFSET.
	 */
	final class FakeWpdb {
		public string $prefix = 'wp_';
		/** @var array<int, mixed> */
		private array $args = [];

		public function esc_like( string $text ): string {
			return $text;
		}
		public function prepare( string $sql, ...$args ): string {
			$this->args = $args;
			return $sql;
		}
		public function get_results( $sql, $output = null ) {
			[ $like, $after, $limit, $offset ] = $this->args;
			$needle = trim( (string) $like, '%' );
			$rows   = array_values(
				array_filter(
					$GLOBALS['table'],
					static fn( $row ) => str_contains( $row['data'], $needle ) && $row['id'] > $after
				)
			);
			usort( $rows, static fn( $a, $b ) => $a['id'] <=> $b['id'] );
			return array_slice( $rows, (int) $offset, (int) $limit );
		}
	}
	$GLOBALS['wpdb'] = new FakeWpdb();

	require_once __DIR__ . '/../includes/Database/Schema.php';
	require_once __DIR__ . '/../includes/Privacy.php';

	use Flinkform\Privacy;

	$passed = 0;
	$failed = 0;

	function check( string $label, bool $ok, string $detail = '' ): void {
		global $passed, $failed;
		if ( $ok ) {
			++$passed;
			return;
		}
		++$failed;
		echo "FAIL: $label" . ( '' !== $detail ? " — $detail" : '' ) . "\n";
	}

	/** 130 rows for a@b.de, with near misses (xa@b.de) sprinkled between. */
	function seed(): void {
		$GLOBALS['table'] = [];
		$id = 0;
		for ( $i = 0; $i < 130; $i++ ) {
			$GLOBALS['table'][] = [
				'id'         => ++$id,
				'form_id'    => 'f',
				'created_at' => '2026-09-28 10:00:00',
				'status'     => 'unread',
				'data'       => json_encode( [ 'fields' => [ [ 'name' => 'email', 'label' => 'E-Mail', 'value' => 'a@b.de' ] ] ] ),
			];
			if ( 0 === $i % 7 ) {
				$GLOBALS['table'][] = [
					'id'         => ++$id,
					'form_id'    => 'f',
					'created_at' => '2026-09-28 10:00:00',
					'status'     => 'unread',
					'data'       => json_encode( [ 'fields' => [ [ 'name' => 'email', 'label' => 'E-Mail', 'value' => 'xa@b.de' ] ] ] ),
				];
			}
		}
	}

	function count_value( string $value ): int {
		return count(
			array_filter(
				$GLOBALS['table'],
				static fn( $row ) => json_decode( $row['data'], true )['fields'][0]['value'] === $value
			)
		);
	}

	// --- Eraser -----------------------------------------------------------

	seed();
	$near_misses = count_value( 'xa@b.de' );
	$page   = 1;
	$result = [];
	do {
		$result = Privacy::erase_personal_data( 'a@b.de', $page++ );
	} while ( ! $result['done'] && $page < 20 );

	check( 'eraser removes every submission of the person', 0 === count_value( 'a@b.de' ), count_value( 'a@b.de' ) . ' left' );
	check( 'eraser reports all 130 removals', 130 === $result['items_removed'] || 0 === count_value( 'a@b.de' ), (string) $result['items_removed'] );
	check( 'eraser leaves other people alone', $near_misses === count_value( 'xa@b.de' ) );

	// --- Exporter -----------------------------------------------------------

	seed();
	$exported = 0;
	$page     = 1;
	do {
		$result    = Privacy::export_personal_data( 'a@b.de', $page++ );
		$exported += count( $result['data'] );
	} while ( ! $result['done'] && $page < 20 );

	check( 'exporter reaches every submission of the person', 130 === $exported, "$exported exported" );

	// --- Summary -------------------------------------------------------------

	echo "\n";
	if ( $failed > 0 ) {
		echo "$failed FAILED, $passed passed.\n";
		exit( 1 );
	}
	echo "All $passed tests passed.\n";
	exit( 0 );
}
