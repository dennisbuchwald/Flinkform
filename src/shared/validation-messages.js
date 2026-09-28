/**
 * Client-side validation messages in the SITE's language (1.15.0).
 *
 * `field.validationMessage` is written by the browser, in the BROWSER's
 * language: an English site visited with a German Chrome said "Wähle ein
 * Element in der Liste aus." under an English label. The texts now come
 * from the server (translated like every other string, du/Sie included)
 * via `data-flinkform-messages` on the form, and this module only decides
 * WHICH text applies, from `field.validity`.
 *
 * Pure module on purpose, so `tests/validation-messages.mjs` can test it
 * without the view script.
 */

/**
 * Read the message catalogue off the form.
 *
 * @param {HTMLFormElement|null} form
 * @returns {Object<string,string>}
 */
export function readMessages( form ) {
	if ( ! form ) {
		return {};
	}
	try {
		const parsed = JSON.parse( form.getAttribute( 'data-flinkform-messages' ) || '{}' );
		return parsed && typeof parsed === 'object' ? parsed : {};
	} catch ( e ) {
		return {};
	}
}

/**
 * Human form of a min/max bound. Dates are formatted in the page's
 * language, everything else is passed through.
 *
 * @param {string} raw  Attribute value.
 * @param {string} type Input type.
 * @param {string} lang BCP 47 tag of the page.
 * @returns {string}
 */
export function formatBound( raw, type, lang ) {
	if ( type === 'date' && /^\d{4}-\d{2}-\d{2}$/.test( raw ) ) {
		try {
			const [ y, m, d ] = raw.split( '-' ).map( Number );
			return new Intl.DateTimeFormat( lang || undefined, { dateStyle: 'medium', timeZone: 'UTC' } )
				.format( new Date( Date.UTC( y, m - 1, d ) ) );
		} catch ( e ) {
			return raw;
		}
	}
	return raw;
}

/**
 * Pick the message for an invalid control.
 *
 * Returns '' for a valid control. Never returns the browser's
 * validationMessage; when no specific text fits, the generic one does.
 *
 * @param {HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement} field
 * @param {Object<string,string>} messages Catalogue from readMessages().
 * @param {string} [lang] Page language for date bounds.
 * @returns {string}
 */
export function messageFor( field, messages, lang = '' ) {
	const v = field && field.validity;
	if ( ! v || v.valid ) {
		return '';
	}
	const m = messages || {};
	const generic = m.invalid || '';
	const tag = ( field.tagName || '' ).toLowerCase();
	const type = tag === 'select' ? 'select' : ( field.type || '' ).toLowerCase();
	const pick = ( key ) => m[ key ] || generic;
	const fill = ( key, value ) => pick( key ).replace( '%s', value );

	if ( v.valueMissing ) {
		if ( type === 'select' || type === 'radio' ) {
			return pick( 'choose' );
		}
		if ( type === 'checkbox' ) {
			return pick( 'check' );
		}
		return pick( 'required' );
	}
	if ( v.badInput ) {
		return pick( type === 'date' ? 'date' : 'number' );
	}
	if ( v.typeMismatch ) {
		if ( type === 'email' ) {
			return pick( 'email' );
		}
		if ( type === 'url' ) {
			return pick( 'url' );
		}
		return generic;
	}
	if ( v.patternMismatch ) {
		return pick( type === 'tel' ? 'tel' : 'invalid' );
	}
	if ( v.rangeUnderflow ) {
		return fill( type === 'date' ? 'dateMin' : 'min', formatBound( field.getAttribute( 'min' ) || '', type, lang ) );
	}
	if ( v.rangeOverflow ) {
		return fill( type === 'date' ? 'dateMax' : 'max', formatBound( field.getAttribute( 'max' ) || '', type, lang ) );
	}
	if ( v.tooShort ) {
		return fill( 'tooShort', field.getAttribute( 'minlength' ) || '' );
	}
	if ( v.tooLong ) {
		return fill( 'tooLong', field.getAttribute( 'maxlength' ) || '' );
	}
	if ( v.stepMismatch ) {
		return pick( 'step' );
	}
	return generic;
}
