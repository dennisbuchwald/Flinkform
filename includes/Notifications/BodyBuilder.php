<?php
/**
 * Builds the notification email body in both HTML and plain text.
 *
 * The generated admin notification used to be a dense list of
 * `Label: value` lines. The feedback from a site owner reading these on a
 * phone was, fairly: cramped, and technical. So this produces a proper
 * multipart body — readable HTML with room to breathe, and a plain-text
 * alternative that says the same thing for clients that will not render
 * HTML.
 *
 * Design constraints, in order of importance:
 *
 *   - Always the field's LABEL, never its internal name. `date_ojb5bo`
 *     means nothing to the person reading the mail.
 *   - Only fields that were actually filled in. A list padded with
 *     "Telefon: -" is noise, and a conditionally hidden field is not
 *     merely empty, it was never part of this submission.
 *   - Values in the shape a human writes them: a date as 27.04.2027 rather
 *     than 2027-04-27, a multi-line message with its line breaks intact,
 *     an email address you can tap.
 *   - Inline styles only, system fonts, no images, no external anything.
 *     Mail clients strip <style> blocks, and a notification is not the
 *     place for a design that might not survive the trip.
 *
 * @package Flinkform
 * @since 1.10.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Renders submission values into a readable email body.
 */
final class BodyBuilder {

	/**
	 * Shared type styling. Kept in one place so the HTML below stays
	 * readable and the two halves of a row cannot drift apart.
	 */
	private const FONT       = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
	private const INK        = '#1f2430';
	private const INK_MUTED  = '#6b7280';
	private const LINE       = '#e5e7eb';
	private const SURFACE    = '#f6f7f9';

	/**
	 * Turn a submission into the rows an email should show.
	 *
	 * Skips anything the recipient would gain nothing from: fields with no
	 * value, and fields that are not in the values map at all — which is
	 * how a conditionally hidden field arrives here, the Handler having
	 * stripped it before this ever runs.
	 *
	 * @param array<int, array<string, mixed>> $fields Field records from the Locator.
	 * @param array<string, mixed>             $values Sanitised submission values.
	 * @return array<int, array{label: string, value: mixed, type: string}>
	 */
	public static function rows( array $fields, array $values ): array {
		$rows = [];

		foreach ( $fields as $field ) {
			$name = (string) ( $field['name'] ?? '' );
			if ( '' === $name || ! array_key_exists( $name, $values ) ) {
				continue;
			}

			$value = $values[ $name ];
			if ( self::is_blank( $value ) ) {
				continue;
			}

			$rows[] = [
				'label' => (string) ( $field['label'] ?? $name ),
				'value' => $value,
				'type'  => (string) ( $field['type'] ?? 'text' ),
			];
		}

		return $rows;
	}

	/**
	 * The HTML half of the notification.
	 *
	 * @param array<int, array{label: string, value: mixed, type: string}> $rows
	 * @param array<string, string>                                        $parts Intro, outro, footer.
	 * @return string
	 */
	public static function html( array $rows, array $parts ): string {
		$out  = '<!DOCTYPE html><html><head><meta charset="utf-8">';
		$out .= '<meta name="viewport" content="width=device-width, initial-scale=1"></head>';
		$out .= '<body style="margin:0;padding:0;background:' . self::SURFACE . ';">';
		$out .= '<div style="margin:0 auto;padding:24px 16px;max-width:600px;font-family:' . self::FONT . ';';
		$out .= 'font-size:16px;line-height:1.6;color:' . self::INK . ';">';

		if ( '' !== ( $parts['intro'] ?? '' ) ) {
			$out .= '<p style="margin:0 0 24px;font-size:18px;font-weight:600;">'
				. esc_html( $parts['intro'] ) . '</p>';
		}

		$out .= '<div style="background:#ffffff;border:1px solid ' . self::LINE . ';border-radius:10px;padding:8px 20px;">';

		foreach ( $rows as $index => $row ) {
			// A rule between rows rather than around them: the first row
			// needs no line above it and the last none below.
			$border = $index > 0 ? 'border-top:1px solid ' . self::LINE . ';' : '';
			$out   .= '<div style="' . $border . 'padding:16px 0;">';
			$out   .= '<div style="font-size:13px;letter-spacing:0.02em;text-transform:uppercase;color:'
				. self::INK_MUTED . ';margin-bottom:6px;">' . esc_html( $row['label'] ) . '</div>';
			$out   .= '<div style="font-size:16px;">' . self::format_html( $row['value'], $row['type'] ) . '</div>';
			$out   .= '</div>';
		}

		$out .= '</div>';

		if ( '' !== ( $parts['outro'] ?? '' ) ) {
			$out .= '<p style="margin:24px 0 0;color:' . self::INK_MUTED . ';font-size:15px;">'
				. esc_html( $parts['outro'] ) . '</p>';
		}

		if ( '' !== ( $parts['footer'] ?? '' ) ) {
			$out .= '<p style="margin:24px 0 0;padding-top:16px;border-top:1px solid ' . self::LINE . ';'
				. 'color:' . self::INK_MUTED . ';font-size:13px;">' . esc_html( $parts['footer'] ) . '</p>';
		}

		$out .= '</div></body></html>';

		return $out;
	}

