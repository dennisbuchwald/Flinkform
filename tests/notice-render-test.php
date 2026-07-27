#!/usr/bin/env php
<?php
/**
 * Standalone unit tests for the Notice block's server render.
 *
 * The Notice submits nothing, so the risk is not validation but output:
 * an unknown variant must not leak into a class name, an empty note must
 * not render as a bare coloured strip, and the message must be filtered
 * down to inline formatting.
 *
 * Run:  php tests/notice-render-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

namespace Flinkform\Conditions {
	/** Stub: returns a recognisable marker so forwarding can be asserted. */
	class Wrapper {
		public static function condition_attributes( $conditional_logic ) {
			return empty( $conditional_logic ) ? '' : ' data-flinkform-condition="COND"';
		}
	}
}

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}
	define( 'FLINKFORM_TEST_ROOT', __DIR__ . '/..' );

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES );
		}
		function wp_strip_all_tags( $text ) {
			return strip_tags( (string) $text );
		}
		/** Crude stand-in: keeps only the tags the real allowlist permits. */
		function wp_kses( $text, $allowed ) {
			return strip_tags( (string) $text, '<' . implode( '><', array_keys( $allowed ) ) . '>' );
		}
	}

	/** Stand-in for WP_Block — the render only ever reads `context`. */
	class Flinkform_Notice_Test_Block {
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
	 * @param array<string, mixed> $extra Attribute overrides.
	 */
	function render_notice( array $extra = [] ): string {
		$block          = new Flinkform_Notice_Test_Block();
		$block->context = [ 'flinkform/formId' => 'form-1' ];

		$attributes = array_merge(
			[
				'message'          => 'Bitte beachten.',
				'variant'          => 'info',
				'showIcon'         => true,
				'fullWidth'        => true,
				'conditionalLogic' => [],
			],
			$extra
		);

		$content = '';
		ob_start();
		include FLINKFORM_TEST_ROOT . '/src/notice/render.php';
		return (string) ob_get_clean();
	}

	// --- Variants ---

	foreach ( [ 'info', 'success', 'warning', 'important' ] as $variant ) {
		$html = render_notice( [ 'variant' => $variant ] );
		check( "variant $variant renders its modifier", str_contains( $html, "flinkform-notice--$variant" ) );
	}

	$html = render_notice( [ 'variant' => 'evil" onload="alert(1)' ] );
	check( 'unknown variant falls back to info', str_contains( $html, 'flinkform-notice--info' ) );
	check( 'unknown variant cannot inject markup', ! str_contains( $html, 'onload' ), $html );

	// --- Empty state ---

	check( 'empty message renders nothing', '' === trim( render_notice( [ 'message' => '' ] ) ) );
	check( 'whitespace-only message renders nothing', '' === trim( render_notice( [ 'message' => "  \n " ] ) ) );
	check( 'markup-only message renders nothing', '' === trim( render_notice( [ 'message' => '<strong></strong>' ] ) ) );

	// --- Icon ---

	check( 'icon rendered by default', str_contains( render_notice(), 'flinkform-notice__icon' ) );
	check( 'icon can be switched off', ! str_contains( render_notice( [ 'showIcon' => false ] ), 'flinkform-notice__icon' ) );
	check( 'switched-off icon adds its modifier', str_contains( render_notice( [ 'showIcon' => false ] ), 'flinkform-notice--no-icon' ) );

	// Gutenberg omits attributes that match their block.json default, and
	// `showIcon` defaults to true — a missing key must keep the icon.
	$html = render_notice();
	unset( $html );
	$block          = new Flinkform_Notice_Test_Block();
	$block->context = [ 'flinkform/formId' => 'form-1' ];
	$attributes     = [ 'message' => 'Ohne showIcon-Schlüssel.', 'variant' => 'info', 'fullWidth' => true, 'conditionalLogic' => [] ];
	$content        = '';
	ob_start();
	include FLINKFORM_TEST_ROOT . '/src/notice/render.php';
	$html = (string) ob_get_clean();
	check( 'missing showIcon attribute keeps the icon', str_contains( $html, 'flinkform-notice__icon' ), $html );

	// --- Message filtering ---

	$html = render_notice( [ 'message' => 'Ein <strong>fetter</strong> und <em>kursiver</em> <a href="/x">Link</a>.' ] );
	foreach ( [ '<strong>', '<em>', '<a href="/x">' ] as $tag ) {
		check( "inline formatting survives: $tag", str_contains( $html, $tag ), $html );
	}

	$html = render_notice( [ 'message' => 'Harmlos<script>alert(1)</script><div>Block</div>' ] );
	check( 'script tag stripped', ! str_contains( $html, '<script' ), $html );
	check( 'block-level tag stripped', ! str_contains( $html, '<div>Block' ), $html );

	// --- Layout + conditional logic ---

	check( 'full width adds its modifier', str_contains( render_notice(), 'flinkform-notice--full-width' ) );
	check( 'full width can be switched off', ! str_contains( render_notice( [ 'fullWidth' => false ] ), 'flinkform-notice--full-width' ) );
	check(
		'conditional logic lands on the wrapper',
		str_contains( render_notice( [ 'conditionalLogic' => [ 'enabled' => true ] ] ), 'data-flinkform-condition="COND"' )
	);
	check( 'no condition attribute when unset', ! str_contains( render_notice(), 'data-flinkform-condition' ) );

	// --- Summary ---

	echo "\n";
	if ( $failed > 0 ) {
		echo "$failed FAILED, $passed passed.\n";
		exit( 1 );
	}
	echo "All $passed tests passed.\n";
	exit( 0 );
}
