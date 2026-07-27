#!/usr/bin/env php
<?php
/**
 * Standalone unit tests for the notification sender and Reply-To handling.
 *
 * Two things are worth pinning here.
 *
 * The sender is applied through `wp_mail_from{,_name}` rather than a
 * `From:` header, because wp_mail() runs a header-derived address through
 * that filter afterwards — an SMTP plugin hooked there would silently
 * overrule a per-form setting. Registering at PHP_INT_MAX is what makes
 * the form-level value win, and removing it again is what keeps every
 * other plugin's mail untouched. Both are asserted below.
 *
 * And the Reply-To on the submitter confirmation is the whole point of
 * the feature: send from the website's address, collect replies elsewhere.
 *
 * Run:  php tests/mailer-sender-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../' );
	}

	// --- Minimal WordPress hook + helper layer -------------------------

	$GLOBALS['wp_filters'] = [];
	$GLOBALS['sent_mail']  = [];

	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
		$GLOBALS['wp_filters'][ $hook ][ $priority ][] = $cb;
		return true;
	}
	function remove_filter( $hook, $cb, $priority = 10 ) {
		foreach ( $GLOBALS['wp_filters'][ $hook ][ $priority ] ?? [] as $i => $existing ) {
			if ( $existing === $cb ) {
				unset( $GLOBALS['wp_filters'][ $hook ][ $priority ][ $i ] );
				return true;
			}
		}
		return false;
	}
	function apply_filters( $hook, $value, ...$rest ) {
		$byPriority = $GLOBALS['wp_filters'][ $hook ] ?? [];
		ksort( $byPriority );
		foreach ( $byPriority as $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$value = $cb( $value, ...$rest );
			}
		}
		return $value;
	}
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) {
		return true;
	}

	/** Stand-in for wp_mail: records the call and resolves the From filters. */
	function wp_mail( $to, $subject, $body, $headers = [], $attachments = [] ) {
		$GLOBALS['sent_mail'][] = [
			'to'         => (array) $to,
			'subject'    => $subject,
			'body'       => $body,
			'headers'    => (array) $headers,
			'from_email' => apply_filters( 'wp_mail_from', 'wordpress@example.com' ),
			'from_name'  => apply_filters( 'wp_mail_from_name', 'WordPress' ),
		];
		return true;
	}

	function is_email( $email ) {
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
	function sanitize_email( $email ) {
		return is_email( $email ) ? $email : '';
	}
	function sanitize_text_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
	function __( $text, $domain = '' ) {
		return $text;
	}
	function esc_html( $text ) {
		return $text;
	}
	function get_option( $name, $default = '' ) {
		return 'admin' === $name ? '' : $default;
	}
	function get_bloginfo( $what = '' ) {
		return 'Test Site';
	}
}

namespace Flinkform\Notifications {
	/** Stub: merge tags are covered by their own paths; identity is enough. */
	class MergeTags {
		public static function render( $template, $context ) {
			return (string) $template;
		}
		public static function context( $id, $form_id, $clean, $form_def ) {
			return [];
		}
	}
}

namespace {
	require_once __DIR__ . '/../includes/Notifications/Mailer.php';

	$passed = 0;
	$failed = 0;

	function check( string $label, bool $ok, string $detail = '' ): void {
		global $passed, $failed;
		if ( $ok ) {
			++$passed;
			return;
		}
		++$failed;
		echo "FAIL: $label" . ( '' !== $detail ? " — $detail" : '' ) . "\n";
	}

	/**
	 * Run one submission through the Mailer and return the recorded mails.
	 *
	 * @param array<string, mixed> $notifications The block's notifications attribute.
	 * @param array<string, mixed> $clean         Submitted values.
	 * @return array<int, array<string, mixed>>
	 */
	function dispatch( array $notifications, array $clean = [ 'email' => 'besucherin@example.org' ] ): array {
		$GLOBALS['sent_mail'] = [];
		$mailer   = new \Flinkform\Notifications\Mailer();
		$form_def = [
			'attributes' => [ 'notifications' => $notifications ],
			'fields'     => [ [ 'name' => 'email', 'label' => 'Email', 'type' => 'email' ] ],
		];
		$mailer->send_admin_notification( 1, 'form-1', $clean, $form_def );
		$mailer->send_submitter_confirmation( 1, 'form-1', $clean, $form_def );
		return $GLOBALS['sent_mail'];
	}

	/**
	 * Pick a recorded mail by recipient rather than by position — the admin
	 * notification is skipped when it has no recipient, which would shift
	 * every index and make the assertions lie about which mail they read.
	 *
	 * @param array<int, array<string, mixed>> $mails
	 * @return array<string, mixed> Empty array when that mail was not sent.
	 */
	function mail_to( array $mails, string $recipient ): array {
		foreach ( $mails as $mail ) {
			if ( in_array( $recipient, $mail['to'], true ) ) {
				return $mail;
			}
		}
		return [ 'to' => [], 'headers' => [], 'from_email' => '(nicht gesendet)', 'from_name' => '(nicht gesendet)' ];
	}

	$base_submitter = [
		'enabled'    => true,
		'emailField' => 'email',
		'subject'    => 'Danke',
		'body'       => 'Text',
	];

	// --- Sender override -----------------------------------------------

