<?php
/**
 * Remembers how the last notification mails went, for Site Health and the
 * admin (1.15.0).
 *
 * Honest about what it can know: wp_mail() returning true means the mail
 * was handed to the server (or an SMTP plugin), not that it arrived. What
 * this catches is the common silent failure: a host without a working
 * mail setup, where every notification is lost and nobody notices until a
 * customer calls.
 *
 * One small non-autoloaded option, no table, no personal data (no
 * addresses, only counts, times and the last error text).
 *
 * @package Flinkform
 * @since 1.15.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Rolling record of notification outcomes.
 */
final class MailHealth {

	public const OPTION = 'flinkform_mail_health';

	/**
	 * How many recent outcomes are kept.
	 */
	private const WINDOW = 20;

	/**
	 * Record one wp_mail() outcome.
	 *
	 * @param bool   $ok    What wp_mail() returned.
	 * @param string $error Error text from wp_mail_failed, if any.
	 * @return void
	 */
	public static function record( bool $ok, string $error = '' ): void {
		$state = self::get();
		$now   = time();

		$state['recent'][] = $ok ? 1 : 0;
		$state['recent']   = array_slice( $state['recent'], -self::WINDOW );
		if ( $ok ) {
			$state['last_ok'] = $now;
		} else {
			$state['last_failed'] = $now;
			$state['last_error']  = function_exists( 'mb_substr' ) ? mb_substr( $error, 0, 300 ) : substr( $error, 0, 300 );
		}

		update_option( self::OPTION, $state, false );
	}

	/**
	 * @return array{recent: array<int, int>, last_ok: int, last_failed: int, last_error: string}
	 */
	public static function get(): array {
		$raw = get_option( self::OPTION, [] );
		$raw = is_array( $raw ) ? $raw : [];
		return [
			'recent'      => isset( $raw['recent'] ) && is_array( $raw['recent'] ) ? array_map( 'intval', $raw['recent'] ) : [],
			'last_ok'     => (int) ( $raw['last_ok'] ?? 0 ),
			'last_failed' => (int) ( $raw['last_failed'] ?? 0 ),
			'last_error'  => (string) ( $raw['last_error'] ?? '' ),
		];
	}

	/**
	 * Verdict for Site Health, from the recorded outcomes.
	 *
	 * @param array{recent: array<int, int>, last_ok: int, last_failed: int, last_error: string} $state
	 * @return string 'none' (nothing sent yet), 'good', 'some_failed', 'all_failed'
	 */
	public static function verdict( array $state ): string {
		$recent = $state['recent'];
		if ( empty( $recent ) ) {
			return 'none';
		}
		$failed = count( $recent ) - array_sum( $recent );
		if ( 0 === $failed ) {
			return 'good';
		}
		// The most recent attempts decide: a site that failed last month
		// and has worked since is fine now.
		$last = array_slice( $recent, -3 );
		if ( 0 === array_sum( $last ) ) {
			return 'all_failed';
		}
		return end( $recent ) === 1 ? 'good' : 'some_failed';
	}
}
