#!/usr/bin/env php
<?php
/**
 * Standalone checks for the translation of untouched block defaults.
 *
 * Blocks\DefaultStrings and shared/default-label.js both hold a copy of
 * the English defaults from block.json, because both need them as msgids.
 * Three copies of the same literal drift the moment someone edits one, and
 * the failure is silent: the label simply stays English. These checks
 * compare all three against each other and against the catalogue.
 *
 * Run:  php tests/default-strings-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}

	$root = __DIR__ . '/..';

	// Record what the filter is asked to translate, then hand back a marker
	// so the assertions can tell "translated" from "left alone".
	$GLOBALS['translated'] = [];
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
		return true;
	}
	function __( $text, $domain = '' ) {
		$GLOBALS['translated'][] = $text;
		return 'DE:' . $text;
	}

	require_once $root . '/includes/Blocks/DefaultStrings.php';

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

	$map = \Flinkform\Blocks\DefaultStrings::defaults();

	// --- The map must agree with block.json ------------------------------

	$from_json = [];
	foreach ( (array) glob( $root . '/src/*/block.json' ) as $file ) {
		$data = json_decode( (string) file_get_contents( (string) $file ), true );
		$name = $data['name'] ?? '';
		foreach ( [ 'label', 'title' ] as $attribute ) {
			$default = $data['attributes'][ $attribute ]['default'] ?? null;
			if ( is_string( $default ) && '' !== trim( $default ) ) {
				$from_json[ $name ][ $attribute ] = $default;
			}
		}
	}

	// The form container's own defaults are handled in its edit.js, which
	// has to keep them for backwards compatibility with older markup.
	unset( $from_json['flinkform/form'] );

	foreach ( $from_json as $block => $attributes ) {
		foreach ( $attributes as $attribute => $default ) {
			check(
				"$block/$attribute is covered by the map",
				isset( $map[ $block ][ $attribute ] ),
				'block.json default: ' . $default
			);
			if ( isset( $map[ $block ][ $attribute ] ) ) {
				check(
					"$block/$attribute matches block.json exactly",
					$map[ $block ][ $attribute ] === $default,
					sprintf( 'map "%s" vs json "%s"', $map[ $block ][ $attribute ], $default )
				);
			}
		}
	}

	foreach ( $map as $block => $attributes ) {
		foreach ( $attributes as $attribute => $default ) {
			check( "$block/$attribute still exists in block.json", isset( $from_json[ $block ][ $attribute ] ) );
		}
	}

	// --- The JS helper must know every default the PHP map has ------------

	$js = (string) file_get_contents( $root . '/src/shared/default-label.js' );
	foreach ( $map as $block => $attributes ) {
		foreach ( $attributes as $default ) {
			check(
				"the editor helper knows \"$default\"",
				(bool) preg_match( '/__\(\s*\'' . preg_quote( $default, '/' ) . '\'/', $js ),
				'missing from src/shared/default-label.js'
			);
		}
	}

	// --- Behaviour --------------------------------------------------------

	$strings = new \Flinkform\Blocks\DefaultStrings();

	$out = $strings->translate_defaults( [ 'blockName' => 'flinkform/field-number', 'attrs' => [] ] );
	check( 'an untouched default is filled in', 'DE:Number' === ( $out['attrs']['label'] ?? null ), json_encode( $out['attrs'] ) );

	$out = $strings->translate_defaults( [ 'blockName' => 'flinkform/field-number', 'attrs' => [ 'label' => 'Anzahl Kinder' ] ] );
	check( 'an author-set label is left alone', 'Anzahl Kinder' === $out['attrs']['label'] );

	// The author typed the English default on purpose — present in the
	// markup, so it must survive untranslated.
	$out = $strings->translate_defaults( [ 'blockName' => 'flinkform/field-number', 'attrs' => [ 'label' => 'Number' ] ] );
	check( 'a deliberately English label survives', 'Number' === $out['attrs']['label'] );

	$out = $strings->translate_defaults( [ 'blockName' => 'core/paragraph', 'attrs' => [] ] );
	check( 'other blocks are untouched', [] === $out['attrs'] );

	$out = $strings->translate_defaults( [ 'blockName' => 'flinkform/field-text' ] );
	check( 'a block without an attrs key does not fatal', isset( $out['attrs']['label'] ) );

	$out = $strings->translate_defaults( [ 'blockName' => null, 'attrs' => [] ] );
	check( 'a null block name is survivable', [] === $out['attrs'] );

	// --- Every default must be a real msgid --------------------------------

	$po = (string) file_get_contents( $root . '/languages/flinkform-de_DE.po' );
	foreach ( $map as $block => $attributes ) {
		foreach ( $attributes as $default ) {
			check(
				"\"$default\" is in the German catalogue",
				str_contains( $po, 'msgid "' . $default . '"' ),
				'no msgid in flinkform-de_DE.po'
			);
		}
	}

	// --- Summary ------------------------------------------------------------

	echo "\n";
	if ( $failed > 0 ) {
		echo "$failed FAILED, $passed passed.\n";
		exit( 1 );
	}
	echo "All $passed tests passed.\n";
	exit( 0 );
}
