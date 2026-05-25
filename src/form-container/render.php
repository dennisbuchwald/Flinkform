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

$columns = isset( $appearance['columns'] ) && (int) $appearance['columns'] === 2 ? 2 : 1;

// Count page-break markers in the inner-block tree — drives the
// multi-step wrapper class and decides whether the inner-block render
// loop below splits the stream into steps or emits a flat field list.
$page_break_count = 0;
foreach ( $block->inner_blocks as $perform_inner_probe ) {
	if ( 'perform/page-break' === $perform_inner_probe->name ) {
		$page_break_count++;
	}
}
$is_multi_step = $page_break_count > 0;

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

/**
 * Sanitise an author-supplied Custom CSS string before it lands in a
 * <style> block on the page.
 *
 * Inspector access is gated by the `edit_posts` capability, so the
 * threat model is "trusted user", not "drive-by attacker" — but we
 * still defend in depth:
 *
 *   1. wp_strip_all_tags() — removes any <script> / <style> blocks
 *      the author tries to embed (also strips raw HTML tags, which
 *      have no business in a CSS textarea).
 *   2. preg_replace() of the three legacy IE vectors that survive
 *      strip_all_tags:
 *        - expression(…)   — old IE-only CSS expression evaluator
 *        - behavior:       — IE-only binding to an HTC behaviour file
 *        - javascript:     — only meaningful in url() values; tiny
 *                            attack surface but cheap to neutralise
 *      All three are dead in every supported browser today (the
 *      plugin requires WP 7.0 + PHP 8.1, which target evergreen
 *      browsers anyway) but neutralising them costs us nothing.
 *
 * @param string $css Raw CSS from the block attribute.
 * @return string Sanitised CSS, safe to echo into <style>…</style>.
 */
$perform_sanitize_custom_css = static function ( string $css ): string {
	$css = wp_strip_all_tags( $css );
	$css = (string) preg_replace( '/expression\s*\(/i', '', $css );
	$css = (string) preg_replace( '/behavior\s*:/i', '', $css );
	$css = (string) preg_replace( '/javascript\s*:/i', '', $css );
	return trim( $css );
};

$custom_css = isset( $attributes['customCSS'] ) && is_string( $attributes['customCSS'] )
	? $perform_sanitize_custom_css( $attributes['customCSS'] )
	: '';

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
	'perform-form--columns-' . $columns,
];
// Multi-step modifier — added only when the form actually renders fields
// (skipped on the success branch, which replaces the form with a single
// status message and has no steps to lay out). The class drives the
// step-aware CSS overrides further down style.scss and will be the hook
// Slice 5b's Interactivity-API script binds to.
if ( $is_multi_step && ! $is_success ) {
	$wrapper_classes[] = 'perform-form--multi-step';
}

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
// Interactivity API wiring — only emitted on multi-step renders.
// The success branch deliberately skips this (no form, nothing to
// drive). With JavaScript disabled these attributes sit dormant in
// the HTML and the form falls back to its single-page rendering —
// every step visible, every required field server-validated as in
// 5a, see the progressive-enhancement notes in view.js.
$step_count = $page_break_count + 1;
if ( $is_multi_step && ! $is_success ) {
	$wrapper_args['data-wp-interactive'] = 'perform/form';
	$wrapper_args['data-wp-context']     = (string) wp_json_encode(
		[
			'currentStep' => 0,
			'totalSteps'  => $step_count,
		]
	);
	$wrapper_args['data-wp-init'] = 'callbacks.markEnhanced';
}
$wrapper_attrs = get_block_wrapper_attributes( $wrapper_args );

// Custom CSS — same <style> block goes out in both the success view and
// the normal form render so author rules survive past submission. ID is
// derived from the form UUID so multiple forms on one page don't clash
// on the element id and a single rule-set is reused if the page caches.
$custom_css_block = '';
if ( '' !== $custom_css ) {
	$custom_css_block = '<style id="perform-custom-css-' . esc_attr( $form_id ) . '">' . $custom_css . '</style>';
}

if ( $is_success ) :
	echo $custom_css_block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised above.
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
//
// While iterating we also split the inner-block stream at every
// `perform/page-break` marker into separate steps. A form with zero
// page-breaks produces a single step ($steps has length 1) and we emit
// the inner HTML directly with no extra wrapper — backwards-compatible
// with every existing PerForm in the wild and with any author CSS that
// targets fields as direct children of `.perform-form__form`.
//
// A form with at least one page-break enters multi-step mode: each step's
// inner HTML is wrapped in a `<div class="perform-form__step"
// data-step-index="N" data-step-label="L">` and a visible separator is
// emitted between every adjacent pair of steps. The separator is a 5a
// affordance — it makes the server-side step splitting verifiable in the
// browser without JS — and will be removed in 5b once the Interactivity
// API hides non-current steps and drives Next/Back navigation.
$steps = [
	[
		'label' => '',
		'html'  => '',
	],
];
foreach ( $block->inner_blocks as $inner ) {
	if ( 'perform/page-break' === $inner->name ) {
		$break_label  = isset( $inner->attributes['label'] ) && is_string( $inner->attributes['label'] )
			? sanitize_text_field( $inner->attributes['label'] )
			: '';
		$steps[]      = [
			'label' => $break_label,
			'html'  => '',
		];
		continue;
	}

	$last_index                     = count( $steps ) - 1;
	$steps[ $last_index ]['html']  .= $inner->render();
}

