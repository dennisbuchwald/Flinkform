<?php
/**
 * Conditional-logic wrapper-attribute helper.
 *
 * Renders the `data-perform-condition` HTML attribute every PerForm
 * block that supports conditional logic emits on its outer wrapper —
 * the frontend JS reads the JSON back out via `dataset.performCondition`
 * and re-evaluates against current form values on every input change.
 *
 * Kept as a tiny static method so each block's `render.php` can
 * call it inline without instantiating anything. The HTML it returns
 * either starts with a leading space (when there's a rule set to
 * emit) or is an empty string (no rule, no attribute, no leading
 * space). Callers echo the result directly into the opening tag.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Wrapper-attribute renderer.
 */
final class Wrapper {

	/**
	 * Render the `data-perform-condition` attribute (with a leading
	 * space) for a block whose `conditionalLogic` is enabled. Returns
	 * an empty string when the rule set is missing, disabled, or
	 * carries no rules — so the block's HTML stays byte-identical to
	 * its pre-Phase-7 output on every form that doesn't use the feature.
	 *
	 * @param mixed $rule_set The block's `conditionalLogic` attribute (object-shaped array, or anything).
	 * @return string `' data-perform-condition="..."'` or `''`.
	 */
	public static function data_attribute( $rule_set ): string {
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

		return ' data-perform-condition="' . esc_attr( (string) wp_json_encode( $payload ) ) . '"';
	}
}
