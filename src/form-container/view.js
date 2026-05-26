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

// ---------------------------------------------------------------------
// Conditional-logic frontend evaluator (Phase 7b)
//
// Independent of the Interactivity store because conditional logic
// applies to every PerForm form on the page, including single-step
// forms that never set up the multi-step context. Wiring it through
// the store would gate the feature on the wrapper carrying
// `data-wp-interactive`; doing it as a free-standing module-init means
// any form with a `[data-perform-condition]` wrapper inside it works.
//
// On module load we walk every `.perform-form__form`, build a list of
// its conditional wrappers, bind a single `input` listener per form,
// and on every change re-evaluate every wrapper's rule set against
// the form's current values. The server runs the same rule set in
// `Submissions\Handler` so a DOM-manipulated visible field can't
// smuggle data through — client-side is UX, server-side is truth.
// ---------------------------------------------------------------------

const EMPTY_OPERATORS = new Set( [ 'is_empty', 'is_not_empty' ] );

if ( typeof document !== 'undefined' ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initConditionalLogic );
	} else {
		initConditionalLogic();
	}
}

function initConditionalLogic() {
	const forms = document.querySelectorAll( '.perform-form__form' );
	forms.forEach( ( form ) => {
		const fieldWrappers   = form.querySelectorAll( '[data-perform-condition]' );
		const stepWrappers    = form.querySelectorAll( '.perform-form__step[data-perform-step-condition]' );
		const submitButtons   = form.querySelectorAll( '.perform-form__submit[data-perform-submit-condition]' );

		if ( fieldWrappers.length === 0 && stepWrappers.length === 0 && submitButtons.length === 0 ) {
			return;
		}

		// Initial pass — apply visibility before the user types
		// anything, in case server-render already had values from a
		// repopulated submission (flash state).
		evaluateAll( form, fieldWrappers, stepWrappers, submitButtons );

		// `input` covers text fields, textareas, selects (modern
		// browsers fire input on select change), and number/email/
		// password. `change` covers radio/checkbox toggles where
		// input doesn't fire. Both bubble, so a single listener on
		// the form catches everything.
		form.addEventListener( 'input', () => evaluateAll( form, fieldWrappers, stepWrappers, submitButtons ) );
		form.addEventListener( 'change', () => evaluateAll( form, fieldWrappers, stepWrappers, submitButtons ) );

		// Submit-guard against the submit-condition (Phase 7d) — the
		// existing multi-step `submitGuard` action only fires on
		// multi-step forms via its data-wp-on--submit binding. This
		// vanilla `submit` listener covers single-step forms too and
		// runs first via the `capture` phase so it can preventDefault
		// before the multi-step guard sees the event.
		if ( submitButtons.length > 0 ) {
			form.addEventListener( 'submit', ( event ) => {
				const values = gatherFormValues( form );
				for ( const btn of submitButtons ) {
					const raw = btn.getAttribute( 'data-perform-submit-condition' );
					if ( ! raw ) {
						continue;
					}
					try {
						const ruleSet = JSON.parse( raw );
						if ( ! evaluateRuleSet( ruleSet, values ) ) {
							event.preventDefault();
							btn.focus();
							return;
						}
					} catch ( _ ) {
						// Malformed JSON — let the submit proceed; the
						// server-side enforcement will catch this if it
						// matters.
					}
				}
			}, true );
		}
	} );
}

/**
 * Re-evaluate every conditional wrapper inside the given form and
 * toggle its `hidden` attribute + (when applicable) its inputs'
 * `disabled` state — disabled hidden inputs aren't submitted, which
 * is what we want for fields that aren't supposed to be visible.
 *
 * @param {HTMLFormElement} form
 * @param {NodeListOf<Element>} wrappers
 */
