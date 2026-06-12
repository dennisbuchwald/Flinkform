<?php
/**
 * Pro-capability façade — the single source of truth for the Free/Pro split.
 *
 * The free core never ships any of these capabilities active. The Pro add-on
 * switches them on by hooking the `flinkform_pro_features` filter (from its main
 * file, before `plugins_loaded` fires). Every free-core code path that touches
 * a Pro-bound module asks here first, so the core degrades gracefully when Pro
 * is absent instead of erroring:
 *
 *   if ( Features::has( Features::MULTISTEP ) ) {
 *       // Pro is present — let it slice the form into steps.
 *   } else {
 *       // Free — render the whole form as a single page. No fatal.
 *   }
 *
 * This mirrors the Spam\Guard façade: one integration point, callers branch on
 * the result, and the resolution logic lives in exactly one place. Adding a new
 * capability is a matter of declaring a constant here — never a rewrite of the
 * call sites.
 *
 * CONTRACT: see includes/Bridge/README.md. Once the Pro add-on ships, the
 * `flinkform_pro_features` filter name and the capability keys below are frozen —
 * additive changes only.
 *
 * @package Flinkform
 * @since 0.2.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Bridge;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves which Pro capabilities are currently available.
 */
final class Features {

	/**
	 * Capability keys.
	 *
	 * These are the stable identifiers the Pro add-on advertises and the free
	 * core checks against. Mapped 1:1 to the Free/Pro feature matrix in
	 * FLINKFORM_ROADMAP.md (Phase M).
	 */
	public const WEBHOOKS           = 'webhooks';
	public const SUBMISSIONS_EXPORT = 'submissions_export';
	public const SMTP               = 'smtp';
	public const MULTI_STEP         = 'multi_step';
	public const SPAM_CHALLENGE     = 'spam_challenge';
	public const CUSTOM_CSS         = 'custom_css';

	/**
	 * Resolve the active capability set.
	 *
	 * Accepts both shapes the Pro add-on might return from the filter, so the
	 * add-on author can use whichever reads cleaner:
	 *
	 *   [ 'webhooks', 'smtp' ]                  // sequential list
	 *   [ 'webhooks' => true, 'smtp' => false ] // keyed map (false disables)
	 *
	 * Not cached on purpose: the filter is added during the boot cycle and the
	 * lookups happen later (render time), but keeping this stateless avoids the
	 * "filter registered after the first read" footgun for the cost of one
	 * cheap apply_filters per call.
	 *
	 * @return array<string, true> Set of active capability keys.
	 */
	public static function all(): array {
		$raw = (array) apply_filters( 'flinkform_pro_features', [] );
		$set = [];

		foreach ( $raw as $key => $value ) {
			if ( is_int( $key ) ) {
				// Sequential list form: the value is the capability name.
				$name = sanitize_key( (string) $value );
				if ( '' !== $name ) {
					$set[ $name ] = true;
				}
			} elseif ( $value ) {
				// Keyed map form: truthy value enables the capability.
				$name = sanitize_key( (string) $key );
				if ( '' !== $name ) {
					$set[ $name ] = true;
				}
			}
		}

		return $set;
	}

	/**
	 * Is a given Pro capability available right now?
	 *
	 * @param string $feature One of the capability constants above.
	 * @return bool
	 */
	public static function has( string $feature ): bool {
		return isset( self::all()[ sanitize_key( $feature ) ] );
	}

	/**
	 * Is any Pro capability active at all?
	 *
	 * Cheap "is the Pro add-on docked?" probe for code that only needs the
	 * yes/no — e.g. whether to show upsell hints in the editor or admin.
	 *
	 * @return bool
	 */
	public static function is_pro_active(): bool {
		return [] !== self::all();
	}
}
