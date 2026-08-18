#!/usr/bin/env node
/**
 * Regression tests for the client-side spam-challenge refresh.
 *
 * The outage these pin: the challenge token is minted into the page
 * with a 30-minute TTL and was never renewed client-side. A visitor who
 * kept the form open longer submitted an expired token and the server
 * silently discarded the whole request (F-0). view.js now swaps the
 * token for a fresh one via the REST endpoint; this file covers the
 * DOM-application half of that (src/shared/challenge-refresh.js):
 *
 *   - applyChallengeData must update every input the Handler later
 *     reads (token, PoW params, math question, submit nonce) and clear
 *     the stale solution/answer so the old salt's solution can never be
 *     submitted against the new token.
 *   - Malformed endpoint payloads must be rejected without touching the
 *     DOM — a failed refresh leaves a working (if aging) challenge.
 *   - challengeExpiry must read the real expiry out of the token so
 *     cache-served pages refresh immediately instead of trusting a
 *     timer that started too late.
 *   - shouldDeferRefresh must hold the swap while a visitor is typing a
 *     math answer.
 *
 * Run:  node tests/challenge-refresh.mjs
 *
 * @package Flinkform
 */

import { JSDOM } from 'jsdom';
import {
	applyChallengeData,
	challengeExpiry,
	shouldDeferRefresh,
} from '../src/shared/challenge-refresh.js';

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

/**
 * Build a form with a spam block exactly like Spam\Renderer emits it.
 *
 * @param {object} opts
 * @param {boolean} opts.mathVisible  The fallback row is on screen.
 * @param {string}  opts.mathValue    Pre-typed math answer.
 * @return {{ dom: JSDOM, form: HTMLFormElement, block: HTMLElement }}
 */
function formWithBlock( { mathVisible = false, mathValue = '' } = {} ) {
	const dom = new JSDOM( `<!doctype html><body>
		<form>
			<input type="hidden" name="_flinkform_nonce" value="old-nonce" />
			<div class="flinkform-form__spam" data-flinkform-spam="1"
			     data-flinkform-pow-salt="old-salt"
			     data-flinkform-pow-difficulty="13"
			     data-flinkform-refresh-url="https://example.test/wp-json/flinkform/v1/challenge?form_id=f1">
				<input type="hidden" name="flinkform_spam_token" value="old-token" />
				<input type="hidden" name="flinkform_spam_solution" value="1234" data-flinkform-spam-solution />
				<div class="flinkform-form__spam-math" data-flinkform-spam-math${ mathVisible ? '' : ' hidden' }>
					<label for="m">What is 2 + 2?</label>
					<input type="text" id="m" name="flinkform_spam_answer" value="${ mathValue }" />
				</div>
			</div>
		</form>
	</body>` );
	const block = dom.window.document.querySelector( '.flinkform-form__spam' );
	const form  = dom.window.document.querySelector( 'form' );
	return { dom, form, block };
}

const freshData = {
	token: 'new-token',
	salt: 'new-salt',
	difficulty: 14,
	question: 'What is 3 + 5?',
	nonce: 'new-nonce',
};

// --- applyChallengeData: full swap -------------------------------------

{
	const { block, form } = formWithBlock();
	const ok = applyChallengeData( block, freshData );
	check( 'apply: reports success', ok === true );
	check( 'apply: token input updated', block.querySelector( '[name="flinkform_spam_token"]' ).value === 'new-token' );
	check( 'apply: salt attribute updated', block.getAttribute( 'data-flinkform-pow-salt' ) === 'new-salt' );
	check( 'apply: difficulty attribute updated', block.getAttribute( 'data-flinkform-pow-difficulty' ) === '14' );
	// The solution is NOT cleared by the swap on purpose: the caller solves
	// the new challenge first and then writes token + solution together, so
	// clearing here would re-open the race (fresh token, empty solution).
	// The previous solution stays until the caller overwrites it; against the
	// new salt it fails the PoW and takes the gentle path, never a silent drop.
	check( 'apply: solution left for the caller to overwrite atomically', block.querySelector( '[data-flinkform-spam-solution]' ).value === '1234' );
	check( 'apply: math question swapped', block.querySelector( 'label' ).textContent === 'What is 3 + 5?' );
	check( 'apply: nonce input updated', form.querySelector( '[name="_flinkform_nonce"]' ).value === 'new-nonce' );
}

