/**
 * Block transforms between the three choice fields — radio, checkbox
 * group and select.
 *
 * User request: "bei einem Auswahlfeld festlegen, ob nur eine oder
 * mehrere Antworten erlaubt sind, ohne jedes Mal einen anderen Feldtyp
 * anzulegen". The Gutenberg-native answer is a block transform: switch
 * the block type from the toolbar and keep everything the author has
 * already built — options, label, required, help text, width, and the
 * conditional logic OTHER fields point at (which is why fieldName must
 * survive the switch, odd-looking prefix and all).
 *
 * Every block declares only its `to` side; with all three doing that,
 * each one offers the other two in the block switcher without duplicate
 * registrations.
 *
 * @package Flinkform
 */

import { createBlock, getBlockType } from '@wordpress/blocks';

const CHOICE_BLOCKS = [
	'flinkform/field-radio',
	'flinkform/field-checkbox',
	'flinkform/field-select',
];

/**
 * Attributes all three choice blocks share. Type-specific extras
 * (radio's display/buttonShape, select's multiple/placeholder,
 * checkbox's requiredMessage) deliberately fall away — the target
 * block's own defaults apply.
 */
const SHARED_ATTRIBUTES = [
	'label',
	'required',
	'helpText',
	'fieldName',
	'options',
	'fullWidth',
	'conditionalLogic',
];

/**
 * The subset of a block's attributes that should ride along.
 *
 * An untouched label stays behind: "Choose one" carried onto a checkbox
 * group would read wrong, and dropping it lets the target's own default
 * ("Choose any") take over. A label the author typed is kept verbatim.
 *
 * @param {string} sourceName Source block name.
 * @param {Object} attributes Source block attributes.
 * @returns {Object}
 */
function carriedAttributes( sourceName, attributes ) {
	const sourceDefaults = getBlockType( sourceName )?.attributes || {};
	const carried = {};

	SHARED_ATTRIBUTES.forEach( ( key ) => {
		if ( ! ( key in attributes ) ) {
			return;
		}
		if ( 'label' === key && attributes.label === sourceDefaults.label?.default ) {
			return;
		}
		carried[ key ] = attributes[ key ];
	} );

	return carried;
}

/**
 * Build the `transforms` setting for one choice block.
 *
 * @param {string} ownName The block registering the transforms.
 * @returns {Object}
 */
export function choiceTransforms( ownName ) {
	return {
		to: CHOICE_BLOCKS.filter( ( name ) => name !== ownName ).map( ( target ) => ( {
			type: 'block',
			blocks: [ target ],
			transform: ( attributes ) => createBlock( target, carriedAttributes( ownName, attributes ) ),
		} ) ),
	};
}
