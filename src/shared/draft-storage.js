/**
 * Multi-step draft in sessionStorage (1.15.0): a reload, an accidental
 * back-swipe or a crashed tab halfway through a three-step form no longer
 * throws away what the visitor typed.
 *
 * Deliberately narrow:
 *   - sessionStorage, not localStorage: the draft lives in this tab only
 *     and is gone when the tab closes (nothing lingers on a shared PC);
 *   - only the visitor's own answers: no consent (must be given fresh), no
 *     hidden, token, nonce, timestamp, spam, file or honeypot inputs;
 *   - restored only into EMPTY controls, so server-rendered values (a
 *     retry page after an error) always win;
 *   - removed once the form reports success.
 *
 * Pure DOM module, tested in tests/draft-storage.mjs.
 */

const PREFIX = 'flinkform-draft:';

/** Inputs that never go into a draft. */
function isStorable( el ) {
	if ( ! el.name || ! el.name.startsWith( 'flinkform_field[' ) || el.disabled ) {
		return false;
	}
	const type = ( el.type || '' ).toLowerCase();
	if ( [ 'hidden', 'file', 'password', 'submit', 'button' ].includes( type ) ) {
		return false;
	}
	return ! el.closest( '.flinkform-field--consent' );
}

export function draftKey( formId ) {
	return PREFIX + formId;
}

/**
 * Snapshot of the storable controls: name → string | string[].
 *
 * @param {HTMLFormElement} form
 * @returns {Object<string, string|string[]>}
 */
export function collectDraft( form ) {
	const data = {};
	for ( const el of form.elements ) {
		if ( ! isStorable( el ) ) {
			continue;
		}
		const type = ( el.type || '' ).toLowerCase();
		if ( type === 'checkbox' || type === 'radio' ) {
			if ( el.name.endsWith( '[]' ) || type === 'checkbox' ) {
				data[ el.name ] = data[ el.name ] || [];
				if ( el.checked ) {
					data[ el.name ].push( el.value );
				}
			} else if ( el.checked ) {
				data[ el.name ] = el.value;
			}
		} else if ( el.tagName === 'SELECT' && el.multiple ) {
			data[ el.name ] = [ ...el.selectedOptions ].map( ( o ) => o.value );
		} else if ( el.value !== '' ) {
			data[ el.name ] = el.value;
		}
	}
	for ( const k of Object.keys( data ) ) {
		if ( Array.isArray( data[ k ] ) && data[ k ].length === 0 ) {
			delete data[ k ];
		}
	}
	return data;
}

/**
 * Put a draft back into empty controls. Returns the controls it changed,
 * so the caller can fire change events (conditional logic listens).
 *
 * @param {HTMLFormElement} form
 * @param {Object<string, string|string[]>} data
 * @returns {Element[]}
 */
export function applyDraft( form, data ) {
	const changed = [];
	if ( ! data || typeof data !== 'object' ) {
		return changed;
	}
	// Groups the visitor (or the server) already answered stay as they are.
	// Decided once, up front: deciding per box would stop after the first
	// restored tick of a checkbox group.
	const answered = new Set(
		[ ...form.elements ].filter( ( e ) => e.checked ).map( ( e ) => e.name )
	);
	for ( const el of form.elements ) {
		if ( ! isStorable( el ) || ! ( el.name in data ) ) {
			continue;
		}
		const want = data[ el.name ];
		const type = ( el.type || '' ).toLowerCase();
		if ( type === 'checkbox' || type === 'radio' ) {
			const values = Array.isArray( want ) ? want : [ want ];
			if ( ! answered.has( el.name ) && values.includes( el.value ) ) {
				el.checked = true;
				changed.push( el );
			}
		} else if ( el.tagName === 'SELECT' && el.multiple ) {
			if ( el.selectedOptions.length === 0 && Array.isArray( want ) ) {
				[ ...el.options ].forEach( ( o ) => {
					o.selected = want.includes( o.value );
				} );
				changed.push( el );
			}
		} else if ( el.value === '' && typeof want === 'string' ) {
			el.value = want;
			changed.push( el );
		}
	}
	return changed;
}
