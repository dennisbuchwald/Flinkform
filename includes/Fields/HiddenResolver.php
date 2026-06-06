<?php
/**
 * Resolve dynamic values for hidden form fields.
 *
 * Single source of truth used by both render.php (when the form is
 * served to the visitor) and Handler::validate (when the submission
 * comes back). Doing the resolution server-side at submit time means
 * a visitor can't tamper with the hidden value via DevTools — whatever
 * the browser sends is ignored, the source dictates the truth.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace PerForm\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Maps a value-source identifier to the actual value at request time.
 */
final class HiddenResolver {

	/**
	 * Allowed source identifiers. Anything outside this list collapses
	 * to "static" (with whatever staticValue was configured).
	 */
	public const SOURCES = [
		'static',
		'current_url',
		'current_post_id',
		'current_user_id',
		'current_date',
		'current_datetime',
	];

	/**
	 * Compute the value for a given source.
	 *
	 * @param string $source       Source identifier (one of self::SOURCES).
	 * @param string $static_value Used only when $source === 'static'.
	 * @return string
	 */
	public static function resolve( string $source, string $static_value = '' ): string {
		switch ( $source ) {
			case 'current_url':
				return self::current_url();
			case 'current_post_id':
				return (string) (int) get_the_ID();
			case 'current_user_id':
				return (string) get_current_user_id();
			case 'current_date':
				return current_time( 'Y-m-d' );
			case 'current_datetime':
				return current_time( 'c' );
			case 'static':
			default:
				return $static_value;
		}
	}

	/**
	 * Best-effort current URL — works on both the page render path
	 * (where we can use get_permalink) and on the admin-post.php submit
	 * path (where we have to fall back to the HTTP referer).
	 *
	 * @return string
	 */
	private static function current_url(): string {
		$post_id = (int) get_the_ID();
		if ( $post_id > 0 ) {
			$permalink = get_permalink( $post_id );
			if ( false !== $permalink ) {
				return $permalink;
			}
		}

		$referer = wp_get_raw_referer();
		return is_string( $referer ) ? esc_url_raw( $referer ) : '';
	}
}
