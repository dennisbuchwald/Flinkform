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
			'rules'   => array_values( array_filter(
				array_map(
					static function ( $rule ) {
						if ( ! is_array( $rule ) ) {
							return null;
						}
						return [
							'field'    => isset( $rule['field'] ) ? (string) $rule['field'] : '',
							'operator' => isset( $rule['operator'] ) ? (string) $rule['operator'] : '',
							'value'    => isset( $rule['value'] ) ? (string) $rule['value'] : '',
						];
					},
					$rules
				)
			) ),
		];

		return (string) wp_json_encode( $payload );
	}
}
