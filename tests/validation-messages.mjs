#!/usr/bin/env node
/**
 * Client validation messages come from the site, not the browser (1.15.0).
 *
 * The bug: `field.validationMessage` is in the BROWSER's language, so an
 * English site in a German Chrome said "Wähle ein Element in der Liste
 * aus." This pins that messageFor() maps every validity flag to a
 * catalogue text and never falls back to validationMessage.
 *
 * Run:  node tests/validation-messages.mjs
 *
 * @package Flinkform
 */

import { JSDOM } from 'jsdom';
import { readMessages, messageFor, formatBound } from '../src/shared/validation-messages.js';

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

const catalogue = {
	invalid: 'GENERIC', required: 'REQUIRED', choose: 'CHOOSE', check: 'CHECK',
	email: 'EMAIL', url: 'URL', tel: 'TEL', number: 'NUMBER', date: 'DATE',
	min: 'MIN %s', max: 'MAX %s', dateMin: 'DMIN %s', dateMax: 'DMAX %s',
	tooShort: 'SHORT %s', tooLong: 'LONG %s', step: 'STEP',
};

const dom = new JSDOM( `<!doctype html><html lang="de-DE"><body>
<form data-flinkform-messages='${ JSON.stringify( catalogue ) }' novalidate>
	<input id="req" type="text" required>
	<input id="mail" type="email" value="nope">
	<input id="web" type="url" value="nope">
	<input id="tel" type="tel" pattern="[0-9]*" value="abc">
	<input id="num" type="number" min="3" max="9" value="1">
	<input id="big" type="number" min="3" max="9" value="12">
	<select id="sel" required><option value="">-</option><option value="a">A</option></select>
	<input id="rad" type="radio" name="r" required>
	<input id="box" type="checkbox" required>
	<input id="ok" type="text" value="fine">
</form></body></html>` );
const doc = dom.window.document;
const form = doc.querySelector( 'form' );
const msgs = readMessages( form );
const $ = ( id ) => doc.getElementById( id );

check( 'catalogue parsed from the form', msgs.required === 'REQUIRED' );
check( 'broken JSON gives an empty catalogue', ( () => { const f = doc.createElement( 'form' ); f.setAttribute( 'data-flinkform-messages', '{oops' ); return Object.keys( readMessages( f ) ).length === 0; } )() );
check( 'no form gives an empty catalogue', Object.keys( readMessages( null ) ).length === 0 );

check( 'valid control has no message', messageFor( $( 'ok' ), msgs ) === '' );
check( 'required text', messageFor( $( 'req' ), msgs ) === 'REQUIRED' );
check( 'email typeMismatch', messageFor( $( 'mail' ), msgs ) === 'EMAIL' );
check( 'url typeMismatch', messageFor( $( 'web' ), msgs ) === 'URL' );
check( 'tel pattern', messageFor( $( 'tel' ), msgs ) === 'TEL' );
check( 'number underflow with bound', messageFor( $( 'num' ), msgs ) === 'MIN 3', messageFor( $( 'num' ), msgs ) );
check( 'number overflow with bound', messageFor( $( 'big' ), msgs ) === 'MAX 9' );
check( 'select required = choose', messageFor( $( 'sel' ), msgs ) === 'CHOOSE' );
check( 'radio required = choose', messageFor( $( 'rad' ), msgs ) === 'CHOOSE' );
check( 'single checkbox required = check', messageFor( $( 'box' ), msgs ) === 'CHECK' );

// Flags jsdom does not compute: fake the validity object.
const fake = ( type, flags, attrs = {} ) => ( {
	tagName: 'INPUT', type,
	validity: { valid: false, ...flags },
	getAttribute: ( k ) => attrs[ k ] ?? null,
	validationMessage: 'BROWSER LANGUAGE',
} );
check( 'badInput number', messageFor( fake( 'number', { badInput: true } ), msgs ) === 'NUMBER' );
check( 'badInput date', messageFor( fake( 'date', { badInput: true } ), msgs ) === 'DATE' );
check( 'tooShort fills length', messageFor( fake( 'text', { tooShort: true }, { minlength: '5' } ), msgs ) === 'SHORT 5' );
check( 'tooLong fills length', messageFor( fake( 'text', { tooLong: true }, { maxlength: '9' } ), msgs ) === 'LONG 9' );
check( 'stepMismatch', messageFor( fake( 'number', { stepMismatch: true } ), msgs ) === 'STEP' );
check( 'unknown flag = generic', messageFor( fake( 'text', { customError: true } ), msgs ) === 'GENERIC' );
check( 'never the browser text', ! messageFor( fake( 'text', { customError: true } ), {} ).includes( 'BROWSER' ) );
check( 'missing key falls back to generic', messageFor( fake( 'email', { typeMismatch: true } ), { invalid: 'G' } ) === 'G' );

const dMin = messageFor( fake( 'date', { rangeUnderflow: true }, { min: '2026-10-01' } ), msgs, 'de-DE' );
check( 'date min is formatted in page language', dMin.startsWith( 'DMIN ' ) && dMin.includes( '2026' ) && ! dMin.includes( '2026-10-01' ), dMin );
check( 'non-ISO bound passes through', formatBound( 'x', 'date', 'de' ) === 'x' );
check( 'number bound passes through', formatBound( '3.5', 'number', 'de' ) === '3.5' );

console.log( '' );
if ( failed > 0 ) {
	console.log( `${ failed } FAILED, ${ passed } passed.` );
	process.exit( 1 );
}
console.log( `All ${ passed } tests passed.` );
