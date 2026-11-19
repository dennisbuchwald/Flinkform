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
	 * Whole days left until END, counted up (the last day says "1 day").
	 *
	 * @param int|null $now Unix time, for tests.
	 * @return int
	 */
	public static function days_left( ?int $now = null ): int {
		$now = $now ?? time();
		return max( 1, (int) ceil( ( self::END - $now ) / DAY_IN_SECONDS ) );
	}

	/**
	 * All submissions on this site, cached for an hour. Local data only,
	 * nothing leaves the site.
	 *
	 * @return int
	 */
	private static function submission_count(): int {
		$count = get_transient( 'flinkform_promo_total' );
		if ( false === $count ) {
			$count = ( new \Flinkform\Submissions\Repository() )->count();
			set_transient( 'flinkform_promo_total', $count, HOUR_IN_SECONDS );
		}
		return (int) $count;
	}

	/**
	 * Translate, falling back to the .mo shipped in languages/.
	 *
	 * WordPress prefers the language pack from translate.wordpress.org over
	 * the bundled file. A site that has not fetched the pack for this
	 * release yet shows a new string in English, although the plugin ships
	 * the German one. A campaign that runs for two weeks cannot wait for
	 * that, so strings the loaded translation does not know are looked up
	 * in the bundled file.
	 *
	 * @param string      $single Text (or singular).
	 * @param string|null $plural Plural, for _n().
	 * @param int         $n      Number, for _n().
	 * @return string
	 */
	public static function t( string $single, ?string $plural = null, int $n = 1 ): string {
		// phpcs:disable WordPress.WP.I18n -- dynamic strings, all literals are listed in the POT via the calls in render().
		$out = null === $plural ? __( $single, 'flinkform' ) : _n( $single, $plural, $n, 'flinkform' );
		// phpcs:enable
		$original = null === $plural || 1 === $n ? $single : $plural;
		if ( $out !== $original && $out !== $single ) {
			return $out;
		}
		$mo = self::bundled_mo();
		if ( ! $mo ) {
			return $out;
		}
		$fallback = null === $plural ? $mo->translate( $single ) : $mo->translate_plural( $single, $plural, $n );
		return ( '' !== (string) $fallback ) ? (string) $fallback : $out;
	}

	/**
	 * The bundled .mo for the current locale, or null.
	 *
	 * @return \MO|null
	 */
	private static function bundled_mo() {
		static $cache = [];
		$locale = determine_locale();
		if ( array_key_exists( $locale, $cache ) ) {
			return $cache[ $locale ];
		}
		$cache[ $locale ] = null;
		$file             = FLINKFORM_PLUGIN_DIR . 'languages/flinkform-' . $locale . '.mo';
		if ( 'en_US' !== $locale && is_readable( $file ) && class_exists( '\MO' ) ) {
			$mo = new \MO();
			if ( $mo->import_from_file( $file ) ) {
				$cache[ $locale ] = $mo;
			}
		}
		return $cache[ $locale ];
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
		$days  = self::days_left();
		$total = self::submission_count();

		// The literal calls below are never executed; they put every string
		// into the POT for xgettext. self::t() does the real lookup.
		if ( false ) { // phpcs:ignore Generic.CodeAnalysis.UnconditionalIfStatement.Found
			__( 'Black Week: Flinkform Pro for 29.50 € instead of 59 € in the first year', 'flinkform' );
			/* translators: %s: number of submissions on this site. */
			_n( 'Your forms have already collected %s submission on this site. Pro gets more out of every one:', 'Your forms have already collected %s submissions on this site. Pro gets more out of every one:', 2, 'flinkform' );
			__( 'Pro gets more out of every form:', 'flinkform' );
			__( 'Every email arrives: sent through your own mail server (SMTP) instead of landing in spam.', 'flinkform' );
			__( 'Get paid in the form: card, SEPA, Apple Pay and Google Pay through Stripe.', 'flinkform' );
			__( 'Prices calculate themselves: calculation fields for quotes, bookings and order forms.', 'flinkform' );
			__( 'No more copying by hand: file uploads, CSV export, webhooks to Zapier, Make or n8n, sign-ups straight into Brevo, Mailchimp or CleverReach.', 'flinkform' );
			__( 'Need none of it? Your purchase still helps: Flinkform comes from a small agency in Heilbronn, and every licence keeps the free plugin free and in active development. Thank you!', 'flinkform' );
			/* translators: %s: number of days until the offer ends. */
			_n( 'Only %s day left, for every annual plan. Not convinced? 14 days money back, no questions asked.', 'Only %s days left, for every annual plan. Not convinced? 14 days money back, no questions asked.', 2, 'flinkform' );
			__( 'Get Pro for 29.50 €', 'flinkform' );
		}

		$intro = $total >= 10
			? sprintf( self::t( 'Your forms have already collected %s submission on this site. Pro gets more out of every one:', 'Your forms have already collected %s submissions on this site. Pro gets more out of every one:', $total ), number_format_i18n( $total ) )
			: self::t( 'Pro gets more out of every form:' );
		$benefits = [
			self::t( 'Every email arrives: sent through your own mail server (SMTP) instead of landing in spam.' ),
			self::t( 'Get paid in the form: card, SEPA, Apple Pay and Google Pay through Stripe.' ),
			self::t( 'Prices calculate themselves: calculation fields for quotes, bookings and order forms.' ),
			self::t( 'No more copying by hand: file uploads, CSV export, webhooks to Zapier, Make or n8n, sign-ups straight into Brevo, Mailchimp or CleverReach.' ),
		];
		?>
		<div class="notice notice-info is-dismissible flinkform-promo" data-flinkform-promo="<?php echo esc_attr( wp_create_nonce( self::ACTION ) ); ?>">
			<p><strong><?php echo esc_html( self::t( 'Black Week: Flinkform Pro for 29.50 € instead of 59 € in the first year' ) ); ?></strong></p>
			<p><?php echo esc_html( $intro ); ?></p>
			<ul style="list-style:disc;margin:0 0 0 1.5em">
				<?php foreach ( $benefits as $benefit ) : ?>
					<?php [ $lead, $rest ] = array_pad( explode( ':', $benefit, 2 ), 2, '' ); ?>
					<li><?php if ( '' !== $rest ) : ?><strong><?php echo esc_html( $lead ); ?>:</strong><?php echo esc_html( $rest ); ?><?php else : ?><?php echo esc_html( $benefit ); ?><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
			<p><?php echo esc_html( self::t( 'Need none of it? Your purchase still helps: Flinkform comes from a small agency in Heilbronn, and every licence keeps the free plugin free and in active development. Thank you!' ) ); ?></p>
			<p><?php echo esc_html( sprintf( self::t( 'Only %s day left, for every annual plan. Not convinced? 14 days money back, no questions asked.', 'Only %s days left, for every annual plan. Not convinced? 14 days money back, no questions asked.', $days ), number_format_i18n( $days ) ) ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::url() ); ?>" target="_blank" rel="noopener">
					<?php echo esc_html( self::t( 'Get Pro for 29.50 €' ) ); ?><span class="screen-reader-text"> <?php echo esc_html( self::t( '(opens flinkform.de in a new tab)' ) ); ?></span>
				</a>
				<a class="flinkform-promo__dismiss" href="<?php echo esc_url( $dismiss ); ?>" style="margin-left:12px"><?php echo esc_html( self::t( 'Don\'t show again' ) ); ?></a>
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
