<?php
/**
 * Works out which text in CF7's free-form markup labels which field
 * (1.15.0). The unreliable part of the import, so every result says where
 * it came from and the preview flags the weak ones.
 *
 * Order (FLINKFORM_CF7_IMPORT.md §3):
 *   1. the tag sits in a <label>: the label's text      → 'label'
 *   2. text on the same line, else the line before       → 'text'
 *   3. the placeholder                                    → 'placeholder' (check)
 *   4. the field name, humanised (your-name → Name)       → 'name' (check)
 *
 * Also returns what is left over: headings (become section headings) and
 * free text nobody used as a label (reported, not imported).
 *
 * Pure PHP, tested in tests/cf7-import-test.php.
 *
 * @package Flinkform
 * @since 1.15.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Import\Cf7;

defined( 'ABSPATH' ) || exit;

/**
 * Label heuristics over CF7 markup.
 */
final class LabelGuesser {

	/**
	 * Analyse the markup around the parsed tags.
	 *
	 * @param string                           $markup CF7 `_form`.
	 * @param array<int, array<string, mixed>> $tags   From TagParser::parse().
	 * @return array{labels: array<int, array{text: string, source: string}>, headings: array<int, array{offset: int, text: string, level: int}>, leftover: array<int, string>}
	 */
	public static function analyse( string $markup, array $tags ): array {
		$labels   = [];
		$headings = [];
		$leftover = [];

		// Headings anywhere in the markup become section headings.
		if ( preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $markup, $hm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $hm as $h ) {
				$text = self::clean( $h[2][0] );
				if ( '' !== $text ) {
					$headings[] = [ 'offset' => (int) $h[0][1], 'text' => $text, 'level' => (int) $h[1][0] ];
				}
			}
		}
		// Blank out headings (same length, offsets stay valid) so their
		// text is never taken for a label.
		$work = preg_replace_callback( '/<h([1-6])[^>]*>.*?<\/h\1>/is', static fn ( $m ) => str_repeat( ' ', strlen( $m[0] ) ), $markup ) ?? $markup;

		$prev_end = 0;
		foreach ( $tags as $i => $tag ) {
			$start = (int) $tag['offset'];
			$end   = $start + (int) $tag['length'];
			$gap   = substr( $work, $prev_end, max( 0, $start - $prev_end ) );

			$label = self::from_label_element( $work, $start, $end );
			if ( null !== $label && '' !== $label ) {
				$labels[ $i ] = [ 'text' => $label, 'source' => 'label' ];
				// What stood before the <label> in the gap is free text.
				$open = strripos( $gap, '<label' );
				$before = false === $open ? '' : substr( $gap, 0, $open );
				self::collect_leftover( $before, $leftover );
			} else {
				[ $line, $rest ] = self::last_line( $gap );
				if ( '' !== $line && self::is_labelish( $tag ) ) {
					$labels[ $i ] = [ 'text' => $line, 'source' => 'text' ];
					self::collect_leftover( $rest, $leftover );
				} else {
					self::collect_leftover( $gap, $leftover );
				}
			}

			// Skip past a closing </label> that belongs to this tag.
			$after = substr( $work, $end, 400 );
			if ( preg_match( '/^[^<\[]*<\/label>/i', $after, $lm ) ) {
				$end += strlen( $lm[0] );
			}
			$prev_end = $end;
		}
		self::collect_leftover( substr( $work, $prev_end ), $leftover );

		return [ 'labels' => $labels, 'headings' => $headings, 'leftover' => $leftover ];
	}

	/**
	 * Fallback label from the tag itself (placeholder, then name).
	 *
	 * @param array<string, mixed> $tag
	 * @return array{text: string, source: string}
	 */
	public static function fallback( array $tag ): array {
		if ( null !== TagParser::option( $tag, 'placeholder' ) || null !== TagParser::option( $tag, 'watermark' ) ) {
			$first = $tag['values'][0] ?? '';
			if ( '' !== trim( (string) $first ) ) {
				return [ 'text' => trim( (string) $first ), 'source' => 'placeholder' ];
			}
		}
		return [ 'text' => self::humanise( (string) $tag['name'] ), 'source' => 'name' ];
	}

	/**
	 * your-name → Name, company_name → Company name.
	 *
	 * @param string $name
	 * @return string
	 */
	public static function humanise( string $name ): string {
		$name = preg_replace( '/^your[-_]/i', '', $name ) ?? $name;
		$name = trim( str_replace( [ '-', '_', ':' ], ' ', $name ) );
		return '' === $name ? '' : ucfirst( $name );
	}

	/**
	 * Tags that can carry a label (not submit buttons, captchas, …).
	 *
	 * @param array<string, mixed> $tag
	 * @return bool
	 */
	private static function is_labelish( array $tag ): bool {
		return '' !== (string) $tag['name'] && ! in_array( $tag['basetype'], [ 'submit', 'hidden', 'acceptance', 'quiz', 'recaptcha', 'turnstile', 'response' ], true );
	}

	/**
	 * Text of the <label> element enclosing [start, end), or null.
	 */
	private static function from_label_element( string $work, int $start, int $end ): ?string {
		$before = substr( $work, 0, $start );
		$open   = strripos( $before, '<label' );
		if ( false === $open ) {
			return null;
		}
		$close_before = strripos( $before, '</label>' );
		if ( false !== $close_before && $close_before > $open ) {
			return null; // The last <label> was already closed.
		}
		$open_end = strpos( $work, '>', $open );
		if ( false === $open_end || $open_end > $start ) {
			return null;
		}
		$text = self::clean( substr( $work, $open_end + 1, $start - $open_end - 1 ) );
		if ( '' === $text ) {
			// Label text after the tag: <label>[checkbox x] I agree</label>.
			$close = stripos( $work, '</label>', $end );
			if ( false !== $close ) {
				$text = self::clean( substr( $work, $end, $close - $end ) );
			}
		}
		return $text;
	}

	/**
	 * Split a gap into its last non-empty line and everything before it.
	 * Lines end at newlines, <br>, and paragraph/div boundaries.
	 *
	 * @return array{0: string, 1: string}
	 */
	private static function last_line( string $gap ): array {
		$parts = preg_split( '/\r?\n|<br\s*\/?>|<\/?(?:p|div|li|td|tr)[^>]*>/i', $gap ) ?: [];
		$line  = '';
		while ( ! empty( $parts ) ) {
			$candidate = self::clean( (string) array_pop( $parts ) );
			if ( '' !== $candidate ) {
				$line = $candidate;
				break;
			}
		}
		return [ $line, implode( "\n", $parts ) ];
	}

	/**
	 * Remember free text that did not become a label.
	 *
	 * @param string              $chunk
	 * @param array<int, string>  $leftover
	 */
	private static function collect_leftover( string $chunk, array &$leftover ): void {
		// Closing tags of enclosing add-on tags ([/group]) are not text.
		$chunk = preg_replace( '/\[\/[a-zA-Z][0-9a-zA-Z:._-]*\]/', ' ', $chunk ) ?? $chunk;
		$text  = self::clean( $chunk );
		if ( '' !== $text ) {
			$leftover[] = $text;
		}
	}

	/**
	 * Strip markup, required markers and whitespace.
	 */
	private static function clean( string $html ): string {
		$text = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\((?:required|pflichtfeld|erforderlich|optional)\)|\*/iu', '', $text ) ?? $text;
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
		return trim( $text, " \t\n\r\0\x0B:" );
	}
}
