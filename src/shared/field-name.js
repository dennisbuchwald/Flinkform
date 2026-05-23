/**
 * Generate a stable, URL-safe field name on first block mount.
 *
 * Used by every field block's editor to assign a sticky default `fieldName`
 * attribute. Users may override the value freely afterwards.
 */
export function generateFieldName( typeHint = 'field' ) {
	const rand = Math.random().toString( 36 ).slice( 2, 8 );
	return `${ typeHint }_${ rand }`;
}
