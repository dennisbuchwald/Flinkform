#!/usr/bin/env php
<?php
/**
 * Standalone unit tests for date_before / date_on_or_after operators
 * in RuleEvaluator.
 *
 * Run:  php tests/rule-evaluator-date-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

// Minimal WordPress stub so the evaluator's `defined( 'ABSPATH' )` guard passes.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../includes/Conditions/RuleEvaluator.php';

$evaluator = new \Flinkform\Conditions\RuleEvaluator();
$passed    = 0;
$failed    = 0;

function assert_result( string $label, bool $expected, bool $actual ): void {
	global $passed, $failed;
	if ( $expected === $actual ) {
		++$passed;
	} else {
		++$failed;
		$exp = $expected ? 'true' : 'false';
		$act = $actual ? 'true' : 'false';
		echo "FAIL: $label — expected $exp, got $act\n";
	}
}

function rule_set( string $operator, string $value ): array {
	return [
		'enabled' => true,
		'logic'   => 'all',
		'rules'   => [
			[ 'field' => 'due_date', 'operator' => $operator, 'value' => $value ],
		],
	];
}

// --- date_before ---

assert_result(
	'date_before: field before cutoff',
	true,
	$evaluator->should_show( rule_set( 'date_before', '2027-05-01' ), [ 'due_date' => '2027-04-30' ] )
);

assert_result(
	'date_before: field equals cutoff (not before)',
	false,
	$evaluator->should_show( rule_set( 'date_before', '2027-05-01' ), [ 'due_date' => '2027-05-01' ] )
);

assert_result(
	'date_before: field after cutoff',
	false,
	$evaluator->should_show( rule_set( 'date_before', '2027-05-01' ), [ 'due_date' => '2027-06-15' ] )
);

assert_result(
	'date_before: empty field value → false',
	false,
	$evaluator->should_show( rule_set( 'date_before', '2027-05-01' ), [ 'due_date' => '' ] )
);

assert_result(
	'date_before: missing field → false',
	false,
	$evaluator->should_show( rule_set( 'date_before', '2027-05-01' ), [] )
);

assert_result(
	'date_before: invalid field format → false',
	false,
	$evaluator->should_show( rule_set( 'date_before', '2027-05-01' ), [ 'due_date' => '30.04.2027' ] )
);

assert_result(
	'date_before: invalid cutoff format → false',
	false,
	$evaluator->should_show( rule_set( 'date_before', 'May 1 2027' ), [ 'due_date' => '2027-04-30' ] )
);

assert_result(
	'date_before: empty cutoff → false',
	false,
	$evaluator->should_show( rule_set( 'date_before', '' ), [ 'due_date' => '2027-04-30' ] )
);

// --- date_on_or_after ---

assert_result(
	'date_on_or_after: field equals cutoff',
	true,
	$evaluator->should_show( rule_set( 'date_on_or_after', '2027-05-01' ), [ 'due_date' => '2027-05-01' ] )
);

assert_result(
	'date_on_or_after: field after cutoff',
	true,
	$evaluator->should_show( rule_set( 'date_on_or_after', '2027-05-01' ), [ 'due_date' => '2027-06-15' ] )
);

assert_result(
	'date_on_or_after: field before cutoff',
	false,
	$evaluator->should_show( rule_set( 'date_on_or_after', '2027-05-01' ), [ 'due_date' => '2027-04-30' ] )
);

assert_result(
	'date_on_or_after: empty field value → false',
	false,
	$evaluator->should_show( rule_set( 'date_on_or_after', '2027-05-01' ), [ 'due_date' => '' ] )
);

assert_result(
	'date_on_or_after: missing field → false',
	false,
	$evaluator->should_show( rule_set( 'date_on_or_after', '2027-05-01' ), [] )
);

assert_result(
	'date_on_or_after: invalid field format → false',
	false,
	$evaluator->should_show( rule_set( 'date_on_or_after', '2027-05-01' ), [ 'due_date' => '2027/05/01' ] )
);

assert_result(
	'date_on_or_after: invalid cutoff format → false',
	false,
	$evaluator->should_show( rule_set( 'date_on_or_after', '01.05.2027' ), [ 'due_date' => '2027-05-01' ] )
);

assert_result(
	'date_on_or_after: empty cutoff → false',
	false,
	$evaluator->should_show( rule_set( 'date_on_or_after', '' ), [ 'due_date' => '2027-06-15' ] )
);

// --- Summary ---

echo "\n";
if ( $failed > 0 ) {
	echo "$failed FAILED, $passed passed.\n";
	exit( 1 );
}
echo "All $passed tests passed.\n";
exit( 0 );
