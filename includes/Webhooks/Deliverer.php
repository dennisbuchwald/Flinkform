<?php
/**
 * Webhook deliverer — sends a single HTTP request and reports back.
 *
 * Stateless on purpose. Dispatcher / RestController construct an
 * instance, hand over a webhook + submission combo, and inspect the
 * returned tuple `[ $code, $body ]`. The repository takes care of
 * the persistence side of things; this class deals only in HTTP.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Webhooks;

use PerForm\Submissions\Repository as SubmissionsRepository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Outgoing HTTP delivery.
 */
final class Deliverer {

	/**
	 * Wall-clock cap on each outbound request. Long enough for slow
	 * webhook receivers (n8n cold-starts, Zapier queues) without
	 * letting a single hung delivery block a cron tick for too long.
	 */
	private const TIMEOUT_SECONDS = 10;

	/**
	 * Maximum number of bytes from the receiver's response body that
	 * gets persisted to the delivery log. Anything over this gets
	 * truncated with an ellipsis — keeps the log queries fast and the
	 * admin UI responsive.
	 */
	private const MAX_LOG_BODY_BYTES = 4096;

	private SubmissionsRepository $submissions;

	/**
	 * @param SubmissionsRepository $submissions Used to resolve a submission_id back to its row at dispatch time.
	 */
	public function __construct( SubmissionsRepository $submissions ) {
		$this->submissions = $submissions;
	}

	/**
	 * Deliver a webhook for an actual submission.
	 *
	 * @param array<string, mixed> $webhook       Hydrated webhook config row.
	 * @param int|null             $submission_id Submission row id, or null for a Send-test run.
	 * @return array{code: int|null, body: string} HTTP status + truncated response body.
	 */
	public function deliver( array $webhook, ?int $submission_id ): array {
		$payload = $this->build_payload( $webhook, $submission_id );
		return $this->send_request( $webhook, $payload );
	}

	/**
	 * Deliver a Send-test request with a synthetic sample payload.
	 *
	 * Used by the inspector test button so authors can validate URL,
	 * auth headers, and receiver-side handling without creating a
	 * real submission. The sample payload matches the production
	 * shape exactly — same envelope, same field naming convention —
	 * with placeholder values so receivers can wire mappings against
	 * the same keys they'll see in production.
	 *
	 * @param array<string, mixed> $webhook Hydrated webhook config row.
	 * @return array{code: int|null, body: string}
	 */
	public function send_test( array $webhook ): array {
		$payload = [
			'form_id'       => isset( $webhook['form_id'] ) ? (string) $webhook['form_id'] : '',
			'submission_id' => 0,
			'submitted_at'  => gmdate( 'c' ),
			'is_test'       => true,
			'site'          => [
				'name' => (string) get_bloginfo( 'name' ),
				'url'  => (string) home_url( '/' ),
			],
			'fields'        => [
				'name'    => 'PerForm Test',
				'email'   => 'test@example.com',
				'message' => 'This is a sample payload sent from the PerForm inspector.',
			],
		];

		return $this->send_request( $webhook, $payload );
	}

	/**
	 * Build the standard outgoing payload for a real submission.
	 *
	 * Shape: { form_id, submission_id, submitted_at, site, fields: {…} }
	 * where `fields` is keyed by the form's field names (the same
	 * `name=` attribute the user-facing inputs carry), so receivers
	 * can wire mappings against stable identifiers rather than
	 * positional indices.
	 *
	 * @param array<string, mixed> $webhook       Hydrated webhook config row.
	 * @param int|null             $submission_id Submission row id.
	 * @return array<string, mixed>
	 */
	private function build_payload( array $webhook, ?int $submission_id ): array {
		$payload = [
			'form_id'       => isset( $webhook['form_id'] ) ? (string) $webhook['form_id'] : '',
			'submission_id' => $submission_id,
			'submitted_at'  => gmdate( 'c' ),
			'is_test'       => false,
			'site'          => [
				'name' => (string) get_bloginfo( 'name' ),
				'url'  => (string) home_url( '/' ),
			],
			'fields'        => [],
		];

		if ( null === $submission_id ) {
			return $payload;
		}

		$submission = $this->submissions->find( $submission_id );
		if ( null === $submission ) {
			return $payload;
		}

		$payload['submitted_at'] = isset( $submission['created_at'] ) ? (string) $submission['created_at'] : $payload['submitted_at'];

		// The submission `data` array comes from the handler as a
		// self-contained shape: each field carries its name + label +
		// type + value. For the outgoing payload we project that down
		// to a flat name=>value map; the receiver doesn't care about
		// labels or types, and including them would double the
		// payload size for no integration win.
		$fields = [];
		if ( isset( $submission['data']['fields'] ) && is_array( $submission['data']['fields'] ) ) {
			foreach ( $submission['data']['fields'] as $field ) {
				if ( ! is_array( $field ) || ! isset( $field['name'] ) ) {
					continue;
				}
				$name = (string) $field['name'];
				if ( '' === $name ) {
					continue;
				}
				$fields[ $name ] = $field['value'] ?? '';
			}
		}
		$payload['fields'] = $fields;

		return $payload;
	}

