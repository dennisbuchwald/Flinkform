<?php
/**
 * Contact Form 7 form-tag parser (1.15.0).
 *
 * Reads the `_form` markup of a CF7 form into a list of tags. Mirrors CF7's
 * own grammar (checked against CF7 6.1.7, form-tags-manager.php tag_regex()
 * and parse_atts()), with one deliberate difference: CF7 only matches tag
 * types that are registered right now, so a tag from a deactivated add-on
 * stays raw text there. We match any well-formed type and let the mapper
 * decide, so such tags can be reported instead of silently vanishing.
 *
 * Used whether CF7 is active or not: the grammar is the same, and CF7's own
 * scanner would drop exactly the tags we most need to report (those of a
 * deactivated add-on). CF7 itself is never loaded or called for parsing.
 *
 * Pure PHP, no WordPress, tested in tests/cf7-import-test.php.
 *
 * @package Flinkform
 * @since 1.15.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Import\Cf7;

defined( 'ABSPATH' ) || exit;

/**
 * Tag shape (array):
 *   type      e.g. 'text*'
 *   basetype  e.g. 'text'
 *   required  bool (trailing *)
 *   name      '' for nameless tags (submit, response)
 *   options   list of bare options: 'placeholder', 'min:3', 'class:x' …
 *   values    quoted values, before any pipe ("Label|value" → "Label")
 *   pipes     list of [ before, after ] for each quoted value
 *   content   text between [tag]…[/tag], '' otherwise
 *   offset    byte offset of the tag in the markup
 *   length    byte length of the whole match
 */
final class TagParser {

	/**
	 * CF7's whitespace class (formatting.php), as a regex character-class body.
	 */
	private const WS = '\x09-\x0D\x20\x85\xA0\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}';

	/**
	 * Parse form markup into tags, in document order.
	 *
	 * @param string $markup CF7 `_form` value.
	 * @return array<int, array<string, mixed>>
	 */
	public static function parse( string $markup ): array {
		$ws    = self::WS;
		$types = '[a-zA-Z][0-9a-zA-Z:._-]*\*?';
		// Same structure as WPCF7_FormTagsManager::tag_regex(): escaped
		// [[tag]] (groups 1 and 6), type (2), attributes (3), self-closing
		// slash (4), enclosed content (5, must not contain "[").
		$regex = '/(\[?)\[(' . $types . ')(?:[' . $ws . ']+(.*?))?(?:[' . $ws . ']+(\/))?\](?:([^[]*?)\[\/\2\])?(\]?)/su';

		if ( ! preg_match_all( $regex, $markup, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return [];
		}

		$tags = [];
		foreach ( $matches as $m ) {
			// [[escaped]] is literal text in CF7.
			if ( '[' === $m[1][0] && ']' === ( $m[6][0] ?? '' ) ) {
				continue;
			}
			$type     = $m[2][0];
			$required = '*' === substr( $type, -1 );
			$basetype = rtrim( $type, '*' );
			$atts     = self::parse_atts( isset( $m[3] ) ? (string) $m[3][0] : '' );

			$name = '';
			// Nameless types: the first option is not a name there.
			if ( ! in_array( $basetype, [ 'submit', 'response', 'reflection', 'recaptcha', 'turnstile' ], true ) && ! empty( $atts['options'] ) ) {
				$candidate = $atts['options'][0];
				if ( preg_match( '/^[A-Za-z][-A-Za-z0-9_:.]*$/', $candidate ) ) {
					$name = strtr( $candidate, '.', '_' );
					array_shift( $atts['options'] );
				}
			}

			$pipes  = [];
			$values = [];
			foreach ( $atts['values'] as $raw ) {
				$pos = strpos( $raw, '|' );
				if ( false === $pos ) {
					$pipes[]  = [ $raw, $raw ];
					$values[] = $raw;
				} else {
					$pipes[]  = [ substr( $raw, 0, $pos ), substr( $raw, $pos + 1 ) ];
					$values[] = substr( $raw, 0, $pos );
				}
			}

			$tags[] = [
				'type'     => $type,
				'basetype' => $basetype,
				'required' => $required,
				'name'     => $name,
				'options'  => $atts['options'],
				'values'   => $values,
				'pipes'    => $pipes,
				'content'  => isset( $m[5] ) && -1 !== $m[5][1] ? (string) $m[5][0] : '',
				'offset'   => (int) $m[0][1],
				'length'   => strlen( $m[0][0] ),
			];
		}
		return $tags;
	}

	/**
	 * Split the attribute string: options first, then quoted values
	 * (WPCF7_FormTagsManager::parse_atts()).
	 *
	 * @param string $text
	 * @return array{options: array<int, string>, values: array<int, string>}
	 */
	private static function parse_atts( string $text ): array {
		$ws   = self::WS;
		$atts = [ 'options' => [], 'values' => [] ];
		$text = trim( $text );
		if ( '' === $text ) {
			return $atts;
		}
		$pattern = '%^([-+*=0-9a-zA-Z:.!?#$&@_/|\%' . $ws . ']*?)((?:[' . $ws . ']*"[^"]*"|[' . $ws . ']*\'[^\']*\')*)$%u';
		if ( preg_match( $pattern, $text, $m ) ) {
			if ( '' !== trim( $m[1] ) ) {
				$atts['options'] = array_values( array_filter( (array) preg_split( '/[' . $ws . ']+/u', trim( $m[1] ) ), 'strlen' ) );
			}
			if ( '' !== trim( $m[2] ) ) {
				preg_match_all( '/"[^"]*"|\'[^\']*\'/', $m[2], $vm );
				$atts['values'] = array_map( static fn ( $v ) => substr( $v, 1, -1 ), $vm[0] );
			}
		} else {
			// Malformed: CF7 treats the whole thing as one value.
			$atts['values'] = [ $text ];
		}
		return $atts;
	}

	/**
	 * Option lookup: 'min:3' → '3' for option('min'), true for flags.
	 *
	 * @param array<string, mixed> $tag
	 * @param string               $name
	 * @return string|bool|null Null when absent.
	 */
	public static function option( array $tag, string $name ) {
		foreach ( $tag['options'] as $opt ) {
			if ( $opt === $name ) {
				return true;
			}
			if ( 0 === strpos( $opt, $name . ':' ) ) {
				return substr( $opt, strlen( $name ) + 1 );
			}
		}
		return null;
	}
}
