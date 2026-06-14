<?php
/**
 * Server-side render for the Consent Field block.
 *
 * A single required checkbox documenting the visitor's consent (GDPR Art.
 * 6(1)(a)). Always required — a form cannot be submitted without it — and it
 * optionally links to the site's privacy policy. The field is recorded in the
 * submission with the canonical type `consent` (see Forms\Locator) so consent
 * is identifiable in exports and data-subject requests.
 *
 * @var array<string, mixed> $attributes
 * @var WP_Block             $block
 *
 * @package Flinkform
 * @since 0.2.7
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$form_id     = isset( $block->context['flinkform/formId'] ) ? (string) $block->context['flinkform/formId'] : '';
$consent_txt = isset( $attributes['consentText'] ) && is_string( $attributes['consentText'] ) ? $attributes['consentText'] : '';
$link_pp     = ! empty( $attributes['linkPrivacyPolicy'] );
$field_name  = isset( $attributes['fieldName'] ) && is_string( $attributes['fieldName'] ) ? $attributes['fieldName'] : '';

if ( '' === $field_name || '' === $form_id ) {
	return;
}

$value      = \Flinkform\Submissions\Handler::flash_value( $field_name );
$is_checked = '' !== (string) ( is_array( $value ) ? reset( $value ) : $value );

$error    = \Flinkform\Submissions\Handler::flash_error( $field_name );
$uid      = 'flinkform-field-' . md5( $form_id . '-' . $field_name );
$error_id = $error ? $uid . '-error' : '';

$privacy_url = $link_pp ? (string) get_privacy_policy_url() : '';
?>
<div
	class="flinkform-field flinkform-field--consent<?php echo $error ? ' flinkform-field--has-error' : ''; ?><?php echo ! empty( $attributes['fullWidth'] ) ? ' flinkform-field--full-width' : ''; ?>"
	<?php $flinkform_condition = \Flinkform\Conditions\Wrapper::condition_value( $attributes['conditionalLogic'] ?? [] ); echo $flinkform_condition ? ' data-flinkform-condition="' . esc_attr( $flinkform_condition ) . '"' : ''; ?>
	data-flinkform-field-name="<?php echo esc_attr( $field_name ); ?>"
>
	<label class="flinkform-field__option flinkform-field__consent-label" for="<?php echo esc_attr( $uid ); ?>">
		<input
			type="checkbox"
			id="<?php echo esc_attr( $uid ); ?>"
			name="flinkform_field[<?php echo esc_attr( $field_name ); ?>]"
			value="1"
			required
			aria-required="true"
			<?php checked( $is_checked ); ?>
			<?php echo $error ? 'aria-invalid="true" aria-describedby="' . esc_attr( $error_id ) . '"' : ''; ?>
		/>
		<span>
			<?php echo esc_html( $consent_txt ); ?>
			<span class="flinkform-field__required" aria-hidden="true"> *</span>
			<?php if ( '' !== $privacy_url ) : ?>
				<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Privacy Policy', 'flinkform' ); ?>
					<span class="flinkform-sr-only"><?php esc_html_e( '(opens in a new tab)', 'flinkform' ); ?></span>
				</a>
			<?php endif; ?>
		</span>
	</label>
	<?php if ( $error ) : ?>
		<p class="flinkform-field__error" id="<?php echo esc_attr( $error_id ); ?>" role="alert">
			<?php echo esc_html( $error ); ?>
		</p>
	<?php endif; ?>
</div>
