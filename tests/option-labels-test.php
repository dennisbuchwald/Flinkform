#!/usr/bin/env php
<?php
/**
 * Choice fields: mails and the admin show the option LABEL, storage keeps
 * the VALUE (1.15.0).
 *
 * Pins OptionLabels, the mail body rows, and the merge tags
 * ({field:x} = label, {field:x:value} = stored value).
 *
 * Run:  php tests/option-labels-test.php
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

function __( $text, $domain = '' ) { return $text; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $t ) { return (string) $t; }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function get_bloginfo( $k ) { return 'Site'; }
function home_url( $p = '' ) { return 'https://example.test/'; }
function current_time( $t ) { return '2026-09-28 12:00:00'; }

require_once __DIR__ . '/../includes/Fields/OptionLabels.php';
require_once __DIR__ . '/../includes/Notifications/BodyBuilder.php';
require_once __DIR__ . '/../includes/Notifications/MergeTags.php';

use Flinkform\Fields\OptionLabels;
use Flinkform\Notifications\BodyBuilder;
use Flinkform\Notifications\MergeTags;

$passed = 0;
$failed = 0;
function check( string $label, bool $ok, string $detail = '' ): void {
	global $passed, $failed;
	if ( $ok ) { ++$passed; return; }
	++$failed;
	echo "FAIL: $label" . ( '' !== $detail ? " — $detail" : '' ) . "\n";
}

// --- map_from_options -------------------------------------------------------
$map = OptionLabels::map_from_options( [
	[ 'label' => 'Website relaunch', 'value' => 'relaunch' ],
	[ 'label' => 'Shop', 'value' => 'shop' ],
	[ 'label' => 'same', 'value' => 'same' ],        // nothing to translate
	[ 'label' => '', 'value' => 'nolabel' ],          // no label
	[ 'label' => 'Duplicate', 'value' => 'shop' ],   // first wins
	'garbage',
] );
check( 'map keeps real translations', $map === [ 'relaunch' => 'Website relaunch', 'shop' => 'Shop' ], var_export( $map, true ) );
check( 'map of non-array is empty', OptionLabels::map_from_options( null ) === [] );

// --- resolve ----------------------------------------------------------------
check( 'single value resolves', OptionLabels::resolve( 'relaunch', $map ) === 'Website relaunch' );
check( 'unknown value falls back to itself', OptionLabels::resolve( 'gone', $map ) === 'gone' );
check( 'list resolves element-wise', OptionLabels::resolve( [ 'shop', 'gone' ], $map ) === [ 'Shop', 'gone' ] );

// --- for_field only touches choice types -----------------------------------
$select = [ 'name' => 'art', 'label' => 'Art', 'type' => 'select', 'optionLabels' => $map ];
$text   = [ 'name' => 'msg', 'label' => 'Msg', 'type' => 'text', 'optionLabels' => $map ];
check( 'select field gets label', OptionLabels::for_field( 'relaunch', $select ) === 'Website relaunch' );
check( 'text field untouched even with a map', OptionLabels::for_field( 'relaunch', $text ) === 'relaunch' );
check( 'field without map untouched', OptionLabels::for_field( 'x', [ 'type' => 'radio' ] ) === 'x' );

// --- display_value on persisted records ------------------------------------
$stored_new = [ 'name' => 'art', 'type' => 'select', 'value' => 'relaunch', 'display' => 'Old label at submit time' ];
check( 'snapshot wins over live definition', OptionLabels::display_value( $stored_new, [ $select ] ) === 'Old label at submit time' );
$stored_old = [ 'name' => 'art', 'type' => 'select', 'value' => 'relaunch' ];
check( 'legacy row resolves via live definition', OptionLabels::display_value( $stored_old, [ $select ] ) === 'Website relaunch' );
check( 'legacy row without definition shows value', OptionLabels::display_value( $stored_old, null ) === 'relaunch' );
check( 'legacy checkbox list', OptionLabels::display_value( [ 'name' => 'art', 'type' => 'checkbox', 'value' => [ 'shop' ] ], [ array_merge( $select, [ 'type' => 'checkbox' ] ) ] ) === [ 'Shop' ] );

// --- mail body rows ---------------------------------------------------------
$rows = BodyBuilder::rows( [ $select, $text ], [ 'art' => 'relaunch', 'msg' => 'relaunch' ] );
check( 'mail row shows option label', $rows[0]['value'] === 'Website relaunch' );
check( 'mail row for text keeps value', $rows[1]['value'] === 'relaunch' );
$html = BodyBuilder::html( $rows, [] );
check( 'html body contains label', false !== strpos( $html, 'Website relaunch' ) );

// --- merge tags -------------------------------------------------------------
$check_def = [ 'name' => 'services', 'label' => 'Services', 'type' => 'checkbox', 'optionLabels' => $map ];
$ctx = MergeTags::context( 7, 'f', [ 'art' => 'relaunch', 'services' => [ 'shop', 'relaunch' ] ], [ 'attributes' => [], 'fields' => [ $select, $check_def ] ] );
check( '{field:x} = label', MergeTags::render( 'A: {field:art}', $ctx ) === 'A: Website relaunch' );
check( '{field:x:value} = stored value', MergeTags::render( 'A: {field:art:value}', $ctx ) === 'A: relaunch' );
check( 'list label joined', MergeTags::render( '{field:services}', $ctx ) === 'Shop, Website relaunch' );
check( 'list raw joined', MergeTags::render( '{field:services:value}', $ctx ) === 'shop, relaunch' );
check( 'unknown field with modifier stays verbatim', MergeTags::render( '{field:typo:value}', $ctx ) === '{field:typo:value}' );
check( 'other modifiers are not tags', MergeTags::render( '{field:art:label}', $ctx ) === '{field:art:label}' );
check( 'plain tags still work', MergeTags::render( '{submission:id} {site:name}', $ctx ) === '7 Site' );

echo "\n";
if ( $failed > 0 ) {
	echo "$failed FAILED, $passed passed.\n";
	exit( 1 );
}
echo "All $passed tests passed.\n";
