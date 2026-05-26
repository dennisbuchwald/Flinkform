<?php
/**
 * SMTP settings admin page (Phase A — SMTP module).
 *
 * Phase A is split into three slices, each shipped as its own
 * independent feat: commit so the operator can verify on the sandbox
 * between increments:
 *
 *   A-a (THIS SLICE) — Settings page + storage + crypto helper.
 *                       Renders the UI, persists the credentials
 *                       (password encrypted via Settings\Secret),
 *                       offers provider presets that pre-fill
 *                       host / port / encryption. NOT YET wired to
 *                       PHPMailer — saving settings here has no
 *                       runtime effect until A-b lands.
 *
 *   A-b              — phpmailer_init hook + plugin-conflict
 *                       detection (WP Mail SMTP / FluentSMTP / etc.)
 *                       + wp_mail_from{,_name} overrides.
 *
 *   A-c              — "Send test email" button + connection-error
 *                       surfacing + per-provider inline help.
 *
 * Phase D (post-launch) — OAuth2 for Google Workspace + Microsoft 365.
 *                          App-registration strategy (BYO vs. hosted
 *                          proxy) decided then based on real-user
 *                          feedback.
 *
 * Storage shape — single wp_options key `perform_smtp_settings`:
 *
 *   [ *     'enabled'    => bool,                   // master toggle
 *     'provider'   => string,                 // preset key, '' = custom
 *     'host'       => string,                 // SMTP host
 *     'port'       => int,                    // 1-65535
 *     'encryption' => 'none'|'ssl'|'tls',
 *     'auth'       => bool,                   // SMTP-Auth on/off
 *     'username'   => string,
 *     'password'   => string,                 // ENCRYPTED cipher (Settings\Secret)
 *     'from_email' => string,                 // overrides wp_mail_from in A-b
 *     'from_name'  => string,                 // overrides wp_mail_from_name in A-b
 *   ]
 *
 * Single autoloaded option, not nine — settings are atomic to the
 * operator and an array of nine entries is one row, one autoload
 * fetch, one save.
 *
 * Why not Settings API (register_setting + options.php): the
 * options.php endpoint redirects after save which would force us to
 * handle the encrypt-on-save flow in a separate sanitize_callback
 * disconnected from the form context. Keeping the POST handler local
 * matches SubmissionsPage / FormsPage / WebhookLogPage and lets us
 * cleanly express "empty password field means keep the existing
 * cipher" — which the Settings API would force into a register_setting
 * filter that has no visibility into the previous value.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Admin;

use PerForm\Settings\Secret;
use PerForm\Smtp\Transport;

defined( 'ABSPATH' ) || exit;

/**
 * Controller for the PerForm → SMTP page.
 */
final class SmtpPage {

	/**
	 * Submenu slug.
	 *
	 * @var string
	 */
	public const SLUG = 'perform-smtp';

