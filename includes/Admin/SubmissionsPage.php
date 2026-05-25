<?php
/**
 * Submissions admin page controller.
 *
 * Handles three responsibilities:
 *
 *  1. dispatch() — runs on admin_init for any incoming bulk action or
 *     row action (delete, mark read/unread, CSV export). Mutating
 *     handlers redirect back to a clean URL after running, so the user
 *     never has a destructive action sitting in the address bar.
 *  2. render()   — renders either the list view or the single-submission
 *     detail view depending on the `?action=view&id=...` query args.
 *  3. Inline CSS — a tiny block of styles for the unread dot, status
 *     badge and detail layout. Kept inline because there's not enough
 *     CSS here to justify an enqueued stylesheet for a hundred bytes.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Admin;

use PerForm\Forms\Indexer;
use PerForm\Submissions\Exporter;
use PerForm\Submissions\Repository;
use PerForm\Webhooks\DeliveryRepository;
use PerForm\Webhooks\Dispatcher as WebhookDispatcher;

defined( 'ABSPATH' ) || exit;

/**
 * Controller for the PerForm → Submissions page.
 */
final class SubmissionsPage {

	private Repository $repository;

	public function __construct( ?Repository $repository = null ) {
		$this->repository = $repository ?? new Repository();
	}

	/**
	 * Handle mutating actions early, before headers go out.
	 *
	 * Single-row actions and the CSV export ship with their own per-action
	 * nonces in the URL (`perform_action=...`). Bulk actions arrive via
	 * WP_List_Table's outer form using the standard `action`/`action2`
	 * pair plus the `bulk-submissions` nonce.
	 *
	 * @return void
	 */
	public function dispatch(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified inside each branch.
		$single_action = isset( $_GET['perform_action'] ) ? sanitize_key( wp_unslash( $_GET['perform_action'] ) ) : '';
		if ( '' !== $single_action ) {
			$this->handle_single_action( $single_action );
			return;
		}

		$bulk_action = $this->resolve_bulk_action();
		if ( '' !== $bulk_action ) {
			$this->handle_bulk( $bulk_action );
		}
	}

