/**
 * Required-checkbox-group validation.
 *
 * "Tick at least one of these" is the one rule HTML cannot express: there
 * is no `required` that spans a group of checkboxes. So the fieldset
 * carries `data-flinkform-required` (see field-checkbox/render.php) and we
 * check it ourselves.
 *
 * Doing it ourselves means also reproducing what the browser does for free
 * everywhere else — most importantly that a disabled control is barred from
 * constraint validation and can therefore never be the reason a form
 * refuses to submit. Lives in shared/ so tests can exercise the real
 * functions instead of a copy (same reasoning as shared/rule-evaluator.js).
 *
 * @package Flinkform
 * @since 1.12.2
 */

/**
 * May this group block the submission at all?
 *
 * Only when the visitor could actually act on it: the group is not hidden,
 * and it holds at least one checkbox they could tick. Every field hidden by
 * conditional logic gets its inputs disabled (view.js toggleInputs), which
 * is exactly the case the browser bars from validation.
 *
 * @param {HTMLElement} group Fieldset carrying data-flinkform-required.
 * @return {boolean}
 */
export function isGroupEnforceable( group ) {
	if ( group.hidden || group.closest( '[hidden]' ) ) {
		return false;
	}
	return !! group.querySelector( 'input[type="checkbox"]:not([disabled])' );
}

/**
 * Required checkbox groups within a scope that have nothing ticked and can
 * legitimately block the submission.
 *
 * The enforceability filter is the fix for a reported outage: a group
 * belonging to the other branch of an either/or form stayed hidden and
 * unticked, kept being flagged, and blocked the submit — while its error
 * message rendered into the hidden wrapper, so the button appeared to do
 * nothing at all. The server had already dropped the field
 * (Conditions\VisibilityResolver), so the two sides disagreed about the
 * same form.
 *
 * @param {HTMLElement} scopeEl Step element, or the form for single-step forms.
 * @return {HTMLElement[]} Offending group fieldsets, in document order.
 */
export function requiredCheckboxGroupsMissing( scopeEl ) {
	return Array.from( scopeEl.querySelectorAll( '[data-flinkform-required]' ) ).filter(
		( group ) => isGroupEnforceable( group ) && ! group.querySelector( 'input[type="checkbox"]:checked' )
	);
}
