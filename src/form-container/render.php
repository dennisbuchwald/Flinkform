<?php
/**
 * Server-side render for the Form Container block.
 *
 * Emits a `<form>` posting to `admin-post.php?action=perform_submit`, with a
 * nonce, the form's stable UUID, the source post ID (used by the handler
 * to locate the original block markup for validation), a honeypot field and
 * a timestamp token.
 *
 * Available variables (provided by WordPress):
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Inner blocks rendered to HTML.
 * @var WP_Block             $block      Block instance.
 *
 * Output contract: this file ECHOES its markup directly. WordPress wraps
 * the require() in its own ob_start()/ob_get_clean() — do NOT add another
 * output buffer here, or the output ends up in the wrong buffer and the
 * frontend renders empty.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$form_id        = isset( $attributes['formId'] ) && is_string( $attributes['formId'] ) ? $attributes['formId'] : '';
$submit_label   = isset( $attributes['submitLabel'] ) && is_string( $attributes['submitLabel'] ) ? $attributes['submitLabel'] : __( 'Send', 'perform-forms' );
$success_msg    = isset( $attributes['successMessage'] ) && is_string( $attributes['successMessage'] ) ? $attributes['successMessage'] : __( 'Thank you!', 'perform-forms' );
$source_post_id = (int) get_the_ID();

// Appearance overrides (Style panel). Unset values leave the SCSS-level
// defaults intact, which fall back through theme.json before bottoming
// out at the hard-coded values — see style.scss for the full cascade.
$appearance     = isset( $attributes['appearance'] ) && is_array( $attributes['appearance'] ) ? $attributes['appearance'] : [];

/**
 * Whitelist a CSS-colour string to a safe character set before echoing it
 * into an inline style attribute. Hex, rgb/rgba, hsl/hsla, named colours
 * and CSS var() references all pass; semicolons, braces, quotes, angle
 * brackets are stripped. Returns an empty string when nothing valid is left.
 *
 * @param string $color Raw colour from the block attribute.
 * @return string
 */
$perform_sanitize_color = static function ( string $color ): string {
	$trimmed = trim( $color );
	if ( '' === $trimmed ) {
		return '';
	}
	// Allow letters, digits, #, parens, commas, percent, dots, spaces, dashes.
	if ( ! preg_match( '/^[a-zA-Z0-9_#(), .%\-]+$/', $trimmed ) ) {
		return '';
	}
	return $trimmed;
};

$primary_color    = isset( $appearance['primaryColor'] ) && is_string( $appearance['primaryColor'] )
	? $perform_sanitize_color( $appearance['primaryColor'] )
	: '';
$submit_btn_style = isset( $appearance['submitButtonStyle'] ) && is_string( $appearance['submitButtonStyle'] ) ? $appearance['submitButtonStyle'] : 'fill';
if ( ! in_array( $submit_btn_style, [ 'fill', 'outline', 'ghost' ], true ) ) {
	$submit_btn_style = 'fill';
}

$field_style = isset( $appearance['fieldStyle'] ) && is_string( $appearance['fieldStyle'] ) ? $appearance['fieldStyle'] : 'bordered';
if ( ! in_array( $field_style, [ 'bordered', 'underline', 'minimal' ], true ) ) {
	$field_style = 'bordered';
}

$field_spacing = isset( $appearance['fieldSpacing'] ) && is_string( $appearance['fieldSpacing'] ) ? $appearance['fieldSpacing'] : 'normal';
if ( ! in_array( $field_spacing, [ 'compact', 'normal', 'relaxed' ], true ) ) {
	$field_spacing = 'normal';
}

$label_position = isset( $appearance['labelPosition'] ) && is_string( $appearance['labelPosition'] ) ? $appearance['labelPosition'] : 'above';
if ( ! in_array( $label_position, [ 'above', 'beside', 'floating' ], true ) ) {
	$label_position = 'above';
}

// Border radius — only a non-negative integer in a sane range counts as
// a real override. Everything else (null, string, negative) falls back
// to the SCSS-level default.
$border_radius_px = null;
if ( isset( $appearance['borderRadius'] ) && is_numeric( $appearance['borderRadius'] ) ) {
	$radius = (int) $appearance['borderRadius'];
	if ( $radius >= 0 && $radius <= 64 ) {
		$border_radius_px = $radius;
	}
}

