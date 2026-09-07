<?php
/**
 * Decides how a form's request-specific state reaches the browser.
 *
 * Every Flinkform form needs four values that only the server can mint:
 * a submit nonce, an HMAC-signed render timestamp, and (when the form is
 * protected) a spam token plus its proof-of-work salt and math question.
 * Until 1.13.x all four were printed into the HTML at render time, which
 * forced the plugin to exclude the whole page from every full-page cache
 * via DONOTCACHEPAGE — measurably ~0.7 s extra TTFB per view on exactly
 * the pages a site wants to convert on.
 *
 * Since 1.14.0 there are two modes:
 *
 *   DEFERRED (default) — the HTML carries empty placeholders and the
 *                        browser fetches the real values from the
 *                        challenge endpoint on first contact with the
 *                        form. Nothing request-specific ends up in the
 *                        cache file, so the page caches normally.
 *
 *   INLINE (legacy)    — the pre-1.14 behaviour: everything rendered
 *                        server-side, page marked uncacheable. Still the
 *                        right answer for renders that are inherently
 *                        per-visitor (a flashed error state) or that
 *                        contain state this plugin cannot defer (a Pro
 *                        payment field mints its own nonces).
 *
 * @package Flinkform
 * @since 1.14.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Spam;

defined( 'ABSPATH' ) || exit;

use Flinkform\Submissions\Handler;

/**
 * Render-mode resolution + the one place that marks a page uncacheable.
 */
final class RenderMode {

	/**
	 * Challenge values are fetched by the browser after render.
	 *
	 * @var string
	 */
	public const DEFERRED = 'deferred';

	/**
	 * Challenge values are printed into the HTML (pre-1.14 behaviour).
	 *
	 * @var string
	 */
	public const INLINE = 'inline';

	/**
	 * Query arg that forces one uncached inline render.
	 *
	 * The `<noscript>` escape hatch links here: a visitor without
	 * JavaScript cannot fetch the deferred challenge, so they get a
	 * one-off uncached render of the same form that works exactly the
	 * way it did before 1.14.0.
	 *
	 * @var string
	 */
	public const NOJS_QUERY_ARG = 'flinkform_nojs';

	/**
	 * Hidden input that tells the server a form was rendered deferred.
	 *
	 * Without it, a submission arriving with an empty nonce or timestamp
	 * would be indistinguishable from a forged one and get dropped
	 * silently — the exact failure class this release exists to remove.
	 *
	 * @var string
	 */
	public const MARKER_FIELD = 'flinkform_challenge';

	/**
	 * Resolve the render mode for one form render.
	 *
	 * @param string               $form_id    Form UUID.
	 * @param array<string, mixed> $attributes Form-container block attributes.
	 * @param \WP_Block|null       $block      The block being rendered, for inner-block inspection.
	 * @return string Self::DEFERRED or self::INLINE.
	 */
	public static function resolve( string $form_id, array $attributes = [], ?\WP_Block $block = null ): string {
		$forced = self::must_render_inline( $form_id, $attributes, $block );

		/**
		 * Filter the render mode for a single form.
		 *
		 * Return true to restore the pre-1.14 behaviour (everything
		 * rendered server-side, page excluded from full-page caches).
		 * The escape hatch for setups where the deferred fetch cannot
		 * work — a locked-down REST API behind an auth wall, say.
		 *
		 * Note that the conditions in must_render_inline() are NOT
		 * filterable downwards: a flashed error state or a payment field
		 * must never be served from a shared cache, so returning false
		 * here cannot force those renders into deferred mode.
		 *
		 * @since 1.14.0
		 *
		 * @param bool                 $inline     Whether to render inline.
		 * @param string               $form_id    Form UUID.
		 * @param array<string, mixed> $attributes Form-container block attributes.
		 */
		$inline = (bool) apply_filters( 'flinkform_render_challenge_inline', $forced, $form_id, $attributes );

		return ( $inline || $forced ) ? self::INLINE : self::DEFERRED;
	}

	/**
	 * Conditions under which deferring is not an option.
	 *
	 * @param string               $form_id    Form UUID.
	 * @param array<string, mixed> $attributes Form-container block attributes.
	 * @param \WP_Block|null       $block      The block being rendered.
	 * @return bool
	 */
	private static function must_render_inline( string $form_id, array $attributes, ?\WP_Block $block ): bool {
		// Logged-in visitors are never served from a full-page cache, so
		// there is nothing to gain from deferring — and the editor's
		// server-side block preview renders through this path too.
		if ( is_user_logged_in() ) {
			return true;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing decision, no state change.
		// The no-JS escape hatch: one uncached render on request.
		if ( isset( $_GET[ self::NOJS_QUERY_ARG ] ) ) {
			return true;
		}

		// A success or error render is per-visitor by definition. Caches
		// that key on the query string (LiteSpeed does by default) would
		// otherwise hand one visitor's re-populated form to the next.
		if ( isset( $_GET['flinkform_status'] ) ) {
			return true;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Same reason, one step earlier: this visitor has a flash cookie,
		// so this render may repopulate their values even without the
		// query arg (a themed 404/redirect that dropped it, say).
		if ( isset( $_COOKIE[ Handler::FLASH_COOKIE_NAME ] ) ) {
			return true;
		}

		return self::has_uncacheable_inner_block( $block );
	}

	/**
	 * Whether the form contains a block whose own markup is request-specific.
	 *
	 * The Pro payment field prints a Stripe intent nonce and a wp_rest
	 * nonce at render time. Those are not this plugin's to defer, and a
	 * cached payment form would fail once the nonce tick rolls over — the
	 * bug Pro 1.2.2 fixed, just delayed by twelve hours.
	 *
	 * @param \WP_Block|null $block Block to inspect (recursively).
	 * @return bool
	 */
	private static function has_uncacheable_inner_block( ?\WP_Block $block ): bool {
		if ( null === $block ) {
			return false;
		}

		/**
		 * Filter the block types that force an inline, uncached render.
		 *
		 * Add-ons that print their own per-request state into a field's
		 * markup register the block name here.
		 *
		 * @since 1.14.0
		 *
		 * @param array<int, string> $names Block names.
		 */
		$names = (array) apply_filters( 'flinkform_uncacheable_blocks', [ 'flinkform/field-payment' ] );
		if ( empty( $names ) ) {
			return false;
		}

		return self::tree_contains( $block, $names );
	}

	/**
	 * Depth-first search for any of the given block names.
	 *
	 * @param \WP_Block          $block Block to walk.
	 * @param array<int, string> $names Block names to look for.
	 * @return bool
	 */
	private static function tree_contains( \WP_Block $block, array $names ): bool {
		foreach ( $block->inner_blocks as $inner ) {
			if ( in_array( $inner->name, $names, true ) ) {
				return true;
			}
			if ( self::tree_contains( $inner, $names ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Mark the current page as uncacheable.
	 *
	 * DONOTCACHEPAGE is respected by WP Super Cache, W3 Total Cache,
	 * LiteSpeed Cache, AccelerateWP, WP Rocket and virtually every other
	 * WordPress caching plugin. Called only from the inline path — in
	 * deferred mode the page is meant to cache, which is the whole point.
	 *
	 * @return void
	 */
	public static function mark_uncacheable(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Community-standard constant that caching plugins (WP Super Cache, W3TC, LiteSpeed, WP Rocket) look for by exactly this name; a prefixed variant would be ignored by all of them.
			define( 'DONOTCACHEPAGE', true );
		}
	}
}
