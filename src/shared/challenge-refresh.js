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

	const solutionInput = block.querySelector( '[data-flinkform-spam-solution]' );
	if ( solutionInput ) {
		solutionInput.value = '';
	}

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

	// The endpoint issues the nonce for the requesting visitor, so a page
	// older than the nonce lifetime heals along with the token.
	if ( typeof data.nonce === 'string' && data.nonce !== '' ) {
		const form = block.closest( 'form' );
		const nonceInput = form ? form.querySelector( 'input[name="_flinkform_nonce"]' ) : null;
		if ( nonceInput ) {
			nonceInput.value = data.nonce;
		}
	}

	return true;
}
