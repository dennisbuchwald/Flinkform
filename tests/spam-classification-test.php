#!/usr/bin/env php
<?php
/**
 * Standalone unit tests for the spam-challenge verdict classification.
 *
 * Challenge::assess() replaced the old boolean verify() core because the
 * Handler must treat a forged token (attack → silent reject) differently
 * from a token this server provably minted that merely expired, was
 * already consumed, or carries a wrong human answer (real visitor →
 * keep their input, ask them to resend). Collapsing those cases into
 * `false` is exactly what silently discarded real submissions (F-0).
 *
 * The high-risk assertions:
 *
 *   - Anything NOT provably ours (bad HMAC, wrong form, wrong version,
 *     structural garbage, no solution attempt) must classify as
 *     STATUS_INVALID — that verdict is the only one that still silent-
 *     rejects, so a misclassification here either punishes a human or
 *     hands a bot the soft path.
 *   - An HMAC-valid but expired/replayed token must NOT be invalid.
 *   - verify() keeps its boolean contract (true only for STATUS_OK) so
 *     existing callers behave exactly as before.
 *
 * Tokens are crafted by replicating mint()'s signing with the stubbed
 * wp_salt(), so expired/foreign/tampered variants can be built at will.
 *
 * Run:  php tests/spam-classification-test.php
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

	$GLOBALS['transients'] = [];

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
	require_once __DIR__ . '/../includes/Spam/Challenge.php';

	use Flinkform\Spam\Challenge;

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

	// --- Token factory (replicates mint()'s signing) ----------------------

	function b64url( string $bin ): string {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	function hmac_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	/**
	 * Build a signed token from an explicit payload. Difficulty defaults
	 * low so the test's PoW brute force stays instant.
	 *
	 * @param array<string, mixed> $overrides
	 * @return array{token: string, salt: string, answer: int}
	 */
	function craft( array $overrides = [] ): array {
		$salt   = b64url( random_bytes( 16 ) );
		$answer = 7;

		$payload = array_merge(
			[
				'v' => 1,
				'f' => 'form-uuid-1',
				's' => $salt,
				'd' => 4,
				'a' => hash( 'sha256', $salt . '|' . $answer ),
				'e' => time() + 1800,
				'n' => b64url( random_bytes( 12 ) ),
			],
			$overrides
		);
		// An override may replace the salt — keep math hash consistent.
		$payload['a'] = hash( 'sha256', $payload['s'] . '|' . $answer );

		$encoded = b64url( (string) json_encode( $payload ) );
		$token   = $encoded . '.' . hash_hmac( 'sha256', $encoded, hmac_key() );

		return [
			'token'  => $token,
			'salt'   => (string) $payload['s'],
			'answer' => $answer,
			'd'      => (int) $payload['d'],
		];
	}

	function solve_pow( string $salt, int $difficulty ): string {
		for ( $n = 0; ; $n++ ) {
			$hash = hash( 'sha256', $salt . '|' . $n );
			$bits = 0;
			for ( $i = 0; $i < strlen( $hash ) && $bits < $difficulty; $i++ ) {
				$v = hexdec( $hash[ $i ] );
				if ( 0 === $v ) {
					$bits += 4;
					continue;
				}
				foreach ( [ 8, 4, 2, 1 ] as $bit ) {
					if ( $v & $bit ) {
						break 2;
					}
					++$bits;
				}
				break;
			}
			if ( $bits >= $difficulty ) {
				return (string) $n;
			}
		}
	}

	$form = 'form-uuid-1';

	// --- Happy paths ------------------------------------------------------

	$t = craft();
	check(
		'fresh token + valid PoW → ok',
		Challenge::STATUS_OK === Challenge::assess( $t['token'], $form, [ 'pow_solution' => solve_pow( $t['salt'], $t['d'] ) ], false )
	);

	$t = craft();
	check(
		'fresh token + correct math answer → ok',
		Challenge::STATUS_OK === Challenge::assess( $t['token'], $form, [ 'math_answer' => (string) $t['answer'] ], false )
	);

	// A real mint() round-trip: solve the actual difficulty-13 challenge.
	$minted = Challenge::mint( $form );
	$sol    = solve_pow( (string) $minted['pow']['salt'], (int) $minted['pow']['difficulty'] );
	check(
		'minted token + solved PoW → ok',
		Challenge::STATUS_OK === Challenge::assess( (string) $minted['token'], $form, [ 'pow_solution' => $sol ], false )
	);

	// --- Burn / replay ----------------------------------------------------

	$t   = craft();
	$sol = solve_pow( $t['salt'], $t['d'] );
	check( 'burn=false leaves token reusable', Challenge::STATUS_OK === Challenge::assess( $t['token'], $form, [ 'pow_solution' => $sol ], false ) );
	check( 'burn=false second check still ok', Challenge::STATUS_OK === Challenge::assess( $t['token'], $form, [ 'pow_solution' => $sol ], false ) );

	check( 'burn=true consumes the token', Challenge::STATUS_OK === Challenge::assess( $t['token'], $form, [ 'pow_solution' => $sol ], true ) );
	check( 'burnt token → replayed', Challenge::STATUS_REPLAYED === Challenge::assess( $t['token'], $form, [ 'pow_solution' => $sol ], false ) );

	$t   = craft();
	$sol = solve_pow( $t['salt'], $t['d'] );
	Challenge::burn( $t['token'] );
	check( 'Challenge::burn() token → replayed', Challenge::STATUS_REPLAYED === Challenge::assess( $t['token'], $form, [ 'pow_solution' => $sol ], false ) );

	// --- Expiry -----------------------------------------------------------

	$t   = craft( [ 'e' => time() - 60 ] );
	$sol = solve_pow( $t['salt'], $t['d'] );
	check( 'expired token + valid solution → expired', Challenge::STATUS_EXPIRED === Challenge::assess( $t['token'], $form, [ 'pow_solution' => $sol ], false ) );
	check( 'expired token + wrong answer → expired (expiry wins)', Challenge::STATUS_EXPIRED === Challenge::assess( $t['token'], $form, [ 'math_answer' => '99' ], false ) );
	check( 'expired token + no solution attempt → invalid (bot signature)', Challenge::STATUS_INVALID === Challenge::assess( $t['token'], $form, [], false ) );

	// --- Attack signatures must stay invalid ------------------------------

	$t        = craft();
	$sol      = solve_pow( $t['salt'], $t['d'] );
	$tampered = substr( $t['token'], 0, 10 ) . ( 'A' === $t['token'][10] ? 'B' : 'A' ) . substr( $t['token'], 11 );
	check( 'tampered payload → invalid', Challenge::STATUS_INVALID === Challenge::assess( $tampered, $form, [ 'pow_solution' => $sol ], false ) );

	$t   = craft( [ 'f' => 'some-other-form' ] );
	$sol = solve_pow( $t['salt'], $t['d'] );
	check( 'token bound to another form → invalid', Challenge::STATUS_INVALID === Challenge::assess( $t['token'], $form, [ 'pow_solution' => $sol ], false ) );

	$t   = craft( [ 'v' => 99 ] );
	$sol = solve_pow( $t['salt'], $t['d'] );
	check( 'wrong token version → invalid', Challenge::STATUS_INVALID === Challenge::assess( $t['token'], $form, [ 'pow_solution' => $sol ], false ) );

	check( 'structurally broken token → invalid', Challenge::STATUS_INVALID === Challenge::assess( 'no-dot-here', $form, [ 'pow_solution' => '1' ], false ) );
	check( 'garbage with dot → invalid', Challenge::STATUS_INVALID === Challenge::assess( '$$$.###', $form, [ 'pow_solution' => '1' ], false ) );

	$t = craft();
	check( 'fresh token but no solution attempt → invalid', Challenge::STATUS_INVALID === Challenge::assess( $t['token'], $form, [], false ) );
	check( 'whitespace-only math answer → invalid', Challenge::STATUS_INVALID === Challenge::assess( $t['token'], $form, [ 'math_answer' => '  ' ], false ) );

	// --- Wrong-but-attempted solutions → failed (human typo) --------------

	$t = craft();
	check( 'fresh token + wrong math answer → failed', Challenge::STATUS_FAILED === Challenge::assess( $t['token'], $form, [ 'math_answer' => '99' ], false ) );
	check( 'fresh token + wrong PoW solution → failed', Challenge::STATUS_FAILED === Challenge::assess( $t['token'], $form, [ 'pow_solution' => '99999999' ], false ) );

	// A failed attempt must not burn the token — the retry with the right
	// answer has to succeed.
	check( 'retry after failed attempt → ok', Challenge::STATUS_OK === Challenge::assess( $t['token'], $form, [ 'math_answer' => (string) $t['answer'] ], false ) );

	// --- verify() keeps its boolean contract ------------------------------

	$t   = craft();
	$sol = solve_pow( $t['salt'], $t['d'] );
	check( 'verify(): ok → true', true === Challenge::verify( $t['token'], $form, [ 'pow_solution' => $sol ], false ) );

	$t = craft( [ 'e' => time() - 60 ] );
	check( 'verify(): expired → false', false === Challenge::verify( $t['token'], $form, [ 'math_answer' => (string) $t['answer'] ], false ) );
	check( 'verify(): forged → false', false === Challenge::verify( 'x.y', $form, [ 'pow_solution' => '1' ], false ) );

	// --- Summary ----------------------------------------------------------

	if ( $failed > 0 ) {
		echo "\n$failed of " . ( $passed + $failed ) . " tests failed.\n";
		exit( 1 );
	}
	echo "All $passed tests passed.\n";
}
