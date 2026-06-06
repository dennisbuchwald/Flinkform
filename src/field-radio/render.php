<?php
/**
 * Server-side render for the Radio Group Field block.
 *
 * @var array<string, mixed> $attributes
 * @var WP_Block             $block
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$form_id    = isset( $block->context['perform/formId'] ) ? (string) $block->context['perform/formId'] : '';
$label      = isset( $attributes['label'] ) && is_string( $attributes['label'] ) ? $attributes['label'] : '';
$required   = ! empty( $attributes['required'] );
$help_text  = isset( $attributes['helpText'] ) && is_string( $attributes['helpText'] ) ? $attributes['helpText'] : '';
$field_name = isset( $attributes['fieldName'] ) && is_string( $attributes['fieldName'] ) ? $attributes['fieldName'] : '';
$options    = isset( $attributes['options'] ) && is_array( $attributes['options'] ) ? $attributes['options'] : [];

if ( '' === $field_name || '' === $form_id ) {
	return;
}

$value     = (string) \PerForm\Submissions\Handler::flash_value( $field_name );
$error     = \PerForm\Submissions\Handler::flash_error( $field_name );
$group_uid = 'perform-field-' . md5( $form_id . '-' . $field_name );
$help_id   = $help_text ? $group_uid . '-help' : '';
$error_id  = $error ? $group_uid . '-error' : '';
$described = trim( $help_id . ' ' . $error_id );
?>
<fieldset
	class="perform-field perform-field--radio<?php echo $error ? ' perform-field--has-error' : ''; ?><?php echo ! empty( $attributes['fullWidth'] ) ? ' perform-field--full-width' : ''; ?>"
	<?php echo $described ? 'aria-describedby="' . esc_attr( $described ) . '"' : ''; ?>
	<?php echo $error ? 'aria-invalid="true"' : ''; ?>
	<?php echo \PerForm\Conditions\Wrapper::data_attribute( $attributes['conditionalLogic'] ?? [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- data_attribute() returns an esc_attr()-escaped attribute string. ?>
	data-perform-field-name="<?php echo esc_attr( $field_name ); ?>"
>
	<legend class="perform-field__label">
		<?php echo esc_html( $label ); ?>
		<?php if ( $required ) : ?>
			<span class="perform-field__required" aria-hidden="true"> *</span>
		<?php endif; ?>
	</legend>
	<?php foreach ( $options as $i => $opt ) : ?>
		<?php
		$opt_value = isset( $opt['value'] ) ? (string) $opt['value'] : '';
		$opt_label = isset( $opt['label'] ) && '' !== $opt['label'] ? (string) $opt['label'] : $opt_value;
		$opt_uid   = $group_uid . '-' . $i;
		?>
		<label class="perform-field__option" for="<?php echo esc_attr( $opt_uid ); ?>">
			<input
				type="radio"
				id="<?php echo esc_attr( $opt_uid ); ?>"
				name="perffo_field[<?php echo esc_attr( $field_name ); ?>]"
				value="<?php echo esc_attr( $opt_value ); ?>"
				<?php checked( $value, $opt_value ); ?>
				<?php echo $required ? 'required aria-required="true"' : ''; ?>
			/>
			<span><?php echo esc_html( $opt_label ); ?></span>
		</label>
	<?php endforeach; ?>
	<?php if ( $help_text ) : ?>
		<p class="perform-field__help" id="<?php echo esc_attr( $help_id ); ?>">
			<?php echo esc_html( $help_text ); ?>
		</p>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<p class="perform-field__error" id="<?php echo esc_attr( $error_id ); ?>" role="alert">
			<?php echo esc_html( $error ); ?>
		</p>
	<?php endif; ?>
</fieldset>
