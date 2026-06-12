<?php
/**
 * Server-side render for the Select Field block (single or multi).
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

$form_id     = isset( $block->context['flinkform/formId'] ) ? (string) $block->context['flinkform/formId'] : '';
$label       = isset( $attributes['label'] ) && is_string( $attributes['label'] ) ? $attributes['label'] : '';
$placeholder = isset( $attributes['placeholder'] ) && is_string( $attributes['placeholder'] ) ? $attributes['placeholder'] : '';
$flinkform_appearance = isset( $block->context['flinkform/appearance'] ) && is_array( $block->context['flinkform/appearance'] ) ? $block->context['flinkform/appearance'] : [];
if ( '' === $placeholder && ( $flinkform_appearance['labelPosition'] ?? '' ) === 'placeholder' && '' !== $label ) {
	$placeholder = '-- ' . $label . ( ! empty( $attributes['required'] ) ? '*' : '' ) . ' --';
}
$required    = ! empty( $attributes['required'] );
$help_text   = isset( $attributes['helpText'] ) && is_string( $attributes['helpText'] ) ? $attributes['helpText'] : '';
$field_name  = isset( $attributes['fieldName'] ) && is_string( $attributes['fieldName'] ) ? $attributes['fieldName'] : '';
$multiple    = ! empty( $attributes['multiple'] );
$options     = isset( $attributes['options'] ) && is_array( $attributes['options'] ) ? $attributes['options'] : [];

if ( '' === $field_name || '' === $form_id ) {
	return;
}

$value = \Flinkform\Submissions\Handler::flash_value( $field_name );
// Normalise flash value to an array of strings for comparison.
$selected = is_array( $value ) ? array_map( 'strval', $value ) : [ (string) $value ];

$error     = \Flinkform\Submissions\Handler::flash_error( $field_name );
$field_uid = 'flinkform-field-' . md5( $form_id . '-' . $field_name );
$help_id   = $help_text ? $field_uid . '-help' : '';
$error_id  = $error ? $field_uid . '-error' : '';
$described = trim( $help_id . ' ' . $error_id );
$name_attr = 'flinkform_field[' . $field_name . ']' . ( $multiple ? '[]' : '' );
?>
<div class="flinkform-field flinkform-field--select<?php echo $error ? ' flinkform-field--has-error' : ''; ?><?php echo ! empty( $attributes['fullWidth'] ) ? ' flinkform-field--full-width' : ''; ?>"<?php echo \Flinkform\Conditions\Wrapper::data_attribute( $attributes['conditionalLogic'] ?? [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- data_attribute() returns an esc_attr()-escaped attribute string. ?> data-flinkform-field-name="<?php echo esc_attr( $field_name ); ?>">
	<label class="flinkform-field__label" for="<?php echo esc_attr( $field_uid ); ?>">
		<?php echo esc_html( $label ); ?>
		<?php if ( $required ) : ?>
			<span class="flinkform-field__required" aria-hidden="true"> *</span>
		<?php endif; ?>
	</label>
	<select
		id="<?php echo esc_attr( $field_uid ); ?>"
		name="<?php echo esc_attr( $name_attr ); ?>"
		class="flinkform-field__input"
		<?php echo $multiple ? 'multiple' : ''; ?>
		<?php echo $required ? 'required aria-required="true"' : ''; ?>
		<?php echo $described ? 'aria-describedby="' . esc_attr( $described ) . '"' : ''; ?>
		<?php echo $error ? 'aria-invalid="true"' : ''; ?>
	>
		<?php if ( ! $multiple && '' !== $placeholder ) : ?>
			<option value="" <?php selected( in_array( '', $selected, true ) ); ?>>
				<?php echo esc_html( $placeholder ); ?>
			</option>
		<?php endif; ?>
		<?php foreach ( $options as $opt ) : ?>
			<?php
			$opt_value = isset( $opt['value'] ) ? (string) $opt['value'] : '';
			$opt_label = isset( $opt['label'] ) && '' !== $opt['label'] ? (string) $opt['label'] : $opt_value;
			?>
			<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( in_array( $opt_value, $selected, true ) ); ?>>
				<?php echo esc_html( $opt_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php if ( $help_text ) : ?>
		<p class="flinkform-field__help" id="<?php echo esc_attr( $help_id ); ?>">
			<?php echo esc_html( $help_text ); ?>
		</p>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<p class="flinkform-field__error" id="<?php echo esc_attr( $error_id ); ?>" role="alert">
			<?php echo esc_html( $error ); ?>
		</p>
	<?php endif; ?>
</div>
