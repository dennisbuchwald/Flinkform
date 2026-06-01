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
if ( ! defined( 'PERFORM_PLUGIN_DIR' ) ) {
	define( 'PERFORM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

require_once __DIR__ . '/includes/Autoloader.php';
\PerForm\Autoloader::register();

// Drop all custom database tables + the schema-version option.
\PerForm\Database\Schema::drop();

// Remove plugin options created by the SMTP module (Phase A).
delete_option( 'perform_smtp_settings' );
delete_option( 'perform_smtp_last_test' );

// Remove the Forms-Indexer transient cache.
delete_transient( 'perform_forms_index' );

// Clean up per-user transients from SMTP test results. These use the
// pattern `perform_smtp_test_result_{user_id}` — iterate over all users
// who might have one. Limit to administrators for efficiency.
$admins = get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] );
foreach ( $admins as $admin_id ) {
	delete_transient( 'perform_smtp_test_result_' . $admin_id );
}
