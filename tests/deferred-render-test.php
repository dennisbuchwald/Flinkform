#!/usr/bin/env php
<?php
/**
 * Renders the real form-container template and inspects the HTML.
 *
 * This is the test that guards the actual claim of 1.14.0: a cached page
 * must contain nothing that is only valid for the request that produced
 * it. Everything else in this release is machinery around that sentence.
 *
 * Four values used to be baked into the markup, and each one fails in its
 * own way once the HTML is served from a cache to somebody else:
 *
 *   flinkform_ts          the minimum-fill-time gate ends up measuring the
 *                         age of the cache entry, not the visitor
 *   _flinkform_nonce      valid 12-24 h, then every visitor gets WordPress's
 *                         "Are you sure you want to do this?" screen
 *   flinkform_spam_token  30-minute TTL, then submissions are rejected
 *   the math question     the answer belongs to a salt nobody holds any more
 *
 * So: render the block for real, in both modes, and assert on the output.
 * A grep-level assertion is deliberate here — it is exactly the check a
 * person would run against the live page (view source, look for a value),
 * and it fails loudly if any future edit puts one of these back.
 *
 * Run:  php tests/deferred-render-test.php
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}
	if ( ! defined( 'FLINKFORM_VERSION' ) ) {
		define( 'FLINKFORM_VERSION', '1.14.0' );
	}

	// --- WordPress stubs --------------------------------------------------

	$GLOBALS['transients'] = [];
	$GLOBALS['logged_in']  = false;
	$GLOBALS['inline_scripts'] = [];

	function wp_salt( $scheme = 'auth' ) {
		return 'test-salt-' . $scheme;
	}
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
	function get_transient( $key ) {
		return $GLOBALS['transients'][ $key ] ?? false;
	}
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['transients'][ $key ] = $value;
		return true;
	}
	function delete_transient( $key ) {
		unset( $GLOBALS['transients'][ $key ] );
		return true;
	}
	function __( $text, $domain = '' ) {
		return $text;
	}
	function esc_html__( $text, $domain = '' ) {
		return $text;
	}
	function esc_attr__( $text, $domain = '' ) {
		return $text;
	}
	function esc_html_e( $text, $domain = '' ) {
		echo $text;
	}
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
	function esc_url( $url ) {
		return (string) $url;
	}
	function wp_kses_post( $text ) {
		return $text;
	}
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
	}
	function sanitize_text_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
	function wp_unslash( $value ) {
		return $value;
	}
	function absint( $value ) {
		return abs( (int) $value );
	}
	function is_user_logged_in() {
		return (bool) $GLOBALS['logged_in'];
	}
	function wp_create_nonce( $action = -1 ) {
		return 'live-nonce-' . md5( (string) $action );
	}
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		$html = '<input type="hidden" name="' . $name . '" value="' . wp_create_nonce( $action ) . '" />';
		if ( $referer ) {
			$html .= wp_referer_field( false );
		}
		if ( $display ) {
			echo $html;
		}
		return $html;
	}
	function wp_referer_field( $display = true ) {
		$html = '<input type="hidden" name="_wp_http_referer" value="/kontakt/" />';
		if ( $display ) {
			echo $html;
		}
		return $html;
	}
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . $path;
	}
	function rest_url( $path = '' ) {
		return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' );
	}
	function get_the_ID() {
		return 42;
	}
	function add_query_arg( ...$args ) {
		if ( is_array( $args[0] ) ) {
			$pairs = $args[0];
			$url   = $args[1] ?? '/kontakt/';
		} else {
			$pairs = [ $args[0] => $args[1] ];
			$url   = $args[2] ?? '/kontakt/';
		}
		$query = [];
		foreach ( $pairs as $key => $value ) {
			$query[] = $key . '=' . $value;
		}
		return $url . ( str_contains( (string) $url, '?' ) ? '&' : '?' ) . implode( '&', $query );
	}
	function apply_filters( $hook, $value, ...$rest ) {
		if ( isset( $GLOBALS['filter_overrides'][ $hook ] ) ) {
			return ( $GLOBALS['filter_overrides'][ $hook ] )( $value, ...$rest );
		}
		return $value;
	}
	function get_block_wrapper_attributes( $extra = [] ) {
		$out = [];
		foreach ( $extra as $key => $value ) {
			$out[] = $key . '="' . esc_attr( $value ) . '"';
		}
		return implode( ' ', $out );
	}
	function wp_register_script( ...$args ) {
		return true;
	}
	function wp_enqueue_script( ...$args ) {
		return true;
	}
	function wp_script_is( $handle, $list = 'enqueued' ) {
		return true;
	}
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		$GLOBALS['inline_scripts'][] = $data;
		return true;
	}
	function wp_interactivity_state( $store = '', $state = [] ) {
		return $state;
	}

	require_once __DIR__ . '/../includes/Spam/Challenge.php';
	require_once __DIR__ . '/../includes/Spam/RefreshEndpoint.php';
	require_once __DIR__ . '/../includes/Spam/Renderer.php';
	require_once __DIR__ . '/../includes/Spam/Guard.php';
	require_once __DIR__ . '/../includes/Conditions/RuleEvaluator.php';
	require_once __DIR__ . '/../includes/Conditions/Wrapper.php';
	require_once __DIR__ . '/../includes/Submissions/Handler.php';
	require_once __DIR__ . '/../includes/Spam/RenderMode.php';

	use Flinkform\Spam\RenderMode;

	$passed = 0;
	$failed = 0;

	function check( string $label, bool $ok, string $detail = '' ): void {
		if ( $ok ) {
			$GLOBALS['passed']++;
			return;
		}
		$GLOBALS['failed']++;
		echo 'FAIL: ' . $label . ( '' !== $detail ? ' — ' . $detail : '' ) . "\n";
	}

	/** Stand-in for WP_Block: render.php only walks inner_blocks. */
	class FakeBlock {
		public string $name;
		public array $inner_blocks;
		public array $attributes;

		public function __construct( string $name, array $inner_blocks = [], array $attributes = [] ) {
			$this->name         = $name;
			$this->inner_blocks = $inner_blocks;
			$this->attributes   = $attributes;
		}
	}
	class_alias( 'FakeBlock', 'WP_Block' );

	/**
	 * Render the form-container template exactly the way WordPress does.
	 *
	 * Inside a function on purpose: render.php uses a file-scope `static`
	 * for the once-per-page marker script, which is only legal in a
	 * function scope — and it is how WordPress includes the file.
	 */
	function render_form( array $attributes, \WP_Block $block ): string {
		$content = '';
		ob_start();
		require __DIR__ . '/../src/form-container/render.php';
		return (string) ob_get_clean();
	}

	$attrs = [
		'formId'      => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
		'submitLabel' => 'Send',
	];
	$block = new FakeBlock( 'flinkform/form-container', [], $attrs );

	// --- The deferred render: the cache file must be inert ----------------

	$_GET    = [];
	$_COOKIE = [];
	$deferred_html = render_form( $attrs, $block );

	check(
		'deferred: renders a form at all',
		str_contains( $deferred_html, '<form' ) && str_contains( $deferred_html, 'flinkform_submit' )
	);
	check(
		'deferred: the nonce field is empty',
		str_contains( $deferred_html, 'name="_flinkform_nonce" value=""' ),
		'a real nonce in cached HTML expires and locks every later visitor out'
	);
	check(
		'deferred: no minted nonce leaked into the markup',
		! str_contains( $deferred_html, 'live-nonce-' )
	);
	check(
		'deferred: the timestamp field is empty',
		str_contains( $deferred_html, 'name="flinkform_ts" value=""' ),
		'a cached timestamp measures the age of the cache entry, not the visitor'
	);
	check(
		'deferred: the spam token is empty',
		str_contains( $deferred_html, 'name="flinkform_spam_token" value=""' )
	);
	check(
		'deferred: the proof-of-work salt is empty',
		str_contains( $deferred_html, 'data-flinkform-pow-salt=""' )
	);
	check(
		'deferred: no arithmetic question in the markup',
		0 === preg_match( '/\d\s*\+\s*\d/', $deferred_html ),
		'the question was the most visible request-specific value of all'
	);
	check(
		'deferred: the page is NOT excluded from the cache',
		! defined( 'DONOTCACHEPAGE' ),
		'this single constant is what cost ~0.7 s of TTFB on every form page'
	);
	check(
		'deferred: the form is marked for the arming script',
		str_contains( $deferred_html, 'data-flinkform-challenge="deferred"' )
	);
	check(
		'deferred: both challenge transports are offered',
		str_contains( $deferred_html, 'data-flinkform-challenge-url="' )
			&& str_contains( $deferred_html, 'data-flinkform-challenge-fallback-url="' ),
		'REST alone is not enough — plenty of sites wall off /wp-json/'
	);
	check(
		'deferred: the server can recognise the submission',
		str_contains( $deferred_html, 'name="' . RenderMode::MARKER_FIELD . '" value="deferred"' ),
		'without the marker an empty nonce looks like an attack and is dropped silently'
	);
	check(
		'deferred: the no-JS escape hatch is present',
		str_contains( $deferred_html, '<noscript>' )
			&& str_contains( $deferred_html, RenderMode::NOJS_QUERY_ARG . '=' . $attrs['formId'] ),
		'without JavaScript the challenge cannot be fetched, so there has to be a way out'
	);
	check(
		'deferred: the wrapper carries the class the stylesheet needs',
		str_contains( $deferred_html, 'flinkform-form--deferred' ),
		'this class is what hides the unusable form from visitors without scripting'
	);
	check(
		'deferred: the anchor the error redirect points at exists',
		str_contains( $deferred_html, 'id="flinkform-form-' . $attrs['formId'] . '"' )
	);

	// --- The inline render: unchanged from before 1.14.0 ------------------

	$_GET = [ RenderMode::NOJS_QUERY_ARG => $attrs['formId'] ];
	$inline_html = render_form( $attrs, $block );

	check(
		'inline: a real nonce is rendered',
		str_contains( $inline_html, 'name="_flinkform_nonce" value="live-nonce-' )
	);
	check(
		'inline: a signed timestamp is rendered',
		1 === preg_match( '/name="flinkform_ts" value="[A-Za-z0-9_\-]+\.[a-f0-9]{64}"/', $inline_html )
	);
	check(
		'inline: a real spam token is rendered',
		1 === preg_match( '/name="flinkform_spam_token" value="[^"]{20,}"/', $inline_html )
	);
	check(
		'inline: an arithmetic question is rendered',
		1 === preg_match( '/\d\s*\+\s*\d/', $inline_html ),
		'the no-JS route depends on it'
	);
	check(
		'inline: the math answer stays required',
		str_contains( $inline_html, 'size="4" required' )
	);
	check(
		'inline: the page IS excluded from the cache',
		defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE,
		'a per-visitor render must never be written into a shared cache'
	);
	check(
		'inline: no deferred wiring',
		! str_contains( $inline_html, 'data-flinkform-challenge="deferred"' )
			&& ! str_contains( $inline_html, '<noscript>' )
	);

	// --- Spam protection off: nonce and timestamp still have to arrive ----
	//
	// A form with spamProtection "none" has no spam block, so the arming
	// wiring cannot live there. If it ever moves back onto the block, every
	// unprotected form silently loses its nonce and timestamp.

	$_GET       = [];
	$unprotected = [ 'formId' => '11111111-2222-3333-4444-555555555555', 'spamProtection' => 'none' ];
	$plain_html  = render_form( $unprotected, new FakeBlock( 'flinkform/form-container', [], $unprotected ) );

	check(
		'unprotected: still marked deferred',
		str_contains( $plain_html, 'data-flinkform-challenge="deferred"' )
	);
	check(
		'unprotected: challenge URL is on the form, not on the spam block',
		str_contains( $plain_html, 'data-flinkform-challenge-url="' )
			&& ! str_contains( $plain_html, 'data-flinkform-spam="1"' ),
		'there is no spam block here to hang the arming wiring on'
	);
	check(
		'unprotected: nonce and timestamp are still deferred',
		str_contains( $plain_html, 'name="_flinkform_nonce" value=""' )
			&& str_contains( $plain_html, 'name="flinkform_ts" value=""' )
	);
	check(
		'unprotected: the escape hatch is still offered',
		str_contains( $plain_html, '<noscript>' )
	);

	if ( $failed > 0 ) {
		echo "\n{$failed} of " . ( $passed + $failed ) . " tests failed.\n";
		exit( 1 );
	}
	echo "All {$passed} tests passed.\n";
}
