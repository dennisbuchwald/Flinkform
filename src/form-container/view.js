/**
 * Flinkform Form — Interactivity API view module.
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
 * @package Flinkform
 * @since 0.1.0
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

const NAMESPACE = 'flinkform/form';

// ---------------------------------------------------------------------
// Conditional-logic frontend evaluator (Phase 7b)
//
// Independent of the Interactivity store because conditional logic
// applies to every Flinkform form on the page, including single-step
// forms that never set up the multi-step context. Wiring it through
// the store would gate the feature on the wrapper carrying
// `data-wp-interactive`; doing it as a free-standing module-init means
// any form with a `[data-flinkform-condition]` wrapper inside it works.
//
// On module load we walk every `.flinkform-form__form`, build a list of
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
		document.addEventListener( 'DOMContentLoaded', initSubmitFeedback );
		document.addEventListener( 'DOMContentLoaded', initFetchSubmit );
	} else {
		initConditionalLogic();
		initSubmitFeedback();
		initFetchSubmit();
	}
}

// ---------------------------------------------------------------------
// Fetch submission for popup/modal contexts.
//
// A form inside a popup can't use the normal POST + redirect flow — the
// page reload closes the popup and the visitor never sees the outcome.
// When a form lives inside a modal container we intercept the final
// submit, send it via fetch() with the X-Flinkform-Fetch header (the
// Handler answers those with JSON instead of a redirect), and render
// the success card or the field errors inline.
//
// Everything else is untouched: forms outside popups keep the exact
// page-reload behaviour, and all server-side gates (nonce, honeypot,
// time-check, spam challenge, idempotency) run unchanged because the
// fetch carries the same FormData a native submit would.
// ---------------------------------------------------------------------

const POPUP_SELECTOR = '.wp-block-dbw-base-popup, dialog, [role="dialog"]';

function initFetchSubmit() {
	document.querySelectorAll( '.flinkform-form__form' ).forEach( ( form ) => {
		if ( ! form.closest( POPUP_SELECTOR ) ) {
			return; // Normal flow — page reload, exactly as before.
		}

		form.addEventListener( 'submit', ( event ) => {
			// Respect earlier guards: multi-step's non-final-step guard and
			// the Pro payment interceptor both preventDefault first.
			if ( event.defaultPrevented ) {
				return;
			}

			// A payment field that hasn't been confirmed yet must complete
			// the Stripe flow first — its own handler re-submits afterwards
			// (with the intent input filled), and only then do we take over.
			const paymentIntent = form.querySelector( '[data-flinkform-payment-intent]' );
			if ( paymentIntent && ! paymentIntent.value ) {
				return;
			}

			event.preventDefault();
			submitViaFetch( form );
		} );
	} );
}

async function submitViaFetch( form ) {
	setSubmitLoading( form, true );

	let data;
	try {
		const response = await fetch( form.action, {
			method: 'POST',
			body: new FormData( form ),
			headers: { 'X-Flinkform-Fetch': '1' },
		} );
		data = await response.json();
	} catch {
		// Network error or a non-JSON answer (e.g. a security wp_die page):
		// fall back to the native submission so nothing is ever lost.
		// form.submit() bypasses this listener, so no loop.
		form.submit();
		return;
	}

	if ( data && data.success && data.data ) {
		if ( data.data.behaviour === 'redirect' && data.data.redirect_url ) {
			window.location.assign( data.data.redirect_url );
			return;
		}
		showFetchSuccess( form, data.data.message || '' );
		return;
	}

	const errors = data && data.data && data.data.errors ? data.data.errors : {};
	showFetchErrors( form, errors );
	setSubmitLoading( form, false );
}

function setSubmitLoading( form, loading ) {
	form.querySelectorAll( '.flinkform-form__submit' ).forEach( ( btn ) => {
		btn.classList.toggle( 'is-loading', loading );
		if ( loading ) {
			btn.setAttribute( 'aria-busy', 'true' );
		} else {
			btn.removeAttribute( 'aria-busy' );
		}
		btn.disabled = loading;
	} );
}

/**
 * Replace the form with the same success card render.php emits after the
 * redirect flow, and move focus onto it (screen readers announce it via
 * role="status").
 */