function evaluateAll( form, fieldWrappers, stepWrappers, submitButtons = [] ) {
	const values = gatherFormValues( form );

	// Field-level conditions: toggle wrapper hidden + disable inputs.
	fieldWrappers.forEach( ( wrapper ) => {
		const raw = wrapper.getAttribute( 'data-perform-condition' );
		if ( ! raw ) {
			return;
		}

		let ruleSet;
		try {
			ruleSet = JSON.parse( raw );
		} catch ( _ ) {
			return; // Malformed JSON — leave the wrapper alone.
		}

		const shouldShow = evaluateRuleSet( ruleSet, values );

		if ( shouldShow ) {
			wrapper.removeAttribute( 'hidden' );
			toggleInputs( wrapper, false );
		} else {
			wrapper.setAttribute( 'hidden', '' );
			toggleInputs( wrapper, true );
		}
	} );

	// Step-level conditions (Phase 7c): set a `data-perform-skipped`
	// marker on the wrapper so the Interactivity action can read the
	// up-to-date skip state when the user clicks Next / Back. The
	// step's own visibility is still driven by the multi-step
	// `state.isNotCurrentStep` binding — skipping plus current-step
	// matching are two independent toggles.
	let skippedChanged = false;
	stepWrappers.forEach( ( wrapper ) => {
		const raw = wrapper.getAttribute( 'data-perform-step-condition' );
		if ( ! raw ) {
			return;
		}

		let ruleSet;
		try {
			ruleSet = JSON.parse( raw );
		} catch ( _ ) {
			return;
		}

		const shouldShow = evaluateRuleSet( ruleSet, values );
		const isSkipped  = wrapper.getAttribute( 'data-perform-skipped' ) === 'true';

		if ( ! shouldShow ) {
			if ( ! isSkipped ) {
				wrapper.setAttribute( 'data-perform-skipped', 'true' );
				skippedChanged = true;
			}
			// Disable inputs in skipped steps — same reasoning as
			// hidden fields: a skipped step's values must not reach
			// the server through the form's native submit.
			toggleInputs( wrapper, true );
		} else {
			if ( isSkipped ) {
				wrapper.removeAttribute( 'data-perform-skipped' );
				skippedChanged = true;
			}
			toggleInputs( wrapper, false );
		}
	} );

	// Notify the Interactivity store that the skipped-set has shifted
	// so it can recompute the progress indicator. The wrapper-level
	// `data-wp-on--perform-skipped-changed` binding turns this custom
	// event into a call to `actions.onSkippedChanged`, where the
	// progress context values get re-derived inside an element-scope
	// that getContext() can actually resolve.
	if ( skippedChanged ) {
		const wrapper = form.closest( '.perform-form' );
		if ( wrapper ) {
			wrapper.dispatchEvent( new CustomEvent( 'perform-skipped-changed', { bubbles: false } ) );
		}
	}

	// Submit-button conditions (Phase 7d): each Submit button can
	// carry its own rule set; when the rule says "don't enable",
	// the button gets `disabled` + an explanatory hint span becomes
	// visible (the hint's id is already wired through aria-describedby
	// on the button so screen readers announce the reason every time
	// focus lands on the button).
	if ( submitButtons && submitButtons.length > 0 ) {
		submitButtons.forEach( ( btn ) => {
			const raw = btn.getAttribute( 'data-perform-submit-condition' );
			if ( ! raw ) {
				return;
			}

			let ruleSet;
			try {
				ruleSet = JSON.parse( raw );
			} catch ( _ ) {
				return;
			}

			const shouldEnable = evaluateRuleSet( ruleSet, values );
			btn.disabled       = ! shouldEnable;

			const hintId = btn.getAttribute( 'aria-describedby' );
			if ( hintId ) {
				const hint = document.getElementById( hintId );
				if ( hint ) {
					if ( shouldEnable ) {
						hint.setAttribute( 'hidden', '' );
					} else {
						hint.removeAttribute( 'hidden' );
					}
				}
			}
		} );
	}
}

/**
 * Disable every named input inside a wrapper (or undo that). Disabled
 * inputs aren't included in the form's submission, which lines up
 * with the server-side stripping: a hidden field's value never
 * reaches the database in either direction.
 *
 * @param {Element} wrapper
 * @param {boolean} disable
 */
function toggleInputs( wrapper, disable ) {
	wrapper.querySelectorAll( 'input, textarea, select' ).forEach( ( el ) => {
		// Don't override aria-hidden honeypot inputs etc. — they don't
		// live inside conditional wrappers anyway, but defensive.
		if ( disable ) {
			// Record the ORIGINAL (pre-our-touch) disabled state, but
			// only once — on the first disable call. Subsequent
			// disable calls (e.g. user keeps typing in a different
			// field while this wrapper stays hidden) must not
			// overwrite the marker with the currently-disabled state
			// we ourselves set on the previous tick, or `false`
			// branch below would treat all inputs as "was already
			// disabled" and leak the disabled state forward forever.
			if ( ! el.hasAttribute( 'data-perform-was-disabled' ) ) {
				el.setAttribute( 'data-perform-was-disabled', el.disabled ? '1' : '0' );
			}
			el.disabled = true;
		} else {
			// Only re-enable if WE disabled it (not if the markup
			// shipped it as disabled, e.g. read-only state from the
			// server). The marker tells us which.
			if ( el.getAttribute( 'data-perform-was-disabled' ) === '0' ) {
				el.disabled = false;
			}
			el.removeAttribute( 'data-perform-was-disabled' );
		}
	} );
}

