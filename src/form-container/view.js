/**
 * PerForm Form — Interactivity API view module.
 *
 * Drives Slice 5b's multi-step navigation: hides non-current steps,
 * wires Next/Back/Submit visibility, validates the current step's
 * required fields against HTML5 constraints before advancing, and moves
 * keyboard focus to the first field of the new step on every move.
 *
 * Progressive enhancement contract: the server-side render emits all
 * steps unhidden and the Next + Back buttons start with a `hidden`
 * attribute. When this module hydrates, the `data-wp-bind--hidden`
 * bindings take over — non-current steps gain `hidden`, the navigation
 * buttons un-hide where appropriate, and the 5a step-separators get
 * hidden globally. With JavaScript disabled none of this happens, so
 * every step + the Submit button remain visible and the form keeps
 * working as a single-page submission (Next/Back stay server-hidden,
 * which is fine — there's no JS to drive them).
 *
 * Validation: the standard HTML5 constraint API (`required`, `type`,
 * `pattern`, etc.) is the source of truth for per-step validation.
 * `step.querySelectorAll(':invalid')` enumerates the failing fields,
 * `reportValidity()` raises the browser's native error UI on the first
 * one, and `aria-invalid="true"` is added so assistive technology
 * picks up the state. Server-side validation continues to run on the
 * final submit — the client check is UX, not security.
 *
 * @package PerForm
 * @since 0.1.0
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

const NAMESPACE = 'perform/form';

const { state } = store( NAMESPACE, {
	state: {
		get isFirstStep() {
			return getContext().currentStep === 0;
		},
		get isLastStep() {
			const { currentStep, totalSteps } = getContext();
			return currentStep === totalSteps - 1;
		},
		get isNotLastStep() {
			return ! state.isLastStep;
		},
		// Per-step visibility — the step element supplies its own
		// `stepIndex` via a nested data-wp-context, which merges with
		// the wrapper's `currentStep`/`totalSteps`. The same getter
		// produces the right answer for every step in the form.
		get isCurrentStep() {
			const { stepIndex, currentStep } = getContext();
			return stepIndex === currentStep;
		},
		get isNotCurrentStep() {
			return ! state.isCurrentStep;
		},
		// Boolean constant used by data-wp-bind--hidden on the step
		// separators: as soon as Interactivity hydrates, the binding
		// fires and hides them. Without JS the bind never runs, so the
		// separators stay visible — same look as the 5a fallback.
		get alwaysTrue() {
			return true;
		},

		// Progress-indicator per-dot state. These two getters depend on
		// per-element context (the dot's `dotIndex` merges with the
		// wrapper's `currentStep`) so they stay as JS getters — there
		// is no static initial server attribute to preserve, only a
		// class that PHP renders on the first dot directly. The other
		// indicator values (bar fill, label text, ariaValueNow) live
		// on the form context so WP Interactivity's server-side
		// directive processing can resolve them for JS-disabled
		// visitors; the actions below mutate those context values on
		// every step change.
		get isCurrentDot() {
			const { dotIndex, currentStep } = getContext();
			return dotIndex === currentStep;
		},

		get isPastDot() {
			const { dotIndex, currentStep } = getContext();
			return dotIndex < currentStep;
		},
	},

	actions: {
		nextStep() {
			const ctx = getContext();
			const wrapper = getElement().ref.closest( '.perform-form' );
			if ( ! wrapper ) {
				return;
			}

			const currentStepEl = wrapper.querySelector(
				`.perform-form__step[data-step-index="${ ctx.currentStep }"]`
			);
			if ( ! currentStepEl ) {
				return;
			}

			// Clear stale aria-invalid markers from a previous attempt
			// on this step so the new check starts from a clean slate.
			currentStepEl
				.querySelectorAll( '[aria-invalid="true"]' )
				.forEach( ( el ) => el.removeAttribute( 'aria-invalid' ) );

			const invalid = currentStepEl.querySelectorAll( ':invalid' );

			if ( invalid.length > 0 ) {
				invalid.forEach( ( field ) => {
					field.setAttribute( 'aria-invalid', 'true' );
				} );
				// reportValidity() surfaces the browser's native error
				// tooltip on the first invalid field and is itself an
				// i18n-aware message — no string table to maintain.
				invalid[ 0 ].reportValidity();
				if ( typeof invalid[ 0 ].focus === 'function' ) {
					invalid[ 0 ].focus();
				}
				return;
			}

			if ( ctx.currentStep < ctx.totalSteps - 1 ) {
				ctx.currentStep += 1;
				syncProgressContext( ctx );
				focusFirstFieldOfStep( wrapper, ctx.currentStep );
			}
		},

		prevStep() {
			const ctx = getContext();
			if ( ctx.currentStep <= 0 ) {
				return;
			}
			const wrapper = getElement().ref.closest( '.perform-form' );
			ctx.currentStep -= 1;
			syncProgressContext( ctx );
			if ( wrapper ) {
				focusFirstFieldOfStep( wrapper, ctx.currentStep );
			}
		},

		// Submit guard — intercepts the form's submit event and blocks
		// it unless we're on the final step. Necessary because pressing
		// Enter inside an input on Step 1 (or any non-final step) would
		// otherwise fall through to the hidden Submit button: the
		// `hidden` attribute hides it visually but it still acts as
		// the form's default submitter for keyboard input.
		submitGuard( event ) {
			if ( state.isNotLastStep ) {
				event.preventDefault();
			}
		},
	},

} );

/**
 * Recompute and write the progress-indicator context values for the
 * current step.
 *
 * Context (not namespace state) is the home for these because WP
 * Interactivity's server-side directive processing can resolve
 * `context.X` references against the JSON in `data-wp-context` —
 * meaning JS-disabled visitors still get a correctly server-rendered
 * progressbar (aria-valuenow, bar fill width, label text). Once
 * Interactivity hydrates, navigation calls into here and the
 * bindings re-evaluate against the freshly written context.
 *
 * The localised label template lives on namespace state because it's
 * identical across every form on the page; only the numbers
 * substituted into it change.
 *
 * @param {object} ctx The merged context object for this form.
 */
