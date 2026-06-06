<?php
/**
 * PSR-4 autoloader for the PerForm plugin.
 *
 * Maps the `PerForm\` namespace to the `includes/` directory. Kept
 * dependency-free on purpose: no Composer, no `vendor/`, no overhead in the
 * shipped ZIP. One class, one register() call, done.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace PerForm;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal PSR-4 autoloader.
 */
final class Autoloader {

	private const NAMESPACE_PREFIX = 'PerForm\\';

	/**
	 * Register the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( [ self::class, 'load' ] );
	}

	/**
	 * Resolve a class name to a file path and require it.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public static function load( string $class ): void {
		if ( ! str_starts_with( $class, self::NAMESPACE_PREFIX ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::NAMESPACE_PREFIX ) );
		$path     = PERFFO_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