$step_count = count( $steps );

if ( 1 === $step_count ) {
	$inner_html = $steps[0]['html'];
} else {
	$step_chunks = [];
	foreach ( $steps as $step_index => $step_data ) {
		// Per-step nested context — merges with the wrapper's
		// {currentStep, totalSteps} so state.isCurrentStep can pick the
		// right step out by comparing stepIndex to currentStep. The
		// data-wp-bind--hidden binding then either adds or removes the
		// `hidden` attribute on hydration; the server-side render
		// emits every step unhidden so the form keeps working with
		// JavaScript disabled.
		$step_context = (string) wp_json_encode( [ 'stepIndex' => $step_index ] );
		$step_attr    = sprintf(
			'class="perform-form__step" data-step-index="%d" data-wp-context="%s" data-wp-bind--hidden="state.isNotCurrentStep"',
			$step_index,
			esc_attr( $step_context )
		);
		if ( '' !== $step_data['label'] ) {
			$step_attr .= ' data-step-label="' . esc_attr( $step_data['label'] ) . '"';
		}
		$step_chunks[ $step_index ] = '<div ' . $step_attr . '>' . $step_data['html'] . '</div>';
	}

	$inner_html = $step_chunks[0];
	for ( $i = 1; $i < $step_count; $i++ ) {
		$next_label   = $steps[ $i ]['label'];
		$next_number  = $i + 1;
		if ( '' !== $next_label ) {
			/* translators: 1: step number, 2: author-supplied step label */
			$separator_text = sprintf( __( 'Step %1$d — %2$s', 'perform-forms' ), $next_number, $next_label );
		} else {
			/* translators: %d: step number */
			$separator_text = sprintf( __( 'Step %d', 'perform-forms' ), $next_number );
		}

		// The separator only makes sense when every step is visible at
		// once (the JS-disabled fallback). data-wp-bind--hidden bound
		// to a constant-true getter fires as soon as Interactivity
		// hydrates, removing the separator from the layout. Without
		// JS the binding never runs and it stays as the 5a divider.
		$inner_html .= '<div class="perform-form__step-separator" aria-hidden="true" data-wp-bind--hidden="state.alwaysTrue">'
			. '<span class="perform-form__step-separator-rule"></span>'
			. '<span class="perform-form__step-separator-label">' . esc_html( $separator_text ) . '</span>'
			. '<span class="perform-form__step-separator-rule"></span>'
			. '</div>';
		$inner_html .= $step_chunks[ $i ];
	}
}

// Timestamp token — base64 of `time()`, validated server-side on submit to
// catch bots that submit a form within microseconds of rendering it.
$timestamp_token = base64_encode( (string) time() );
?>
<?php echo $custom_css_block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised above. ?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<form
		class="perform-form__form"
		method="post"
		action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		novalidate
		<?php if ( $is_multi_step ) : ?>
			data-wp-on--submit="actions.submitGuard"
		<?php endif; ?>
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
			<?php if ( $is_multi_step ) : ?>
				<?php
				// Navigation buttons — emitted only in multi-step mode.
				// Server-side initial state matches the JS-on Step 1 layout:
				// Back is hidden (no previous step to go to), Next is visible.
				// Without JS the Next/Back buttons stay in their server state
				// (Back hidden, Next visible) and never fire — fine, because
				// every step is visible at once and the user can submit on
				// any step they're on. With JS the data-wp-bind--hidden
				// expressions take over and toggle visibility per step.
				?>
				<button
					type="button"
					class="perform-form__nav perform-form__nav--back"
					data-wp-on--click="actions.prevStep"
					data-wp-bind--hidden="state.isFirstStep"
					hidden
				>
					<?php esc_html_e( 'Back', 'perform-forms' ); ?>
				</button>
				<button
					type="button"
					class="perform-form__submit perform-form__nav perform-form__nav--next"
					data-wp-on--click="actions.nextStep"
					data-wp-bind--hidden="state.isLastStep"
					hidden
				>
					<?php esc_html_e( 'Next', 'perform-forms' ); ?>
				</button>
				<button
					type="submit"
					class="perform-form__submit"
					data-wp-bind--hidden="state.isNotLastStep"
				>
					<?php echo esc_html( $submit_label ); ?>
				</button>
			<?php else : ?>
				<button type="submit" class="perform-form__submit">
					<?php echo esc_html( $submit_label ); ?>
				</button>
			<?php endif; ?>
		</div>
	</form>
</div>
<?php
\PerForm\Submissions\Handler::clear_render_state();
