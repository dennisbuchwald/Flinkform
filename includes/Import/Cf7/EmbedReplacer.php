<?php
/**
 * Finds the places a CF7 form is embedded in post content and swaps them
 * for the imported synced pattern (1.15.0).
 *
 * Replaced (whole block, so no half-block is left behind):
 *   - the CF7 block contact-form-7/contact-form-selector (static: its saved
 *     HTML holds the shortcode, so matching the shortcode alone would leave
 *     an empty CF7 block wrapper);
 *   - a core/shortcode or core/paragraph block holding nothing but the
 *     shortcode;
 *   - a bare shortcode on its own line in classic (block-less) content.
 * A shortcode inside running text is reported, never touched.
 *
 * Every replacement is returned as [ original snippet, new snippet ], which
 * is all undo needs: put the original back where the new one stands. That
 * works even after the page was edited again.
 *
 * Matches the form the way CF7's shortcode does (CF7 6.1.7): hash prefix
 * (7+ hex chars), then numeric post ID, then title; the legacy
 * [contact-form N] shortcode by its old unit ID.
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
 * Embed discovery and replacement.
 */
final class EmbedReplacer {

	private const SHORTCODE = '\[(contact-form-7|contact-form)(\s[^\]]*)?\]';

	/**
	 * Replace every embed of one CF7 form in a post's content.
	 *
	 * @param string               $content Post content.
	 * @param array<string, mixed> $form    { id: int, hash: string, title: string, old_id: int }
	 * @param int                  $ref     wp_block post ID of the imported pattern.
	 * @return array{content: string, replacements: array<int, array{0: string, 1: string}>, inline: int}
	 */
	public static function replace( string $content, array $form, int $ref ): array {
		$pattern_block = '<!-- wp:block {"ref":' . $ref . '} /-->';
		$replacements  = [];

		// One pass, one combined pattern, so replacements are recorded in
		// document order (undo depends on that order when a page holds the
		// same form twice in different wrappers).
		$sc      = self::SHORTCODE;
		$classic = false === strpos( $content, '<!-- wp:' );
		$parts   = [
			// 1. CF7 block (static: its saved HTML holds the shortcode).
			'(?<cf7><!-- wp:contact-form-7/contact-form-selector(?:\s+(?<cf7attrs>\{(?:(?!-->).)*?\}))?\s*(?:/-->|-->.*?<!-- /wp:contact-form-7/contact-form-selector -->))',
			// 2. core/shortcode or core/paragraph holding only the shortcode.
			'(?<blk><!-- wp:(?<bname>shortcode|paragraph)(?:\s+\{(?:(?!-->).)*?\})?\s*-->\s*(?:<p[^>]*>)?\s*\[(?<btag>contact-form-7|contact-form)(?<batts>\s[^\]]*)?\]\s*(?:</p>)?\s*<!-- /wp:(?P=bname) -->)',
		];
		if ( $classic ) {
			// 3. Classic content: a shortcode alone on its line or in a <p>.
			$parts[] = '(?<cls>(?<=^|\n)[ \t]*(?:<p>)?[ \t]*\[(?<ctag>contact-form-7|contact-form)(?<catts>\s[^\]]*)?\][ \t]*(?:</p>)?[ \t]*(?=\r?\n|$))';
		}
		$content = (string) preg_replace_callback(
			'#' . implode( '|', $parts ) . '#s',
			static function ( array $m ) use ( $form, $pattern_block, &$replacements ): string {
				if ( '' !== ( $m['cf7'] ?? '' ) ) {
					$attrs = '' !== ( $m['cf7attrs'] ?? '' ) ? json_decode( $m['cf7attrs'], true ) : null;
					$attrs = is_array( $attrs ) ? $attrs : [];
					$inner = self::shortcode_atts_in( $m[0] );
					$ref   = [
						'id'    => (string) ( $attrs['id'] ?? ( $inner['id'] ?? '' ) ),
						'hash'  => (string) ( $attrs['hash'] ?? '' ),
						'title' => (string) ( $attrs['title'] ?? ( $inner['title'] ?? '' ) ),
					];
				} elseif ( '' !== ( $m['blk'] ?? '' ) ) {
					$ref = self::parse_atts( $m['btag'], $m['batts'] ?? '' );
				} else {
					$ref = self::parse_atts( $m['ctag'] ?? '', $m['catts'] ?? '' );
				}
				// Belt and braces: a match spanning more than one block
				// opener has run across block boundaries. Never replace
				// that, it would delete everything in between.
				if ( substr_count( $m[0], '<!-- wp:' ) > 1 ) {
					return $m[0];
				}
				if ( self::matches( $form, $ref ) ) {
					$replacements[] = [ $m[0], $pattern_block ];
					return $pattern_block;
				}
				return $m[0];
			},
			$content
		);

		// What is left of this form is inside running text: report only.
		$inline = 0;
		if ( preg_match_all( '#' . self::SHORTCODE . '#', $content, $rest, PREG_SET_ORDER ) ) {
			foreach ( $rest as $r ) {
				if ( self::matches( $form, self::parse_atts( $r[1], $r[2] ?? '' ) ) ) {
					++$inline;
				}
			}
		}

		return [ 'content' => $content, 'replacements' => $replacements, 'inline' => $inline ];
	}

