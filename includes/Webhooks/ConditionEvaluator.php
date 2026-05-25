<?php
/**
 * Webhook condition evaluator.
 *
 * Each webhook can carry a single trigger condition — a field, an
 * operator and (for value-based operators) a comparison value. When
 * a submission lands, the evaluator decides whether the webhook
 * should fire for that submission. A webhook without a condition
 * fires unconditionally; a webhook with an invalid condition (e.g.
 * referring to a field name that doesn't exist on the form) fails
 * closed — the delivery is skipped rather than dispatched against
 * unverified intent.
 *
 * Operators:
 *
 *   equals           — strict-ish string equality
 *   not_equals       — inverse of equals
 *   contains         — substring search, case-insensitive
 *   not_contains     — inverse of contains
 *   is_empty         — true when the field value is null, empty string or empty array
 *   is_not_empty     — inverse of is_empty
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Webhooks;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless condition evaluator.
 */
final class ConditionEvaluator {

	private const VALUE_OPERATORS = [ 'equals', 'not_equals', 'contains', 'not_contains' ];
	private const EMPTY_OPERATORS = [ 'is_empty', 'is_not_empty' ];

	/**
	 * Decide whether a webhook should fire for the given submission.
	 *
	 * @param array<string, mixed> $webhook Hydrated webhook config row.
	 * @param array<string, mixed> $clean   Sanitised values keyed by field name (the same array passed to perform_after_submission).
	 * @return bool True = fire the webhook; false = skip.
	 */
	public function should_fire( array $webhook, array $clean ): bool {
		$field    = isset( $webhook['condition_field'] ) ? (string) $webhook['condition_field'] : '';
		$operator = isset( $webhook['condition_operator'] ) ? (string) $webhook['condition_operator'] : '';
		$value    = isset( $webhook['condition_value'] ) ? (string) $webhook['condition_value'] : '';

		if ( '' === $field || '' === $operator ) {
			// No condition configured — fire unconditionally.
			return true;
		}

		if ( ! in_array( $operator, array_merge( self::VALUE_OPERATORS, self::EMPTY_OPERATORS ), true ) ) {
			// Unknown operator — fail closed.
			return false;
		}

		$field_value = $this->extract_value( $clean, $field );

		switch ( $operator ) {
			case 'equals':
				return $this->to_string( $field_value ) === $value;
			case 'not_equals':
				return $this->to_string( $field_value ) !== $value;
			case 'contains':
				return '' !== $value && false !== stripos( $this->to_string( $field_value ), $value );
			case 'not_contains':
				return '' === $value || false === stripos( $this->to_string( $field_value ), $value );
			case 'is_empty':
				return $this->is_empty( $field_value );
			case 'is_not_empty':
				return ! $this->is_empty( $field_value );
		}

		return false; // Unreachable — the in_array guard above already filters unknown operators.
	}

	/**
	 * Pull a field's value out of the submission's clean array.
	 * Returns null when the field isn't on the form (which the
	 * `is_empty` operator treats as empty, matching the user's
	 * mental model of "the user didn't fill that one in").
	 *
	 * @param array<string, mixed> $clean Sanitised values keyed by field name.
	 * @param string               $field Field name to look up.
	 * @return mixed
	 */
	private function extract_value( array $clean, string $field ) {
		return $clean[ $field ] ?? null;
	}

	/**
	 * Coerce a field value into its string form for comparison.
	 * Multi-value fields (radio groups, multi-selects) join their
	 * entries with a comma — same shape the CSV exporter uses, so
	 * the author's mental model of "contains 'foo'" matches what
	 * they'd see in the admin list.
	 *
	 * @param mixed $value Field value.
	 * @return string
	 */
	private function to_string( $value ): string {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}
		if ( null === $value ) {
			return '';
		}
		return (string) $value;
	}

	/**
	 * Decide whether a field counts as "empty" — covers null, empty
	 * string, empty array, and the bool-false toggle-off state.
	 *
	 * @param mixed $value Field value.
	 * @return bool
	 */
	private function is_empty( $value ): bool {
		if ( null === $value ) {
			return true;
		}
		if ( is_array( $value ) ) {
			return empty( $value );
		}
		if ( is_string( $value ) ) {
			return '' === trim( $value );
		}
		return false;
	}
}
