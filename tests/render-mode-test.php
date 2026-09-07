#!/usr/bin/env php
<?php
/**
 * Standalone unit tests for Spam\RenderMode and the deferred challenge markup.
 *
 * What these pin, and why each one is expensive to get wrong:
 *
 *   - DEFERRED is the default. Every render that falls back to INLINE
 *     drags DONOTCACHEPAGE back in and costs the site its page cache on
 *     exactly the pages that are supposed to convert (measured at ~0.7 s
 *     of extra TTFB per view before 1.14.0).
 *   - The four conditions that MUST stay inline are not negotiable by
 *     filter: a flashed error state, a success/error URL, the no-JS
 *     escape hatch and a form containing a payment field. Two of them
 *     (flash cookie, status arg) render one visitor's data — caching
 *     those would serve it to the next visitor.
 *   - Renderer::render() must emit no token, no salt, no difficulty and
 *     no question in deferred mode. Anything request-specific that slips
 *     into the markup lands in the cache file and is wrong, expired or
 *     someone else's by the time the next visitor gets it.
 *   - The math input must not be `required` in deferred mode: it is
 *     hidden until the proof of work fails, and a hidden required control
 *     makes the browser refuse the submit with nothing to focus.
 *
 * Run:  php tests/render-mode-test.php
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

	// --- WordPress stubs --------------------------------------------------

	$GLOBALS['transients']    = [];
	$GLOBALS['logged_in']     = false;
	$GLOBALS['applied_filters'] = [];

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
	function __( $text, $domain = '' ) {
		return $text;
	}
	function esc_html__( $text, $domain = '' ) {
		return $text;
	}
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
	function esc_url( $url ) {
		return $url;
	}
	function is_user_logged_in() {
		return (bool) $GLOBALS['logged_in'];
	}
	function wp_create_nonce( $action = -1 ) {
		return 'nonce-for-' . $action;
	}
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . $path;
	}
	function rest_url( $path = '' ) {
		return 'https://example.test/wp-json/' . ltrim( $path, '/' );
	}
	function add_query_arg( ...$args ) {
		if ( is_array( $args[0] ) ) {
			$pairs = $args[0];
			$url   = $args[1] ?? '';
		} else {
			$pairs = [ $args[0] => $args[1] ];
			$url   = $args[2] ?? '';
		}
		$query = [];
		foreach ( $pairs as $key => $value ) {
			$query[] = $key . '=' . $value;
		}
		return $url . ( str_contains( (string) $url, '?' ) ? '&' : '?' ) . implode( '&', $query );
	}
	function apply_filters( $hook, $value, ...$rest ) {
		$GLOBALS['applied_filters'][ $hook ] = true;
		if ( isset( $GLOBALS['filter_overrides'][ $hook ] ) ) {
			return ( $GLOBALS['filter_overrides'][ $hook ] )( $value, ...$rest );
		}
		return $value;
	}

	require_once __DIR__ . '/../includes/Spam/Challenge.php';
	require_once __DIR__ . '/../includes/Spam/RefreshEndpoint.php';
	require_once __DIR__ . '/../includes/Spam/Renderer.php';
	require_once __DIR__ . '/../includes/Submissions/Handler.php';
	require_once __DIR__ . '/../includes/Spam/RenderMode.php';

	use Flinkform\Spam\RefreshEndpoint;
	use Flinkform\Spam\Renderer;
	use Flinkform\Spam\RenderMode;
	use Flinkform\Submissions\Handler;

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

	/** Reset every stubbed request-scope global between cases. */
	function reset_request(): void {
		$_GET                        = [];
		$_COOKIE                     = [];
		$GLOBALS['logged_in']        = false;
		$GLOBALS['filter_overrides'] = [];
	}

	/**
	 * Minimal WP_Block stand-in — RenderMode only ever walks inner_blocks
	 * and reads names.
	 */
	class FakeBlock {
		public string $name;
		public array $inner_blocks;

		public function __construct( string $name, array $inner_blocks = [] ) {
			$this->name         = $name;
			$this->inner_blocks = $inner_blocks;
		}
	}

	// RenderMode type-hints \WP_Block, so the stand-in has to answer to
	// that name. Aliasing keeps the production signature honest instead of
	// loosening it for the test.
	class_alias( 'FakeBlock', 'WP_Block' );

	// --- The default is what the whole release is about -------------------

	reset_request();
	check(
		'default is deferred',
		RenderMode::DEFERRED === RenderMode::resolve( 'form-1', [], null )
	);
	check(
		'default does not define DONOTCACHEPAGE',
		! defined( 'DONOTCACHEPAGE' )
	);

	// --- Conditions that force an inline render ---------------------------

	reset_request();
	$GLOBALS['logged_in'] = true;
	check(
		'logged-in visitors render inline',
		RenderMode::INLINE === RenderMode::resolve( 'form-1', [], null ),
		'page caches skip logged-in requests anyway, and the editor preview renders through this path'
	);

	reset_request();
	$_GET[ RenderMode::NOJS_QUERY_ARG ] = 'form-1';
	check(
		'the no-JS escape hatch renders inline',
		RenderMode::INLINE === RenderMode::resolve( 'form-1', [], null )
	);

	reset_request();
	$_GET['flinkform_status'] = 'error';
	check(
		'an error state renders inline',
		RenderMode::INLINE === RenderMode::resolve( 'form-1', [], null ),
		'the re-populated form carries one visitor\'s input'
	);

	reset_request();
	$_GET['flinkform_status'] = 'success';
	check(
		'a success state renders inline',
		RenderMode::INLINE === RenderMode::resolve( 'form-1', [], null )
	);

	reset_request();
	$_COOKIE[ Handler::FLASH_COOKIE_NAME ] = 'abc123';
	check(
		'a visitor holding a flash cookie renders inline',
		RenderMode::INLINE === RenderMode::resolve( 'form-1', [], null )
	);

	// --- Payment fields, at any nesting depth -----------------------------

	reset_request();
	$flat = new FakeBlock( 'flinkform/form-container', [ new FakeBlock( 'flinkform/field-payment' ) ] );
	check(
		'a payment field forces inline',
		RenderMode::INLINE === RenderMode::resolve( 'form-1', [], $flat ),
		'Pro mints Stripe and wp_rest nonces at render time'
	);

	reset_request();
	$nested = new FakeBlock(
		'flinkform/form-container',
		[
			new FakeBlock( 'core/group', [ new FakeBlock( 'core/columns', [ new FakeBlock( 'flinkform/field-payment' ) ] ) ] ),
		]
	);
	check(
		'a nested payment field forces inline',
		RenderMode::INLINE === RenderMode::resolve( 'form-1', [], $nested ),
		'the search has to be recursive or a payment form inside a group gets cached'
	);

	reset_request();
	$ordinary = new FakeBlock(
		'flinkform/form-container',
		[ new FakeBlock( 'core/group', [ new FakeBlock( 'flinkform/field-text' ) ] ) ]
	);
	check(
		'an ordinary nested form still defers',
		RenderMode::DEFERRED === RenderMode::resolve( 'form-1', [], $ordinary )
	);

	// --- The filter: may force inline, may never force deferred -----------

	reset_request();
	$GLOBALS['filter_overrides']['flinkform_render_challenge_inline'] = static fn( $value ) => true;
	check(
		'the filter can restore the legacy inline mode',
		RenderMode::INLINE === RenderMode::resolve( 'form-1', [], null )
	);

	reset_request();
	$_COOKIE[ Handler::FLASH_COOKIE_NAME ] = 'abc123';
	$GLOBALS['filter_overrides']['flinkform_render_challenge_inline'] = static fn( $value ) => false;
	check(
		'the filter cannot cache a per-visitor render',
		RenderMode::INLINE === RenderMode::resolve( 'form-1', [], null ),
		'returning false here must not be able to leak a flashed form into a shared cache'
	);

	reset_request();
	$GLOBALS['filter_overrides']['flinkform_uncacheable_blocks'] = static fn( $value ) => [ 'acme/widget' ];
	$acme = new FakeBlock( 'flinkform/form-container', [ new FakeBlock( 'acme/widget' ) ] );
	check(
		'add-ons can register their own uncacheable blocks',
		RenderMode::INLINE === RenderMode::resolve( 'form-1', [], $acme )
	);

	// --- The markup: nothing request-specific in deferred mode ------------

	reset_request();
	$deferred_markup = Renderer::render( 'form-1', RenderMode::DEFERRED );
	$inline_markup   = Renderer::render( 'form-1', RenderMode::INLINE );

	check(
		'deferred markup carries an empty token',
		str_contains( $deferred_markup, 'name="flinkform_spam_token" value=""' ),
		'a minted token in cached HTML is expired or wrong for the next visitor'
	);
	check(
		'deferred markup carries an empty salt',
		str_contains( $deferred_markup, 'data-flinkform-pow-salt=""' )
	);
	check(
		'deferred markup carries no difficulty',
		str_contains( $deferred_markup, 'data-flinkform-pow-difficulty="0"' )
	);
	check(
		'deferred markup carries no math question',
		str_contains( $deferred_markup, '<label class="flinkform-form__spam-label" for="flinkform-spam-answer-' )
			&& ! preg_match( '/\d\s*\+\s*\d/', $deferred_markup ),
		'the question is the most visible request-specific value of all'
	);
	check(
		'deferred markup marks itself for view.js',
		str_contains( $deferred_markup, 'data-flinkform-spam-deferred="1"' )
	);
	check(
		'deferred math input is not required',
		! str_contains( $deferred_markup, 'size="4" required' ),
		'a hidden required control makes the browser refuse the submit'
	);
	check(
		'deferred markup offers both transports',
		str_contains( $deferred_markup, 'data-flinkform-refresh-url="' )
			&& str_contains( $deferred_markup, 'data-flinkform-refresh-fallback-url="' )
	);

	check(
		'inline markup still mints a real token',
		preg_match( '/name="flinkform_spam_token" value="[^"]+"/', $inline_markup ) === 1,
		'the legacy path has to keep working for the no-JS route and old installs'
	);
	check(
		'inline markup still asks a question',
		preg_match( '/\d\s*\+\s*\d/', $inline_markup ) === 1
	);
	check(
		'inline math input stays required',
		str_contains( $inline_markup, 'size="4" required' )
	);
	check(
		'inline markup is not marked deferred',
		! str_contains( $inline_markup, 'data-flinkform-spam-deferred' )
	);
	check(
		'the default argument keeps pre-1.14 callers on inline',
		preg_match( '/name="flinkform_spam_token" value="[^"]+"/', Renderer::render( 'form-1' ) ) === 1
	);

	// --- The endpoint has to ship what the markup no longer does ----------

	$payload = RefreshEndpoint::payload( 'form-1' );
	foreach ( [ 'token', 'salt', 'difficulty', 'question', 'nonce', 'ts' ] as $key ) {
		check( "endpoint payload carries {$key}", isset( $payload[ $key ] ) && '' !== (string) $payload[ $key ] );
	}
	check(
		'endpoint timestamp verifies against the same form',
		\Flinkform\Spam\Challenge::verify_timestamp( (string) $payload['ts'], 'form-1' ) > 0,
		'without this the minimum-fill-time gate silently rejects every deferred submission'
	);
	check(
		'endpoint timestamp is bound to its form',
		0 === \Flinkform\Spam\Challenge::verify_timestamp( (string) $payload['ts'], 'other-form' )
	);

	// --- mark_uncacheable is the only place the constant is set -----------

	RenderMode::mark_uncacheable();
	check( 'mark_uncacheable defines the constant', defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );
	RenderMode::mark_uncacheable(); // Must not fatal on a second call.
	check( 'mark_uncacheable is idempotent', defined( 'DONOTCACHEPAGE' ) );

	if ( $failed > 0 ) {
		echo "\n{$failed} of " . ( $passed + $failed ) . " tests failed.\n";
		exit( 1 );
	}
	echo "All {$passed} tests passed.\n";
}
