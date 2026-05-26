<?php
/**
 * SMTP transport-layer integration (Phase A-b).
 *
 * Where this fits in the SMTP module:
 *
 *   A-a — Settings page + storage + crypto helper (UI persists, but
 *          the stored config has no runtime effect on wp_mail yet).
 *   A-b — THIS SLICE: the phpmailer_init hook that actually routes
 *          outgoing mail through the configured SMTP server, the
 *          wp_mail_from{,_name} overrides, and the plugin-conflict
 *          detection that self-disables PerForm SMTP whenever a
 *          dedicated SMTP plugin is already active.
 *   A-c — Test-email button + per-provider quick-start help polish.
 *
 * Naming choice — Smtp\Transport vs. Mailer\Transport: PerForm already
 * has `Notifications\Mailer` (which composes the notification + the
 * confirmation mail). Putting the transport-layer code under
 * `Smtp\` keeps "what to send" (Notifications) and "how to send"
 * (Smtp\Transport) cleanly separated even at the namespace level.
 *
 * Hook strategy:
 *
 *   - All WordPress hook registrations happen on the `init` action.
 *     This keeps the Transport class i18n-safe: every admin-notice
 *     string only runs through __() after WordPress has finished
 *     loading the translation pipeline. See [[feedback-i18n-timing]]
 *     in repo memory and commit c092c70 for the precedent.
 *
 *   - Priority 1000 on `phpmailer_init`, `wp_mail_from`,
 *     `wp_mail_from_name`. Late enough that any other plugin's
 *     own override runs first; if a known competitor SMTP plugin
 *     is active we explicitly bail out (see should_handle()), so
 *     "last writer wins" never becomes a fight.
 *
 *   - The admin-notice hook is only attached inside wp-admin. The
 *     notice fires on every admin page (not just PerForm pages),
 *     so an operator who enabled PerForm SMTP and then installed
 *     WP Mail SMTP can't miss the warning.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Smtp;

use PerForm\Admin\SmtpPage;
use PerForm\Settings\Secret;
use PHPMailer\PHPMailer\PHPMailer;

defined( 'ABSPATH' ) || exit;

/**
 * Owns every outgoing-mail hook the SMTP module installs.
 */
final class Transport {

	/**
	 * Plugin slug → display label map of known SMTP plugins we
	 * defer to.
	 *
	 * Whenever any of these is active and PerForm SMTP is also
	 * enabled, the Transport self-disables: phpmailer_init returns
	 * without touching the PHPMailer instance, the wp_mail_from
	 * filters return the value unchanged, and an admin-notice
	 * informs the operator. Operators who want the two side-by-side
	 * (almost always a mistake) must deactivate one of them.
	 *
	 * @var array<string, string>
	 */
	private const CONFLICTING_PLUGINS = [
		'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP',
		'fluent-smtp/fluent-smtp.php'   => 'FluentSMTP',
		'easy-wp-smtp/easy-wp-smtp.php' => 'Easy WP SMTP',
		'post-smtp/postman-smtp.php'    => 'Post SMTP Mailer',
	];

	/**
	 * Static "is there a conflict?" cache, scoped to the request.
	 *
	 * Both phpmailer_init and the two From filters need the answer,
	 * the admin-notice hook fires once per page render, and the
	 * SMTP-status diagnostic on the settings page calls it too.
	 * Static (not instance) so the diagnostic on the settings page
	 * shares the same memo even though it uses self::detect_conflict()
	 * without an instance.
	 *
	 * @var string|null|false  null = uncached, false = no conflict,
	 *                          string = display label of the
	 *                          first detected conflicting plugin.
	 */
	private static $cached_conflict_label = null;

