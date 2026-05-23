<?php
/**
 * Submissions list table.
 *
 * WP_List_Table extension that powers the wp-admin "PerForm → Submissions"
 * list view. Filtering, sorting and pagination are delegated to the
 * Repository — this class only wires query state to UI state.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Admin;

use PerForm\Submissions\Repository;

defined( 'ABSPATH' ) || exit;

// WP_List_Table is not autoloaded by WordPress core.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the paginated submissions table in wp-admin.
 */
final class SubmissionsListTable extends \WP_List_Table {

	private Repository $repository;

	/**
	 * Active filters parsed from $_GET. Stored so column callbacks and
	 * extra_tablenav() can reuse them without re-sanitising.
	 *
	 * @var array<string, string>
	 */
	private array $filters = [];

	/**
	 * @param Repository $repository
	 */
	public function __construct( Repository $repository ) {
		parent::__construct(
			[
				'singular' => 'submission',
				'plural'   => 'submissions',
				'ajax'     => false,
			]
		);
		$this->repository = $repository;
	}

	/**
	 * Define the table columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'cb'         => '<input type="checkbox" />',
			'created_at' => __( 'Date', 'perform-forms' ),
			'form_id'    => __( 'Form', 'perform-forms' ),
			'preview'    => __( 'Preview', 'perform-forms' ),
			'status'     => __( 'Status', 'perform-forms' ),
		];
	}

	/**
	 * Columns the user can click to sort by.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns(): array {
		return [
			'created_at' => [ 'created_at', true ],
			'form_id'    => [ 'form_id', false ],
			'status'     => [ 'status', false ],
		];
	}

	/**
	 * Bulk action choices.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return [
			'mark_read'   => __( 'Mark as read', 'perform-forms' ),
			'mark_unread' => __( 'Mark as unread', 'perform-forms' ),
			'delete'      => __( 'Delete', 'perform-forms' ),
		];
	}

	/**
	 * Build the items list and pagination args from the current request.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = 20;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only listing parameters.
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$order    = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';
		// phpcs:enable

		$this->filters = $this->read_filters();

		$total = $this->repository->count( $this->filters );
		$rows  = $this->repository->find_paginated( $this->filters, $page, $per_page, $orderby, $order );

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
		$this->items           = $rows;
		$this->set_pagination_args(
			[
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			]
		);
	}

	/**
	 * Empty-state message.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No submissions yet.', 'perform-forms' );
	}

	/**
	 * Fallback column renderer for columns without a custom callback.
	 *
	 * @param array<string, mixed> $item
	 * @param string               $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	/**
	 * Checkbox column.
	 *
	 * @param array<string, mixed> $item
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="ids[]" value="%d" />',
			(int) ( $item['id'] ?? 0 )
		);
	}

	/**
	 * Date column (primary) — includes row actions and a link to the detail view.
	 *
	 * @param array<string, mixed> $item
	 * @return string
	 */
	public function column_created_at( $item ): string {
		$id           = (int) ( $item['id'] ?? 0 );
		$is_unread    = 'unread' === ( $item['status'] ?? '' );
		$created_gmt  = isset( $item['created_at'] ) ? (string) $item['created_at'] : '';
		$local_ts     = get_date_from_gmt( $created_gmt, 'Y-m-d H:i' );
		$view_url     = add_query_arg(
			[
				'page'   => Menu::PARENT_SLUG,
				'action' => 'view',
				'id'     => $id,
			],
			admin_url( 'admin.php' )
		);
		$delete_nonce = wp_create_nonce( 'perform_delete_' . $id );
		$delete_url   = add_query_arg(
			[
				'page'           => Menu::PARENT_SLUG,
				'perform_action' => 'delete',
				'id'             => $id,
				'_wpnonce'       => $delete_nonce,
			],
			admin_url( 'admin.php' )
		);

		$label = sprintf(
			'<strong><a href="%s">%s</a></strong>',
			esc_url( $view_url ),
			esc_html( $local_ts )
		);
		if ( $is_unread ) {
			$label = '<span class="perform-unread-dot" aria-hidden="true">●</span> ' . $label;
		}

		$actions = [
			'view'   => sprintf( '<a href="%s">%s</a>', esc_url( $view_url ), esc_html__( 'View', 'perform-forms' ) ),
			'delete' => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(%s)">%s</a>',
				esc_url( $delete_url ),
				esc_js( wp_json_encode( __( 'Delete this submission permanently?', 'perform-forms' ) ) ),
				esc_html__( 'Delete', 'perform-forms' )
			),
		];

