#!/usr/bin/env node
/**
 * Dark-surface detection for the error colour (1.15.0): #b80000 is
 * unreadable on near-black, so forms on dark surfaces get a lighter red.
 *
 * Run:  node tests/surface-tone.mjs
 */
import { isDarkColour } from '../src/shared/surface-colour.js';

let passed = 0;
let failed = 0;
const check = ( label, ok ) => ( ok ? passed++ : ( failed++, console.log( `FAIL: ${ label }` ) ) );

check( 'white is light', ! isDarkColour( 'rgb(255, 255, 255)' ) );
check( 'near-black is dark', isDarkColour( 'rgb(18, 18, 18)' ) );
check( 'navy is dark', isDarkColour( 'rgb(20, 30, 70)' ) );
check( 'light grey is light', ! isDarkColour( 'rgb(240, 240, 240)' ) );
check( 'mid grey is light enough for #b80000', ! isDarkColour( 'rgb(160, 160, 160)' ) );
check( 'unknown surface is not dark', ! isDarkColour( null ) );
check( 'garbage is not dark', ! isDarkColour( 'transparent' ) );

console.log( '' );
if ( failed ) {
	console.log( `${ failed } FAILED, ${ passed } passed.` );
	process.exit( 1 );
}
console.log( `All ${ passed } tests passed.` );
