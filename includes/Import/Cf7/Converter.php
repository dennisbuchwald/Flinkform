<?php
/**
 * Turns one Contact Form 7 form into a Flinkform block tree plus a list of
 * notes for the preview (1.15.0). A translator, not a runtime adapter:
 * after the import nothing in Flinkform depends on CF7.
 *
 * Pure PHP apart from __()/sprintf, tested in tests/cf7-import-test.php.
 * Mapping table and reasoning: FLINKFORM_CF7_IMPORT.md (checked against
 * the CF7 6.1.7 source, see the section at its end).
 *
 * @package Flinkform
 * @since 1.15.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Import\Cf7;

defined( 'ABSPATH' ) || exit;

/**
 * CF7 form → Flinkform form.
 */
final class Converter {

	/** Note levels, worst last. Drive the traffic light. */
	public const INFO = 'info';
	public const CHECK = 'check';
	public const LOST = 'lost';

	/** Tags that only exist for CF7's own machinery: dropped quietly (info). */
	private const DROP_QUIETLY = [ 'recaptcha', 'turnstile', 'quiz', 'captchac', 'captchar', 'response', 'count', 'reflection', 'output', 'stripe' ];

	/** Autofill tokens the Flinkform text field accepts. */
	private const AUTOCOMPLETE = [ 'name', 'given-name', 'family-name', 'organization', 'organization-title', 'street-address', 'postal-code', 'address-level2', 'country-name', 'off' ];

	/** @var array<int, array{level: string, text: string}> */
	private array $notes = [];

	/** @var array<string, bool> Field names that had pipes (mail tags then mean the value). */
	private array $piped = [];