/**
 * Build a `{ fieldName: value }` map from the form's submittable
 * inputs. Multi-value inputs (radio groups, multi-select, checkbox
 * groups) collapse to an array of selected values; toggles + single
 * checkboxes return their `value` attribute when checked, or null
 * when unchecked.
 *
 * @param {HTMLFormElement} form
 * @returns {Object<string, string|string[]|null>}
 */
function gatherFormValues( form ) {
	const values = {};

	form.querySelectorAll( 'input[name], textarea[name], select[name]' ).forEach( ( el ) => {
		const rawName = el.getAttribute( 'name' );
		// Field inputs are emitted as `perform_field[<name>]` — extract
		// the inner name so the values map matches the field-name shape
		// the conditional-logic rules reference (and the server's
		// `$clean` map uses too).
		const match = rawName.match( /^perform_field\[([^\]]+)\](\[\])?$/ );
		if ( ! match ) {
			return;
		}
		const name = match[ 1 ];

		if ( el.tagName === 'SELECT' && el.multiple ) {
			values[ name ] = Array.from( el.selectedOptions ).map( ( o ) => o.value );
			return;
		}

		if ( el.type === 'checkbox' ) {
			// Multi-value checkbox group: name ends with `[]`.
			if ( match[ 2 ] ) {
				if ( ! Array.isArray( values[ name ] ) ) {
					values[ name ] = [];
				}
				if ( el.checked ) {
					values[ name ].push( el.value );
				}
				return;
			}
			// Single toggle / boolean checkbox.
			values[ name ] = el.checked ? el.value : '';
			return;
		}

		if ( el.type === 'radio' ) {
			if ( el.checked ) {
				values[ name ] = el.value;
			} else if ( ! ( name in values ) ) {
				values[ name ] = '';
			}
			return;
		}

		values[ name ] = el.value;
	} );

	return values;
}

/**
 * Mirror of `PerForm\Conditions\RuleEvaluator::should_show()` in JS.
 *
 * @param {object} ruleSet
 * @param {Object<string, any>} values
 * @returns {boolean}
 */
function evaluateRuleSet( ruleSet, values ) {
	if ( ! ruleSet || ! ruleSet.enabled ) {
		return true;
	}
	const rules = Array.isArray( ruleSet.rules ) ? ruleSet.rules : [];
	if ( rules.length === 0 ) {
		return true;
	}
	const mode = ruleSet.logic === 'any' ? 'any' : 'all';

	for ( const rule of rules ) {
		const match = evaluateRule( rule, values );
		if ( mode === 'any' && match ) {
			return true;
		}
		if ( mode === 'all' && ! match ) {
			return false;
		}
	}
	return mode === 'all';
}

function evaluateRule( rule, values ) {
	if ( ! rule || typeof rule !== 'object' ) {
		return false;
	}
	const field = String( rule.field ?? '' );
	const operator = String( rule.operator ?? '' );
	const value = String( rule.value ?? '' );

	if ( field === '' || operator === '' ) {
		return false;
	}

	const fieldValue = values[ field ] ?? null;

	if ( EMPTY_OPERATORS.has( operator ) ) {
		const empty = isEmptyValue( fieldValue );
		return operator === 'is_empty' ? empty : ! empty;
	}

	const fieldString = toComparableString( fieldValue );

	switch ( operator ) {
		case 'is':
			// Case-insensitive on purpose — matches the PHP-side
			// RuleEvaluator::evaluate_rule(). See the long comment
			// there for the slugify rationale; in short: "Skip" in
			// the rule UI must match "skip" in the serialised option
			// value the editor's slugify helper produced.
			return fieldString.toLowerCase() === value.toLowerCase();
		case 'is_not':
			return fieldString.toLowerCase() !== value.toLowerCase();
		case 'contains':
			return value !== '' && fieldString.toLowerCase().includes( value.toLowerCase() );
		case 'not_contains':
			return value === '' || ! fieldString.toLowerCase().includes( value.toLowerCase() );
		case 'greater_than':
			if ( fieldString === '' || isNaN( Number( fieldString ) ) || isNaN( Number( value ) ) ) {
				return false;
			}
			return Number( fieldString ) > Number( value );
		case 'less_than':
			if ( fieldString === '' || isNaN( Number( fieldString ) ) || isNaN( Number( value ) ) ) {
				return false;
			}
			return Number( fieldString ) < Number( value );
		default:
			return false;
	}
}

