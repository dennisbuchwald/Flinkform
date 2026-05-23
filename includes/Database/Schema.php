<?php
/**
 * Submissions table schema.
 *
 * Owns the `{prefix}_perform_submissions` DDL and the schema-version option
 * used by future migrations. Runs through `dbDelta()` so re-activations are
 * idempotent.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and tracks the submissions table.
 */
final class Schema {

	/**
	 * Bumped whenever the schema changes — read by the activator to decide
	 * whether dbDelta() needs to run.
	 */
	public const DB_VERSION = '1';

	/**
	 * Option key holding the currently installed schema version.
	 */
	public const OPTION_DB_VERSION = 'perform_db_version';

	/**
	 * Resolve the fully-qualified submissions table name.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'perform_submissions';
	}

	/**
	 * Create or migrate the submissions table and persist the schema version.
	 *
	 * Safe to call multiple times — dbDelta() is the contract that makes
	 * this idempotent.
	 *
	 * @return void
	 */
	public static function create(): void {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		// form_id is a UUID v4 string (36 chars incl. dashes), generated in
		// the block editor and embedded into the form block's attributes.
		// Keeping it as a string decouples submissions from any single post.
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id varchar(36) NOT NULL,
			data longtext NOT NULL,
			created_at datetime NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'unread',
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY created_at (created_at),
			KEY status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
	}

	/**
	 * Drop the submissions table and forget the schema version.
	 *
	 * Called from uninstall.php — never from deactivation.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be parameterised.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

		delete_option( self::OPTION_DB_VERSION );
	}
}
