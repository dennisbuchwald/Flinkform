#!/usr/bin/env php
<?php
/**
 * Drives Submissions\Handler::handle() end to end and asserts the outcome.
 *
 * Every other test in this folder checks a piece: the token classification,
 * the render mode, the rule evaluator. None of them ever called handle(),
 * and that is exactly the place where submissions have been lost again and
 * again — each time through a gate that answered with the wrong one of the
 * four possible outcomes:
 *
 *   silent   redirect to the homepage, nothing stored, nothing said.
 *            Correct ONLY for provable bot traffic (forged tokens, unknown
 *            forms). For a person this is the worst outcome of all.
 *   soft     redirect back to the form with ?flinkform_status=error and the
 *            values flashed, so the visitor can send again.
 *   403      wp_die screen. Only for a failed nonce on an inline render.
 *   success  a row is stored (or an identical resubmit replays the outcome).
 *
 * The cases below pin which gate answers with which outcome, including the
 * ones that went wrong in production: F-0 (1.13.0), the write-once
 * timestamp (1.14.2) and the minimum-fill-time gate on deferred forms.
 *
 * Run:  php tests/handler-gates-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

namespace {
	// setcookie() in the flash path must not warn about headers.
	ob_start();

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}
	if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
		define( 'MINUTE_IN_SECONDS', 60 );
	}
	if ( ! defined( 'COOKIEPATH' ) ) {
		define( 'COOKIEPATH', '/' );
	}
	if ( ! defined( 'COOKIE_DOMAIN' ) ) {
		define( 'COOKIE_DOMAIN', '' );
	}
	if ( ! defined( 'ARRAY_A' ) ) {
		define( 'ARRAY_A', 'ARRAY_A' );
	}

	/** Thrown by every stub that ends the request, so the test can inspect it. */
	final class HandlerExit extends \RuntimeException {
		/** @var array<string, mixed> */
		public array $outcome;

		/** @param array<string, mixed> $outcome */
		public function __construct( array $outcome ) {
			parent::__construct( (string) ( $outcome['kind'] ?? 'exit' ) );
			$this->outcome = $outcome;
		}
	}

	// --- World state ------------------------------------------------------

	$GLOBALS['transients'] = [];
	$GLOBALS['cache']      = [];
	$GLOBALS['posts']      = [];
	$GLOBALS['can']        = false;
	$GLOBALS['blocks_by_content'] = [];

	final class WP_Post {
		public int $ID = 0;
		public string $post_content = '';
		public string $post_status = 'publish';
		public string $post_title = 'Test page';
		public string $post_type = 'page';
		public int $post_parent = 0;
	}

	final class FakeWpdb {
		public string $prefix = 'wp_';
		public string $posts = 'wp_posts';
		public int $insert_id = 0;
		/** @var array<int, array<string, mixed>> */
		public array $rows = [];

		/** @param array<string, mixed> $data */
		public function insert( string $table, array $data, $format = null ) {
			$this->rows[] = $data;
			$this->insert_id = count( $this->rows );
			return 1;
		}
		public function esc_like( string $text ): string {
			return $text;
		}
		public function prepare( string $sql, ...$args ): string {
			return $sql;
		}
		public function get_col( $sql ) {
			// Indexer candidates: every stored post that embeds a form.
			return array_keys( $GLOBALS['posts'] );
		}
		public function get_results( $sql, $output = null ) {
			return [];
		}
		public function get_var( $sql ) {
			return null; // No submissions table: the Indexer skips its stats.
		}
	}
	$GLOBALS['wpdb'] = new FakeWpdb();

	final class WP_Block_Type_Registry {
		public static function get_instance(): self {
			return new self();
		}
		public function get_registered( $name ) {
			return null;
		}
	}

	// --- WordPress stubs --------------------------------------------------

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
	function wp_cache_get( $key, $group = '' ) {
		return $GLOBALS['cache'][ $group . ':' . $key ] ?? false;
	}
	function wp_cache_set( $key, $value, $group = '', $ttl = 0 ) {
		$GLOBALS['cache'][ $group . ':' . $key ] = $value;
		return true;
	}
	function __( $text, $domain = '' ) {
		return $text;
	}
	function esc_html__( $text, $domain = '' ) {
		return $text;
	}
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
	}
	function sanitize_text_field( $text ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) );
	}
	function sanitize_textarea_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
	function sanitize_email( $email ) {
		return trim( (string) $email );
	}
	function is_email( $email ) {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
	function esc_url_raw( $url ) {
		return (string) $url;
	}
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
	function wp_unslash( $value ) {
		return $value;
	}
	function absint( $value ) {
		return abs( (int) $value );
	}
	$GLOBALS['filters'] = [];
	function add_filter( $hook, $callback ) {
		$GLOBALS['filters'][ $hook ][] = $callback;
	}
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['filters'][ $hook ] ?? [] as $callback ) {
			$value = $callback( $value, ...$args );
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
	}
	function current_time( $type, $gmt = false ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
	function current_user_can( $cap, ...$args ) {
		return (bool) $GLOBALS['can'];
	}
	function get_post( $id ) {
		return $GLOBALS['posts'][ (int) $id ] ?? null;
	}
	function parse_blocks( $content ) {
		return $GLOBALS['blocks_by_content'][ $content ] ?? [];
	}
	function get_permalink( $id ) {
		return 'https://example.test/page-' . (int) $id . '/';
	}
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
	function add_query_arg( $args, $url ) {
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
	}
	function wp_validate_redirect( $location, $fallback = '' ) {
		return str_starts_with( (string) $location, 'https://example.test' ) ? $location : $fallback;
	}
	function wp_is_post_revision( $id ) {
		return false;
	}
	function wp_is_post_autosave( $id ) {
		return false;
	}
	function wp_generate_password( $length = 12, $special = true, $extra = false ) {
		return str_repeat( 'a', $length );
	}
	function is_ssl() {
		return true;
	}
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		$given = $_POST[ $query_arg ] ?? '';
		if ( $given === 'nonce-' . $action ) {
			return 1;
		}
		// Core dies here instead of returning false.
		throw new HandlerExit( [ 'kind' => '403' ] );
	}
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return $nonce === 'nonce-' . $action ? 1 : false;
	}
	function wp_die( $message = '', $title = '', $args = [] ) {
		throw new HandlerExit( [ 'kind' => '403' ] );
	}
	function wp_safe_redirect( $location, $status = 302 ) {
		if ( 'https://example.test/' === $location ) {
			throw new HandlerExit( [ 'kind' => 'silent', 'url' => $location ] );
		}
		if ( str_contains( $location, 'flinkform_status=error' ) ) {
			throw new HandlerExit( [ 'kind' => 'soft', 'url' => $location ] );
		}
		if ( str_contains( $location, 'flinkform_status=success' ) ) {
			throw new HandlerExit( [ 'kind' => 'success', 'url' => $location ] );
		}
		throw new HandlerExit( [ 'kind' => 'redirect', 'url' => $location ] );
	}
	function wp_send_json_success( $data = null, $status = null ) {
		throw new HandlerExit( [ 'kind' => 'success', 'json' => $data ] );
	}
	function wp_send_json_error( $data = null, $status = null ) {
		throw new HandlerExit( [ 'kind' => 'json_error', 'json' => $data, 'status' => $status ] );
	}

	require_once __DIR__ . '/../includes/Database/Schema.php';
	require_once __DIR__ . '/../includes/Submissions/Repository.php';
	require_once __DIR__ . '/../includes/Forms/Indexer.php';
	require_once __DIR__ . '/../includes/Forms/Locator.php';
	require_once __DIR__ . '/../includes/Fields/HiddenResolver.php';
	require_once __DIR__ . '/../includes/Conditions/RuleEvaluator.php';
	require_once __DIR__ . '/../includes/Conditions/VisibilityResolver.php';
	require_once __DIR__ . '/../includes/Spam/Challenge.php';
	require_once __DIR__ . '/../includes/Spam/Renderer.php';
	require_once __DIR__ . '/../includes/Spam/RenderMode.php';
	require_once __DIR__ . '/../includes/Spam/Guard.php';
	require_once __DIR__ . '/../includes/Submissions/Handler.php';

	use Flinkform\Spam\Challenge;

	const FORM_ID = 'form-1';
	const PAGE_ID = 10;

	// --- Test plumbing ------------------------------------------------------

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

	/** Put a page holding the test form into the world. */
	function add_form_page( int $id, string $status = 'publish', string $type = 'page' ): void {
		$content = '<!-- wp:flinkform/form {"formId":"' . FORM_ID . '"} --><!-- /wp:flinkform/form --> #' . $id;
		$post = new WP_Post();
		$post->ID = $id;
		$post->post_content = $content;
		$post->post_status  = $status;
		$post->post_type    = $type;
		$GLOBALS['posts'][ $id ] = $post;
		$GLOBALS['blocks_by_content'][ $content ] = [
			[
				'blockName'   => 'flinkform/form',
				'attrs'       => [ 'formId' => FORM_ID ],
				'innerBlocks' => [
					[
						'blockName' => 'flinkform/field-text',
						'attrs'     => [ 'fieldName' => 'name', 'label' => 'Name', 'required' => true ],
					],
					[
						'blockName' => 'flinkform/field-email',
						'attrs'     => [ 'fieldName' => 'email', 'label' => 'E-Mail' ],
					],
				],
			],
		];
	}

	function reset_world(): void {
		$GLOBALS['transients'] = [];
		$GLOBALS['cache']      = [];
		$GLOBALS['posts']      = [];
		$GLOBALS['can']        = false;
		$GLOBALS['blocks_by_content'] = [];
		$GLOBALS['wpdb']       = new FakeWpdb();
		$_POST   = [];
		$_COOKIE = [];
		unset( $_SERVER['HTTP_X_FLINKFORM_FETCH'] );
		add_form_page( PAGE_ID );
	}

	/** A signed timestamp that is $age seconds old. */
	function ts_aged( int $age, string $form_id = FORM_ID ): string {
		$epoch_b64 = rtrim( strtr( base64_encode( (string) ( time() - $age ) ), '+/', '-_' ), '=' );
		$key       = hash( 'sha256', wp_salt( 'auth' ), true );
		return $epoch_b64 . '.' . hash_hmac( 'sha256', 'ts|' . $form_id . '|' . $epoch_b64, $key );
	}

	/** A freshly minted spam token plus the matching math answer. */
	function solved_challenge(): array {
		$minted = Challenge::mint( FORM_ID );
		$token  = $minted['token'];
		$payload = json_decode( base64_decode( strtr( explode( '.', $token )[0], '-_', '+/' ) ), true );
		for ( $sum = 4; $sum <= 18; $sum++ ) {
			if ( hash( 'sha256', $payload['s'] . '|' . $sum ) === $payload['a'] ) {
				return [ $token, (string) $sum ];
			}
		}
		throw new \RuntimeException( 'could not solve test challenge' );
	}

	/**
	 * Build a valid POST. $overrides replaces top-level keys; null removes one.
	 *
	 * @param array<string, mixed> $overrides
	 */
	function post( array $overrides = [] ): array {
		[ $token, $answer ] = solved_challenge();
		$post = [
			'flinkform_form_id'     => FORM_ID,
			'flinkform_post_id'     => (string) PAGE_ID,
			'_flinkform_nonce'      => 'nonce-flinkform_submit_' . FORM_ID,
			'flinkform_ts'          => ts_aged( 30 ),
			'flinkform_hp'          => '',
			'flinkform_spam_token'  => $token,
			'flinkform_spam_answer' => $answer,
			'flinkform_field'       => [
				'name'  => 'Erika Muster',
				'email' => 'erika@example.test',
			],
		];
		foreach ( $overrides as $key => $value ) {
			if ( null === $value ) {
				unset( $post[ $key ] );
			} else {
				$post[ $key ] = $value;
			}
		}
		return $post;
	}

	/** Run handle() with the given POST and return the outcome. */
	function submit( array $post_data, bool $fetch = false ): array {
		$_POST = $post_data;
		if ( $fetch ) {
			$_SERVER['HTTP_X_FLINKFORM_FETCH'] = '1';
		} else {
			unset( $_SERVER['HTTP_X_FLINKFORM_FETCH'] );
		}
		$handler = new \Flinkform\Submissions\Handler(
			new \Flinkform\Forms\Locator(),
			new \Flinkform\Submissions\Repository()
		);
		try {
			$handler->handle();
		} catch ( HandlerExit $exit ) {
			return $exit->outcome;
		}
		return [ 'kind' => 'fell-through' ];
	}

	function rows(): int {
		return count( $GLOBALS['wpdb']->rows );
	}

	function flash_written(): bool {
		foreach ( array_keys( $GLOBALS['transients'] ) as $key ) {
			if ( str_starts_with( (string) $key, 'flinkform_flash_' ) ) {
				return true;
			}
		}
		return false;
	}

	// --- The happy path ---------------------------------------------------

	reset_world();
	$out = submit( post() );
	check( 'valid submission: success', 'success' === $out['kind'], $out['kind'] );
	check( 'valid submission: one row stored', 1 === rows(), (string) rows() );

	// --- Idempotency --------------------------------------------------------

	reset_world();
	$first = post();
	submit( $first );
	$again = submit( $first );
	check( 'double submit: the repeat replays success', 'success' === $again['kind'], $again['kind'] );
	check( 'double submit: still exactly one row', 1 === rows(), (string) rows() );

	// Back button to a restored page (bfcache keeps the hidden inputs), the
	// visitor changes the message and sends again. The same timestamp must
	// not make the server claim success for a message it never stored.
	reset_world();
	$first = post();
	submit( $first );
	$changed = $first;
	$changed['flinkform_field']['name'] = 'Erika Muster (zweite Nachricht)';
	$out = submit( $changed );
	check(
		'changed resend from a restored page is never a fake success',
		! ( 'success' === $out['kind'] && 1 === rows() ),
		$out['kind'] . ', rows ' . rows()
	);
	check( 'changed resend from a restored page is never silent', 'silent' !== $out['kind'], $out['kind'] );

	// --- Minimum fill time --------------------------------------------------

	reset_world();
	$out = submit( post( [ 'flinkform_ts' => ts_aged( 0 ) ] ) );
	check( 'inline, sent within 2 s: never silent', 'silent' !== $out['kind'], $out['kind'] );
	check( 'inline, sent within 2 s: soft, values kept', 'soft' === $out['kind'] && flash_written(), $out['kind'] );
	check( 'inline, sent within 2 s: nothing stored', 0 === rows() );

	reset_world();
	$out = submit( post( [ 'flinkform_ts' => ts_aged( 1 ), 'flinkform_challenge' => 'deferred' ] ) );
	check( 'deferred, sent 1 s after arming: never silent', 'silent' !== $out['kind'], $out['kind'] );
	check( 'deferred, sent 1 s after arming: soft', 'soft' === $out['kind'], $out['kind'] );

	reset_world();
	$out = submit( post( [ 'flinkform_ts' => ts_aged( 1 ), 'flinkform_challenge' => 'deferred' ] ), true );
	check(
		'fetch, sent too fast: JSON with a code the client can recover from',
		'json_error' === $out['kind'] && 'too_fast' === ( $out['json']['code'] ?? '' ),
		$out['kind'] . ' ' . ( $out['json']['code'] ?? '' )
	);

	reset_world();
	$out = submit( post( [ 'flinkform_ts' => ts_aged( 2 ) ] ) );
	check( 'exactly 2 s old is accepted', 'success' === $out['kind'], $out['kind'] );

	// A timestamp this server never signed is a bot and stays silent.
	reset_world();
	$out = submit( post( [ 'flinkform_ts' => base64_encode( (string) ( time() - 60 ) ) . '.forged' ] ) );
	check( 'forged timestamp: silent', 'silent' === $out['kind'], $out['kind'] );
	check( 'forged timestamp: no flash written', ! flash_written() );

	reset_world();
	$out = submit( post( [ 'flinkform_ts' => ts_aged( 0, 'other-form' ) ] ) );
	check( 'timestamp signed for another form: silent', 'silent' === $out['kind'], $out['kind'] );

	// --- Honeypot -----------------------------------------------------------

	reset_world();
	$out = submit( post( [ 'flinkform_hp' => 'http://spam.test' ] ) );
	check( 'honeypot: fake success', 'success' === $out['kind'], $out['kind'] );
	check( 'honeypot: nothing stored', 0 === rows() );

	reset_world();
	$out = submit( post( [ 'flinkform_hp' => 'x', 'flinkform_ts' => ts_aged( 0 ) ] ) );
	check( 'honeypot beats the too-fast soft path (no flash for bots)', ! flash_written(), $out['kind'] );

	// --- Deferred without challenge ----------------------------------------

	reset_world();
	$out = submit( post( [ 'flinkform_challenge' => 'deferred', '_flinkform_nonce' => '', 'flinkform_ts' => '' ] ) );
	check( 'deferred without challenge: soft', 'soft' === $out['kind'], $out['kind'] );
	check( 'deferred without challenge: values flashed', flash_written() );

	reset_world();
	$out = submit( post( [ 'flinkform_challenge' => 'deferred', '_flinkform_nonce' => '', 'flinkform_ts' => '', 'flinkform_hp' => 'x' ] ) );
	check( 'deferred without challenge + honeypot: no flash', ! flash_written(), $out['kind'] );

	reset_world();
	$out = submit( post( [ 'flinkform_challenge' => 'deferred', 'flinkform_spam_token' => '', 'flinkform_spam_answer' => '' ] ) );
	check( 'deferred, armed but no spam token: soft', 'soft' === $out['kind'], $out['kind'] );

	// --- Nonce --------------------------------------------------------------

	reset_world();
	$out = submit( post( [ '_flinkform_nonce' => 'stale' ] ) );
	check( 'inline render with a bad nonce: 403', '403' === $out['kind'], $out['kind'] );

	// A deferred page carries a nonce the browser fetched for itself. If it
	// does not verify (a logged-in visitor served a cached page, where the
	// fetch ran as a guest), the visitor still must not lose the message.
	reset_world();
	$out = submit( post( [ '_flinkform_nonce' => 'stale', 'flinkform_challenge' => 'deferred' ] ) );
	check( 'deferred render with a bad nonce: soft, not 403', 'soft' === $out['kind'], $out['kind'] );
	check( 'deferred render with a bad nonce: nothing stored', 0 === rows() );

	// --- Spam token verdicts -----------------------------------------------

	reset_world();
	$out = submit( post( [ 'flinkform_spam_token' => 'garbage.token' ] ) );
	check( 'forged spam token: silent', 'silent' === $out['kind'], $out['kind'] );

	reset_world();
	$out = submit( post( [ 'flinkform_spam_answer' => '99' ] ) );
	check( 'wrong math answer: soft', 'soft' === $out['kind'], $out['kind'] );

	// --- Where the form lives ----------------------------------------------

	reset_world();
	$GLOBALS['posts'] = [];
	add_form_page( PAGE_ID, 'draft' );
	$out = submit( post() );
	check( 'form on a draft, anonymous visitor: not accepted', 0 === rows(), $out['kind'] );

	reset_world();
	$GLOBALS['posts'] = [];
	add_form_page( PAGE_ID, 'draft' );
	$GLOBALS['can'] = true;
	$out = submit( post() );
	check( 'form on a draft, previewed by its editor: accepted', 'success' === $out['kind'] && 1 === rows(), $out['kind'] );

	reset_world();
	$GLOBALS['posts'] = [];
	add_form_page( PAGE_ID, 'private' );
	$out = submit( post() );
	check( 'form on a private page, anonymous visitor: not accepted', 0 === rows(), $out['kind'] );

	// The form sits in a footer template part (post 99), the visitor is on
	// page 10 which does not contain it. The Indexer fallback must find it.
	reset_world();
	$GLOBALS['posts'] = [];
	add_form_page( 99, 'publish', 'wp_template_part' );
	$page = new WP_Post();
	$page->ID = PAGE_ID;
	$page->post_content = 'plain page';
	$GLOBALS['posts'][ PAGE_ID ] = $page;
	$out = submit( post() );
	check( 'form in a template part, submitted from another page: accepted', 'success' === $out['kind'] && 1 === rows(), $out['kind'] );

	// The form was moved from page 50 into a template part (99), but the
	// cached index still points at page 50 — an import or a copied database
	// bypassed the hooks that invalidate it. The Locator must rebuild once
	// and find the form rather than drop the submission.
	reset_world();
	$GLOBALS['posts'] = [];
	add_form_page( 99, 'publish', 'wp_template_part' );
	$page = new WP_Post();
	$page->ID = PAGE_ID;
	$page->post_content = 'plain page';
	$GLOBALS['posts'][ PAGE_ID ] = $page;
	$GLOBALS['transients']['flinkform_forms_index'] = [
		[
			'form_id' => FORM_ID,
			'sources' => [ [ 'post_id' => 50 ] ],
		],
	];
	$out = submit( post() );
	check( 'stale form index: rebuilt once, submission accepted', 'success' === $out['kind'] && 1 === rows(), $out['kind'] );

	// Unknown form ids must not turn into one full index rebuild each.
	reset_world();
	$GLOBALS['transients']['flinkform_forms_index'] = [];
	$out1 = submit( post( [ 'flinkform_form_id' => 'nope-1', 'flinkform_ts' => ts_aged( 30, 'nope-1' ), '_flinkform_nonce' => 'nonce-flinkform_submit_nope-1' ] ) );
	$rebuilt_first = ! isset( $GLOBALS['transients']['flinkform_forms_index'] ) || [] !== $GLOBALS['transients']['flinkform_forms_index'];
	$GLOBALS['transients']['flinkform_forms_index'] = [];
	$out2 = submit( post( [ 'flinkform_form_id' => 'nope-2', 'flinkform_ts' => ts_aged( 30, 'nope-2' ), '_flinkform_nonce' => 'nonce-flinkform_submit_nope-2' ] ) );
	check( 'unknown form: silent', 'silent' === $out1['kind'] && 'silent' === $out2['kind'], $out1['kind'] . '/' . $out2['kind'] );
	check( 'unknown form: first miss rebuilds the index', $rebuilt_first );
	check(
		'unknown form: second miss inside a minute does not rebuild again',
		[] === ( $GLOBALS['transients']['flinkform_forms_index'] ?? null )
	);

	// --- Values derived before conditional logic (1.14.3) -----------------
	// A server-computed field (Pro's calculation field) is filled in through
	// flinkform_values_before_visibility. The submit condition must see it:
	// before this seam existed it saw '' and refused every submission.
	reset_world();
	$content = $GLOBALS['posts'][ PAGE_ID ]->post_content;
	$GLOBALS['blocks_by_content'][ $content ][0]['attrs']['submitCondition'] = [
		'enabled' => true,
		'logic'   => 'all',
		'rules'   => [ [ 'field' => 'total', 'operator' => 'greater_than', 'value' => '0' ] ],
	];
	add_filter( 'flinkform_values_before_visibility', static function ( $clean ) {
		$clean['total'] = '49.00';
		return $clean;
	} );
	$out = submit( post() );
	check( 'a value derived before visibility satisfies the submit condition', 'success' === $out['kind'] && 1 === rows(), $out['kind'] );
	$GLOBALS['filters'] = [];

	reset_world();
	$GLOBALS['blocks_by_content'][ $content ][0]['attrs']['submitCondition'] = [
		'enabled' => true,
		'logic'   => 'all',
		'rules'   => [ [ 'field' => 'total', 'operator' => 'greater_than', 'value' => '0' ] ],
	];
	$out = submit( post() );
	check( 'without the derived value the same condition still blocks (soft, values kept)', 'soft' === $out['kind'] && 0 === rows(), $out['kind'] );

	// --- Summary -------------------------------------------------------------

	echo "\n";
	if ( $failed > 0 ) {
		echo "$failed FAILED, $passed passed.\n";
		exit( 1 );
	}
	echo "All $passed tests passed.\n";
	exit( 0 );
}
