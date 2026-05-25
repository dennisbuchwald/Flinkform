<?php
/**
 * REST controller for the editor-side Webhook CRUD.
 *
 * Endpoints under `/wp-json/perform/v1/webhooks`:
 *   GET    /webhooks?form_id=<uuid> — list webhooks for a form
 *   POST   /webhooks                — create a webhook
 *   GET    /webhooks/{id}           — read a single webhook
 *   PUT    /webhooks/{id}           — update a webhook
 *   DELETE /webhooks/{id}           — delete a webhook (+ its deliveries)
 *
 * Permission: `edit_posts` everywhere — the editor inspector is the
 * only consumer, and editing the block tree of any post already
 * requires that capability.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Webhooks;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for webhook CRUD.
 */
final class RestController {

	public const NAMESPACE = 'perform/v1';
	public const REST_BASE = 'webhooks';

	private Repository $repository;
	private Deliverer $deliverer;

	/**
	 * @param Repository $repository Injected for unit-testing.
	 * @param Deliverer  $deliverer  Used by the test endpoint to fire a sample-payload request synchronously.
	 */
	public function __construct( Repository $repository, Deliverer $deliverer ) {
		$this->repository = $repository;
		$this->deliverer  = $deliverer;
	}

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Wire the four CRUD routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE,
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'list_webhooks' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'form_id' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_webhook' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => $this->item_schema(),
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>\d+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_webhook' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update_webhook' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => $this->item_schema( false ),
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'delete_webhook' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		// Send-test endpoint — fires the configured webhook with a
		// synthetic sample payload and returns the receiver's
		// response inline. Synchronous on purpose: authors need to
		// see the result while they're still on the inspector page.
		register_rest_route(
			self::NAMESPACE,
			'/' . self::REST_BASE . '/(?P<id>\d+)/test',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'test_webhook' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);
	}

	/**
	 * Capability gate. `edit_posts` matches what the block editor
	 * already requires for the post the inspector sits inside.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * GET /webhooks?form_id=<uuid>
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function list_webhooks( WP_REST_Request $request ): WP_REST_Response {
		$form_id = (string) $request->get_param( 'form_id' );
		$rows    = $this->repository->find_for_form( $form_id );

		return new WP_REST_Response( $rows, 200 );
	}

	/**
	 * GET /webhooks/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_webhook( WP_REST_Request $request ) {
		$id  = (int) $request->get_param( 'id' );
		$row = $this->repository->find( $id );

		if ( null === $row ) {
			return new WP_Error( 'perform_webhook_not_found', __( 'Webhook not found.', 'perform-forms' ), [ 'status' => 404 ] );
		}

		return new WP_REST_Response( $row, 200 );
	}

	/**
	 * POST /webhooks
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_webhook( WP_REST_Request $request ) {
		$data = $this->extract_item_payload( $request );

		if ( '' === $data['form_id'] ) {
			return new WP_Error( 'perform_webhook_invalid', __( 'A form id is required.', 'perform-forms' ), [ 'status' => 400 ] );
		}
		if ( '' === $data['url'] ) {
			return new WP_Error( 'perform_webhook_invalid', __( 'A webhook URL is required.', 'perform-forms' ), [ 'status' => 400 ] );
		}

		$id = $this->repository->create( $data );
		if ( null === $id ) {
			return new WP_Error( 'perform_webhook_create_failed', __( 'Could not create webhook.', 'perform-forms' ), [ 'status' => 500 ] );
		}

		$row = $this->repository->find( $id );
		return new WP_REST_Response( $row, 201 );
	}

	/**
	 * PUT /webhooks/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_webhook( WP_REST_Request $request ) {
		$id      = (int) $request->get_param( 'id' );
		$existing = $this->repository->find( $id );
		if ( null === $existing ) {
			return new WP_Error( 'perform_webhook_not_found', __( 'Webhook not found.', 'perform-forms' ), [ 'status' => 404 ] );
		}

		// Merge the incoming payload over the existing row so a partial
		// PUT (e.g. just toggling `is_active`) doesn't blank everything
		// else out. Form id is fixed after creation — clients can't
		// reassign a webhook to another form via a stray param.
		$data            = array_merge( $existing, $this->extract_item_payload( $request ) );
		$data['form_id'] = $existing['form_id'];

		$ok = $this->repository->update( $id, $data );
		if ( ! $ok ) {
			return new WP_Error( 'perform_webhook_update_failed', __( 'Could not update webhook.', 'perform-forms' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( $this->repository->find( $id ), 200 );
	}

	/**
	 * POST /webhooks/{id}/test
	 *
	 * Fires a synthetic Send-test request against the configured
	 * webhook receiver and returns the HTTP status + (truncated)
	 * response body for the inspector to display inline. Doesn't
	 * touch the delivery log table — test sends are one-shot.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_webhook( WP_REST_Request $request ) {
		$id      = (int) $request->get_param( 'id' );
		$webhook = $this->repository->find( $id );
		if ( null === $webhook ) {
			return new WP_Error( 'perform_webhook_not_found', __( 'Webhook not found.', 'perform-forms' ), [ 'status' => 404 ] );
		}

		$result = $this->deliverer->send_test( $webhook );

		return new WP_REST_Response(
			[
				'response_code' => $result['code'],
				'response_body' => $result['body'],
				'ok'            => ( null !== $result['code'] && $result['code'] >= 200 && $result['code'] < 300 ),
			],
			200
		);
	}

	/**
	 * DELETE /webhooks/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_webhook( WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );
		$ok = $this->repository->delete( $id );
		if ( ! $ok ) {
			return new WP_Error( 'perform_webhook_delete_failed', __( 'Could not delete webhook.', 'perform-forms' ), [ 'status' => 404 ] );
		}

		return new WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
	}

	/**
	 * REST schema descriptor for a webhook payload.
	 *
	 * @param bool $form_id_required Whether `form_id` is required (true on create, false on partial update).
	 * @return array<string, array<string, mixed>>
	 */
	private function item_schema( bool $form_id_required = true ): array {
		return [
			'form_id'            => [
				'required'          => $form_id_required,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'label'              => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'url'                => [
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			],
			'method'             => [
				'type' => 'string',
				'enum' => [ 'POST', 'GET' ],
			],
			'format'             => [
				'type' => 'string',
				'enum' => [ 'json', 'form' ],
			],
			'headers'            => [ 'type' => 'object' ],
			'field_mapping'      => [ 'type' => 'object' ],
			'condition_field'    => [ 'type' => 'string' ],
			'condition_operator' => [ 'type' => 'string' ],
			'condition_value'    => [ 'type' => 'string' ],
			'is_active'          => [ 'type' => 'boolean' ],
		];
	}

	/**
	 * Pull every webhook field out of the request body. The args definition
	 * above declares the same set so WP's REST framework sanitises strings
	 * + validates enums automatically; here we just normalise types and
	 * fall back to sensible defaults.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function extract_item_payload( WP_REST_Request $request ): array {
		return [
			'form_id'            => (string) ( $request->get_param( 'form_id' ) ?? '' ),
			'label'              => (string) ( $request->get_param( 'label' ) ?? '' ),
			'url'                => (string) ( $request->get_param( 'url' ) ?? '' ),
			'method'             => (string) ( $request->get_param( 'method' ) ?? 'POST' ),
			'format'             => (string) ( $request->get_param( 'format' ) ?? 'json' ),
			'headers'            => (array) ( $request->get_param( 'headers' ) ?? [] ),
			'field_mapping'      => (array) ( $request->get_param( 'field_mapping' ) ?? [] ),
			'condition_field'    => (string) ( $request->get_param( 'condition_field' ) ?? '' ),
			'condition_operator' => (string) ( $request->get_param( 'condition_operator' ) ?? '' ),
			'condition_value'    => (string) ( $request->get_param( 'condition_value' ) ?? '' ),
			'is_active'          => (bool) ( $request->get_param( 'is_active' ) ?? false ),
		];
	}
}