	/**
	 * Convert.
	 *
	 * @param array<string, mixed> $form {
	 *     @type string $title    CF7 form title.
	 *     @type string $markup   `_form`.
	 *     @type array  $mail     `_mail`.
	 *     @type array  $mail_2   `_mail_2`.
	 *     @type array  $messages `_messages`.
	 *     @type string $settings `_additional_settings`.
	 * }
	 * @param array<string, mixed> $env {
	 *     @type bool   $file_block  Is flinkform/field-file (Pro) registered?
	 *     @type string $privacy_url URL of the site's privacy policy page ('' if none).
	 *     @type string $form_id     UUID for the new form.
	 * }
	 * @return array{attrs: array<string, mixed>, blocks: array<int, array<string, mixed>>, fields: array<int, array<string, string>>, notes: array<int, array{level: string, text: string}>, status: string}
	 */
	public function convert( array $form, array $env ): array {
		$this->notes = [];
		$this->piped = [];

		$markup   = (string) ( $form['markup'] ?? '' );
		$tags     = TagParser::parse( $markup );
		$analysis = LabelGuesser::analyse( $markup, $tags );

		$attrs  = [
			'formId' => (string) ( $env['form_id'] ?? '' ),
			'title'  => trim( (string) ( $form['title'] ?? '' ) ),
		];
		$blocks = [];
		$fields = [];
		$items  = []; // [ offset, block ] to merge headings in order.
		$names  = [];

		foreach ( $tags as $i => $tag ) {
			$label = $analysis['labels'][ $i ] ?? LabelGuesser::fallback( $tag );
			$block = $this->map_tag( $tag, $label, $attrs, $env );
			if ( null === $block ) {
				continue;
			}
			if ( isset( $block['attrs']['fieldName'] ) ) {
				$name = (string) $block['attrs']['fieldName'];
				if ( isset( $names[ $name ] ) ) {
					$this->note( self::LOST, sprintf(
						/* translators: %s: field name. */
						__( 'The field name "%s" is used twice. Only the first field was imported.', 'flinkform' ),
						$name
					) );
					continue;
				}
				$names[ $name ] = true;
				// Where the label came from. Some blocks carry their own
				// text and never needed a guess: the toggle shows its
				// option text, the consent field its consent sentence, a
				// hidden field is never seen by visitors.
				$source = (string) $label['source'];
				if ( 'flinkform/field-toggle' === $block['blockName'] ) {
					$source = 'option';
				} elseif ( 'flinkform/field-consent' === $block['blockName'] ) {
					$source = 'consent';
				} elseif ( 'flinkform/field-hidden' === $block['blockName'] ) {
					$source = 'hidden';
				}
				$fields[] = [
					'name'   => $name,
					'type'   => (string) $tag['basetype'],
					'label'  => 'consent' === $source ? (string) ( $block['attrs']['consentText'] ?? $name ) : (string) ( $block['attrs']['label'] ?? $label['text'] ),
					'source' => $source,
				];
				if ( in_array( $source, [ 'placeholder', 'name' ], true ) && isset( $block['attrs']['label'] ) ) {
					$this->note( self::CHECK, sprintf(
						/* translators: 1: guessed label, 2: field name. */
						__( 'Label "%1$s" (field %2$s) was guessed. Please check it.', 'flinkform' ),
						(string) $block['attrs']['label'],
						$name
					) );
				}
			}
			$items[] = [ (int) $tag['offset'], $block ];
		}

		foreach ( $analysis['headings'] as $h ) {
			$items[] = [
				(int) $h['offset'],
				[ 'blockName' => 'flinkform/section-heading', 'attrs' => [ 'title' => $h['text'], 'headingLevel' => max( 2, min( 4, $h['level'] ) ) ], 'innerBlocks' => [] ],
			];
		}
		usort( $items, static fn ( $a, $b ) => $a[0] <=> $b[0] );
		$blocks = array_map( static fn ( $it ) => $it[1], $items );

		foreach ( $analysis['leftover'] as $text ) {
			$this->note( self::INFO, sprintf(
				/* translators: %s: a piece of text from the old form. */
				__( 'Text not carried over: "%s"', 'flinkform' ),
				self::shorten( $text, 80 )
			) );
		}

		$email_fields = array_column( array_filter( $fields, static fn ( $f ) => 'email' === $f['type'] ), 'name' );
		$attrs['notifications'] = $this->notifications( (array) ( $form['mail'] ?? [] ), (array) ( $form['mail_2'] ?? [] ), array_keys( $names ), $email_fields );

		$messages = (array) ( $form['messages'] ?? [] );
		if ( ! empty( $messages['mail_sent_ok'] ) && is_string( $messages['mail_sent_ok'] ) ) {
			$attrs['successMessage'] = trim( $messages['mail_sent_ok'] );
		}

		$this->additional_settings( (string) ( $form['settings'] ?? '' ) );

		if ( empty( $names ) ) {
			$this->note( self::LOST, __( 'No field could be carried over.', 'flinkform' ) );
		}

		return [
			'attrs'  => $attrs,
			'blocks' => $blocks,
			'fields' => $fields,
			'notes'  => $this->notes,
			'status' => $this->status(),
		];
	}

	/**
	 * Traffic light from the notes: green (nothing to check), yellow
	 * (please check), red (something could not be carried over).
	 *
	 * @return string green|yellow|red
	 */
	private function status(): string {
		$levels = array_column( $this->notes, 'level' );
		if ( in_array( self::LOST, $levels, true ) ) {
			return 'red';
		}
		return in_array( self::CHECK, $levels, true ) ? 'yellow' : 'green';
	}

	/**
	 * @param string $level
	 * @param string $text
	 * @param bool   $pro   Feature exists in Flinkform Pro.
	 */
	private function note( string $level, string $text, bool $pro = false ): void {
		$note = [ 'level' => $level, 'text' => $text ];
		if ( $pro ) {
			$note['pro'] = true; // The admin page adds a Pro link (if hints are on).
		}
		$this->notes[] = $note;
	}

