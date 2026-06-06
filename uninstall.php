<?php
/**
 * Uninstall script for PerForm.
 *
 * Executed by WordPress when the plugin is deleted through the admin UI
 * (NOT on simple deactivation). All persistent plugin data is removed
 * here, so a fresh install starts truly clean.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

// Security: only run when called by WordPress core.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// uninstall.php runs standalone — the main plugin bootstrap has not been
// loaded. Define the path constant the autoloader expects, then wire it up.
if ( ! defined( 'PERFFO_PLUGIN_DIR' ) ) {
	define( 'PERFFO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

require_once __DIR__ . '/includes/Autoloader.php';
\PerForm\Autoloader::register();

// Drop the submissions table + the schema-version option.
\PerForm\Database\Schema::drop();

// Remove the Forms-Indexer transient cache.
delete_transient( 'perffo_forms_index' );

// Note: SMTP options/transients and the webhook tables are owned by PerForm
// Pro and are cleaned up by the Pro add-on's own uninstall.php.