	/**
	 * Entry point. Defers all hook registration to the `init` action
	 * to keep the i18n-timing rule.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_hooks' ] );
	}

	/**
	 * Late-bound hook registration — runs once `init` fires so any
	 * __() call inside our callbacks is safe.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'phpmailer_init', [ $this, 'apply_smtp_config' ], 1000 );
		add_filter( 'wp_mail_from', [ $this, 'override_from_email' ], 1000 );
		add_filter( 'wp_mail_from_name', [ $this, 'override_from_name' ], 1000 );

		if ( is_admin() ) {
			add_action( 'admin_notices', [ $this, 'maybe_render_conflict_notice' ] );
		}
	}

	/**
	 * phpmailer_init callback. Applies the stored SMTP config to
	 * the active PHPMailer instance — but only when the master
	 * toggle is on AND no rival SMTP plugin is active AND the
	 * stored config is complete enough to actually connect.
	 *
	 * The function takes PHPMailer by reference (WordPress passes
	 * the instance that way), so mutations land on the real object.
	 *
	 * @param PHPMailer $mail PHPMailer instance prepared by WP.
	 * @return void
	 */
	public function apply_smtp_config( PHPMailer $mail ): void {
		if ( ! $this->should_handle() ) {
			return;
		}

		$settings = SmtpPage::get_settings();

		// Defensive empty-host check: a stored row with an empty
		// host would silently degrade to "send via localhost:25"
		// once we call isSMTP(). Better to bail and fall back to
		// the WordPress default.
		$host = (string) $settings['host'];
		$port = (int) $settings['port'];
		if ( '' === $host || $port < 1 ) {
			return;
		}

		$mail->isSMTP();
		$mail->Host = $host;
		$mail->Port = $port;

		$encryption = (string) $settings['encryption'];
		switch ( $encryption ) {
			case 'ssl':
				$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
				break;
			case 'tls':
				$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
				break;
			default:
				// 'none' — explicitly opt out of opportunistic TLS too.
				// PHPMailer otherwise STARTTLSes whenever the server
				// advertises support, which would surprise operators
				// who picked "None" on purpose (e.g. an internal
				// relay on a trusted network).
				$mail->SMTPSecure  = '';
				$mail->SMTPAutoTLS = false;
				break;
		}

		if ( ! empty( $settings['auth'] ) ) {
			$plaintext = Secret::decrypt( (string) $settings['password'] );

			// Auth was requested but the cipher won't decrypt — the
			// likely cause is a rotated auth salt in wp-config.php
			// that invalidated the stored password. Bail out so we
			// don't try to authenticate with an empty string (which
			// some providers accept then silently drop the mail).
			if ( '' === $plaintext ) {
				return;
			}

			$mail->SMTPAuth = true;
			$mail->Username = (string) $settings['username'];
			$mail->Password = $plaintext;
		} else {
			$mail->SMTPAuth = false;
		}
	}

	/**
	 * wp_mail_from filter — replaces the WP default From address
	 * when the operator configured one. Leaves the original value
	 * alone whenever PerForm SMTP is disabled OR a rival plugin is
	 * active OR no override is configured.
	 *
	 * @param string $from
	 * @return string
	 */
	public function override_from_email( string $from ): string {
		if ( ! $this->should_handle() ) {
			return $from;
		}

		$settings   = SmtpPage::get_settings();
		$configured = (string) $settings['from_email'];

		return '' !== $configured && is_email( $configured ) ? $configured : $from;
	}

	/**
	 * wp_mail_from_name filter — same semantics as the email
	 * override, for the human-readable From name.
	 *
	 * @param string $name
	 * @return string
	 */
	public function override_from_name( string $name ): string {
		if ( ! $this->should_handle() ) {
			return $name;
		}

		$settings   = SmtpPage::get_settings();
		$configured = (string) $settings['from_name'];

		return '' !== $configured ? $configured : $name;
	}

