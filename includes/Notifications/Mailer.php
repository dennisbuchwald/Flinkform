<?php
/**
 * Email notifications for accepted submissions.
 *
 * Subscriber on `flinkform_after_submission`. Composes an admin notification
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
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into the submission pipeline and sends notification emails.
 */
final class Mailer {

	/**
	 * Register the WordPress hooks.
	 *
	 * Two listeners on the same action — admin notification at priority 10,
	 * submitter confirmation at 11. The ordering is cosmetic (both are
	 * independent) but keeps logs predictable when both fire.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'flinkform_after_submission', [ $this, 'send_admin_notification' ], 10, 4 );
		add_action( 'flinkform_after_submission', [ $this, 'send_submitter_confirmation' ], 11, 4 );
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
		$subject  = str_replace( [ "\r", "\n" ], '', MergeTags::render( $config['subject'], $context ) );
		$body     = MergeTags::render( $config['body'], $context );
		// Strip CR/LF before anything else — defence-in-depth against header
		// injection via a merge-tagged Reply-To (is_email() + PHPMailer would
		// also reject it, but we never want a raw newline near a mail header).
		$reply_to = str_replace( [ "\r", "\n" ], '', trim( MergeTags::render( $config['reply_to'], $context ) ) );
		$reply_to = sanitize_email( $reply_to );

		$headers = [];
		if ( '' !== $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		/**
		 * Filter the outgoing notification before it hits wp_mail().
		 *
		 * Fires for both the admin notification and the submitter
		 * confirmation; the fourth argument disambiguates them so a single
		 * listener can target one branch (or both). Mirrors the wp_mail()
		 * signature plus the resolved context and the source form
		 * definition, so integrations can rewrite headers, switch the
		 * recipient, or convert the body to HTML. Returning an empty `to`
		 * or `subject` short-circuits the send.
		 *
		 * @since 0.1.0
		 *
		 * @param array{to: array<int, string>, subject: string, body: string, headers: array<int, string>} $email
		 * @param array<string, mixed>                                                                       $context
		 * @param array<string, mixed>                                                                       $form_def
		 * @param string                                                                                     $type One of 'admin' | 'submitter'.
		 */
		// Laid out BEFORE the filter, not after: an integration that rewrites
		// `body` has to see what will actually be sent, and be able to change
		// it. `text_alternative` rides along so a listener can adjust both
		// halves of the multipart rather than only the one it can see.
		[ $html, $text ] = $this->dress_admin_body(
			$body,
			$config['is_default_body'],
			$form_def,
			$clean,
			'' !== $reply_to
		);
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		$email = (array) apply_filters(
			'flinkform_email_notification',
			[
				'to'               => $to,
				'subject'          => $subject,
				'body'             => $html,
				'text_alternative' => $text,
				'headers'          => $headers,
				// File paths for wp_mail()'s attachments parameter. Empty in
				// the free core; add-ons append here (e.g. Pro attaches a
				// File Upload field's stored file to the admin notification).
				'attachments'      => [],
			],
			$context,
			$form_def,
			'admin'
		);

		$recipients = isset( $email['to'] ) && is_array( $email['to'] ) ? $email['to'] : [];
		if ( empty( $recipients ) || empty( $email['subject'] ) ) {
			return;
		}

