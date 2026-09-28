/**
 * Flinkform → Move from Contact Form 7 (1.15.0).
 *
 * One request per form, one after the other ("import all" included), so a
 * site with many forms never runs into a PHP timeout. Plain script, no
 * build step, no dependencies.
 */
( function () {
	'use strict';

	var cfg = window.flinkformCf7;
	if ( ! cfg ) {
		return;
	}

	function post( action, id ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', cfg.nonce );
		body.append( 'id', String( id ) );
		return fetch( cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json();
			} )
			.catch( function () {
				return { success: false, data: { message: cfg.i18n.failed } };
			} );
	}

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			node.setAttribute( k, attrs[ k ] );
		} );
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	function report( box, data ) {
		var result = box.querySelector( '.flinkform-cf7__result' );
		result.textContent = cfg.i18n.imported + '. ';
		if ( data.edit ) {
			result.appendChild( el( 'a', { href: data.edit }, cfg.i18n.editPattern ) );
		}
		if ( data.pages && data.pages.length ) {
			var p = el( 'p', {}, cfg.i18n.test + ' ' );
			data.pages.forEach( function ( page, i ) {
				if ( i > 0 ) {
					p.appendChild( document.createTextNode( ', ' ) );
				}
				p.appendChild( el( 'a', { href: page.view, target: '_blank', rel: 'noopener' }, page.title ) );
			} );
			box.appendChild( p );
		}
	}

	function run( box ) {
		var id = box.getAttribute( 'data-flinkform-cf7' );
		var importBtn = box.querySelector( '[data-action="import"]' );
		var undoBtn = box.querySelector( '[data-action="undo"]' );
		var result = box.querySelector( '.flinkform-cf7__result' );
		importBtn.disabled = true;
		result.textContent = cfg.i18n.importing;
		return post( 'flinkform_cf7_import', id ).then( function ( res ) {
			importBtn.disabled = false;
			if ( ! res || ! res.success ) {
				result.textContent = cfg.i18n.failed + ': ' + ( res && res.data && res.data.message ? res.data.message : '' );
				return false;
			}
			box.setAttribute( 'data-imported', '1' );
			importBtn.hidden = true;
			undoBtn.hidden = false;
			report( box, res.data );
			return true;
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.flinkform-cf7 [data-action]' );
		if ( btn ) {
			var box = btn.closest( '[data-flinkform-cf7]' );
			if ( btn.getAttribute( 'data-action' ) === 'import' ) {
				run( box );
				return;
			}
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( cfg.i18n.confirmUndo ) ) {
				return;
			}
			var result = box.querySelector( '.flinkform-cf7__result' );
			btn.disabled = true;
			result.textContent = cfg.i18n.undoing;
			post( 'flinkform_cf7_undo', box.getAttribute( 'data-flinkform-cf7' ) ).then( function ( res ) {
				btn.disabled = false;
				if ( ! res || ! res.success ) {
					result.textContent = cfg.i18n.failed + ': ' + ( res && res.data && res.data.message ? res.data.message : '' );
					return;
				}
				box.setAttribute( 'data-imported', '0' );
				btn.hidden = true;
				box.querySelector( '[data-action="import"]' ).hidden = false;
				result.textContent = cfg.i18n.undone;
			} );
			return;
		}

		var all = e.target.closest( '[data-flinkform-cf7-all]' );
		if ( all ) {
			all.disabled = true;
			var boxes = Array.prototype.slice.call( document.querySelectorAll( '[data-flinkform-cf7][data-imported="0"]' ) );
			boxes.reduce( function ( chain, box ) {
				return chain.then( function () {
					return run( box );
				} );
			}, Promise.resolve() ).then( function () {
				all.disabled = false;
				all.insertAdjacentElement( 'afterend', el( 'p', { role: 'status' }, cfg.i18n.done ) );
			} );
		}
	} );
}() );
