<?php
/**
 * Spam-protection challenge — cryptographic core.
 *
 * Two strategies share the same HMAC-signed token format so the
 * Submissions\Handler verification path can decide what kind of
 * solution it expects without keeping state of its own:
 *
 *   ProofOfWork (PoW) — Browser computes sha256(salt || n) until the
 *                       hash starts with `difficulty` leading-zero
 *                       bits. The accepted solution `n` is submitted
 *                       in a hidden field and verified server-side.
 *
 *   Math          — Server picks two small integers and asks the
 *                       visitor to type their sum. Only used when the
 *                       browser couldn't solve the PoW (no JS, no
 *                       crypto.subtle, ancient browser, etc.).
 *
 * Token shape (base64-encoded JSON, then signed):
 *   {     "v":   1,                       // format version
 *     "f":   "<form-uuid>",           // form identity (replay-domain scope)
 *     "s":   "<base64 salt>",         // 16 bytes, used by both strategies
 *     "d":   <difficulty bits>,       // PoW only, e.g. 18
 *     "a":   <int> | "<sha256 hash>", // Math only — expected answer hash
 *     "e":   <unix-ts expires>,       // 5-minute TTL
 *     "n":   "<request nonce>",       // single-use protection key
 *   }
 *
 * Why "request nonce" + transient on verify
 * -----------------------------------------
 * The token alone is a *witness* (a valid HMAC over a payload) — it
 * doesn't prove it hasn't been used before. The `n` field is a per-
 * issue random identifier; on successful verify we set a transient
 * `perffo_spam_used_<n>` for the remaining token TTL. A replay of
 * the same token within the TTL window finds the transient and is
 * rejected. After the TTL expires the transient self-cleans, which
 * is fine because the underlying token is also expired by then.
 *
 * HMAC key derivation matches Settings\Secret: SHA-256 over
 * wp_salt('auth') in raw-output mode. We deliberately use the auth
 * salt, NOT the nonce salt: nonce salt rotates on user-session
 * lifetime in some environments, auth salt is operator-managed and
 * stable across requests for the same install. Rotating auth salts
 * invalidates outstanding challenges (operator just re-renders the
 * form) which is acceptable for a 5-minute TTL feature.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace PerForm\Spam;

/**
 * Token mint + verify + replay-guard for built-in spam challenges.
 */
final class Challenge {

	/**
	 * Token format version. Bump on schema changes so old in-flight
	 * tokens can be cleanly rejected by the new verifier.
	 *
	 * @var int
	 */
	private const VERSION = 1;

	/**
	 * Token lifetime in seconds. 5 minutes is long enough to cover
	 * users that read the form thoroughly, short enough to keep the
	 * replay-window (and the transient TTL) bounded.
	 *
	 * @var int
	 */
	private const TTL_SECONDS = 300;

	/**
	 * PoW difficulty (in leading-zero bits the sha256 must satisfy).
	 * 18 bits = 1 in 262,144 hashes — about 50-500ms on modern CPUs,
	 * up to ~2s on low-end mobile. Spambot economics break above
	 * 16 bits (the per-submission cost exceeds the per-submission
	 * revenue of click-spam farms).
	 *
	 * @var int
	 */
	public const POW_DIFFICULTY = 18;

	/**
	 * Salt length in bytes. 16 bytes = 128 bits of randomness —
	 * trivially out of reach for offline brute-force given the TTL.
	 *
	 * @var int
	 */
	private const SALT_BYTES = 16;

