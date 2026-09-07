/**
 * Spam-challenge refresh — DOM application logic.
 *
 * The server mints the challenge token into the page with a 30-minute
 * TTL. view.js periodically fetches a fresh challenge from the REST
 * endpoint and calls applyChallengeData() to swap the rendered one in
 * place, so a long-open page never submits an aged token.
 *
 * Kept as a shared module (same pattern as rule-evaluator and
 * group-validation) so tests/challenge-refresh.mjs can exercise the
 * DOM mutations in jsdom without dragging in the whole view script.
 */

/**
 * Read the expiry epoch (seconds) out of a challenge token.
 *
 * The token is base64url(JSON payload) + "." + HMAC — the payload is
 * not a secret (the server signs it, it doesn't encrypt it), so the
 * client may read `e` to know when the token actually expires. That
 * matters for pages served from an HTML cache: the token can already
 * be old when the script first runs, and "age since script start"
 * would miss that entirely.
 *
 * @param {string} token The value of the flinkform_spam_token input.
 * @return {number} Expiry as unix epoch seconds, or 0 when unreadable.
 */
export function challengeExpiry( token ) {
	if ( typeof token !== 'string' || token.indexOf( '.' ) < 1 ) {
		return 0;
	}
	try {
		const encoded = token.split( '.' )[ 0 ].replace( /-/g, '+' ).replace( /_/g, '/' );
		const payload = JSON.parse( atob( encoded ) );
		const expiry  = payload && typeof payload.e === 'number' ? payload.e : 0;
		return expiry > 0 ? expiry : 0;
	} catch {
		return 0;
	}
}

/**
 * Whether a pending refresh should be skipped for now.
 *
 * When the PoW solver could not finish, the math fallback row is
 * visible and the visitor may be mid-answer. Swapping the question
 * under their cursor would invalidate what they typed — defer and let
 * the next tick retry. (An aged token is still caught server-side.)
 *
 * @param {Element} block The `.flinkform-form__spam` element.
 * @return {boolean}
 */
export function shouldDeferRefresh( block ) {
	const mathRow = block.querySelector( '[data-flinkform-spam-math]' );
	if ( ! mathRow || mathRow.hasAttribute( 'hidden' ) ) {
		return false;
	}
	const mathInput = mathRow.querySelector( 'input[type="text"]' );
	return !! ( mathInput && mathInput.value !== '' );
}

/**
 * Write the form-level challenge values: submit nonce and signed timestamp.
 *
 * Both live on the form, not on the spam block, because a form with spam
 * protection switched off still needs them — and since 1.14.0 a deferred
 * render ships them empty so the page can be cached. This is the only
 * place they are filled in.
 *
 * @param {HTMLFormElement} form The form element.
 * @param {Object}          data Endpoint payload: nonce, ts.
 * @return {boolean} Whether anything was written.
 */
export function applyFormChallenge( form, data ) {
	if ( ! form || ! data ) {
		return false;
	}

	let written = false;

	if ( typeof data.nonce === 'string' && data.nonce !== '' ) {
		const nonceInput = form.querySelector( 'input[name="_flinkform_nonce"]' );
		if ( nonceInput ) {
			nonceInput.value = data.nonce;
			written = true;
		}
	}

	if ( typeof data.ts === 'string' && data.ts !== '' ) {
		const tsInput = form.querySelector( 'input[name="flinkform_ts"]' );
		if ( tsInput ) {
			tsInput.value = data.ts;
			written = true;
		}
	}

	return written;
}

/**
 * Swap the rendered challenge for freshly issued data.
 *
 * Updates the token input, the PoW parameters the solver reads, the
 * math fallback question, and the form's submit nonce. The solution
 * and answer inputs are cleared — they belong to the old salt and the
 * caller re-runs the solver afterwards.
 *
 * @param {Element} block The `.flinkform-form__spam` element.
 * @param {Object}  data  Endpoint payload: token, salt, difficulty, question, nonce.
 * @return {boolean} Whether the swap was applied.
 */
export function applyChallengeData( block, data ) {
	if ( ! data || typeof data.token !== 'string' || data.token === '' || typeof data.salt !== 'string' || data.salt === '' ) {
		return false;
	}

	const tokenInput = block.querySelector( 'input[name="flinkform_spam_token"]' );
	if ( ! tokenInput ) {
		return false;
	}

	tokenInput.value = data.token;
	block.setAttribute( 'data-flinkform-pow-salt', data.salt );
	if ( typeof data.difficulty === 'number' && data.difficulty > 0 ) {
		block.setAttribute( 'data-flinkform-pow-difficulty', String( data.difficulty ) );
	}

	// The solution input is deliberately NOT cleared here. The caller solves
	// the new challenge first and then writes token and solution together, so
	// clearing it in this step would re-open the exact race this avoids: a
	// fresh token next to an empty solution. Until the caller writes the new
	// solution, the previous one stays in place — paired with the new salt it
	// simply fails the proof-of-work and takes the gentle "please resend"
	// path, never the silent drop.

	const mathRow = block.querySelector( '[data-flinkform-spam-math]' );
	if ( mathRow ) {
		if ( typeof data.question === 'string' && data.question !== '' ) {
			const label = mathRow.querySelector( 'label' );
			if ( label ) {
				label.textContent = data.question;
			}
		}
		const mathInput = mathRow.querySelector( 'input[type="text"]' );
		if ( mathInput ) {
			mathInput.value = '';
		}
	}

	// The endpoint issues the nonce and the signed timestamp for the
	// requesting visitor, so a page older than the nonce lifetime — or one
	// that never carried either value because it was rendered deferred —
	// heals along with the token.
	applyFormChallenge( block.closest( 'form' ), data );

	return true;
}
