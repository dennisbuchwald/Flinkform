<?php
/**
 * Form submission handler.
 *
 * Hooks `admin-post.php?action=perform_submit` (both logged-in and nopriv
 * variants) and walks every incoming submission through the same gauntlet:
 * nonce → honeypot → time-check → field validation → persist → redirect.
 *
 * Errors and submitted values from a failed attempt are flashed via short-
 * lived transients so the form's render.php can re-populate the form on the
 * next page load.
 *
 * Also exposes static accessors used by field render.php files to read the
 * flashed state for the form currently being rendered.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Submissions;

use PerForm\Forms\Locator;

defined( 'ABSPATH' ) || exit;

/**
 * Processes POSTs to admin-post.php?action=perform_submit.
 */
final class Handler {

	private const ACTION             = 'perform_submit';
	private const NONCE_FIELD        = '_perform_nonce';
	private const HONEYPOT_FIELD     = 'perform_hp';
	private const TIMESTAMP_FIELD    = 'perform_ts';
	private const MIN_FILL_SECONDS   = 2;
	private const FLASH_TTL_SECONDS  = 60;
	private const FLASH_COOKIE_NAME  = 'perform_flash';

	/**
	 * Errors for the form currently being rendered.
	 *
	 * @var array<string, string>
	 */
	private static array $current_errors = [];

	/**
	 * Submitted values for the form currently being rendered.
	 *
	 * @var array<string, string>
	 */
	private static array $current_values = [];

	private Locator $locator;
	private Repository $repository;

	/**
	 * Inject dependencies.
	 *
	 * @param Locator    $locator    Resolves form UUIDs to definitions.
	 * @param Repository $repository Persists accepted submissions.
	 */
	public function __construct( Locator $locator, Repository $repository ) {
		$this->locator    = $locator;
		$this->repository = $repository;
	}

