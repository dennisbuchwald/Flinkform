<?php
/**
 * Server-side render for the Hidden Field block.
 *
 * The value is computed server-side at render time and at submit time.
 * Any POSTed value is discarded by the handler — see HiddenResolver.
 *
 * @var array<string, mixed> $attributes
 * @var WP_Block             $block
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$form_id    = isset( $block->context['flinkform/formId'] ) ? (string) $block->context['flinkform/formId'] : '';
$field_name = isset( $attributes['fieldName'] ) && is_string( $attributes['fieldName'] ) ? $attributes['fieldName'] : '';

if ( '' === $field_name || '' === $form_id ) {
	return;
}

$source       = isset( $attributes['valueSource'] ) && is_string( $attributes['valueSource'] ) ? $attributes['valueSource'] : 'static';
$static_value = isset( $attributes['staticValue'] ) && is_string( $attributes['staticValue'] ) ? $attributes['staticValue'] : '';
$resolved     = \Flinkform\Fields\HiddenResolver::resolve( $source, $static_value );

// We emit the hidden input purely for display/devtools clarity; the
// handler re-resolves the value on submit using the source from the
// form definition, ignoring whatever the browser POSTed.
?>
<input
	type="hidden"
	name="flinkform_field[<?php echo esc_attr( $field_name ); ?>]"
	value="<?php echo esc_attr( $resolved ); ?>"
/>
