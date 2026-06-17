<?php
/**
 * Server-side render for the Form Container block.
 *
 * Emits a `<form>` posting to `admin-post.php?action=flinkform_submit`, with a
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
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$form_id        = isset( $attributes['formId'] ) && is_string( $attributes['formId'] ) ? $attributes['formId'] : '';
// block.json attribute defaults are stored verbatim (English) in the post
// content — WordPress does not translate them. When the saved value still
// matches the English default, swap it for the translated string so forms
// created on English sites render correctly after a locale switch.
$submit_label_raw = isset( $attributes['submitLabel'] ) && is_string( $attributes['submitLabel'] ) ? $attributes['submitLabel'] : '';
$submit_label     = ( '' === $submit_label_raw || 'Send' === $submit_label_raw ) ? __( 'Send', 'flinkform' ) : $submit_label_raw;

$success_msg_raw = isset( $attributes['successMessage'] ) && is_string( $attributes['successMessage'] ) ? $attributes['successMessage'] : '';
$success_msg     = ( '' === $success_msg_raw || 'Thank you! Your message has been sent.' === $success_msg_raw )
	? __( 'Thank you! Your message has been sent.', 'flinkform' )
	: $success_msg_raw;
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
$flinkform_sanitize_color = static function ( string $color ): string {
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
	? $flinkform_sanitize_color( $appearance['primaryColor'] )
	: '';
$button_color       = isset( $appearance['buttonColor'] ) && is_string( $appearance['buttonColor'] )
	? $flinkform_sanitize_color( $appearance['buttonColor'] )
	: '';
$button_text_color  = isset( $appearance['buttonTextColor'] ) && is_string( $appearance['buttonTextColor'] )
	? $flinkform_sanitize_color( $appearance['buttonTextColor'] )
	: '';
$button_border_color = isset( $appearance['buttonBorderColor'] ) && is_string( $appearance['buttonBorderColor'] )
	? $flinkform_sanitize_color( $appearance['buttonBorderColor'] )
	: '';
$submit_btn_style = isset( $appearance['submitButtonStyle'] ) && is_string( $appearance['submitButtonStyle'] ) ? $appearance['submitButtonStyle'] : 'fill';
if ( ! in_array( $submit_btn_style, [ 'fill', 'outline', 'ghost' ], true ) ) {
	$submit_btn_style = 'fill';
}

$field_style = isset( $appearance['fieldStyle'] ) && is_string( $appearance['fieldStyle'] ) ? $appearance['fieldStyle'] : 'bordered';
if ( ! in_array( $field_style, [ 'bordered', 'soft', 'underline', 'minimal' ], true ) ) {
	$field_style = 'bordered';
}

$field_spacing = isset( $appearance['fieldSpacing'] ) && is_string( $appearance['fieldSpacing'] ) ? $appearance['fieldSpacing'] : 'normal';
if ( ! in_array( $field_spacing, [ 'compact', 'normal', 'relaxed' ], true ) ) {
	$field_spacing = 'normal';
}

$label_position = isset( $appearance['labelPosition'] ) && is_string( $appearance['labelPosition'] ) ? $appearance['labelPosition'] : 'above';
if ( ! in_array( $label_position, [ 'above', 'beside', 'floating', 'placeholder' ], true ) ) {
	$label_position = 'above';
}

$columns = isset( $appearance['columns'] ) && (int) $appearance['columns'] === 2 ? 2 : 1;

$progress_indicator = isset( $appearance['progressIndicator'] ) && is_string( $appearance['progressIndicator'] )
	? $appearance['progressIndicator']
	: 'bar';
if ( ! in_array( $progress_indicator, [ 'bar', 'dots', 'numbers', 'none' ], true ) ) {
	$progress_indicator = 'bar';
}

$show_step_labels = ! empty( $appearance['showStepLabels'] );

// Count page-break markers in the inner-block tree — drives the
// multi-step wrapper class and decides whether the inner-block render
// loop below splits the stream into steps or emits a flat field list.
// In the same pass we also collect each step's label (the label
// attribute of the page-break that opens it) so the indicator's
// optional "Step Labels" feature can hand them to the JS store
// without having to read the markup back.
$page_break_count = 0;
$step_labels      = [ '' ]; // Step 0 has no opening page-break, so no label.
foreach ( $block->inner_blocks as $flinkform_inner_probe ) {
	if ( 'flinkform/page-break' === $flinkform_inner_probe->name ) {
		$page_break_count++;
		$label_attr     = isset( $flinkform_inner_probe->attributes['label'] ) && is_string( $flinkform_inner_probe->attributes['label'] )
			? sanitize_text_field( $flinkform_inner_probe->attributes['label'] )
			: '';
		$step_labels[]  = $label_attr;
	}
}
$is_multi_step = $page_break_count > 0;
$has_any_step_label = false;
foreach ( $step_labels as $flinkform_step_label_probe ) {
	if ( '' !== $flinkform_step_label_probe ) {
		$has_any_step_label = true;
		break;
	}
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
$status     = isset( $_GET['flinkform_status'] ) ? sanitize_key( wp_unslash( $_GET['flinkform_status'] ) ) : '';
$status_for = isset( $_GET['flinkform_form'] ) ? sanitize_text_field( wp_unslash( $_GET['flinkform_form'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$is_success = ( 'success' === $status && $status_for === $form_id );

// Submit condition (Phase 7d) — block attribute → optional rule set
// that disables the Submit button until the rules match (typical
// example: "I agree to terms" checkbox). The data attribute itself
// is added to every Submit-button render below; the standalone JS
// listener picks it up and toggles `disabled` reactively. Server-
// side enforcement happens in Submissions\Handler::handle().
$submit_condition_value   = '';
$submit_condition_hint_id = '';
if ( ! $is_success ) {
	$submit_condition = isset( $attributes['submitCondition'] ) && is_array( $attributes['submitCondition'] ) ? $attributes['submitCondition'] : [];
	if ( ! empty( $submit_condition['enabled'] ) && ! empty( $submit_condition['rules'] ) ) {
		$submit_condition_value   = \Flinkform\Conditions\Wrapper::condition_value( $submit_condition );
		$submit_condition_hint_id = 'flinkform-submit-hint-' . md5( $form_id );
	}
}

$wrapper_classes = [
	'flinkform-form',
	'flinkform-form--button-' . $submit_btn_style,
	'flinkform-form--field-style-' . $field_style,
	'flinkform-form--spacing-' . $field_spacing,
	'flinkform-form--labels-' . $label_position,
	'flinkform-form--columns-' . $columns,
];
// Multi-step modifier — added only when the form actually renders fields
// (skipped on the success branch, which replaces the form with a single
// status message and has no steps to lay out). The class drives the
// step-aware CSS overrides further down style.scss and will be the hook
// Slice 5b's Interactivity-API script binds to.
if ( $is_multi_step && ! $is_success ) {
	$wrapper_classes[] = 'flinkform-form--multi-step';
}

$inline_style_parts = [];
if ( '' !== $primary_color ) {
	$inline_style_parts[] = '--flinkform-color-primary:' . $primary_color;
}
if ( null !== $border_radius_px ) {
	$inline_style_parts[] = '--flinkform-border-radius:' . $border_radius_px . 'px';
}
if ( '' !== $button_color ) {
	$inline_style_parts[] = '--flinkform-button-bg:' . $button_color;
}
if ( '' !== $button_text_color ) {
	$inline_style_parts[] = '--flinkform-button-color:' . $button_text_color;
}
if ( '' !== $button_border_color ) {
	$inline_style_parts[] = '--flinkform-button-border-color:' . $button_border_color;
}

$wrapper_args = [
	'class'           => implode( ' ', $wrapper_classes ),
	'data-flinkform-id' => $form_id,
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
	// Per-form context — every value the indicator bindings consume
	// lives here, not on the namespace state, so WP Interactivity's
	// server-side directive processing can resolve them against the
	// JSON payload in this attribute and emit the right initial
	// attribute values for JS-disabled visitors. Pure JS getters like
	// `state.alwaysTrue` (which depend on no PHP-resolvable input)
	// stay where they are and the SSR-strip on those is intentional.
	$initial_aria_now = 1;
	$initial_percent  = round( ( 1 / $step_count ) * 100, 2 );
	/* translators: 1: current step number, 2: total step count */
	$initial_label = sprintf( __( 'Step %1$s of %2$s', 'flinkform' ), '1', (string) $step_count );

	$context_payload = [
		'currentStep'      => 0,
		'totalSteps'       => $step_count,
		'ariaValueNow'     => $initial_aria_now,
		'progressBarStyle' => '--flinkform-progress-percent:' . $initial_percent . '%',
		'progressLabel'    => $initial_label,
	];
	if ( $show_step_labels && $has_any_step_label ) {
		// Initial label = the current step's label. JS later swaps it
		// from the seeded `stepLabels` array on every navigation.
		$context_payload['currentStepLabel'] = $step_labels[0];
	}

	$wrapper_args['data-wp-interactive']                  = 'flinkform/form';
	$wrapper_args['data-wp-context']                      = (string) wp_json_encode( $context_payload );
	// `flinkform-skipped-changed` is a custom event the conditional-
	// logic DOM listener dispatches on this wrapper whenever the set
	// of step-skip rules changes which steps are skipped (Phase 7c).
	// The action lives in view.js and reads the DOM markers to
	// recompute the progress indicator's totals + position.
	$wrapper_args['data-wp-on--flinkform-skipped-changed'] = 'actions.onSkippedChanged';
	// `data-flinkform-enhanced` is set by the boot script (enqueued via
	// wp_add_inline_script further down) before view.js loads — so any
	// CSS gated on it applies before Interactivity hydrates and the
	// action row + step visibility don't flash.
}
$wrapper_attrs = get_block_wrapper_attributes( $wrapper_args );

