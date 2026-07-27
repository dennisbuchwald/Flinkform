#!/usr/bin/env php
<?php
/**
 * Standalone checks for the editor's script translations.
 *
 * WordPress finds a JED file by hashing the script's path relative to the
 * plugin folder: `<domain>-<locale>-<md5(relative path)>.json`. Get that
 * name wrong by one character and nothing breaks loudly — the editor just
 * silently stays English, which is exactly how it went unnoticed until
 * 1.8.1. These checks recompute the name the way core does and assert a
 * matching file exists for every editor script we ship.
 *
 * They also assert the wiring itself: register_block_type() sets script
 * translations WITHOUT a path, so the bundled files are invisible unless
 * the Registry re-registers them with one.
 *
 * Run:  php tests/script-translations-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

$root = __DIR__ . '/..';

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

// --- The Registry must pass a path -----------------------------------

$registry = (string) file_get_contents( $root . '/includes/Blocks/Registry.php' );
check(
	'Registry calls wp_set_script_translations',
	str_contains( $registry, 'wp_set_script_translations(' ),
	'without it the bundled JED files are never read'
);
check(
	'the call carries the plugin languages path',
	(bool) preg_match( '/wp_set_script_translations\([^)]*FLINKFORM_PLUGIN_DIR\s*\.\s*.languages./', $registry ),
	'a path-less call only searches WP_LANG_DIR/plugins'
);

// --- Every editor script needs a matching JED file --------------------

$locale  = 'de_DE';
$scripts = glob( $root . '/build/*/index.js' );
sort( $scripts );

check( 'editor scripts found', count( $scripts ) > 0, (string) count( $scripts ) );

foreach ( $scripts as $script ) {
	// Core strips the plugin folder from the URL, leaving exactly this.
	$relative = 'build/' . basename( dirname( $script ) ) . '/index.js';
	$expected = sprintf( '%s/languages/flinkform-%s-%s.json', $root, $locale, md5( $relative ) );

	if ( ! file_exists( $expected ) ) {
		check( "JED file exists for $relative", false, 'expected ' . basename( $expected ) );
		continue;
	}

	$data = json_decode( (string) file_get_contents( $expected ), true );
	$messages = $data['locale_data']['messages'] ?? [];

	check( "JED file exists for $relative", true );
	check(
		"$relative: JED has the required header entry",
		isset( $messages[''] ) && isset( $messages['']['domain'] ),
		basename( $expected )
	);
	check(
		"$relative: JED is not empty",
		count( $messages ) > 1,
		count( $messages ) . ' entries'
	);

	// A translation that is not in the bundle is dead weight; one that is
	// in the bundle but missing here shows up as English in the editor.
	$source = (string) file_get_contents( $script );
	$stray  = [];
	foreach ( array_keys( $messages ) as $msgid ) {
		if ( '' !== $msgid && ! str_contains( $source, (string) $msgid ) ) {
			$stray[] = $msgid;
		}
	}
	check(
		"$relative: every JED string actually occurs in the bundle",
		[] === $stray,
		count( $stray ) . ' stray: ' . implode( ', ', array_slice( $stray, 0, 3 ) )
	);
}

// --- No orphaned JED files -------------------------------------------

$expected_names = [];
foreach ( $scripts as $script ) {
	$relative                            = 'build/' . basename( dirname( $script ) ) . '/index.js';
	$expected_names[ md5( $relative ) ]  = true;
}
$orphans = [];
foreach ( (array) glob( $root . '/languages/flinkform-de_DE-*.json' ) as $file ) {
	if ( preg_match( '/flinkform-de_DE-([0-9a-f]{32})\.json$/', (string) $file, $m ) && ! isset( $expected_names[ $m[1] ] ) ) {
		$orphans[] = basename( (string) $file );
	}
}
check( 'no orphaned JED files', [] === $orphans, implode( ', ', $orphans ) );

// --- Summary ----------------------------------------------------------

echo "\n";
if ( $failed > 0 ) {
	echo "$failed FAILED, $passed passed.\n";
	exit( 1 );
}
echo "All $passed tests passed.\n";
exit( 0 );
