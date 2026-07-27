/**
 * Display a block.json default label in the site language.
 *
 * Gutenberg only serialises an attribute once it differs from the default,
 * and those defaults are literals in a JSON file that never pass through
 * the i18n layer. A freshly inserted Date Field therefore carries the
 * label "Date" even on a German site.
 *
 * The frontend solves this centrally in Blocks\DefaultStrings, which fills
 * in the translated value for any attribute the author left alone. The
 * editor cannot use that: it reads the attribute directly, so it has to
 * recognise the untouched default itself. Comparing against the English
 * literal is the available signal here — the practical cost is that an
 * author who deliberately types the English default word for word sees the
 * translation instead, which is the same trade the address field has made
 * since 1.6.3.
 *
 * Display only: the stored attribute stays English so the markup keeps
 * meaning the same thing in every language, and the frontend keeps
 * translating it at render time.
 *
 * @package Flinkform
 * @since 1.8.3
 */

import { __ } from '@wordpress/i18n';

/**
 * Every English default this helper knows how to translate.
 *
 * The keys are the msgids, so they must match block.json character for
 * character; tests/default-strings-test.php asserts that they still do.
 * Listed as literal `__()` calls rather than looked up dynamically so the
 * string extractor can actually see them.
 *
 * @returns {Object<string, string>}
 */
const translations = () => ( {
	Address: __( 'Address', 'flinkform' ),
	'Choose any': __( 'Choose any', 'flinkform' ),
	'Choose one': __( 'Choose one', 'flinkform' ),
	Date: __( 'Date', 'flinkform' ),
	Email: __( 'Email', 'flinkform' ),
	'Hidden Field': __( 'Hidden Field', 'flinkform' ),
	'I agree': __( 'I agree', 'flinkform' ),
	Message: __( 'Message', 'flinkform' ),
	Number: __( 'Number', 'flinkform' ),
	Phone: __( 'Phone', 'flinkform' ),
	Section: __( 'Section', 'flinkform' ),
	Text: __( 'Text', 'flinkform' ),
	Website: __( 'Website', 'flinkform' ),
} );

/**
 * @param {string} value       The block attribute as stored.
 * @param {string} englishDefault The block.json default for that attribute.
 * @returns {string} The translation when the value is still the untouched
 *                   default, otherwise the value unchanged.
 */
export default function displayDefaultLabel( value, englishDefault ) {
	if ( value !== englishDefault ) {
		return value;
	}
	return translations()[ englishDefault ] ?? value;
}
