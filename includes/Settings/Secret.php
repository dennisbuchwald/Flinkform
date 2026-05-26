<?php
/**
 * Symmetric encryption helper for plugin-managed secrets.
 *
 * Used by the SMTP module (Phase A) and reserved for any future module
 * that needs to persist sensitive credentials in wp_options — API keys,
 * OAuth refresh tokens, CAPTCHA secrets, etc. The goal is "a wp_options
 * dump must never expose a plaintext password".
 *
 * Cipher choice: AES-256-CBC with a random 16-byte IV per encrypt.
 * CBC (not GCM) because we need OpenSSL on every supported PHP target
 * (8.1+) and the AEAD upgrade is reserved for v2 of the cipher format
 * (see VERSION constant below). Tampering protection at v1 relies on
 * the fact that a flipped cipher byte produces garbled plaintext that
 * fails subsequent SMTP auth — acceptable for our threat model (the
 * attacker we're defending against is a DB-dump leak, not an active
 * MITM with write access to the options table).
 *
 * Key derivation: SHA-256 over wp_salt('auth'). The auth salt is
 * site-specific and lives in wp-config.php; rotating it invalidates
 * every PerForm-encrypted secret on the site. That's intentional —
 * the operator just re-enters credentials, and we never have to
 * deal with key-rotation migrations inside the plugin.
 *
 * Cipher output format: "perform_enc_v1:<base64(iv || ciphertext)>".
 * The version prefix is mandatory so a future cipher upgrade (e.g.
 * AES-256-GCM as v2) can co-exist with v1 ciphers on the same install
 * without forcing a migration script.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Static encrypt / decrypt helpers.
 *
 * Static-only because there is no instance state and a single shared
 * cipher per WordPress install. Callers don't need to wire anything,
 * which keeps the SMTP page and any future settings page trivial:
 *
 *   $cipher    = Secret::encrypt( $plaintext );
 *   $plaintext = Secret::decrypt( $cipher );
 */
final class Secret {

	/**
	 * OpenSSL cipher identifier.
	 *
	 * @var string
	 */
	private const CIPHER = 'aes-256-cbc';

	/**
	 * IV length in bytes for AES-256-CBC.
	 *
	 * @var int
	 */
	private const IV_LEN = 16;

	/**
	 * Cipher-format version prefix. Bump (to perform_enc_v2 etc.)
	 * if we ever switch the cipher or the encoding — decrypt() will
	 * still recognise v1 entries because each version-branch can
	 * keep its own dispatch arm.
	 *
	 * @var string
	 */
	private const VERSION = 'perform_enc_v1';

	/**
	 * Encrypt a plaintext string for persistence.
	 *
	 * Returns '' for empty input so callers can treat "no value set"
	 * as the absence of a cipher without a wrapping null-check at
	 * every callsite. Returns '' on OpenSSL failure too — we'd
	 * rather store nothing than store the plaintext by accident.
	 *
	 * @param string $plaintext
	 * @return string Cipher string ready to persist (or '').
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// PHP shipped without the OpenSSL extension is exotic but
			// possible on hardened shared hosts. Fail closed.
			return '';
		}

		$key = self::derive_key();
		$iv  = openssl_random_pseudo_bytes( self::IV_LEN );

		$cipher = openssl_encrypt(
			$plaintext,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( false === $cipher ) {
			return '';
		}

		return self::VERSION . ':' . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a cipher previously produced by encrypt().
	 *
	 * Returns '' on every error path (unknown version, malformed
	 * base64, truncated payload, OpenSSL failure, mismatched key).
	 * The caller can then choose to fall back to "no credential
	 * configured" instead of breaking the page render.
	 *
	 * @param string $cipher_string
	 * @return string Plaintext, or '' if the cipher is unrecognised
	 *                / tampered / produced under a different auth salt.
	 */
	public static function decrypt( string $cipher_string ): string {
		if ( '' === $cipher_string ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$parts = explode( ':', $cipher_string, 2 );
		if ( 2 !== count( $parts ) || self::VERSION !== $parts[0] ) {
			return '';
		}

		$raw = base64_decode( $parts[1], true );
		if ( false === $raw || strlen( $raw ) <= self::IV_LEN ) {
			return '';
		}

		$iv         = substr( $raw, 0, self::IV_LEN );
		$ciphertext = substr( $raw, self::IV_LEN );
		$key        = self::derive_key();

		$plain = openssl_decrypt(
			$ciphertext,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);

		return false === $plain ? '' : $plain;
	}

	/**
	 * Cheap "is this value already a PerForm cipher?" check.
	 *
	 * Used by save handlers so a re-submitted settings form with an
	 * unchanged password field does not double-encrypt the existing
	 * cipher. Convention: the password field renders empty (we never
	 * round-trip the plaintext through the browser); the save handler
	 * detects "empty submission" and keeps the existing cipher.
	 *
	 * @param string $value
	 * @return bool
	 */
	public static function is_encrypted( string $value ): bool {
		return 0 === strpos( $value, self::VERSION . ':' );
	}

	/**
	 * Derive a 32-byte AES key from the site-specific auth salt.
	 *
	 * SHA-256 in raw-output mode produces exactly 32 bytes — the
	 * key length AES-256-CBC requires.
	 *
	 * @return string Raw 32-byte key.
	 */
	private static function derive_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}
}
