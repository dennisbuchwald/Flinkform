<?php
/**
 * Flinkform Pro, shown where it would help (1.15.0).
 *
 * Rules this class exists to keep (WordPress.org guidelines 5 and 11, and
 * Dennis' "no tracking"):
 *   - no Pro code in the free plugin: every "Pro" spot is information plus
 *     a link, the feature itself only ever arrives with the add-on;
 *   - only where the feature would be used (field picker, form sidebar,
 *     submissions list, one menu page), never an admin-wide banner;
 *   - gone as soon as Pro is active, detected through the Bridge
 *     (Features::is_pro_active()), not by guessing class names;
 *   - `flinkform_show_pro_upsell` switches all of it off (agencies);
 *   - no click tracking in the plugin, the UTM parameters are read on
 *     flinkform.de only.
 *
 * @package Flinkform
 * @since 1.15.0
 */

declare( strict_types = 1 );

namespace Flinkform\Admin;

defined( 'ABSPATH' ) || exit;

use Flinkform\Bridge\Features;

/**
 * Upsell gate, links and the "Pro" admin page.
 */
final class Upsell {

	/**
	 * Admin page slug. Pro's own page is `flinkform-pro`; a different slug
	 * so the two can never collide while both plugins are installed.
	 */
	public const SLUG = 'flinkform-upgrade';

