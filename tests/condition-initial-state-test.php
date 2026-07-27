#!/usr/bin/env php
<?php
/**
 * Standalone unit tests for Conditions\Wrapper::condition_attributes().
 *
 * The server decides whether a conditional block starts hidden so it does
 * not flash into view and then disappear once the deferred frontend module
 * runs. These tests pin that the server's verdict matches what the client
 * evaluator would compute from the same values — if the two drift, the
 * flash comes back, just in the other direction.
 *
 * Run:  php tests/condition-initial-state-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

namespace Flinkform\Submissions {
	/**
	 * Stub Handler exposing a settable value map, standing in for the
	 * render state the form container primes before rendering fields.
	 */
	class Handler {
		/** @var array<string, mixed> */
		public static $values = [];

		/** @return array<string, mixed> */
		public static function current_values(): array {
			return self::$values;
		}
	}
}

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES );
		}
		function wp_json_encode( $data ) {
			return json_encode( $data );
		}
	}

	require_once __DIR__ . '/../includes/Conditions/RuleEvaluator.php';
	require_once __DIR__ . '/../includes/Conditions/Wrapper.php';

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

	/**
	 * @param array<int, array<string, string>> $rules
	 * @param array<string, mixed>              $values
	 */
	function attrs( array $rules, array $values, string $logic = 'all' ): string {
		\Flinkform\Submissions\Handler::$values = $values;
		return \Flinkform\Conditions\Wrapper::condition_attributes(
			[ 'enabled' => true, 'logic' => $logic, 'rules' => $rules ]
		);
	}

	function is_hidden( string $attributes ): bool {
		return str_contains( $attributes, ' hidden' );
	}

	$rule_is_yes = [ [ 'field' => 'choice', 'operator' => 'is', 'value' => 'yes' ] ];

	// --- Fresh page load: no values at all ---

	$out = attrs( $rule_is_yes, [] );
	check( 'fresh load emits the condition payload', str_contains( $out, 'data-flinkform-condition=' ), $out );
	check( 'fresh load hides a block whose rule cannot match yet', is_hidden( $out ), $out );

	// --- Error re-render: the submitted values are known ---

	check( 'matching value renders visible', ! is_hidden( attrs( $rule_is_yes, [ 'choice' => 'yes' ] ) ) );
	check( 'non-matching value renders hidden', is_hidden( attrs( $rule_is_yes, [ 'choice' => 'no' ] ) ) );
	check( 'empty value renders hidden', is_hidden( attrs( $rule_is_yes, [ 'choice' => '' ] ) ) );

	// --- Parity with the client evaluator on the awkward inputs ---
	// A missing key and an empty string must behave identically, because
	// the browser reports an untouched input as an empty string while the
	// server simply has no entry for it.

	$rule_empty     = [ [ 'field' => 'choice', 'operator' => 'is_empty', 'value' => '' ] ];
	$missing        = attrs( $rule_empty, [] );
	$empty_string   = attrs( $rule_empty, [ 'choice' => '' ] );
	check( 'missing key and empty string agree', is_hidden( $missing ) === is_hidden( $empty_string ) );
	check( 'is_empty shows the block when the field is untouched', ! is_hidden( $missing ), $missing );

	check( 'whitespace counts as empty', ! is_hidden( attrs( $rule_empty, [ 'choice' => '   ' ] ) ) );
	check( 'is_not_empty hides while untouched', is_hidden( attrs( [ [ 'field' => 'choice', 'operator' => 'is_not_empty', 'value' => '' ] ], [] ) ) );

	// --- Multi-value fields (checkbox group, multi-select) ---

	check( 'empty array counts as empty', ! is_hidden( attrs( $rule_empty, [ 'choice' => [] ] ) ) );
	check(
		'array value matches via contains',
		! is_hidden( attrs( [ [ 'field' => 'choice', 'operator' => 'contains', 'value' => 'b' ] ], [ 'choice' => [ 'a', 'b' ] ] ) )
	);

	// --- Date operators, the motivating case for a conditional notice ---

	$before_cutoff = [ [ 'field' => 'due', 'operator' => 'date_before', 'value' => '2027-04-29' ] ];
	check( 'date before cutoff shows the notice', ! is_hidden( attrs( $before_cutoff, [ 'due' => '2027-03-01' ] ) ) );
	check( 'date after cutoff hides the notice', is_hidden( attrs( $before_cutoff, [ 'due' => '2027-06-01' ] ) ) );
	check( 'unset date hides the notice', is_hidden( attrs( $before_cutoff, [] ) ) );
	check( 'malformed date hides the notice', is_hidden( attrs( $before_cutoff, [ 'due' => '01.03.2027' ] ) ) );

	// --- ALL vs ANY ---

	$two = [
		[ 'field' => 'a', 'operator' => 'is', 'value' => '1' ],
		[ 'field' => 'b', 'operator' => 'is', 'value' => '2' ],
	];
	check( 'ALL needs every rule', is_hidden( attrs( $two, [ 'a' => '1' ], 'all' ) ) );
	check( 'ALL satisfied renders visible', ! is_hidden( attrs( $two, [ 'a' => '1', 'b' => '2' ], 'all' ) ) );
	check( 'ANY needs only one', ! is_hidden( attrs( $two, [ 'a' => '1' ], 'any' ) ) );
	check( 'ANY with no match hides', is_hidden( attrs( $two, [], 'any' ) ) );

	// --- No condition configured means no attributes at all ---

	\Flinkform\Submissions\Handler::$values = [];
	check( 'disabled rule set emits nothing', '' === \Flinkform\Conditions\Wrapper::condition_attributes( [ 'enabled' => false, 'rules' => $rule_is_yes ] ) );
	check( 'empty rule list emits nothing', '' === \Flinkform\Conditions\Wrapper::condition_attributes( [ 'enabled' => true, 'rules' => [] ] ) );
	check( 'missing rule set emits nothing', '' === \Flinkform\Conditions\Wrapper::condition_attributes( [] ) );

	// --- The payload stays escaped ---

	$out = attrs( [ [ 'field' => 'choice', 'operator' => 'is', 'value' => '"><script>' ] ], [] );
	check( 'payload is escaped', ! str_contains( $out, '<script>' ), $out );

	// --- Summary ---

	echo "\n";
	if ( $failed > 0 ) {
		echo "$failed FAILED, $passed passed.\n";
		exit( 1 );
	}
	echo "All $passed tests passed.\n";
	exit( 0 );
}