	/**
	 * The plain-text half — same content, same order, no markup.
	 *
	 * Not a fallback nobody looks at: it is what lands in a plain-text
	 * client and in most notification previews, so it gets the same blank
	 * line between rows that makes the HTML readable.
	 *
	 * @param array<int, array{label: string, value: mixed, type: string}> $rows
	 * @param array<string, string>                                        $parts
	 * @return string
	 */
	public static function text( array $rows, array $parts ): string {
		$lines = [];

		if ( '' !== ( $parts['intro'] ?? '' ) ) {
			$lines[] = $parts['intro'];
			$lines[] = '';
		}

		foreach ( $rows as $row ) {
			$lines[] = $row['label'];
			foreach ( explode( "\n", self::format_text( $row['value'], $row['type'] ) ) as $line ) {
				$lines[] = '  ' . $line;
			}
			$lines[] = '';
		}

		if ( '' !== ( $parts['outro'] ?? '' ) ) {
			$lines[] = $parts['outro'];
			$lines[] = '';
		}

		if ( '' !== ( $parts['footer'] ?? '' ) ) {
			$lines[] = '--';
			$lines[] = $parts['footer'];
		}

		return rtrim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * Wrap an author's own body text in the same shell.
	 *
	 * Their words are not touched — escaped, with blank lines becoming
	 * paragraphs so the shape they typed survives. An author who wrote
	 * prose gets prose, just legible on a phone.
	 *
	 * @param string                $body  Already merge-tag-resolved body text.
	 * @param array<string, string> $parts Footer only; a custom body brings its own greeting.
	 * @return string
	 */
	public static function html_from_text( string $body, array $parts = [] ): string {
		$out  = '<!DOCTYPE html><html><head><meta charset="utf-8">';
		$out .= '<meta name="viewport" content="width=device-width, initial-scale=1"></head>';
		$out .= '<body style="margin:0;padding:0;background:' . self::SURFACE . ';">';
		$out .= '<div style="margin:0 auto;padding:24px 16px;max-width:600px;font-family:' . self::FONT . ';';
		$out .= 'font-size:16px;line-height:1.6;color:' . self::INK . ';">';
		$out .= '<div style="background:#ffffff;border:1px solid ' . self::LINE . ';border-radius:10px;padding:24px;">';

		foreach ( preg_split( '/\n\s*\n/', trim( $body ) ) as $paragraph ) {
			$out .= '<p style="margin:0 0 16px;">' . nl2br( esc_html( trim( $paragraph ) ) ) . '</p>';
		}

		$out .= '</div>';

		if ( '' !== ( $parts['footer'] ?? '' ) ) {
			$out .= '<p style="margin:24px 0 0;padding-top:16px;border-top:1px solid ' . self::LINE . ';'
				. 'color:' . self::INK_MUTED . ';font-size:13px;">' . esc_html( $parts['footer'] ) . '</p>';
		}

		$out .= '</div></body></html>';

		return $out;
	}

	/**
	 * Is there nothing worth printing for this value?
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private static function is_blank( $value ): bool {
		if ( is_array( $value ) ) {
			return empty( array_filter( $value, static fn ( $v ) => '' !== trim( (string) $v ) ) );
		}
		return '' === trim( (string) $value );
	}

	/**
	 * Render one value as HTML.
	 *
	 * @param mixed  $value
	 * @param string $type Field type from the Locator.
	 * @return string
	 */
	private static function format_html( $value, string $type ): string {
		if ( is_array( $value ) ) {
			$items = array_map(
				static fn ( $v ) => '<li style="margin:0 0 4px;">' . esc_html( (string) $v ) . '</li>',
				array_filter( $value, static fn ( $v ) => '' !== trim( (string) $v ) )
			);
			return '<ul style="margin:0;padding-left:20px;">' . implode( '', $items ) . '</ul>';
		}

		$text = (string) $value;

		switch ( $type ) {
			case 'email':
				return '<a href="mailto:' . esc_attr( $text ) . '" style="color:#2563eb;">' . esc_html( $text ) . '</a>';
			case 'phone':
				// Strip the characters a dialler cannot use, but show what
				// the visitor actually typed.
				$dial = preg_replace( '/[^\d+]/', '', $text );
				return '<a href="tel:' . esc_attr( (string) $dial ) . '" style="color:#2563eb;">' . esc_html( $text ) . '</a>';
			case 'url':
				return '<a href="' . esc_url( $text ) . '" style="color:#2563eb;">' . esc_html( $text ) . '</a>';
			case 'date':
				return esc_html( self::format_date( $text ) );
			case 'textarea':
				return nl2br( esc_html( $text ) );
		}

		return esc_html( $text );
	}

	/**
	 * Render one value as plain text.
	 *
	 * @param mixed  $value
	 * @param string $type
	 * @return string
	 */
	private static function format_text( $value, string $type ): string {
		if ( is_array( $value ) ) {
			return implode( "\n", array_filter( $value, static fn ( $v ) => '' !== trim( (string) $v ) ) );
		}

		$text = (string) $value;

		if ( 'date' === $type ) {
			return self::format_date( $text );
		}

		return $text;
	}

	/**
	 * A date input submits ISO (2027-04-29); nobody reads dates that way in
	 * German. Anything that is not a plain ISO date is left alone rather
	 * than guessed at.
	 *
	 * @param string $value
	 * @return string
	 */
	private static function format_date( string $value ): string {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', trim( $value ), $m ) ) {
			return $value;
		}
		return $m[3] . '.' . $m[2] . '.' . $m[1];
	}
}
