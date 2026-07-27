#!/usr/bin/env php
<?php
/**
 * Standalone unit tests for the Address Field's server render.
 *
 * Guards the label/placeholder contract per label position. The address
 * sub-inputs render as full `.flinkform-field--text` wrappers, so they pick
 * up the form's label-position styling — and a hard-coded placeholder then
 * prints straight through a floating label (the 1.6.0-1.6.2 bug).
 *
 * Run:  php tests/field-address-render-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

namespace Flinkform\Submissions {
	/** Stub: the render only reads repopulation state, which is empty here. */
	class Handler {
		public static function flash_value( $name ) {
			return '';
		}
		public static function flash_error( $name ) {
			return '';
		}
	}
}

namespace Flinkform\Conditions {
	/** Stub: returns a recognisable marker so forwarding can be asserted. */
	class Wrapper {
		public static function condition_value( $conditional_logic ) {
			return empty( $conditional_logic ) ? '' : 'COND';
		}
	}
}

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}
	define( 'FLINKFORM_TEST_ROOT', __DIR__ . '/..' );

	if ( ! function_exists( '__' ) ) {
		/** Identity stub — these tests assert markup, not translation. */
		function __( $text, $domain = '' ) {
			return $text;
		}
		function esc_attr( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES );
		}
		function esc_html( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES );
		}
	}

	/** Stand-in for WP_Block — the render only ever reads `context`. */
	class Flinkform_Test_Block {
		public $context = [];
	}

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
	 * Render the block and return its markup.
	 *
	 * @param string|null          $label_position Appearance context, null to omit it entirely.
	 * @param bool                 $required
	 * @param array<string, mixed> $extra          Attribute overrides.
	 */
	function render_address( ?string $label_position, bool $required, array $extra = [] ): string {
		$block          = new Flinkform_Test_Block();
		$block->context = [ 'flinkform/formId' => 'form-1' ];
		if ( null !== $label_position ) {
			$block->context['flinkform/appearance'] = [ 'labelPosition' => $label_position ];
		}

		$attributes = array_merge(
			[
				'label'            => 'Address',
				'required'         => $required,
				'helpText'         => '',
				'fieldName'        => 'addr',
				'showCountry'      => false,
				'showAddressLine2' => false,
				'countryDefault'   => '',
				'fullWidth'        => true,
				'conditionalLogic' => [],
			],
			$extra
		);

		$content = '';
		ob_start();
		include FLINKFORM_TEST_ROOT . '/src/field-address/render.php';
		return (string) ob_get_clean();
	}

	/** @return array<int, string> */
	function placeholders( string $html ): array {
		preg_match_all( '/placeholder="([^"]*)"/', $html, $matches );
		return $matches[1];
	}

	/** @return array<string, bool> Sub-field name => is required. */
	function required_map( string $html ): array {
		$out = [];
		foreach ( explode( '<input', $html ) as $chunk ) {
			if ( preg_match( '/name="flinkform_field\[(addr_[a-z0-9]+)\]"/', $chunk, $name ) ) {
				$out[ $name[1] ] = str_contains( $chunk, 'required aria-required' );
			}
		}
		return $out;
	}

	// --- Floating labels: the label sits inside the input, so no placeholder ---

	$html = render_address( 'floating', true );
	check(
		'floating: every placeholder is empty',
		[ '', '', '' ] === placeholders( $html ),
		json_encode( placeholders( $html ) )
	);
	check( 'floating: labels are still rendered', 3 === substr_count( $html, 'flinkform-field__label' ) );
	check(
		'floating: sub-fields keep the --text wrapper that carries the styling',
		3 === substr_count( $html, 'flinkform-field flinkform-field--text' )
	);

	$html = render_address( 'floating', true, [ 'showAddressLine2' => true, 'showCountry' => true ] );
	check(
		'floating: optional sub-fields are placeholder-free too',
		array_fill( 0, 5, '' ) === placeholders( $html ),
		json_encode( placeholders( $html ) )
	);

	// --- Above / beside: the descriptive placeholder adds value, keep it ---

	foreach ( [ 'above', 'beside' ] as $position ) {
		$html = render_address( $position, false );
		check(
			"$position: descriptive placeholders kept",
			[ 'Street + house number', 'Postal code', 'City' ] === placeholders( $html ),
			json_encode( placeholders( $html ) )
		);
	}

	check( 'above: optional address renders no required markers', ! str_contains( render_address( 'above', false ), 'aria-required' ) );

	// --- Placeholder mode: label is visually hidden, marker moves inward ---

	$html = render_address( 'placeholder', true );
	check(
		'placeholder mode: required marker appended to the placeholder',
		[ 'Street + house number*', 'Postal code*', 'City*' ] === placeholders( $html ),
		json_encode( placeholders( $html ) )
	);

	$html = render_address( 'placeholder', false );
	check(
		'placeholder mode: optional address gets no marker',
		[ 'Street + house number', 'Postal code', 'City' ] === placeholders( $html ),
		json_encode( placeholders( $html ) )
	);

	// --- Sub-field set and the "line 2 is never required" rule ---

	$html = render_address( 'above', true, [ 'showAddressLine2' => true, 'showCountry' => true ] );
	$map  = required_map( $html );
	check( 'all five sub-fields render when enabled', 5 === count( $map ), json_encode( array_keys( $map ) ) );
	check(
		'line 2 stays optional, every other sub-field is required',
		[
			'addr_street'  => true,
			'addr_line2'   => false,
			'addr_zip'     => true,
			'addr_city'    => true,
			'addr_country' => true,
		] === $map,
		json_encode( $map )
	);

	$map = required_map( render_address( 'above', false, [ 'showAddressLine2' => true, 'showCountry' => true ] ) );
	check( 'optional address requires nothing', ! in_array( true, $map, true ), json_encode( $map ) );

	// --- Country default ---

	$html = render_address( 'above', false, [ 'showCountry' => true, 'countryDefault' => 'Deutschland' ] );
	check( 'country default is pre-filled', str_contains( $html, 'value="Deutschland"' ) );

	// --- Conditional logic forwarding ---

	check(
		'conditional logic lands on the fieldset',
		str_contains( render_address( 'above', false, [ 'conditionalLogic' => [ 'enabled' => true ] ] ), 'data-flinkform-condition="COND"' )
	);
	check( 'no condition attribute when unset', ! str_contains( render_address( 'above', false ), 'data-flinkform-condition' ) );

	// --- Missing appearance context must not fatal or change behaviour ---

	check(
		'missing appearance context falls back to "above"',
		[ 'Street + house number', 'Postal code', 'City' ] === placeholders( render_address( null, false ) ),
		json_encode( placeholders( render_address( null, false ) ) )
	);

	// --- Guard clause ---

	check( 'no output without a field name', '' === trim( render_address( 'above', false, [ 'fieldName' => '' ] ) ) );

	// --- Summary ---

	echo "\n";
	if ( $failed > 0 ) {
		echo "$failed FAILED, $passed passed.\n";
		exit( 1 );
	}
	echo "All $passed tests passed.\n";
	exit( 0 );
}