	$mails = dispatch( [
		'fromEmail' => 'info@meine-seite.de',
		'fromName'  => 'Hebamme Lea Weiss',
		'admin'     => [ 'enabled' => true, 'to' => 'lea@gmail.com', 'subject' => 'Neu', 'body' => 'X' ],
		'submitter' => $base_submitter,
	] );
	check( 'both mails are sent', 2 === count( $mails ), (string) count( $mails ) );
	check( 'admin mail uses the form sender address', 'info@meine-seite.de' === mail_to( $mails, 'lea@gmail.com' )['from_email'], json_encode( mail_to( $mails, 'lea@gmail.com' ) ) );
	check( 'admin mail uses the form sender name', 'Hebamme Lea Weiss' === mail_to( $mails, 'lea@gmail.com' )['from_name'] );
	check( 'confirmation uses the same sender', 'info@meine-seite.de' === mail_to( $mails, 'besucherin@example.org' )['from_email'] );

	// The filters must not survive the send, or every later wp_mail() on
	// the request would inherit this form's sender.
	check(
		'sender filters are removed again',
		'wordpress@example.com' === apply_filters( 'wp_mail_from', 'wordpress@example.com' )
			&& 'WordPress' === apply_filters( 'wp_mail_from_name', 'WordPress' ),
		apply_filters( 'wp_mail_from', 'wordpress@example.com' )
	);

	// --- Halves are independent ----------------------------------------

	$mails = dispatch( [ 'fromName' => 'Nur der Name', 'admin' => [ 'enabled' => true, 'to' => 'a@b.de' ] ] );
	check( 'name alone leaves the address untouched', 'wordpress@example.com' === mail_to( $mails, 'a@b.de' )['from_email'] );
	check( 'name alone is applied', 'Nur der Name' === mail_to( $mails, 'a@b.de' )['from_name'] );

	$mails = dispatch( [ 'fromEmail' => 'nur@adresse.de', 'admin' => [ 'enabled' => true, 'to' => 'a@b.de' ] ] );
	check( 'address alone leaves the name untouched', 'WordPress' === mail_to( $mails, 'a@b.de' )['from_name'] );

	// --- Nothing configured means nothing changed -----------------------

	$mails = dispatch( [ 'admin' => [ 'enabled' => true, 'to' => 'a@b.de' ] ] );
	check( 'no sender configured leaves WordPress alone', 'wordpress@example.com' === mail_to( $mails, 'a@b.de' )['from_email'] );

	// --- A malformed address must not be applied ------------------------

	$mails = dispatch( [ 'fromEmail' => 'kein-email', 'admin' => [ 'enabled' => true, 'to' => 'a@b.de' ] ] );
	check( 'malformed sender address is ignored', 'wordpress@example.com' === mail_to( $mails, 'a@b.de' )['from_email'] );

	$mails = dispatch( [ 'fromEmail' => "ok@seite.de\r\nBcc: angreifer@evil.tld", 'admin' => [ 'enabled' => true, 'to' => 'a@b.de' ] ] );
	check(
		'CR/LF in the sender cannot smuggle a header',
		! str_contains( mail_to( $mails, 'lea@gmail.com' )['from_email'], 'Bcc' ),
		mail_to( $mails, 'lea@gmail.com' )['from_email']
	);

	// --- Reply-To on the submitter confirmation -------------------------

	$mails = dispatch( [ 'submitter' => $base_submitter + [ 'replyTo' => 'lea@gmail.com' ] ] );
	check(
		'confirmation carries the Reply-To',
		in_array( 'Reply-To: lea@gmail.com', mail_to( $mails, 'besucherin@example.org' )['headers'], true ),
		json_encode( mail_to( $mails, 'besucherin@example.org' )['headers'] )
	);

	$mails = dispatch( [ 'submitter' => $base_submitter ] );
	check( 'no Reply-To configured means no header', [] === mail_to( $mails, 'besucherin@example.org' )['headers'], json_encode( mail_to( $mails, 'besucherin@example.org' )['headers'] ) );

	$mails = dispatch( [ 'submitter' => $base_submitter + [ 'replyTo' => 'kaputt' ] ] );
	check( 'malformed Reply-To is dropped', [] === mail_to( $mails, 'besucherin@example.org' )['headers'] );

	$mails = dispatch( [ 'submitter' => $base_submitter + [ 'replyTo' => "ok@seite.de\r\nBcc: angreifer@evil.tld" ] ] );
	$headers = mail_to( $mails, 'besucherin@example.org' )['headers'];
	check( 'CR/LF in Reply-To cannot smuggle a header', ! str_contains( implode( '|', $headers ), 'Bcc' ), json_encode( $headers ) );

	// --- The admin Reply-To keeps working -------------------------------

	$mails = dispatch( [ 'admin' => [ 'enabled' => true, 'to' => 'a@b.de', 'replyTo' => 'besucherin@example.org' ] ] );
	check(
		'admin Reply-To is unchanged by the refactor',
		in_array( 'Reply-To: besucherin@example.org', mail_to( $mails, 'a@b.de' )['headers'], true ),
		json_encode( mail_to( $mails, 'a@b.de' )['headers'] )
	);

	// --- Summary --------------------------------------------------------

	echo "\n";
	if ( $failed > 0 ) {
		echo "$failed FAILED, $passed passed.\n";
		exit( 1 );
	}
	echo "All $passed tests passed.\n";
	exit( 0 );
}
