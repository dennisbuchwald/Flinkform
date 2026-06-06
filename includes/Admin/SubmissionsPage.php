<?php
/**
 * Submissions admin page controller.
 *
 * Handles three responsibilities:
 *
 *  1. dispatch() — runs on admin_init for any incoming bulk action or
 *     row action (delete, mark read/unread). Mutating handlers redirect
 *     back to a clean URL after running, so the user never has a
 *     destructive action sitting in the address bar. (CSV export is owned
 *     by PerForm Pro, which registers its own handler via the bridge.)
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
use PerForm\Submissions\Repository;

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
	 * nonces in the URL (`perffo_action=...`). Bulk actions arrive via
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
		$single_action = isset( $_GET['perffo_action'] ) ? sanitize_key( wp_unslash( $_GET['perffo_action'] ) ) : '';
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
	 * Render the page (list or detail).
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view PerForm submissions.', 'perform-forms' ) );
		}

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
				$table->search_box( __( 'Search submissions', 'perform-forms' ), 'perffo-submissions-search' );
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

		$delete_nonce = wp_create_nonce( 'perffo_delete_' . $id );
		$delete_url   = add_query_arg(
			[
				'page'           => Menu::PARENT_SLUG,
				'perffo_action' => 'delete',
				'id'             => $id,
				'_wpnonce'       => $delete_nonce,
			],
			admin_url( 'admin.php' )
		);
		$toggle_nonce = wp_create_nonce( 'perffo_status_' . $id );
		$toggle_url   = add_query_arg(
			[
				'page'           => Menu::PARENT_SLUG,
				'perffo_action' => 'mark_unread',
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

			<?php
			/**
			 * Fires after the submission's field table, inside the detail view.
			 *
			 * PerForm Pro hooks this to render the "Webhook Deliveries" section
			 * for the submission. With no add-on, nothing extra renders.
			 *
			 * @since 0.2.5
			 *
			 * @param int $id Submission id.
			 */
			do_action( 'perffo_submission_detail_after', $id );
			?>

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
	 * Handle a single-row action (delete, mark_unread).
	 *
	 * CSV export is owned by PerForm Pro — it registers its own handler and
	 * filter-bar button via the bridge layer, so the free core no longer
	 * routes an 'export' action here.
	 *
	 * @param string $action The `perffo_action` query arg.
	 * @return void
	 */
	private function handle_single_action( string $action ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified per branch.
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		switch ( $action ) {
			case 'delete':
				if ( 0 === $id ) {
					return;
				}
				check_admin_referer( 'perffo_delete_' . $id );
				$this->repository->delete( $id );
				$this->redirect_with_notice( __( 'Submission deleted.', 'perform-forms' ) );
				break;
			case 'mark_unread':
				if ( 0 === $id ) {
					return;
				}
				check_admin_referer( 'perffo_status_' . $id );
				$this->repository->update_status( $id, 'unread' );
				$this->redirect_with_notice( __( 'Submission marked as unread.', 'perform-forms' ) );
				break;
		}
	}

	/**
	 * Print a transient admin notice if the URL carries one.
	 *
	 * @return void
	 */
	private function maybe_print_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash message.
		$notice = isset( $_GET['perffo_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['perffo_notice'] ) ) : '';
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
			$url = add_query_arg( 'perffo_notice', rawurlencode( $notice ), $url );
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
	 * CSS for the Submissions admin page.
	 *
	 * Called from Menu::enqueue_submissions_styles() via wp_add_inline_style().
	 *
	 * @return string
	 */
	public static function inline_css(): string {
		return <<<'CSS'
.perform-unread-dot { color: #2271b1; font-size: 14px; line-height: 1; margin-right: 4px; }
.perform-status { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 12px; line-height: 1.4; }
.perform-status--unread { background: #e7f5ff; color: #1d4ed8; font-weight: 600; }
.perform-status--read { background: #f1f1f1; color: #555; }
.perform-preview { color: #444; }
.perform-detail__meta p { margin: 4px 0; }
.perform-detail__fields th { vertical-align: top; }
.perform-detail__actions { margin-top: 24px; display: flex; gap: 12px; }
CSS;
	}
}
