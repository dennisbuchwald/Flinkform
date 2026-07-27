<?php
/**
 * Server-side render for the Address Field block.
 *
 * Composite field that expands into separate sub-inputs: street,
 * (optional) address line 2, postal code, city, (optional) country.
 * Each sub-input uses its own name key (fieldName_street, fieldName_zip,
 * etc.) so the Handler treats them as individual text fields.
 *
 * Each sub-field is rendered as a full `.flinkform-field .flinkform-field--text`
 * wrapper so it inherits ALL form styles (floating labels, field styles,
 * error states, spacing). The outer wrapper provides the grid layout.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 * @var WP_Block             $block
 *
 * @package Flinkform
 * @since 1.6.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$form_id            = isset( $block->context['flinkform/formId'] ) ? (string) $block->context['flinkform/formId'] : '';
$label              = isset( $attributes['label'] ) && is_string( $attributes['label'] ) ? $attributes['label'] : '';
$required           = ! empty( $attributes['required'] );
$help_text          = isset( $attributes['helpText'] ) && is_string( $attributes['helpText'] ) ? $attributes['helpText'] : '';
$field_name         = isset( $attributes['fieldName'] ) && is_string( $attributes['fieldName'] ) ? $attributes['fieldName'] : '';
$show_country       = ! empty( $attributes['showCountry'] );
$show_line2         = ! empty( $attributes['showAddressLine2'] );
$country_default    = isset( $attributes['countryDefault'] ) && is_string( $attributes['countryDefault'] ) ? $attributes['countryDefault'] : '';

if ( '' === $field_name || '' === $form_id ) {
	return;
}

// Sub-field definitions: key suffix, label, placeholder, full-row flag, visibility.
$sub_fields = [
	[ 'key' => 'street',  'label' => __( 'Street', 'flinkform' ),         'placeholder' => __( 'Street + house number', 'flinkform' ), 'full' => true,  'show' => true ],
	[ 'key' => 'line2',   'label' => __( 'Address line 2', 'flinkform' ), 'placeholder' => __( 'Apartment, suite, floor etc.', 'flinkform' ), 'full' => true, 'show' => $show_line2 ],
	[ 'key' => 'zip',     'label' => __( 'Postal code', 'flinkform' ),    'placeholder' => __( 'Postal code', 'flinkform' ),           'full' => false, 'show' => true ],
	[ 'key' => 'city',    'label' => __( 'City', 'flinkform' ),           'placeholder' => __( 'City', 'flinkform' ),                  'full' => false, 'show' => true ],
	[ 'key' => 'country', 'label' => __( 'Country', 'flinkform' ),        'placeholder' => __( 'Country', 'flinkform' ),               'full' => true,  'show' => $show_country ],
];

$help_id = $help_text ? 'flinkform-field-' . md5( $form_id . '-' . $field_name ) . '-help' : '';
?>
<fieldset class="flinkform-field flinkform-field--address<?php echo ! empty( $attributes['fullWidth'] ) ? ' flinkform-field--full-width' : ''; ?>"<?php $flinkform_condition = \Flinkform\Conditions\Wrapper::condition_value( $attributes['conditionalLogic'] ?? [] ); echo $flinkform_condition ? ' data-flinkform-condition="' . esc_attr( $flinkform_condition ) . '"' : ''; ?> data-flinkform-field-name="<?php echo esc_attr( $field_name ); ?>">
	<legend class="flinkform-field-address__legend">
		<?php echo esc_html( $label ); ?>
		<?php if ( $required ) : ?>
			<span class="flinkform-field__required" aria-hidden="true"> *</span>
		<?php endif; ?>
	</legend>
	<div class="flinkform-field-address__grid">
		<?php foreach ( $sub_fields as $sf ) : ?>
			<?php
			if ( ! $sf['show'] ) {
				continue;
			}
			$sub_name  = $field_name . '_' . $sf['key'];
			$sub_uid   = 'flinkform-field-' . md5( $form_id . '-' . $sub_name );
			$sub_value = \Flinkform\Submissions\Handler::flash_value( $sub_name );
			$sub_error = \Flinkform\Submissions\Handler::flash_error( $sub_name );

			// Country gets a default value when no flash value exists.
			if ( 'country' === $sf['key'] && '' === (string) $sub_value && '' !== $country_default ) {
				$sub_value = $country_default;
			}

			// Line 2 is never required even when the address is required.
			$sub_required = $required && 'line2' !== $sf['key'];
			$grid_class   = 'flinkform-field flinkform-field--text';
			$grid_class  .= $sf['full'] ? ' flinkform-field-address__sub--full' : '';
			$grid_class  .= $sub_error ? ' flinkform-field--has-error' : '';

			$sub_error_id = $sub_error ? $sub_uid . '-error' : '';
			$described    = trim( ( $help_id ? $help_id . ' ' : '' ) . $sub_error_id );
			?>
			<div class="<?php echo esc_attr( $grid_class ); ?>">
				<label class="flinkform-field__label" for="<?php echo esc_attr( $sub_uid ); ?>">
					<?php echo esc_html( $sf['label'] ); ?>
					<?php if ( $sub_required ) : ?>
						<span class="flinkform-field__required" aria-hidden="true"> *</span>
					<?php endif; ?>
				</label>
				<input
					type="text"
					id="<?php echo esc_attr( $sub_uid ); ?>"
					name="flinkform_field[<?php echo esc_attr( $sub_name ); ?>]"
					class="flinkform-field__input"
					value="<?php echo esc_attr( (string) $sub_value ); ?>"
					placeholder="<?php echo esc_attr( $sf['placeholder'] ); ?>"
					<?php echo $sub_required ? 'required aria-required="true"' : ''; ?>
					<?php echo $described ? 'aria-describedby="' . esc_attr( $described ) . '"' : ''; ?>
					<?php echo $sub_error ? 'aria-invalid="true"' : ''; ?>
				/>
				<?php if ( $sub_error ) : ?>
					<p class="flinkform-field__error" id="<?php echo esc_attr( $sub_error_id ); ?>" role="alert">
						<?php echo esc_html( $sub_error ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php if ( $help_text ) : ?>
		<p class="flinkform-field__help" id="<?php echo esc_attr( $help_id ); ?>">
			<?php echo esc_html( $help_text ); ?>
		</p>
	<?php endif; ?>
</fieldset>