if ( $is_success ) :
	// The success card replaces the whole form. The SVG checkmark is drawn
	// via a CSS stroke animation (style.scss); with prefers-reduced-motion
	// it renders fully drawn and static. tabindex="-1" lets view.js move
	// focus here after the redirect so screen readers announce the result
	// and keyboard users continue from the right place.
	?>
	<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper_attrs is the return value of the WordPress core function get_block_wrapper_attributes(), which returns an already-escaped attribute string. ?>>
		<div class="flinkform-form__success" role="status" aria-live="polite" tabindex="-1">
			<span class="flinkform-form__success-icon" aria-hidden="true">
				<svg viewBox="0 0 52 52" focusable="false">
					<circle class="flinkform-form__success-ring" cx="26" cy="26" r="24" fill="none" />
					<path class="flinkform-form__success-check" fill="none" d="M15 27.2l7.6 7.6L37.4 19.4" />
				</svg>
			</span>
			<span class="flinkform-form__success-text"><?php echo esc_html( $success_msg ); ?></span>
		</div>
	</div>
	<?php
	return;
endif;

// Re-populate field values + errors from the previous failed attempt. The
// flash state is consumed here (transient deleted) and stashed in static
// state on the Handler so field renders can read it without re-fetching.
$errors = \Flinkform\Submissions\Handler::flash_errors_for( $form_id );
$values = \Flinkform\Submissions\Handler::flash_values_for( $form_id );
\Flinkform\Submissions\Handler::set_render_state( $form_id, $errors, $values );

