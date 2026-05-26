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

use PerForm\Fields\HiddenResolver;
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
	 * Values are either strings (scalar fields) or arrays of strings
	 * (multi-value fields). Field render.php files normalise as needed.
	 *
	 * @var array<string, mixed>
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

		// Built-in spam challenge verify (Phase B-a). Sits between
		// the time-check and field validation: a missing or invalid
		// challenge token is silent-rejected the same way honeypot
		// hits are. The Guard façade reads the form's spamProtection
		// attribute to decide whether to run a check at all — so a
		// form explicitly opted out via 'none' falls through here
		// instantly. Honeypot + time-check from Phase 1 still apply
		// even when spam protection is 'none' (defense in depth).
		$form_attrs = isset( $definition['attributes'] ) && is_array( $definition['attributes'] ) ? $definition['attributes'] : [];
		if ( ! \PerForm\Spam\Guard::verify_submission( $form_id, $form_attrs ) ) {
			$this->silent_reject();
		}

		// Sanitize + validate user input against that definition.
		[ $clean, $errors ] = $this->validate( $definition['fields'] );

		// Conditional logic — server-side re-evaluation (Phase 7b).
		// Walks every field's `conditionalLogic` rule set against the
		// sanitised values we just collected; fields whose rules say
		// "don't show" get stripped from $clean and have their
		// validation errors dropped so a hidden-required field doesn't
		// block submission. The same evaluator runs in view.js on
		// every input change for the live UX — this server pass is the
		// authoritative one, so DOM-manipulated visible fields can't
		// smuggle data through.
		$skipped_steps = $this->resolve_skipped_steps( $definition['steps'] ?? [], $clean );
		$hidden_fields = $this->resolve_hidden_fields( $definition['fields'], $clean, $skipped_steps );
		if ( ! empty( $hidden_fields ) ) {
			foreach ( $hidden_fields as $hidden_name ) {
				unset( $clean[ $hidden_name ], $errors[ $hidden_name ] );
			}
		}

		// Submit-button condition (Phase 7d). Server-side enforcement
		// is the safety net for JS-disabled visitors who don't see the
		// button disabled in the first place — without it, a no-JS
		// visitor could submit a form whose submit-condition the
		// author meant to gate (e.g. "I agree to terms" never ticked).
		// Evaluated against $clean post-strip so a hidden field can't
		// satisfy the condition.
		$submit_condition = isset( $definition['attributes']['submitCondition'] ) && is_array( $definition['attributes']['submitCondition'] )
			? $definition['attributes']['submitCondition']
			: [];
		if ( ! empty( $submit_condition['enabled'] ) && ! empty( $submit_condition['rules'] ) ) {
			$evaluator = new \PerForm\Conditions\RuleEvaluator();
			if ( ! $evaluator->should_show( $submit_condition, $clean ) ) {
				$errors['_form'] = __( 'Please complete the required selection before submitting.', 'perform-forms' );
			}
		}

		if ( ! empty( $errors ) ) {
			$this->flash( $form_id, $errors, $clean );
			$this->redirect_error( $post_id, $form_id );
		}

		/**
		 * Fires after validation passes, before the submission is persisted.
		 *
		 * Pure action hook — no return value, the submit cannot be cancelled
		 * from here. Use it to log, enrich a $_SERVER-derived context, or
		 * fire pre-save side effects. Listeners that genuinely need to abort
		 * must throw an exception or call wp_die() themselves; the handler
		 * will not check a flag.
		 *
		 * Spam-rejected submissions (honeypot, time-check, missing nonce)
		 * never reach this hook.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $form_id UUID of the form.
		 * @param array<string, mixed> $clean   Sanitised, validated values keyed by field name.
		 */
		do_action( 'perform_before_submission', $form_id, $clean );

		// Compose the self-contained payload. Storing labels and types
		// alongside the values means each submission stays readable in the
		// admin even if the source form is later edited or deleted. The
		// form_title snapshot keeps history-friendly: a later rename of
		// the form doesn't retroactively change what this row says.
		$form_attrs = isset( $definition['attributes'] ) && is_array( $definition['attributes'] ) ? $definition['attributes'] : [];
		$form_title = isset( $form_attrs['title'] ) && is_string( $form_attrs['title'] ) ? trim( $form_attrs['title'] ) : '';

		$payload = [
			'fields' => $this->compose_field_payload( $definition['fields'], $clean ),
			'_meta'  => [
				'post_id'    => $post_id,
				'post_url'   => get_permalink( $post_id ) ?: '',
				'form_title' => $form_title,
			],
		];

		// Persist. A storage failure surfaces as a generic form-level error
		// rather than a silent drop — the user deserves to know.
		$result = $this->repository->save( $form_id, $payload );
		if ( false === $result ) {
			$this->flash(
				$form_id,
				[ '_form' => __( 'Sorry, something went wrong saving your message. Please try again.', 'perform-forms' ) ],
				$clean
			);
			$this->redirect_error( $post_id, $form_id );
		}

		$submission_id = (int) $result;

		/**
		 * Fires after a submission has been persisted successfully.
		 *
		 * The primary integration point for notifications, webhooks, CRM
		 * sync, and anything else that should happen exactly once per
		 * accepted submission. Spam-rejected submissions never reach this
		 * hook (see perform_before_submission for the same guarantee).
		 *
		 * Pure action hook — listeners cannot cancel the success redirect.
		 * The submission row is already committed by the time this fires,
		 * so even a throwing listener leaves the row in place.
		 *
		 * @since 0.1.0
		 *
		 * @param int                  $submission_id Newly inserted row ID.
		 * @param string               $form_id       UUID of the form.
		 * @param array<string, mixed> $clean         Sanitised values keyed by field name.
		 * @param array{attributes: array<string, mixed>, fields: array<int, array<string, mixed>>} $form_def Authoritative form definition from the source post.
		 */
		do_action( 'perform_after_submission', $submission_id, $form_id, $clean, $definition );

		$this->redirect_success( $post_id, $form_id );
	}

	/**
	 * Combine the form's field definition with the user's clean input into
	 * the persisted "fields" array. Multi-value fields preserve their
	 * array shape in `value`; everything else stays a string.
	 *
	 * @param array<int, array<string, mixed>> $fields
	 * @param array<string, mixed>             $clean
	 * @return array<int, array<string, mixed>>
	 */
	private function compose_field_payload( array $fields, array $clean ): array {
		$payload = [];
		foreach ( $fields as $field ) {
			$name = (string) $field['name'];
			// Fields that conditional logic stripped from $clean don't
			// belong in the persisted payload either — the submission
			// should reflect what was actually submitted, not the
			// shape of the form template.
			if ( ! array_key_exists( $name, $clean ) ) {
				continue;
			}
			$value = $clean[ $name ];

			$payload[] = [
				'name'  => $name,
				'label' => (string) ( $field['label'] ?? $name ),
				'type'  => (string) ( $field['type'] ?? 'text' ),
				'value' => is_array( $value ) ? array_values( array_map( 'strval', $value ) ) : (string) $value,
			];
		}
		return $payload;
	}

	/**
	 * Walk the form's field list and return the names of fields whose
	 * conditional-logic rules say "don't show". Hidden fields will be
	 * stripped from $clean before persistence + validation-error
	 * surfacing, so a hidden required field doesn't block submission
	 * and a hidden data field doesn't leak into the saved row.
	 *
	 * @param array<int, array<string, mixed>> $fields Field records from the Locator.
	 * @param array<string, mixed>             $clean  Sanitised values keyed by field name.
	 * @return array<int, string> Field names that should be treated as hidden.
	 */
	private function resolve_hidden_fields( array $fields, array $clean, array $skipped_steps = [] ): array {
		$evaluator    = new \PerForm\Conditions\RuleEvaluator();
		$hidden       = [];
		$skipped_set  = array_flip( array_map( 'intval', $skipped_steps ) );

		foreach ( $fields as $field ) {
			$field_step = isset( $field['step'] ) ? (int) $field['step'] : 0;

			// Field lives inside a step the page-break condition skipped —
			// drop it irrespective of the field's own rule set.
			if ( isset( $skipped_set[ $field_step ] ) ) {
				$hidden[] = (string) $field['name'];
				continue;
			}

			$rule_set = isset( $field['conditionalLogic'] ) && is_array( $field['conditionalLogic'] )
				? $field['conditionalLogic']
				: [];
			if ( empty( $rule_set ) ) {
				continue;
			}
			if ( ! $evaluator->should_show( $rule_set, $clean ) ) {
				$hidden[] = (string) $field['name'];
			}
		}

		return $hidden;
	}

	/**
	 * Walk the form's step list and return the indices of steps whose
	 * page-break conditional-logic rules say "skip". Step 0 has no
	 * opening page-break and therefore no rules of its own — it never
	 * appears in the skipped set.
	 *
	 * @param array<int, array<string, mixed>> $steps Step records from Locator::collect_steps.
	 * @param array<string, mixed>             $clean Sanitised values keyed by field name.
	 * @return array<int, int> Step indices that should be treated as skipped.
	 */
	private function resolve_skipped_steps( array $steps, array $clean ): array {
		$evaluator = new \PerForm\Conditions\RuleEvaluator();
		$skipped   = [];

		foreach ( $steps as $step ) {
			$rule_set = isset( $step['conditionalLogic'] ) && is_array( $step['conditionalLogic'] )
				? $step['conditionalLogic']
				: [];
			if ( empty( $rule_set ) ) {
				continue;
			}
			if ( ! $evaluator->should_show( $rule_set, $clean ) ) {
				$skipped[] = (int) ( $step['index'] ?? 0 );
			}
		}

		return $skipped;
	}

	/**
	 * Sanitize and validate POSTed field values against the form definition.
	 *
	 * Scalar fields end up as strings in $clean; multi-value fields
	 * (checkbox group, multi-select) as arrays of strings. The handler
	 * persists whatever shape lands here, so the admin renders need to
	 * cope with both.
	 *
	 * @param array<int, array<string, mixed>> $fields Field definitions from the Locator.
	 * @return array{0: array<string, mixed>, 1: array<string, string>} [clean, errors]
	 */
	private function validate( array $fields ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already validated by caller.
		$raw = isset( $_POST['perform_field'] ) && is_array( $_POST['perform_field'] ) ? wp_unslash( $_POST['perform_field'] ) : [];

		$clean  = [];
		$errors = [];

		foreach ( $fields as $field ) {
			$name     = (string) $field['name'];
			$type     = (string) $field['type'];
			$required = ! empty( $field['required'] );
			$label    = (string) ( $field['label'] ?? $name );
			$incoming = $raw[ $name ] ?? '';

			// Hidden fields are computed server-side regardless of what
			// the visitor sent — the source of truth is the form def.
			if ( 'hidden' === $type ) {
				$clean[ $name ] = HiddenResolver::resolve(
					(string) ( $field['valueSource'] ?? 'static' ),
					(string) ( $field['staticValue'] ?? '' )
				);
				continue;
			}

			$sanitised = $this->sanitise( $type, $incoming, $field );

			if ( $required && $this->is_empty( $sanitised ) ) {
				$errors[ $name ] = 'toggle' === $type
					? sprintf(
						/* translators: %s: field label */
						__( '%s must be checked.', 'perform-forms' ),
						$label
					)
					: sprintf(
						/* translators: %s: field label */
						__( '%s is required.', 'perform-forms' ),
						$label
					);
				$clean[ $name ] = $sanitised;
				continue;
			}

			// Type-specific validity checks (after the required gate so
			// an empty optional field doesn't trigger "invalid").
			$type_error = $this->validate_type( $type, $sanitised, $field );
			if ( '' !== $type_error ) {
				$errors[ $name ] = sprintf( $type_error, $label );
			}

			$clean[ $name ] = $sanitised;
		}

		return [ $clean, $errors ];
	}

	/**
	 * Type-specific sanitisation.
	 *
	 * @param string               $type
	 * @param mixed                $value Raw POST value (string or array).
	 * @param array<string, mixed> $field Field definition.
	 * @return mixed Sanitised value, shape preserved.
	 */
	private function sanitise( string $type, $value, array $field ) {
		switch ( $type ) {
			case 'email':
				return sanitize_email( (string) $value );
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'number':
				$str = trim( (string) $value );
				return '' === $str ? '' : $str;
			case 'toggle':
				return '1' === (string) $value ? '1' : '';
			case 'radio':
				return sanitize_text_field( (string) $value );
			case 'select':
				if ( ! empty( $field['multiple'] ) ) {
					$arr = is_array( $value ) ? $value : ( '' === (string) $value ? [] : [ $value ] );
					return array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $arr ) ), static fn( $v ): bool => '' !== $v ) );
				}
				return sanitize_text_field( (string) $value );
			case 'checkbox':
				$arr = is_array( $value ) ? $value : ( '' === (string) $value ? [] : [ $value ] );
				return array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $arr ) ), static fn( $v ): bool => '' !== $v ) );
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Decide whether a sanitised value should count as "empty" for the
	 * required-check. Arrays count as empty when they have no items.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private function is_empty( $value ): bool {
		if ( is_array( $value ) ) {
			return empty( $value );
		}
		return '' === trim( (string) $value );
	}

	/**
	 * Run the type-specific validity check on an already-sanitised value.
	 * Returns an sprintf-ready error template (with one %s for the label)
	 * or an empty string when the value is acceptable.
	 *
	 * @param string               $type
	 * @param mixed                $value
	 * @param array<string, mixed> $field
	 * @return string
	 */
	private function validate_type( string $type, $value, array $field ): string {
		switch ( $type ) {
			case 'email':
				if ( '' !== (string) $value && ! is_email( (string) $value ) ) {
					/* translators: %s: field label */
					return __( '%s must be a valid email address.', 'perform-forms' );
				}
				return '';
			case 'number':
				$str = (string) $value;
				if ( '' === $str ) {
					return '';
				}
				if ( ! is_numeric( $str ) ) {
					/* translators: %s: field label */
					return __( '%s must be a number.', 'perform-forms' );
				}
				$num = (float) $str;
				$min = isset( $field['min'] ) && '' !== $field['min'] ? (float) $field['min'] : null;
				$max = isset( $field['max'] ) && '' !== $field['max'] ? (float) $field['max'] : null;
				if ( null !== $min && $num < $min ) {
					/* translators: %s: field label */
					return __( '%s is below the minimum.', 'perform-forms' );
				}
				if ( null !== $max && $num > $max ) {
					/* translators: %s: field label */
					return __( '%s is above the maximum.', 'perform-forms' );
				}
				return '';
			case 'select':
			case 'radio':
			case 'checkbox':
				$allowed = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : [];
				if ( empty( $allowed ) ) {
					return '';
				}
				$values = is_array( $value ) ? $value : ( '' === (string) $value ? [] : [ (string) $value ] );
				foreach ( $values as $v ) {
					if ( ! in_array( (string) $v, $allowed, true ) ) {
						/* translators: %s: field label */
						return __( '%s contains an invalid choice.', 'perform-forms' );
					}
				}
				return '';
		}
		return '';
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

		// Lowercase: sanitize_key() lowercases on read, so the original token
		// must already be lowercase or the read-back token won't match the
		// transient key we computed at write time.
		$token = strtolower( wp_generate_password( 24, false, false ) );
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
	 * @param array<string, mixed>  $values Mixed: strings or arrays of strings.
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
	 * Returns either a string (scalar fields) or an array of strings
	 * (multi-value fields like checkbox group / multi-select).
	 *
	 * @param string $field_name
	 * @return string|array<int, string>
	 */
	public static function flash_value( string $field_name ) {
		if ( ! isset( self::$current_values[ $field_name ] ) ) {
			return '';
		}
		$value = self::$current_values[ $field_name ];
		return is_array( $value ) ? array_values( array_map( 'strval', $value ) ) : (string) $value;
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