	/**
	 * Generate a fresh challenge token for a given form.
	 *
	 * Returns a JSON-ready array that the Renderer encodes into the
	 * form markup. Two siblings are returned so the renderer can
	 * print both at once:
	 *
	 *   - 'pow_payload'    — token shape for the JS solver
	 *   - 'math_question'  — already-formatted human-readable string
	 *
	 * The math fallback's expected answer is included in the same
	 * token (field `a`) as a sha256 hash so the visible markup
	 * doesn't leak "the answer is 8". Verify side just hashes the
	 * submitted answer and compares.
	 *
	 * @param string $form_id Form UUID the token is bound to.
	 * @return array{token: string, pow: array<string, mixed>, math: array{question: string}}
	 */
	public static function mint( string $form_id ): array {
		$salt_raw  = random_bytes( self::SALT_BYTES );
		$salt_b64  = self::base64_url_encode( $salt_raw );
		$nonce_raw = random_bytes( 12 );
		$nonce_b64 = self::base64_url_encode( $nonce_raw );

		// Math fallback — pick two integers in [2, 9] so the sum is
		// in [4, 18]. Small enough to be trivial for a human, large
		// enough that a brute-force enumeration server-side would
		// trip the single-use guard anyway.
		$math_a    = random_int( 2, 9 );
		$math_b    = random_int( 2, 9 );
		$math_sum  = $math_a + $math_b;
		$math_hash = hash( 'sha256', $salt_b64 . '|' . $math_sum );

		$payload = [
			'v' => self::VERSION,
			'f' => $form_id,
			's' => $salt_b64,
			'd' => self::POW_DIFFICULTY,
			'a' => $math_hash,
			'e' => time() + self::TTL_SECONDS,
			'n' => $nonce_b64,
		];

		$encoded = self::base64_url_encode( (string) wp_json_encode( $payload ) );
		$hmac    = hash_hmac( 'sha256', $encoded, self::derive_key() );
		$token   = $encoded . '.' . $hmac;

		return [
			'token' => $token,
			'pow'   => [
				'salt'       => $salt_b64,
				'difficulty' => self::POW_DIFFICULTY,
			],
			'math'  => [
				'question' => sprintf(
					/* translators: 1: first integer 2-9, 2: second integer 2-9 */
					__( 'What is %1$d + %2$d?', 'perform-forms' ),
					$math_a,
					$math_b
				),
			],
		];
	}

	/**
	 * Verify a submitted token + solution pair.
	 *
	 * Order of checks (each independent + cheap):
	 *   1. Token structurally valid (two dot-separated parts).
	 *   2. HMAC matches (constant-time compare).
	 *   3. Payload decodes to expected shape.
	 *   4. Version matches.
	 *   5. Form ID matches the submitted form.
	 *   6. Not expired.
	 *   7. Not already used (transient replay-guard).
	 *   8. Solution actually solves the puzzle.
	 *
	 * On success the per-token transient is set so a second
	 * verify with the same token within the TTL fails at step 7.
	 *
	 * @param string $token_string
	 * @param string $form_id
	 * @param array{pow_solution?: string, math_answer?: string} $submitted
	 * @return bool
	 */
	public static function verify( string $token_string, string $form_id, array $submitted ): bool {
		$parts = explode( '.', $token_string, 2 );
		if ( 2 !== count( $parts ) ) {
			return false;
		}
		[ $encoded, $hmac ] = $parts;

		$expected_hmac = hash_hmac( 'sha256', $encoded, self::derive_key() );
		if ( ! hash_equals( $expected_hmac, $hmac ) ) {
			return false;
		}

		$decoded = self::base64_url_decode( $encoded );
		if ( '' === $decoded ) {
			return false;
		}
		$payload = json_decode( $decoded, true );
		if ( ! is_array( $payload ) ) {
			return false;
		}

		if ( (int) ( $payload['v'] ?? 0 ) !== self::VERSION ) {
			return false;
		}
		if ( (string) ( $payload['f'] ?? '' ) !== $form_id ) {
			return false;
		}
		if ( (int) ( $payload['e'] ?? 0 ) < time() ) {
			return false;
		}

		// Replay-guard: a successful verify burns the token. If
		// the transient is already set, this is a replay attempt.
		$used_key = 'perffo_spam_used_' . md5( (string) ( $payload['n'] ?? '' ) );
		if ( false !== get_transient( $used_key ) ) {
			return false;
		}

		// Solution check — accept EITHER a valid PoW solution OR
		// the math answer. The frontend picks whichever it could
		// produce; servers don't care which arrived as long as one
		// of them satisfies its branch.
		$pow_solution = isset( $submitted['pow_solution'] ) ? (string) $submitted['pow_solution'] : '';
		$math_answer  = isset( $submitted['math_answer'] ) ? (string) $submitted['math_answer'] : '';

		$pow_ok  = '' !== $pow_solution && self::verify_pow(
			(string) ( $payload['s'] ?? '' ),
			(int) ( $payload['d'] ?? self::POW_DIFFICULTY ),
			$pow_solution
		);
		$math_ok = '' !== $math_answer && self::verify_math(
			(string) ( $payload['s'] ?? '' ),
			(string) ( $payload['a'] ?? '' ),
			$math_answer
		);

		if ( ! $pow_ok && ! $math_ok ) {
			return false;
		}

		// Burn the token for the remainder of its TTL.
		$remaining = max( 1, (int) ( $payload['e'] ?? 0 ) - time() );
		set_transient( $used_key, 1, $remaining );

		return true;
	}