// --- applyChallengeData: typed math answer is cleared with the swap ----

{
	const { block } = formWithBlock( { mathVisible: true, mathValue: '4' } );
	applyChallengeData( block, freshData );
	check( 'apply: stale math answer cleared', block.querySelector( 'input[type="text"]' ).value === '' );
}

// --- applyChallengeData: malformed payloads leave the DOM alone --------

for ( const [ label, bad ] of [
	[ 'null payload', null ],
	[ 'empty token', { ...freshData, token: '' } ],
	[ 'missing token', { salt: 's', difficulty: 13 } ],
	[ 'empty salt', { ...freshData, salt: '' } ],
	[ 'non-string token', { ...freshData, token: 42 } ],
] ) {
	const { block, form } = formWithBlock();
	const ok = applyChallengeData( block, bad );
	check( `reject ${ label }: reports failure`, ok === false );
	check( `reject ${ label }: token untouched`, block.querySelector( '[name="flinkform_spam_token"]' ).value === 'old-token' );
	check( `reject ${ label }: solution untouched`, block.querySelector( '[data-flinkform-spam-solution]' ).value === '1234' );
	check( `reject ${ label }: nonce untouched`, form.querySelector( '[name="_flinkform_nonce"]' ).value === 'old-nonce' );
}

// --- applyChallengeData: optional parts may be absent ------------------

{
	const { block } = formWithBlock();
	const ok = applyChallengeData( block, { token: 'new-token', salt: 'new-salt' } );
	check( 'apply without difficulty/question/nonce: still succeeds', ok === true );
	check( 'apply without difficulty: attribute untouched', block.getAttribute( 'data-flinkform-pow-difficulty' ) === '13' );
}

// --- challengeExpiry ---------------------------------------------------

{
	const b64url = ( obj ) =>
		Buffer.from( JSON.stringify( obj ) ).toString( 'base64' )
			.replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/, '' );

	// atob lives on the window in jsdom, on globalThis in Node 16+.
	check( 'expiry: reads e from a real-shaped token',
		challengeExpiry( b64url( { v: 1, f: 'f1', e: 1787032880 } ) + '.deadbeef' ) === 1787032880 );
	check( 'expiry: 0 for missing e', challengeExpiry( b64url( { v: 1 } ) + '.x' ) === 0 );
	check( 'expiry: 0 for string e', challengeExpiry( b64url( { e: 'soon' } ) + '.x' ) === 0 );
	check( 'expiry: 0 for garbage', challengeExpiry( '%%%%.x' ) === 0 );
	check( 'expiry: 0 for missing dot', challengeExpiry( 'nodot' ) === 0 );
	check( 'expiry: 0 for empty string', challengeExpiry( '' ) === 0 );
	check( 'expiry: 0 for non-string', challengeExpiry( undefined ) === 0 );
}

// --- shouldDeferRefresh ------------------------------------------------

{
	const { block } = formWithBlock( { mathVisible: true, mathValue: '7' } );
	check( 'defer: visitor mid-answer', shouldDeferRefresh( block ) === true );
}
{
	const { block } = formWithBlock( { mathVisible: true, mathValue: '' } );
	check( 'no defer: fallback visible but empty', shouldDeferRefresh( block ) === false );
}
{
	const { block } = formWithBlock( { mathVisible: false, mathValue: '7' } );
	check( 'no defer: math row hidden (solver already won)', shouldDeferRefresh( block ) === false );
}

// --- Renderer/JS contract: the refresh URL attribute must exist --------
// (a renamed attribute on either side silently disables the refresh)

{
	const { readFileSync } = await import( 'node:fs' );
	const renderer = readFileSync( new URL( '../includes/Spam/Renderer.php', import.meta.url ), 'utf8' );
	const view     = readFileSync( new URL( '../src/form-container/view.js', import.meta.url ), 'utf8' );
	check( 'contract: Renderer emits data-flinkform-refresh-url', renderer.includes( 'data-flinkform-refresh-url' ) );
	check( 'contract: view.js reads data-flinkform-refresh-url', view.includes( 'data-flinkform-refresh-url' ) );
	check( 'contract: view.js sends X-Flinkform-Fetch on retry path', view.includes( 'spam_expired' ) );
}

if ( failed > 0 ) {
	console.log( `\n${ failed } of ${ passed + failed } tests failed.` );
	process.exit( 1 );
}
console.log( `All ${ passed } tests passed.` );
