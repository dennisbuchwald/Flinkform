#!/usr/bin/env php
<?php
/**
 * Black Week notice (1.15.2): days left, the German fallback to the bundled
 * .mo when the language pack does not know a string yet, and the rendered
 * text in English, du and Sie.
 *
 * Needs WordPress' pomo library (MO class). Set WP_POMO to a wp-includes/pomo
 * folder, otherwise a known local WordPress copy is used; without one the
 * fallback checks are skipped.
 *
 * Run:  php tests/promo-notice-test.php
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/../' );
	define( 'FLINKFORM_PLUGIN_DIR', __DIR__ . '/../' );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );

	$pomo = getenv( 'WP_POMO' ) ?: '';
	foreach ( [ $pomo, getenv( 'HOME' ) . '/Arbeitsplatz/01_Code/02_Eigenentwicklungen/wp-mcp-connector-plus/tests/wp-real/.cache/wordpress/wp-includes/pomo' ] as $dir ) {
		if ( $dir && is_readable( $dir . '/mo.php' ) ) {
			require_once $dir . '/mo.php';
			break;
		}
	}

	$GLOBALS['locale']     = 'de_DE';
	$GLOBALS['pack']       = []; // What the "language pack" knows: msgid => translation.
	$GLOBALS['transients'] = [];
	$GLOBALS['total']      = 25;

	function determine_locale() { return $GLOBALS['locale']; }
	function __( $t, $d = null ) { return $GLOBALS['pack'][ $t ] ?? $t; }
	function _n( $s, $p, $n, $d = null ) { return $GLOBALS['pack'][ $s ] ?? ( 1 === $n ? $s : $p ); }
	function get_transient( $k ) { return $GLOBALS['transients'][ $k ] ?? false; }
	function set_transient( $k, $v ) { $GLOBALS['transients'][ $k ] = $v; }
	function number_format_i18n( $n ) { return (string) $n; }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_attr( $s ) { return esc_html( $s ); }
	function esc_url( $s ) { return esc_html( $s ); }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
	function add_query_arg( array $a, string $u ) { return $u . '?' . http_build_query( $a ); }
	function wp_nonce_url( $u ) { return $u . '&_wpnonce=x'; }
	function wp_create_nonce() { return 'x'; }
	function wp_unslash( $v ) { return $v; }
	function current_user_can() { return true; }
	function get_user_meta() { return ''; }
	function get_current_user_id() { return 1; }
	function get_current_screen() { return (object) [ 'id' => 'dashboard' ]; }
	function apply_filters( $h, $v ) { return $v; }

	require __DIR__ . '/../includes/Bridge/Features.php';
	require __DIR__ . '/../includes/Admin/Upsell.php';
}

namespace Flinkform\Admin {
	final class Menu { public const CAPABILITY = 'manage_options'; }
}

namespace Flinkform\Submissions {
	final class Repository { public function count(): int { return $GLOBALS['total']; } }
}

namespace {
	require __DIR__ . '/../includes/Admin/PromoNotice.php';

	use Flinkform\Admin\PromoNotice;

	$passed = 0; $failed = 0; $skipped = 0;
	function check( string $l, bool $ok, string $d = '' ): void { global $passed, $failed; if ( $ok ) { ++$passed; return; } ++$failed; echo "FAIL: $l" . ( $d ? " — $d" : '' ) . "\n"; }
	function html(): string {
		$GLOBALS['transients'] = [];
		ob_start();
		( new PromoNotice() )->render();
		return (string) ob_get_clean();
	}

	// Days left, counted up.
	check( 'first second: 18 days', 18 === PromoNotice::days_left( PromoNotice::START ), (string) PromoNotice::days_left( PromoNotice::START ) );
	check( 'last day says 1', 1 === PromoNotice::days_left( PromoNotice::END - 60 ) );
	check( 'never 0 or less', 1 === PromoNotice::days_left( PromoNotice::END + 999 ) );
	check( '19 Nov 10:00 Berlin: 12 days', 12 === PromoNotice::days_left( 1795078835 ), (string) PromoNotice::days_left( 1795078835 ) );

	// English site: plain text, no fallback.
	$GLOBALS['locale'] = 'en_US';
	$en = html();
	check( 'en: price in the headline', str_contains( $en, 'Flinkform Pro for 29.50 € instead of 59 € in the first year' ) );
	check( 'en: submissions mentioned from 10 on', str_contains( $en, 'already collected 25 submissions on this site' ) );
	check( 'en: four benefits', 4 === substr_count( $en, '<li>' ) );
	check( 'en: benefit lead in bold', str_contains( $en, '<strong>Every email arrives:</strong>' ) );
	check( 'en: support line', str_contains( $en, 'keeps the free plugin free' ) );
	check( 'en: price on the button', str_contains( $en, 'Get Pro for 29.50 €' ) );
	$GLOBALS['total'] = 3;
	check( 'en: under 10 submissions no count', ! str_contains( html(), 'already collected' ) && str_contains( html(), 'Pro gets more out of every form:' ) );
	$GLOBALS['total'] = 1;

	if ( ! class_exists( 'MO' ) ) {
		++$skipped;
		echo "skip: German fallback (no pomo library, set WP_POMO)\n";
	} else {
		// Old language pack: knows nothing of the new strings → bundled .mo.
		$GLOBALS['locale'] = 'de_DE';
		$GLOBALS['total']  = 1;
		$du = html();
		check( 'du: headline from the bundled file', str_contains( $du, 'Flinkform Pro für 29,50 € statt 59 € im ersten Jahr' ), $du );
		check( 'du: benefits in German', str_contains( $du, '<strong>Jede Mail kommt an:</strong>' ) );
		check( 'du: support line in du', str_contains( $du, 'Brauchst du nichts davon?' ) );
		check( 'du: under 10 no count', str_contains( $du, 'Mit Pro holst du mehr aus jedem Formular:' ) );
		$GLOBALS['total'] = 1;
		check( 'du: singular day', str_contains( PromoNotice::t( 'Only %s day left, for every annual plan. Not convinced? 14 days money back, no questions asked.', 'Only %s days left, for every annual plan. Not convinced? 14 days money back, no questions asked.', 1 ), 'Nur noch %s Tag,' ) );
		check( 'du: plural days', str_contains( PromoNotice::t( 'Only %s day left, for every annual plan. Not convinced? 14 days money back, no questions asked.', 'Only %s days left, for every annual plan. Not convinced? 14 days money back, no questions asked.', 5 ), 'Nur noch %s Tage,' ) );
		check( 'du: no English left', ! str_contains( $du, 'first year' ) && ! str_contains( $du, 'money back' ) );

		$GLOBALS['locale'] = 'de_DE_formal';
		$GLOBALS['total']  = 40;
		$sie = html();
		check( 'Sie: count in Sie', str_contains( $sie, 'Ihre Formulare haben auf dieser Seite schon 40 Anfragen eingesammelt' ), $sie );
		check( 'Sie: support line in Sie', str_contains( $sie, 'Brauchen Sie nichts davon?' ) );

		// A language pack that has the string wins over the bundled file.
		$GLOBALS['pack'] = [ 'Get Pro for 29.50 €' => 'PACK' ];
		check( 'language pack wins when it knows the string', 'PACK' === PromoNotice::t( 'Get Pro for 29.50 €' ) );
	}

	echo ( $failed ? "FAILED: $failed, " : '' ) . "$passed passed" . ( $skipped ? ", $skipped skipped" : '' ) . "\n";
	exit( $failed ? 1 : 0 );
}