	/**
	 * Server-side PoW verification — recompute the hash and check
	 * the leading-zero bits.
	 *
	 * @param string $salt_b64    The salt sent to the browser.
	 * @param int    $difficulty  Bits of leading zeros required.
	 * @param string $solution    The integer the browser produced.
	 * @return bool
	 */
	private static function verify_pow( string $salt_b64, int $difficulty, string $solution ): bool {
		if ( '' === $salt_b64 || $difficulty < 1 ) {
			return false;
		}

		// Defensive: a malicious "n" of length 1MB would slow the
		// server. Real solutions for difficulty=18 are < 8 digits.
		if ( strlen( $solution ) > 20 ) {
			return false;
		}
		if ( ! preg_match( '/^\d+$/', $solution ) ) {
			return false;
		}

		$hash = hash( 'sha256', $salt_b64 . '|' . $solution );

		// Check leading $difficulty zero bits. Each hex character
		// covers 4 bits; we check whole hex characters as a fast
		// outer loop then the partial byte bit-by-bit.
		$full_hex   = intdiv( $difficulty, 4 );
		$extra_bits = $difficulty % 4;

		for ( $i = 0; $i < $full_hex; $i++ ) {
			if ( '0' !== $hash[ $i ] ) {
				return false;
			}
		}

		if ( 0 === $extra_bits ) {
			return true;
		}

		$next_nibble = hexdec( $hash[ $full_hex ] );
		$mask        = ( 0x0F << ( 4 - $extra_bits ) ) & 0x0F;
		return 0 === ( $next_nibble & $mask );
	}

	/**
	 * Math-fallback verification. Submitted answer is hashed with
	 * the same salt-prefixed pattern as mint() so the comparison
	 * is salt-bound (can't replay a "the answer is 8" hash from
	 * a previous challenge).
	 *
	 * @param string $salt_b64
	 * @param string $expected_hash
	 * @param string $answer
	 * @return bool
	 */
	private static function verify_math( string $salt_b64, string $expected_hash, string $answer ): bool {
		$answer = trim( $answer );
		if ( '' === $answer || strlen( $answer ) > 4 ) {
			return false;
		}
		if ( ! preg_match( '/^\d+$/', $answer ) ) {
			return false;
		}
		$candidate = hash( 'sha256', $salt_b64 . '|' . $answer );
		return hash_equals( $expected_hash, $candidate );
	}

	/**
	 * 32-byte HMAC key derived from the site-specific auth salt.
	 *
	 * Matches the derivation in Settings\Secret so any operator
	 * documentation about "rotate AUTH_KEY to invalidate stored
	 * credentials" also implicitly covers in-flight challenges.
	 *
	 * @return string
	 */
	private static function derive_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	/**
	 * URL-safe base64 (no padding, +/ → -_). Lets the token survive
	 * a query-string round-trip without escaping artefacts.
	 *
	 * @param string $bin
	 * @return string
	 */
	private static function base64_url_encode( string $bin ): string {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	/**
	 * Inverse of base64_url_encode.
	 *
	 * @param string $b64
	 * @return string Decoded bytes, or '' on malformed input.
	 */
	private static function base64_url_decode( string $b64 ): string {
		$decoded = base64_decode( strtr( $b64, '-_', '+/' ), true );
		return false === $decoded ? '' : $decoded;
	}
}