		$this->send(
			$email,
			$recipients,
			$this->resolve_sender( $form_def, $context ),
			(string) ( $email['text_alternative'] ?? '' )
		);
	}

	/**
	 * Turn the admin body into an HTML/plain-text pair.
	 *
	 * Two paths on purpose. Our own generated body is rebuilt from the
	 * field records so it can use labels, drop empty fields and format
	 * dates — none of which survives a round trip through a merge-tagged
	 * text template. An author's own body is not rewritten; it keeps every
	 * word and only gains the same shell around it, so a form that has been
	 * customised does not silently change what it says.
	 *
	 * @param string                                                                            $body        Merge-tag-resolved body.
	 * @param bool                                                                              $is_default  Whether that body is ours.
	 * @param array{fields: array<int, array<string, mixed>>}                                   $form_def
	 * @param array<string, mixed>                                                              $clean
	 * @param bool                                                                              $has_reply_to
	 * @return array{0: string, 1: string} HTML body, plain-text alternative.
	 */
	private function dress_admin_body( string $body, bool $is_default, array $form_def, array $clean, bool $has_reply_to ): array {
		$footer = sprintf(
			/* translators: %1$s: site name, %2$s: site URL. */
			__( 'Sent by %1$s (%2$s)', 'flinkform' ),
			get_bloginfo( 'name' ),
			home_url()
		);

		if ( ! $is_default ) {
			return [ BodyBuilder::html_from_text( $body, [ 'footer' => $footer ] ), $body ];
		}

		$fields = isset( $form_def['fields'] ) && is_array( $form_def['fields'] ) ? $form_def['fields'] : [];
		$rows   = BodyBuilder::rows( $fields, $clean );

		// The submitter's name, if the form collected one, makes the opening
		// line read like a message about a person rather than a database row.
		$name  = $this->find_name( $fields, $clean );
		$intro = '' !== $name
			/* translators: %s: the name the visitor entered. */
			? sprintf( __( 'New enquiry from %s via your website', 'flinkform' ), $name )
			: __( 'New enquiry via your website', 'flinkform' );

		$parts = [
			'intro'  => $intro,
			'outro'  => $has_reply_to
				? __( 'You can reply to this email directly — your answer goes straight to the sender.', 'flinkform' )
				: '',
			'footer' => $footer,
		];

		return [ BodyBuilder::html( $rows, $parts ), BodyBuilder::text( $rows, $parts ) ];
	}

	/**
	 * Best guess at the submitter's name for the greeting line.
	 *
	 * Matches on the field's own name rather than its label, since the label
	 * is whatever the author typed and the field name is the stable key. No
	 * match simply means a neutral opening line — this is a nicety, not
	 * something worth guessing wrongly over.
	 *
	 * @param array<int, array<string, mixed>> $fields
	 * @param array<string, mixed>             $clean
	 * @return string
	 */
	private function find_name( array $fields, array $clean ): string {
		foreach ( $fields as $field ) {
			$name = (string) ( $field['name'] ?? '' );
			if ( ! preg_match( '/^(name|vorname|first[_-]?name|full[_-]?name)(_[a-z0-9]+)?$/i', $name ) ) {
				continue;
			}
			$value = $clean[ $name ] ?? '';
			if ( is_array( $value ) || '' === trim( (string) $value ) ) {
				continue;
			}
			return trim( (string) $value );
		}
		return '';
	}

	/**
	 * Send a confirmation email to the submitter.
	 *
	 * Off by default. When enabled, the configured email field is read
	 * straight out of the (sanitised) submission. If the field is empty,
	 * missing, multi-valued, or not a valid email address, the send is
	 * skipped silently — better than failing the whole pipeline because
	 * an optional confirmation hit a snag.
	 *
	 * @param int                                                                               $submission_id
	 * @param string                                                                            $form_id
	 * @param array<string, mixed>                                                              $clean
	 * @param array{attributes: array<string, mixed>, fields: array<int, array<string, mixed>>} $form_def
	 * @return void
	 */
	public function send_submitter_confirmation( int $submission_id, string $form_id, array $clean, array $form_def ): void {
		$config = $this->resolve_submitter_config( $form_def );
		if ( ! $config['enabled'] ) {
			return;
		}
		if ( '' === $config['email_field'] ) {
			return;
		}

		$raw_value = $clean[ $config['email_field'] ] ?? '';
		if ( is_array( $raw_value ) ) {
			// Multi-value fields (checkbox/multi-select) can't be a single
			// recipient — bail rather than mailing the first item silently.
			return;
		}
		$recipient = trim( (string) $raw_value );
		if ( '' === $recipient || ! is_email( $recipient ) ) {
			return;
		}

		$context = MergeTags::context( $submission_id, $form_id, $clean, $form_def );

		$subject = str_replace( [ "\r", "\n" ], '', MergeTags::render( $config['subject'], $context ) );
		$body    = MergeTags::render( $config['body'], $context );

		// Same sanitising as the admin notification's Reply-To: strip CR/LF
		// first so a merge-tagged value can never smuggle in a second header,
		// then require a well-formed address.
		$reply_to = str_replace( [ "\r", "\n" ], '', trim( MergeTags::render( $config['reply_to'], $context ) ) );
		$reply_to = sanitize_email( $reply_to );

		$headers = [];
		if ( '' !== $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		// Same shell, same words. The confirmation is often a personal note
		// the author wrote themselves, so it keeps its text exactly and only
		// gains the layout.
		$html = BodyBuilder::html_from_text(
			$body,
			[
				'footer' => sprintf(
					/* translators: %1$s: site name, %2$s: site URL. */
					__( 'Sent by %1$s (%2$s)', 'flinkform' ),
					get_bloginfo( 'name' ),
					home_url()
				),
			]
		);
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		/** This filter is documented in this file. */
		$email = (array) apply_filters(
			'flinkform_email_notification',
			[
				'to'               => [ $recipient ],
				'subject'          => $subject,
				'body'             => $html,
				'text_alternative' => $body,
				'headers'          => $headers,
				'attachments'      => [],
			],
			$context,
			$form_def,
			'submitter'
		);

		$recipients = isset( $email['to'] ) && is_array( $email['to'] ) ? $email['to'] : [];
		if ( empty( $recipients ) || empty( $email['subject'] ) ) {
			return;
		}

		$this->send(
			$email,
			$recipients,
			$this->resolve_sender( $form_def, $context ),
			(string) ( $email['text_alternative'] ?? '' )
		);
	}

	/**
	 * Hand a composed email to wp_mail(), applying the form's own sender
	 * for the duration of that one call.
	 *
	 * Why filters and not a `From:` header: wp_mail() reads a From header
	 * into `$from_email`, but then runs it through the `wp_mail_from`
	 * filter before handing it to PHPMailer. Any SMTP plugin hooked there
	 * would therefore silently overrule a per-form sender. Registering our
	 * own filter at PHP_INT_MAX for the length of the send is the only way
	 * a form-level setting reliably wins — and removing it immediately
	 * afterwards keeps every other plugin's mail untouched.
	 *
	 * @param array<string, mixed>                  $email      Composed email.
	 * @param array<int, string>                    $recipients Resolved To list.
	 * @param array{email: string, name: string}    $sender     Empty strings mean "leave WordPress alone".
	 * @return void
	 */
	private function send( array $email, array $recipients, array $sender, string $text_alternative = '' ): void {
		$from_email = static fn () => $sender['email'];
		$from_name  = static fn () => $sender['name'];

		// True multipart, not an HTML-only mail.
		//
		// wp_mail() alone can only do one or the other: a `Content-Type:
		// text/html` header swaps the body wholesale, leaving a plain-text
		// client with raw markup. Setting AltBody on the PHPMailer instance
		// is what produces multipart/alternative, so a client that will not
		// render HTML still gets a readable mail rather than a soup of
		// tags. Registered for this one send and removed straight after, so
		// no other plugin's mail is affected.
		$alt_body = static function ( $phpmailer ) use ( $text_alternative ) {
			$phpmailer->AltBody = $text_alternative; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar -- PHPMailer's own property name.
		};

		if ( '' !== $sender['email'] ) {
			add_filter( 'wp_mail_from', $from_email, PHP_INT_MAX );
		}
		if ( '' !== $sender['name'] ) {
			add_filter( 'wp_mail_from_name', $from_name, PHP_INT_MAX );
		}
		if ( '' !== $text_alternative ) {
			add_action( 'phpmailer_init', $alt_body, PHP_INT_MAX );
		}

		try {
			wp_mail(
				$recipients,
				(string) $email['subject'],
				(string) ( $email['body'] ?? '' ),
				isset( $email['headers'] ) && is_array( $email['headers'] ) ? $email['headers'] : [],
				isset( $email['attachments'] ) && is_array( $email['attachments'] ) ? $email['attachments'] : []
			);
		} finally {
			// `finally` because a mail plugin throwing mid-send must not
			// leave our sender or our AltBody bolted onto every later
			// wp_mail() call.
			if ( '' !== $sender['email'] ) {
				remove_filter( 'wp_mail_from', $from_email, PHP_INT_MAX );
			}
			if ( '' !== $sender['name'] ) {
				remove_filter( 'wp_mail_from_name', $from_name, PHP_INT_MAX );
			}
			if ( '' !== $text_alternative ) {
				remove_action( 'phpmailer_init', $alt_body, PHP_INT_MAX );
			}
		}
	}

	/**
	 * Resolve the form's sender override.
	 *
	 * Both halves are independent: a form may set only the name and keep
	 * the address WordPress or an SMTP plugin would have used.
	 *
	 * The address is deliberately NOT checked against the site's domain
	 * here. That belongs in the editor, where the author can be warned
	 * while there is still something to fix; refusing it at send time would
	 * turn a deliverability question into silently missing mail.
	 *
	 * @param array{attributes?: array<string, mixed>} $form_def
	 * @param array<string, mixed>                     $context
	 * @return array{email: string, name: string}
	 */
	private function resolve_sender( array $form_def, array $context ): array {
		$notif = $form_def['attributes']['notifications'] ?? [];
		$notif = is_array( $notif ) ? $notif : [];

		$email = isset( $notif['fromEmail'] ) ? (string) $notif['fromEmail'] : '';
		$email = str_replace( [ "\r", "\n" ], '', trim( MergeTags::render( $email, $context ) ) );
		$email = sanitize_email( $email );
		if ( '' !== $email && ! is_email( $email ) ) {
			$email = '';
		}

		$name = isset( $notif['fromName'] ) ? (string) $notif['fromName'] : '';
		$name = str_replace( [ "\r", "\n" ], '', trim( MergeTags::render( $name, $context ) ) );
		$name = sanitize_text_field( $name );

		return [
			'email' => $email,
			'name'  => $name,
		];
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
		$default_subject = __( 'New submission: {form:title}', 'flinkform' );
		$default_body    = $this->default_admin_body(
			isset( $form_def['fields'] ) && is_array( $form_def['fields'] ) ? $form_def['fields'] : []
		);

		return [
			'enabled'  => array_key_exists( 'enabled', $notif ) ? (bool) $notif['enabled'] : true,
			'to'       => isset( $notif['to'] ) && '' !== trim( (string) $notif['to'] ) ? (string) $notif['to'] : $default_to,
			'subject'  => isset( $notif['subject'] ) && '' !== trim( (string) $notif['subject'] ) ? (string) $notif['subject'] : $default_subject,
			'body'     => isset( $notif['body'] ) && '' !== trim( (string) $notif['body'] ) ? (string) $notif['body'] : $default_body,
			'reply_to' => isset( $notif['replyTo'] ) ? (string) $notif['replyTo'] : '',
			// Whether the body above is ours. Only our own gets rebuilt from
			// the field records into the laid-out HTML; an author's text is
			// wrapped but never rewritten.
			'is_default_body' => ! ( isset( $notif['body'] ) && '' !== trim( (string) $notif['body'] ) ),
		];
	}

	/**
	 * Merge the form's submitter-confirmation attributes with defaults.
	 *
	 * Unlike the admin branch, this one is off by default — opt-in matches
	 * the spec (and avoids surprising visitors with unexpected emails on a
	 * freshly-installed plugin).
	 *
	 * @param array{attributes: array<string, mixed>, fields: array<int, array<string, mixed>>} $form_def
	 * @return array{enabled: bool, email_field: string, subject: string, body: string}
	 */
	private function resolve_submitter_config( array $form_def ): array {
		$attrs = isset( $form_def['attributes'] ) && is_array( $form_def['attributes'] ) ? $form_def['attributes'] : [];
		$conf  = isset( $attrs['notifications']['submitter'] ) && is_array( $attrs['notifications']['submitter'] )
			? $attrs['notifications']['submitter']
			: [];

		$default_subject = __( 'We received your submission', 'flinkform' );
		$default_body    = $this->default_submitter_body(
			isset( $form_def['fields'] ) && is_array( $form_def['fields'] ) ? $form_def['fields'] : []
		);

		return [
			'enabled'     => ! empty( $conf['enabled'] ),
			'email_field' => isset( $conf['emailField'] ) ? (string) $conf['emailField'] : '',
			'subject'     => isset( $conf['subject'] ) && '' !== trim( (string) $conf['subject'] ) ? (string) $conf['subject'] : $default_subject,
			'body'        => isset( $conf['body'] ) && '' !== trim( (string) $conf['body'] ) ? (string) $conf['body'] : $default_body,
			// Where a reply from the visitor should land. Without it the
			// confirmation answers back to whatever address the site sends
			// from, which is usually a mailbox nobody reads.
			'reply_to'    => isset( $conf['replyTo'] ) ? (string) $conf['replyTo'] : '',
		];
	}

	/**
	 * Build the default admin-notification body — one line per field.
	 *
	 * The body is a template (merge tags resolved later) so an author who
	 * customises the subject but keeps the default body still gets the
	 * dynamic values they expect.
	 *
	 * @param array<int, array<string, mixed>> $fields
	 * @return string
	 */
	private function default_admin_body( array $fields ): string {
		$lines   = [];
		$lines[] = __( 'A new submission was received:', 'flinkform' );
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
	 * Build the default submitter-confirmation body.
	 *
	 * Includes the submitted values so the recipient has a paper trail of
	 * what they sent without us having to surface a separate "your copy"
	 * receipt feature. An author who wants a shorter "thanks, we'll be in
	 * touch" can overwrite the body in the Inspector.
	 *
	 * @param array<int, array<string, mixed>> $fields
	 * @return string
	 */
	private function default_submitter_body( array $fields ): string {
		$lines   = [];
		$lines[] = __( 'Hi,', 'flinkform' );
		$lines[] = '';
		$lines[] = __( 'Thanks for your submission. We received the following and will be in touch shortly:', 'flinkform' );
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
