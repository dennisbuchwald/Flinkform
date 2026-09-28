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
		'notice',
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
		'field-address',
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
		// No category icon on purpose: WordPress renders it next to the
		// "Flinkform" heading in the inserter, and no other block plugin
		// (GenerateBlocks, core, …) does this — it just looks off. The
		// branded gradient lives on the Form block icon itself instead.
		return array_merge(
			[
				[
					'slug'  => 'flinkform',
					'title' => __( 'Flinkform', 'flinkform' ),
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
			if ( ! is_string( $path ) || ! is_dir( $path ) ) {
				continue;
			}

			$block_type = register_block_type( $path );

			// Point the editor scripts at OUR languages folder.
			//
			// register_block_type() already calls wp_set_script_translations()
			// for us when block.json carries a textdomain — but it calls it
			// without a path, and WordPress then looks for the JED files in
			// WP_LANG_DIR/plugins only. That folder is filled by
			// translate.wordpress.org, which has no Flinkform translations,
			// so every bundled .json sat unused and the whole block inspector
			// stayed English on sites whose PHP strings were translated fine.
			// (PHP works because just-in-time loading honours Domain Path;
			// script translations have no such fallback.)
			//
			// Re-registering with the third argument is enough — the file
			// names already follow core's convention, md5() of the script's
			// path relative to the plugin folder.
			if ( ! $block_type instanceof \WP_Block_Type ) {
				continue;
			}

			// Blocks an add-on registers through `flinkform_block_dirs` live in
			// the add-on's folder, carry the add-on's textdomain and ship on
			// the add-on's release cycle. Pointing their scripts at OUR
			// textdomain made WordPress look for Pro's JED files under the
			// wrong name (the Pro inspectors stayed English), and stamping
			// OUR version on their assets meant a Pro-only release never
			// changed a URL, so browsers kept the old view.js.
			if ( ! $this->is_own_block_dir( $path ) ) {
				$this->version_foreign_styles( $block_type );
				continue;
			}

			foreach ( (array) $block_type->editor_script_handles as $handle ) {
				wp_set_script_translations( $handle, 'flinkform', FLINKFORM_PLUGIN_DIR . 'languages' );
			}

			$this->version_assets( $block_type );
		}
	}

	/**
	 * Whether a block directory belongs to this plugin (not to an add-on).
	 *
	 * @param string $path Absolute path to the block.json directory.
	 * @return bool
	 */
	private function is_own_block_dir( string $path ): bool {
		return str_starts_with(
			wp_normalize_path( $path ),
			wp_normalize_path( FLINKFORM_PLUGIN_DIR )
		);
	}

	/**
	 * Cache-bust an add-on block's stylesheets by file modification time.
	 *
	 * Scripts need nothing: register_block_type() already versions them
	 * with the content hash from their *.asset.php. Stylesheets only get
	 * block.json's static `version`, so they take the file's mtime, which
	 * moves with every deploy of the add-on.
	 *
	 * @param \WP_Block_Type $block_type A freshly registered add-on block.
	 * @return void
	 */
	private function version_foreign_styles( \WP_Block_Type $block_type ): void {
		$styles = wp_styles();

		$style_handles = array_merge(
			(array) $block_type->style_handles,
			(array) $block_type->editor_style_handles
		);
		foreach ( $style_handles as $handle ) {
			if ( ! isset( $styles->registered[ $handle ] ) ) {
				continue;
			}
			$file = $styles->registered[ $handle ]->extra['path'] ?? '';
			if ( is_string( $file ) && '' !== $file && is_readable( $file ) ) {
				$styles->registered[ $handle ]->ver = (string) filemtime( $file );
			}
		}
	}

	/**
	 * Stamp the plugin version onto every asset a block registers.
	 *
	 * `register_block_type()` takes the cache-busting version straight from
	 * block.json's `version` field. Ours said "0.1.0" in all seventeen
	 * blocks, unchanged since the first commit, so every stylesheet and
	 * script has been served from the identical URL across every release
	 * the plugin ever shipped. Browsers, CDNs and page caches all treat
	 * that as the same file forever: a visitor who loaded a form once got
	 * the CSS from that day and never saw another fix. Which is exactly how
	 * a Safari layout fix could be live on the server and still absent in
	 * the browser.
	 *
	 * Overwriting the registered `ver` is the smallest reliable repair. The
	 * alternative — bumping `version` in seventeen block.json files on every
	 * release — is the same information kept in eighteen places, and it only
	 * takes forgetting once to be back here.
	 *
	 * The view script MODULE is left alone: module registration already
	 * derives its own content hash, so those URLs change on their own.
	 *
	 * @param \WP_Block_Type $block_type A freshly registered block type.
	 * @return void
	 */
	private function version_assets( \WP_Block_Type $block_type ): void {
		$styles  = wp_styles();
		$scripts = wp_scripts();

		$style_handles = array_merge(
			(array) $block_type->style_handles,
			(array) $block_type->editor_style_handles
		);
		foreach ( $style_handles as $handle ) {
			if ( isset( $styles->registered[ $handle ] ) ) {
				$styles->registered[ $handle ]->ver = FLINKFORM_VERSION;
			}
		}

		$script_handles = array_merge(
			(array) $block_type->script_handles,
			(array) $block_type->editor_script_handles,
			(array) $block_type->view_script_handles
		);
		foreach ( $script_handles as $handle ) {
			if ( isset( $scripts->registered[ $handle ] ) ) {
				$scripts->registered[ $handle ]->ver = FLINKFORM_VERSION;
			}
		}
	}
}
