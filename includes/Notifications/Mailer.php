<?php
/**
 * Email notifications for accepted submissions.
 *
 * Subscriber on `perform_after_submission`. Composes an admin notification
 * and dispatches it via `wp_mail()` — no SMTP module, no HTML body, no
 * configuration UI yet. Slice 3a deliberately ships with working defaults:
 * the moment the plugin update is uploaded, the site admin starts
 * receiving every submission at `admin_email` with an auto-generated body.
 *
 * Slice 3b will introduce the Form-Block Inspector that writes
 * `attributes.notifications.admin.*`. This class reads those attributes
 * if present and falls back to the defaults below for every missing key,
 * so existing forms keep working without re-saving the post.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into the submission pipeline and sends notification emails.
 */
final class Mailer {

	/**
	 * Register the WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'perform_after_submission', [ $this, 'send_admin_notification' ], 10, 4 );
	}

	/**
	 * Compose and dispatch the admin notification.
	 *
	 * @param int                                                                               $submission_id
	 * @param string                                                                            $form_id
	 * @param array<string, mixed>                                                              $clean
	 * @param array{attributes: array<string, mixed>, fields: array<int, array<string, mixed>>} $form_def
	 * @return void
	 */
	public function send_admin_notification( int $submission_id, string $form_id, array $clean, array $form_def ): void {
		$config = $this->resolve_admin_config( $form_def );
		if ( ! $config['enabled'] ) {
			return;
		}

		$context = MergeTags::context( $submission_id, $form_id, $clean, $form_def );

		$to       = $this->resolve_recipients( MergeTags::render( $config['to'], $context ) );
		$subject  = MergeTags::render( $config['subject'], $context );
		$body     = MergeTags::render( $config['body'], $context );
		$reply_to = trim( MergeTags::render( $config['reply_to'], $context ) );

		$headers = [];
		if ( '' !== $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		/**
		 * Filter the outgoing admin notification before it hits wp_mail().
		 *
		 * Mirrors the wp_mail() signature plus the resolved context and the
		 * source form definition, so integrations can rewrite headers,
		 * switch the recipient, or convert the body to HTML. Returning an
		 * empty `to` or `subject` short-circuits the send.
		 *
		 * @since 0.1.0
		 *
		 * @param array{to: array<int, string>, subject: string, body: string, headers: array<int, string>} $email
		 * @param array<string, mixed>                                                                       $context
		 * @param array<string, mixed>                                                                       $form_def
		 */
		$email = (array) apply_filters(
			'perform_email_notification',
			[
				'to'      => $to,
				'subject' => $subject,
				'body'    => $body,
				'headers' => $headers,
			],
			$context,
			$form_def
		);

		$recipients = isset( $email['to'] ) && is_array( $email['to'] ) ? $email['to'] : [];
		if ( empty( $recipients ) || empty( $email['subject'] ) ) {
			return;
		}

		wp_mail(
			$recipients,
			(string) $email['subject'],
			(string) ( $email['body'] ?? '' ),
			isset( $email['headers'] ) && is_array( $email['headers'] ) ? $email['headers'] : []
		);
	}

	/**
	 * Merge the form's notification attributes with safe defaults.
	 *
	 * Every key falls back independently — a half-configured form (e.g. a
	 * custom To but the default subject) still produces a valid email.
	 *
	 * @param array{attributes: array<string, mixed>, fields: array<int, array<string, mixed>>} $form_def
	 * @return array{enabled: bool, to: string, subject: string, body: string, reply_to: string}
	 */
	private function resolve_admin_config( array $form_def ): array {
		$attrs = isset( $form_def['attributes'] ) && is_array( $form_def['attributes'] ) ? $form_def['attributes'] : [];
		$notif = isset( $attrs['notifications']['admin'] ) && is_array( $attrs['notifications']['admin'] )
			? $attrs['notifications']['admin']
			: [];

		$default_to      = (string) get_option( 'admin_email', '' );
		$default_subject = __( 'New submission: {form:title}', 'perform-forms' );
		$default_body    = $this->default_body(
			isset( $form_def['fields'] ) && is_array( $form_def['fields'] ) ? $form_def['fields'] : []
		);

		return [
			'enabled'  => array_key_exists( 'enabled', $notif ) ? (bool) $notif['enabled'] : true,
			'to'       => isset( $notif['to'] ) && '' !== trim( (string) $notif['to'] ) ? (string) $notif['to'] : $default_to,
			'subject'  => isset( $notif['subject'] ) && '' !== trim( (string) $notif['subject'] ) ? (string) $notif['subject'] : $default_subject,
			'body'     => isset( $notif['body'] ) && '' !== trim( (string) $notif['body'] ) ? (string) $notif['body'] : $default_body,
			'reply_to' => isset( $notif['replyTo'] ) ? (string) $notif['replyTo'] : '',
		];
	}

	/**
	 * Build the default plaintext body — one line per field, "Label: value".
	 *
	 * The body is a template (merge tags resolved later) so an author who
	 * customises the subject but keeps the default body still gets the
	 * dynamic values they expect.
	 *
	 * @param array<int, array<string, mixed>> $fields
	 * @return string
	 */
	private function default_body( array $fields ): string {
		$lines   = [];
		$lines[] = __( 'A new submission was received:', 'perform-forms' );
		$lines[] = '';

		foreach ( $fields as $field ) {
			$name = (string) ( $field['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$label   = (string) ( $field['label'] ?? $name );
			$lines[] = $label . ': {field:' . $name . '}';
		}

		$lines[] = '';
		$lines[] = '--';
		$lines[] = '{site:name} ({site:url})';

		return implode( "\n", $lines );
	}

	/**
	 * Split a comma/whitespace/semicolon-separated string into valid emails.
	 *
	 * Resolved-tag output may itself be a list (e.g. a To configured as
	 * `{field:cc}` where the field contains comma-separated addresses), so
	 * we always tokenise after merge-tag rendering. Invalid tokens are
	 * dropped silently — the alternative is dispatching to nobody, which is
	 * worse than dispatching to the valid subset.
	 *
	 * @param string $raw
	 * @return array<int, string>
	 */
	private function resolve_recipients( string $raw ): array {
		$parts = preg_split( '/[\s,;]+/', trim( $raw ) );
		if ( ! is_array( $parts ) ) {
			return [];
		}

		$valid = [];
		foreach ( $parts as $candidate ) {
			$email = trim( (string) $candidate );
			if ( '' !== $email && is_email( $email ) ) {
				$valid[] = $email;
			}
		}

		return array_values( array_unique( $valid ) );
	}
}
