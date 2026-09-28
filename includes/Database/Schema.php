<?php
/**
 * Database schema owner.
 *
 * Single point of truth for every table the plugin creates plus the
 * schema-version option used by future migrations. `create()` runs all
 * DDLs through `dbDelta()`, so re-activations and partial-version
 * upgrades are idempotent.
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and tracks every Flinkform table.
 *
 * Schema-version history:
 *   1 — initial submissions table (Phase 1)
 *   2 — webhooks + webhook_deliveries tables (Phase 6a)
 *   3 — submissions: mail_status, trashed_at, KEY (form_id, created_at)
 *       (1.15.0; dbDelta adds the columns and keys to existing tables)
 *
 * As of M-c-d-2 the webhook tables moved to Flinkform Pro (FlinkformPro\Database\
 * Schema), which owns their creation + lifecycle. The free core keeps DB_VERSION
 * at 2 (the submissions table is unchanged) and simply no longer manages the
 * webhook tables; Pro adopts the existing tables in place (same names, dbDelta).
 */
final class Schema {

	/**
	 * Bumped whenever the schema changes — read by the activator to decide
	 * whether dbDelta() needs to run.
	 */
	public const DB_VERSION = '3';

	/**
	 * Option key holding the currently installed schema version.
	 */
	public const OPTION_DB_VERSION = 'flinkform_db_version';

	/**
	 * Resolve the fully-qualified submissions table name.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'flinkform_submissions';
	}

	/**
	 * Create or migrate every Flinkform table and persist the schema version.
	 *
	 * Safe to call multiple times — dbDelta() is the contract that makes
	 * this idempotent.
	 *
	 * @return void
	 */
	public static function create(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		self::create_submissions_table();

		// Only claim version 3 once its columns really exist. A database
		// user without ALTER rights would otherwise leave the option at 3
		// and every query naming the new columns would fail; this way the
		// Repository keeps using the version-2 shape and the upgrade is
		// retried (at most hourly, see Plugin::init()).
		if ( self::has_column( 'trashed_at' ) && self::has_column( 'mail_status' ) ) {
			update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
		} else {
			update_option( self::OPTION_DB_VERSION, '2', false );
			set_transient( 'flinkform_schema_retry', 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Whether the version-3 columns (mail status, trash) are available.
	 * Cheap: reads the version option WordPress already cached.
	 *
	 * @return bool
	 */
	public static function has_v3(): bool {
		return version_compare( (string) get_option( self::OPTION_DB_VERSION, '0' ), '3', '>=' );
	}

	/**
	 * Does the submissions table have this column?
	 *
	 * @param string $column Column name (trusted, from this class).
	 * @return bool
	 */
	private static function has_column( string $column ): bool {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Controlled table name, schema check at upgrade time only.
		$found = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
		return null !== $found;
	}

	/**
	 * Submissions table (Phase 1).
	 *
	 * @return void
	 */
	private static function create_submissions_table(): void {
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
			mail_status varchar(20) NOT NULL DEFAULT '',
			trashed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY created_at (created_at),
			KEY status (status),
			KEY form_created (form_id,created_at),
			KEY trashed_at (trashed_at)
		) {$charset};";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- dbDelta() is the WordPress-sanctioned way to create/upgrade a custom table.
		dbDelta( $sql );
	}

	/**
	 * Drop every Flinkform table and forget the schema version.
	 *
	 * Called from uninstall.php — never from deactivation.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall-time DROP of our own table; name cannot be parameterised, no caching applies.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

		delete_option( self::OPTION_DB_VERSION );
	}
}