		return $label . $this->row_actions( $actions );
	}

	/**
	 * Form-ID column (shortened UUID).
	 *
	 * @param array<string, mixed> $item
	 * @return string
	 */
	public function column_form_id( $item ): string {
		$uuid = isset( $item['form_id'] ) ? (string) $item['form_id'] : '';
		if ( '' === $uuid ) {
			return '<em>' . esc_html__( 'unknown', 'perform-forms' ) . '</em>';
		}
		return sprintf(
			'<code title="%s">%s…</code>',
			esc_attr( $uuid ),
			esc_html( substr( $uuid, 0, 8 ) )
		);
	}

	/**
	 * Preview column — first textual field, truncated.
	 *
	 * @param array<string, mixed> $item
	 * @return string
	 */
	public function column_preview( $item ): string {
		$fields = isset( $item['data']['fields'] ) && is_array( $item['data']['fields'] ) ? $item['data']['fields'] : [];

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$value = isset( $field['value'] ) ? (string) $field['value'] : '';
			$label = isset( $field['label'] ) ? (string) $field['label'] : '';
			if ( '' === trim( $value ) ) {
				continue;
			}
			$preview = wp_html_excerpt( $value, 60, '…' );
			return sprintf( '<span class="perform-preview"><strong>%s:</strong> %s</span>', esc_html( $label ), esc_html( $preview ) );
		}

		return '<em>' . esc_html__( 'empty', 'perform-forms' ) . '</em>';
	}

	/**
	 * Status column — colored badge.
	 *
	 * @param array<string, mixed> $item
	 * @return string
	 */
	public function column_status( $item ): string {
		$status = isset( $item['status'] ) ? (string) $item['status'] : 'unread';
		$label  = 'read' === $status ? __( 'Read', 'perform-forms' ) : __( 'Unread', 'perform-forms' );
		return sprintf(
			'<span class="perform-status perform-status--%s">%s</span>',
			esc_attr( $status ),
			esc_html( $label )
		);
	}

	/**
	 * Render the filter row above the table (form + status + date range).
	 *
	 * @param string $which 'top' or 'bottom'.
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$form_ids = $this->repository->distinct_form_ids();
		$current  = $this->filters;
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="filter-form-id"><?php esc_html_e( 'Filter by form', 'perform-forms' ); ?></label>
			<select name="form_id" id="filter-form-id">
				<option value=""><?php esc_html_e( 'All forms', 'perform-forms' ); ?></option>
				<?php foreach ( $form_ids as $uuid ) : ?>
					<option value="<?php echo esc_attr( $uuid ); ?>" <?php selected( $current['form_id'] ?? '', $uuid ); ?>>
						<?php echo esc_html( substr( $uuid, 0, 8 ) . '…' ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="filter-status"><?php esc_html_e( 'Filter by status', 'perform-forms' ); ?></label>
			<select name="status" id="filter-status">
				<option value=""><?php esc_html_e( 'All statuses', 'perform-forms' ); ?></option>
				<option value="unread" <?php selected( $current['status'] ?? '', 'unread' ); ?>><?php esc_html_e( 'Unread', 'perform-forms' ); ?></option>
				<option value="read" <?php selected( $current['status'] ?? '', 'read' ); ?>><?php esc_html_e( 'Read', 'perform-forms' ); ?></option>
			</select>

			<input
				type="date"
				name="date_from"
				value="<?php echo esc_attr( $current['date_from'] ?? '' ); ?>"
				aria-label="<?php esc_attr_e( 'From date', 'perform-forms' ); ?>"
				placeholder="<?php esc_attr_e( 'From', 'perform-forms' ); ?>"
			/>
			<input
				type="date"
				name="date_to"
				value="<?php echo esc_attr( $current['date_to'] ?? '' ); ?>"
				aria-label="<?php esc_attr_e( 'To date', 'perform-forms' ); ?>"
				placeholder="<?php esc_attr_e( 'To', 'perform-forms' ); ?>"
			/>

			<?php submit_button( __( 'Filter', 'perform-forms' ), '', 'filter_action', false ); ?>

			<?php
			$export_url = add_query_arg(
				array_merge(
					[
						'page'           => Menu::PARENT_SLUG,
						'perform_action' => 'export',
						'_wpnonce'       => wp_create_nonce( 'perform_export' ),
					],
					$current
				),
				admin_url( 'admin.php' )
			);
			?>
			<a href="<?php echo esc_url( $export_url ); ?>" class="button">
				<?php esc_html_e( 'Export CSV', 'perform-forms' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Parse + sanitise the filter set from $_GET.
	 *
	 * @return array<string, string>
	 */
	private function read_filters(): array {
		$filters = [];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter inputs.
		if ( ! empty( $_GET['form_id'] ) ) {
			$filters['form_id'] = sanitize_text_field( wp_unslash( $_GET['form_id'] ) );
		}
		if ( ! empty( $_GET['status'] ) ) {
			$filters['status'] = sanitize_key( wp_unslash( $_GET['status'] ) );
		}
		if ( ! empty( $_GET['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) );
		}
		if ( ! empty( $_GET['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( wp_unslash( $_GET['date_to'] ) );
		}
		if ( ! empty( $_GET['s'] ) ) {
			$filters['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}
		// phpcs:enable

		return $filters;
	}
}
