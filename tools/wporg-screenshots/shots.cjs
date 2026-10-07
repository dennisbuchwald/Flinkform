// Takes the six WordPress.org screenshots from the local Playground (port 9441).
const puppeteer = require( process.env.FF_REPO + '/node_modules/puppeteer-core' );
const fs = require( 'fs' );
const base = process.env.HOME + '/.cache/puppeteer/chrome/mac_arm-153.0.8010.36';
const dir = fs.readdirSync( base ).find( ( d ) => d.startsWith( 'chrome-mac' ) );
const exe = `${ base }/${ dir }/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing`;
const B = 'http://127.0.0.1:9441';
const OUT = __dirname + '/out';
const ONLY = ( process.env.ONLY || 'seed,1,2,3,4,5,6' ).split( ',' );
const sleep = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

async function pageId( p, slug ) {
	return p.evaluate( async ( s ) => ( await ( await fetch( '/wp-json/wp/v2/pages?slug=' + s ) ).json() )[ 0 ].id, slug );
}

async function openEditor( p, id ) {
	await p.goto( `${ B }/wp-admin/post.php?post=${ id }&action=edit`, { waitUntil: 'networkidle0', timeout: 120000 } );
	await p.waitForFunction( () => window.wp && wp.data && wp.data.select( 'core/block-editor' ).getBlocks().length > 0, { timeout: 60000 } );
	await p.evaluate( () => {
		const pref = wp.data.dispatch( 'core/preferences' );
		pref.set( 'core/edit-post', 'welcomeGuide', false );
		pref.set( 'core', 'fullscreenMode', true );
		pref.set( 'core', 'distractionFree', false );
		pref.set( 'core', 'fixedToolbar', true );
		wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar?.( 'edit-post/block' );
	} );
	await sleep( 1500 );
	// Close any modal (welcome guide) that is still open.
	await p.evaluate( () => document.querySelectorAll( '.components-modal__screen-overlay button[aria-label="Close"]' ).forEach( ( b ) => b.click() ) );
	await sleep( 500 );
}

async function canvasTop( p, y = 0 ) {
	const f = p.frames().find( ( fr ) => fr.name() === 'editor-canvas' ) || p.frames().find( ( fr ) => fr.url().includes( 'blob:' ) || fr !== p.mainFrame() );
	if ( f ) await f.evaluate( ( yy ) => window.scrollTo( 0, yy ), y );
	else await p.evaluate( ( yy ) => document.querySelector( '.interface-interface-skeleton__content' )?.scrollTo( 0, yy ), y );
}

async function selectBlock( p, matcher ) {
	return p.evaluate( ( m ) => {
		const all = [];
		const walk = ( bs ) => bs.forEach( ( b ) => { all.push( b ); walk( b.innerBlocks ); } );
		walk( wp.data.select( 'core/block-editor' ).getBlocks() );
		const hit = all.find( ( b ) => b.name === m.name && ( ! m.field || b.attributes.fieldName === m.field ) );
		if ( hit ) wp.data.dispatch( 'core/block-editor' ).selectBlock( hit.clientId );
		return !! hit;
	}, matcher );
}

async function openPanel( p, title ) {
	return p.evaluate( ( t ) => {
		const btn = [ ...document.querySelectorAll( '.components-panel__body-toggle' ) ].find( ( b ) => b.textContent.trim().toLowerCase().startsWith( t.toLowerCase() ) );
		if ( btn && btn.getAttribute( 'aria-expanded' ) === 'false' ) btn.click();
		return btn ? btn.textContent.trim() : null;
	}, title );
}

async function fillContact( p, d, submit ) {
	await p.goto( `${ B }/contact/?ff_clean=1`, { waitUntil: 'load', timeout: 60000 } );
	await sleep( 1200 );
	const f = ( n ) => `[name="flinkform_field[${ n }]"]`;
	await p.type( f( 'name' ), d.name, { delay: 20 } );
	await p.type( f( 'email' ), d.email, { delay: 10 } );
	if ( d.phone ) await p.type( f( 'phone' ), d.phone, { delay: 10 } );
	if ( d.topic ) await p.select( f( 'topic' ), d.topic );
	await p.type( f( 'message' ), d.message, { delay: 2 } );
	await p.click( `${ f( 'consent' ) }` );
	if ( ! submit ) return;
	await sleep( 3000 );
	await Promise.all( [
		p.waitForNavigation( { waitUntil: 'networkidle0', timeout: 30000 } ).catch( () => null ),
		p.click( '.flinkform-form button[type=submit]' ),
	] );
	await sleep( 1500 );
	return p.evaluate( () => !! document.querySelector( '.flinkform-form__success' ) );
}

