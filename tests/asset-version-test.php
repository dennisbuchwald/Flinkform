#!/usr/bin/env php
<?php
/**
 * Standalone checks for block asset cache-busting.
 *
 * `register_block_type()` takes the `?ver=` on every stylesheet and script
 * straight from block.json's `version` field. Ours sat at "0.1.0" in every
 * block from the first commit onwards, so across more than thirty releases
 * each asset was served from the identical URL — browsers, CDNs and page
 * caches all kept the very first copy. A CSS fix could be deployed,
 * verified on the server with curl, and still be entirely absent in the
 * visitor's browser.
 *
 * That failure is invisible from the server side, which is what makes it
 * worth a test: the file on disk is correct, only the URL never moves.
 *
 * Run:  php tests/asset-version-test.php
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

// --- The Registry must overwrite the registered version ----------------

$registry = (string) file_get_contents( $root . '/includes/Blocks/Registry.php' );

check(
	'Registry stamps its own asset version',
	str_contains( $registry, 'version_assets' ),
	'without it the ?ver= comes from block.json and never changes'
);
check(
	'the stamp uses the plugin version constant',
	(bool) preg_match( '/->ver\s*=\s*FLINKFORM_VERSION/', $registry ),
	'a literal would go stale the same way block.json did'
);

foreach ( [ 'style_handles', 'editor_style_handles', 'script_handles', 'editor_script_handles', 'view_script_handles' ] as $bucket ) {
	check( "handles covered: $bucket", str_contains( $registry, $bucket ) );
}

// --- The plugin version itself has to move ------------------------------

$plugin  = (string) file_get_contents( $root . '/flinkform.php' );
preg_match( "/define\(\s*'FLINKFORM_VERSION',\s*'([^']+)'/", $plugin, $m );
$version = $m[1] ?? '';
check( 'FLINKFORM_VERSION is defined', '' !== $version, 'not found in flinkform.php' );

preg_match( '/^\s*\*\s*Version:\s*(.+)$/m', $plugin, $m );
$header = trim( $m[1] ?? '' );
check( 'constant and plugin header agree', $version === $header, "constant $version vs header $header" );

preg_match( '/^Stable tag:\s*(.+)$/m', (string) file_get_contents( $root . '/readme.txt' ), $m );
$stable = trim( $m[1] ?? '' );
check( 'readme Stable tag agrees too', $version === $stable, "constant $version vs stable tag $stable" );

// --- And it must actually differ from the stale block.json value --------

$block_versions = [];
foreach ( (array) glob( $root . '/src/*/block.json' ) as $file ) {
	$data = json_decode( (string) file_get_contents( (string) $file ), true );
	if ( isset( $data['version'] ) ) {
		$block_versions[ (string) $data['version'] ][] = basename( dirname( (string) $file ) );
	}
}

check(
	'the plugin version is not one of the frozen block.json values',
	! isset( $block_versions[ $version ] ),
	'if these ever coincide the test stops proving anything'
);

// Not a failure in itself — block.json versions are now unused for
// cache-busting — but worth surfacing so nobody "fixes" them by hand and
// assumes that solved it.
foreach ( $block_versions as $stale => $blocks ) {
	echo sprintf(
		"  note: %d block.json still declare version %s (unused for cache-busting since the Registry overwrites it)\n",
		count( $blocks ),
		$stale
	);
}

// --- Summary -------------------------------------------------------------

echo "\n";
if ( $failed > 0 ) {
	echo "$failed FAILED, $passed passed.\n";
	exit( 1 );
}
echo "All $passed tests passed.\n";
exit( 0 );
