#!/usr/bin/env php
<?php
/**
 * Standalone unit tests for the notification body builder.
 *
 * The generated admin mail used to be a dense list of `Label: value` lines
 * and the feedback was, fairly: cramped and technical. What matters now is
 * mostly what the mail leaves OUT and how it renders what it keeps — so
 * that is what these pin.
 *
 * Run:  php tests/mail-body-test.php
 *
 * No PHPUnit required — exits 0 on success, 1 on failure.
 *
 * @package Flinkform
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_url( $text ) {
		return filter_var( (string) $text, FILTER_SANITIZE_URL );
	}
	function __( $text, $domain = '' ) {
		return $text;
	}
}

require_once __DIR__ . '/../includes/Notifications/BodyBuilder.php';

use Flinkform\Notifications\BodyBuilder;

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

$fields = [
	[ 'name' => 'art-der-betreeung', 'label' => 'Wonach suchst du? (Art der Betreuung)', 'type' => 'select' ],
	[ 'name' => 'plz-wochenbett',    'label' => 'Deine Postleitzahl',                    'type' => 'select' ],
	[ 'name' => 'name',              'label' => 'Name',                                  'type' => 'text' ],
	[ 'name' => 'email',             'label' => 'Email',                                 'type' => 'email' ],
	[ 'name' => 'telefon',           'label' => 'Telefon',                               'type' => 'phone' ],
	[ 'name' => 'date_ojb5bo',       'label' => 'Errechneter Entbindungstermin',          'type' => 'date' ],
	[ 'name' => 'address_x_street',  'label' => 'Adresse - Straße',                       'type' => 'text' ],
	[ 'name' => 'textarea_amtg4c',   'label' => 'Deine Kontaktanfrage',                   'type' => 'textarea' ],
	[ 'name' => 'wuensche',          'label' => 'Wünsche',                                'type' => 'checkbox' ],
];

$values = [
	'art-der-betreeung' => 'Schwangerschaft & Wochenbett',
	'name'              => 'Anna Beispiel',
	'email'             => 'anna@example.org',
	'telefon'           => '07131 / 123 456',
	'date_ojb5bo'       => '2027-04-29',
	'address_x_street'  => 'Binswanger Str. 31',
	'textarea_amtg4c'   => "Hallo Lea,\n\nich bin in der 12. Woche.\nMelde dich gern!",
	'wuensche'          => [ 'Hausbesuch', 'Stillberatung' ],
	// plz-wochenbett deliberately absent — a conditionally hidden field
	// never reaches the mailer, and must not surface as an empty row.
];

$rows = BodyBuilder::rows( $fields, $values );

// --- What gets left out --------------------------------------------------

$labels = array_column( $rows, 'label' );
check( 'a hidden field produces no row', ! in_array( 'Deine Postleitzahl', $labels, true ), implode( ' | ', $labels ) );
check( 'eight filled fields produce eight rows', 8 === count( $rows ), (string) count( $rows ) );

$rows_with_blank = BodyBuilder::rows( $fields, $values + [ 'plz-wochenbett' => '' ] );
check( 'an empty value produces no row', 8 === count( $rows_with_blank ), (string) count( $rows_with_blank ) );

$rows_with_spaces = BodyBuilder::rows( $fields, $values + [ 'plz-wochenbett' => '   ' ] );
check( 'a whitespace-only value produces no row', 8 === count( $rows_with_spaces ) );

$rows_empty_array = BodyBuilder::rows( [ [ 'name' => 'a', 'label' => 'A', 'type' => 'checkbox' ] ], [ 'a' => [] ] );
check( 'an empty array produces no row', 0 === count( $rows_empty_array ) );

// --- Labels, never internal names ----------------------------------------

$html = BodyBuilder::html( $rows, [ 'intro' => 'Neue Anfrage', 'outro' => '', 'footer' => 'Fuß' ] );
$text = BodyBuilder::text( $rows, [ 'intro' => 'Neue Anfrage', 'outro' => '', 'footer' => 'Fuß' ] );

foreach ( [ 'date_ojb5bo', 'textarea_amtg4c', 'address_x_street', 'art-der-betreeung' ] as $internal ) {
	check( "HTML never shows the internal name $internal", ! str_contains( $html, $internal ) );
	check( "text never shows the internal name $internal", ! str_contains( $text, $internal ) );
}
check( 'HTML uses the label', str_contains( $html, 'Errechneter Entbindungstermin' ) );
check( 'text uses the label', str_contains( $text, 'Errechneter Entbindungstermin' ) );

// --- Value formatting ------------------------------------------------------

check( 'date rendered as TT.MM.JJJJ in HTML', str_contains( $html, '29.04.2027' ) && ! str_contains( $html, '2027-04-29' ), 'date' );
check( 'date rendered as TT.MM.JJJJ in text', str_contains( $text, '29.04.2027' ) && ! str_contains( $text, '2027-04-29' ) );
check( 'email is a mailto link', str_contains( $html, 'href="mailto:anna@example.org"' ) );
check( 'phone is a tel link with dialling characters only', str_contains( $html, 'href="tel:07131123456"' ), $html );
check( 'phone still displays what was typed', str_contains( $html, '07131 / 123 456' ) );
check( 'textarea keeps its line breaks', str_contains( $html, '<br />' ) || str_contains( $html, '<br>' ) );
check( 'multi-value renders as a list', str_contains( $html, '<li' ) && str_contains( $html, 'Stillberatung' ) );
check( 'multi-value in text is one per line', str_contains( $text, "  Hausbesuch\n  Stillberatung" ), $text );

// A non-ISO date is left alone rather than mangled by a guess.
$odd = BodyBuilder::html( BodyBuilder::rows( [ [ 'name' => 'd', 'label' => 'D', 'type' => 'date' ] ], [ 'd' => 'irgendwann' ] ), [] );
check( 'a non-ISO date is passed through untouched', str_contains( $odd, 'irgendwann' ) );

// --- Escaping ---------------------------------------------------------------

$evil = BodyBuilder::html(
	BodyBuilder::rows( [ [ 'name' => 'x', 'label' => '<script>bad</script>', 'type' => 'text' ] ], [ 'x' => '<img src=x onerror=alert(1)>' ] ),
	[ 'intro' => '<b>hi</b>', 'outro' => '', 'footer' => '' ]
);
check( 'a label cannot inject markup', ! str_contains( $evil, '<script>bad' ), $evil );
check( 'a value cannot inject markup', ! str_contains( $evil, '<img src=x' ) );
check( 'the intro cannot inject markup', ! str_contains( $evil, '<b>hi</b>' ) );

// --- Structure --------------------------------------------------------------

check( 'HTML declares a viewport for phones', str_contains( $html, 'width=device-width' ) );
check( 'HTML styles are inline, no style block', ! str_contains( $html, '<style' ) );
check( 'HTML loads nothing external', ! preg_match( '/(src|href)="https?:/', str_replace( 'mailto:', '', $html ) ), 'external reference' );
check( 'rows are separated by a rule, not crammed', substr_count( $html, 'border-top:1px solid' ) >= count( $rows ) - 1 );
check( 'text puts a blank line between rows', str_contains( $text, "Name\n  Anna Beispiel\n\n" ), $text );

// --- An author's own body is wrapped, not rewritten ---------------------------

$custom  = "Liebe werdende Mama,\n\nvielen Dank für deine Nachricht.\n\nLiebe Grüße\nLea";
$wrapped = BodyBuilder::html_from_text( $custom, [ 'footer' => 'Fuß' ] );
check( 'every paragraph of a custom body survives', 3 === substr_count( $wrapped, '<p style="margin:0 0 16px;">' ), $wrapped );
check( 'a custom body keeps its wording', str_contains( $wrapped, 'vielen Dank für deine Nachricht.' ) );
check( 'a single newline stays a line break', str_contains( $wrapped, 'Liebe Grüße<br />' ) || str_contains( $wrapped, 'Liebe Grüße<br>' ) );
check( 'a custom body is escaped', ! str_contains( BodyBuilder::html_from_text( '<script>x</script>' ), '<script>x' ) );

// --- Summary -----------------------------------------------------------------

echo "\n";
if ( $failed > 0 ) {
	echo "$failed FAILED, $passed passed.\n";
	exit( 1 );
}
echo "All $passed tests passed.\n";
exit( 0 );