function toComparableString( v ) {
	if ( v === null || v === undefined ) {
		return '';
	}
	if ( Array.isArray( v ) ) {
		return v.map( ( x ) => String( x ) ).join( ', ' );
	}
	if ( typeof v === 'boolean' ) {
		return v ? '1' : '';
	}
	return String( v );
}

function isEmptyValue( v ) {
	if ( v === null || v === undefined ) {
		return true;
	}
	if ( Array.isArray( v ) ) {
		return v.length === 0;
	}
	if ( typeof v === 'string' ) {
		return v.trim() === '';
	}
	if ( typeof v === 'boolean' ) {
		return ! v;
	}
	return false;
}

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

			const target = findNextVisibleStep( wrapper, ctx.currentStep, ctx.totalSteps );
			if ( null !== target ) {
				ctx.currentStep = target;
				syncProgressContext( ctx, wrapper );
				deferFocus( wrapper, ctx.currentStep );
			}
		},

		prevStep() {
			const ctx = getContext();
			if ( ctx.currentStep <= 0 ) {
				return;
			}
			const wrapper = getElement().ref.closest( '.perform-form' );
			const target  = findPrevVisibleStep( wrapper, ctx.currentStep );
			if ( null === target ) {
				return;
			}
			ctx.currentStep = target;
			syncProgressContext( ctx, wrapper );
			if ( wrapper ) {
				deferFocus( wrapper, ctx.currentStep );
			}
		},

		// Called from the standalone DOM listener via a custom event
		// when step-skip conditions change mid-form (e.g. user typed
		// a value that newly satisfies a skip rule). Recomputes the
		// progress-indicator context against the now-current skipped
		// set so totalSteps / currentStep counters stay accurate
		// without waiting for the user to hit Next.
		//
		// Plus: when the user is currently sitting on a step that
		// itself just became skipped, bump them forward to the next
		// visible step (or backward, if forward isn't possible) so
		// the form doesn't strand them on an invisible step.
		onSkippedChanged() {
			const ctx        = getContext();
			const wrapper    = getElement().ref;
			const currentEl  = wrapper.querySelector(
				`.perform-form__step[data-step-index="${ ctx.currentStep }"]`
			);
			const isCurrentNowSkipped = currentEl && currentEl.getAttribute( 'data-perform-skipped' ) === 'true';

			if ( isCurrentNowSkipped ) {
				let target = findNextVisibleStep( wrapper, ctx.currentStep, ctx.totalSteps );
				if ( null === target ) {
					target = findPrevVisibleStep( wrapper, ctx.currentStep );
				}
				if ( null !== target ) {
					ctx.currentStep = target;
					deferFocus( wrapper, ctx.currentStep );
				}
			}

			syncProgressContext( ctx, wrapper );
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
function syncProgressContext( ctx, wrapper = null ) {
	// Step-skipping awareness (Phase 7c). When the wrapper is in scope
	// we count the `data-perform-skipped="true"` markers the
	// standalone listener leaves on currently-skipped steps and
	// adjust the indicator's totals + position so the user sees
	// "Step 2 of 3" turn into "Step 2 of 2" the moment a step drops
	// out of the flow. Without the wrapper (calls predating 7c) we
	// fall back to the raw markup totals.
	let totalSkipped         = 0;
	let skippedBeforeCurrent = 0;
	if ( wrapper ) {
		wrapper.querySelectorAll( '.perform-form__step[data-perform-skipped="true"]' ).forEach( ( s ) => {
			totalSkipped += 1;
			const idx = parseInt( s.getAttribute( 'data-step-index' ) || '0', 10 );
			if ( idx < ctx.currentStep ) {
				skippedBeforeCurrent += 1;
			}
		} );
	}

	const effectiveTotal   = Math.max( 1, ctx.totalSteps - totalSkipped );
	const effectiveCurrent = Math.max( 1, ctx.currentStep + 1 - skippedBeforeCurrent );

	ctx.ariaValueNow     = effectiveCurrent;
	ctx.progressBarStyle = `--perform-progress-percent:${ ( ( effectiveCurrent / effectiveTotal ) * 100 ).toFixed( 2 ) }%`;

	const template = state.progressTemplate;
	if ( typeof template === 'string' && template !== '' ) {
		ctx.progressLabel = template
			.replace( '%CURRENT%', String( effectiveCurrent ) )
			.replace( '%TOTAL%', String( effectiveTotal ) );
	} else {
		// Fallback when the PHP-seeded template isn't present.
		ctx.progressLabel = `Step ${ effectiveCurrent } of ${ effectiveTotal }`;
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
 * Find the next visible step index strictly after `current`, skipping
 * over any `[data-perform-skipped="true"]` step. Returns null when
 * there's no visible step left (= we're already on the last visible
 * step).
 *
 * @param {Element} wrapper Form wrapper element.
 * @param {number}  current Current step index (0-based).
 * @param {number}  total   Total markup steps.
 * @returns {number|null}
 */
function findNextVisibleStep( wrapper, current, total ) {
	if ( ! wrapper ) {
		return current < total - 1 ? current + 1 : null;
	}
	for ( let i = current + 1; i < total; i++ ) {
		const step = wrapper.querySelector( `.perform-form__step[data-step-index="${ i }"]` );
		if ( step && step.getAttribute( 'data-perform-skipped' ) === 'true' ) {
			continue;
		}
		return i;
	}
	return null;
}

/**
 * Find the previous visible step strictly before `current`. Same
 * skipping rules as findNextVisibleStep, walking backwards.
 *
 * @param {Element} wrapper Form wrapper.
 * @param {number}  current Current step index (0-based).
 * @returns {number|null}
 */
function findPrevVisibleStep( wrapper, current ) {
	if ( ! wrapper ) {
		return current > 0 ? current - 1 : null;
	}
	for ( let i = current - 1; i >= 0; i-- ) {
		const step = wrapper.querySelector( `.perform-form__step[data-step-index="${ i }"]` );
		if ( step && step.getAttribute( 'data-perform-skipped' ) === 'true' ) {
			continue;
		}
		return i;
	}
	return null;
}

/**
 * Schedule a focus move to the new step after Interactivity has
 * finished reconciling the DOM.
 *
 * WP Interactivity uses Preact signals internally; mutating
 * `ctx.currentStep` triggers a reactive re-render that flushes via
 * the microtask queue, not synchronously. Calling `focus()` right
 * after the mutation lands on a step element that still carries the
 * `hidden` attribute from the previous tick — and the browser
 * silently drops a focus() call on a `display:none` element, leaving
 * focus on the Next button instead of the new step's first field.
 *
 * `requestAnimationFrame` runs after all pending microtasks and after
 * the browser has applied DOM changes for the next paint, so by the
 * time the callback fires the new step is visible and `focus()`
 * lands where it's supposed to.
 *
 * @param {HTMLElement} wrapper   The form wrapper element.
 * @param {number}      stepIndex Zero-based index of the now-current step.
 */
function deferFocus( wrapper, stepIndex ) {
	if ( typeof window.requestAnimationFrame === 'function' ) {
		window.requestAnimationFrame( () => focusFirstFieldOfStep( wrapper, stepIndex ) );
		return;
	}
	// Ancient-browser fallback — should never run on any environment
	// the plugin actually supports, but better than swallowing the call.
	focusFirstFieldOfStep( wrapper, stepIndex );
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

// ---------------------------------------------------------------------
// Built-in spam challenge — proof-of-work solver (Phase B-a)
//
// Each protected form renders a `.perform-form__spam` block with
// `data-perform-pow-salt` + `data-perform-pow-difficulty` attributes and
// a hidden `[data-perform-spam-solution]` input. We find the salt/
// difficulty, compute sha256(salt + n) for increasing n until the hex
// hash matches the leading-zero requirement, then write the winning n
// into the hidden input. The visible math fallback is hidden once we
// have a solution so JS-on visitors never see it.
//
// Compute strategy: main-thread async with yield-every-1000 iterations.
// Avoids Web Workers (no extra script asset to register, no inline-blob
// CSP friction). Average wall time at difficulty=18 is ~50-500 ms on
// modern CPUs, sometimes up to ~2 s on low-end mobile. crypto.subtle
// is available on every browser shipped since 2016 — we don't fall
// back any further; visitors on truly ancient browsers see the math
// challenge and answer it manually.
//
// SSR + Interactivity-API friendliness: the solver runs as a free-
// standing DOM init (not through the Interactivity store) so it works
// on every form regardless of multi-step state. Phase 7b's conditional
// logic init follows the same pattern.
// ---------------------------------------------------------------------

if ( typeof document !== 'undefined' ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initSpamChallenge );
	} else {
		initSpamChallenge();
	}
}

function initSpamChallenge() {
	const blocks = document.querySelectorAll( '.perform-form__spam[data-perform-spam]' );
	blocks.forEach( ( block ) => {
		const salt       = block.getAttribute( 'data-perform-pow-salt' ) || '';
		const difficulty = parseInt(
			block.getAttribute( 'data-perform-pow-difficulty' ) || '0',
			10
		);
		const solutionInput = block.querySelector( '[data-perform-spam-solution]' );
		const mathRow       = block.querySelector( '[data-perform-spam-math]' );

		if ( ! salt || ! difficulty || ! solutionInput ) {
			// Mis-rendered block — leave the math fallback visible so
			// the visitor can still submit by answering the question.
			return;
		}

		if ( ! window.crypto || ! window.crypto.subtle ) {
			// Browser lacks Web Crypto — same fallback path.
			return;
		}

		solvePoW( salt, difficulty )
			.then( ( solution ) => {
				solutionInput.value = String( solution );
				// PoW solved → math row is redundant; hide it. We
				// purposely hide AFTER successful solve so a slow
				// device that's still computing leaves the math row
				// visible as a fallback the visitor can use.
				if ( mathRow ) {
					mathRow.setAttribute( 'hidden', '' );
					// Clear any prefilled math answer so the server
					// doesn't see two competing solutions on submit.
					const mathInput = mathRow.querySelector( 'input[type="text"]' );
					if ( mathInput ) {
						mathInput.value = '';
					}
				}
			} )
			.catch( () => {
				// Compute aborted (e.g. tab backgrounded long enough
				// for the browser to throttle the JS loop) — fall
				// back to the math row that's already visible.
			} );
	} );
}

/**
 * Compute the PoW solution.
 *
 * Loops with a microtask yield every 1024 iterations so the UI thread
 * stays responsive. crypto.subtle.digest is async; the inner await
 * already lets the event loop breathe, but Chromium specifically can
 * starve repaint cycles when the yields are too tight — 1024 is the
 * sweet spot between "responsive" and "not artificially slow".
 *
 * @param {string} salt        base64url-encoded server-supplied salt
 * @param {number} difficulty  leading-zero bits required
 * @returns {Promise<number>}  the winning integer
 */
async function solvePoW( salt, difficulty ) {
	const encoder = new TextEncoder();
	const fullHex = Math.floor( difficulty / 4 );
	const extraBits = difficulty % 4;
	const mask = ( 0x0f << ( 4 - extraBits ) ) & 0x0f;

	let n = 0;
	while ( true ) {
		const data = encoder.encode( `${ salt }|${ n }` );
		const digest = await window.crypto.subtle.digest( 'SHA-256', data );
		const hex = bytesToHex( digest );

		let leadingZeros = true;
		for ( let i = 0; i < fullHex; i++ ) {
			if ( hex[ i ] !== '0' ) {
				leadingZeros = false;
				break;
			}
		}

		if ( leadingZeros ) {
			if ( extraBits === 0 ) {
				return n;
			}
			const nibble = parseInt( hex[ fullHex ], 16 );
			if ( ( nibble & mask ) === 0 ) {
				return n;
			}
		}

		n++;
		if ( ( n & 1023 ) === 0 ) {
			// Yield to give the UI thread a chance to repaint.
			// 0-ms timeout is the standard "next tick" pattern.
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		}
	}
}

/**
 * Convert an ArrayBuffer to a lowercase hex string.
 * Faster than `Array.from(bytes).map(...).join('')` on hot paths
 * because the latter allocates a fresh array per call.
 *
 * @param {ArrayBuffer} buffer
 * @returns {string}
 */
function bytesToHex( buffer ) {
	const bytes = new Uint8Array( buffer );
	let out = '';
	for ( let i = 0; i < bytes.length; i++ ) {
		out += bytes[ i ].toString( 16 ).padStart( 2, '0' );
	}
	return out;
}