function showFetchSuccess( form, message ) {
	const card = document.createElement( 'div' );
	card.className = 'flinkform-form__success';
	card.setAttribute( 'role', 'status' );
	card.setAttribute( 'aria-live', 'polite' );
	card.setAttribute( 'tabindex', '-1' );

	const icon = document.createElement( 'span' );
	icon.className = 'flinkform-form__success-icon';
	icon.setAttribute( 'aria-hidden', 'true' );
	icon.innerHTML =
		'<svg viewBox="0 0 52 52" focusable="false">' +
		'<circle class="flinkform-form__success-ring" cx="26" cy="26" r="24" fill="none" />' +
		'<path class="flinkform-form__success-check" fill="none" d="M15 27.2l7.6 7.6L37.4 19.4" />' +
		'</svg>';

	const text = document.createElement( 'span' );
	text.className = 'flinkform-form__success-text';
	text.textContent = message;

	card.appendChild( icon );
	card.appendChild( text );
	form.replaceWith( card );
	card.focus( { preventScroll: true } );
}

/**
 * Render server-side validation errors inline, mirroring the markup
 * render.php emits on the redirect flow (global banner + per-field
 * role="alert" messages + has-error wrappers).
 */
function showFetchErrors( form, errors ) {
	clearFetchErrors( form );

	let firstInvalid = null;

	Object.entries( errors ).forEach( ( [ fieldName, message ] ) => {
		if ( fieldName === '_form' ) {
			const banner = document.createElement( 'div' );
			banner.className = 'flinkform-form__error flinkform-form__error--global';
			banner.setAttribute( 'role', 'alert' );
			banner.textContent = message;
			form.prepend( banner );
			return;
		}

		const wrapper = form.querySelector( `[data-flinkform-field-name="${ fieldName }"]` );
		if ( ! wrapper ) {
			return;
		}

		wrapper.classList.add( 'flinkform-field--has-error' );

		const error = document.createElement( 'p' );
		error.className = 'flinkform-field__error';
		error.setAttribute( 'role', 'alert' );
		error.textContent = message;
		wrapper.appendChild( error );

		const input = wrapper.querySelector( 'input, select, textarea' );
		if ( input ) {
			input.setAttribute( 'aria-invalid', 'true' );
			if ( ! firstInvalid ) {
				firstInvalid = input;
			}
		}
	} );

	if ( firstInvalid ) {
		const reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		firstInvalid.focus( { preventScroll: true } );
		firstInvalid.scrollIntoView( {
			behavior: reduceMotion ? 'auto' : 'smooth',
			block: 'center',
		} );
	}
}

function clearFetchErrors( form ) {
	form.querySelectorAll( '.flinkform-form__error--global' ).forEach( ( el ) => el.remove() );
	form.querySelectorAll( '.flinkform-field__error' ).forEach( ( el ) => el.remove() );
	form.querySelectorAll( '.flinkform-field--has-error' ).forEach( ( el ) => {
		el.classList.remove( 'flinkform-field--has-error' );
	} );
	form.querySelectorAll( '[aria-invalid="true"]' ).forEach( ( el ) => {
		el.removeAttribute( 'aria-invalid' );
	} );
}

// ---------------------------------------------------------------------
// Submit feedback — loading state + success-card focus.
//
// 1. When a form's POST navigation actually starts (submit event that
//    nobody preventDefault-ed — validation guards run earlier in the
//    capture phase), the submit button gets `.is-loading` + disabled +
//    aria-busy so slow servers don't leave the visitor without feedback.
//    The mutation is deferred a tick so disabling the button can never
//    interfere with the submission itself.
// 2. After the success redirect, the server renders the success card
//    (tabindex="-1"). We move focus to it so screen readers announce
//    the confirmation and the page scrolls to the right spot. Smooth
//    scrolling only under no-preference.
// ---------------------------------------------------------------------
function initSubmitFeedback() {
	document.querySelectorAll( '.flinkform-form__form' ).forEach( ( form ) => {
		form.addEventListener( 'submit', ( event ) => {
			if ( event.defaultPrevented ) {
				return;
			}
			window.setTimeout( () => {
				form.querySelectorAll( '.flinkform-form__submit' ).forEach( ( btn ) => {
					btn.classList.add( 'is-loading' );
					btn.setAttribute( 'aria-busy', 'true' );
					btn.disabled = true;
				} );
			}, 0 );
		} );
	} );

	// Restore submit buttons when the page is served from the back/forward
	// cache — otherwise navigating back to the form leaves a disabled,
	// spinning button behind.
	window.addEventListener( 'pageshow', ( event ) => {
		if ( ! event.persisted ) {
			return;
		}
		document.querySelectorAll( '.flinkform-form__submit.is-loading' ).forEach( ( btn ) => {
			btn.classList.remove( 'is-loading' );
			btn.removeAttribute( 'aria-busy' );
			btn.disabled = false;
		} );
	} );

	const success = document.querySelector( '.flinkform-form__success' );
	if ( success ) {
		const reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		success.focus( { preventScroll: true } );
		success.scrollIntoView( {
			behavior: reduceMotion ? 'auto' : 'smooth',
			block: 'center',
		} );
	}
}

