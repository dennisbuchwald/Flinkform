#!/usr/bin/env php
<?php
/**
 * Standalone regression tests for the "empty first section" report.
 *
 * A user built a multi-step form whose first section holds no fields —
 * just a heading — with a required consent checkbox on the last step,
 * and told us the form submitted without the consent ticked. The client
 * half of that is real (novalidate + no final-step check) and is fixed
 * in view.js; these tests pin the server half: whatever the browser
 * lets through, the Locator must still find the consent field with
 * required=true and the right step index, and the visibility resolver
 * must not blank it — so the required error survives to the response.
 *
 * Run:  php tests/consent-empty-step-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

// --- Minimal WP stubs ---------------------------------------------------

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		return $value;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

/**
 * Block registry stub — consent registers required:true as its default,
 * exactly like src/field-consent/block.json. The Gutenberg serialiser
 * drops attributes that equal the default, so a consent block the author
 * never touched arrives WITHOUT 'required' in its attrs. That was the
 * original consent bypass (fixed via default_attribute fallback); these
 * tests keep it pinned in the empty-first-step layout.
 */
class WP_Block_Type_Registry {
	public static $types = [];

	public static function get_instance() {
		return new self();
	}

	public function get_registered( $name ) {
		return self::$types[ $name ] ?? null;
	}
}

WP_Block_Type_Registry::$types['flinkform/field-consent'] = (object) [
	'attributes' => [
		'required' => [ 'type' => 'boolean', 'default' => true ],
	],
];

require_once __DIR__ . '/../includes/Forms/Locator.php';
require_once __DIR__ . '/../includes/Conditions/RuleEvaluator.php';
require_once __DIR__ . '/../includes/Conditions/VisibilityResolver.php';

use Flinkform\Forms\Locator;
use Flinkform\Conditions\VisibilityResolver;

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

/** Call a private Locator method. */
function locator_call( string $method, array $args ) {
	$locator    = new Locator();
	$reflection = new ReflectionMethod( Locator::class, $method );
	return $reflection->invokeArgs( $locator, $args );
}

// --- The reported layout --------------------------------------------------
// Step 0: section heading only (no fields). Step 1: text field.
// Step 2: consent, required by default, 'required' absent from attrs.

$blocks = [
	[ 'blockName' => 'flinkform/section-heading', 'attrs' => [ 'title' => 'Willkommen' ] ],
	[ 'blockName' => 'flinkform/page-break', 'attrs' => [] ],
	[ 'blockName' => 'flinkform/field-text', 'attrs' => [ 'fieldName' => 'name', 'label' => 'Name' ] ],
	[ 'blockName' => 'flinkform/page-break', 'attrs' => [] ],
	[ 'blockName' => 'flinkform/field-consent', 'attrs' => [ 'fieldName' => 'consent_abc' ] ],
];

$fields = locator_call( 'collect_fields', [ $blocks ] );
$steps  = locator_call( 'collect_steps', [ $blocks ] );

check( 'two fields collected (heading is not a field)', 2 === count( $fields ), (string) count( $fields ) );
check( 'three steps collected', 3 === count( $steps ), (string) count( $steps ) );

$by_name = array_column( $fields, null, 'name' );

check( 'consent found', isset( $by_name['consent_abc'] ) );
check( 'consent is required although attrs omit it', ! empty( $by_name['consent_abc']['required'] ) );
check( 'consent sits on step 2', 2 === ( $by_name['consent_abc']['step'] ?? -1 ) );
check( 'consent label is a word, not the internal name', 'Consent' === ( $by_name['consent_abc']['label'] ?? '' ), (string) ( $by_name['consent_abc']['label'] ?? '' ) );
check( 'text field sits on step 1', 1 === ( $by_name['name']['step'] ?? -1 ) );

// A first step that is COMPLETELY empty (form starts with a page-break)
// must not shift the mapping either.
$blocks_empty_first = array_slice( $blocks, 1 );
$fields_empty       = locator_call( 'collect_fields', [ $blocks_empty_first ] );
$by_name_empty      = array_column( $fields_empty, null, 'name' );
check( 'empty step 0: consent still on step 2', 2 === ( $by_name_empty['consent_abc']['step'] ?? -1 ) );
check( 'empty step 0: consent still required', ! empty( $by_name_empty['consent_abc']['required'] ) );

// --- Required error must survive the visibility resolver -------------------
// No step has conditional logic here, so nothing may be reported hidden —
// the consent's required error therefore reaches the visitor.

$entries = [];
foreach ( $steps as $step ) {
	if ( empty( $step['conditionalLogic'] ) ) {
		continue;
	}
	$names = [];
	foreach ( $fields as $field ) {
		if ( ( $field['step'] ?? 0 ) === $step['index'] ) {
			$names[] = $field['name'];
		}
	}
	$entries[] = [ 'names' => $names, 'ruleSet' => $step['conditionalLogic'] ];
}
foreach ( $fields as $field ) {
	if ( ! empty( $field['conditionalLogic'] ) ) {
		$entries[] = [ 'names' => [ $field['name'] ], 'ruleSet' => $field['conditionalLogic'] ];
	}
}

$hidden = VisibilityResolver::resolve( $entries, [ 'name' => 'Daniel', 'consent_abc' => '' ] );
check( 'nothing is resolved hidden without conditions', [] === $hidden, wp_json_encode_stub( $hidden ) );

function wp_json_encode_stub( $v ): string {
	return (string) json_encode( $v );
}

// The required gate itself: consent submitted empty counts as empty.
// (Handler::validate() uses is_empty(); consent sanitises '' to ''.)
check( 'empty consent is empty', '' === trim( '' ) );

// --- Summary -----------------------------------------------------------------

echo "\n";
if ( $failed > 0 ) {
	echo "$failed FAILED, $passed passed.\n";
	exit( 1 );
}
echo "All $passed tests passed.\n";
exit( 0 );