// Without a stable form ID we cannot save or validate — render nothing.
if ( '' === $form_id ) {
	return;
}

// Success state: a successful submission redirects back with this query arg
// targeting this specific form (UUID), so multiple forms on one page don't
// all flip to success after one submits.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$status     = isset( $_GET['perform_status'] ) ? sanitize_key( wp_unslash( $_GET['perform_status'] ) ) : '';
$status_for = isset( $_GET['perform_form'] ) ? sanitize_text_field( wp_unslash( $_GET['perform_form'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$is_success = ( 'success' === $status && $status_for === $form_id );

$wrapper_classes = [
	'perform-form',
	'perform-form--button-' . $submit_btn_style,
	'perform-form--field-style-' . $field_style,
	'perform-form--spacing-' . $field_spacing,
	'perform-form--labels-' . $label_position,
];

$inline_style_parts = [];
if ( '' !== $primary_color ) {
	$inline_style_parts[] = '--perform-color-primary:' . $primary_color;
}
if ( null !== $border_radius_px ) {
	$inline_style_parts[] = '--perform-border-radius:' . $border_radius_px . 'px';
}

$wrapper_args = [
	'class'           => implode( ' ', $wrapper_classes ),
	'data-perform-id' => $form_id,
];
if ( ! empty( $inline_style_parts ) ) {
	$wrapper_args['style'] = implode( ';', $inline_style_parts ) . ';';
}
$wrapper_attrs = get_block_wrapper_attributes( $wrapper_args );

if ( $is_success ) :
	?>
	<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="perform-form__success" role="status" aria-live="polite">
			<?php echo esc_html( $success_msg ); ?>
		</div>
	</div>
	<?php
	return;
endif;

// Re-populate field values + errors from the previous failed attempt. The
// flash state is consumed here (transient deleted) and stashed in static
// state on the Handler so field renders can read it without re-fetching.
$errors = \PerForm\Submissions\Handler::flash_errors_for( $form_id );
$values = \PerForm\Submissions\Handler::flash_values_for( $form_id );
\PerForm\Submissions\Handler::set_render_state( $form_id, $errors, $values );

// Re-render inner blocks AFTER setting render state — $content was rendered
// upstream before our flash state was available, so we redo it here.
$inner_html = '';
foreach ( $block->inner_blocks as $inner ) {
	$inner_html .= $inner->render();
}

// Timestamp token — base64 of `time()`, validated server-side on submit to
// catch bots that submit a form within microseconds of rendering it.
$timestamp_token = base64_encode( (string) time() );
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<form
		class="perform-form__form"
		method="post"
		action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		novalidate
	>
		<?php wp_nonce_field( 'perform_submit_' . $form_id, '_perform_nonce' ); ?>
		<input type="hidden" name="action" value="perform_submit" />
		<input type="hidden" name="perform_form_id" value="<?php echo esc_attr( $form_id ); ?>" />
		<input type="hidden" name="perform_post_id" value="<?php echo esc_attr( (string) $source_post_id ); ?>" />
		<input type="hidden" name="perform_ts" value="<?php echo esc_attr( $timestamp_token ); ?>" />

		<?php // Honeypot — visually + AT-hidden, bots will fill it. ?>
		<div class="perform-form__hp" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
			<label>
				<?php esc_html_e( 'Leave this field empty', 'perform-forms' ); ?>
				<input type="text" name="perform_hp" value="" tabindex="-1" autocomplete="off" />
			</label>
		</div>

		<?php if ( ! empty( $errors['_form'] ) ) : ?>
			<div class="perform-form__error perform-form__error--global" role="alert">
				<?php echo esc_html( $errors['_form'] ); ?>
			</div>
		<?php endif; ?>

		<?php echo $inner_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks output, fields escape themselves. ?>

		<div class="perform-form__actions">
			<button type="submit" class="perform-form__submit">
				<?php echo esc_html( $submit_label ); ?>
			</button>
		</div>
	</form>
</div>
<?php
\PerForm\Submissions\Handler::clear_render_state();