	/**
	 * Register the WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Main entry point — runs the full submission pipeline.
	 *
	 * @return void
	 */
	public function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce is checked below explicitly.
		$form_id = isset( $_POST['perform_form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['perform_form_id'] ) ) : '';
		$post_id = isset( $_POST['perform_post_id'] ) ? absint( wp_unslash( $_POST['perform_post_id'] ) ) : 0;
		// phpcs:enable

		if ( '' === $form_id || 0 === $post_id ) {
			$this->silent_reject();
		}

		// Nonce — the only check whose failure we surface as 403, because
		// it usually means a real human ran into a caching/session issue.
		if ( ! check_admin_referer( 'perform_submit_' . $form_id, self::NONCE_FIELD ) ) {
			wp_die(
				esc_html__( 'Security check failed. Please go back and try again.', 'perform-forms' ),
				esc_html__( 'Submission rejected', 'perform-forms' ),
				[ 'response' => 403 ]
			);
		}

		// Honeypot — bots fill hidden fields; humans don't see them.
		// On hit we redirect to "success" so the bot believes it won.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already validated above.
		$honeypot = isset( $_POST[ self::HONEYPOT_FIELD ] ) ? (string) wp_unslash( $_POST[ self::HONEYPOT_FIELD ] ) : '';
		if ( '' !== trim( $honeypot ) ) {
			$this->redirect_success( $post_id, $form_id );
		}

		// Time-check — render-to-submit faster than humans can read.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already validated above.
		$ts_raw  = isset( $_POST[ self::TIMESTAMP_FIELD ] ) ? (string) wp_unslash( $_POST[ self::TIMESTAMP_FIELD ] ) : '';
		$ts_decoded = (int) base64_decode( $ts_raw, true );
		if ( $ts_decoded <= 0 || ( time() - $ts_decoded ) < self::MIN_FILL_SECONDS ) {
			$this->silent_reject();
		}

		// Locate the authoritative form definition in the source post.
		$definition = $this->locator->locate( $post_id, $form_id );
		if ( null === $definition ) {
			$this->silent_reject();
		}

		// Sanitize + validate user input against that definition.
		[ $clean, $errors ] = $this->validate( $definition['fields'] );

		if ( ! empty( $errors ) ) {
			$this->flash( $form_id, $errors, $clean );
			$this->redirect_error( $post_id, $form_id );
		}

		// Persist. A storage failure surfaces as a generic form-level error
		// rather than a silent drop — the user deserves to know.
		$result = $this->repository->save( $form_id, $clean );
		if ( false === $result ) {
			$this->flash(
				$form_id,
				[ '_form' => __( 'Sorry, something went wrong saving your message. Please try again.', 'perform-forms' ) ],
				$clean
			);
			$this->redirect_error( $post_id, $form_id );
		}

		$this->redirect_success( $post_id, $form_id );
	}

	/**
	 * Sanitize and validate POSTed field values against the form definition.
	 *
	 * @param array<int, array{name: string, type: string, label: string, required: bool}> $fields
	 * @return array{0: array<string, string>, 1: array<string, string>} [clean, errors]
	 */
	private function validate( array $fields ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already validated by caller.
		$raw = isset( $_POST['perform_field'] ) && is_array( $_POST['perform_field'] ) ? wp_unslash( $_POST['perform_field'] ) : [];

		$clean  = [];
		$errors = [];

		foreach ( $fields as $field ) {
			$name     = $field['name'];
			$type     = $field['type'];
			$required = $field['required'];
			$value    = isset( $raw[ $name ] ) ? (string) $raw[ $name ] : '';

			// Type-specific sanitisation.
			$sanitised = match ( $type ) {
				'email'    => sanitize_email( $value ),
				'textarea' => sanitize_textarea_field( $value ),
				default    => sanitize_text_field( $value ),
			};

			// Required-check after sanitisation: "   " trimmed away is empty.
			if ( $required && '' === trim( $sanitised ) ) {
				$errors[ $name ] = sprintf(
					/* translators: %s: field label */
					__( '%s is required.', 'perform-forms' ),
					$field['label']
				);
				$clean[ $name ] = $sanitised;
				continue;
			}

			// Type-specific validity check (after the required gate so
			// an empty optional email field doesn't trigger "invalid").
			if ( 'email' === $type && '' !== $sanitised && ! is_email( $sanitised ) ) {
				$errors[ $name ] = sprintf(
					/* translators: %s: field label */
					__( '%s must be a valid email address.', 'perform-forms' ),
					$field['label']
				);
			}

			$clean[ $name ] = $sanitised;
		}

		return [ $clean, $errors ];
	}

	/**
	 * Persist errors + values for the next render of this form.
	 *
	 * Keyed by a per-visitor cookie token so flashes don't leak between
	 * concurrent users on a cached page.
	 *
	 * @param string                $form_id
	 * @param array<string, string> $errors
	 * @param array<string, string> $values
	 * @return void
	 */
	private function flash( string $form_id, array $errors, array $values ): void {
		$token = $this->flash_token( true );

		set_transient(
			$this->flash_key( $token, $form_id ),
			[
				'errors' => $errors,
				'values' => $values,
			],
			self::FLASH_TTL_SECONDS
		);
	}

	/**
	 * Build (and return) the flash cookie token, creating one if missing.
	 *
	 * @param bool $create Whether to create + set the cookie when absent.
	 * @return string
	 */
	private function flash_token( bool $create ): string {
		if ( isset( $_COOKIE[ self::FLASH_COOKIE_NAME ] ) ) {
			return sanitize_key( wp_unslash( $_COOKIE[ self::FLASH_COOKIE_NAME ] ) );
		}

		if ( ! $create ) {
			return '';
		}

		$token = wp_generate_password( 24, false, false );
		setcookie(
			self::FLASH_COOKIE_NAME,
			$token,
			[
				'expires'  => time() + self::FLASH_TTL_SECONDS,
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN ?: '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);

		return $token;
	}

	/**
	 * Compose the transient key for a form/visitor pair.
	 *
	 * @param string $token
	 * @param string $form_id
	 * @return string
	 */
	private function flash_key( string $token, string $form_id ): string {
		return 'perform_flash_' . md5( $token . '|' . $form_id );
	}

	/**
	 * Reject silently — used for spam/honeypot hits + nonsense requests.
	 *
	 * @return never
	 */
	private function silent_reject(): void {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/**
	 * Redirect to the source post with success status.
	 *
	 * @param int    $post_id
	 * @param string $form_id
	 * @return never
	 */
	private function redirect_success( int $post_id, string $form_id ): void {
		$url = add_query_arg(
			[
				'perform_status' => 'success',
				'perform_form'   => $form_id,
			],
			get_permalink( $post_id ) ?: home_url( '/' )
		);
		wp_safe_redirect( $url . '#perform-form-' . $form_id );
		exit;
	}

	/**
	 * Redirect to the source post with error status.
	 *
	 * @param int    $post_id
	 * @param string $form_id
	 * @return never
	 */
	private function redirect_error( int $post_id, string $form_id ): void {
		$url = add_query_arg(
			[
				'perform_status' => 'error',
				'perform_form'   => $form_id,
			],
			get_permalink( $post_id ) ?: home_url( '/' )
		);
		wp_safe_redirect( $url . '#perform-form-' . $form_id );
		exit;
	}

	/* --------------------------------------------------------------------
	 * Static render-state accessors.
	 *
	 * The form container render.php calls set_render_state() with the
	 * flashed values for the form it is about to render; nested field
	 * render.php files read individual values/errors through the helpers
	 * below. clear_render_state() is invoked after the form is rendered
	 * so a second form on the same page starts with a clean slate.
	 * ------------------------------------------------------------------ */

	/**
	 * Pull flashed errors for a form (consuming the transient).
	 *
	 * @param string $form_id
	 * @return array<string, string>
	 */
	public static function flash_errors_for( string $form_id ): array {
		$flash = self::consume_flash( $form_id );
		return $flash['errors'] ?? [];
	}

	/**
	 * Pull flashed values for a form (consuming the transient).
	 *
	 * Note: errors_for() and values_for() both call consume_flash(), but
	 * the inner static cache makes the second call a no-op — the transient
	 * is read at most once per request.
	 *
	 * @param string $form_id
	 * @return array<string, string>
	 */
	public static function flash_values_for( string $form_id ): array {
		$flash = self::consume_flash( $form_id );
		return $flash['values'] ?? [];
	}

	/**
	 * Read + delete the flashed payload for a form (one-shot per request).
	 *
	 * @param string $form_id
	 * @return array{errors?: array<string, string>, values?: array<string, string>}
	 */
	private static function consume_flash( string $form_id ): array {
		static $consumed = [];
		if ( isset( $consumed[ $form_id ] ) ) {
			return $consumed[ $form_id ];
		}

		$token = isset( $_COOKIE[ self::FLASH_COOKIE_NAME ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::FLASH_COOKIE_NAME ] ) ) : '';
		if ( '' === $token ) {
			$consumed[ $form_id ] = [];
			return [];
		}

		$key  = 'perform_flash_' . md5( $token . '|' . $form_id );
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			$consumed[ $form_id ] = [];
			return [];
		}

		delete_transient( $key );
		$consumed[ $form_id ] = $data;
		return $data;
	}

	/**
	 * Mark the start of a form render — fields will read this state.
	 *
	 * @param string                $form_id
	 * @param array<string, string> $errors
	 * @param array<string, string> $values
	 * @return void
	 */
	public static function set_render_state( string $form_id, array $errors, array $values ): void {
		self::$current_errors = $errors;
		self::$current_values = $values;
		unset( $form_id );
	}

	/**
	 * Reset the render state after a form finishes rendering.
	 *
	 * @return void
	 */
	public static function clear_render_state(): void {
		self::$current_errors = [];
		self::$current_values = [];
	}

	/**
	 * Read the flashed value for a single field, if any.
	 *
	 * @param string $field_name
	 * @return string
	 */
	public static function flash_value( string $field_name ): string {
		return isset( self::$current_values[ $field_name ] ) ? (string) self::$current_values[ $field_name ] : '';
	}

	/**
	 * Read the flashed error for a single field, if any.
	 *
	 * @param string $field_name
	 * @return string
	 */
	public static function flash_error( string $field_name ): string {
		return isset( self::$current_errors[ $field_name ] ) ? (string) self::$current_errors[ $field_name ] : '';
	}
}
