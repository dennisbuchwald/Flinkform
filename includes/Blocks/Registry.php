<?php
/**
 * Central block registration.
 *
 * Registers the PerForm block category and every shipped block by pointing
 * register_block_type() at its `block.json` under `/build`. Build assets
 * (scripts, styles, render.php) are picked up automatically from the
 * compiled block manifest.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Discovers and registers all PerForm blocks with WordPress.
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
		'field-select',
		'field-radio',
		'field-checkbox',
		'field-toggle',
		'field-hidden',
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
	 * Add the "PerForm" block category to the inserter.
	 *
	 * @param array<int, array<string, string>> $categories Existing categories.
	 * @return array<int, array<string, string>>
	 */
	public function register_category( array $categories ): array {
		return array_merge(
			[
				[
					'slug'  => 'perform',
					'title' => __( 'PerForm', 'perform-forms' ),
					'icon'  => 'feedback',
				],
			],
			$categories
		);
	}

	/**
	 * Register every block whose compiled output lives under /build.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		foreach ( self::BLOCKS as $block ) {
			$path = PERFORM_PLUGIN_DIR . 'build/' . $block;

			if ( is_dir( $path ) ) {
				register_block_type( $path );
			}
		}
	}
}
