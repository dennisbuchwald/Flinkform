<?php
/**
 * The marker after a field label: "*" for required, and since 1.15.0
 * optionally "(optional)" for everything else.
 *
 * Forms with mostly required fields read better with the star; forms with
 * mostly optional ones read better when the few optional fields say so.
 * The form decides (`appearance.markOptional`), every field block renders
 * through here so the two can never disagree.
 *
 * @package Flinkform
 * @since 1.15.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Required / optional label marker.
 */
final class LabelMarks {

	/**
	 * Marker HTML (already escaped).
	 *
	 * The star stays aria-hidden: the control carries `required`, which
	 * screen readers announce. "(optional)" is real text, because nothing
	 * else tells a screen reader that a field may be skipped.
	 *
	 * @param bool  $required Whether the field is required.
	 * @param mixed $block    WP_Block (reads the flinkform/appearance context), or null.
	 * @return string
	 */
	public static function html( bool $required, $block = null ): string {
		if ( $required ) {
			return '<span class="flinkform-field__required" aria-hidden="true"> *</span>';
		}
		$appearance = is_object( $block ) && isset( $block->context['flinkform/appearance'] ) && is_array( $block->context['flinkform/appearance'] )
			? $block->context['flinkform/appearance']
			: [];
		if ( empty( $appearance['markOptional'] ) ) {
			return '';
		}
		return '<span class="flinkform-field__optional"> ' . esc_html__( '(optional)', 'flinkform' ) . '</span>';
	}
}