	/**
	 * Execute the HTTP request for a given webhook + payload.
	 *
	 * Translates the configured method/format/headers into a
	 * wp_remote_request call, runs it, and normalises the response
	 * (or error) into the `[ code, body ]` shape the dispatcher
	 * persists. The body is truncated to MAX_LOG_BODY_BYTES so the
	 * log table can't grow unbounded on chatty webhook receivers.
	 *
	 * @param array<string, mixed> $webhook Hydrated webhook config row.
	 * @param array<string, mixed> $payload Built payload to send.
	 * @return array{code: int|null, body: string}
	 */
	private function send_request( array $webhook, array $payload ): array {
		$method  = isset( $webhook['method'] ) ? strtoupper( (string) $webhook['method'] ) : 'POST';
		$format  = isset( $webhook['format'] ) ? strtolower( (string) $webhook['format'] ) : 'json';
		$headers = isset( $webhook['headers'] ) && is_array( $webhook['headers'] ) ? $webhook['headers'] : [];
		$url     = isset( $webhook['url'] ) ? (string) $webhook['url'] : '';

		if ( '' === $url ) {
			return [ 'code' => null, 'body' => 'No URL configured.' ];
		}

		$args = [
			'method'  => $method,
			'timeout' => self::TIMEOUT_SECONDS,
			'headers' => $headers,
		];

		// Serialise the payload into the body (POST) or query string (GET)
		// according to the chosen format. Form-encoded uses `_perform_`
		// prefixed meta keys for the envelope fields so receivers that
		// parse a flat $_POST don't have to know about a nested
		// `fields[name]=value` array syntax.
		if ( 'GET' === $method ) {
			$flat = $this->flatten_for_form_encoded( $payload );
			$url  = add_query_arg( array_map( 'rawurlencode', $flat ), $url );
		} elseif ( 'json' === $format ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = (string) wp_json_encode( $payload );
		} else {
			$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
			$args['body']                    = $this->flatten_for_form_encoded( $payload );
		}

		$args['user-agent'] = 'PerForm/' . PERFORM_VERSION . ' (+' . home_url( '/' ) . ')';

		$response = wp_remote_request( $url, $args );

		if ( $response instanceof WP_Error ) {
			return [
				'code' => null,
				'body' => $this->truncate( $response->get_error_message() ),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		return [
			'code' => $code,
			'body' => $this->truncate( $body ),
		];
	}

	/**
	 * Flatten the nested payload into a string=>string map suitable
	 * for `application/x-www-form-urlencoded` or query-string use.
	 * Field values pass through directly under their field names;
	 * envelope keys get a `_perform_` prefix so they don't collide
	 * with user-defined field names that happen to be called
	 * "form_id" or "site".
	 *
	 * @param array<string, mixed> $payload Built payload.
	 * @return array<string, string>
	 */
	private function flatten_for_form_encoded( array $payload ): array {
		$out = [];

		$envelope = [
			'form_id'       => '_perform_form_id',
			'submission_id' => '_perform_submission_id',
			'submitted_at'  => '_perform_submitted_at',
			'is_test'       => '_perform_is_test',
		];
		foreach ( $envelope as $key => $target ) {
			if ( isset( $payload[ $key ] ) ) {
				$out[ $target ] = is_scalar( $payload[ $key ] )
					? (string) $payload[ $key ]
					: (string) wp_json_encode( $payload[ $key ] );
			}
		}

		if ( isset( $payload['site'] ) && is_array( $payload['site'] ) ) {
			foreach ( $payload['site'] as $sk => $sv ) {
				$out[ '_perform_site_' . $sk ] = is_scalar( $sv ) ? (string) $sv : (string) wp_json_encode( $sv );
			}
		}

		if ( isset( $payload['fields'] ) && is_array( $payload['fields'] ) ) {
			foreach ( $payload['fields'] as $fk => $fv ) {
				$out[ (string) $fk ] = is_scalar( $fv ) ? (string) $fv : (string) wp_json_encode( $fv );
			}
		}

		return $out;
	}

	/**
	 * Cut a response body down to MAX_LOG_BODY_BYTES with an ellipsis
	 * marker, multibyte-safe.
	 *
	 * @param string $body Raw response body.
	 * @return string
	 */
	private function truncate( string $body ): string {
		if ( strlen( $body ) <= self::MAX_LOG_BODY_BYTES ) {
			return $body;
		}

		// mb_strcut respects byte boundaries for multibyte text — a
		// hard substr would chop UTF-8 sequences in the middle and
		// produce invalid output.
		$cut = function_exists( 'mb_strcut' )
			? (string) mb_strcut( $body, 0, self::MAX_LOG_BODY_BYTES - 1, 'UTF-8' )
			: substr( $body, 0, self::MAX_LOG_BODY_BYTES - 1 );

		return $cut . '…';
	}
}
