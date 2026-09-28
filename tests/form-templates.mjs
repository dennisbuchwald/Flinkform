#!/usr/bin/env node
/**
 * Starter templates (1.15.0): structure checks that keep the "two minutes
 * to a working, GDPR-clean form" promise honest.
 *
 * Run:  node tests/form-templates.mjs
 *
 * @package Flinkform
 */

import { readFileSync } from 'node:fs';
import { buildTemplates } from '../src/form-container/templates.js';

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

const identity = ( s ) => s;
const templates = buildTemplates( identity );

// Allowed blocks come from the editor's own list, so a template can never
// use a block the form would refuse.
const edit = readFileSync( new URL( '../src/form-container/edit.js', import.meta.url ), 'utf8' );
const allowed = new Set( [ ...edit.matchAll( /'(flinkform\/[a-z-]+)'/g ) ].map( ( m ) => m[ 1 ] ) );
const blockAttrs = ( name ) => {
	const json = JSON.parse( readFileSync( new URL( `../src/${ name.replace( 'flinkform/', '' ) }/block.json`, import.meta.url ), 'utf8' ) );
	return json.attributes;
};

check( 'five templates', templates.length === 5, String( templates.length ) );
check( 'template names are unique', new Set( templates.map( ( t ) => t.name ) ).size === templates.length );

for ( const t of templates ) {
	const blocks = t.innerBlocks;
	const names = blocks.map( ( [ n ] ) => n );
	const fieldNames = blocks.map( ( [ , a ] ) => a.fieldName ).filter( Boolean );

	check( `${ t.name }: has title and description`, !! t.title && !! t.description );
	check( `${ t.name }: sets a form title`, !! t.attributes.title );
	check( `${ t.name }: ends with the consent field`, names[ names.length - 1 ] === 'flinkform/field-consent' );
	// A way to answer: email, or a required phone number (callback).
	check( `${ t.name }: can be answered`, names.includes( 'flinkform/field-email' ) || blocks.some( ( [ n, a ] ) => n === 'flinkform/field-phone' && a.required ) );
	check( `${ t.name }: only allowed blocks`, names.every( ( n ) => allowed.has( n ) ), names.filter( ( n ) => ! allowed.has( n ) ).join( ', ' ) );
	check( `${ t.name }: field names unique`, new Set( fieldNames ).size === fieldNames.length, fieldNames.join( ', ' ) );
	check( `${ t.name }: field names are merge-tag safe`, fieldNames.every( ( f ) => /^[A-Za-z0-9_-]+$/.test( f ) ) );
	check( `${ t.name }: no page break first or last`, names[ 0 ] !== 'flinkform/page-break' && names[ names.length - 1 ] !== 'flinkform/page-break' );

	for ( const [ block, attrs ] of blocks ) {
		const known = blockAttrs( block );
		const unknown = Object.keys( attrs ).filter( ( k ) => ! ( k in known ) );
		check( `${ t.name }/${ block }: only real attributes`, unknown.length === 0, unknown.join( ', ' ) );
		if ( attrs.options ) {
			const values = attrs.options.map( ( o ) => o.value );
			check( `${ t.name }/${ attrs.fieldName }: option values unique and non-empty`, new Set( values ).size === values.length && values.every( Boolean ) );
		}
		if ( attrs.consentText ) {
			check( `${ t.name }: custom consent text links the privacy policy`, attrs.consentText.includes( '{privacy_policy}' ) );
		}
	}
}

const project = templates.find( ( t ) => t.name === 'project' );
check( 'project inquiry is multi-step', project.innerBlocks.filter( ( [ n ] ) => n === 'flinkform/page-break' ).length === 2 );

console.log( '' );
if ( failed > 0 ) {
	console.log( `${ failed } FAILED, ${ passed } passed.` );
	process.exit( 1 );
}
console.log( `All ${ passed } tests passed.` );
