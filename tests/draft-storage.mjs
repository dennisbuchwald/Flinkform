#!/usr/bin/env node
/**
 * Multi-step draft (1.15.0): what goes into the sessionStorage draft, and
 * that restoring never overwrites what is already on the page.
 *
 * Run:  node tests/draft-storage.mjs
 */
import { JSDOM } from 'jsdom';
import { collectDraft, applyDraft, draftKey } from '../src/shared/draft-storage.js';

let passed = 0;
let failed = 0;
const check = ( label, ok, detail = '' ) => ( ok ? passed++ : ( failed++, console.log( `FAIL: ${ label }${ detail ? ' — ' + detail : '' }` ) ) );

const markup = `<form>
	<input type="hidden" name="flinkform_ts" value="SECRET">
	<input type="hidden" name="flinkform_field[source]" value="page">
	<input type="text" name="flinkform_hp" value="bot">
	<input type="text" name="flinkform_field[name]">
	<textarea name="flinkform_field[msg]"></textarea>
	<select name="flinkform_field[budget]"><option value="">-</option><option value="low">L</option><option value="high">H</option></select>
	<input type="radio" name="flinkform_field[type]" value="a"><input type="radio" name="flinkform_field[type]" value="b">
	<input type="checkbox" name="flinkform_field[svc][]" value="x"><input type="checkbox" name="flinkform_field[svc][]" value="y"><input type="checkbox" name="flinkform_field[svc][]" value="z">
	<div class="flinkform-field--consent"><input type="checkbox" name="flinkform_field[consent]" value="1"></div>
	<input type="file" name="flinkform_field[upload]">
</form>`;
const fresh = () => new JSDOM( markup ).window.document.querySelector( 'form' );

const f = fresh();
f.elements[ 'flinkform_field[name]' ].value = 'Erika';
f.elements[ 'flinkform_field[msg]' ].value = 'Hallo';
f.elements[ 'flinkform_field[budget]' ].value = 'high';
f.querySelector( '[value="b"]' ).checked = true;
f.querySelector( '[value="x"]' ).checked = true;
f.querySelector( '[value="z"]' ).checked = true;
f.elements[ 'flinkform_field[consent]' ].checked = true;
const d = collectDraft( f );

check( 'text, textarea, select kept', d[ 'flinkform_field[name]' ] === 'Erika' && d[ 'flinkform_field[msg]' ] === 'Hallo' && d[ 'flinkform_field[budget]' ] === 'high' );
check( 'radio kept', d[ 'flinkform_field[type]' ] === 'b' );
check( 'checkbox group kept as list', JSON.stringify( d[ 'flinkform_field[svc][]' ] ) === '["x","z"]' );
check( 'consent is never stored', ! ( 'flinkform_field[consent]' in d ) );
check( 'hidden, token, honeypot, file never stored', ! JSON.stringify( d ).includes( 'SECRET' ) && ! ( 'flinkform_hp' in d ) && ! ( 'flinkform_field[source]' in d ) && ! ( 'flinkform_field[upload]' in d ) );

const g = fresh();
const changed = applyDraft( g, JSON.parse( JSON.stringify( d ) ) );
check( 'restore fills empty text', g.elements[ 'flinkform_field[name]' ].value === 'Erika' );
check( 'restore ticks every box of the group', g.querySelector( '[value="x"]' ).checked && g.querySelector( '[value="z"]' ).checked && ! g.querySelector( '[value="y"]' ).checked );
check( 'restore picks the radio', g.querySelector( '[value="b"]' ).checked );
check( 'restore never ticks consent', ! g.elements[ 'flinkform_field[consent]' ].checked );
check( 'changed controls reported for change events', changed.length >= 5, String( changed.length ) );

const h = fresh();
h.elements[ 'flinkform_field[name]' ].value = 'Server value';
h.querySelector( '[value="a"]' ).checked = true;
applyDraft( h, d );
check( 'server value wins over the draft', h.elements[ 'flinkform_field[name]' ].value === 'Server value' );
check( 'answered radio group untouched', h.querySelector( '[value="a"]' ).checked && ! h.querySelector( '[value="b"]' ).checked );
check( 'garbage draft is ignored', applyDraft( fresh(), 'nope' ).length === 0 && applyDraft( fresh(), null ).length === 0 );
check( 'key is per form', draftKey( 'abc' ) === 'flinkform-draft:abc' );

console.log( '' );
if ( failed ) {
	console.log( `${ failed } FAILED, ${ passed } passed.` );
	process.exit( 1 );
}
console.log( `All ${ passed } tests passed.` );
