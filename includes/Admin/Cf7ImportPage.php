<?php
/**
 * Flinkform → "Move from Contact Form 7" (1.15.0).
 *
 * Preview first (traffic light, fields with where their label came from,
 * affected pages), then import form by form over AJAX so a site with many
 * forms never hits a PHP timeout, then a report with links to every page
 * to test. Undo per form. All of it for manage_options only, with nonces.
 *
 * The page and its menu entry only exist while there are CF7 forms in the
 * database. The notice is shown on the dashboard, the plugins screen and
 * Flinkform's own pages, never elsewhere, and can be dismissed for good.
 *
 * @package Flinkform
 * @since 1.15.0
 */

declare( strict_types = 1 );

namespace Flinkform\Admin;

defined( 'ABSPATH' ) || exit;

use Flinkform\Import\Cf7\Importer;

/**
 * Admin page, AJAX endpoints and notice for the CF7 import.
 */
final class Cf7ImportPage {

	public const SLUG        = 'flinkform-cf7-import';
	private const NONCE      = 'flinkform_cf7_import';
	private const CAPABILITY = 'manage_options';
	private const DISMISSED  = 'flinkform_cf7_notice_dismissed';

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ], 15 );
		add_action( 'admin_notices', [ $this, 'notice' ] );
		add_action( 'wp_ajax_flinkform_cf7_import', [ $this, 'ajax_import' ] );
		add_action( 'wp_ajax_flinkform_cf7_undo', [ $this, 'ajax_undo' ] );
		add_action( 'wp_ajax_flinkform_cf7_dismiss', [ $this, 'ajax_dismiss' ] );
	}

	/**
	 * @return void
	 */
	public function add_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) || 0 === Importer::count_forms() ) {
			return;
		}
		$hook = add_submenu_page(
			Menu::PARENT_SLUG,
			__( 'Move from Contact Form 7', 'flinkform' ),
			__( 'Import from CF7', 'flinkform' ),
			self::CAPABILITY,
			self::SLUG,
			[ $this, 'render' ]
		);
		if ( $hook ) {
			add_action( "admin_print_scripts-{$hook}", [ $this, 'assets' ] );
		}
	}

	/**
	 * Script + styles, only on this page.
	 *
	 * @return void
	 */
	public function assets(): void {
		wp_enqueue_script( 'flinkform-cf7-import', FLINKFORM_PLUGIN_URL . 'assets/cf7-import.js', [], FLINKFORM_VERSION, true );
		wp_localize_script(
			'flinkform-cf7-import',
			'flinkformCf7',
			[
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( self::NONCE ),
				'i18n'  => [
					'importing'   => __( 'Importing…', 'flinkform' ),
					'undoing'     => __( 'Undoing…', 'flinkform' ),
					'imported'    => __( 'Imported', 'flinkform' ),
					'undone'      => __( 'Undone', 'flinkform' ),
					'failed'      => __( 'Failed', 'flinkform' ),
					'confirmUndo' => __( 'Undo this import? The pages get their Contact Form 7 shortcode back and the Flinkform pattern goes to the trash. Submissions already received stay in Flinkform.', 'flinkform' ),
					'editPattern' => __( 'Edit form', 'flinkform' ),
					'test'        => __( 'Test these pages:', 'flinkform' ),
					'done'        => __( 'Done. Test every page listed, then deactivate Contact Form 7 when you are happy.', 'flinkform' ),
				],
			]
		);
		wp_register_style( 'flinkform-cf7-import', false, [], FLINKFORM_VERSION );
		wp_enqueue_style( 'flinkform-cf7-import' );
		wp_add_inline_style( 'flinkform-cf7-import', self::inline_css() );
	}

	/**
	 * The page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$importer = new Importer();
		$forms    = $importer->forms();
		$lights   = [
			'green'  => __( 'Complete', 'flinkform' ),
			'yellow' => __( 'Please check', 'flinkform' ),
			'red'    => __( 'Partly not transferable', 'flinkform' ),
		];
		$sources  = [
			'label'       => __( 'from its label', 'flinkform' ),
			'text'        => __( 'from the text before it', 'flinkform' ),
			'placeholder' => __( 'from the placeholder, please check', 'flinkform' ),
			'name'        => __( 'from the field name, please check', 'flinkform' ),
			'option'      => __( 'the checkbox text', 'flinkform' ),
			'consent'     => __( 'consent field', 'flinkform' ),
			'hidden'      => __( 'hidden field, not shown', 'flinkform' ),
		];
		$cf7_active = defined( 'WPCF7_VERSION' );
		?>
		<div class="wrap flinkform-cf7">
			<h1><?php esc_html_e( 'Move from Contact Form 7', 'flinkform' ); ?></h1>
			<p class="flinkform-cf7__lead">
				<?php esc_html_e( 'Each Contact Form 7 form becomes a Flinkform form, stored as a synced pattern, and every page that used it is switched over. Contact Form 7 itself is not changed or deactivated, and every import can be undone.', 'flinkform' ); ?>
			</p>
			<?php if ( ! $cf7_active ) : ?>
				<p class="description"><?php esc_html_e( 'Contact Form 7 is not active. The forms are read from the database, that works just the same.', 'flinkform' ); ?></p>
			<?php endif; ?>
			<p>
				<button type="button" class="button button-primary" data-flinkform-cf7-all><?php esc_html_e( 'Import all forms that are not imported yet', 'flinkform' ); ?></button>
			</p>

			<?php foreach ( $forms as $form ) : ?>
				<?php $p = $importer->preview( $form ); ?>
				<div class="flinkform-cf7__form flinkform-cf7__form--<?php echo esc_attr( $p['status'] ); ?>" data-flinkform-cf7="<?php echo esc_attr( (string) $p['id'] ); ?>" data-imported="<?php echo $p['imported'] ? '1' : '0'; ?>">
					<h2>
						<span class="flinkform-cf7__light" aria-hidden="true"></span>
						<?php echo esc_html( '' !== $p['title'] ? $p['title'] : '#' . $p['id'] ); ?>
						<span class="flinkform-cf7__status"><?php echo esc_html( $lights[ $p['status'] ] ); ?></span>
					</h2>

					<?php if ( $p['imported'] ) : ?>
						<p class="flinkform-cf7__done">
							<?php
							printf(
								/* translators: 1: date, 2: number of pages, 3: number of submissions. */
								esc_html__( 'Imported on %1$s, %2$d page(s) switched, %3$d submission(s) received since.', 'flinkform' ),
								esc_html( $p['imported']['time'] ),
								(int) $p['imported']['pages'],
								(int) $p['imported']['submissions']
							);
							?>
							<a href="<?php echo esc_url( $p['imported']['edit'] ); ?>"><?php esc_html_e( 'Edit form', 'flinkform' ); ?></a>
						</p>
					<?php endif; ?>

					<details>
						<summary><?php esc_html_e( 'Fields and notes', 'flinkform' ); ?></summary>
						<ul class="flinkform-cf7__fields">
							<?php foreach ( $p['fields'] as $f ) : ?>
								<li<?php echo in_array( $f['source'], [ 'placeholder', 'name' ], true ) ? ' class="is-guess"' : ''; ?>>
									<strong><?php echo esc_html( mb_strimwidth( $f['label'], 0, 70, '…' ) ); ?></strong>
									<code><?php echo esc_html( $f['name'] ); ?></code>
									<span><?php echo esc_html( $sources[ $f['source'] ] ?? '' ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
						<?php if ( ! empty( $p['notes'] ) ) : ?>
							<ul class="flinkform-cf7__notes">
								<?php foreach ( $p['notes'] as $n ) : ?>
									<li class="is-<?php echo esc_attr( $n['level'] ); ?>">
										<?php echo esc_html( $n['text'] ); ?>
										<?php if ( ! empty( $n['pro'] ) && Upsell::enabled() ) : ?>
											<a href="<?php echo esc_url( Upsell::url( 'cf7-import' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See Flinkform Pro', 'flinkform' ); ?></a>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</details>

					<p class="flinkform-cf7__pages">
						<?php if ( empty( $p['pages'] ) ) : ?>
							<?php esc_html_e( 'Not embedded on any page. The form is imported as a pattern you can insert anywhere.', 'flinkform' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Used on:', 'flinkform' ); ?>
							<?php foreach ( $p['pages'] as $i => $page ) : ?>
								<?php echo $i > 0 ? ', ' : ''; ?><a href="<?php echo esc_url( $page['view'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $page['title'] ); ?></a>
							<?php endforeach; ?>
						<?php endif; ?>
					</p>

					<p class="flinkform-cf7__actions">
						<button type="button" class="button button-primary" data-action="import"<?php echo $p['imported'] ? ' hidden' : ''; ?>><?php esc_html_e( 'Import', 'flinkform' ); ?></button>
						<button type="button" class="button" data-action="undo"<?php echo $p['imported'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Undo import', 'flinkform' ); ?></button>
						<span class="flinkform-cf7__result" role="status" aria-live="polite"></span>
					</p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	public function ajax_import(): void {
		$this->guard();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$result = ( new Importer() )->import( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
		is_wp_error( $result ) ? wp_send_json_error( [ 'message' => $result->get_error_message() ] ) : wp_send_json_success( $result );
	}

	/**
	 * @return void
	 */
	public function ajax_undo(): void {
		$this->guard();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		$result = ( new Importer() )->undo( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
		is_wp_error( $result ) ? wp_send_json_error( [ 'message' => $result->get_error_message() ] ) : wp_send_json_success( $result );
	}

	/**
	 * @return void
	 */
	public function ajax_dismiss(): void {
		$this->guard();
		update_user_meta( get_current_user_id(), self::DISMISSED, 1 );
		wp_send_json_success();
	}

	/**
	 * Capability + nonce, or stop.
	 *
	 * @return void
	 */
	private function guard(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to do this.', 'flinkform' ) ], 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
	}

	/**
	 * "N Contact Form 7 forms found. Move them to Flinkform?"
	 *
	 * @return void
	 */
	public function notice(): void {
		if ( ! current_user_can( self::CAPABILITY ) || get_user_meta( get_current_user_id(), self::DISMISSED, true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		if ( ! in_array( $id, [ 'dashboard', 'plugins' ], true ) && false === strpos( $id, 'flinkform' ) ) {
			return;
		}
		if ( false !== strpos( $id, self::SLUG ) ) {
			return;
		}
		$total    = Importer::count_forms();
		$imported = count( ( new Importer() )->log() );
		$open     = $total - $imported;
		if ( $open < 1 ) {
			return;
		}
		$nonce = wp_create_nonce( self::NONCE );
		printf(
			'<div class="notice notice-info is-dismissible" data-flinkform-cf7-notice data-nonce="%1$s"><p>%2$s <a href="%3$s">%4$s</a></p></div>',
			esc_attr( $nonce ),
			esc_html(
				sprintf(
					/* translators: %d: number of Contact Form 7 forms. */
					_n( '%d Contact Form 7 form found.', '%d Contact Form 7 forms found.', $open, 'flinkform' ),
					$open
				)
			),
			esc_url( add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) ) ),
			esc_html__( 'Move them to Flinkform', 'flinkform' )
		);
		// Remember the dismissal: one tiny inline listener on a footer
		// handle (the header scripts are already out at this point).
		wp_register_script( 'flinkform-cf7-notice', false, [], FLINKFORM_VERSION, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
		wp_enqueue_script( 'flinkform-cf7-notice' );
		wp_add_inline_script(
			'flinkform-cf7-notice',
			"document.addEventListener('click',function(e){var n=e.target.closest('[data-flinkform-cf7-notice] .notice-dismiss');if(!n)return;var d=n.closest('[data-flinkform-cf7-notice]');var b=new FormData();b.append('action','flinkform_cf7_dismiss');b.append('nonce',d.getAttribute('data-nonce'));fetch(ajaxurl,{method:'POST',body:b,credentials:'same-origin'});});"
		);
	}

	/**
	 * @return string
	 */
	private static function inline_css(): string {
		return <<<'CSS'
.flinkform-cf7 { max-width: 900px; }
.flinkform-cf7__lead { font-size: 14px; }
.flinkform-cf7__form { background: #fff; border: 1px solid #dcdcde; border-left-width: 4px; padding: 4px 16px 8px; margin: 16px 0; }
.flinkform-cf7__form--green { border-left-color: #00a32a; }
.flinkform-cf7__form--yellow { border-left-color: #dba617; }
.flinkform-cf7__form--red { border-left-color: #d63638; }
.flinkform-cf7__form h2 { display: flex; align-items: center; gap: 8px; font-size: 15px; }
.flinkform-cf7__status { font-weight: 400; color: #50575e; font-size: 13px; }
.flinkform-cf7__fields, .flinkform-cf7__notes { margin: 8px 0 8px 18px; list-style: disc; }
.flinkform-cf7__fields code { margin: 0 6px; }
.flinkform-cf7__fields span { color: #50575e; }
.flinkform-cf7__fields .is-guess span, .flinkform-cf7__notes .is-check { color: #996800; }
.flinkform-cf7__notes .is-lost { color: #b32d2e; }
.flinkform-cf7__actions { display: flex; gap: 8px; align-items: center; }
.flinkform-cf7__result { color: #50575e; }
.flinkform-cf7__done { color: #00a32a; }
CSS;
	}
}
