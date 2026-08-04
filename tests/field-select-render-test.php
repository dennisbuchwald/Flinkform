#!/usr/bin/env php
<?php
/**
 * Standalone render tests for the select field's placeholder option.
 *
 * A single select with no placeholder used to render its first real
 * option preselected — required could never fail, and conditions
 * watching the field fired without the visitor ever choosing. Surfaced
 * when a radio (no preselection) was transformed into a select (silent
 * preselection): the dependent notice appeared on page load.
 *
 * Run:  php tests/field-select-render-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

namespace Flinkform\Submissions {
	class Handler {
		public static function flash_value( string $name ) {
			return '';
		}
		public static function flash_error( string $name ): string {
			return '';
		}
	}
}

namespace Flinkform\Conditions {
	class Wrapper {
		public static function condition_attributes( array $rules ): string {
			return '';
		}
	}
}

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}

	function esc_html( $t ) {
		return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $t ) {
		return esc_html( $t );
	}
	function __( $t, $d = '' ) {
		return $t;
	}
	function selected( $v ) {
		echo $v ? 'selected' : '';
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
	 * Render the block template with the given attributes.
	 *
	 * @param array<string, mixed> $attributes
	 * @return string
	 */
	function render_select( array $attributes ): string {
		$block = new class() {
			public $context = [
				'flinkform/formId'     => 'test-form',
				'flinkform/appearance' => [],
			];
		};
		ob_start();
		include __DIR__ . '/../src/field-select/render.php';
		return (string) ob_get_clean();
	}

	$base = [
		'label'     => 'Anliegen',
		'fieldName' => 'anliegen',
		'options'   => [
			[ 'label' => 'Frage', 'value' => 'frage' ],
			[ 'label' => 'Auftrag', 'value' => 'auftrag' ],
		],
	];

	// --- Single select, no placeholder configured ---------------------------

	$html = render_select( $base );
	check( 'single select leads with an empty option', str_contains( $html, '<option value="' . '"' ) || preg_match( '/<option value="">/', $html ) === 1, $html );
	check( 'empty option carries the fallback text', str_contains( $html, 'Please choose' ) );
	$first_option = preg_match( '/<option value="([^"]*)"/', $html, $m ) ? $m[1] : 'NONE';
	check( 'the empty option comes first', '' === $first_option, $first_option );

	// --- Author-supplied placeholder wins ------------------------------------

	$html = render_select( $base + [ 'placeholder' => 'Bitte auswählen!' ] );
	check( 'custom placeholder is kept', str_contains( $html, 'Bitte auswählen!' ) );
	check( 'fallback text does not double up', ! str_contains( $html, 'Please choose' ) );

	// --- Multi select gets no placeholder option ------------------------------

	$html = render_select( $base + [ 'multiple' => true ] );
	check( 'multi select has no empty option', ! preg_match( '/<option value="">/', $html ), $html );
	check( 'multi select keeps its real options', str_contains( $html, 'value="frage"' ) && str_contains( $html, 'value="auftrag"' ) );

	// --- Summary ---------------------------------------------------------------

	echo "\n";
	if ( $failed > 0 ) {
		echo "$failed FAILED, $passed passed.\n";
		exit( 1 );
	}
	echo "All $passed tests passed.\n";
	exit( 0 );
}
