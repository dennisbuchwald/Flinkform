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

	callbacks: {
		// Sets `data-perform-enhanced` on the form wrapper the moment
		// Interactivity hydrates. CSS keys off this attribute to lay
		// out the navigation row (margin-right:auto on the Back button
		// only matters when JS is going to make Back appear); without
		// it the form-actions row keeps its single-button look from
		// pre-multi-step PerForm.
		markEnhanced() {
			const { ref } = getElement();
			ref.setAttribute( 'data-perform-enhanced', '' );
		},
	},
} );

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
