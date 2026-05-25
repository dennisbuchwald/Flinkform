<?php
/**
 * Database schema owner.
 *
 * Single point of truth for every table the plugin creates plus the
 * schema-version option used by future migrations. `create()` runs all
 * DDLs through `dbDelta()`, so re-activations and partial-version
 * upgrades are idempotent.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and tracks every PerForm table.
 *
 * Schema-version history:
 *   1 — initial submissions table (Phase 1)
 *   2 — webhooks + webhook_deliveries tables (Phase 6a)
 */
final class Schema {

	/**
	 * Bumped whenever the schema changes — read by the activator to decide
	 * whether dbDelta() needs to run.
	 */
	public const DB_VERSION = '2';

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
	 * Resolve the fully-qualified webhooks table name.
	 *
	 * @return string
	 */
	public static function webhooks_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'perform_webhooks';
	}

	/**
	 * Resolve the fully-qualified webhook-deliveries table name.
	 *
	 * @return string
	 */
	public static function webhook_deliveries_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'perform_webhook_deliveries';
	}

	/**
	 * Create or migrate every PerForm table and persist the schema version.
	 *
	 * Safe to call multiple times — dbDelta() is the contract that makes
	 * this idempotent.
	 *
	 * @return void
	 */
	public static function create(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		self::create_submissions_table();
		self::create_webhooks_table();
		self::create_webhook_deliveries_table();

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
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
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY created_at (created_at),
			KEY status (status)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Webhook configurations (Phase 6a).
	 *
	 * One row per configured webhook. Headers and field_mapping are
	 * JSON-encoded TEXT — small payloads (a handful of headers, a
	 * handful of field renames), no need to normalise out.
	 *
	 * @return void
	 */
	private static function create_webhooks_table(): void {
		global $wpdb;

		$table   = self::webhooks_table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id varchar(36) NOT NULL,
			label varchar(255) NOT NULL DEFAULT '',
			url text NOT NULL,
			method varchar(10) NOT NULL DEFAULT 'POST',
			format varchar(10) NOT NULL DEFAULT 'json',
			headers longtext NOT NULL,
			field_mapping longtext NOT NULL,
			condition_field varchar(255) NOT NULL DEFAULT '',
			condition_operator varchar(20) NOT NULL DEFAULT '',
			condition_value text NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY is_active (is_active)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Webhook delivery log (Phase 6a).
	 *
	 * One row per dispatch attempt. The async dispatcher in Phase 6b
	 * polls this table for rows with `status='pending'` and a due
	 * `next_retry_at`, runs the HTTP request, updates the row, and on
	 * failure either schedules another attempt or marks the row failed.
	 *
	 * submission_id is nullable to accommodate the Inspector "Send test"
	 * flow that exercises the dispatch path without a real submission.
	 *
	 * @return void
	 */
	private static function create_webhook_deliveries_table(): void {
		global $wpdb;

		$table   = self::webhook_deliveries_table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			webhook_id bigint(20) unsigned NOT NULL,
			submission_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			response_code smallint(5) unsigned DEFAULT NULL,
			response_body text NOT NULL,
			attempt tinyint(3) unsigned NOT NULL DEFAULT 1,
			next_retry_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY webhook_id (webhook_id),
			KEY submission_id (submission_id),
			KEY status_retry (status, next_retry_at)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Drop every PerForm table and forget the schema version.
	 *
	 * Called from uninstall.php — never from deactivation.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;

		$tables = [
			self::webhook_deliveries_table_name(),
			self::webhooks_table_name(),
			self::table_name(),
		];

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name cannot be parameterised.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::OPTION_DB_VERSION );
	}
}