	/**
	 * One CF7 tag → one Flinkform block, or null (dropped / form attribute).
	 *
	 * @param array<string, mixed>              $tag
	 * @param array{text: string, source: string} $label
	 * @param array<string, mixed>              $form_attrs Receives submitLabel.
	 * @param array<string, mixed>              $env
	 * @return array<string, mixed>|null
	 */
	private function map_tag( array $tag, array $label, array &$form_attrs, array $env ): ?array {
		$type = (string) $tag['basetype'];
		$name = str_replace( ':', '_', (string) $tag['name'] );
		$base = [ 'fieldName' => $name, 'label' => $label['text'] ];
		if ( $tag['required'] ) {
			$base['required'] = true;
		}
		$placeholder = null;
		if ( null !== TagParser::option( $tag, 'placeholder' ) || null !== TagParser::option( $tag, 'watermark' ) ) {
			$placeholder = (string) ( $tag['values'][0] ?? '' );
		}

		switch ( $type ) {
			case 'submit':
				$text = trim( (string) ( $tag['values'][0] ?? '' ) );
				if ( '' !== $text ) {
					$form_attrs['submitLabel'] = $text;
				}
				return null;

			case 'text':
			case 'email':
			case 'url':
			case 'tel':
			case 'textarea':
				$block = [ 'text' => 'flinkform/field-text', 'email' => 'flinkform/field-email', 'url' => 'flinkform/field-url', 'tel' => 'flinkform/field-phone', 'textarea' => 'flinkform/field-textarea' ][ $type ];
				$a     = $base;
				if ( null !== $placeholder && '' !== $placeholder ) {
					$a['placeholder'] = $placeholder;
				} elseif ( ! empty( $tag['values'][0] ) || '' !== trim( (string) $tag['content'] ) ) {
					$this->note( self::INFO, sprintf(
						/* translators: %s: field name. */
						__( 'Default value of "%s" not carried over (Flinkform fields start empty).', 'flinkform' ),
						$name
					) );
				}
				$ac = TagParser::option( $tag, 'autocomplete' );
				if ( 'text' === $type && is_string( $ac ) && in_array( $ac, self::AUTOCOMPLETE, true ) ) {
					$a['autocomplete'] = $ac;
				}
				$this->note_dynamic_default( $tag, $name );
				return $this->block( $block, $a );

			case 'number':
			case 'range':
				$a = $base;
				foreach ( [ 'min', 'max', 'step' ] as $k ) {
					$v = TagParser::option( $tag, $k );
					if ( is_string( $v ) && is_numeric( $v ) ) {
						$a[ $k ] = $v;
					}
				}
				if ( null !== $placeholder && '' !== $placeholder ) {
					$a['placeholder'] = $placeholder;
				}
				if ( 'range' === $type ) {
					$this->note( self::INFO, sprintf(
						/* translators: %s: field name. */
						__( 'The slider "%s" becomes a number field.', 'flinkform' ),
						$name
					) );
				}
				return $this->block( 'flinkform/field-number', $a );

			case 'date':
				$a = $base;
				foreach ( [ 'min' => 'minDate', 'max' => 'maxDate' ] as $k => $attr ) {
					$v = TagParser::option( $tag, $k );
					if ( is_string( $v ) ) {
						if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
							$a[ $attr ] = $v;
						} else {
							$this->note( self::CHECK, sprintf(
								/* translators: 1: field name, 2: CF7 date expression like today+1_month. */
								__( 'Date field "%1$s": the relative limit "%2$s" was not carried over. Set a date range in Flinkform if needed.', 'flinkform' ),
								$name,
								$v
							) );
						}
					}
				}
				return $this->block( 'flinkform/field-date', $a );

			case 'select':
			case 'checkbox':
			case 'radio':
				return $this->map_choice( $tag, $type, $base, $name );

			case 'acceptance':
				$text = trim( preg_replace( '/\s+/u', ' ', (string) $tag['content'] ) ?? '' );
				$text = $this->privacy_placeholder( $text, (string) ( $env['privacy_url'] ?? '' ) );
				$a    = [ 'fieldName' => $name ];
				if ( '' !== $text ) {
					$a['consentText'] = $text;
				}
				if ( true === TagParser::option( $tag, 'optional' ) ) {
					$a['required'] = false;
				}
				if ( true === TagParser::option( $tag, 'invert' ) ) {
					$this->note( self::CHECK, sprintf(
						/* translators: %s: field name. */
						__( 'The consent box "%s" was inverted in CF7 (unticked = agreed). Flinkform asks for a tick. Please check the text.', 'flinkform' ),
						$name
					) );
				}
				return $this->block( 'flinkform/field-consent', $a );

			case 'hidden':
				$a = [ 'fieldName' => $name, 'label' => $label['text'], 'valueSource' => 'static', 'staticValue' => (string) ( $tag['values'][0] ?? '' ) ];
				$this->note_dynamic_default( $tag, $name );
				return $this->block( 'flinkform/field-hidden', $a );

			case 'file':
				if ( empty( $env['file_block'] ) ) {
					$this->note( self::LOST, sprintf(
						/* translators: %s: field name. */
						__( 'File upload "%s" needs Flinkform Pro and was left out.', 'flinkform' ),
						$name
					), true );
					return null;
				}
				$a     = $base;
				$types = TagParser::option( $tag, 'filetypes' );
				if ( is_string( $types ) && '' !== $types ) {
					$a['allowedTypes'] = array_values( array_filter( array_map( static fn ( $t ) => strtolower( ltrim( trim( $t ), '.' ) ), explode( '|', $types ) ) ) );
				}
				$limit = TagParser::option( $tag, 'limit' );
				if ( is_string( $limit ) && preg_match( '/^(\d+)(kb|mb)?$/i', $limit, $lm ) ) {
					$bytes         = (int) $lm[1] * ( 'mb' === strtolower( $lm[2] ?? '' ) ? 1048576 : ( 'kb' === strtolower( $lm[2] ?? '' ) ? 1024 : 1 ) );
					$a['maxSizeMb'] = max( 1, (int) ceil( $bytes / 1048576 ) );
				}
				return $this->block( 'flinkform/field-file', $a );
		}

