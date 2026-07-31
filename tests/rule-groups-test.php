#!/usr/bin/env php
<?php
/**
 * Standalone unit tests for nested condition groups.
 *
 * A rule entry is either a leaf (`{field, operator, value}`) or a group
 * (`{logic, rules}`) evaluated on its own ALL/ANY, whose verdict then
 * folds into the parent like a leaf's. That is what makes
 * "(A or B or C) and D" expressible; a flat list with one match mode
 * cannot say it.
 *
 * Two things carry real risk and are covered accordingly:
 *
 *   - Flat rule sets saved before groups existed must behave exactly as
 *     they did. The discriminator is the presence of a `rules` array, so
 *     an old leaf can never be read as a group — asserted, not assumed.
 *   - Wrapper::condition_value() re-serialises the rule set into the data
 *     attribute the browser reads. It used to flatten every entry to
 *     `{field, operator, value}`, which would silently drop nesting and
 *     leave the browser evaluating a different condition than the server.
 *
 * Run:  php tests/rule-groups-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

namespace Flinkform\Submissions {
	/** Stub: Wrapper asks for the render state when deciding `hidden`. */
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

	$evaluator = new \Flinkform\Conditions\RuleEvaluator();
	$passed    = 0;
	$failed    = 0;

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
	 * @param array<int, array<string, mixed>> $rules
	 * @param array<string, mixed>             $values
	 */
	function shows( array $rules, array $values, string $logic = 'all' ): bool {
		global $evaluator;
		return $evaluator->should_show(
			[ 'enabled' => true, 'logic' => $logic, 'rules' => $rules ],
			$values
		);
	}

	function leaf( string $field, string $operator, string $value = '' ): array {
		return [ 'field' => $field, 'operator' => $operator, 'value' => $value ];
	}

	/** @param array<int, array<string, mixed>> $rules */
	function group( string $logic, array $rules ): array {
		return [ 'logic' => $logic, 'rules' => $rules ];
	}

	// --- Flat rule sets must be untouched by all of this -------------------

	check( 'flat ALL: both match', shows( [ leaf( 'a', 'is', '1' ), leaf( 'b', 'is', '2' ) ], [ 'a' => '1', 'b' => '2' ] ) );
	check( 'flat ALL: one fails', ! shows( [ leaf( 'a', 'is', '1' ), leaf( 'b', 'is', '2' ) ], [ 'a' => '1' ] ) );
	check( 'flat ANY: one matches', shows( [ leaf( 'a', 'is', '1' ), leaf( 'b', 'is', '2' ) ], [ 'b' => '2' ], 'any' ) );
	check( 'flat ANY: none match', ! shows( [ leaf( 'a', 'is', '1' ) ], [ 'a' => 'x' ], 'any' ) );
	check( 'empty rule list shows', shows( [], [] ) );
	check( 'disabled rule set shows', $evaluator->should_show( [ 'enabled' => false, 'rules' => [ leaf( 'a', 'is', '1' ) ] ], [] ) );

	// --- The motivating case ------------------------------------------------
	// ( due is empty OR due < 2027-03-19 OR due >= 2027-04-29 ) AND plz is not "andere-plz"

	$holiday = [
		group( 'any', [
			leaf( 'due', 'is_empty' ),
			leaf( 'due', 'date_before', '2027-03-19' ),
			leaf( 'due', 'date_on_or_after', '2027-04-29' ),
		] ),
		leaf( 'plz', 'is_not', 'andere-plz' ),
	];

	check( 'no date yet, plz fine → submit allowed', shows( $holiday, [ 'plz' => '74172' ] ) );
	check( 'date before the holiday → allowed', shows( $holiday, [ 'due' => '2027-02-01', 'plz' => '74172' ] ) );
	check( 'date after the holiday → allowed', shows( $holiday, [ 'due' => '2027-06-01', 'plz' => '74172' ] ) );
	check( 'date inside the holiday → blocked', ! shows( $holiday, [ 'due' => '2027-04-01', 'plz' => '74172' ] ) );
	check( 'date on the first blocked day → blocked', ! shows( $holiday, [ 'due' => '2027-03-19', 'plz' => '74172' ] ) );
	check( 'date on the first allowed day again → allowed', shows( $holiday, [ 'due' => '2027-04-29', 'plz' => '74172' ] ) );
	check( 'excluded postcode → blocked even with a fine date', ! shows( $holiday, [ 'due' => '2027-06-01', 'plz' => 'andere-plz' ] ) );
	check( 'excluded postcode and bad date → blocked', ! shows( $holiday, [ 'due' => '2027-04-01', 'plz' => 'andere-plz' ] ) );

	// --- Mixed nesting both ways --------------------------------------------

	// ALL parent, ALL group
	$both = [ group( 'all', [ leaf( 'a', 'is', '1' ), leaf( 'b', 'is', '2' ) ] ), leaf( 'c', 'is', '3' ) ];
	check( 'ALL in ALL: everything matches', shows( $both, [ 'a' => '1', 'b' => '2', 'c' => '3' ] ) );
	check( 'ALL in ALL: group half-satisfied fails', ! shows( $both, [ 'a' => '1', 'c' => '3' ] ) );

	// ANY parent with a group inside: group true is enough
	$anyParent = [ group( 'all', [ leaf( 'a', 'is', '1' ), leaf( 'b', 'is', '2' ) ] ), leaf( 'c', 'is', '3' ) ];
	check( 'ANY parent: satisfied group alone is enough', shows( $anyParent, [ 'a' => '1', 'b' => '2' ], 'any' ) );
	check( 'ANY parent: leaf alone is enough', shows( $anyParent, [ 'c' => '3' ], 'any' ) );
	check( 'ANY parent: nothing satisfied', ! shows( $anyParent, [ 'a' => '1' ], 'any' ) );

	// Two groups side by side
	$twoGroups = [
		group( 'any', [ leaf( 'a', 'is', '1' ), leaf( 'a', 'is', '2' ) ] ),
		group( 'any', [ leaf( 'b', 'is', 'x' ), leaf( 'b', 'is', 'y' ) ] ),
	];
	check( 'two ANY groups under ALL: both satisfied', shows( $twoGroups, [ 'a' => '2', 'b' => 'y' ] ) );
	check( 'two ANY groups under ALL: one unsatisfied', ! shows( $twoGroups, [ 'a' => '2', 'b' => 'z' ] ) );

	// --- Empty and malformed groups ------------------------------------------

	check(
		'an empty group is ignored, not counted as false',
		shows( [ group( 'any', [] ), leaf( 'a', 'is', '1' ) ], [ 'a' => '1' ] )
	);
	check(
		'an empty group does not satisfy an ANY parent on its own',
		! shows( [ group( 'any', [] ), leaf( 'a', 'is', '1' ) ], [ 'a' => 'nope' ], 'any' )
	);
	check(
		'a group with only broken leaves evaluates, it is not skipped',
		! shows( [ group( 'all', [ leaf( '', 'is', '1' ) ] ), leaf( 'a', 'is', '1' ) ], [ 'a' => '1' ] )
	);
	check( 'a rule set of nothing but an empty group shows', shows( [ group( 'any', [] ) ], [] ) );

	// A group missing its logic key falls back to ALL, like the top level.
	check(
		'a group without a logic key behaves as ALL',
		! shows( [ [ 'rules' => [ leaf( 'a', 'is', '1' ), leaf( 'b', 'is', '2' ) ] ] ], [ 'a' => '1' ] )
	);

	// --- Depth --------------------------------------------------------------

	$deep = leaf( 'a', 'is', '1' );
	for ( $i = 0; $i < 4; $i++ ) {
		$deep = group( 'all', [ $deep ] );
	}
	check( 'four levels deep still evaluates', shows( [ $deep ], [ 'a' => '1' ] ) );
	check( 'four levels deep can also fail', ! shows( [ $deep ], [ 'a' => '2' ] ) );

	// Past the ceiling the evaluator stops descending and returns true —
	// a corrupted payload must not hide a field, and must not recurse.
	$absurd = leaf( 'a', 'is', 'never' );
	for ( $i = 0; $i < 40; $i++ ) {
		$absurd = group( 'all', [ $absurd ] );
	}
	check( 'absurd nesting is survivable', shows( [ $absurd ], [] ) );

	// --- Serialisation must keep the nesting ---------------------------------

	\Flinkform\Submissions\Handler::$values = [];
	$json = \Flinkform\Conditions\Wrapper::condition_value( [ 'enabled' => true, 'logic' => 'all', 'rules' => $holiday ] );
	$back = json_decode( $json, true );

	check( 'serialised payload keeps two top-level entries', 2 === count( $back['rules'] ), $json );
	check( 'the first entry is still a group', isset( $back['rules'][0]['rules'] ), $json );
	check( 'the group keeps its own logic', 'any' === ( $back['rules'][0]['logic'] ?? '' ), $json );
	check( 'the group keeps all three rules', 3 === count( $back['rules'][0]['rules'] ), $json );
	check( 'the leaf sibling survives', 'plz' === ( $back['rules'][1]['field'] ?? '' ), $json );
	check(
		'the round-tripped payload evaluates identically',
		shows( $back['rules'], [ 'due' => '2027-04-01', 'plz' => '74172' ] ) === false
			&& shows( $back['rules'], [ 'due' => '2027-06-01', 'plz' => '74172' ] ) === true,
		$json
	);

	// Empty groups are dropped from the payload rather than shipped.
	$json = \Flinkform\Conditions\Wrapper::condition_value(
		[ 'enabled' => true, 'logic' => 'all', 'rules' => [ group( 'any', [] ), leaf( 'a', 'is', '1' ) ] ]
	);
	$back = json_decode( $json, true );
	check( 'an empty group is left out of the payload', 1 === count( $back['rules'] ), $json );

	// And a flat set still serialises exactly as before.
	$json = \Flinkform\Conditions\Wrapper::condition_value(
		[ 'enabled' => true, 'logic' => 'any', 'rules' => [ leaf( 'a', 'is', '1' ) ] ]
	);
	check(
		'a flat payload is unchanged',
		'{"enabled":true,"logic":"any","rules":[{"field":"a","operator":"is","value":"1"}]}' === $json,
		$json
	);

	// --- Summary -------------------------------------------------------------

	echo "\n";
	if ( $failed > 0 ) {
		echo "$failed FAILED, $passed passed.\n";
		exit( 1 );
	}
	echo "All $passed tests passed.\n";
	exit( 0 );
}
