<?php
/**
 * Spam-challenge markup renderer.
 *
 * Emits the per-form challenge HTML that the form-container
 * `render.php` injects into every protected `<form>`. The markup
 * carries:
 *
 *   - One hidden input  `flinkform_spam_token`     — the HMAC-signed
 *                                                  token from Challenge::mint().
 *   - One hidden input  `flinkform_spam_solution`  — populated by the
 *                                                  PoW solver in view.js.
 *   - One visible input `flinkform_spam_answer`   — math fallback,
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
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Spam;

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
	public const FIELD_TOKEN = 'flinkform_spam_token';

	/**
	 * Hidden input name carrying the PoW solution (integer string).
	 *
	 * @var string
	 */
	public const FIELD_SOLUTION = 'flinkform_spam_solution';

	/**
	 * Visible input name for the math fallback answer.
	 *
	 * @var string
	 */
	public const FIELD_ANSWER = 'flinkform_spam_answer';

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

		// data-flinkform-spam wraps the whole block so view.js can
		// pick it up via a single querySelector per form. The
		// data-flinkform-pow-* attributes carry the parameters the
		// solver needs — no extra fetch, no JSON inside an
		// attribute, no parse step.
		$markup  = '<div class="flinkform-form__spam"';
		$markup .= ' data-flinkform-spam="1"';
		$markup .= ' data-flinkform-pow-salt="' . esc_attr( $salt ) . '"';
		$markup .= ' data-flinkform-pow-difficulty="' . esc_attr( (string) $diff ) . '"';
		$markup .= '>';

		// Token + solution: always hidden, always present. The
		// solver writes into FIELD_SOLUTION; the math row writes
		// into FIELD_ANSWER. Either one passing server-side
		// satisfies Challenge::verify().
		$markup .= '<input type="hidden" name="' . esc_attr( self::FIELD_TOKEN ) . '" value="' . esc_attr( $token ) . '" />';
		$markup .= '<input type="hidden" name="' . esc_attr( self::FIELD_SOLUTION ) . '" value="" data-flinkform-spam-solution />';

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
		$math_id = 'flinkform-spam-answer-' . substr( md5( $form_id ), 0, 8 );
		$hint_id = $math_id . '-hint';

		$markup .= '<div class="flinkform-form__spam-math" data-flinkform-spam-math>';
		$markup .= '<label class="flinkform-form__spam-label" for="' . esc_attr( $math_id ) . '">';
		$markup .= esc_html( $question );
		$markup .= '</label>';
		// `required` gives JS-off visitors proper inline validation. On the
		// JS path view.js clears AND removes `required` when it hides this row
		// after the PoW solves, so a hidden field never blocks submission.
		$markup .= ' <input type="text" id="' . esc_attr( $math_id ) . '" name="' . esc_attr( self::FIELD_ANSWER ) . '" value="" autocomplete="off" inputmode="numeric" pattern="[0-9]*" size="4" required aria-describedby="' . esc_attr( $hint_id ) . '" />';
		$markup .= '<p class="flinkform-form__spam-hint" id="' . esc_attr( $hint_id ) . '">' . esc_html__( 'Spam protection — answer the question above to submit the form.', 'flinkform' ) . '</p>';
		$markup .= '</div>';

		$markup .= '</div>';

		return $markup;
	}
}
