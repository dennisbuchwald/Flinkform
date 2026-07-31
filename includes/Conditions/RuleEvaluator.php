<?php
/**
 * Conditional-logic rule evaluator.
 *
 * Stateless engine. Consumers (frontend renderer, submission handler,
 * webhook subsystem) hand over a `conditionalLogic` block-attribute
 * struct and the current values map; the evaluator returns a bool —
 * true when the rule set says "this element should be visible /
 * active", false when it doesn't.
 *
 * Rule set shape (matches the JS-side schema produced by
 * `src/shared/conditional-logic-panel.js`):
 *
 *   [
 *     'enabled' => true,           // false → always returns true (= visible)
 *     'logic'   => 'all'|'any',   // AND vs OR across all rules
 *     'rules'   => [
 *       [ 'field' => 'email', 'operator' => 'contains', 'value' => '@dbw-media.de' ],
 *       [ 'field' => 'role',  'operator' => 'is',       'value' => 'admin' ],
 *     ],
 *   ]
 *
 * Operators understood:
 *
 *   is              — strict-ish string equality
 *   is_not          — inverse of `is`
 *   contains        — case-insensitive substring match
 *   not_contains    — inverse of `contains`
 *   is_empty        — null, empty string, empty array
 *   is_not_empty    — inverse of `is_empty`
 *   greater_than    — numeric > (non-numeric values fall back to false)
 *   less_than       — numeric < (non-numeric values fall back to false)
 *
 * Failure modes are intentionally lopsided:
 *
 *   * Disabled rule set → returns true (visible).
 *   * Empty rule list   → returns true (visible).
 *   * Unknown operator  → that rule reads as false (excluded from
 *     `any`, fails the overall match for `all`). Better to under-
 *     show than to leak a hidden field through a typo in storage.
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless conditional-logic engine.
 */
final class RuleEvaluator {

	/**
	 * How deep a nested rule set may go before we stop descending.
	 *
	 * The editor offers one level of nesting; this only exists so a
	 * hand-edited or corrupted payload cannot recurse without bound.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 10;

	private const VALUE_OPERATORS = [ 'is', 'is_not', 'contains', 'not_contains', 'greater_than', 'less_than', 'date_before', 'date_on_or_after' ];
	private const EMPTY_OPERATORS = [ 'is_empty', 'is_not_empty' ];

	/**
	 * Evaluate a rule set against the current submission values.
	 *
	 * @param array<string, mixed>|null $rule_set Block-attribute shape (see class-level docblock).
	 * @param array<string, mixed>      $values   Sanitised values keyed by field name.
	 * @return bool True = element should be visible / active.
	 */
	public function should_show( $rule_set, array $values ): bool {
		if ( ! is_array( $rule_set ) || empty( $rule_set['enabled'] ) ) {
			return true;
		}

		return $this->evaluate_group( $rule_set, $values, 0 );
	}

	/**
	 * Evaluate one level of a rule set.
	 *
	 * An entry is either a leaf (`{field, operator, value}`) or a nested
	 * group (`{logic, rules}`) carrying its own ALL/ANY. A group's verdict
	 * folds into the parent exactly like a leaf's, which is what makes
	 * "(A or B or C) and D" expressible at all — a single flat list with one
	 * match mode cannot say that.
	 *
	 * Empty nested groups are skipped rather than counted. An author who
	 * has added a group but not yet filled it should not thereby flip the
	 * whole condition, which is what a hard true or false would do in ANY
	 * and ALL mode respectively.
	 *
	 * @param array<string, mixed> $group  A rule set or nested group.
	 * @param array<string, mixed> $values Sanitised values keyed by field name.
	 * @param int                  $depth  Current nesting depth.
	 * @return bool
	 */
	private function evaluate_group( array $group, array $values, int $depth ): bool {
		// Cheap insurance against a hand-crafted payload nesting far enough
		// to blow the stack. Two levels is the documented feature; ten
		// leaves room without ever being reachable by the editor UI.
		if ( $depth > self::MAX_DEPTH ) {
			return true;
		}

		$rules = isset( $group['rules'] ) && is_array( $group['rules'] ) ? $group['rules'] : [];
		if ( empty( $rules ) ) {
			return true;
		}

		$logic     = ( isset( $group['logic'] ) && 'any' === $group['logic'] ) ? 'any' : 'all';
		$evaluated = 0;

		foreach ( $rules as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( self::is_group( $entry ) ) {
				// An empty group says nothing; leave the verdict to its siblings.
				if ( empty( $entry['rules'] ) ) {
					continue;
				}
				$match = $this->evaluate_group( $entry, $values, $depth + 1 );
			} else {
				$match = $this->evaluate_rule( $entry, $values );
			}

			++$evaluated;

			if ( 'any' === $logic && $match ) {
				return true;
			}
			if ( 'all' === $logic && ! $match ) {
				return false;
			}
		}

		// Nothing usable in here at all — same stance as an empty rule set:
		// a condition that says nothing must not hide anything.
		if ( 0 === $evaluated ) {
			return true;
		}

		// Every entry processed without a short-circuit:
		//   - all-mode: everything matched → true
		//   - any-mode: nothing matched → false
		return 'all' === $logic;
	}

