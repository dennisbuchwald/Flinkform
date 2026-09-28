#!/usr/bin/env php
<?php
/**
 * Contact Form 7 import (1.15.0): parser, label heuristics, field mapping,
 * mail translation and embed replacement, against CF7-shaped fixtures.
 * CF7 facts behind the expectations were checked against the CF7 6.1.7
 * source (see FLINKFORM_CF7_IMPORT.md).
 *
 * Run:  php tests/cf7-import-test.php
 */

declare( strict_types = 1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/../' );
	function __( $t, $d = '' ) { return $t; }
	function wp_strip_all_tags( $t, $b = false ) { $t = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $t ); return trim( strip_tags( $t ) ); }

	require __DIR__ . '/../includes/Import/Cf7/TagParser.php';
	require __DIR__ . '/../includes/Import/Cf7/LabelGuesser.php';
	require __DIR__ . '/../includes/Import/Cf7/Converter.php';
	require __DIR__ . '/../includes/Import/Cf7/EmbedReplacer.php';

	use Flinkform\Import\Cf7\Converter;
	use Flinkform\Import\Cf7\EmbedReplacer;
	use Flinkform\Import\Cf7\TagParser;

	$passed = 0; $failed = 0;
	function check( string $l, bool $ok, string $d = '' ): void { global $passed, $failed; if ( $ok ) { ++$passed; return; } ++$failed; echo "FAIL: $l" . ( '' !== $d ? " — $d" : '' ) . "\n"; }
	function block_by( array $blocks, string $field ): ?array { foreach ( $blocks as $b ) { if ( ( $b['attrs']['fieldName'] ?? '' ) === $field ) { return $b; } } return null; }
	function notes_text( array $r ): string { return implode( "\n", array_column( $r['notes'], 'text' ) ); }

	// --- Parser -----------------------------------------------------------
	$tags = TagParser::parse( '[text* your-name placeholder class:big "Max"] [select s multiple include_blank "A|a" \'B\'] [acceptance ok optional] Yes [/acceptance] [[text esc]] [submit "Go"]' );
	check( 'parser finds 4 tags (escaped one skipped)', 4 === count( $tags ), (string) count( $tags ) );
	check( 'required star', true === $tags[0]['required'] && 'text' === $tags[0]['basetype'] );
	check( 'name split from options', 'your-name' === $tags[0]['name'] && [ 'placeholder', 'class:big' ] === $tags[0]['options'] );
	check( 'quoted values', [ 'Max' ] === $tags[0]['values'] );
	check( 'pipes split', [ [ 'A', 'a' ], [ 'B', 'B' ] ] === $tags[1]['pipes'] && [ 'A', 'B' ] === $tags[1]['values'] );
	check( 'enclosed content', 'Yes' === trim( $tags[2]['content'] ) );
	check( 'submit has no name', '' === $tags[3]['name'] && [ 'Go' ] === $tags[3]['values'] );
	check( 'option() reads flags and values', true === TagParser::option( $tags[1], 'multiple' ) && 'big' === TagParser::option( $tags[0], 'class' ) && null === TagParser::option( $tags[0], 'min' ) );
	check( 'options after values = malformed, whole string is one value (CF7 does the same)', [ 'your-name placeholder "Max" class:big' ] === TagParser::parse( '[text your-name placeholder "Max" class:big]' )[0]['values'] || '' === TagParser::parse( '[text your-name placeholder "Max" class:big]' )[0]['name'] );
	check( 'dots in names become underscores', 'a_b' === TagParser::parse( '[text a.b]' )[0]['name'] );
	check( 'unregistered add-on types are still found', 'group' === TagParser::parse( '[group g][/group]' )[0]['basetype'] );

	// --- Default CF7 form ---------------------------------------------------
	$conv = new Converter();
	$default = $conv->convert(
		[
			'title'    => 'Contact form 1',
			'markup'   => file_get_contents( __DIR__ . '/fixtures/cf7/default.txt' ),
			'mail'     => [ 'active' => true, 'subject' => '[_site_title] "[your-subject]"', 'sender' => '[_site_title] <wordpress@example.test>', 'recipient' => '[_site_admin_email]', 'body' => "From: [your-name] [your-email]\nSubject: [your-subject]\n\n[your-message]\n\n-- \nThis e-mail was sent from a contact form on [_site_title] ([_site_url])", 'additional_headers' => 'Reply-To: [your-email]', 'attachments' => '', 'use_html' => false ],
			'mail_2'   => [ 'active' => false ],
			'messages' => [ 'mail_sent_ok' => 'Thank you for your message. It has been sent.' ],
			'settings' => '',
		],
		[ 'file_block' => false, 'privacy_url' => '', 'form_id' => 'uuid-1' ]
	);
	$names = array_map( static fn ( $b ) => $b['attrs']['fieldName'] ?? null, $default['blocks'] );
	check( 'default: four fields in order', [ 'your-name', 'your-email', 'your-subject', 'your-message' ] === $names, json_encode( $names ) );
	check( 'default: labels from <label>', 'Your name' === block_by( $default['blocks'], 'your-name' )['attrs']['label'] && 'Your message' === block_by( $default['blocks'], 'your-message' )['attrs']['label'] );
	check( 'default: label source is label', 'label' === $default['fields'][0]['source'] );
	check( 'default: required kept, optional not required', true === ( block_by( $default['blocks'], 'your-email' )['attrs']['required'] ?? false ) && ! isset( block_by( $default['blocks'], 'your-message' )['attrs']['required'] ) );
	check( 'default: block types', 'flinkform/field-email' === block_by( $default['blocks'], 'your-email' )['blockName'] && 'flinkform/field-textarea' === block_by( $default['blocks'], 'your-message' )['blockName'] );
	check( 'default: autocomplete carried over', 'name' === ( block_by( $default['blocks'], 'your-name' )['attrs']['autocomplete'] ?? '' ) );
	check( 'default: submit label', 'Submit' === $default['attrs']['submitLabel'] );
	check( 'default: success message', 'Thank you for your message. It has been sent.' === $default['attrs']['successMessage'] );
	$admin = $default['attrs']['notifications']['admin'];
	check( 'default: admin recipient = Flinkform default', ! isset( $admin['to'] ), json_encode( $admin ) );
	check( 'default: subject tags', '{site:name} "{field:your-subject}"' === $admin['subject'], $admin['subject'] );
	check( 'default: body tags', str_contains( $admin['body'], 'From: {field:your-name} {field:your-email}' ) && str_contains( $admin['body'], '({site:url})' ) );
	check( 'default: reply-to', '{field:your-email}' === $admin['replyTo'] );
	check( 'default: no confirmation mail', ! isset( $default['attrs']['notifications']['submitter'] ) );
	check( 'default: green', 'green' === $default['status'], notes_text( $default ) );
	check( 'default: form id and title', 'uuid-1' === $default['attrs']['formId'] && 'Contact form 1' === $default['attrs']['title'] );

	// --- Agency form ----------------------------------------------------------
	$markup = file_get_contents( __DIR__ . '/fixtures/cf7/agency.txt' );
	$agency = $conv->convert(
		[
			'title'  => 'Projektanfrage',
			'markup' => $markup,
			'mail'   => [ 'recipient' => 'info@example.test, [_site_admin_email]', 'subject' => 'Neu: [projekt] / [_raw_projekt]', 'body' => '<p>Budget: [budget]</p><p>IP: [_remote_ip]</p><p>[_post_title]</p>', 'use_html' => true, 'additional_headers' => "Reply-To: [telefon]\nBcc: chef@example.test", 'sender' => 'Agentur <noreply@example.test>', 'attachments' => '[anhang]' ],
			'mail_2' => [ 'active' => true, 'recipient' => '[telefon]', 'subject' => 'Danke', 'body' => 'Danke' ],
			'messages' => [],
			'settings' => "skip_mail: on\non_sent_ok: \"location='/danke';\"",
		],
		[ 'file_block' => false, 'privacy_url' => 'https://example.test/datenschutz', 'form_id' => 'uuid-2' ]
	);
	$b = $agency['blocks'];
	check( 'agency: heading becomes a section heading first', 'flinkform/section-heading' === $b[0]['blockName'] && 'Ihr Projekt' === $b[0]['attrs']['title'] );
	$radio = block_by( $b, 'projekt' );
	check( 'agency: radio label from the line before', 'Art des Projekts' === $radio['attrs']['label'], $radio['attrs']['label'] ?? '' );
	check( 'agency: radio options with pipes', [ [ 'label' => 'Neue Website', 'value' => 'neu' ], [ 'label' => 'Relaunch', 'value' => 'relaunch' ], [ 'label' => 'Shop', 'value' => 'Shop' ] ] === $radio['attrs']['options'] );
	check( 'agency: radio is always required (CF7 behaviour)', true === $radio['attrs']['required'] );
	$sel = block_by( $b, 'budget' );
	check( 'agency: first_as_label becomes placeholder', 'Bitte wählen' === $sel['attrs']['placeholder'] && 2 === count( $sel['attrs']['options'] ) );
	check( 'agency: select label after <br>', 'Budget' === $sel['attrs']['label'] );
	check( 'agency: checkbox group', 'flinkform/field-checkbox' === block_by( $b, 'leistungen' )['blockName'] && 3 === count( block_by( $b, 'leistungen' )['attrs']['options'] ) );
	check( 'agency: single checkbox becomes a toggle with its text', 'flinkform/field-toggle' === block_by( $b, 'newsletter' )['blockName'] && 'Newsletter abonnieren' === block_by( $b, 'newsletter' )['attrs']['label'] );
	$firma = block_by( $b, 'firma' );
	check( 'agency: placeholder kept', 'Firma' === $firma['attrs']['placeholder'] );
	check( 'agency: label from placeholder is flagged', 'placeholder' === array_values( array_filter( $agency['fields'], static fn ( $f ) => 'firma' === $f['name'] ) )[0]['source'] );
	check( 'agency: name-derived label', 'Telefon' === block_by( $b, 'telefon' )['attrs']['label'] && 'flinkform/field-phone' === block_by( $b, 'telefon' )['blockName'] );
	check( 'agency: number limits', [ '1', '500', '1' ] === [ block_by( $b, 'mitarbeiter' )['attrs']['min'], block_by( $b, 'mitarbeiter' )['attrs']['max'], block_by( $b, 'mitarbeiter' )['attrs']['step'] ] );
	$date = block_by( $b, 'termin' );
	check( 'agency: absolute date limit kept, relative dropped', '2027-12-31' === ( $date['attrs']['maxDate'] ?? '' ) && ! isset( $date['attrs']['minDate'] ) );
	check( 'agency: file left out without Pro', null === block_by( $b, 'anhang' ) && str_contains( notes_text( $agency ), 'needs Flinkform Pro' ) );
	check( 'agency: quiz left out', null === block_by( $b, 'quiz-1' ) );
	check( 'agency: [group] reported as unknown', str_contains( notes_text( $agency ), '[group]' ) );
	check( 'agency: hidden static value', 'website' === block_by( $b, 'quelle' )['attrs']['staticValue'] );
	check( 'agency: dynamic default flagged', str_contains( notes_text( $agency ), 'default:get' ) );
	$consent = block_by( $b, 'datenschutz' );
	check( 'agency: consent link to the privacy page becomes the placeholder', 'Ich habe die {privacy_policy} gelesen.' === ( $consent['attrs']['consentText'] ?? '' ), $consent['attrs']['consentText'] ?? '' );
	check( 'agency: escaped tag not imported', null === block_by( $b, 'escaped' ) );
	check( 'agency: submit label', 'Anfrage senden' === $agency['attrs']['submitLabel'] );
	check( 'agency: free text reported', str_contains( notes_text( $agency ), 'Bitte füllen Sie alle Pflichtfelder aus.' ) );
	$adm = $agency['attrs']['notifications']['admin'];
	check( 'agency: recipient keeps the address, drops the default tag', 'info@example.test' === ( $adm['to'] ?? '' ), $adm['to'] ?? '' );
	check( 'agency: [x] of a piped field = stored value, [_raw_x] = label', 'Neu: {field:projekt:value} / {field:projekt}' === $adm['subject'], $adm['subject'] );
	check( 'agency: HTML body to text, IP tag removed', ! str_contains( $adm['body'], '<p>' ) && str_contains( $adm['body'], 'Budget: {field:budget:value}' ) && ! str_contains( $adm['body'], 'remote_ip' ), $adm['body'] );
	check( 'agency: Bcc flagged', str_contains( notes_text( $agency ), 'Bcc' ) );
	check( 'agency: confirmation mail to a non-email field is flagged', ! isset( $agency['attrs']['notifications']['submitter'] ) && str_contains( notes_text( $agency ), 'Mail 2' ) );
	check( 'agency: skip_mail flagged', str_contains( notes_text( $agency ), 'skip_mail' ) );
	check( 'agency: red (things could not be carried over)', 'red' === $agency['status'] );
	$src = array_column( $agency['fields'], 'source', 'name' );
	check( 'agency: toggle, consent and hidden are never "guessed"', 'option' === $src['newsletter'] && 'consent' === $src['datenschutz'] && 'hidden' === $src['quelle'] );
	check( 'agency: no guess note for them', ! str_contains( notes_text( $agency ), '(field quelle)' ) && ! str_contains( notes_text( $agency ), '(field datenschutz)' ) && ! str_contains( notes_text( $agency ), '(field newsletter)' ) );
	check( 'agency: closing [/group] is not reported as text', ! str_contains( notes_text( $agency ), '[/group]' ) );

	// With Pro's file block available.
	$pro = $conv->convert( [ 'title' => 'x', 'markup' => $markup ], [ 'file_block' => true, 'privacy_url' => '', 'form_id' => 'u' ] );
	$file = block_by( $pro['blocks'], 'anhang' );
	check( 'pro: file field with types and size', 'flinkform/field-file' === ( $file['blockName'] ?? '' ) && [ 'pdf', 'jpg' ] === $file['attrs']['allowedTypes'] && 2 === $file['attrs']['maxSizeMb'] );

	// Confirmation mail proper.
	$m2 = $conv->convert( [ 'title' => 'x', 'markup' => '[email* your-email]', 'mail_2' => [ 'active' => true, 'recipient' => '[your-email]', 'subject' => 'Danke [your-email]', 'body' => 'Hallo' ] ], [ 'form_id' => 'u' ] );
	check( 'mail 2 becomes the submitter confirmation', [ 'enabled' => true, 'emailField' => 'your-email', 'subject' => 'Danke {field:your-email}', 'body' => 'Hallo' ] === $m2['attrs']['notifications']['submitter'] );

	check( 'duplicate names: second dropped and reported', 1 === count( $conv->convert( [ 'title' => 'x', 'markup' => '[text a][text a]' ], [ 'form_id' => 'u' ] )['fields'] ) );

	// --- Embeds -----------------------------------------------------------
	$form = [ 'id' => 42, 'hash' => 'abc1234def5678', 'title' => 'Kontakt', 'old_id' => 3 ];
	check( 'match by hash prefix', EmbedReplacer::matches( $form, [ 'id' => 'abc1234' ] ) );
	check( 'match by numeric id', EmbedReplacer::matches( $form, [ 'id' => '42' ] ) );
	check( 'no match for another id', ! EmbedReplacer::matches( $form, [ 'id' => '43' ] ) );
	check( 'match by title only when no id', EmbedReplacer::matches( $form, [ 'title' => 'Kontakt' ] ) && ! EmbedReplacer::matches( $form, [ 'id' => '43', 'title' => 'Kontakt' ] ) );
	check( 'legacy [contact-form 3]', EmbedReplacer::matches( $form, EmbedReplacer::parse_atts( 'contact-form', ' 3 "Kontakt"' ) ) );

	$page = "<!-- wp:paragraph -->\n<p>Intro [contact-form-7 id=\"42\"] inline</p>\n<!-- /wp:paragraph -->\n\n"
		. "<!-- wp:shortcode -->\n[contact-form-7 id=\"abc1234\" title=\"Kontakt\"]\n<!-- /wp:shortcode -->\n\n"
		. "<!-- wp:contact-form-7/contact-form-selector {\"id\":42,\"hash\":\"abc1234\",\"title\":\"Kontakt\"} -->\n<div class=\"wp-block-contact-form-7-contact-form-selector\">[contact-form-7 id=\"abc1234\" title=\"Kontakt\"]</div>\n<!-- /wp:contact-form-7/contact-form-selector -->\n\n"
		. "<!-- wp:paragraph -->\n<p>[contact-form-7 id=\"99\"]</p>\n<!-- /wp:paragraph -->";
	$r = EmbedReplacer::replace( $page, $form, 777 );
	check( 'shortcode block + CF7 block replaced', 2 === count( $r['replacements'] ) && 2 === substr_count( $r['content'], '<!-- wp:block {"ref":777} /-->' ) );
	check( 'no CF7 block wrapper left behind', ! str_contains( $r['content'], 'contact-form-selector' ) );
	check( 'inline shortcode untouched and counted', 1 === $r['inline'] && str_contains( $r['content'], 'Intro [contact-form-7 id="42"] inline' ) );
	check( 'other form untouched', str_contains( $r['content'], '[contact-form-7 id="99"]' ) );
	check( 'replacements in document order', str_contains( $r['replacements'][0][0], 'wp:shortcode' ) && str_contains( $r['replacements'][1][0], 'contact-form-selector' ) );

	$edited = "<!-- wp:heading --><h2>New</h2><!-- /wp:heading -->\n" . $r['content'];
	$back   = EmbedReplacer::revert( $edited, $r['replacements'] );
	check( 'undo restores the originals even after an edit', $back['content'] === "<!-- wp:heading --><h2>New</h2><!-- /wp:heading -->\n" . $page && 0 === $back['missing'] );
	$gone = EmbedReplacer::revert( str_replace( '<!-- wp:block {"ref":777} /-->', '', $r['content'] ), $r['replacements'] );
	check( 'undo counts replacements deleted since', 2 === $gone['missing'] );

	// Review find (critical): a paragraph with attributes BEFORE the
	// target paragraph must not be swallowed by the match.
	$greedy = "<!-- wp:paragraph {\"align\":\"center\"} -->\n<p>Willkommen</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Kontakt</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph {\"className\":\"x\"} -->\n<p>[contact-form-7 id=\"42\"]</p>\n<!-- /wp:paragraph -->";
	$rg = EmbedReplacer::replace( $greedy, $form, 9 );
	check( 'match never crosses block boundaries (content kept)', str_contains( $rg['content'], '<p>Willkommen</p>' ) && str_contains( $rg['content'], '<h2>Kontakt</h2>' ) && 1 === count( $rg['replacements'] ), $rg['content'] );
	check( 'only the target paragraph replaced', 1 === substr_count( $rg['replacements'][0][0] ?? '', '<!-- wp:' ) );
	$cf7greedy = "<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div>x</div>\n<!-- /wp:group -->\n<!-- wp:contact-form-7/contact-form-selector {\"id\":42} -->\n<div class=\"wp-block-contact-form-7-contact-form-selector\">[contact-form-7 id=\"42\"]</div>\n<!-- /wp:contact-form-7/contact-form-selector -->";
	$rc7 = EmbedReplacer::replace( $cf7greedy, $form, 9 );
	check( 'CF7 block match stays inside its block', str_contains( $rc7['content'], '<!-- wp:group' ) && 1 === count( $rc7['replacements'] ) );

	$classic = "Hallo\n\n[contact-form-7 id=\"abc1234\" title=\"Kontakt\"]\n\nTschüss";
	$rc = EmbedReplacer::replace( $classic, $form, 5 );
	check( 'classic content: shortcode on its own line replaced', "Hallo\n\n<!-- wp:block {\"ref\":5} /-->\n\nTschüss" === $rc['content'], $rc['content'] );

	echo "\n";
	if ( $failed ) { echo "$failed FAILED, $passed passed.\n"; exit( 1 ); }
	echo "All $passed tests passed.\n";
}
