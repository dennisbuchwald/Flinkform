#!/usr/bin/env node
/**
 * Regression tests for required-checkbox-group validation.
 *
 * The outage these pin: a form with two branches ("private person" vs
 * "company") had a required checkbox group belonging to the company
 * branch on the LAST step. Choosing the private branch hid the group —
 * and the client-side check kept flagging it anyway, so the submit
 * button silently did nothing while the error message rendered into the
 * hidden wrapper. The server had already dropped the field, so it would
 * have accepted the very submission the browser refused to send.
 *
 * The rule being pinned: our hand-written group check must reproduce
 * what the browser applies to every other field for free — a disabled
 * control is barred from constraint validation and can never be the
 * reason a form refuses to submit.
 *
 * Run:  node tests/group-validation.mjs
 *
 * @package Flinkform
 */

import { JSDOM } from 'jsdom';
import { requiredCheckboxGroupsMissing, isGroupEnforceable } from '../src/shared/group-validation.js';

let passed = 0;
let failed = 0;

function check( label, ok, detail = '' ) {
	if ( ok ) {
		passed++;
		return;
	}
	failed++;
	console.log( `FAIL: ${ label }${ detail ? ` — ${ detail }` : '' }` );
}

/**
 * Build a scope containing one group.
 *
 * @param {object} opts
 * @param {boolean} opts.hidden          Wrapper carries the hidden attribute.
 * @param {boolean} opts.disabled        Every checkbox is disabled (what toggleInputs does).
 * @param {boolean} opts.checked         One box is ticked.
 * @param {boolean} opts.hiddenAncestor  Group sits inside a hidden container.
 * @return {HTMLElement} The scope element.
 */
function scopeWithGroup( { hidden = false, disabled = false, checked = false, hiddenAncestor = false } = {} ) {
	const dom = new JSDOM( `<!doctype html><body>
		<div id="scope">
			<div id="outer"${ hiddenAncestor ? ' hidden' : '' }>
				<fieldset data-flinkform-required="1"
				          data-flinkform-required-message="Bitte mindestens eine Option wählen."
				          data-flinkform-field-name="wofuer"${ hidden ? ' hidden' : '' }>
					<label><input type="checkbox" value="a"${ disabled ? ' disabled' : '' }${ checked ? ' checked' : '' }> A</label>
					<label><input type="checkbox" value="b"${ disabled ? ' disabled' : '' }> B</label>
				</fieldset>
			</div>
		</div>
	</body>` );
	return dom.window.document.getElementById( 'scope' );
}

// --- The reported outage --------------------------------------------------
// Hidden by conditional logic (wrapper hidden + inputs disabled), nothing
// ticked. This MUST NOT block the submission.

const versteckt = scopeWithGroup( { hidden: true, disabled: true } );
check(
	'a conditionally hidden group does not block the submit',
	requiredCheckboxGroupsMissing( versteckt ).length === 0,
	`${ requiredCheckboxGroupsMissing( versteckt ).length } group(s) flagged`
);

// Belt and braces: each half of the hidden state on its own is enough.
check(
	'wrapper hidden alone is enough to skip the group',
	requiredCheckboxGroupsMissing( scopeWithGroup( { hidden: true } ) ).length === 0
);
check(
	'all boxes disabled alone is enough to skip the group',
	requiredCheckboxGroupsMissing( scopeWithGroup( { disabled: true } ) ).length === 0
);
check(
	'a group inside a hidden ancestor is skipped',
	requiredCheckboxGroupsMissing( scopeWithGroup( { hiddenAncestor: true, disabled: true } ) ).length === 0
);

// --- The rule still has to work ------------------------------------------
// A visible, enabled, unticked group is exactly what this check exists for.

const sichtbarLeer = scopeWithGroup();
check(
	'a visible group with nothing ticked still blocks',
	requiredCheckboxGroupsMissing( sichtbarLeer ).length === 1,
	`${ requiredCheckboxGroupsMissing( sichtbarLeer ).length } group(s) flagged`
);
check(
	'the flagged element is the fieldset carrying the message',
	requiredCheckboxGroupsMissing( sichtbarLeer )[ 0 ]?.getAttribute( 'data-flinkform-required-message' )
		=== 'Bitte mindestens eine Option wählen.'
);
check(
	'a visible group with a tick passes',
	requiredCheckboxGroupsMissing( scopeWithGroup( { checked: true } ) ).length === 0
);

// A group the author never marked required is none of our business.
const ohneAttribut = new JSDOM(
	`<!doctype html><body><div id="s"><fieldset><input type="checkbox"></fieldset></div></body>`
).window.document.getElementById( 's' );
check( 'an optional group is never flagged', requiredCheckboxGroupsMissing( ohneAttribut ).length === 0 );

// --- Both branches of an either/or form, the real-world shape --------------

const beideZweige = new JSDOM( `<!doctype html><body>
	<div id="step">
		<fieldset data-flinkform-required="1" data-flinkform-field-name="privat-wofuer">
			<input type="checkbox" value="p1" checked>
		</fieldset>
		<fieldset data-flinkform-required="1" data-flinkform-field-name="unternehmen-wofuer" hidden>
			<input type="checkbox" value="u1" disabled>
		</fieldset>
	</div>
</body>` ).window.document.getElementById( 'step' );
check(
	'the active branch passes while the hidden branch stays out of it',
	requiredCheckboxGroupsMissing( beideZweige ).length === 0
);

// Flip it: the visible branch is the one left empty.
const zweigLeer = new JSDOM( `<!doctype html><body>
	<div id="step">
		<fieldset data-flinkform-required="1" data-flinkform-field-name="privat-wofuer">
			<input type="checkbox" value="p1">
		</fieldset>
		<fieldset data-flinkform-required="1" data-flinkform-field-name="unternehmen-wofuer" hidden>
			<input type="checkbox" value="u1" disabled>
		</fieldset>
	</div>
</body>` ).window.document.getElementById( 'step' );
const geflaggt = requiredCheckboxGroupsMissing( zweigLeer );
check( 'exactly one group is flagged', geflaggt.length === 1 );
check(
	'and it is the visible one, never the hidden branch',
	geflaggt[ 0 ]?.getAttribute( 'data-flinkform-field-name' ) === 'privat-wofuer',
	geflaggt[ 0 ]?.getAttribute( 'data-flinkform-field-name' )
);

// --- isGroupEnforceable on its own ----------------------------------------

check( 'enforceable: visible + enabled', isGroupEnforceable( scopeWithGroup().querySelector( 'fieldset' ) ) === true );
check( 'not enforceable: hidden', isGroupEnforceable( scopeWithGroup( { hidden: true } ).querySelector( 'fieldset' ) ) === false );
check( 'not enforceable: all disabled', isGroupEnforceable( scopeWithGroup( { disabled: true } ).querySelector( 'fieldset' ) ) === false );

// --- Summary ----------------------------------------------------------------

console.log( '' );
if ( failed > 0 ) {
	console.log( `${ failed } FAILED, ${ passed } passed.` );
	process.exit( 1 );
}
console.log( `All ${ passed } tests passed.` );
