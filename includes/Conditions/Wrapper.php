<?php
/**
 * Conditional-logic wrapper-attribute helper.
 *
 * Renders the `data-flinkform-condition` HTML attribute every Flinkform
 * block that supports conditional logic emits on its outer wrapper —
 * the frontend JS reads the JSON back out via `dataset.flinkformCondition`
 * and re-evaluates against current form values on every input change.
 *
 * Kept as a tiny static method so each block's `render.php` can
 * call it inline without instantiating anything. The HTML it returns
 * either starts with a leading space (when there's a rule set to
 * emit) or is an empty string (no rule, no attribute, no leading
 * space). Callers echo the result directly into the opening tag.
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Wrapper-attribute renderer.
 */
final class Wrapper {

	/**
	 * Nesting ceiling for the serialised payload, matching
	 * RuleEvaluator::MAX_DEPTH — there is no point emitting depth the
	 * evaluator will refuse to descend into.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 10;

	/**
	 * Build the raw JSON payload a conditional-logic block exposes to the
	 * frontend evaluator. Returns an empty string when the rule set is
	 * missing, disabled, or carries no rules.
	 *
	 * NOTE: the returned value is intentionally NOT escaped — callers must
	 * escape it at output with `esc_attr()` (escaping late). The frontend
	 * reads it back via `JSON.parse( el.dataset.* )`, so the value is plain
	 * JSON and `esc_attr()` is the correct context for the data attribute.
	 *
	 * @param mixed $rule_set The block's `conditionalLogic` attribute (object-shaped array, or anything).
	 * @return string A JSON string, or `''` when no condition applies.
	 */
	public static function condition_value( $rule_set ): string {
		if ( ! is_array( $rule_set ) || empty( $rule_set['enabled'] ) ) {
			return '';
		}
		$rules = isset( $rule_set['rules'] ) && is_array( $rule_set['rules'] ) ? $rule_set['rules'] : [];
		if ( empty( $rules ) ) {
			return '';
		}

		// Re-serialise only the fields the frontend evaluator needs
		// (matches the shape RuleEvaluator::should_show consumes).
		// Trimming extra keys keeps the data attribute small + makes
		// the payload deterministic for caching layers.
		$payload = [
			'enabled' => true,
			'logic'   => ( isset( $rule_set['logic'] ) && 'any' === $rule_set['logic'] ) ? 'any' : 'all',
			'rules'   => self::normalise_rules( $rules, 0 ),
		];

		return (string) wp_json_encode( $payload );
	}

	/**
	 * Re-serialise a rule list, keeping nested groups intact.
	 *
	 * Trimming each entry to the keys the evaluator consumes keeps the data
	 * attribute small and its content deterministic for caching layers.
	 * Groups have to recurse rather than be flattened: dropping the nesting
	 * would silently turn "(A or B) and C" into a flat list under one match
	 * mode, which is a different condition — and the browser would then
	 * disagree with the server, which reads the unflattened attribute.
	 *
	 * @param array<int, mixed> $rules Raw rule entries from the attribute.
	 * @param int               $depth Current nesting depth.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalise_rules( array $rules, int $depth ): array {
		if ( $depth > self::MAX_DEPTH ) {
			return [];
		}

		$out = [];

		foreach ( $rules as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( RuleEvaluator::is_group( $entry ) ) {
				$nested = self::normalise_rules( $entry['rules'], $depth + 1 );
				if ( empty( $nested ) ) {
					// An empty group carries no meaning; leaving it out
					// keeps the payload honest about what will be evaluated.
					continue;
				}
				$out[] = [
					'logic' => ( isset( $entry['logic'] ) && 'any' === $entry['logic'] ) ? 'any' : 'all',
					'rules' => $nested,
				];
				continue;
			}

			$out[] = [
				'field'    => isset( $entry['field'] ) ? (string) $entry['field'] : '',
				'operator' => isset( $entry['operator'] ) ? (string) $entry['operator'] : '',
				'value'    => isset( $entry['value'] ) ? (string) $entry['value'] : '',
			];
		}

		return $out;
	}

	/**
	 * Build the complete wrapper attributes for a conditional block: the
	 * `data-flinkform-condition` payload plus `hidden` when the block
	 * should not be visible in the form's INITIAL state.
	 *
	 * Why the server decides the initial state at all: the frontend module
	 * is deferred, so between first paint and DOMContentLoaded every
	 * conditional block is visible. For a text input that is a blip; for a
	 * Notice block it is a coloured box that visibly appears and then
	 * vanishes. Rendering the correct state up front removes the flash.
	 *
	 * The server can do this because it knows exactly the values the client
	 * will evaluate against on load: nothing on a fresh page, or the
	 * submitted values when a failed submission is being re-rendered. The
	 * PHP and JS evaluators agree on those inputs (both treat a missing
	 * field as empty), so the initial verdict matches and the JS pass at
	 * DOMContentLoaded is a no-op.
	 *
	 * Where they can disagree is a block whose rule targets a field that
	 * renders with a non-empty default the server's value map does not
	 * carry — a hidden field with a static value, say. Then the JS corrects
	 * it on load, which is exactly the behaviour before this method existed:
	 * no worse, just not improved.
	 *
	 * Note for no-JS visitors: conditional blocks now start in their
	 * evaluated state rather than all-visible. Conditional logic is
	 * inherently interactive and never worked without JS, so a correct
	 * initial state beats showing every branch at once.
	 *
	 * Returns a string that is safe to echo directly into an opening tag,
	 * with a leading space, or `''` when no condition applies.
	 *
	 * @param mixed $rule_set The block's `conditionalLogic` attribute.
	 * @return string Pre-escaped attribute string.
	 */
	public static function condition_attributes( $rule_set ): string {
		$json = self::condition_value( $rule_set );
		if ( '' === $json ) {
			return '';
		}

		$attributes = ' data-flinkform-condition="' . esc_attr( $json ) . '"';

		$evaluator = new RuleEvaluator();
		if ( ! $evaluator->should_show( $rule_set, \Flinkform\Submissions\Handler::current_values() ) ) {
			$attributes .= ' hidden';
		}

		return $attributes;
	}
}
