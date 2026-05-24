<?php
/**
 * Plugin bootstrap class.
 *
 * Single entry point for runtime initialisation. Phase 0 keeps init() empty
 * on purpose — the surface area lives here so subsequent phases can wire
 * block registration, REST routes, admin pages, etc. without touching the
 * main plugin file again.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin runtime singleton.
 */
final class Plugin {

	private static ?self $instance = null;

	/**
	 * Get (or create) the shared instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot subsystems.
	 *
	 * Called once on `plugins_loaded`. Wires the block registry, the
	 * submission handler and the admin menu — every other PerForm
	 * capability builds on top of these three.
	 *
	 * @return void
	 */
	public function init(): void {
		( new Blocks\Registry() )->register();

		// Forms indexer registers its cache-invalidation hooks on both
		// front and back ends — a save_post during a REST request needs
		// to invalidate too.
		( new Forms\Indexer() )->register();

		(
			new Submissions\Handler(
				new Forms\Locator(),
				new Submissions\Repository()
			)
		)->register();

		// Notifications subscribe to perform_after_submission. Registered
		// unconditionally — even REST-context saves should mail the admin.
		( new Notifications\Mailer() )->register();

		if ( is_admin() ) {
			( new Admin\Menu() )->register();
		}
	}

	/**
	 * Singletons are not cloneable.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Singletons are not constructible from outside.
	 */
	private function __construct() {}
}
