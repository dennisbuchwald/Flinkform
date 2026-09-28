/**
 * Values a condition can compare a field against, for the editor's value
 * dropdown (1.15.0). Choice fields only compare meaningfully against the
 * option VALUES they submit, and a typed-in "Relaunch" where the stored
 * value is "relaunch" silently never matched. Pure, see
 * tests/condition-choices.mjs.
 *
 * @param {string} blockName  e.g. 'flinkform/field-select'.
 * @param {Object} attributes Block attributes.
 * @param {Function} __       Translation function.
 * @returns {Array<{value: string, label: string}>|null} Null = free text.
 */
export function choicesFor( blockName, attributes, __ ) {
	if ( [ 'flinkform/field-select', 'flinkform/field-radio', 'flinkform/field-checkbox' ].includes( blockName ) ) {
		const options = Array.isArray( attributes?.options ) ? attributes.options : [];
		const seen = new Set();
		return options
			.filter( ( o ) => o && typeof o.value === 'string' && o.value !== '' && ! seen.has( o.value ) && seen.add( o.value ) )
			.map( ( o ) => ( { value: o.value, label: o.label && o.label !== o.value ? `${ o.label } (${ o.value })` : o.value } ) );
	}
	if ( blockName === 'flinkform/field-toggle' || blockName === 'flinkform/field-consent' ) {
		return [ { value: '1', label: __( 'checked', 'flinkform' ) } ];
	}
	return null;
}

/**
 * Operators that compare against one concrete value, where a dropdown of
 * the field's choices makes sense.
 */
export const CHOICE_OPERATORS = new Set( [ 'is', 'is_not', 'contains', 'not_contains' ] );

/**
 * Does this block carry an active conditional rule? Drives the editor badge.
 *
 * @param {string} blockName
 * @param {Object} attributes
 * @returns {boolean}
 */
export function hasActiveCondition( blockName, attributes ) {
	if ( ! blockName || ! blockName.startsWith( 'flinkform/' ) || blockName === 'flinkform/form' ) {
		return false;
	}
	const rules = attributes?.conditionalLogic;
	return !! ( rules && rules.enabled && Array.isArray( rules.rules ) && rules.rules.length > 0 );
}
