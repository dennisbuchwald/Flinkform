<?php
/**
 * Server-side render for the Radio Group Field block.
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
$label      = isset( $attributes['label'] ) && is_string( $attributes['label'] ) ? $attributes['label'] : '';
$required   = ! empty( $attributes['required'] );
$help_text  = isset( $attributes['helpText'] ) && is_string( $attributes['helpText'] ) ? $attributes['helpText'] : '';
$field_name = isset( $attributes['fieldName'] ) && is_string( $attributes['fieldName'] ) ? $attributes['fieldName'] : '';
$options    = isset( $attributes['options'] ) && is_array( $attributes['options'] ) ? $attributes['options'] : [];

if ( '' === $field_name || '' === $form_id ) {
	return;
}

$value     = (string) \Flinkform\Submissions\Handler::flash_value( $field_name );
$error     = \Flinkform\Submissions\Handler::flash_error( $field_name );
$group_uid = 'flinkform-field-' . md5( $form_id . '-' . $field_name );
$help_id   = $help_text ? $group_uid . '-help' : '';
$error_id  = $error ? $group_uid . '-error' : '';
$described = trim( $help_id . ' ' . $error_id );
?>
<fieldset
	class="flinkform-field flinkform-field--radio<?php echo $error ? ' flinkform-field--has-error' : ''; ?><?php echo ! empty( $attributes['fullWidth'] ) ? ' flinkform-field--full-width' : ''; ?>"
	<?php echo $described ? 'aria-describedby="' . esc_attr( $described ) . '"' : ''; ?>
	<?php echo $error ? 'aria-invalid="true"' : ''; ?>
	<?php echo $required ? 'aria-required="true"' : ''; ?>
	<?php $flinkform_condition = \Flinkform\Conditions\Wrapper::condition_value( $attributes['conditionalLogic'] ?? [] ); echo $flinkform_condition ? ' data-flinkform-condition="' . esc_attr( $flinkform_condition ) . '"' : ''; ?>
	data-flinkform-field-name="<?php echo esc_attr( $field_name ); ?>"
>
	<legend class="flinkform-field__label">
		<?php echo esc_html( $label ); ?>
		<?php if ( $required ) : ?>
			<span class="flinkform-field__required" aria-hidden="true"> *</span>
		<?php endif; ?>
	</legend>
	<?php foreach ( $options as $i => $opt ) : ?>
		<?php
		$opt_value = isset( $opt['value'] ) ? (string) $opt['value'] : '';
		$opt_label = isset( $opt['label'] ) && '' !== $opt['label'] ? (string) $opt['label'] : $opt_value;
		$opt_uid   = $group_uid . '-' . $i;
		?>
		<label class="flinkform-field__option" for="<?php echo esc_attr( $opt_uid ); ?>">
			<input
				type="radio"
				id="<?php echo esc_attr( $opt_uid ); ?>"
				name="flinkform_field[<?php echo esc_attr( $field_name ); ?>]"
				value="<?php echo esc_attr( $opt_value ); ?>"
				<?php checked( $value, $opt_value ); ?>
				<?php echo $required ? 'required aria-required="true"' : ''; ?>
			/>
			<span><?php echo esc_html( $opt_label ); ?></span>
		</label>
	<?php endforeach; ?>
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
</fieldset>
