#!/usr/bin/env php
<?php
/**
 * Pro hints (1.15.0): on for Free, off the moment Pro docks through the
 * Bridge, off by filter, links in the site's language with UTM only.
 *
 * Run:  php tests/upsell-test.php
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/../' );
	$GLOBALS['filters'] = [];
	$GLOBALS['locale']  = 'de_DE_formal';
	function add_filter( $h, $cb ) { $GLOBALS['filters'][ $h ][] = $cb; }
	function apply_filters( $h, $v, ...$a ) { foreach ( $GLOBALS['filters'][ $h ] ?? [] as $cb ) { $v = $cb( $v, ...$a ); } return $v; }
	function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $k ) ); }
	function determine_locale() { return $GLOBALS['locale']; }
	function add_query_arg( array $args, string $url ) { return $url . '?' . http_build_query( $args ); }

	require __DIR__ . '/../includes/Bridge/Features.php';
	require __DIR__ . '/../includes/Admin/Upsell.php';

	use Flinkform\Admin\Upsell;

	$passed = 0; $failed = 0;
	function check( string $l, bool $ok, string $d = '' ): void { global $passed, $failed; if ( $ok ) { ++$passed; return; } ++$failed; echo "FAIL: $l" . ( $d ? " — $d" : '' ) . "\n"; }

	check( 'free: hints on', Upsell::enabled() );
	$url = Upsell::url( 'field-picker' );
	check( 'German site links the German page', str_starts_with( $url, 'https://flinkform.de/pro/?' ), $url );
	check( 'UTM tells where it was clicked', str_contains( $url, 'utm_content=field-picker' ) && str_contains( $url, 'utm_source=flinkform-free' ) );
	parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
	check( 'only the four UTM keys, nothing about site or user', [ 'utm_campaign', 'utm_content', 'utm_medium', 'utm_source' ] === ( static function ( $k ) { sort( $k ); return $k; } )( array_keys( $q ) ) );
	$GLOBALS['locale'] = 'en_US';
	check( 'other languages link the English page', str_starts_with( Upsell::url( 'x' ), 'https://flinkform.de/en/pro/?' ) );

	add_filter( 'flinkform_show_pro_upsell', '__return_false' );
	function __return_false() { return false; }
	check( 'filter switches everything off', ! Upsell::enabled() );
	$GLOBALS['filters']['flinkform_show_pro_upsell'] = [];

	add_filter( 'flinkform_pro_features', static fn ( $f ) => array_merge( $f, [ 'smtp' ] ) );
	check( 'Pro docked through the Bridge: hints off', ! Upsell::enabled() );

	echo "\n";
	if ( $failed ) { echo "$failed FAILED, $passed passed.\n"; exit( 1 ); }
	echo "All $passed tests passed.\n";
}