	/**
	 * Is this entry a nested group rather than a leaf rule?
	 *
	 * The presence of a `rules` array is the discriminator. A leaf never
	 * carries one, so old flat rule sets can never be mistaken for groups.
	 *
	 * @param array<string, mixed> $entry
	 * @return bool
	 */
	public static function is_group( array $entry ): bool {
		return isset( $entry['rules'] ) && is_array( $entry['rules'] );
	}

	/**
	 * Evaluate a single rule against the values map.
	 *
	 * @param array<string, mixed> $rule   `{field, operator, value}`.
	 * @param array<string, mixed> $values Sanitised values keyed by field name.
	 * @return bool
	 */
	private function evaluate_rule( array $rule, array $values ): bool {
		$field    = isset( $rule['field'] ) ? (string) $rule['field'] : '';
		$operator = isset( $rule['operator'] ) ? (string) $rule['operator'] : '';
		$value    = isset( $rule['value'] ) ? (string) $rule['value'] : '';

		if ( '' === $field || '' === $operator ) {
			return false;
		}

		$field_value = $this->extract_value( $values, $field );

		if ( in_array( $operator, self::EMPTY_OPERATORS, true ) ) {
			$empty = $this->is_empty( $field_value );
			return 'is_empty' === $operator ? $empty : ! $empty;
		}

		if ( ! in_array( $operator, self::VALUE_OPERATORS, true ) ) {
			return false; // Unknown operator.
		}

		$field_string = $this->to_string( $field_value );

		switch ( $operator ) {
			case 'is':
				// Case-insensitive on purpose. `contains` /
				// `not_contains` already match case-insensitively, and
				// the editor's slugify helper lowercases auto-derived
				// option values ("Skip" → "skip"); a strict-equality
				// `is` would silently fail when the form builder
				// types "Skip" in the rule UI to match a label they
				// wrote as "Skip" but ended up serialised as "skip".
				return 0 === strcasecmp( $field_string, $value );
			case 'is_not':
				return 0 !== strcasecmp( $field_string, $value );
			case 'contains':
				return '' !== $value && false !== stripos( $field_string, $value );
			case 'not_contains':
				return '' === $value || false === stripos( $field_string, $value );
			case 'greater_than':
				if ( ! is_numeric( $field_string ) || ! is_numeric( $value ) ) {
					return false;
				}
				return (float) $field_string > (float) $value;
			case 'less_than':
				if ( ! is_numeric( $field_string ) || ! is_numeric( $value ) ) {
					return false;
				}
				return (float) $field_string < (float) $value;
			case 'date_before':
			case 'date_on_or_after':
				// `/D` so `$` cannot also match before a trailing newline —
				// without it "2026-07-27\n" would pass here but fail the
				// identical client-side regex in view.js, splitting the
				// client/server verdict on the same input.
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $field_string ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
					return false;
				}
				return 'date_before' === $operator
					? $field_string < $value
					: $field_string >= $value;
		}

		return false;
	}

	/**
	 * Pull a field's value out of the values map.
	 *
	 * @param array<string, mixed> $values Values map.
	 * @param string               $field  Field name.
	 * @return mixed
	 */
	private function extract_value( array $values, string $field ) {
		return $values[ $field ] ?? null;
	}

	/**
	 * Coerce a field value into its string form for comparison.
	 * Multi-value fields (radio groups, multi-selects) join with
	 * comma — same shape the admin CSV exporter produces, so the
	 * author's mental model of "contains 'foo'" matches what they
	 * see in the admin.
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
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		return (string) $value;
	}

	/**
	 * Decide whether a field counts as "empty".
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
		if ( is_bool( $value ) ) {
			return ! $value;
		}
		return false;
	}
}