( async () => {
	const browser = await puppeteer.launch( { executablePath: exe, headless: true, args: [ '--lang=en-US', '--accept-lang=en-US' ], env: { ...process.env, LANG: 'en_US.UTF-8', LC_ALL: 'en_US.UTF-8', LANGUAGE: 'en_US' }, defaultViewport: { width: 1440, height: 900, deviceScaleFactor: 1 } } );
	const p = await browser.newPage();
	await p.setExtraHTTPHeaders( { 'Accept-Language': 'en-US,en;q=0.9' } );
	const errs = [];
	p.on( 'pageerror', ( e ) => errs.push( e.message ) );
	p.on( 'dialog', ( d ) => d.accept().catch( () => {} ) );
	await p.goto( B + '/wp-admin/', { waitUntil: 'networkidle0', timeout: 120000 } );

	if ( ONLY.includes( 'seed' ) ) {
		const people = [
			{ name: 'Jana Schulte', email: 'jana@schulte-design.de', phone: '040 2398 114', topic: 'New website', message: 'Hi! We are a small architecture firm and need a new website with a project gallery. Can we talk next week?' },
			{ name: 'Tom Becker', email: 'tom.becker@example.org', topic: 'Support', message: 'The contact form on our landing page sends to the wrong address. Could you have a look?' },
			{ name: 'Aylin Kaya', email: 'aylin@kaya-coaching.com', phone: '+49 151 2233 4455', topic: 'New website', message: 'Looking for a one-page site for my coaching business, ideally with online booking.' },
			{ name: 'Marco Rossi', email: 'marco@rossi-bikes.it', topic: 'Something else', message: 'Do you also do Google Ads for local shops?' },
			{ name: 'Lena Hoffmann', email: 'lena.hoffmann@example.com', topic: 'Support', message: 'Quick question about the hosting invoice for September.' },
		];
		await p.goto( B + '/wp-admin/?ff_reset=1', { waitUntil: 'load' } );
		people.push( { name: 'Paul Neumann', email: 'p.neumann@example.net', topic: 'New website', message: 'We need a careers page with an application form for our bakery.' } );
		for ( const d of people.reverse() ) console.log( 'seed', d.name, await fillContact( p, d, true ) );
		await p.goto( B + '/wp-admin/?ff_spread=1', { waitUntil: 'load' } );
	}

	const contactId = await pageId( p, 'contact' );
	const projectId = await pageId( p, 'project' );

	if ( ONLY.includes( '1' ) ) {
		await openEditor( p, contactId );
		console.log( '1 select', await selectBlock( p, { name: 'flinkform/form' } ) );
		await sleep( 1200 );
		await canvasTop( p, 0 );
		await sleep( 600 );
		await p.screenshot( { path: OUT + '/screenshot-1.png' } );
	}

	if ( ONLY.includes( '2' ) ) {
		await p.goto( `${ B }/project/?ff_clean=1`, { waitUntil: 'load', timeout: 60000 } );
		await sleep( 1500 );
		await p.evaluate( () => [ ...document.querySelectorAll( '.flinkform-form label, .flinkform-form button' ) ].find( ( e ) => e.textContent.trim() === 'Online shop' )?.click() );
		await sleep( 400 );
		await p.evaluate( () => [ ...document.querySelectorAll( '.flinkform-form button' ) ].find( ( b ) => /next|weiter/i.test( b.textContent ) && b.offsetParent )?.click() );
		await sleep( 900 );
		await p.type( '[name="flinkform_field[products]"]', '250', { delay: 40 } );
		await p.evaluate( () => [ ...document.querySelectorAll( '.flinkform-form button' ) ].find( ( b ) => /next/i.test( b.textContent ) && b.offsetParent )?.click() );
		await sleep( 900 );
		await p.evaluate( () => document.activeElement?.blur() );
		await p.evaluate( () => document.querySelector( '.flinkform-form' ).scrollIntoView( { block: 'center' } ) );
		await sleep( 400 );
		await p.screenshot( { path: OUT + '/screenshot-2.png' } );
	}

	if ( ONLY.includes( '3' ) ) {
		await openEditor( p, projectId );
		console.log( '3 select', await selectBlock( p, { name: 'flinkform/notice' } ) );
		await sleep( 1000 );
		console.log( '3 panel', await openPanel( p, 'Conditional' ) );
		await sleep( 800 );
		await p.evaluate( () => {
			const btn = [ ...document.querySelectorAll( '.components-panel__body-toggle' ) ].find( ( b ) => /conditional/i.test( b.textContent ) );
			btn?.scrollIntoView( { block: 'start' } );
			const sel = wp.data.select( 'core/block-editor' ).getSelectedBlockClientId();
			document.querySelector( `[data-block="${ sel }"]` )?.scrollIntoView( { block: 'center' } );
		} );
		await sleep( 800 );
		await p.screenshot( { path: OUT + '/screenshot-3.png' } );
	}

	if ( ONLY.includes( '4' ) ) {
		await p.goto( `${ B }/wp-admin/admin.php?page=flinkform-submissions`, { waitUntil: 'load', timeout: 60000 } );
		await sleep( 800 );
		await p.screenshot( { path: OUT + '/screenshot-4.png' } );
	}

	if ( ONLY.includes( '5' ) ) {
		await fillContact( p, { name: 'Jana Schulte', email: 'jana@schulte-design.de', phone: '040 2398 114', topic: 'New website', message: 'Hi! We are a small architecture firm and need a new website with a project gallery.' }, false );
		await p.evaluate( () => { const h = document.querySelector( 'h1' ); window.scrollTo( 0, h.getBoundingClientRect().top + window.scrollY - 48 ); } );
		await p.hover( '.flinkform-form button[type=submit]' );
		await sleep( 600 );
		await p.screenshot( { path: OUT + '/screenshot-5.png' } );
	}

	if ( ONLY.includes( '6' ) ) {
		const shot = async ( q ) => {
			const t = await browser.newPage();
			await t.setViewport( { width: 900, height: 1000, deviceScaleFactor: 1 } );
			await t.goto( `${ B }/contact/?ff_clean=1${ q }`, { waitUntil: 'networkidle0' } );
			const el = await t.$( 'main' ) || await t.$( 'body' );
			await new Promise( ( r ) => setTimeout( r, 600 ) );
			const b64 = await t.screenshot( { encoding: 'base64' } );
			await t.close();
			return b64;
		};
		const light = await shot( '' );
		const dark = await shot( '&ff_var=Sunrise' );
		const c = await browser.newPage();
		await c.setViewport( { width: 1440, height: 900, deviceScaleFactor: 1 } );
		await c.setContent( `<!doctype html><html><body style="margin:0;background:#e9e7f2;font-family:-apple-system,Helvetica,Arial,sans-serif">
			<div style="display:flex;gap:28px;padding:56px 40px 0;justify-content:center">
			${ [ [ light, 'Twenty Twenty-Five' ], [ dark, 'Twenty Twenty-Five, "Sunrise" style' ] ].map( ( [ img, cap ] ) => `<figure style="margin:0;width:660px">
				<div style="border-radius:12px;overflow:hidden;box-shadow:0 12px 40px rgba(30,20,60,.18);line-height:0">
				<img src="data:image/png;base64,${ img }" style="width:660px;display:block"></div>
				<figcaption style="text-align:center;margin-top:14px;font-size:17px;color:#3b3550">${ cap }</figcaption></figure>` ).join( '' ) }
			</div></body></html>`, { waitUntil: 'load' } );
		await sleep( 300 );
		await c.screenshot( { path: OUT + '/screenshot-6.png' } );
	}

	console.log( 'JS errors:', errs );
	await browser.close();
} )();