		if ( in_array( $type, self::DROP_QUIETLY, true ) ) {
			if ( in_array( $type, [ 'recaptcha', 'turnstile', 'quiz', 'captchac', 'captchar' ], true ) ) {
				$this->note( self::INFO, __( 'CAPTCHA / quiz left out: Flinkform has its own spam protection without third parties.', 'flinkform' ) );
			}
			return null;
		}

		$this->note( self::LOST, sprintf(
			/* translators: %s: CF7 tag type, e.g. "[group]". */
			__( 'Field type [%s] is not known to Flinkform and was left out (usually from a CF7 add-on).', 'flinkform' ),
			$type
		) );
		return null;
	}

	/**
	 * select / checkbox / radio.
	 *
	 * @param array<string, mixed> $tag
	 * @param string               $type
	 * @param array<string, mixed> $base
	 * @param string               $name
	 * @return array<string, mixed>|null
	 */
	private function map_choice( array $tag, string $type, array $base, string $name ): ?array {
		$pipes = $tag['pipes'];
		$first_as_label = true === TagParser::option( $tag, 'first_as_label' );
		$placeholder    = null;
		if ( 'select' === $type && $first_as_label && ! empty( $pipes ) ) {
			$placeholder = (string) array_shift( $pipes )[0];
		}

		$options = [];
		foreach ( $pipes as [ $shown, $value ] ) {
			$value = '' === trim( (string) $value ) ? (string) $shown : (string) $value;
			if ( $shown !== $value ) {
				$this->piped[ $name ] = true;
			}
			$options[] = [ 'label' => (string) $shown, 'value' => $value ];
		}
		if ( empty( $options ) ) {
			$this->note( self::LOST, sprintf(
				/* translators: %s: field name. */
				__( 'Choice field "%s" has no options and was left out.', 'flinkform' ),
				$name
			) );
			return null;
		}
		foreach ( [ 'free_text' => __( 'the free-text "other" option', 'flinkform' ), 'exclusive' => __( 'the "only one box" behaviour', 'flinkform' ) ] as $opt => $what ) {
			if ( true === TagParser::option( $tag, $opt ) ) {
				$this->note( self::CHECK, sprintf(
					/* translators: 1: field name, 2: feature description. */
					__( 'Field "%1$s": %2$s is not carried over.', 'flinkform' ),
					$name,
					$what
				) );
			}
		}

		// A single checkbox without a pipe reads as "I agree"-style toggle.
		if ( 'checkbox' === $type && 1 === count( $options ) && $options[0]['label'] === $options[0]['value'] ) {
			$a = [ 'fieldName' => $name, 'label' => $options[0]['label'] ];
			if ( $tag['required'] ) {
				$a['required'] = true;
			}
			return $this->block( 'flinkform/field-toggle', $a );
		}

		$a = $base + [ 'options' => $options ];
		if ( 'radio' === $type ) {
			// CF7 treats a radio group as answered by default (first option
			// preselected) and has no radio*; Flinkform asks explicitly.
			$a['required'] = true;
			return $this->block( 'flinkform/field-radio', $a );
		}
		if ( 'checkbox' === $type ) {
			return $this->block( 'flinkform/field-checkbox', $a );
		}
		if ( true === TagParser::option( $tag, 'multiple' ) ) {
			$a['multiple'] = true;
		}
		if ( null !== $placeholder && '' !== $placeholder ) {
			$a['placeholder'] = $placeholder;
		}
		return $this->block( 'flinkform/field-select', $a );
	}

	/**
	 * Note CF7's request-dependent defaults (default:get, default:user_email …).
	 *
	 * @param array<string, mixed> $tag
	 * @param string               $name
	 */
	private function note_dynamic_default( array $tag, string $name ): void {
		$default = TagParser::option( $tag, 'default' );
		if ( is_string( $default ) && ! preg_match( '/^\d+(_\d+)*$/', $default ) ) {
			$this->note( self::CHECK, sprintf(
				/* translators: 1: field name, 2: CF7 default source, e.g. get or user_email. */
				__( 'Field "%1$s" filled itself in CF7 (default:%2$s). Flinkform does not carry that over.', 'flinkform' ),
				$name,
				$default
			) );
		}
	}

	/**
	 * Replace a link to the site's privacy page by {privacy_policy}, strip
	 * other markup to text.
	 */
	private function privacy_placeholder( string $html, string $privacy_url ): string {
		if ( '' !== $privacy_url ) {
			$quoted = preg_quote( rtrim( $privacy_url, '/' ), '#' );
			$html   = preg_replace( '#<a\s[^>]*href=["\']' . $quoted . '/?["\'][^>]*>.*?</a>#is', '{privacy_policy}', $html ) ?? $html;
		}
		$text = trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' !== $text && false === strpos( $text, '{privacy_policy}' ) && false !== stripos( $html, '<a ' ) ) {
			$this->note( self::CHECK, __( 'The consent text contained a link that is not your privacy page. Only its text was kept.', 'flinkform' ) );
		}
		return $text;
	}

	/**
	 * CF7 mail + mail_2 → Flinkform notifications.
	 *
	 * @param array<string, mixed> $mail
	 * @param array<string, mixed> $mail2
	 * @param array<int, string>   $fields       Imported field names.
	 * @param array<int, string>   $email_fields Names of imported email fields.
	 * @return array<string, mixed>
	 */
	private function notifications( array $mail, array $mail2, array $fields, array $email_fields = [] ): array {
		$out   = [];
		$admin = [];

		$to = trim( $this->mail_tags( (string) ( $mail['recipient'] ?? '' ), $fields, true ) );
		if ( '' !== $to ) {
			$admin['to'] = $to;
		}
		if ( ! empty( $mail['subject'] ) ) {
			$admin['subject'] = $this->mail_tags( (string) $mail['subject'], $fields );
		}
		if ( ! empty( $mail['body'] ) ) {
			$admin['body'] = $this->mail_body( (string) $mail['body'], ! empty( $mail['use_html'] ), $fields );
		}
		$reply = $this->header( (string) ( $mail['additional_headers'] ?? '' ), 'Reply-To' );
		if ( '' !== $reply ) {
			$admin['replyTo'] = $this->mail_tags( $reply, $fields );
		}
		foreach ( [ 'Cc', 'Bcc' ] as $h ) {
			if ( '' !== $this->header( (string) ( $mail['additional_headers'] ?? '' ), $h ) ) {
				$this->note( self::CHECK, sprintf(
					/* translators: %s: mail header name (Cc or Bcc). */
					__( 'The %s header of the notification was not carried over. Add those addresses to the recipients if needed.', 'flinkform' ),
					$h
				) );
			}
		}
		$sender = (string) ( $mail['sender'] ?? '' );
		if ( '' !== $sender && false === strpos( $sender, '[_site_title]' ) ) {
			$this->note( self::INFO, __( 'CF7 used its own sender address. Flinkform sends from the WordPress default (or your SMTP setup); a per-form sender can be set in the form.', 'flinkform' ) );
		}
		if ( ! empty( trim( (string) ( $mail['attachments'] ?? '' ) ) ) ) {
			$this->note( self::CHECK, __( 'Mail attachments were not carried over.', 'flinkform' ) );
		}
		if ( ! empty( $admin ) ) {
			$out['admin'] = $admin;
		}

		if ( ! empty( $mail2['active'] ) ) {
			$recipient = trim( (string) ( $mail2['recipient'] ?? '' ) );
			if ( preg_match( '/^\[\s*([A-Za-z][-A-Za-z0-9_:.]*)\s*\]$/', $recipient, $m ) && in_array( strtr( $m[1], '.:', '__' ), $email_fields, true ) ) {
				$sub = [ 'enabled' => true, 'emailField' => strtr( $m[1], '.:', '__' ) ];
				if ( ! empty( $mail2['subject'] ) ) {
					$sub['subject'] = $this->mail_tags( (string) $mail2['subject'], $fields );
				}
				if ( ! empty( $mail2['body'] ) ) {
					$sub['body'] = $this->mail_body( (string) $mail2['body'], ! empty( $mail2['use_html'] ), $fields );
				}
				$out['submitter'] = $sub;
			} else {
				$this->note( self::CHECK, __( 'The confirmation mail (Mail 2) could not be carried over: its recipient is not a single email field. Set it up in the form\'s notifications.', 'flinkform' ) );
			}
		}
		return $out;
	}

	/**
	 * Body: HTML bodies become plain text (Flinkform lays out the mail).
	 */
	private function mail_body( string $body, bool $is_html, array $fields ): string {
		if ( $is_html ) {
			$body = preg_replace( '/<br\s*\/?>|<\/p>|<\/div>|<\/tr>|<\/li>/i', "\n", $body ) ?? $body;
			$body = trim( html_entity_decode( wp_strip_all_tags( $body ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$this->note( self::INFO, __( 'The HTML mail body was converted to text. Flinkform wraps it in its own mail layout.', 'flinkform' ) );
		}
		return $this->mail_tags( $body, $fields );
	}

	/**
	 * Translate CF7 mail tags into Flinkform merge tags.
	 *
	 * @param string             $text
	 * @param array<int, string> $fields
	 * @param bool               $recipient Recipient line: [_site_admin_email] means "default".
	 * @return string
	 */
	public function mail_tags( string $text, array $fields, bool $recipient = false ): string {
		$map = [
			'_site_title'         => '{site:name}',
			'_site_url'           => '{site:url}',
			'_date'               => '{submission:date}',
			'_time'               => '{submission:date}',
			'_serial_number'      => '{submission:id}',
			'_contact_form_title' => '{form:title}',
		];
		$dropped = [];
		$out     = preg_replace_callback(
			'/(\[?)\[[\t ]*([a-zA-Z_][0-9a-zA-Z:._-]*)((?:[\t ]+"[^"]*"|[\t ]+\'[^\']*\')*)[\t ]*\](\]?)/',
			function ( array $m ) use ( $map, $fields, $recipient, &$dropped ): string {
				if ( '[' === $m[1] && ']' === $m[4] ) {
					return substr( $m[0], 1, -1 ); // [[escaped]] → literal [tag].
				}
				$tag = $m[2];
				if ( '_site_admin_email' === $tag ) {
					if ( $recipient ) {
						return ''; // Flinkform's default recipient is the admin email.
					}
					$dropped[ $tag ] = true;
					return '';
				}
				if ( isset( $map[ $tag ] ) ) {
					return $m[1] . $map[ $tag ] . $m[4];
				}
				$raw   = 0 === strpos( $tag, '_raw_' ) ? substr( $tag, 5 ) : null;
				$fmt   = 0 === strpos( $tag, '_format_' ) ? substr( $tag, 8 ) : null;
				$field = strtr( $raw ?? $fmt ?? $tag, '.:', '__' );
				if ( in_array( $field, $fields, true ) ) {
					if ( null !== $raw ) {
						return $m[1] . '{field:' . $field . '}' . $m[4];
					}
					// CF7's [x] prints the part after the pipe = our value.
					return $m[1] . '{field:' . $field . ( isset( $this->piped[ $field ] ) ? ':value' : '' ) . '}' . $m[4];
				}
				if ( '_' === $tag[0] ) {
					$dropped[ $tag ] = true;
					return '';
				}
				return $m[0]; // Unknown: CF7 would print it verbatim as well.
			},
			$text
		);
		foreach ( array_keys( $dropped ) as $tag ) {
			$this->note( in_array( $tag, [ '_remote_ip', '_user_agent' ], true ) ? self::INFO : self::CHECK, sprintf(
				/* translators: %s: CF7 mail tag like [_remote_ip]. */
				__( 'Mail tag [%s] was removed. Flinkform does not know it (and stores no IP addresses or browser data).', 'flinkform' ),
				$tag
			) );
		}
		$out = is_string( $out ) ? $out : $text;
		if ( $recipient ) {
			$out = trim( (string) preg_replace( '/\s*,\s*,+\s*|^\s*,\s*|\s*,\s*$/', ',', $out ), " ,\t\n" );
		}
		return $out;
	}

	/**
	 * Value of one header line in CF7's additional_headers.
	 */
	private function header( string $headers, string $name ): string {
		if ( preg_match( '/^' . preg_quote( $name, '/' ) . '\s*:\s*(.+)$/mi', $headers, $m ) ) {
			return trim( $m[1] );
		}
		return '';
	}

	/**
	 * Flag CF7 additional settings that change behaviour.
	 */
	private function additional_settings( string $settings ): void {
		foreach ( preg_split( '/\r?\n/', $settings ) ?: [] as $line ) {
			if ( ! preg_match( '/^([a-zA-Z0-9_]+)[\t ]*:(.*)$/', trim( $line ), $m ) ) {
				continue;
			}
			$key = strtolower( $m[1] );
			if ( in_array( $key, [ 'demo_mode', 'skip_mail' ], true ) && in_array( strtolower( trim( $m[2] ) ), [ 'on', 'true', '1' ], true ) ) {
				$this->note( self::CHECK, sprintf(
					/* translators: %s: CF7 setting name. */
					__( 'CF7 had "%s" switched on (no mail sent). Flinkform will send notifications; switch them off in the form if that is wanted.', 'flinkform' ),
					$key
				) );
			} elseif ( ! in_array( $key, [ 'acceptance_as_validation', 'flamingo_email', 'flamingo_name', 'flamingo_subject' ], true ) ) {
				$this->note( self::INFO, sprintf(
					/* translators: %s: CF7 setting name. */
					__( 'CF7 setting "%s" has no equivalent and was left out.', 'flinkform' ),
					$key
				) );
			}
		}
	}

	/**
	 * Shorten text for the preview. mb_strimwidth() needs the mbstring
	 * extension, which WordPress does not guarantee; mb_substr() has a
	 * WordPress fallback (compat.php).
	 *
	 * @param string $text
	 * @param int    $max  Characters.
	 * @return string
	 */
	public static function shorten( string $text, int $max ): string {
		return mb_strlen( $text ) > $max ? rtrim( mb_substr( $text, 0, $max - 1 ) ) . '…' : $text;
	}

	/**
	 * @param string               $name
	 * @param array<string, mixed> $attrs
	 * @return array<string, mixed>
	 */
	private function block( string $name, array $attrs ): array {
		return [ 'blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => [] ];
	}
}
