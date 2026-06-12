<?php
/**
 * Spam-protection façade for Flinkform.
 *
 * Single integration point the rest of the codebase calls:
 *
 *   - Submissions\Handler::handle() asks `Guard::verify_submission()`
 *     after the time-check, before field validation. A false return
 *     means "silent reject" (mirrors honeypot semantics).
 *   - form-container/render.php asks `Guard::should_protect()` to
 *     decide whether to emit the challenge markup at all.
 *
 * Keeping all "is this form protected? what strategy is active?"
 * logic behind this façade means we can later plug in external
 * providers (Phase B-b/B-c: Turnstile, hCaptcha, reCAPTCHA) without
 * touching the handler or the render.php — only Guard needs to
 * grow a strategy switch.
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Spam;

defined( 'ABSPATH' ) || exit;

/**
 * Spam-protection façade.
 */
final class Guard {

	/**
	 * Decide whether a form should render + verify a challenge.
	 *
	 * The `spamProtection` block attribute drives the decision:
	 *
	 *   'auto'    → use the site-wide default (currently always
	 *                built-in; Phase B-b will introduce a global
	 *                "select provider" admin setting).
	 *   'builtin' → force built-in PoW + math challenge.
	 *   'none'    → no challenge (form author opted out — honeypot
	 *                and time-check from Phase 1 still apply).
	 *
	 * Returns the resolved strategy name so callers can branch
	 * on it directly. Today only 'builtin' and 'none' are
	 * meaningful return values; Phase B-b will add 'turnstile' etc.
	 *
	 * @param array<string, mixed> $form_attributes Block attributes from the form-container.
	 * @return string  'builtin' | 'none' | a Pro-registered provider key (e.g. 'turnstile')
	 */
	public static function resolve_strategy( array $form_attributes ): string {
		$attr = isset( $form_attributes['spamProtection'] ) && is_string( $form_attributes['spamProtection'] )
			? sanitize_key( $form_attributes['spamProtection'] )
			: 'auto';

		if ( 'none' === $attr ) {
			return 'none';
		}

		// The built-in PoW + math challenge is a Pro capability. When Pro is
		// absent the free core degrades to 'none' — honeypot + time-check
		// (Phase 1) still apply upstream and require zero configuration.
		if ( 'builtin' === $attr || 'auto' === $attr ) {
			return \Flinkform\Bridge\Features::has( \Flinkform\Bridge\Features::SPAM_CHALLENGE )
				? 'builtin'
				: 'none';
		}

		/**
		 * Filter the registered spam-protection providers.
		 *
		 * The Pro add-on registers external providers here (e.g. 'turnstile',
		 * 'hcaptcha', 'recaptcha'). A form requesting a provider that is not
		 * registered — e.g. a form saved with 'turnstile' but Pro since
		 * deactivated — degrades gracefully to 'none' (honeypot + time-check
		 * still apply) rather than leaving the form unprotected.
		 *
		 * @since 0.2.0
		 *
		 * @param array<int, string> $providers Registered provider keys.
		 */
		$providers = (array) apply_filters( 'flinkform_spam_providers', [] );

		return in_array( $attr, $providers, true ) ? $attr : 'none';
	}

	/**
	 * Convenience: does this form get a rendered challenge?
	 *
	 * @param array<string, mixed> $form_attributes
	 * @return bool
	 */
	public static function should_protect( array $form_attributes ): bool {
		return 'none' !== self::resolve_strategy( $form_attributes );
	}

	/**
	 * Verify the submission against the active strategy.
	 *
	 * Returns true on legitimate-looking submissions, false when
	 * the challenge fails. The caller (Submissions\Handler) treats
	 * false the same way it treats a honeypot hit: silent_reject().
	 *
	 * When the form is not protected (strategy === 'none'), we
	 * return true immediately — Phase-1 baseline (honeypot + time)
	 * still gated the submission upstream.
	 *
	 * @param string               $form_id         UUID of the form being submitted.
	 * @param array<string, mixed> $form_attributes Block attributes from the form-container.
	 * @return bool
	 */
	public static function verify_submission( string $form_id, array $form_attributes ): bool {
		if ( 'none' === self::resolve_strategy( $form_attributes ) ) {
			return true;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce already validated upstream in Handler::handle().
		$token       = isset( $_POST[ Renderer::FIELD_TOKEN ] ) ? sanitize_text_field( wp_unslash( $_POST[ Renderer::FIELD_TOKEN ] ) ) : '';
		$pow_sol     = isset( $_POST[ Renderer::FIELD_SOLUTION ] ) ? sanitize_text_field( wp_unslash( $_POST[ Renderer::FIELD_SOLUTION ] ) ) : '';
		$math_answer = isset( $_POST[ Renderer::FIELD_ANSWER ] ) ? sanitize_text_field( wp_unslash( $_POST[ Renderer::FIELD_ANSWER ] ) ) : '';
		// phpcs:enable

		if ( '' === $token ) {
			// Token missing — either the form was rendered before
			// B-a shipped (legacy form not yet re-rendered) or a
			// bot submitted without bothering with the challenge
			// markup. Reject either way; legitimate visitors re-
			// rendering the page after the upgrade get a fresh
			// token automatically.
			return false;
		}

		return Challenge::verify(
			$token,
			$form_id,
			[
				'pow_solution' => $pow_sol,
				'math_answer'  => $math_answer,
			]
		);
	}
}
