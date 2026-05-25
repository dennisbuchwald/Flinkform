<?php
/**
 * Webhook delivery log — admin list table.
 *
 * Renders the global "Webhook Log" page: every dispatched delivery,
 * one row per attempt, with status / response / retry-clock so the
 * site owner can see what's going through their PerForm at a glance.
 *
 * Mirrors the SubmissionsListTable structure — same column callback
 * convention, same pagination wiring, same single-row action set.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Admin;

use PerForm\Webhooks\DeliveryRepository;
use WP_List_Table;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the Webhook Log table.
 */
final class WebhookLogListTable extends WP_List_Table {

	public const PER_PAGE = 30;

	private DeliveryRepository $deliveries;

	public function __construct( DeliveryRepository $deliveries ) {
		parent::__construct(
			[
				'singular' => 'webhook-delivery',
				'plural'   => 'webhook-deliveries',
				'ajax'     => false,
			]
		);
		$this->deliveries = $deliveries;
	}

	/**
	 * Visible columns + their headers.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'id'            => __( 'ID', 'perform-forms' ),
			'webhook'       => __( 'Webhook', 'perform-forms' ),
			'submission'    => __( 'Submission', 'perform-forms' ),
			'status'        => __( 'Status', 'perform-forms' ),
			'response_code' => __( 'Code', 'perform-forms' ),
			'attempt'       => __( 'Attempt', 'perform-forms' ),
			'created_at'    => __( 'Created', 'perform-forms' ),
			'updated_at'    => __( 'Updated', 'perform-forms' ),
		];
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns(): array {
		return [
			'id'         => [ 'id', false ],
			'status'     => [ 'status', false ],
			'attempt'    => [ 'attempt', false ],
			'created_at' => [ 'created_at', true ],
			'updated_at' => [ 'updated_at', false ],
		];
	}

	/**
	 * Prepare the dataset for the table render. Reads filters from
	 * the request, paginates the result, hands the pagination args
	 * back to WP_List_Table so the footer renders right.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];

		$filters = $this->filters_from_request();

		$page    = max( 1, (int) ( $_REQUEST['paged'] ?? 1 ) );      // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';           // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->items = $this->deliveries->find_paginated(
			$filters,
			$page,
			self::PER_PAGE,
			$orderby,
			$order
		);

		$this->set_pagination_args(
			[
				'total_items' => $this->deliveries->count( $filters ),
				'per_page'    => self::PER_PAGE,
			]
		);
	}

	/**
	 * Build a filter map from the current request. Sanitises every
	 * value; an unsupported status falls back to all-results.
	 *
	 * @return array<string, mixed>
	 */
	private function filters_from_request(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		if ( ! in_array( $status, [ 'pending', 'processing', 'retrying', 'success', 'failed' ], true ) ) {
			$status = '';
		}

		$webhook_id = isset( $_REQUEST['webhook_id'] ) ? (int) $_REQUEST['webhook_id'] : 0;
		$search     = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return [
			'status'     => $status,
			'webhook_id' => $webhook_id > 0 ? $webhook_id : null,
			'search'     => $search,
		];
	}

	/**
	 * Empty-state copy when no rows match.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No webhook deliveries yet.', 'perform-forms' );
	}

	public function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	public function column_id( $item ): string {
		return '#' . (int) $item['id'];
	}

	public function column_webhook( $item ): string {
		$label = isset( $item['webhook_label'] ) && '' !== $item['webhook_label']
			? (string) $item['webhook_label']
			: (string) ( $item['webhook_url'] ?? '' );
		$url   = (string) ( $item['webhook_url'] ?? '' );

		if ( '' === $label && '' === $url ) {
			return '<em>' . esc_html__( '(deleted)', 'perform-forms' ) . '</em>';
		}

		$primary = $label !== '' ? esc_html( $label ) : esc_html( $url );
		$sub     = ( $label !== '' && $url !== '' )
			? '<br><small style="opacity:0.7">' . esc_html( $url ) . '</small>'
			: '';

		return $primary . $sub;
	}

	public function column_submission( $item ): string {
		$sid = isset( $item['submission_id'] ) ? (int) $item['submission_id'] : 0;
		if ( $sid <= 0 ) {
			return '<em>' . esc_html__( 'Test', 'perform-forms' ) . '</em>';
		}

		$url = add_query_arg(
			[
				'page'   => 'perform-submissions',
				'action' => 'view',
				'id'     => $sid,
			],
			admin_url( 'admin.php' )
		);

		return '<a href="' . esc_url( $url ) . '">#' . $sid . '</a>';
	}

	public function column_status( $item ): string {
		$status = (string) ( $item['status'] ?? '' );
		$color  = self::status_color( $status );

		return sprintf(
			'<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:%s;color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;">%s</span>',
			esc_attr( $color ),
			esc_html( $status )
		);
	}

	public function column_response_code( $item ): string {
		$code = $item['response_code'] ?? null;
		if ( null === $code || '' === $code ) {
			return '—';
		}
		return esc_html( (string) (int) $code );
	}

	public function column_created_at( $item ): string {
		return $this->format_timestamp( (string) ( $item['created_at'] ?? '' ) );
	}

	public function column_updated_at( $item ): string {
		return $this->format_timestamp( (string) ( $item['updated_at'] ?? '' ) );
	}

	/**
	 * Format a UTC timestamp string into the site's timezone for display.
	 *
	 * @param string $value GMT mysql timestamp from the database.
	 * @return string
	 */
	private function format_timestamp( string $value ): string {
		if ( '' === $value ) {
			return '—';
		}

		$ts = strtotime( $value . ' UTC' );
		if ( ! $ts ) {
			return esc_html( $value );
		}

		return esc_html(
			(string) wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				$ts
			)
		);
	}

	/**
	 * Filter / search row above the table. Renders a status
	 * dropdown plus a button — the standard search box is already
	 * rendered by WP_List_Table when we call search_box() above.
	 *
	 * @param string $which `top` or `bottom` placement.
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';

		$statuses = [
			''           => __( 'All statuses', 'perform-forms' ),
			'pending'    => __( 'Pending', 'perform-forms' ),
			'processing' => __( 'Processing', 'perform-forms' ),
			'retrying'   => __( 'Retrying', 'perform-forms' ),
			'success'    => __( 'Success', 'perform-forms' ),
			'failed'     => __( 'Failed', 'perform-forms' ),
		];

		echo '<div class="alignleft actions">';
		echo '<select name="status">';
		foreach ( $statuses as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		submit_button( __( 'Filter', 'perform-forms' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Map a status string to a status-pill background colour.
	 *
	 * @param string $status Delivery status.
	 * @return string CSS colour.
	 */
	public static function status_color( string $status ): string {
		switch ( $status ) {
			case 'success':
				return '#28a745';
			case 'failed':
				return '#cc0000';
			case 'retrying':
				return '#f0ad4e';
			case 'processing':
				return '#0073aa';
			case 'pending':
			default:
				return '#888';
		}
	}
}
