<?php
/**
 * Forms admin page controller.
 *
 * Thin counterpart to SubmissionsPage — renders the Forms list and
 * handles the one mutating action it offers: a manual "Refresh index"
 * button that drops the cached scan in case it goes stale ahead of
 * the auto-invalidation hooks (rare, but reassuring to have).
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace PerForm\Admin;

use PerForm\Forms\Indexer;

defined( 'ABSPATH' ) || exit;

/**
 * Controller for the PerForm → Forms page.
 */
final class FormsPage {

	public const SLUG = 'perform-forms';

	private Indexer $indexer;

	public function __construct( ?Indexer $indexer = null ) {
		$this->indexer = $indexer ?? new Indexer();
	}

	/**
	 * Pre-headers action dispatcher (called on admin_init).
	 *
	 * @return void
	 */
	public function dispatch(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified inside.
		$action = isset( $_GET['perffo_action'] ) ? sanitize_key( wp_unslash( $_GET['perffo_action'] ) ) : '';
		if ( 'refresh' !== $action ) {
			return;
		}

		check_admin_referer( 'perffo_forms_refresh' );
		$this->indexer->invalidate();
		wp_safe_redirect( add_query_arg( 'perffo_notice', rawurlencode( __( 'Forms index refreshed.', 'perform-forms' ) ), $this->list_url() ) );
		exit;
	}

	/**
	 * Render the Forms page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view PerForm forms.', 'perform-forms' ) );
		}

		$table = new FormsListTable( $this->indexer );
		$table->prepare_items();

		$refresh_url = wp_nonce_url(
			add_query_arg(
				[
					'page'           => self::SLUG,
					'perffo_action' => 'refresh',
				],
				admin_url( 'admin.php' )
			),
			'perffo_forms_refresh'
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Forms', 'perform-forms' ); ?></h1>
			<a href="<?php echo esc_url( $refresh_url ); ?>" class="page-title-action">
				<?php esc_html_e( 'Refresh index', 'perform-forms' ); ?>
			</a>
			<hr class="wp-header-end" />

			<?php $this->maybe_print_notice(); ?>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
				<?php
				$table->search_box( __( 'Search forms', 'perform-forms' ), 'perform-forms-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Print a flash notice if one is queued in the URL.
	 *
	 * @return void
	 */
	private function maybe_print_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only.
		$notice = isset( $_GET['perffo_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['perffo_notice'] ) ) : '';
		if ( '' === $notice ) {
			return;
		}
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $notice ) );
	}

	/**
	 * URL of the list view.
	 *
	 * @return string
	 */
	private function list_url(): string {
		return add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) );
	}

	/**
	 * CSS for the Forms admin page (tag pills in the title column).
	 *
	 * Called from Menu::enqueue_forms_styles() via wp_add_inline_style().
	 *
	 * @return string
	 */
	public static function inline_css(): string {
		return <<<'CSS'
.perform-tag {
	display: inline-block;
	margin-left: 6px;
	padding: 1px 8px;
	border-radius: 9999px;
	font-size: 11px;
	background: #f1f1f1;
	color: #555;
	font-weight: 400;
	text-transform: lowercase;
}
.perform-tag--warning {
	background: #fff4e5;
	color: #b85c00;
}
CSS;
	}
}