	/**
	 * wp_options key for the settings array.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'perform_smtp_settings';

	/**
	 * Nonce action for the save form.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'perform_smtp_save';

	/**
	 * Default settings — applied with array_merge() on every read so
	 * partial / legacy option shapes still produce a complete config.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'enabled'    => false,
			'provider'   => '',
			'host'       => '',
			'port'       => 587,
			'encryption' => 'tls',
			'auth'       => true,
			'username'   => '',
			'password'   => '',
			'from_email' => '',
			'from_name'  => '',
		];
	}

	/**
	 * Read the current settings, merged with defaults.
	 *
	 * Public so the Phase-A-b PHPMailer hook can pull the active
	 * config without re-implementing the merge.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * Persist a settings array, casting each field through its
	 * sanitiser. The caller is expected to have validated the
	 * request (nonce + capability) already.
	 *
	 * Returns the array as it was actually stored (with defaults
	 * applied + sanitisation), so the render path can immediately
	 * display the canonical state.
	 *
	 * @param array<string, mixed> $input         Raw input.
	 * @param array<string, mixed> $previous      Previously stored settings (for password preservation).
	 * @return array<string, mixed>
	 */
	public static function save_settings( array $input, array $previous ): array {
		$next = self::defaults();

		$next['enabled']    = ! empty( $input['enabled'] );
		$next['provider']   = self::sanitize_provider_key( (string) ( $input['provider'] ?? '' ) );
		$next['host']       = sanitize_text_field( (string) ( $input['host'] ?? '' ) );
		$next['port']       = self::sanitize_port( (int) ( $input['port'] ?? 0 ) );
		$next['encryption'] = self::sanitize_encryption( (string) ( $input['encryption'] ?? '' ) );
		$next['auth']       = ! empty( $input['auth'] );
		$next['username']   = sanitize_text_field( (string) ( $input['username'] ?? '' ) );

		// Password handling: an empty submitted password keeps the
		// existing cipher. This is the only safe way to handle the
		// "show settings on page reload" case without ever sending
		// plaintext back to the browser. The placeholder in the
		// input is empty too — operators see "no value" and know
		// to type a new password only if they want to change it.
		$submitted_password = (string) ( $input['password'] ?? '' );
		if ( '' === $submitted_password ) {
			$next['password'] = (string) ( $previous['password'] ?? '' );
		} else {
			$next['password'] = Secret::encrypt( $submitted_password );
		}

		$from_email          = sanitize_email( (string) ( $input['from_email'] ?? '' ) );
		$next['from_email']  = is_email( $from_email ) ? $from_email : '';
		$next['from_name']   = sanitize_text_field( (string) ( $input['from_name'] ?? '' ) );

		update_option( self::OPTION_KEY, $next, false );

		return $next;
	}

