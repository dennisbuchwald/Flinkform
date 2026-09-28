#!/usr/bin/env node
/**
 * Run the browser smoke tests headless, so tests/run.sh (and with it
 * deploy.sh) covers them instead of relying on someone opening two HTML
 * files by hand after every build.
 *
 *   tests/module-smoke.html   view.js evaluates without a runtime error (TDZ, 1.4.3)
 *   tests/deferred-smoke.html deferred arming, submit hold, fill time, inline retry
 *   tests/validation-smoke.html messages in the site language, check on leaving a field
 *   tests/multistep-smoke.html  multi-step first paint without layout shift
 *
 * Both run against build/, so `npm run build` first.
 *
 * Run:  node tests/browser-smoke.mjs
 *
 * @package Flinkform
 */

import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = normalize( join( fileURLToPath( import.meta.url ), '..', '..' ) );

let chromium;
try {
	( { chromium } = await import( 'playwright' ) );
} catch {
	console.log( 'FAIL: playwright is not installed (npm install)' );
	process.exit( 1 );
}

const types = { '.html': 'text/html', '.js': 'text/javascript', '.json': 'application/json', '.css': 'text/css' };
const server = createServer( async ( req, res ) => {
	const path = normalize( join( root, decodeURIComponent( new URL( req.url, 'http://x' ).pathname ) ) );
	if ( ! path.startsWith( root ) ) {
		res.writeHead( 403 ).end();
		return;
	}
	try {
		const body = await readFile( path );
		res.writeHead( 200, { 'Content-Type': types[ extname( path ) ] || 'application/octet-stream' } ).end( body );
	} catch {
		res.writeHead( 404 ).end();
	}
} );
await new Promise( ( resolve ) => server.listen( 0, '127.0.0.1', resolve ) );
const base = `http://127.0.0.1:${ server.address().port }`;

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

// The bundled headless shell first; if its revision is not downloaded
// (Playwright was updated without `npx playwright install`), the locally
// installed Google Chrome does the same job.
let browser;
try {
	browser = await chromium.launch();
} catch {
	try {
		browser = await chromium.launch( { channel: 'chrome' } );
	} catch ( e ) {
		console.log( `FAIL: no browser to run the smoke tests (npx playwright install chromium) — ${ String( e ).split( '\n' )[ 0 ] }` );
		server.close();
		process.exit( 1 );
	}
}
try {
	const page = await browser.newPage();

	await page.goto( `${ base }/tests/module-smoke.html` );
	await page.waitForFunction( () => window.__result && ( window.__result.loaded || window.__result.error ), null, { timeout: 15000 } );
	const mod = await page.evaluate( () => window.__result );
	check( 'module-smoke: view.js evaluates', mod.loaded === true, mod.error || '' );

	await page.goto( `${ base }/tests/deferred-smoke.html` );
	await page.waitForFunction( () => document.getElementById( 'verdict' ).textContent !== 'running…', null, { timeout: 60000 } );
	const smoke = await page.evaluate( () => ( {
		...window.__smoke,
		fails: [ ...document.querySelectorAll( '#log .fail' ) ].map( ( li ) => li.textContent ),
	} ) );
	smoke.fails.forEach( ( f ) => check( `deferred-smoke: ${ f }`, false ) );
	check( 'deferred-smoke: ran checks', smoke.passed > 0 && smoke.failed === 0, `${ smoke.passed } passed, ${ smoke.failed } failed` );
	passed += smoke.passed;

	for ( const file of [ 'multistep-smoke.html' ] ) {
		await page.goto( `${ base }/tests/${ file }` );
		await page.waitForFunction( () => document.getElementById( 'verdict' ).textContent !== 'running…', null, { timeout: 15000 } );
		const r = await page.evaluate( () => ( {
			...window.__smoke,
			fails: [ ...document.querySelectorAll( '#log .fail' ) ].map( ( li ) => li.textContent ),
		} ) );
		r.fails.forEach( ( f ) => check( `${ file }: ${ f }`, false ) );
		check( `${ file }: ran checks`, r.passed > 0 && r.failed === 0, `${ r.passed } passed, ${ r.failed } failed` );
		passed += r.passed;
	}

	await page.goto( `${ base }/tests/validation-smoke.html` );
	await page.waitForFunction( () => document.getElementById( 'verdict' ).textContent !== 'running…', null, { timeout: 15000 } );
	const val = await page.evaluate( () => ( {
		...window.__smoke,
		fails: [ ...document.querySelectorAll( '#log .fail' ) ].map( ( li ) => li.textContent ),
	} ) );
	val.fails.forEach( ( f ) => check( `validation-smoke: ${ f }`, false ) );
	check( 'validation-smoke: ran checks', val.passed > 0 && val.failed === 0, `${ val.passed } passed, ${ val.failed } failed` );
	passed += val.passed;
} finally {
	await browser.close();
	server.close();
}

console.log( '' );
if ( failed > 0 ) {
	console.log( `${ failed } FAILED, ${ passed } passed.` );
	process.exit( 1 );
}
console.log( `All ${ passed } tests passed.` );