	/**
	 * Undo: put each original snippet back where its replacement stands.
	 * Replacements that are no longer in the content (block deleted since)
	 * are skipped and counted.
	 *
	 * @param string                               $content
	 * @param array<int, array{0: string, 1: string}> $replacements
	 * @return array{content: string, missing: int}
	 */
	public static function revert( string $content, array $replacements ): array {
		$missing = 0;
		foreach ( $replacements as [ $original, $replacement ] ) {
			$pos = strpos( $content, $replacement );
			if ( false === $pos ) {
				++$missing;
				continue;
			}
			$content = substr_replace( $content, $original, $pos, strlen( $replacement ) );
		}
		return [ 'content' => $content, 'missing' => $missing ];
	}

	/**
	 * Does a shortcode/block reference point at this form?
	 *
	 * @param array<string, mixed> $form
	 * @param array<string, mixed> $ref  { id, hash, title, legacy_id }
	 * @return bool
	 */
	public static function matches( array $form, array $ref ): bool {
		$id = (string) ( $ref['id'] ?? '' );
		if ( isset( $ref['legacy_id'] ) && '' !== (string) $ref['legacy_id'] ) {
			return (int) $ref['legacy_id'] > 0 && (int) $ref['legacy_id'] === (int) ( $form['old_id'] ?? 0 );
		}
		$hash = (string) ( $ref['hash'] ?? '' );
		foreach ( [ $hash, $id ] as $candidate ) {
			if ( preg_match( '/^[0-9a-f]{7,}$/', $candidate ) && '' !== (string) ( $form['hash'] ?? '' ) && 0 === strpos( (string) $form['hash'], $candidate ) ) {
				return true;
			}
		}
		if ( '' !== $id && ctype_digit( $id ) ) {
			return (int) $id === (int) ( $form['id'] ?? 0 );
		}
		$title = trim( (string) ( $ref['title'] ?? '' ) );
		return '' === $id && '' === $hash && '' !== $title && $title === trim( (string) ( $form['title'] ?? '' ) );
	}

	/**
	 * Attributes of a [contact-form-7 …] / [contact-form N "title"] shortcode.
	 *
	 * @param string $tag  contact-form-7|contact-form
	 * @param string $atts Raw attribute string.
	 * @return array<string, string>
	 */
	public static function parse_atts( string $tag, string $atts ): array {
		$out = [];
		if ( preg_match_all( '/([a-zA-Z_]+)\s*=\s*"([^"]*)"|([a-zA-Z_]+)\s*=\s*\'([^\']*)\'|([a-zA-Z_]+)\s*=\s*([^\s"\']+)|"([^"]*)"|(\S+)/', $atts, $mm, PREG_SET_ORDER ) ) {
			$positional = [];
			foreach ( $mm as $m ) {
				if ( '' !== ( $m[1] ?? '' ) ) {
					$out[ strtolower( $m[1] ) ] = $m[2];
				} elseif ( '' !== ( $m[3] ?? '' ) ) {
					$out[ strtolower( $m[3] ) ] = $m[4];
				} elseif ( '' !== ( $m[5] ?? '' ) ) {
					$out[ strtolower( $m[5] ) ] = $m[6];
				} else {
					$positional[] = '' !== ( $m[7] ?? '' ) ? $m[7] : ( $m[8] ?? '' );
				}
			}
			if ( 'contact-form' === $tag ) {
				// Legacy: [contact-form 3 "Title"] → old unit ID.
				$out['legacy_id'] = $positional[0] ?? '';
			}
		}
		return $out;
	}

	/**
	 * Attributes of the first CF7 shortcode inside a chunk of markup.
	 *
	 * @param string $markup
	 * @return array<string, string>
	 */
	private static function shortcode_atts_in( string $markup ): array {
		if ( preg_match( '#' . self::SHORTCODE . '#', $markup, $m ) ) {
			return self::parse_atts( $m[1], $m[2] ?? '' );
		}
		return [];
	}
}
