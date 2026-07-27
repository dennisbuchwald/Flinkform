/**
 * Resolve the colour of the surface an element sits on.
 *
 * Shared by the frontend (form-container/view.js) and the editor preview
 * (form-container/edit.js) so the floating-label notch cannot drift
 * between the two.
 *
 * The notch paints a strip behind the lifted label to cover the input's
 * top border, which only works if the colour matches the surface exactly.
 * Guess wrong and the label wears a visibly mismatched box — the classic
 * symptom being a white rectangle on a tinted page. So this returns null
 * rather than a guess whenever the surface is not a flat colour, and the
 * caller falls back to a geometry that needs no colour at all.
 *
 * @package Flinkform
 * @since 1.6.4
 */

/**
 * @param {string} value A computed `background-color`.
 * @returns {{r: number, g: number, b: number, a: number}|null} Null if it is
 *          not a plain rgb/rgba value.
 */
function parseRgb( value ) {
	const m = /^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:[\s,/]+([\d.%]+))?\s*\)$/i.exec( value || '' );
	if ( ! m ) {
		return null;
	}

	let alpha = 1;
	if ( m[ 4 ] !== undefined ) {
		alpha = m[ 4 ].endsWith( '%' ) ? parseFloat( m[ 4 ] ) / 100 : parseFloat( m[ 4 ] );
	}

	return {
		r: parseFloat( m[ 1 ] ),
		g: parseFloat( m[ 2 ] ),
		b: parseFloat( m[ 3 ] ),
		a: isNaN( alpha ) ? 1 : alpha,
	};
}

/**
 * Composite semi-transparent layers onto an opaque base.
 *
 * @param {Array<object>} layers Nearest-first list of translucent colours.
 * @param {object}        base   Opaque colour underneath them all.
 * @returns {string} `rgb(r, g, b)`
 */
function flatten( layers, base ) {
	// reduceRight folds the farthest layer onto the base first and then
	// works inwards, which is the order the browser paints them.
	const result = layers.reduceRight(
		( under, over ) => ( {
			r: over.r * over.a + under.r * ( 1 - over.a ),
			g: over.g * over.a + under.g * ( 1 - over.a ),
			b: over.b * over.a + under.b * ( 1 - over.a ),
			a: 1,
		} ),
		base
	);

	return `rgb(${ Math.round( result.r ) }, ${ Math.round( result.g ) }, ${ Math.round( result.b ) })`;
}

/**
 * Walk outwards from an element and work out what colour shows behind it.
 *
 * Rules, nearest ancestor first:
 *   - a fully opaque background-color wins immediately
 *   - translucent layers are collected and composited onto whatever opaque
 *     colour turns up further out
 *   - a background-image (gradient, photo) means the surface is not a flat
 *     colour at all, so give up rather than paint something wrong
 *   - if nothing painted anything, what shows through is the browser's
 *     canvas, which follows the document's colour-scheme
 *
 * @param {Element} start Element to begin at (inclusive — a background on the
 *                        element itself is what sits closest behind the label).
 * @returns {string|null} An opaque `rgb(r, g, b)`, or null when undeterminable.
 */
export default function resolveSurfaceColour( start ) {
	const translucent = [];
	let node = start;

	while ( node && node.nodeType === 1 ) {
		const style = getComputedStyle( node );

		if ( style.backgroundImage && style.backgroundImage !== 'none' ) {
			return null;
		}

		const layer = parseRgb( style.backgroundColor );
		if ( layer ) {
			if ( layer.a >= 1 ) {
				return flatten( translucent, layer );
			}
			if ( layer.a > 0 ) {
				translucent.push( layer );
			}
		}

		node = node.parentElement;
	}

	// Chrome paints the canvas white unless the document opts into a dark
	// colour-scheme, in which case it uses its own near-black.
	const doc = start.ownerDocument || document;
	const scheme = getComputedStyle( doc.documentElement ).colorScheme || '';
	const canvas = scheme.includes( 'dark' )
		? { r: 18, g: 18, b: 18, a: 1 }
		: { r: 255, g: 255, b: 255, a: 1 };

	return flatten( translucent, canvas );
}