	/**
	 * Resolve the bulk-action selection from the request, honouring both
	 * the top and bottom action dropdowns and ignoring requests where the
	 * "Filter" button was clicked instead of a bulk action.
	 *
	 * @return string
	 */
	private function resolve_bulk_action(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce verified in handle_bulk before any mutation.
		if ( ! empty( $_REQUEST['filter_action'] ) ) {
			return '';
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( '' === $action || '-1' === $action ) {
			$action = isset( $_REQUEST['action2'] ) ? sanitize_key( wp_unslash( $_REQUEST['action2'] ) ) : '';
		}
		// phpcs:enable

		return in_array( $action, [ 'mark_read', 'mark_unread', 'delete' ], true ) ? $action : '';
	}

	/**
	 * URL of the submission-detail view for a given id.
	 *
	 * @param int $id Submission id.
	 * @return string
	 */
	private function detail_url( int $id ): string {
		return add_query_arg(
			[
				'page'   => Menu::PARENT_SLUG,
				'action' => 'view',
				'id'     => $id,
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Render the page (list or detail).
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view PerForm submissions.', 'perform-forms' ) );
		}

		$this->print_inline_styles();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'view' === $action ) {
			$this->render_detail();
			return;
		}

		$this->render_list();
	}

	/**
	 * Render the paginated list view.
	 *
	 * @return void
	 */
	private function render_list(): void {
		$table = new SubmissionsListTable( $this->repository );
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Submissions', 'perform-forms' ); ?></h1>
			<hr class="wp-header-end" />

			<?php $this->maybe_print_notice(); ?>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( Menu::PARENT_SLUG ); ?>" />
				<?php
				$table->search_box( __( 'Search submissions', 'perform-forms' ), 'perform-submissions-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the single-submission detail view.
	 *
	 * @return void
	 */
	private function render_detail(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$submission = $this->repository->find( $id );

		if ( null === $submission ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Submission not found', 'perform-forms' ) . '</h1><p><a href="' . esc_url( $this->list_url() ) . '">' . esc_html__( '← Back to submissions', 'perform-forms' ) . '</a></p></div>';
			return;
		}

		// Auto mark-as-read on first view — convenient and matches every
		// other "messages" UI in WordPress (Comments, etc.).
		if ( 'unread' === $submission['status'] ) {
			$this->repository->update_status( $id, 'read' );
			$submission['status'] = 'read';
		}

		$fields    = isset( $submission['data']['fields'] ) && is_array( $submission['data']['fields'] ) ? $submission['data']['fields'] : [];
		$meta      = isset( $submission['data']['_meta'] ) && is_array( $submission['data']['_meta'] ) ? $submission['data']['_meta'] : [];
		$local_ts  = get_date_from_gmt( $submission['created_at'], 'Y-m-d H:i:s' );
		$source_url = isset( $meta['post_url'] ) ? (string) $meta['post_url'] : '';

		// Live title preferred over the snapshot — operator just renamed
		// the form? We show the current name. Form was deleted? We fall
		// back to whatever name we stored at submission time.
		$live_form  = ( new Indexer() )->find( $submission['form_id'] );
		$form_title = is_array( $live_form ) && ! empty( $live_form['title'] )
			? (string) $live_form['title']
			: (string) ( $meta['form_title'] ?? '' );

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
		$toggle_nonce = wp_create_nonce( 'perform_status_' . $id );
		$toggle_url   = add_query_arg(
			[
				'page'           => Menu::PARENT_SLUG,
				'perform_action' => 'mark_unread',
				'id'             => $id,
				'_wpnonce'       => $toggle_nonce,
			],
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap perform-detail">
			<?php $this->maybe_print_notice(); ?>
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Submission', 'perform-forms' ); ?> #<?php echo (int) $submission['id']; ?>
			</h1>
			<a href="<?php echo esc_url( $this->list_url() ); ?>" class="page-title-action">
				<?php esc_html_e( '← Back', 'perform-forms' ); ?>
			</a>
			<hr class="wp-header-end" />

			<div class="perform-detail__meta">
				<p>
					<strong><?php esc_html_e( 'Received:', 'perform-forms' ); ?></strong>
					<?php echo esc_html( $local_ts ); ?>
				</p>
				<?php if ( '' !== $form_title ) : ?>
					<p>
						<strong><?php esc_html_e( 'Form:', 'perform-forms' ); ?></strong>
						<?php echo esc_html( $form_title ); ?>
					</p>
				<?php endif; ?>
				<p>
					<strong><?php esc_html_e( 'Form ID:', 'perform-forms' ); ?></strong>
					<code><?php echo esc_html( $submission['form_id'] ); ?></code>
				</p>
				<?php if ( '' !== $source_url ) : ?>
					<p>
						<strong><?php esc_html_e( 'Source page:', 'perform-forms' ); ?></strong>
						<a href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $source_url ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>

			<h2><?php esc_html_e( 'Fields', 'perform-forms' ); ?></h2>
			<?php if ( empty( $fields ) ) : ?>
				<p><em><?php esc_html_e( 'This submission has no fields.', 'perform-forms' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped perform-detail__fields">
					<tbody>
						<?php foreach ( $fields as $field ) : ?>
							<?php if ( ! is_array( $field ) ) {
								continue;
							} ?>
							<tr>
								<th scope="row" style="width:200px;">
									<?php echo esc_html( (string) ( $field['label'] ?? '' ) ); ?>
									<br />
									<small><code><?php echo esc_html( (string) ( $field['name'] ?? '' ) ); ?></code></small>
								</th>
								<td><?php echo $this->format_value( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- format_value escapes its output. ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php $this->render_webhook_deliveries_section( $id ); ?>

			<p class="perform-detail__actions">
				<a href="<?php echo esc_url( $toggle_url ); ?>" class="button">
					<?php esc_html_e( 'Mark as unread', 'perform-forms' ); ?>
				</a>
				<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-link-delete" onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Delete this submission permanently?', 'perform-forms' ) ) ); ?>)">
					<?php esc_html_e( 'Delete submission', 'perform-forms' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the per-submission webhook delivery table.
	 *
	 * Shows the dispatch state of every webhook attached to this
	 * submission — useful for debugging "did the webhook fire?"
	 * questions without leaving the submission detail page. Each
	 * row carries a Resend link that re-queues a fresh delivery
	 * attempt for this same submission against the same webhook.
	 *
	 * @param int $submission_id Submission id.
	 * @return void
	 */
	private function render_webhook_deliveries_section( int $submission_id ): void {
		$deliveries = ( new DeliveryRepository() )->find_for_submission( $submission_id );
		if ( empty( $deliveries ) ) {
			return; // No webhooks configured for this form, or none triggered yet.
		}
		?>
		<h2 style="margin-top:32px;"><?php esc_html_e( 'Webhook Deliveries', 'perform-forms' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Webhook', 'perform-forms' ); ?></th>
					<th><?php esc_html_e( 'Status', 'perform-forms' ); ?></th>
					<th><?php esc_html_e( 'Code', 'perform-forms' ); ?></th>
					<th><?php esc_html_e( 'Attempt', 'perform-forms' ); ?></th>
					<th><?php esc_html_e( 'Updated', 'perform-forms' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'perform-forms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $deliveries as $delivery ) :
					$label  = (string) ( $delivery['webhook_label'] ?? '' );
					$url    = (string) ( $delivery['webhook_url'] ?? '' );
					$status = (string) ( $delivery['status'] ?? '' );
					$color  = WebhookLogListTable::status_color( $status );
					$resend_nonce = wp_create_nonce( 'perform_webhook_resend_' . (int) $delivery['id'] );
					$resend_url   = add_query_arg(
						[
							'page'              => Menu::PARENT_SLUG,
							'perform_action'    => 'webhook_resend',
							'id'                => $submission_id,
							'delivery_id'       => (int) $delivery['id'],
							'_wpnonce'          => $resend_nonce,
						],
						admin_url( 'admin.php' )
					);
					?>
					<tr>
						<td>
							<?php
							if ( '' === $label && '' === $url ) {
								echo '<em>' . esc_html__( '(deleted)', 'perform-forms' ) . '</em>';
							} else {
								echo esc_html( '' !== $label ? $label : $url );
								if ( '' !== $label && '' !== $url ) {
									echo '<br><small style="opacity:0.7">' . esc_html( $url ) . '</small>';
								}
							}
							?>
						</td>
						<td>
							<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:<?php echo esc_attr( $color ); ?>;color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;">
								<?php echo esc_html( $status ); ?>
							</span>
						</td>
						<td>
							<?php echo isset( $delivery['response_code'] ) && null !== $delivery['response_code'] ? esc_html( (string) (int) $delivery['response_code'] ) : '—'; ?>
						</td>
						<td><?php echo (int) ( $delivery['attempt'] ?? 0 ); ?></td>
						<td>
							<?php
							$ts = strtotime( ( $delivery['updated_at'] ?? '' ) . ' UTC' );
							echo $ts ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) ) : '—';
							?>
						</td>
						<td>
							<a href="<?php echo esc_url( $resend_url ); ?>" class="button button-small">
								<?php esc_html_e( 'Resend', 'perform-forms' ); ?>
							</a>
						</td>
					</tr>
					<?php if ( ! empty( $delivery['response_body'] ) ) : ?>
						<tr>
							<td colspan="6">
								<details>
									<summary style="cursor:pointer;font-size:12px;opacity:0.75;">
										<?php esc_html_e( 'Response body', 'perform-forms' ); ?>
									</summary>
									<pre style="white-space:pre-wrap;word-break:break-word;font-size:11px;max-height:150px;overflow:auto;margin:4px 0 0;background:#f6f7f7;padding:8px;border-radius:3px;"><?php echo esc_html( (string) $delivery['response_body'] ); ?></pre>
								</details>
							</td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Format a single field's value for the detail view.
	 *
	 * @param array<string, mixed> $field
	 * @return string Already-escaped HTML.
	 */
	private function format_value( array $field ): string {
		$value = $field['value'] ?? '';
		$type  = isset( $field['type'] ) ? (string) $field['type'] : 'text';

		// Multi-value: comma-separated list of escaped items.
		if ( is_array( $value ) ) {
			if ( empty( $value ) ) {
				return '<em>' . esc_html__( 'empty', 'perform-forms' ) . '</em>';
			}
			return implode( ', ', array_map( 'esc_html', array_map( 'strval', $value ) ) );
		}

		$value = (string) $value;

		if ( 'toggle' === $type ) {
			return '1' === $value
				? esc_html__( 'Yes', 'perform-forms' )
				: esc_html__( 'No', 'perform-forms' );
		}

		if ( '' === $value ) {
			return '<em>' . esc_html__( 'empty', 'perform-forms' ) . '</em>';
		}

		if ( 'email' === $type && is_email( $value ) ) {
			return sprintf(
				'<a href="mailto:%1$s">%1$s</a>',
				esc_attr( $value )
			);
		}

		if ( 'textarea' === $type ) {
			return nl2br( esc_html( $value ) );
		}

		return esc_html( $value );
	}

	/**
	 * Run a bulk action against the selected submission IDs.
	 *
	 * @param string $action One of 'mark_read', 'mark_unread', 'delete'.
	 * @return void
	 */
	private function handle_bulk( string $action ): void {
		check_admin_referer( 'bulk-submissions' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$ids = isset( $_REQUEST['ids'] ) && is_array( $_REQUEST['ids'] ) ? array_map( 'intval', wp_unslash( $_REQUEST['ids'] ) ) : [];
		$ids = array_values( array_filter( $ids ) );

		if ( empty( $ids ) ) {
			$this->redirect_with_notice( '' );
		}

		$notice = '';
		switch ( $action ) {
			case 'mark_read':
				$count  = $this->repository->update_status_many( $ids, 'read' );
				$notice = sprintf(
					/* translators: %d: number of submissions affected */
					_n( '%d submission marked as read.', '%d submissions marked as read.', $count, 'perform-forms' ),
					$count
				);
				break;
			case 'mark_unread':
				$count  = $this->repository->update_status_many( $ids, 'unread' );
				$notice = sprintf(
					/* translators: %d: number of submissions affected */
					_n( '%d submission marked as unread.', '%d submissions marked as unread.', $count, 'perform-forms' ),
					$count
				);
				break;
			case 'delete':
				$count  = $this->repository->delete_many( $ids );
				$notice = sprintf(
					/* translators: %d: number of submissions affected */
					_n( '%d submission deleted.', '%d submissions deleted.', $count, 'perform-forms' ),
					$count
				);
				break;
		}

		$this->redirect_with_notice( $notice );
	}

	/**
	 * Handle a single-row action (delete, mark_unread, export).
	 *
	 * @param string $action The `perform_action` query arg.
	 * @return void
	 */
	private function handle_single_action( string $action ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified per branch.
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		switch ( $action ) {
			case 'export':
				check_admin_referer( 'perform_export' );
				$filters = $this->read_filters_from_request();
				( new Exporter( $this->repository ) )->stream( $filters );
				// Exporter calls exit(). Falling through to redirect on failure.
				$this->redirect_with_notice( __( 'CSV export failed.', 'perform-forms' ) );
				break;
			case 'delete':
				if ( 0 === $id ) {
					return;
				}
				check_admin_referer( 'perform_delete_' . $id );
				$this->repository->delete( $id );
				$this->redirect_with_notice( __( 'Submission deleted.', 'perform-forms' ) );
				break;
			case 'mark_unread':
				if ( 0 === $id ) {
					return;
				}
				check_admin_referer( 'perform_status_' . $id );
				$this->repository->update_status( $id, 'unread' );
				$this->redirect_with_notice( __( 'Submission marked as unread.', 'perform-forms' ) );
				break;
			case 'webhook_resend':
				$delivery_id = isset( $_GET['delivery_id'] ) ? (int) $_GET['delivery_id'] : 0;
				if ( 0 === $id || 0 === $delivery_id ) {
					return;
				}
				check_admin_referer( 'perform_webhook_resend_' . $delivery_id );
				$this->handle_webhook_resend( $id, $delivery_id );
				break;
		}
	}

	/**
	 * Re-queue a webhook delivery against the original submission.
	 *
	 * Looks up the original delivery row to recover the webhook_id +
	 * submission_id pairing, then enqueues a fresh row through the
	 * regular dispatch path. The original row stays in the log as
	 * historical record — Resend never mutates past attempts.
	 *
	 * @param int $submission_id          Submission this resend belongs to.
	 * @param int $original_delivery_id   Delivery row id the operator clicked.
	 * @return void
	 */
	private function handle_webhook_resend( int $submission_id, int $original_delivery_id ): void {
		$delivery_repo = new DeliveryRepository();
		$original      = $delivery_repo->find( $original_delivery_id );

		if ( null === $original || (int) $original['submission_id'] !== $submission_id ) {
			$this->redirect_to_detail_with_notice( $submission_id, __( 'Could not resend — delivery not found.', 'perform-forms' ) );
			return;
		}

		$new_id = $delivery_repo->enqueue( (int) $original['webhook_id'], $submission_id );
		if ( null === $new_id ) {
			$this->redirect_to_detail_with_notice( $submission_id, __( 'Could not resend — queue insert failed.', 'perform-forms' ) );
			return;
		}

		// Trigger a single-event cron run so the operator doesn't have
		// to wait up to a minute for the resend to actually fire.
		wp_schedule_single_event( time() + 1, WebhookDispatcher::CRON_HOOK );

		$this->redirect_to_detail_with_notice( $submission_id, __( 'Webhook delivery re-queued.', 'perform-forms' ) );
	}

	/**
	 * Redirect back to a specific submission's detail page, carrying
	 * a transient notice.
	 *
	 * @param int    $id     Submission id.
	 * @param string $notice One-line message.
	 * @return never
	 */
	private function redirect_to_detail_with_notice( int $id, string $notice ): void {
		$url = $this->detail_url( $id );
		if ( '' !== $notice ) {
			$url = add_query_arg( 'perform_notice', rawurlencode( $notice ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Print a transient admin notice if the URL carries one.
	 *
	 * @return void
	 */
	private function maybe_print_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash message.
		$notice = isset( $_GET['perform_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['perform_notice'] ) ) : '';
		if ( '' === $notice ) {
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $notice )
		);
	}

	/**
	 * Redirect back to the list view, optionally with a one-shot notice.
	 *
	 * @param string $notice
	 * @return never
	 */
	private function redirect_with_notice( string $notice ): void {
		$url = $this->list_url();
		if ( '' !== $notice ) {
			$url = add_query_arg( 'perform_notice', rawurlencode( $notice ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * URL of the submissions list view.
	 *
	 * @return string
	 */
	private function list_url(): string {
		return add_query_arg( 'page', Menu::PARENT_SLUG, admin_url( 'admin.php' ) );
	}

	/**
	 * Pull filter args off the request for the exporter.
	 *
	 * @return array<string, string>
	 */
	private function read_filters_from_request(): array {
		$filters = [];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter inputs (export nonce checked separately).
		foreach ( [ 'form_id', 'status', 'date_from', 'date_to', 'search' ] as $key ) {
			if ( ! empty( $_GET[ $key ] ) ) {
				$filters[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}
		// phpcs:enable
		return $filters;
	}

	/**
	 * Emit the small bit of CSS the admin pages need.
	 *
	 * @return void
	 */
	private function print_inline_styles(): void {
		?>
		<style>
			.perform-unread-dot { color: #2271b1; font-size: 14px; line-height: 1; margin-right: 4px; }
			.perform-status { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 12px; line-height: 1.4; }
			.perform-status--unread { background: #e7f5ff; color: #1d4ed8; font-weight: 600; }
			.perform-status--read { background: #f1f1f1; color: #555; }
			.perform-preview { color: #444; }
			.perform-detail__meta p { margin: 4px 0; }
			.perform-detail__fields th { vertical-align: top; }
			.perform-detail__actions { margin-top: 24px; display: flex; gap: 12px; }
		</style>
		<?php
	}
}
