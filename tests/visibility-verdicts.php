#!/usr/bin/env php
<?php
/**
 * PHP half of the visibility-resolution parity check.
 *
 * Prints the resolved hidden-field set per case as JSON. Driven by
 * tests/visibility-parity.mjs — see that file.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../includes/Conditions/RuleEvaluator.php';
require_once __DIR__ . '/../includes/Conditions/VisibilityResolver.php';

$cases = json_decode( (string) file_get_contents( __DIR__ . '/visibility-cases.json' ), true );
$out   = [];

foreach ( $cases as $case ) {
	$hidden = \Flinkform\Conditions\VisibilityResolver::resolve( $case['entries'], $case['values'] );
	sort( $hidden );
	$out[] = $hidden;
}

echo json_encode( $out );
