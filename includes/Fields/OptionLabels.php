<?php
/**
 * Turn stored choice values back into the labels the visitor saw.
 *
 * Select, radio and checkbox fields store the option VALUE ("relaunch"),
 * because calculations, conditions, webhooks and the Pro CSV depend on it.
 * People reading a mail or the admin want the LABEL ("Website relaunch").
 * This class is the one place that maps between the two, so the mail, the
 * merge tags and the admin views never disagree.
 *
 * The stored value never changes. Only the display does.
 *
 * @package Flinkform
 * @since 1.15.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Value → label resolution for choice fields.
 */
final class OptionLabels {

	/**
	 * Field types whose values come from an options list.
	 */
	public const CHOICE_TYPES = [ 'select', 'radio', 'checkbox' ];

	/**
	 * Build a value → label map from a raw block `options` attribute.
	 *
	 * Options without a label (or with the label equal to the value) are
	 * left out: there is nothing to translate.
	 *
	 * @param mixed $raw_options Block attribute, list of { label, value }.
	 * @return array<string, string>
	 */
	public static function map_from_options( $raw_options ): array {
		if ( ! is_array( $raw_options ) ) {
			return [];
		}
		$map = [];
		foreach ( $raw_options as $opt ) {
			if ( ! is_array( $opt ) ) {
				continue;
			}
			$value = isset( $opt['value'] ) ? (string) $opt['value'] : '';
			$label = isset( $opt['label'] ) ? trim( (string) $opt['label'] ) : '';
			if ( '' === $value || '' === $label || $label === $value ) {
				continue;
			}
			// First occurrence wins, the same way the browser would pick
			// the first matching <option>.
			if ( ! isset( $map[ $value ] ) ) {
				$map[ $value ] = $label;
			}
		}
		return $map;
	}

	/**
	 * Resolve a stored value (string or list) to its display form.
	 *
	 * Unknown values (option removed since, free text) fall back to the
	 * value itself, so nothing ever disappears from a mail.
	 *
	 * @param mixed                 $value Stored value.
	 * @param array<string, string> $map   From map_from_options().
	 * @return mixed Same shape as $value.
	 */
	public static function resolve( $value, array $map ) {
		if ( empty( $map ) ) {
			return $value;
		}
		if ( is_array( $value ) ) {
			return array_values(
				array_map(
					static fn ( $v ) => $map[ (string) $v ] ?? (string) $v,
					$value
				)
			);
		}
		$value = (string) $value;
		return $map[ $value ] ?? $value;
	}

	/**
	 * Display form of a value for a field definition record (Locator shape).
	 *
	 * @param mixed                $value Stored value.
	 * @param array<string, mixed> $field Field record carrying `optionLabels`.
	 * @return mixed
	 */
	public static function for_field( $value, array $field ) {
		if ( ! in_array( (string) ( $field['type'] ?? '' ), self::CHOICE_TYPES, true ) ) {
			return $value;
		}
		$map = isset( $field['optionLabels'] ) && is_array( $field['optionLabels'] ) ? $field['optionLabels'] : [];
		return self::resolve( $value, $map );
	}

	/**
	 * Display value of a PERSISTED field record (submission payload).
	 *
	 * Rows since 1.15.0 carry a `display` snapshot. Older rows don't; for
	 * those the caller passes the live form definition, which is the best
	 * we can do (an option renamed since then shows its new label).
	 *
	 * @param array<string, mixed>                  $stored      Persisted field record.
	 * @param array<int, array<string, mixed>>|null $definition  Live field definitions, if known.
	 * @return mixed
	 */
	public static function display_value( array $stored, ?array $definition = null ) {
		$value = $stored['value'] ?? '';
		if ( array_key_exists( 'display', $stored ) ) {
			return $stored['display'];
		}
		if ( null === $definition || ! in_array( (string) ( $stored['type'] ?? '' ), self::CHOICE_TYPES, true ) ) {
			return $value;
		}
		foreach ( $definition as $field ) {
			if ( is_array( $field ) && ( $field['name'] ?? null ) === ( $stored['name'] ?? '' ) ) {
				return self::for_field( $value, $field );
			}
		}
		return $value;
	}

	/**
	 * Live field definitions of a form, cached per request (admin only).
	 *
	 * @param string $form_id Form UUID.
	 * @param int    $post_id Page the submission came from (tried first).
	 * @return array<int, array<string, mixed>>|null Null when the form is gone.
	 */
	public static function live_definition( string $form_id, int $post_id = 0 ): ?array {
		static $cache = [];
		if ( '' === $form_id ) {
			return null;
		}
		if ( ! array_key_exists( $form_id, $cache ) ) {
			$found             = ( new \Flinkform\Forms\Locator() )->locate_by_form_id( $form_id, $post_id );
			$cache[ $form_id ] = is_array( $found ) ? ( $found['fields'] ?? [] ) : null;
		}
		return $cache[ $form_id ];
	}
}