// Re-render inner blocks AFTER setting render state — $content was rendered
// upstream before our flash state was available, so we redo it here.
//
// While iterating we also split the inner-block stream at every
// `flinkform/page-break` marker into separate steps. A form with zero
// page-breaks produces a single step ($steps has length 1) and we emit
// the inner HTML directly with no extra wrapper — backwards-compatible
// with every existing Flinkform in the wild and with any author CSS that
// targets fields as direct children of `.flinkform-form__form`.
//
// A form with at least one page-break enters multi-step mode: each step's
// inner HTML is wrapped in a `<div class="flinkform-form__step"
// data-step-index="N" data-step-label="L">` and a visible separator is
// emitted between every adjacent pair of steps. The separator is a 5a
// affordance — it makes the server-side step splitting verifiable in the
// browser without JS — and will be removed in 5b once the Interactivity
// API hides non-current steps and drives Next/Back navigation.
$steps = [
	[
		'label'            => '',
		'html'             => '',
		'conditionalLogic' => [], // Step 0 has no opening page-break → no rule set.
	],
];
foreach ( $block->inner_blocks as $inner ) {
	if ( 'flinkform/page-break' === $inner->name ) {
		$break_label = isset( $inner->attributes['label'] ) && is_string( $inner->attributes['label'] )
			? sanitize_text_field( $inner->attributes['label'] )
			: '';
		$break_rules = isset( $inner->attributes['conditionalLogic'] ) && is_array( $inner->attributes['conditionalLogic'] )
			? $inner->attributes['conditionalLogic']
			: [];
		$steps[]     = [
			'label'            => $break_label,
			'html'             => '',
			'conditionalLogic' => $break_rules,
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
			'class="flinkform-form__step" data-step-index="%d" data-wp-context="%s" data-wp-bind--hidden="state.isNotCurrentStep"',
			$step_index,
			esc_attr( $step_context )
		);
		if ( '' !== $step_data['label'] ) {
			$step_attr .= ' data-step-label="' . esc_attr( $step_data['label'] ) . '"';
		}
		// Step-skipping conditional logic (Phase 7c). When the
		// page-break that opens this step carries a rule set, we
		// append a `data-flinkform-step-condition` attribute that
		// view.js reads to decide whether to skip the step during
		// Next/Back navigation + the progress total. Step 0 never
		// carries one — it's the form's landing step.
		$step_condition_value = \Flinkform\Conditions\Wrapper::condition_value( $step_data['conditionalLogic'] );
		if ( '' !== $step_condition_value ) {
			$step_attr .= ' data-flinkform-step-condition="' . esc_attr( $step_condition_value ) . '"';
		}
		$step_chunks[ $step_index ] = '<div ' . $step_attr . '>' . $step_data['html'] . '</div>';
	}

	$inner_html = $step_chunks[0];
	for ( $i = 1; $i < $step_count; $i++ ) {
		$next_label   = $steps[ $i ]['label'];
		$next_number  = $i + 1;
		if ( '' !== $next_label ) {
			/* translators: 1: step number, 2: author-supplied step label */
			$separator_text = sprintf( __( 'Step %1$d — %2$s', 'flinkform' ), $next_number, $next_label );
		} else {
			/* translators: %d: step number */
			$separator_text = sprintf( __( 'Step %d', 'flinkform' ), $next_number );
		}

		// The separator only makes sense when every step is visible at
		// once (the JS-disabled fallback). data-wp-bind--hidden bound
		// to a constant-true getter fires as soon as Interactivity
		// hydrates, removing the separator from the layout. Without
		// JS the binding never runs and it stays as the 5a divider.
		$inner_html .= '<div class="flinkform-form__step-separator" aria-hidden="true" data-wp-bind--hidden="state.alwaysTrue">'
			. '<span class="flinkform-form__step-separator-rule"></span>'
			. '<span class="flinkform-form__step-separator-label">' . esc_html( $separator_text ) . '</span>'
			. '<span class="flinkform-form__step-separator-rule"></span>'
			. '</div>';
		$inner_html .= $step_chunks[ $i ];
	}
}

