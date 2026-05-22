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

// TODO (MVP §4.3): drop the custom submissions table.
//
// global $wpdb;
// $table = $wpdb->prefix . 'perform_submissions';
// $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
//
// TODO: delete plugin options (db schema version, global settings, SMTP credentials).
// delete_option( 'perform_db_version' );
// delete_option( 'perform_settings' );
//
// TODO: clear any transients used for rate-limiting / spam protection.
// delete_transient( 'perform_…' );