	/**
	 * Provider-preset registry.
	 *
	 * Keys are stable identifiers persisted in the `provider` field;
	 * 'label' is shown in the dropdown; host/port/encryption fill
	 * the form when the operator picks the preset (JS handler in
	 * render_preset_filler_script()).
	 *
	 * The two `disabled` entries (Microsoft 365, Google Workspace)
	 * are intentionally listed in the dropdown so operators looking
	 * for them see a clear status message instead of getting
	 * confused by their absence. Both require OAuth2 and ship in
	 * Phase D.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function providers(): array {
		return [
			''        => [
				'label'      => __( 'Custom SMTP server', 'perform-forms' ),
				'host'       => '',
				'port'       => 587,
				'encryption' => 'tls',
				'help'       => __( 'Enter the SMTP details supplied by your mail provider.', 'perform-forms' ),
			],
			'gmail'   => [
				'label'      => __( 'Gmail (App-Password)', 'perform-forms' ),
				'host'       => 'smtp.gmail.com',
				'port'       => 587,
				'encryption' => 'tls',
				'help'       => __( 'Requires 2-Step-Verification on your Google account. Create an App-Password at https://myaccount.google.com/apppasswords and use it as the password below. Workspace admins can disable App-Passwords by policy — in that case wait for the OAuth2 release in v0.2.', 'perform-forms' ),
			],
			'outlook' => [
				'label'      => __( 'Outlook.com / Hotmail (personal)', 'perform-forms' ),
				'host'       => 'smtp-mail.outlook.com',
				'port'       => 587,
				'encryption' => 'tls',
				'help'       => __( 'Personal Outlook.com / Hotmail accounts only. Microsoft 365 (business) accounts require OAuth2 — coming in PerForm v0.2.', 'perform-forms' ),
			],
			'sendgrid' => [
				'label'      => __( 'SendGrid', 'perform-forms' ),
				'host'       => 'smtp.sendgrid.net',
				'port'       => 587,
				'encryption' => 'tls',
				'help'       => __( 'Username is literally "apikey" (lowercase). Password is your SendGrid API key.', 'perform-forms' ),
			],
			'mailgun' => [
				'label'      => __( 'Mailgun', 'perform-forms' ),
				'host'       => 'smtp.mailgun.org',
				'port'       => 587,
				'encryption' => 'tls',
				'help'       => __( 'Find your SMTP credentials in Mailgun under Sending → Domain Settings → SMTP credentials. EU customers: change the host to smtp.eu.mailgun.org.', 'perform-forms' ),
			],
			'brevo'   => [
				'label'      => __( 'Brevo (formerly Sendinblue)', 'perform-forms' ),
				'host'       => 'smtp-relay.brevo.com',
				'port'       => 587,
				'encryption' => 'tls',
				'help'       => __( 'SMTP credentials are at Brevo → SMTP & API → SMTP. The username is your Brevo account email.', 'perform-forms' ),
			],
			'postmark' => [
				'label'      => __( 'Postmark', 'perform-forms' ),
				'host'       => 'smtp.postmarkapp.com',
				'port'       => 587,
				'encryption' => 'tls',
				'help'       => __( 'Username and password are both your Postmark Server API token (the same string).', 'perform-forms' ),
			],
			'ses'     => [
				'label'      => __( 'Amazon SES', 'perform-forms' ),
				'host'       => 'smtp.us-east-1.amazonaws.com',
				'port'       => 587,
				'encryption' => 'tls',
				'help'       => __( 'Replace "us-east-1" in the host with your SES region. Username and password are SMTP-specific credentials (created under IAM → SMTP settings), NOT your normal AWS access key.', 'perform-forms' ),
			],
			'm365'    => [
				'label'      => __( 'Microsoft 365 — requires OAuth2 (coming in v0.2)', 'perform-forms' ),
				'host'       => '',
				'port'       => 0,
				'encryption' => '',
				'help'       => __( 'Microsoft disabled basic SMTP authentication for Microsoft 365 tenants. OAuth2 support is scheduled for PerForm v0.2.', 'perform-forms' ),
				'disabled'   => true,
			],
			'workspace' => [
				'label'      => __( 'Google Workspace — use App-Password preset or wait for OAuth2 (v0.2)', 'perform-forms' ),
				'host'       => '',
				'port'       => 0,
				'encryption' => '',
				'help'       => __( 'Google Workspace accounts can use the Gmail preset above if your admin allows App-Passwords. Otherwise wait for the OAuth2 release in PerForm v0.2.', 'perform-forms' ),
				'disabled'   => true,
			],
		];
	}

	/**
	 * Pre-headers POST handler. Bound from Menu::dispatch_actions().
	 *
	 * @return void
	 */
	public function dispatch(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			return;
		}

		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in next line.
		$action = isset( $_POST['perform_smtp_action'] ) ? sanitize_key( wp_unslash( $_POST['perform_smtp_action'] ) ) : '';
		if ( 'save' !== $action ) {
			return;
		}

		check_admin_referer( self::NONCE_ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$raw = isset( $_POST['perform_smtp'] ) && is_array( $_POST['perform_smtp'] )
			? wp_unslash( $_POST['perform_smtp'] )
			: [];

		$previous = self::get_settings();
		self::save_settings( $raw, $previous );

		wp_safe_redirect(
			add_query_arg(
				'perform_notice',
				rawurlencode( __( 'SMTP settings saved.', 'perform-forms' ) ),
				$this->page_url()
			)
		);
		exit;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage PerForm SMTP settings.', 'perform-forms' ) );
		}

		$this->print_inline_styles();

		$settings  = self::get_settings();
		$providers = self::providers();

