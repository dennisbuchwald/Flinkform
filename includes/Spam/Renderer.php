<?php
/**
 * Spam-challenge markup renderer.
 *
 * Emits the per-form challenge HTML that the form-container
 * `render.php` injects into every protected `<form>`. The markup
 * carries:
 *
 *   - One hidden input  `perform_spam_token`     — the HMAC-signed
 *                                                  token from Challenge::mint().
 *   - One hidden input  `perform_spam_solution`  — populated by the
 *                                                  PoW solver in view.js.
 *   - One visible input `perform_spam_answer`   — math fallback,
 *                                                  shown via @media or
 *                                                  noscript-equivalent
 *                                                  defaults to hidden
 *                                                  if Interactivity API
 *                                                  hydrates and the PoW
 *                                                  succeeds.
 *
 * The math row is rendered with `hidden` set by default — the
 * frontend script removes the attribute only if PoW fails. JS-
 * disabled visitors see the row immediately (the attribute lookup
 * fails and the script never runs, so the `hidden` attribute is
 * removed by the noscript fallback below).
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Spam;

defined( 'ABSPATH' ) || exit;

/**
 * Static renderer (no instance state needed).
 */
final class Renderer {

	/**
	 * Hidden input name carrying the signed token.
	 *
	 * @var string
	 */
	public const FIELD_TOKEN = 'perform_spam_token';

	/**
	 * Hidden input name carrying the PoW solution (integer string).
	 *
	 * @var string
	 */
	public const FIELD_SOLUTION = 'perform_spam_solution';

	/**
	 * Visible input name for the math fallback answer.
	 *
	 * @var string
	 */
	public const FIELD_ANSWER = 'perform_spam_answer';

	/**
	 * Render the spam-challenge block for a form.
	 *
	 * @param string $form_id Form UUID.
	 * @return string Already-escaped HTML, safe to echo straight
	 *                into render.php.
	 */
	public static function render( string $form_id ): string {
		$challenge = Challenge::mint( $form_id );

		$token    = (string) $challenge['token'];
		$salt     = (string) ( $challenge['pow']['salt'] ?? '' );
		$diff     = (int) ( $challenge['pow']['difficulty'] ?? Challenge::POW_DIFFICULTY );
		$question = (string) ( $challenge['math']['question'] ?? '' );

		// data-perform-spam wraps the whole block so view.js can
		// pick it up via a single querySelector per form. The
		// data-perform-pow-* attributes carry the parameters the
		// solver needs — no extra fetch, no JSON inside an
		// attribute, no parse step.
		$markup  = '<div class="perform-form__spam"';
		$markup .= ' data-perform-spam="1"';
		$markup .= ' data-perform-pow-salt="' . esc_attr( $salt ) . '"';
		$markup .= ' data-perform-pow-difficulty="' . esc_attr( (string) $diff ) . '"';
		$markup .= '>';

		// Token + solution: always hidden, always present. The
		// solver writes into FIELD_SOLUTION; the math row writes
		// into FIELD_ANSWER. Either one passing server-side
		// satisfies Challenge::verify().
		$markup .= '<input type="hidden" name="' . esc_attr( self::FIELD_TOKEN ) . '" value="' . esc_attr( $token ) . '" />';
		$markup .= '<input type="hidden" name="' . esc_attr( self::FIELD_SOLUTION ) . '" value="" data-perform-spam-solution />';

		// Math row — visible by default for JS-disabled visitors,
		// hidden by view.js as soon as PoW succeeds. The `hidden`
		// attribute is NOT set on initial render: an aggressive
		// caching plugin could otherwise serve the JS-on layout
		// (hidden) to a JS-off visitor and leave them with no way
		// to submit. view.js sets `hidden` AFTER the form is in
		// the DOM, which means the cached HTML never carries it.
		//
		// Inline `style="display:none"` would have the same caching
		// problem; `hidden` is the right semantic but needs to be
		// applied client-side. Until view.js runs, the math row is
		// visible — that's a fraction of a second on fast paths,
		// invisible to the user on the typical sub-100ms hydration.
		$markup .= '<div class="perform-form__spam-math" data-perform-spam-math>';
		$markup .= '<label class="perform-form__spam-label">';
		$markup .= esc_html( $question );
		$markup .= ' <input type="text" name="' . esc_attr( self::FIELD_ANSWER ) . '" value="" autocomplete="off" inputmode="numeric" pattern="[0-9]*" size="4" />';
		$markup .= '</label>';
		$markup .= '<p class="perform-form__spam-hint">' . esc_html__( 'Spam protection — answer the question above to submit the form.', 'perform-forms' ) . '</p>';
		$markup .= '</div>';

		$markup .= '</div>';

		return $markup;
	}
}
