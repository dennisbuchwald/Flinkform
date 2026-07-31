#!/usr/bin/env php
<?php
/**
 * PHP half of the client/server parity check.
 *
 * Reads the shared case table and prints one verdict per case as JSON.
 * tests/rule-groups-parity.mjs runs the browser evaluator over the same
 * table and compares. Not meant to be run on its own — see that file.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../includes/Conditions/RuleEvaluator.php';

$cases = json_decode( (string) file_get_contents( __DIR__ . '/rule-groups-cases.json' ), true );
if ( ! is_array( $cases ) ) {
	fwrite( STDERR, "Could not read rule-groups-cases.json\n" );
	exit( 1 );
}

$evaluator = new \Flinkform\Conditions\RuleEvaluator();
$verdicts  = [];

foreach ( $cases as $case ) {
	$verdicts[] = $evaluator->should_show( $case['ruleSet'], $case['values'] );
}

echo json_encode( $verdicts );
