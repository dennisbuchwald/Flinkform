/**
 * Client/server parity for conditional-visibility resolution.
 *
 * Visibility depends on values and values depend on visibility, so both
 * halves iterate to a fixed point. If they iterate differently — a
 * different pass cap, a different entry order, an accumulating set on one
 * side — they land on different answers for cascades and for contradictory
 * configurations. The visitor then sees a field the server has dropped, or
 * is blocked by a value nobody can reach.
 *
 * So this runs one shared table through both implementations and compares
 * the resolved sets. Cases with `expected: null` are contradictory on
 * purpose: there is no single right answer, only the requirement that both
 * sides pick the same one.
 *
 * Run:  node tests/visibility-parity.mjs
 *
 * @package Flinkform
 */

import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

import { resolveHiddenFields } from '../src/shared/rule-evaluator.js';

const here = dirname( fileURLToPath( import.meta.url ) );
const cases = JSON.parse( readFileSync( join( here, 'visibility-cases.json' ), 'utf8' ) );
const phpResults = JSON.parse(
	execFileSync( 'php', [ join( here, 'visibility-verdicts.php' ) ], { encoding: 'utf8' } )
);

let passed = 0;
let failed = 0;

cases.forEach( ( testCase, index ) => {
	const js = [ ...resolveHiddenFields( testCase.entries, testCase.values ) ].sort();
	const php = [ ...phpResults[ index ] ].sort();

	const agree = JSON.stringify( js ) === JSON.stringify( php );
	const correct =
		testCase.expected === null || JSON.stringify( js ) === JSON.stringify( testCase.expected );

	if ( agree && correct ) {
		passed += 1;
		return;
	}

	failed += 1;
	console.log(
		`FAIL: ${ testCase.name }\n` +
			`      expected ${ JSON.stringify( testCase.expected ) }\n` +
			`      JS       ${ JSON.stringify( js ) }\n` +
			`      PHP      ${ JSON.stringify( php ) }`
	);
} );

console.log( '' );
if ( failed > 0 ) {
	console.log( `${ failed } FAILED, ${ passed } passed.` );
	process.exit( 1 );
}
console.log( `All ${ passed } visibility cases agree between JS and PHP.` );
process.exit( 0 );
