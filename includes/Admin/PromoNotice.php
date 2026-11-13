<?php
/**
 * Time-boxed Flinkform Pro offer notice (Black Week 2026, 1.15.1).
 *
 * Same rules as Upsell (WordPress.org guideline 11, no tracking):
 *   - only between START and END, hard-coded, no remote call: after END
 *     this class does nothing at all;
 *   - only for admins, only on the dashboard, the plugins screen and
 *     Flinkform's own screens, never on the post editor;
 *   - dismissible for good (user meta), with and without JavaScript;
 *   - gone when Pro is active or `flinkform_show_pro_upsell` is false.
 *
 * @package Flinkform
 * @since 1.15.1
 */

declare( strict_types = 1 );

namespace Flinkform\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin notice for the Black Week offer.
 */
final class PromoNotice {

	/** 13 Nov 2026, 00:00 Europe/Berlin. */
	public const START = 1794524400;

	/** 1 Dec 2026, 00:00 Europe/Berlin (offer ends 30 Nov, 23:59). */
	public const END = 1796079600;

	/** User meta key; a new campaign gets a new key. */
	public const META = 'flinkform_dismissed_blackweek_2026';

	public const ACTION = 'flinkform_dismiss_promo';

	/**
	 * Is the offer running right now?
	 *
	 * @param int|null $now Unix time, for tests.
	 * @return bool
	 */
	public static function running( ?int $now = null ): bool {
		$now = $now ?? time();
		return $now >= self::START && $now < self::END;
	}

	/**
	 * Hook in, but only while the offer runs.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! self::running() ) {
			return;
		}
		add_action( 'admin_notices', [ $this, 'render' ] );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'dismiss' ] );
		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'dismiss' ] );
	}

	/**
	 * Show the notice here, to this user?
	 *
	 * @return bool
	 */
	private function should_show(): bool {
		if ( ! Upsell::enabled() || ! current_user_can( Menu::CAPABILITY ) ) {
			return false;
		}
		if ( get_user_meta( get_current_user_id(), self::META, true ) ) {
			return false;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}
		return in_array( $screen->id, [ 'dashboard', 'plugins' ], true )
			|| false !== strpos( (string) $screen->id, 'flinkform' );
	}

	/**
	 * Link to the offer on flinkform.de in the site's language.
	 *
	 * @return string
	 */
	public static function url(): string {
		$base = 0 === strpos( determine_locale(), 'de' ) ? 'https://flinkform.de/pro/' : 'https://flinkform.de/en/pro/';
		return add_query_arg(
			[
				'utm_source'   => 'flinkform-free',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'blackweek',
				'utm_content'  => 'notice',
			],
			$base
		);
	}

	/**
	 * The notice.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! $this->should_show() ) {
			return;
		}
		$dismiss = wp_nonce_url(
			add_query_arg(
				[
					'action'   => self::ACTION,
					'redirect' => rawurlencode( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by wp_safe_redirect().
				],
				admin_url( 'admin-post.php' )
			),
			self::ACTION
		);
		?>
		<div class="notice notice-info is-dismissible flinkform-promo" data-flinkform-promo="<?php echo esc_attr( wp_create_nonce( self::ACTION ) ); ?>">
			<p><strong><?php esc_html_e( 'Black Week: Flinkform Pro at 50% off the first year', 'flinkform' ); ?></strong></p>
			<p><?php esc_html_e( 'Payments, file uploads, webhooks, SMTP and newsletter sign-ups for your forms. Until 30 November every annual plan costs half in the first year, for example 29.50 € instead of 59 € for one website. 14-day money-back guarantee.', 'flinkform' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::url() ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'See the offer', 'flinkform' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens flinkform.de in a new tab)', 'flinkform' ); ?></span>
				</a>
				<a class="flinkform-promo__dismiss" href="<?php echo esc_url( $dismiss ); ?>" style="margin-left:12px"><?php esc_html_e( 'Don\'t show again', 'flinkform' ); ?></a>
			</p>
		</div>
		<script>
		( function () {
			var box = document.querySelector( '.flinkform-promo' );
			if ( ! box ) { return; }
			box.addEventListener( 'click', function ( e ) {
				if ( ! e.target.closest( '.notice-dismiss' ) ) { return; }
				var body = new FormData();
				body.append( 'action', <?php echo wp_json_encode( self::ACTION ); ?> );
				body.append( '_wpnonce', box.getAttribute( 'data-flinkform-promo' ) );
				fetch( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', body: body, credentials: 'same-origin' } );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Remember the dismissal (AJAX from the X, or the no-JS link).
	 *
	 * @return void
	 */
	public function dismiss(): void {
		check_admin_referer( self::ACTION );
		if ( current_user_can( Menu::CAPABILITY ) ) {
			update_user_meta( get_current_user_id(), self::META, 1 );
		}
		if ( wp_doing_ajax() ) {
			wp_send_json_success();
		}
		$back = isset( $_GET['redirect'] ) ? (string) wp_unslash( $_GET['redirect'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by wp_safe_redirect().
		wp_safe_redirect( '' !== $back ? $back : admin_url() );
		exit;
	}
}