// Timestamp token — HMAC-signed render time, validated server-side on submit
// to catch bots that submit a form within microseconds of rendering it. The
// signature (bound to the form UUID) prevents bots from forging an older
// timestamp to skip the minimum-fill-time gate.
$timestamp_token = \Flinkform\Spam\Challenge::mint_timestamp( $form_id );
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper_attrs is the return value of the WordPress core function get_block_wrapper_attributes(), which returns an already-escaped attribute string. ?>>
	<form
		class="flinkform-form__form"
		method="post"
		action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		enctype="multipart/form-data"
		novalidate
		<?php if ( $is_multi_step ) : ?>
			data-wp-on--submit="actions.submitGuard"
		<?php endif; ?>
	>
		<?php wp_nonce_field( 'flinkform_submit_' . $form_id, '_flinkform_nonce' ); ?>
		<input type="hidden" name="action" value="flinkform_submit" />
		<input type="hidden" name="flinkform_form_id" value="<?php echo esc_attr( $form_id ); ?>" />
		<input type="hidden" name="flinkform_post_id" value="<?php echo esc_attr( (string) $source_post_id ); ?>" />
		<input type="hidden" name="flinkform_ts" value="<?php echo esc_attr( $timestamp_token ); ?>" />

		<?php // Honeypot — visually + AT-hidden, bots will fill it. ?>
		<div class="flinkform-form__hp" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
			<label>
				<?php esc_html_e( 'Leave this field empty', 'flinkform' ); ?>
				<input type="text" name="flinkform_hp" value="" tabindex="-1" autocomplete="off" />
			</label>
		</div>

		<?php if ( ! empty( $errors['_form'] ) ) : ?>
			<div class="flinkform-form__error flinkform-form__error--global" role="alert">
				<?php echo esc_html( $errors['_form'] ); ?>
			</div>
		<?php endif; ?>

		<?php
		// Progress indicator (Slice 5c) — only in multi-step mode and
		// only when the author has chosen one of the three variants. The
		// markup sits above the step content so screen readers announce
		// the new progress position the moment focus moves into the
		// next step. Bar / dots / numbers are three layouts of the same
		// underlying state; the bar fill width, dot states and label
		// text are all server-rendered for the initial step + reactively
		// updated via Interactivity bindings when the user navigates.
		//
		// The label template is the only string we hand off to the JS
		// store explicitly: PHP renders 'Step %1$s of %2$s' through
		// gettext for the initial display, and we also stash the
		// placeholder-form on the namespace state so the client-side
		// progressLabel getter can re-fill it on every step change
		// without having to import @wordpress/i18n.
		if ( $is_multi_step && 'none' !== $progress_indicator ) :
			// Seed namespace-level state. Both values are identical
			// across every form on the page — only the per-step
			// substitutions in the actions in view.js change at runtime.
			//
			// `stepLabels` is the per-form step label list; this lives
			// on the namespace state rather than the form context
			// because the array is read-only after page render, doesn't
			// reflect state changes, and would just bloat data-wp-context.
			// Last-write-wins is fine since each form on the page has its
			// own step count + labels — JS reads the slice for its own
			// form via the merged context (totalSteps + currentStep) and
			// indexes into the (potentially shared) array. If two forms
			// on the same page have different step labels, only the
			// later-rendered form's labels survive on namespace state.
			// Edge case; revisit if a multi-form page with differing
			// label sets becomes a real use case.
			if ( function_exists( 'wp_interactivity_state' ) ) {
				$ns_state = [
					'progressTemplate' => sprintf(
						/* translators: 1: current step number, 2: total step count */
						__( 'Step %1$s of %2$s', 'flinkform' ),
						'%CURRENT%',
						'%TOTAL%'
					),
				];
				if ( $show_step_labels && $has_any_step_label ) {
					$ns_state['stepLabels'] = $step_labels;
				}
				wp_interactivity_state( 'flinkform/form', $ns_state );
			}
			?>
			<div
				class="flinkform-form__progress flinkform-form__progress--<?php echo esc_attr( $progress_indicator ); ?>"
				role="progressbar"
				aria-valuemin="1"
				aria-valuemax="<?php echo esc_attr( (string) $step_count ); ?>"
				aria-label="<?php echo esc_attr( $initial_label ); ?>"
				data-wp-bind--aria-valuenow="context.ariaValueNow"
				data-wp-bind--aria-label="context.progressLabel"
			>
				<?php if ( 'bar' === $progress_indicator ) : ?>
					<div class="flinkform-form__progress-track">
						<div
							class="flinkform-form__progress-fill"
							data-wp-bind--style="context.progressBarStyle"
						></div>
					</div>
				<?php elseif ( 'dots' === $progress_indicator ) : ?>
					<?php for ( $dot_index = 0; $dot_index < $step_count; $dot_index++ ) : ?>
						<span
							class="flinkform-form__progress-dot<?php echo 0 === $dot_index ? ' is-current' : ''; ?>"
							data-wp-context="<?php echo esc_attr( (string) wp_json_encode( [ 'dotIndex' => $dot_index ] ) ); ?>"
							data-wp-class--is-current="state.isCurrentDot"
							data-wp-class--is-completed="state.isPastDot"
							aria-hidden="true"
						></span>
					<?php endfor; ?>
				<?php elseif ( 'numbers' === $progress_indicator ) : ?>
					<span
						class="flinkform-form__progress-label"
						data-wp-text="context.progressLabel"
					></span>
				<?php endif; ?>
				<?php if ( $show_step_labels && $has_any_step_label ) : ?>
					<span
						class="flinkform-form__progress-step-label"
						data-wp-text="context.currentStepLabel"
					><?php echo esc_html( $step_labels[0] ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $is_multi_step ) : ?>
			<div class="flinkform-sr-only" aria-live="polite" aria-atomic="true" data-flinkform-step-announce></div>
		<?php endif; ?>

		<?php echo $inner_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $inner_html is the already-rendered inner-block markup (each field block escapes its own output in its render.php). It must not be passed through wp_kses_*, which would strip the data-wp-* Interactivity API bindings and the form input elements the block needs. Same pattern as the core post-content $content variable. ?>

		<?php
		// Built-in spam challenge (Phase B-a). Rendered just above the
		// submit button so it sits at the bottom of the form, not above
		// the fields. The Guard façade decides whether to protect.
		if ( \Flinkform\Spam\Guard::should_protect( $attributes ) ) {
			echo \Flinkform\Spam\Renderer::render( $form_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer::render() builds a fixed markup string and escapes every dynamic value with esc_attr() at the point of concatenation. It cannot be passed through wp_kses_post(), which strips the input elements the spam challenge relies on.
		}
		?>

		<div class="flinkform-form__actions">
			<?php if ( $is_multi_step ) : ?>
				<?php
				// Navigation buttons — emitted only in multi-step mode.
				// The enqueued boot script sets `hidden` on Back + Submit
				// for the JS-on initial state. Interactivity's
				// `data-wp-bind--hidden` expressions take over per step.
				?>
				<button
					type="button"
					class="flinkform-form__nav flinkform-form__nav--back"
					data-wp-on--click="actions.prevStep"
					data-wp-bind--hidden="state.isFirstStep"
				>
					<?php esc_html_e( 'Back', 'flinkform' ); ?>
				</button>
				<button
					type="button"
					class="flinkform-form__submit flinkform-form__nav flinkform-form__nav--next"
					data-wp-on--click="actions.nextStep"
					data-wp-bind--hidden="state.isLastStep"
				>
					<?php esc_html_e( 'Next', 'flinkform' ); ?>
				</button>
				<button
					type="submit"
					class="flinkform-form__submit"
					data-wp-bind--hidden="state.isNotLastStep"
					<?php
					if ( $submit_condition_value ) {
						echo ' data-flinkform-submit-condition="' . esc_attr( $submit_condition_value ) . '"';
						echo ' aria-describedby="' . esc_attr( $submit_condition_hint_id ) . '"';
					}
					?>
				>
					<?php echo esc_html( $submit_label ); ?>
				</button>
			<?php else : ?>
				<button
					type="submit"
					class="flinkform-form__submit"
					<?php
					if ( $submit_condition_value ) {
						echo ' data-flinkform-submit-condition="' . esc_attr( $submit_condition_value ) . '"';
						echo ' aria-describedby="' . esc_attr( $submit_condition_hint_id ) . '"';
					}
					?>
				>
					<?php echo esc_html( $submit_label ); ?>
				</button>
			<?php endif; ?>
			<?php if ( '' !== $submit_condition_hint_id ) : ?>
				<span
					class="flinkform-form__submit-hint"
					id="<?php echo esc_attr( $submit_condition_hint_id ); ?>"
					hidden
				>
					<?php esc_html_e( 'Please complete the required selection above to enable submission.', 'flinkform' ); ?>
				</span>
			<?php endif; ?>
		</div>
	</form>
</div>
<?php
// Boot scripts — use wp_add_inline_script() instead of raw <script> tags
// for WP.org compliance. Both are gated behind Pro capabilities so they
// never execute in the free-only plugin.
if ( \Flinkform\Spam\Guard::should_protect( $attributes ) ) {
	wp_register_script( 'flinkform-boot', false, [], FLINKFORM_VERSION, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
	wp_enqueue_script( 'flinkform-boot' );
	wp_add_inline_script(
		'flinkform-boot',
		'(function(){document.querySelectorAll("[data-flinkform-spam-math]").forEach(function(m){m.setAttribute("hidden","")})})();'
	);
}

if ( $is_multi_step ) {
	if ( ! wp_script_is( 'flinkform-boot', 'registered' ) ) {
		wp_register_script( 'flinkform-boot', false, [], FLINKFORM_VERSION, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
	}
	wp_enqueue_script( 'flinkform-boot' );
	wp_add_inline_script(
		'flinkform-boot',
		'(function(){var h=function(e){if(e)e.setAttribute("hidden","")};document.querySelectorAll(".flinkform-form--multi-step:not([data-flinkform-enhanced])").forEach(function(f){f.setAttribute("data-flinkform-enhanced","");f.querySelectorAll(".flinkform-form__step").forEach(function(s){if(s.getAttribute("data-step-index")!=="0")h(s)});f.querySelectorAll(".flinkform-form__step-separator").forEach(function(s){h(s)});h(f.querySelector(".flinkform-form__nav--back"));f.querySelectorAll(".flinkform-form__submit:not(.flinkform-form__nav--next)").forEach(function(s){h(s)})})})();'
	);
}
?>
<?php
\Flinkform\Submissions\Handler::clear_render_state();
