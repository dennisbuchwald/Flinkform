/**
 * Client/server parity for the conditional-logic evaluator.
 *
 * The browser decides what a visitor sees; the server decides what is
 * stored and whether a submission is accepted. If the two disagree, a
 * field hides in the browser and submits anyway, or a visitor is blocked
 * from submitting something the server would have taken. Neither failure
 * announces itself.
 *
 * So this runs one shared table of cases through both implementations and
 * compares the verdicts, rather than testing each half against its own
 * expectations. The cases live in rule-groups-cases.json, which the PHP
 * side reads too.
 *
 * Run:  node tests/rule-groups-parity.mjs
 *
 * Exits 0 when every case agrees, 1 otherwise.
 *
 * @package Flinkform
 */

import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

import evaluateRuleSet from '../src/shared/rule-evaluator.js';

const here = dirname( fileURLToPath( import.meta.url ) );
const cases = JSON.parse( readFileSync( join( here, 'rule-groups-cases.json' ), 'utf8' ) );

// The PHP half runs in one go and prints a verdict per case, so the two
// sides are compared on identical input rather than on two hand-written
// sets of expectations.
const phpVerdicts = JSON.parse(
	execFileSync( 'php', [ join( here, 'rule-groups-verdicts.php' ) ], { encoding: 'utf8' } )
);

let passed = 0;
let failed = 0;

cases.forEach( ( testCase, index ) => {
	const js = evaluateRuleSet( testCase.ruleSet, testCase.values );
	const php = phpVerdicts[ index ];

	if ( js === php && js === testCase.expected ) {
		passed += 1;
		return;
	}

	failed += 1;
	console.log(
		`FAIL: ${ testCase.name }\n` +
			`      expected ${ testCase.expected }, JS ${ js }, PHP ${ php }`
	);
} );

console.log( '' );
if ( failed > 0 ) {
	console.log( `${ failed } FAILED, ${ passed } passed.` );
	process.exit( 1 );
}
console.log( `All ${ passed } cases agree between JS and PHP.` );
process.exit( 0 );
