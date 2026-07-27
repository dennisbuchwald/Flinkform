<?php
/**
 * Translation of untouched block-attribute defaults.
 *
 * Gutenberg only serialises an attribute once it differs from the default
 * in block.json, and those defaults never pass through the i18n layer —
 * they are literals in a JSON file. A freshly inserted Date Field on a
 * German site therefore rendered a label reading "Date", and a Select
 * read "Choose one", even though both strings sit translated in the
 * catalogue.
 *
 * The absence of an attribute is exactly the signal we need: it means the
 * author never touched it. So this hooks `render_block_data`, which runs
 * before WP_Block applies the defaults, and fills in the translated value
 * for any attribute the author left alone. A label the author typed
 * themselves is present in the markup and is left untouched — even if
 * they happened to type the English default word for word.
 *
 * Doing it here rather than in fifteen render.php files keeps the list in
 * one place and means a new block only has to add a line to the map.
 *
 * @package Flinkform
 * @since 1.8.3
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Fills untouched attribute defaults with their translated counterpart.
 */
final class DefaultStrings {

	/**
	 * Block name => attribute => English default from block.json.
	 *
	 * The English string is the msgid, so it has to match block.json
	 * character for character. flinkform-default-strings-test.php asserts
	 * that it still does.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const DEFAULTS = [
		'flinkform/field-address'   => [ 'label' => 'Address' ],
		'flinkform/field-checkbox'  => [ 'label' => 'Choose any' ],
		'flinkform/field-date'      => [ 'label' => 'Date' ],
		'flinkform/field-email'     => [ 'label' => 'Email' ],
		'flinkform/field-hidden'    => [ 'label' => 'Hidden Field' ],
		'flinkform/field-number'    => [ 'label' => 'Number' ],
		'flinkform/field-phone'     => [ 'label' => 'Phone' ],
		'flinkform/field-radio'     => [ 'label' => 'Choose one' ],
		'flinkform/field-select'    => [ 'label' => 'Choose one' ],
		'flinkform/field-text'      => [ 'label' => 'Text' ],
		'flinkform/field-textarea'  => [ 'label' => 'Message' ],
		'flinkform/field-toggle'    => [ 'label' => 'I agree' ],
		'flinkform/field-url'       => [ 'label' => 'Website' ],
		'flinkform/section-heading' => [ 'title' => 'Section' ],
	];

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'render_block_data', [ $this, 'translate_defaults' ], 10, 1 );
	}

	/**
	 * Fill in translated defaults for attributes the author never set.
	 *
	 * @param array<string, mixed> $parsed_block A parsed block, pre-render.
	 * @return array<string, mixed>
	 */
	public function translate_defaults( array $parsed_block ): array {
		$name = isset( $parsed_block['blockName'] ) ? (string) $parsed_block['blockName'] : '';
		if ( ! isset( self::DEFAULTS[ $name ] ) ) {
			return $parsed_block;
		}

		if ( ! isset( $parsed_block['attrs'] ) || ! is_array( $parsed_block['attrs'] ) ) {
			$parsed_block['attrs'] = [];
		}

		foreach ( self::DEFAULTS[ $name ] as $attribute => $english ) {
			// Present means the author set it — including the case where
			// they typed the English wording on purpose. Leave it alone.
			if ( isset( $parsed_block['attrs'][ $attribute ] ) ) {
				continue;
			}

			// translators: this is a block attribute default; the msgid is
			// the English default from block.json.
			$translated = __( $english, 'flinkform' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- the map holds fixed literals that also appear in the POT; a variable is the only way to keep them in one list.

			if ( $translated !== $english ) {
				$parsed_block['attrs'][ $attribute ] = $translated;
			}
		}

		return $parsed_block;
	}

	/**
	 * The map, for tests and for add-ons that need the same list.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function defaults(): array {
		return self::DEFAULTS;
	}
}