function initConditionalLogic() {
	const forms = document.querySelectorAll( '.flinkform-form__form' );
	forms.forEach( ( form ) => {
		const fieldWrappers   = form.querySelectorAll( '[data-flinkform-condition]' );
		const stepWrappers    = form.querySelectorAll( '.flinkform-form__step[data-flinkform-step-condition]' );
		const submitButtons   = form.querySelectorAll( '.flinkform-form__submit[data-flinkform-submit-condition]' );

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
					const raw = btn.getAttribute( 'data-flinkform-submit-condition' );
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
		const raw = wrapper.getAttribute( 'data-flinkform-condition' );
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

	// Step-level conditions (Phase 7c): set a `data-flinkform-skipped`
	// marker on the wrapper so the Interactivity action can read the
	// up-to-date skip state when the user clicks Next / Back. The
	// step's own visibility is still driven by the multi-step
	// `state.isNotCurrentStep` binding — skipping plus current-step
	// matching are two independent toggles.
	let skippedChanged = false;
	stepWrappers.forEach( ( wrapper ) => {
		const raw = wrapper.getAttribute( 'data-flinkform-step-condition' );
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
		const isSkipped  = wrapper.getAttribute( 'data-flinkform-skipped' ) === 'true';

		if ( ! shouldShow ) {
			if ( ! isSkipped ) {
				wrapper.setAttribute( 'data-flinkform-skipped', 'true' );
				skippedChanged = true;
			}
			// Disable inputs in skipped steps — same reasoning as
			// hidden fields: a skipped step's values must not reach
			// the server through the form's native submit.
			toggleInputs( wrapper, true );
		} else {
			if ( isSkipped ) {
				wrapper.removeAttribute( 'data-flinkform-skipped' );
				skippedChanged = true;
			}
			toggleInputs( wrapper, false );
		}
	} );

	// Notify the Interactivity store that the skipped-set has shifted
	// so it can recompute the progress indicator. The wrapper-level
	// `data-wp-on--flinkform-skipped-changed` binding turns this custom
	// event into a call to `actions.onSkippedChanged`, where the
	// progress context values get re-derived inside an element-scope
	// that getContext() can actually resolve.
	if ( skippedChanged ) {
		const wrapper = form.closest( '.flinkform-form' );
		if ( wrapper ) {
			wrapper.dispatchEvent( new CustomEvent( 'flinkform-skipped-changed', { bubbles: false } ) );
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
			const raw = btn.getAttribute( 'data-flinkform-submit-condition' );
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
			if ( ! el.hasAttribute( 'data-flinkform-was-disabled' ) ) {
				el.setAttribute( 'data-flinkform-was-disabled', el.disabled ? '1' : '0' );
			}
			el.disabled = true;
		} else {
			// Only re-enable if WE disabled it (not if the markup
			// shipped it as disabled, e.g. read-only state from the
			// server). The marker tells us which.
			if ( el.getAttribute( 'data-flinkform-was-disabled' ) === '0' ) {
				el.disabled = false;
			}
			el.removeAttribute( 'data-flinkform-was-disabled' );
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
		// Field inputs are emitted as `flinkform_field[<name>]` — extract
		// the inner name so the values map matches the field-name shape
		// the conditional-logic rules reference (and the server's
		// `$clean` map uses too).
		const match = rawName.match( /^flinkform_field\[([^\]]+)\](\[\])?$/ );
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
 * Mirror of `Flinkform\Conditions\RuleEvaluator::should_show()` in JS.
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
			const wrapper = getElement().ref.closest( '.flinkform-form' );
			if ( ! wrapper ) {
				return;
			}

			const currentStepEl = wrapper.querySelector(
				`.flinkform-form__step[data-step-index="${ ctx.currentStep }"]`
			);
			if ( ! currentStepEl ) {
				return;
			}

			// Reset client-side error state from any previous attempt so the
			// new check starts clean.
			clearStepErrors( currentStepEl );

			// Native constraint-invalid controls, plus required checkbox
			// groups that the :invalid selector can't see (group semantics).
			const invalid = Array.from( currentStepEl.querySelectorAll( ':invalid' ) );
			const missingGroups = requiredCheckboxGroupsMissing( currentStepEl );

			if ( invalid.length > 0 || missingGroups.length > 0 ) {
				// Persistent, screen-reader-announced messages on every field —
				// not just the browser's transient native tooltip. validationMessage
				// is already localised by the browser; group messages come from a
				// server-translated data attribute.
				invalid.forEach( ( field ) => {
					showFieldError( field, field.validationMessage );
				} );
				missingGroups.forEach( ( group ) => markGroupError( group ) );

				// Native tooltip + focus on the first offending control.
				if ( invalid[ 0 ] ) {
					invalid[ 0 ].reportValidity();
				}
				const first = invalid[ 0 ] || missingGroups[ 0 ];
				if ( first && typeof first.focus === 'function' ) {
					first.focus();
				}
				return;
			}

			const target = findNextVisibleStep( wrapper, ctx.currentStep, ctx.totalSteps );
			if ( null !== target ) {
				ctx.currentStep = target;
				syncProgressContext( ctx, wrapper );
				announceStepChange( wrapper, ctx.progressLabel );
				deferFocus( wrapper, ctx.currentStep );
			}
		},

		prevStep() {
			const ctx = getContext();
			if ( ctx.currentStep <= 0 ) {
				return;
			}
			const wrapper = getElement().ref.closest( '.flinkform-form' );
			const target  = findPrevVisibleStep( wrapper, ctx.currentStep );
			if ( null === target ) {
				return;
			}
			ctx.currentStep = target;
			syncProgressContext( ctx, wrapper );
			if ( wrapper ) {
				announceStepChange( wrapper, ctx.progressLabel );
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
				`.flinkform-form__step[data-step-index="${ ctx.currentStep }"]`
			);
			const isCurrentNowSkipped = currentEl && currentEl.getAttribute( 'data-flinkform-skipped' ) === 'true';

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
 * Clear all client-side validation error state from a step before re-checking.
 *
 * @param {HTMLElement} stepEl
 */
function clearStepErrors( stepEl ) {
	stepEl
		.querySelectorAll( '[aria-invalid="true"]' )
		.forEach( ( el ) => el.removeAttribute( 'aria-invalid' ) );
	stepEl
		.querySelectorAll( '.flinkform-field__error--client' )
		.forEach( ( el ) => el.remove() );
	stepEl
		.querySelectorAll( '.flinkform-field--has-error' )
		.forEach( ( el ) => el.classList.remove( 'flinkform-field--has-error' ) );
}

/**
 * Required checkbox GROUPS with nothing checked — the `:invalid` selector
 * can't catch these because group requiredness isn't an HTML `required`.
 * The fieldset carries `data-flinkform-required` when required (see render.php).
 *
 * @param {HTMLElement} stepEl
 * @return {HTMLElement[]} Offending group fieldsets.
 */
function requiredCheckboxGroupsMissing( stepEl ) {
	return Array.from( stepEl.querySelectorAll( '[data-flinkform-required]' ) ).filter(
		( group ) => ! group.querySelector( 'input[type="checkbox"]:checked' )
	);
}

/**
 * Render a persistent, role="alert" error for a single control + wire
 * aria-describedby/aria-invalid so assistive tech announces it.
 *
 * @param {HTMLElement} field   The invalid control.
 * @param {string}      message Localised message (browser validationMessage).
 */
function showFieldError( field, message ) {
	const wrapper = field.closest( '.flinkform-field' );
	if ( wrapper ) {
		field.setAttribute( 'aria-invalid', 'true' );
		renderFieldError( wrapper, field, message );
	}
}

/**
 * Render a persistent error for a required checkbox group (no single control).
 *
 * @param {HTMLElement} group The fieldset.
 */
function markGroupError( group ) {
	renderFieldError( group, null, group.getAttribute( 'data-flinkform-required-message' ) || '' );
}

/**
 * Inject/update a `.flinkform-field__error--client` message inside a field
 * wrapper. Mirrors the server-side error markup so styling + semantics match.
 *
 * @param {HTMLElement}      wrapper The `.flinkform-field` container.
 * @param {HTMLElement|null} field   The control to link (null for groups).
 * @param {string}           message The message text.
 */
function renderFieldError( wrapper, field, message ) {
	if ( ! message ) {
		return;
	}
	wrapper.classList.add( 'flinkform-field--has-error' );

	let errorEl = wrapper.querySelector( '.flinkform-field__error--client' );
	if ( ! errorEl ) {
		const name = wrapper.getAttribute( 'data-flinkform-field-name' ) || 'field';
		errorEl = document.createElement( 'p' );
		errorEl.className = 'flinkform-field__error flinkform-field__error--client';
		errorEl.setAttribute( 'role', 'alert' );
		errorEl.id = 'flinkform-client-error-' + name;
		wrapper.appendChild( errorEl );
	}
	errorEl.textContent = message;

	if ( field ) {
		const described = ( field.getAttribute( 'aria-describedby' ) || '' )
			.split( ' ' )
			.filter( Boolean );
		if ( ! described.includes( errorEl.id ) ) {
			described.push( errorEl.id );
			field.setAttribute( 'aria-describedby', described.join( ' ' ) );
		}
	}
}

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
	// we count the `data-flinkform-skipped="true"` markers the
	// standalone listener leaves on currently-skipped steps and
	// adjust the indicator's totals + position so the user sees
	// "Step 2 of 3" turn into "Step 2 of 2" the moment a step drops
	// out of the flow. Without the wrapper (calls predating 7c) we
	// fall back to the raw markup totals.
	let totalSkipped         = 0;
	let skippedBeforeCurrent = 0;
	if ( wrapper ) {
		wrapper.querySelectorAll( '.flinkform-form__step[data-flinkform-skipped="true"]' ).forEach( ( s ) => {
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
	ctx.progressBarStyle = `--flinkform-progress-percent:${ ( ( effectiveCurrent / effectiveTotal ) * 100 ).toFixed( 2 ) }%`;

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
 * over any `[data-flinkform-skipped="true"]` step. Returns null when
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
		const step = wrapper.querySelector( `.flinkform-form__step[data-step-index="${ i }"]` );
		if ( step && step.getAttribute( 'data-flinkform-skipped' ) === 'true' ) {
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
		const step = wrapper.querySelector( `.flinkform-form__step[data-step-index="${ i }"]` );
		if ( step && step.getAttribute( 'data-flinkform-skipped' ) === 'true' ) {
			continue;
		}
		return i;
	}
	return null;
}

/**
 * Announce the current step to screen readers via the aria-live region
 * that render.php injects into multi-step forms.
 *
 * @param {HTMLElement} wrapper      The form wrapper element.
 * @param {string}      progressLabel The "Step X of Y" text to announce.
 */
function announceStepChange( wrapper, progressLabel ) {
	const region = wrapper.querySelector( '[data-flinkform-step-announce]' );
	if ( region ) {
		// Clear first, then set on next tick — screen readers re-announce
		// identical text only when the node content actually changes.
		region.textContent = '';
		requestAnimationFrame( () => {
			region.textContent = progressLabel;
		} );
	}
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
		`.flinkform-form__step[data-step-index="${ stepIndex }"]`
	);
	if ( ! step ) {
		return;
	}
	const focusable = step.querySelector(
		'input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), button:not([disabled])'
	);
	if ( focusable && typeof focusable.focus === 'function' ) {
		focusable.focus();
	} else {
		// No focusable field in this step (e.g. display-only content
		// or all fields are conditional-hidden). Focus the step
		// container itself so keyboard users land inside the new step.
		if ( ! step.hasAttribute( 'tabindex' ) ) {
			step.setAttribute( 'tabindex', '-1' );
		}
		step.focus();
	}
}

// ---------------------------------------------------------------------
// Built-in spam challenge — proof-of-work solver (Phase B-a)
//
// Each protected form renders a `.flinkform-form__spam` block with
// `data-flinkform-pow-salt` + `data-flinkform-pow-difficulty` attributes and
// a hidden `[data-flinkform-spam-solution]` input. We find the salt/
// difficulty, compute sha256(salt + n) for increasing n until the hex
// hash matches the leading-zero requirement, then write the winning n
// into the hidden input. The visible math fallback is hidden once we
// have a solution so JS-on visitors never see it.
//
// Compute strategy: a Web Worker (spawned from a same-origin Blob URL)
// runs the hash loop completely off the main thread — zero jank, no
// starved click handlers. When the worker can't start (restrictive CSP
// blocking blob: workers, ancient browser), we fall back to the
// main-thread async loop with periodic yields. The earlier main-thread-
// only strategy at difficulty 18 hashed ~260k digests through
// crypto.subtle's per-call overhead — tens of seconds on mobile and a
// flooded event loop that made multi-step buttons feel dead. Visitors
// on browsers without Web Crypto see the math fallback and answer it
// manually.
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
	const blocks = document.querySelectorAll( '.flinkform-form__spam[data-flinkform-spam]' );
	blocks.forEach( ( block ) => {
		const salt       = block.getAttribute( 'data-flinkform-pow-salt' ) || '';
		const difficulty = parseInt(
			block.getAttribute( 'data-flinkform-pow-difficulty' ) || '0',
			10
		);
		const solutionInput = block.querySelector( '[data-flinkform-spam-solution]' );
		const mathRow       = block.querySelector( '[data-flinkform-spam-math]' );

		if ( ! salt || ! difficulty || ! solutionInput ) {
			// Mis-rendered block — leave the math fallback visible so
			// the visitor can still submit by answering the question.
			return;
		}

		if ( ! window.crypto || ! window.crypto.subtle ) {
			// Browser lacks Web Crypto — same fallback path.
			return;
		}

		solvePoWInWorker( salt, difficulty )
			.catch( () => solvePoW( salt, difficulty ) )
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
						// Drop `required` too — the row is now hidden, and a
						// hidden required field would block form submission.
						mathInput.removeAttribute( 'required' );
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
 * Compute the PoW solution in a dedicated Web Worker.
 *
 * The worker is spawned from a same-origin Blob URL (no extra asset, no
 * remote code) and runs the identical hash loop without ever touching
 * the main thread. Rejects when the worker can't start (CSP without
 * blob: worker-src) or errors — the caller falls back to the
 * main-thread loop below.
 *
 * @param {string} salt        base64url-encoded server-supplied salt
 * @param {number} difficulty  leading-zero bits required
 * @returns {Promise<number>}  the winning integer
 */
function solvePoWInWorker( salt, difficulty ) {
	return new Promise( ( resolve, reject ) => {
		let worker;
		let blobUrl;
		try {
			const src = `
				self.onmessage = async ( e ) => {
					const { salt, difficulty } = e.data;
					const encoder = new TextEncoder();
					const fullHex = Math.floor( difficulty / 4 );
					const extraBits = difficulty % 4;
					const mask = ( 0x0f << ( 4 - extraBits ) ) & 0x0f;
					const hexChars = '0123456789abcdef';
					let n = 0;
					try {
						for ( ;; ) {
							const digest = await self.crypto.subtle.digest( 'SHA-256', encoder.encode( salt + '|' + n ) );
							const bytes = new Uint8Array( digest );
							let hex = '';
							for ( let i = 0; i <= fullHex; i++ ) {
								hex += hexChars[ bytes[ i ] >> 4 ] + hexChars[ bytes[ i ] & 0x0f ];
							}
							let ok = true;
							for ( let i = 0; i < fullHex; i++ ) {
								if ( hex[ i ] !== '0' ) { ok = false; break; }
							}
							if ( ok && ( extraBits === 0 || ( parseInt( hex[ fullHex ], 16 ) & mask ) === 0 ) ) {
								self.postMessage( { solution: n } );
								return;
							}
							n++;
						}
					} catch ( err ) {
						self.postMessage( { error: String( err ) } );
					}
				};
			`;
			blobUrl = URL.createObjectURL( new Blob( [ src ], { type: 'application/javascript' } ) );
			worker  = new Worker( blobUrl );
		} catch ( e ) {
			if ( blobUrl ) {
				URL.revokeObjectURL( blobUrl );
			}
			reject( e );
			return;
		}

		const cleanup = () => {
			worker.terminate();
			URL.revokeObjectURL( blobUrl );
		};

		worker.onmessage = ( e ) => {
			cleanup();
			if ( e.data && typeof e.data.solution === 'number' ) {
				resolve( e.data.solution );
			} else {
				reject( new Error( e.data && e.data.error ? e.data.error : 'PoW worker failed' ) );
			}
		};
		worker.onerror = ( e ) => {
			cleanup();
			reject( e );
		};

		worker.postMessage( { salt, difficulty } );
	} );
}

/**
 * Main-thread fallback: compute the PoW solution.
 *
 * Loops with a microtask yield every 256 iterations so the UI thread
 * stays responsive. crypto.subtle.digest is async; the inner await
 * already lets the event loop breathe, but Chromium specifically can
 * starve repaint cycles when the yields are too tight.
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
		if ( ( n & 255 ) === 0 ) {
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
