#!/usr/bin/env php
<?php
/**
 * Label markers and autofill (1.15.0), rendered through the real render.php
 * of every field block:
 *   - required fields keep the aria-hidden star;
 *   - with the form's "Mark optional fields" switch, every other field says
 *     "(optional)" as real text, and without it nothing changes;
 *   - the text field's autocomplete token goes through an allowlist.
 *
 * Run:  php tests/field-marks-render-test.php
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

namespace Flinkform\Submissions {
	class Handler {
		public static function flash_value( string $name ) { return ''; }
		public static function flash_error( string $name ): string { return ''; }
	}
}

namespace Flinkform\Conditions {
	class Wrapper {
		public static function condition_attributes( array $rules ): string { return ''; }
	}
}

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $t ) { return esc_html( $t ); }
	function esc_url( $t ) { return (string) $t; }
	function __( $t, $d = '' ) { return $t; }
	function esc_html__( $t, $d = '' ) { return esc_html( $t ); }
	function esc_attr__( $t, $d = '' ) { return esc_html( $t ); }
	function wp_kses_post( $t ) { return (string) $t; }
	function esc_textarea( $t ) { return esc_html( $t ); }
	function wp_kses( $t, $a = [] ) { return (string) $t; }
	function get_privacy_policy_url() { return ""; }
	function selected( $v ) { echo $v ? 'selected' : ''; }
	function checked( $v ) { echo $v ? 'checked' : ''; }
	function wp_unique_id( $p = '' ) { static $i = 0; return $p . ( ++$i ); }

	require_once __DIR__ . '/../includes/Fields/LabelMarks.php';

	$passed = 0;
	$failed = 0;
	function check( string $label, bool $ok, string $detail = '' ): void {
		global $passed, $failed;
		if ( $ok ) { ++$passed; return; }
		++$failed;
		echo "FAIL: $label" . ( '' !== $detail ? " — $detail" : '' ) . "\n";
	}

	function render_field( string $slug, array $attributes, array $appearance = [] ): string {
		$block = new class( $appearance ) {
			public $context;
			public function __construct( array $appearance ) {
				$this->context = [ 'flinkform/formId' => 'f1', 'flinkform/appearance' => $appearance ];
			}
		};
		$content = '';
		ob_start();
		include __DIR__ . '/../src/' . $slug . '/render.php';
		return (string) ob_get_clean();
	}

	$blocks = [ 'field-text', 'field-email', 'field-textarea', 'field-number', 'field-date', 'field-url', 'field-phone', 'field-select', 'field-radio', 'field-checkbox', 'field-toggle' ];
	$opts   = [ [ 'label' => 'A', 'value' => 'a' ], [ 'label' => 'B', 'value' => 'b' ] ];

	foreach ( $blocks as $slug ) {
		$base = [ 'label' => 'L', 'fieldName' => 'x', 'options' => $opts ];

		$html = render_field( $slug, $base + [ 'required' => true ], [ 'markOptional' => true ] );
		check( "$slug: required shows the star", str_contains( $html, 'flinkform-field__required' ) );
		check( "$slug: required never says optional", ! str_contains( $html, '(optional)' ) );

		$html = render_field( $slug, $base, [ 'markOptional' => true ] );
		check( "$slug: optional field says (optional) when switched on", str_contains( $html, '<span class="flinkform-field__optional"> (optional)</span>' ), $slug );
		check( "$slug: optional marker is readable by screen readers", ! preg_match( '/flinkform-field__optional"[^>]*aria-hidden/', $html ) );

		$html = render_field( $slug, $base );
		check( "$slug: switch off = nothing added", ! str_contains( $html, '(optional)' ) && ! str_contains( $html, 'flinkform-field__required' ) );
	}

	// Address: the group and its always-optional second line.
	$html = render_field( 'field-address', [ 'label' => 'Adresse', 'fieldName' => 'adr', 'required' => true, 'showAddressLine2' => true ], [ 'markOptional' => true ] );
	check( 'address: required group keeps stars', substr_count( $html, 'flinkform-field__required' ) >= 2 );
	check( 'address: line 2 is marked optional', substr_count( $html, '(optional)' ) === 1, (string) substr_count( $html, '(optional)' ) );

	// Autofill on the text field.
	$html = render_field( 'field-text', [ 'label' => 'Name', 'fieldName' => 'name', 'autocomplete' => 'name' ] );
	check( 'text: allowed autocomplete token is rendered', str_contains( $html, 'autocomplete="name"' ) );
	$html = render_field( 'field-text', [ 'label' => 'Name', 'fieldName' => 'name', 'autocomplete' => 'cc-number" onfocus="x' ] );
	check( 'text: unknown token renders nothing', ! str_contains( $html, 'autocomplete=' ) && ! str_contains( $html, 'onfocus' ) );
	$html = render_field( 'field-text', [ 'label' => 'Name', 'fieldName' => 'name' ] );
	check( 'text: no token, no attribute', ! str_contains( $html, 'autocomplete=' ) );

	echo "\n";
	if ( $failed > 0 ) {
		echo "$failed FAILED, $passed passed.\n";
		exit( 1 );
	}
	echo "All $passed tests passed.\n";
}