		?>
		<div class="wrap perform-smtp">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'SMTP Settings', 'perform-forms' ); ?></h1>
			<hr class="wp-header-end" />

			<?php $this->maybe_print_notice(); ?>

			<p class="description" style="max-width: 720px;">
				<?php esc_html_e( 'Configure an SMTP server so PerForm can deliver admin notifications and submitter confirmations through your own mail provider instead of the WordPress default. The status panel below shows whether the configured override is actually in effect for the next wp_mail() call.', 'perform-forms' ); ?>
			</p>

			<?php $this->render_status_block(); ?>

			<form method="post" action="<?php echo esc_url( $this->page_url() ); ?>" class="perform-smtp__form">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="perform_smtp_action" value="save" />

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable PerForm SMTP', 'perform-forms' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="perform_smtp[enabled]" value="1" <?php checked( (bool) $settings['enabled'] ); ?> />
									<?php esc_html_e( 'Route plugin emails through this SMTP configuration.', 'perform-forms' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'When disabled, PerForm falls back to the default wp_mail() transport (PHP mail or whatever any other SMTP plugin provides).', 'perform-forms' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="perform-smtp-provider"><?php esc_html_e( 'Provider preset', 'perform-forms' ); ?></label>
							</th>
							<td>
								<select id="perform-smtp-provider" name="perform_smtp[provider]" class="regular-text">
									<?php foreach ( $providers as $key => $provider ) : ?>
										<option
											value="<?php echo esc_attr( $key ); ?>"
											<?php selected( $settings['provider'], $key ); ?>
											<?php disabled( ! empty( $provider['disabled'] ) ); ?>
										>
											<?php echo esc_html( $provider['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p id="perform-smtp-provider-help" class="description">
									<?php
									$current_help = $providers[ $settings['provider'] ]['help'] ?? $providers['']['help'];
									echo esc_html( (string) $current_help );
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="perform-smtp-host"><?php esc_html_e( 'SMTP host', 'perform-forms' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="perform-smtp-host"
									name="perform_smtp[host]"
									value="<?php echo esc_attr( (string) $settings['host'] ); ?>"
									class="regular-text"
									autocomplete="off"
									placeholder="smtp.example.com"
								/>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="perform-smtp-port"><?php esc_html_e( 'Port', 'perform-forms' ); ?></label>
							</th>
							<td>
								<input
									type="number"
									id="perform-smtp-port"
									name="perform_smtp[port]"
									value="<?php echo esc_attr( (string) $settings['port'] ); ?>"
									class="small-text"
									min="1"
									max="65535"
								/>
								<p class="description">
									<?php esc_html_e( 'Common values: 587 (STARTTLS), 465 (SSL), 25 (none / legacy).', 'perform-forms' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="perform-smtp-encryption"><?php esc_html_e( 'Encryption', 'perform-forms' ); ?></label>
							</th>
							<td>
								<select id="perform-smtp-encryption" name="perform_smtp[encryption]">
									<option value="tls"  <?php selected( $settings['encryption'], 'tls' ); ?>><?php esc_html_e( 'TLS (STARTTLS)', 'perform-forms' ); ?></option>
									<option value="ssl"  <?php selected( $settings['encryption'], 'ssl' ); ?>><?php esc_html_e( 'SSL', 'perform-forms' ); ?></option>
									<option value="none" <?php selected( $settings['encryption'], 'none' ); ?>><?php esc_html_e( 'None (not recommended)', 'perform-forms' ); ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Authentication', 'perform-forms' ); ?></th>
							<td>
								<label>
									<input type="checkbox" id="perform-smtp-auth" name="perform_smtp[auth]" value="1" <?php checked( (bool) $settings['auth'] ); ?> />
									<?php esc_html_e( 'My SMTP server requires authentication (almost always yes).', 'perform-forms' ); ?>
								</label>
							</td>
						</tr>

						<tr class="perform-smtp__auth-row">
							<th scope="row">
								<label for="perform-smtp-username"><?php esc_html_e( 'Username', 'perform-forms' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="perform-smtp-username"
									name="perform_smtp[username]"
									value="<?php echo esc_attr( (string) $settings['username'] ); ?>"
									class="regular-text"
									autocomplete="off"
								/>
							</td>
						</tr>

						<tr class="perform-smtp__auth-row">
							<th scope="row">
								<label for="perform-smtp-password"><?php esc_html_e( 'Password', 'perform-forms' ); ?></label>
							</th>
							<td>
								<input
									type="password"
									id="perform-smtp-password"
									name="perform_smtp[password]"
									value=""
									class="regular-text"
									autocomplete="new-password"
									placeholder="<?php echo esc_attr( '' !== (string) $settings['password'] ? __( '•••••••• (leave empty to keep current)', 'perform-forms' ) : __( 'Enter your SMTP password or API key', 'perform-forms' ) ); ?>"
								/>
								<p class="description">
									<?php esc_html_e( 'Stored encrypted (AES-256-CBC, key derived from wp_salt). The plaintext password is never sent back to your browser — leave this field empty to keep the existing value when re-saving.', 'perform-forms' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="perform-smtp-from-email"><?php esc_html_e( 'From email', 'perform-forms' ); ?></label>
							</th>
							<td>
								<input
									type="email"
									id="perform-smtp-from-email"
									name="perform_smtp[from_email]"
									value="<?php echo esc_attr( (string) $settings['from_email'] ); ?>"
									class="regular-text"
									placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"
								/>
								<p class="description">
									<?php esc_html_e( 'Overrides the WordPress default From-address for plugin emails. Leave empty to keep wp_mail’s default.', 'perform-forms' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="perform-smtp-from-name"><?php esc_html_e( 'From name', 'perform-forms' ); ?></label>
							</th>
							<td>
								<input
									type="text"
									id="perform-smtp-from-name"
									name="perform_smtp[from_name]"
									value="<?php echo esc_attr( (string) $settings['from_name'] ); ?>"
									class="regular-text"
									placeholder="<?php echo esc_attr( (string) get_bloginfo( 'name' ) ); ?>"
								/>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save SMTP settings', 'perform-forms' ) ); ?>
			</form>
		</div>

		<?php $this->render_preset_filler_script( $providers ); ?>
		<?php
	}

	/**
	 * Render the live diagnostic status block at the top of the
	 * settings page.
	 *
	 * The block answers — at a glance, without leaving the page —
	 * the five questions an operator asks when their SMTP override
	 * doesn't seem to be working:
	 *
	 *   1. Is the Transport class even loaded? (Defends against
	 *      partial FTP uploads where SmtpPage.php is newer than
	 *      Smtp/Transport.php.)
	 *   2. Is the master toggle on?
	 *   3. Is the stored config usable (host + port + decryptable
	 *      password)?
	 *   4. Is a rival SMTP plugin active?
	 *   5. Net answer: will the next wp_mail() route via PerForm?
	 *
	 * Status is read fresh on every render — no caching across
	 * requests, no AJAX. Settings page is admin-only and rarely
	 * loaded; the cost is trivial.
	 *
	 * @return void
	 */
	private function render_status_block(): void {
		$transport_loaded = class_exists( Transport::class );

		// Partial-upload defense: if Transport isn't loadable we
		// can't call get_status() without a fatal. Show a single
		// red row + bail.
		if ( ! $transport_loaded ) {
			?>
			<div class="perform-smtp__status perform-smtp__status--effective-false">
				<h2><?php esc_html_e( 'SMTP Status', 'perform-forms' ); ?></h2>
				<table>
					<tbody>
						<tr>
							<td class="perform-smtp__status-label"><?php esc_html_e( 'Transport module', 'perform-forms' ); ?></td>
							<td><span class="perform-smtp__status-badge perform-smtp__status-badge--bad"><?php esc_html_e( 'Missing', 'perform-forms' ); ?></span></td>
							<td class="perform-smtp__status-detail">
								<?php esc_html_e( 'The PerForm\\Smtp\\Transport class could not be loaded. Upload includes/Smtp/Transport.php to fix this.', 'perform-forms' ); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<?php
			return;
		}

		$status = Transport::get_status();
		$wrapper_modifier = $status['effective'] ? 'effective-true' : 'effective-false';
		?>
		<div class="perform-smtp__status perform-smtp__status--<?php echo esc_attr( $wrapper_modifier ); ?>">
			<h2><?php esc_html_e( 'SMTP Status', 'perform-forms' ); ?></h2>
			<table>
				<tbody>
					<tr>
						<td class="perform-smtp__status-label"><?php esc_html_e( 'Transport module', 'perform-forms' ); ?></td>
						<td><span class="perform-smtp__status-badge perform-smtp__status-badge--ok"><?php esc_html_e( 'Loaded', 'perform-forms' ); ?></span></td>
						<td class="perform-smtp__status-detail">
							<?php esc_html_e( 'PerForm\\Smtp\\Transport is active on this request.', 'perform-forms' ); ?>
						</td>
					</tr>

					<tr>
						<td class="perform-smtp__status-label"><?php esc_html_e( 'Master toggle', 'perform-forms' ); ?></td>
						<?php if ( $status['enabled'] ) : ?>
							<td><span class="perform-smtp__status-badge perform-smtp__status-badge--ok"><?php esc_html_e( 'Enabled', 'perform-forms' ); ?></span></td>
							<td class="perform-smtp__status-detail">
								<?php esc_html_e( '"Enable PerForm SMTP" is on.', 'perform-forms' ); ?>
							</td>
						<?php else : ?>
							<td><span class="perform-smtp__status-badge perform-smtp__status-badge--bad"><?php esc_html_e( 'Disabled', 'perform-forms' ); ?></span></td>
							<td class="perform-smtp__status-detail">
								<?php esc_html_e( '"Enable PerForm SMTP" is off — wp_mail() falls back to the WordPress default transport.', 'perform-forms' ); ?>
							</td>
						<?php endif; ?>
					</tr>

					<tr>
						<td class="perform-smtp__status-label"><?php esc_html_e( 'Configuration', 'perform-forms' ); ?></td>
						<?php if ( $status['configured'] ) : ?>
							<td><span class="perform-smtp__status-badge perform-smtp__status-badge--ok"><?php esc_html_e( 'Complete', 'perform-forms' ); ?></span></td>
							<td class="perform-smtp__status-detail">
								<?php
								$settings = self::get_settings();
								echo esc_html(
									sprintf(
										/* translators: 1: host, 2: port, 3: encryption mode (TLS / SSL / none) */
										__( '%1$s:%2$d (%3$s)', 'perform-forms' ),
										(string) $settings['host'],
										(int) $settings['port'],
										strtoupper( (string) $settings['encryption'] )
									)
								);
								?>
							</td>
						<?php else : ?>
							<td><span class="perform-smtp__status-badge perform-smtp__status-badge--bad"><?php esc_html_e( 'Incomplete', 'perform-forms' ); ?></span></td>
							<td class="perform-smtp__status-detail"><?php echo esc_html( (string) $status['configured_notes'] ); ?></td>
						<?php endif; ?>
					</tr>

					<tr>
						<td class="perform-smtp__status-label"><?php esc_html_e( 'Plugin conflict', 'perform-forms' ); ?></td>
						<?php if ( false === $status['conflict'] ) : ?>
							<td><span class="perform-smtp__status-badge perform-smtp__status-badge--neutral"><?php esc_html_e( 'None', 'perform-forms' ); ?></span></td>
							<td class="perform-smtp__status-detail">
								<?php esc_html_e( 'No competing SMTP plugin detected (WP Mail SMTP / FluentSMTP / Easy WP SMTP / Post SMTP).', 'perform-forms' ); ?>
							</td>
						<?php else : ?>
							<td><span class="perform-smtp__status-badge perform-smtp__status-badge--warn"><?php esc_html_e( 'Detected', 'perform-forms' ); ?></span></td>
							<td class="perform-smtp__status-detail">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: name of the conflicting plugin */
										__( '"%s" is active — PerForm SMTP self-disabled to avoid a double configuration. Deactivate the other plugin to let PerForm SMTP take over.', 'perform-forms' ),
										(string) $status['conflict']
									)
								);
								?>
							</td>
						<?php endif; ?>
					</tr>

					<tr>
						<td class="perform-smtp__status-label"><strong><?php esc_html_e( 'Effective', 'perform-forms' ); ?></strong></td>
						<?php if ( $status['effective'] ) : ?>
							<td><span class="perform-smtp__status-badge perform-smtp__status-badge--ok"><?php esc_html_e( 'Active', 'perform-forms' ); ?></span></td>
							<td class="perform-smtp__status-detail">
								<strong><?php esc_html_e( 'The next wp_mail() call WILL route through this SMTP configuration.', 'perform-forms' ); ?></strong>
							</td>
						<?php else : ?>
							<td><span class="perform-smtp__status-badge perform-smtp__status-badge--bad"><?php esc_html_e( 'Inactive', 'perform-forms' ); ?></span></td>
							<td class="perform-smtp__status-detail">
								<strong><?php esc_html_e( 'wp_mail() will use the WordPress default transport (not your configured SMTP).', 'perform-forms' ); ?></strong>
								<?php esc_html_e( 'Resolve the rows marked above to fix this.', 'perform-forms' ); ?>
							</td>
						<?php endif; ?>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Tiny vanilla-JS handler that fills host/port/encryption when
	 * the operator picks a provider preset, and toggles the
	 * username/password row visibility based on the auth checkbox.
	 *
	 * No build pipeline involvement on purpose — this is one
	 * settings page, ~40 lines of JS, inline is the right cost.
	 *
	 * @param array<string, array<string, mixed>> $providers
	 * @return void
	 */
	private function render_preset_filler_script( array $providers ): void {
		// Strip the (already-translated) help text to plain strings
		// for the JS payload — wp_json_encode will escape correctly.
		$payload = [];
		foreach ( $providers as $key => $provider ) {
			$payload[ $key ] = [
				'host'       => (string) ( $provider['host'] ?? '' ),
				'port'       => (int) ( $provider['port'] ?? 0 ),
				'encryption' => (string) ( $provider['encryption'] ?? '' ),
				'help'       => (string) ( $provider['help'] ?? '' ),
				'disabled'   => ! empty( $provider['disabled'] ),
			];
		}
		?>
		<script>
		(function () {
			var presets = <?php echo wp_json_encode( $payload ); ?>;

			var providerSelect = document.getElementById('perform-smtp-provider');
			var hostInput      = document.getElementById('perform-smtp-host');
			var portInput      = document.getElementById('perform-smtp-port');
			var encSelect      = document.getElementById('perform-smtp-encryption');
			var helpEl         = document.getElementById('perform-smtp-provider-help');
			var authCheckbox   = document.getElementById('perform-smtp-auth');
			var authRows       = document.querySelectorAll('.perform-smtp__auth-row');

			if (providerSelect) {
				providerSelect.addEventListener('change', function () {
					var preset = presets[providerSelect.value];
					if (!preset || preset.disabled) {
						return;
					}
					// Only overwrite the host/port/encryption when we
					// have a preset value — picking "Custom SMTP server"
					// (which has host '') must not wipe what the
					// operator already typed.
					if (preset.host && hostInput) {
						hostInput.value = preset.host;
					}
					if (preset.port && portInput) {
						portInput.value = preset.port;
					}
					if (preset.encryption && encSelect) {
						encSelect.value = preset.encryption;
					}
					if (helpEl) {
						helpEl.textContent = preset.help || '';
					}
				});
			}

			function syncAuthVisibility() {
				if (!authCheckbox) {
					return;
				}
				var visible = authCheckbox.checked;
				authRows.forEach(function (row) {
					row.style.display = visible ? '' : 'none';
				});
			}

			if (authCheckbox) {
				authCheckbox.addEventListener('change', syncAuthVisibility);
				syncAuthVisibility();
			}
		})();
		</script>
		<?php
	}

	/**
	 * Constrain the port to the TCP range.
	 *
	 * @param int $port
	 * @return int
	 */
	private static function sanitize_port( int $port ): int {
		if ( $port < 1 ) {
			return 587;
		}
		if ( $port > 65535 ) {
			return 65535;
		}
		return $port;
	}

	/**
	 * Whitelist the encryption mode.
	 *
	 * @param string $encryption
	 * @return string
	 */
	private static function sanitize_encryption( string $encryption ): string {
		$encryption = strtolower( $encryption );
		return in_array( $encryption, [ 'none', 'ssl', 'tls' ], true ) ? $encryption : 'tls';
	}

	/**
	 * Validate the provider key against the preset registry; unknown
	 * keys fall back to '' (custom) so a tampered POST cannot persist
	 * arbitrary strings.
	 *
	 * @param string $provider
	 * @return string
	 */
	private static function sanitize_provider_key( string $provider ): string {
		$provider = sanitize_key( $provider );
		$known    = array_keys( self::providers() );
		return in_array( $provider, $known, true ) ? $provider : '';
	}

	/**
	 * Print a one-shot success notice if the URL carries one.
	 *
	 * @return void
	 */
	private function maybe_print_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash message.
		$notice = isset( $_GET['perform_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['perform_notice'] ) ) : '';
		if ( '' === $notice ) {
			return;
		}
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $notice ) );
	}

	/**
	 * Canonical URL of this page.
	 *
	 * @return string
	 */
	private function page_url(): string {
		return add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) );
	}

	/**
	 * Inline CSS. Tiny — one extra style block beats enqueueing a
	 * dedicated admin stylesheet for a single page.
	 *
	 * @return void
	 */
	private function print_inline_styles(): void {
		?>
		<style>
			.perform-smtp__form .form-table th { width: 220px; }
			.perform-smtp__form input[type="text"],
			.perform-smtp__form input[type="email"],
			.perform-smtp__form input[type="password"],
			.perform-smtp__form select { max-width: 420px; }
			/* Keep description text in the right column from running
			   past the form's visual width on wide monitors. */
			.perform-smtp__form .form-table td .description { max-width: 560px; }

			/* Diagnostic status block. Border-left colour tracks the
			   "effective" boolean: green = SMTP override will fire on
			   the next wp_mail(), red = something is blocking it. */
			.perform-smtp .perform-smtp__status {
				margin: 20px 0 24px;
				padding: 14px 20px 16px;
				background: #f6f7f7;
				border-left: 4px solid #2271b1;
				max-width: 920px;
			}
			.perform-smtp .perform-smtp__status--effective-true  { border-left-color: #00a32a; }
			.perform-smtp .perform-smtp__status--effective-false { border-left-color: #d63638; }
			.perform-smtp .perform-smtp__status h2 {
				margin: 0 0 10px;
				font-size: 12px;
				text-transform: uppercase;
				letter-spacing: 0.06em;
				color: #1d2327;
			}
			.perform-smtp .perform-smtp__status table {
				border-collapse: collapse;
				width: 100%;
			}
			.perform-smtp .perform-smtp__status td {
				padding: 4px 12px 4px 0;
				vertical-align: middle;
				border: 0;
			}
			.perform-smtp .perform-smtp__status td:last-child { padding-right: 0; }
			.perform-smtp .perform-smtp__status-label {
				font-weight: 600;
				width: 150px;
				color: #1d2327;
				white-space: nowrap;
			}
			.perform-smtp .perform-smtp__status-badge {
				display: inline-block;
				padding: 2px 10px;
				border-radius: 9999px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.04em;
				white-space: nowrap;
			}
			.perform-smtp .perform-smtp__status-badge--ok      { background: #d1e7d8; color: #1a6c2f; }
			.perform-smtp .perform-smtp__status-badge--bad     { background: #fce4e7; color: #8e2933; }
			.perform-smtp .perform-smtp__status-badge--warn    { background: #fcf0d4; color: #7c4910; }
			.perform-smtp .perform-smtp__status-badge--neutral { background: #f0f0f1; color: #50575e; }
			.perform-smtp .perform-smtp__status-detail {
				color: #50575e;
				font-size: 13px;
				line-height: 1.5;
			}
		</style>
		<?php
	}
}
