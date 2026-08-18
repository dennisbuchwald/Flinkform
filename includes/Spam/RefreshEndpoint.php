<?php
/**
 * REST endpoint that reissues spam-challenge tokens.
 *
 * The challenge token is minted into the page at render time with a
 * 30-minute TTL. Visitors who keep the form open longer (long reads,
 * multi-step forms, a tab parked overnight) — or who received the page
 * from an HTML cache — would submit an expired token. The server-side
 * safety net keeps their input alive in that case, but the smooth path
 * is to never let the token age out at all: view.js calls this endpoint
 * periodically and swaps the hidden inputs for a fresh challenge.
 *
 * Deliberately stateless and cheap: minting writes nothing (the replay
 * transient is only set when a token is burnt on acceptance), so this
 * endpoint is no more of an attack surface than the page render that
 * emits the same token markup. It hands out challenges, never verdicts
 * — every token still has to be solved and pass the full submission
 * gauntlet.
 *
 * The response also carries a fresh submit nonce for the requesting
 * visitor, so a page older than the nonce lifetime (12-24h) heals
 * itself along with the token.
 *
 * @package Flinkform
 * @since 1.13.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Spam;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and serves GET /wp-json/flinkform/v1/challenge.
 */
final class RefreshEndpoint {

	private const REST_NAMESPACE = 'flinkform/v1';
	private const REST_ROUTE     = '/challenge';

	/**
	 * Hook the route registration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			[
				'methods'             => 'GET',
				// Public on purpose: the same token markup is served to every
				// anonymous visitor in the page render. The endpoint only
				// issues challenges (no state change, no data access), so
				// there is nothing to gate.
				'permission_callback' => '__return_true',
				'callback'            => [ $this, 'serve' ],
				'args'                => [
					'form_id' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Mint and return a fresh challenge for the requested form.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function serve( \WP_REST_Request $request ) {
		$form_id = (string) $request->get_param( 'form_id' );
		if ( '' === $form_id || strlen( $form_id ) > 64 ) {
			return new \WP_Error( 'flinkform_bad_form_id', 'Invalid form id.', [ 'status' => 400 ] );
		}

		// A cached challenge is a broken challenge — make sure neither the
		// page cache nor an edge cache holds on to this response.
		nocache_headers();

		return rest_ensure_response( self::payload( $form_id ) );
	}

	/**
	 * Build the response body. Split out so it stays testable without a
	 * WP_REST_Request round-trip.
	 *
	 * @param string $form_id Form UUID the token is bound to.
	 * @return array{token: string, salt: string, difficulty: int, question: string, nonce: string}
	 */
	public static function payload( string $form_id ): array {
		$challenge = Challenge::mint( $form_id );

		return [
			'token'      => (string) $challenge['token'],
			'salt'       => (string) ( $challenge['pow']['salt'] ?? '' ),
			'difficulty' => (int) ( $challenge['pow']['difficulty'] ?? Challenge::POW_DIFFICULTY ),
			'question'   => (string) ( $challenge['math']['question'] ?? '' ),
			'nonce'      => wp_create_nonce( 'flinkform_submit_' . $form_id ),
		];
	}
}
