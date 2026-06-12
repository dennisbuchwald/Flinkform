<?php
/**
 * Server-side render for the Phone Field block.
 *
 * Output contract: ECHOES markup directly — see form-container/render.php
 * for why nested ob_start() is unsafe here.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
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
	$placeholder = $label . ( ! empty( $attributes['required'] ) ? '*' : '' );
}
$required    = ! empty( $attributes['required'] );
$help_text   = isset( $attributes['helpText'] ) && is_string( $attributes['helpText'] ) ? $attributes['helpText'] : '';
$field_name  = isset( $attributes['fieldName'] ) && is_string( $attributes['fieldName'] ) ? $attributes['fieldName'] : '';

if ( '' === $field_name || '' === $form_id ) {
	return;
}

$value     = \Flinkform\Submissions\Handler::flash_value( $field_name );
$error     = \Flinkform\Submissions\Handler::flash_error( $field_name );
$field_uid = 'flinkform-field-' . md5( $form_id . '-' . $field_name );
$help_id   = $help_text ? $field_uid . '-help' : '';
$error_id  = $error ? $field_uid . '-error' : '';
$described = trim( $help_id . ' ' . $error_id );
?>
<div class="flinkform-field flinkform-field--phone<?php echo $error ? ' flinkform-field--has-error' : ''; ?><?php echo ! empty( $attributes['fullWidth'] ) ? ' flinkform-field--full-width' : ''; ?>"<?php echo \Flinkform\Conditions\Wrapper::data_attribute( $attributes['conditionalLogic'] ?? [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- data_attribute() returns an esc_attr()-escaped attribute string. ?> data-flinkform-field-name="<?php echo esc_attr( $field_name ); ?>">
	<label class="flinkform-field__label" for="<?php echo esc_attr( $field_uid ); ?>">
		<?php echo esc_html( $label ); ?>
		<?php if ( $required ) : ?>
			<span class="flinkform-field__required" aria-hidden="true"> *</span>
		<?php endif; ?>
	</label>
	<input
		type="tel"
		id="<?php echo esc_attr( $field_uid ); ?>"
		name="flinkform_field[<?php echo esc_attr( $field_name ); ?>]"
		class="flinkform-field__input"
		value="<?php echo esc_attr( (string) $value ); ?>"
		placeholder="<?php echo esc_attr( $placeholder ); ?>"
		autocomplete="tel" inputmode="tel"
		<?php echo $required ? 'required aria-required="true"' : ''; ?>
		<?php echo $described ? 'aria-describedby="' . esc_attr( $described ) . '"' : ''; ?>
		<?php echo $error ? 'aria-invalid="true"' : ''; ?>
	/>
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
