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
	 * Called once on `plugins_loaded`. Phase 0 is intentionally a no-op:
	 * Phase 1 will register the Form Container block and its field blocks
	 * here, Phase 2 the submissions admin pages, and so on.
	 *
	 * @return void
	 */
	public function init(): void {
		// Subsystems will be wired up here in subsequent phases.
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