	/**
	 * Show Pro hints at all?
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		if ( Features::is_pro_active() ) {
			return false;
		}
		/**
		 * Switch off every Flinkform Pro hint in the free plugin (field
		 * picker, form sidebar, submissions list, the "Pro" menu page).
		 *
		 * @since 1.15.0
		 *
		 * @param bool $show Default true.
		 */
		return (bool) apply_filters( 'flinkform_show_pro_upsell', true );
	}

	/**
	 * Link to the Pro page on flinkform.de in the site's language, tagged
	 * with where it was clicked (read on flinkform.de, nowhere else).
	 *
	 * @param string $place Short slug of the spot, e.g. 'field-picker'.
	 * @return string
	 */
	public static function url( string $place ): string {
		$base = 0 === strpos( determine_locale(), 'de' ) ? 'https://flinkform.de/pro/' : 'https://flinkform.de/en/pro/';
		return add_query_arg(
			[
				'utm_source'   => 'flinkform-free',
				'utm_medium'   => 'plugin',
				'utm_campaign' => 'upsell',
				'utm_content'  => sanitize_key( $place ),
			],
			$base
		);
	}

	/**
	 * Hook everything in (admin only, and only while there is something
	 * to show).
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ], 20 );
		add_action( 'flinkform_submissions_table_actions', [ $this, 'render_export_hint' ], 20 );
		add_action( 'enqueue_block_editor_assets', [ $this, 'editor_data' ] );
	}

	/**
	 * Tell the editor scripts whether to show Pro hints, and where to link.
	 * Inline data on our own script, read by src/shared/upsell.js.
	 *
	 * @return void
	 */
	public function editor_data(): void {
		$data = [
			'show' => self::enabled(),
			'urls' => [
				'fields'  => self::url( 'field-picker' ),
				'sidebar' => self::url( 'form-sidebar' ),
			],
		];
		wp_add_inline_script(
			'flinkform-form-editor-script',
			'window.flinkformUpsell = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * Flinkform → Pro.
	 *
	 * @return void
	 */
	public function add_page(): void {
		if ( ! self::enabled() ) {
			return;
		}
		$hook = add_submenu_page(
			Menu::PARENT_SLUG,
			__( 'Flinkform Pro', 'flinkform' ),
			__( 'Pro', 'flinkform' ),
			Menu::CAPABILITY,
			self::SLUG,
			[ $this, 'render_page' ]
		);
		if ( $hook ) {
			add_action(
				"admin_print_styles-{$hook}",
				static function (): void {
					wp_register_style( 'flinkform-admin-upgrade', false, [], FLINKFORM_VERSION );
					wp_enqueue_style( 'flinkform-admin-upgrade' );
					wp_add_inline_style( 'flinkform-admin-upgrade', self::inline_css() );
				}
			);
		}
	}

	/**
	 * CSV export, where Pro puts the real button (same action hook).
	 *
	 * @return void
	 */
	public function render_export_hint( $current = [] ): void {
		// Not in the trash view: exporting the trash is not a thing.
		if ( ! self::enabled() || ! empty( $current['trashed'] ) ) {
			return;
		}
		printf(
			'<a class="button flinkform-pro-hint" href="%1$s" target="_blank" rel="noopener">%2$s <span class="flinkform-pro-badge">%3$s</span><span class="screen-reader-text"> %4$s</span></a>',
			esc_url( self::url( 'csv-export' ) ),
			esc_html__( 'Export CSV', 'flinkform' ),
			esc_html__( 'Pro', 'flinkform' ),
			esc_html__( '(opens flinkform.de in a new tab)', 'flinkform' )
		);
	}

	/**
	 * The comparison page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			return;
		}
		$rows = [
			[ __( 'Forms, all 13 field types, multi-step, conditional logic', 'flinkform' ), true ],
			[ __( 'Spam protection without CAPTCHA or third parties', 'flinkform' ), true ],
			[ __( 'Mail notifications, confirmation mail, submissions in the admin', 'flinkform' ), true ],
			[ __( 'GDPR: consent field, retention, privacy tools', 'flinkform' ), true ],
			[ __( 'Stripe payments (card, SEPA, Apple Pay, Google Pay)', 'flinkform' ), false ],
			[ __( 'Calculation fields for prices and totals', 'flinkform' ), false ],
			[ __( 'File uploads, several files per field', 'flinkform' ), false ],
			[ __( 'Webhooks to Zapier, Make, n8n, with retries and a log', 'flinkform' ), false ],
			[ __( 'SMTP sending with a send log', 'flinkform' ), false ],
			[ __( 'Newsletter sign-ups to Brevo, Mailchimp, CleverReach', 'flinkform' ), false ],
			[ __( 'CSV export of submissions', 'flinkform' ), false ],
			[ __( 'Custom CSS per form', 'flinkform' ), false ],
		];
		?>
		<div class="wrap flinkform-upgrade">
			<h1><?php esc_html_e( 'Flinkform Pro', 'flinkform' ); ?></h1>
			<p class="flinkform-upgrade__lead"><?php esc_html_e( 'Everything you have now stays free. Pro adds the things businesses and agencies ask for: payments, uploads, integrations.', 'flinkform' ); ?></p>
			<table class="widefat striped flinkform-upgrade__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Feature', 'flinkform' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Free', 'flinkform' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Pro', 'flinkform' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as [ $label, $in_free ] ) : ?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td><?php echo $in_free ? '<span aria-hidden="true">✓</span><span class="screen-reader-text">' . esc_html__( 'included', 'flinkform' ) . '</span>' : '<span aria-hidden="true">–</span><span class="screen-reader-text">' . esc_html__( 'not included', 'flinkform' ) . '</span>'; ?></td>
							<td><span aria-hidden="true">✓</span><span class="screen-reader-text"><?php esc_html_e( 'included', 'flinkform' ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="flinkform-upgrade__price">
				<?php esc_html_e( 'From 59 € per year for one website. 14-day money-back guarantee.', 'flinkform' ); ?>
			</p>
			<p>
				<a class="button button-primary button-hero" href="<?php echo esc_url( self::url( 'pro-page' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'See Flinkform Pro', 'flinkform' ); ?>
				</a>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: filter name. */
					esc_html__( 'Building sites for clients? %s hides every Pro hint in the free plugin.', 'flinkform' ),
					'<code>add_filter( \'flinkform_show_pro_upsell\', \'__return_false\' );</code>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Styles for the hint button and the Pro page (inline, admin only).
	 *
	 * @return string
	 */
	public static function inline_css(): string {
		return <<<'CSS'
.flinkform-pro-badge { display: inline-block; margin-left: 6px; padding: 0 6px; border-radius: 9999px; background: #f0f0f1; color: #50575e; font-size: 11px; font-weight: 600; line-height: 18px; vertical-align: 1px; }
.button.flinkform-pro-hint { color: #50575e; }
.flinkform-upgrade { max-width: 760px; }
.flinkform-upgrade__lead { font-size: 15px; }
.flinkform-upgrade__table td:not(:first-child), .flinkform-upgrade__table th:not(:first-child) { width: 70px; text-align: center; }
.flinkform-upgrade__price { font-size: 15px; font-weight: 600; margin-top: 20px; }
CSS;
	}
}