function syncProgressContext( ctx ) {
	const next = ctx.currentStep + 1;
	ctx.ariaValueNow = next;
	ctx.progressBarStyle = `--perform-progress-percent:${ ( ( next / ctx.totalSteps ) * 100 ).toFixed( 2 ) }%`;

	const template = state.progressTemplate;
	if ( typeof template === 'string' && template !== '' ) {
		ctx.progressLabel = template
			.replace( '%CURRENT%', String( next ) )
			.replace( '%TOTAL%', String( ctx.totalSteps ) );
	} else {
		// Fallback when the PHP-seeded template isn't present.
		ctx.progressLabel = `Step ${ next } of ${ ctx.totalSteps }`;
	}

	// Optional step-label display (5d). Only updates the binding when
	// the per-form context already has the field — server only seeds
	// `currentStepLabel` when the author enabled Step Labels in the
	// inspector and at least one page-break carries a label, so the
	// guard keeps the binding inert on forms without the feature
	// turned on.
	if ( Object.prototype.hasOwnProperty.call( ctx, 'currentStepLabel' ) ) {
		const labels = state.stepLabels;
		if ( Array.isArray( labels ) && typeof labels[ ctx.currentStep ] === 'string' ) {
			ctx.currentStepLabel = labels[ ctx.currentStep ];
		} else {
			ctx.currentStepLabel = '';
		}
	}
}

/**
 * Move keyboard focus to the first focusable field of the given step.
 *
 * Skips hidden inputs (honeypot, nonce, etc.) and only considers
 * controls that can actually receive focus from the keyboard. Called
 * on every step change so Tab order stays inside the visible step and
 * screen readers announce the new context immediately.
 *
 * @param {HTMLElement} wrapper   The form wrapper element.
 * @param {number}      stepIndex Zero-based index of the now-current step.
 */
function focusFirstFieldOfStep( wrapper, stepIndex ) {
	const step = wrapper.querySelector(
		`.perform-form__step[data-step-index="${ stepIndex }"]`
	);
	if ( ! step ) {
		return;
	}
	const focusable = step.querySelector(
		'input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), button:not([disabled])'
	);
	if ( focusable && typeof focusable.focus === 'function' ) {
		focusable.focus();
	}
}