	/**
	 * Admin-notice renderer. Fires on every wp-admin page render
	 * (not just PerForm pages) so an operator can't accidentally
	 * leave the site in a "PerForm SMTP enabled + WP Mail SMTP
	 * active" state without seeing the warning.
	 *
	 * Notice is dismissible per-page-render but not persistently
	 * suppressed — re-appearing on next page load is intentional
	 * pressure to actually fix the conflict.
	 *
	 * @return void
	 */
	public function maybe_render_conflict_notice(): void {
		$settings = SmtpPage::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$conflict = self::detect_conflict();
		if ( false === $conflict ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'PerForm SMTP is self-disabled.', 'perform-forms' ),
			esc_html(
				sprintf(
					/* translators: %s: name of the conflicting plugin */
					__( 'The plugin "%s" is active and already manages SMTP delivery — PerForm has disabled its own override to avoid a double configuration. Deactivate the other SMTP plugin (or disable PerForm SMTP) to clear this notice.', 'perform-forms' ),
					$conflict
				)
			)
		);
	}

	/**
	 * Single-source gate that all three "do we modify this mail?"
	 * hooks consult. Encapsulates the truth-table:
	 *
	 *   enabled   conflict   should_handle
	 *   ────────  ─────────  ────────────
	 *   false     —          false
	 *   true      detected   false
	 *   true      none       true
	 *
	 * @return bool
	 */
	private function should_handle(): bool {
		$settings = SmtpPage::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}
		return false === self::detect_conflict();
	}

	/**
	 * Detect the first active conflicting SMTP plugin.
	 *
	 * Uses `get_option('active_plugins')` directly because
	 * `is_plugin_active()` lives in wp-admin/includes/plugin.php
	 * which is not loaded on front-end requests — and
	 * phpmailer_init absolutely runs from front-end requests
	 * (form submissions, password resets, contact-form mails).
	 *
	 * Multisite network-activated plugins are NOT scanned;
	 * PerForm's Multisite support is post-MVP.
	 *
	 * Public so the SMTP-settings diagnostic block can show the
	 * same conflict label it would show in the admin notice.
	 *
	 * @return string|false  Display label of the conflicting
	 *                       plugin, or false when there is none.
	 */
	public static function detect_conflict() {
		if ( null !== self::$cached_conflict_label ) {
			return self::$cached_conflict_label;
		}

		$active = (array) get_option( 'active_plugins', [] );

		foreach ( self::CONFLICTING_PLUGINS as $slug => $label ) {
			if ( in_array( $slug, $active, true ) ) {
				self::$cached_conflict_label = $label;
				return $label;
			}
		}

		self::$cached_conflict_label = false;
		return false;
	}

	/**
	 * Diagnostic snapshot for the SMTP-settings page status block.
	 *
	 * Aggregates everything an operator needs to debug "why isn't
	 * my SMTP working?" into one shape. The settings page renders
	 * this with traffic-light badges so the answer is visible at
	 * a glance instead of needing to trace through the codebase.
	 *
	 * Shape:
	 *   [ *     'transport_loaded' => bool,    // class loaded on this request
	 *     'enabled'          => bool,    // master toggle in settings
	 *     'configured'       => bool,    // host + port + (auth → username/password) usable
	 *     'configured_notes' => string,  // human reason if not configured
	 *     'conflict'         => string|false,  // rival plugin label, false = none
	 *     'effective'        => bool,    // would the next wp_mail() route via our config?
	 *   ]
	 *
	 * @return array<string, mixed>
	 */
	public static function get_status(): array {
		$settings = SmtpPage::get_settings();

		$host = (string) $settings['host'];
		$port = (int) $settings['port'];
		$auth = ! empty( $settings['auth'] );

		// "configured" mirrors the apply_smtp_config() bail-out
		// gates: an empty host or zero port stops the hook short
		// even when enabled, so we show that the same way here.
		$configured_notes = '';
		$configured       = true;

		if ( '' === $host ) {
			$configured       = false;
			$configured_notes = __( 'No SMTP host set.', 'perform-forms' );
		} elseif ( $port < 1 ) {
			$configured       = false;
			$configured_notes = __( 'No SMTP port set.', 'perform-forms' );
		} elseif ( $auth ) {
			$has_username = '' !== (string) $settings['username'];
			$has_password_cipher = '' !== (string) $settings['password'];
			if ( ! $has_username ) {
				$configured       = false;
				$configured_notes = __( 'Authentication is on but no username is set.', 'perform-forms' );
			} elseif ( ! $has_password_cipher ) {
				$configured       = false;
				$configured_notes = __( 'Authentication is on but no password is stored.', 'perform-forms' );
			} else {
				$plaintext = Secret::decrypt( (string) $settings['password'] );
				if ( '' === $plaintext ) {
					$configured       = false;
					$configured_notes = __( 'Stored password cannot be decrypted — most likely the wp-config.php auth salt was rotated. Re-enter your password.', 'perform-forms' );
				}
			}
		}

		$conflict = self::detect_conflict();

		// "effective" is the boolean apply_smtp_config() answers
		// when wp_mail() actually fires. Mirrors should_handle()
		// + the inline guards inside apply_smtp_config().
		$effective = ! empty( $settings['enabled'] )
			&& $configured
			&& false === $conflict;

		return [
			'transport_loaded' => true,
			'enabled'          => ! empty( $settings['enabled'] ),
			'configured'       => $configured,
			'configured_notes' => $configured_notes,
			'conflict'         => $conflict,
			'effective'        => $effective,
		];
	}
}
