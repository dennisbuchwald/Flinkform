<?php
/**
 * Central block registration.
 *
 * Registers the Flinkform block category and every shipped block by pointing
 * register_block_type() at its `block.json` under `/build`. Build assets
 * (scripts, styles, render.php) are picked up automatically from the
 * compiled block manifest.
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Discovers and registers all Flinkform blocks with WordPress.
 */
final class Registry {

	/**
	 * Block directories to register (relative to /build).
	 *
	 * Order matters only for the inserter — container before fields keeps
	 * the picker readable.
	 */
	private const BLOCKS = [
		'form-container',
		'section-heading',
		'page-break',
		'field-text',
		'field-email',
		'field-textarea',
		'field-number',
		'field-date',
		'field-url',
		'field-phone',
		'field-select',
		'field-radio',
		'field-checkbox',
		'field-toggle',
		'field-hidden',
		'field-consent',
	];

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'block_categories_all', [ $this, 'register_category' ], 10, 1 );
		add_action( 'init', [ $this, 'register_blocks' ] );
	}

	/**
	 * Add the "Flinkform" block category to the inserter.
	 *
	 * @param array<int, array<string, string>> $categories Existing categories.
	 * @return array<int, array<string, string>>
	 */
	public function register_category( array $categories ): array {
		return array_merge(
			[
				[
					'slug'  => 'flinkform',
					'title' => __( 'Flinkform', 'flinkform' ),
					'icon'  => 'feedback',
				],
			],
			$categories
		);
	}

	/**
	 * Register every Flinkform block from its compiled `block.json` directory.
	 *
	 * The free core's blocks live under its own `/build`. The map is exposed
	 * through `flinkform_block_dirs` so the Pro add-on can append its blocks
	 * (e.g. Pro field types) pointing at the *add-on's* build directory — Pro
	 * block code never ships inside the free core. Keys are block slugs (used
	 * to de-duplicate), values are absolute paths to the directory holding the
	 * compiled `block.json`.
	 *
	 * @since 0.2.0 Made filterable for the Free/Pro bridge.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		$dirs = [];
		foreach ( self::BLOCKS as $block ) {
			// Multi-step (page-break) is part of the free core since 0.2.7
			// — no capability gate. (An earlier slice gated it on the Pro
			// MULTI_STEP capability; that contradicted the published
			// feature matrix and is gone as of 0.4.0.)
			$dirs[ $block ] = FLINKFORM_PLUGIN_DIR . 'build/' . $block;
		}

		/**
		 * Filter the set of block directories Flinkform registers.
		 *
		 * @since 0.2.0
		 *
		 * @param array<string, string> $dirs Map of block slug => absolute path to the block.json directory.
		 */
		$dirs = (array) apply_filters( 'flinkform_block_dirs', $dirs );

		foreach ( $dirs as $path ) {
			if ( is_string( $path ) && is_dir( $path ) ) {
				register_block_type( $path );
			}
		}
	}
}
