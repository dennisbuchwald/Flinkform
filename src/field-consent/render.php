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
 * The consent text supports a `{privacy_policy}` placeholder that is replaced
 * with an inline link to the site's privacy-policy page (Settings → Privacy).
 * When no privacy page is set the placeholder is replaced with the plain label.
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

// Legacy default detection: recognise both the old and new English defaults
// so render-time i18n keeps working for forms saved before the text change.
$old_default = 'I consent to the processing of my data as described in the privacy policy.';
$new_default = 'I agree to the processing of my personal data for the purpose of contact in accordance with the {privacy_policy}.';

$consent_txt_raw = isset( $attributes['consentText'] ) && is_string( $attributes['consentText'] ) ? $attributes['consentText'] : '';
if ( '' === $consent_txt_raw || $old_default === $consent_txt_raw || $new_default === $consent_txt_raw ) {
	$consent_txt = __( 'I agree to the processing of my personal data for the purpose of contact in accordance with the {privacy_policy}.', 'flinkform' );
} else {
	$consent_txt = $consent_txt_raw;
}

// Backwards compat: old attribute may still exist on saved blocks.
$link_pp = true;
if ( isset( $attributes['linkPrivacyPolicy'] ) ) {
	$link_pp = ! empty( $attributes['linkPrivacyPolicy'] );
}

$field_name  = isset( $attributes['fieldName'] ) && is_string( $attributes['fieldName'] ) ? $attributes['fieldName'] : '';

if ( '' === $field_name || '' === $form_id ) {
	return;
}

$value      = \Flinkform\Submissions\Handler::flash_value( $field_name );
$is_checked = '' !== (string) ( is_array( $value ) ? reset( $value ) : $value );

$error    = \Flinkform\Submissions\Handler::flash_error( $field_name );
$uid      = 'flinkform-field-' . md5( $form_id . '-' . $field_name );
$error_id = $error ? $uid . '-error' : '';

$privacy_url   = $link_pp ? (string) get_privacy_policy_url() : '';
$privacy_label = __( 'privacy policy', 'flinkform' );

// Replace the {privacy_policy} placeholder with either a link or the plain label.
if ( str_contains( $consent_txt, '{privacy_policy}' ) ) {
	if ( '' !== $privacy_url ) {
		$link_html = '<a href="' . esc_url( $privacy_url ) . '" target="_blank" rel="noopener noreferrer">'
			. esc_html( $privacy_label )
			. '<span class="flinkform-sr-only"> ' . esc_html__( '(opens in a new tab)', 'flinkform' ) . '</span>'
			. '</a>';
	} else {
		$link_html = esc_html( $privacy_label );
	}
	// Split text at placeholder, escape each part, then join with the link HTML.
	$parts      = explode( '{privacy_policy}', $consent_txt, 2 );
	$label_html = esc_html( $parts[0] ) . $link_html . esc_html( $parts[1] ?? '' );
} else {
	// No placeholder — render as plain text (legacy or custom text).
	$label_html = esc_html( $consent_txt );
}
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
			<?php echo $label_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $label_html is assembled from esc_html() parts and a single <a> tag built with esc_url() + esc_html(). ?>
			<span class="flinkform-field__required" aria-hidden="true"> *</span>
		</span>
	</label>
	<?php if ( $error ) : ?>
		<p class="flinkform-field__error" id="<?php echo esc_attr( $error_id ); ?>" role="alert">
			<?php echo esc_html( $error ); ?>
		</p>
	<?php endif; ?>
</div>
