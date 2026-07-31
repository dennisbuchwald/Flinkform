<?php
/**
 * Works out which fields a form's conditional logic currently hides.
 *
 * A hidden field is excluded from the submission, so its value must not
 * influence any condition either — neither another field's visibility nor
 * the submit gate. Anything else lets the browser and the server disagree
 * about what the form says.
 *
 * The case that surfaced it: a postcode select shown only for
 * "Wochenbett" keeps its "andere-plz" value after the visitor switches to
 * "Hausgeburt". The field is hidden, the server has already dropped it —
 * but in the browser the value kept satisfying conditions, so a notice
 * stayed on screen and the submit button stayed locked.
 *
 * Resolution has to be iterative. Visibility depends on values, and values
 * depend on visibility: hiding A can be exactly what makes B's rule true.
 * One pass over the raw values, which is what this used to be, cannot see
 * that.
 *
 * The mirror image lives in src/shared/rule-evaluator.js
 * (resolveHiddenFields). Both must agree down to the pass cap and the
 * iteration order; tests/visibility-parity.mjs runs them against one
 * shared table of cases.
 *
 * @package Flinkform
 * @since 1.10.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Fixed-point resolution of conditional visibility.
 */
final class VisibilityResolver {

	/**
	 * How many passes before the resolution gives up and keeps what it has.
	 *
	 * Mirrors MAX_VISIBILITY_PASSES in the JS half. A contradictory setup
	 * ("show A when B is empty" against "show B when A is empty")
	 * oscillates between two states forever; the cap turns that into a
	 * defined outcome instead of a hang.
	 *
	 * @var int
	 */
	public const MAX_PASSES = 10;

	/**
	 * Resolve which field names should be treated as hidden.
	 *
	 * Recomputes the whole set from the raw values on every pass, with the
	 * previous pass's hidden fields blanked, until it stops changing.
	 *
	 * Deliberately not accumulated. A set that only ever grew would settle
	 * faster but be wrong: if A is hidden in pass one and that makes B's
	 * rule true, B has to come back, and an accumulating set could never
	 * release it.
	 *
	 * When the cap is reached the last computed set is kept — deterministic
	 * for a given input and field order, which is what lets the client
	 * reach the same answer on a form whose rules contradict each other.
	 *
	 * @param array<int, array<string, mixed>> $entries In document order. Each entry is
	 *        `[ 'names' => string[], 'ruleSet' => array ]` — `names` is plural because one
	 *        rule set can govern several values (an address field, or every field inside a
	 *        step whose page-break condition skips it).
	 * @param array<string, mixed>             $values  Raw values keyed by field name.
	 * @return array<int, string> Field names to treat as empty.
	 */
	public static function resolve( array $entries, array $values ): array {
		$hidden = [];

		for ( $pass = 0; $pass < self::MAX_PASSES; $pass++ ) {
			$effective = self::apply_hidden( $values, $hidden );
			$next      = [];

			foreach ( $entries as $entry ) {
				$rule_set = isset( $entry['ruleSet'] ) && is_array( $entry['ruleSet'] ) ? $entry['ruleSet'] : [];
				if ( empty( $rule_set ) ) {
					continue;
				}

				$evaluator = new RuleEvaluator();
				if ( ! $evaluator->should_show( $rule_set, $effective ) ) {
					foreach ( (array) ( $entry['names'] ?? [] ) as $name ) {
						$next[ (string) $name ] = true;
					}
				}
			}

			$next_names = array_keys( $next );
			sort( $next_names );

			$current = $hidden;
			sort( $current );

			if ( $next_names === $current ) {
				return $hidden;
			}

			$hidden = array_keys( $next );
		}

		return $hidden;
	}

	/**
	 * A copy of the values map with every hidden field blanked.
	 *
	 * Blanked rather than unset: both evaluators treat a missing key and an
	 * empty string identically, and an explicit '' is easier to follow when
	 * reading a dump of what a condition actually saw.
	 *
	 * @param array<string, mixed> $values Raw values keyed by field name.
	 * @param array<int, string>   $hidden Field names currently resolved as hidden.
	 * @return array<string, mixed>
	 */
	public static function apply_hidden( array $values, array $hidden ): array {
		if ( empty( $hidden ) ) {
			return $values;
		}

		foreach ( $hidden as $name ) {
			$values[ (string) $name ] = '';
		}

		return $values;
	}
}
