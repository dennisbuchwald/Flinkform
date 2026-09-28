<?php
/**
 * GDPR / DSGVO compliance: privacy policy content, personal data
 * exporter and personal data eraser.
 *
 * Hooks into WordPress's built-in privacy tools so site operators
 * see Flinkform's data-handling disclosure on the Privacy Policy page
 * and can fulfil data-subject access/erasure requests through the
 * standard wp-admin > Tools > Export/Erase Personal Data flow.
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform;

use Flinkform\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy integration (stateless — all methods are static or hooked
 * callbacks that receive their context from WordPress).
 */
final class Privacy {

	/**
	 * Register all privacy hooks.
	 *
	 * Called from Plugin::init() on `plugins_loaded`. The actual
	 * callbacks run later (`admin_init` for the policy content,
	 * filter callbacks on-demand during export/erasure requests).
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'add_privacy_policy_content' ] );
		add_filter( 'wp_privacy_personal_data_exporters', [ self::class, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ self::class, 'register_eraser' ] );
	}

	/**
	 * Suggest privacy-policy text for the site operator.
	 *
	 * @return void
	 */
	public static function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		// esc_html__ (not __) so a malicious translation file can never inject
		// markup into the privacy-policy page; these strings are plain prose.
		$content = '<h2>' . esc_html__( 'Flinkform — Form Submissions', 'flinkform' ) . '</h2>';

		$content .= '<p>' . esc_html__( 'When a visitor submits a form built with Flinkform, the plugin stores the submitted field values (name, email, message, etc.) in a dedicated database table on this website. No IP addresses or browser user-agent strings are collected.', 'flinkform' ) . '</p>';

		$content .= '<p>' . esc_html__( 'Submissions are retained until a site administrator deletes them (individually or in bulk), until the plugin is uninstalled, or — when a per-form retention period is configured — until they are older than that period, after which a daily routine deletes them automatically.', 'flinkform' ) . '</p>';

		$content .= '<p>' . esc_html__( 'Flinkform does not send any submission data to external services. (Add-ons such as Flinkform Pro may add integrations — e.g. webhooks or SMTP delivery — that transmit data to third parties; those add their own privacy disclosures when active.)', 'flinkform' ) . '</p>';

		$content .= '<p>' . esc_html__( 'The built-in spam protection (proof-of-work challenge) runs entirely in the visitor\'s browser and on this server — no data is sent to any external anti-spam service.', 'flinkform' ) . '</p>';

		$content .= '<p>' . esc_html__( 'Flinkform sets one short-lived, strictly necessary cookie ("flinkform_flash", lifetime ~60 seconds, httpOnly) — and only when a submission fails validation, to carry the error message across the page reload. No tracking, analytics or marketing cookies are ever set.', 'flinkform' ) . '</p>';

		wp_add_privacy_policy_content( 'Flinkform', $content );
	}

	/**
	 * Register the personal data exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['flinkform'] = [
			'exporter_friendly_name' => __( 'Flinkform Submissions', 'flinkform' ),
			'callback'               => [ self::class, 'export_personal_data' ],
		];

		return $exporters;
	}

	/**
	 * Register the personal data eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['flinkform'] = [
			'eraser_friendly_name' => __( 'Flinkform Submissions', 'flinkform' ),
			'callback'             => [ self::class, 'erase_personal_data' ],
		];

		return $erasers;
	}

	/**
	 * Export submissions that contain the requested email address.
	 *
	 * @param string $email_address The email to search for.
	 * @param int    $page          Page number (1-based).
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export_personal_data( string $email_address, int $page = 1 ): array {
		$per_page    = 50;
		$result      = self::query_by_email( $email_address, max( 0, ( $page - 1 ) * $per_page ), $per_page );
		$submissions = $result['matches'];
		$export_data = [];

		foreach ( $submissions as $submission ) {
			$fields = $submission['data']['fields'] ?? [];
			$items  = [];

			foreach ( $fields as $field ) {
				$label = $field['label'] ?? $field['name'] ?? '';
				$value = $field['value'] ?? '';

				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}

				$items[] = [
					'name'  => (string) $label,
					'value' => (string) $value,
				];
			}

			$items[] = [
				'name'  => __( 'Submitted at', 'flinkform' ),
				'value' => $submission['created_at'],
			];

			$form_title = $submission['data']['_meta']['form_title'] ?? '';
			if ( '' !== $form_title ) {
				$items[] = [
					'name'  => __( 'Form', 'flinkform' ),
					'value' => (string) $form_title,
				];
			}

			$export_data[] = [
				'group_id'          => 'flinkform-submissions',
				'group_label'       => __( 'Flinkform Submissions', 'flinkform' ),
				'group_description' => __( 'Form submissions stored by the Flinkform plugin.', 'flinkform' ),
				'item_id'           => 'flinkform-submission-' . $submission['id'],
				'data'              => $items,
			];
		}

		// "Done" is decided by the raw page, not by the filtered matches: a
		// single LIKE hit that turns out not to be this address (a longer
		// address containing it) would otherwise end the export early and
		// leave the remaining pages unexported.
		return [
			'data' => $export_data,
			'done' => $result['raw'] < $per_page,
		];
	}

	/**
	 * Erase submissions that contain the requested email address.
	 *
	 * @param string $email_address The email to search for.
	 * @param int    $page          Page number (1-based).
	 * @return array{items_removed: int, items_retained: int, messages: list<string>, done: bool}
	 */
	public static function erase_personal_data( string $email_address, int $page = 1 ): array {
		unset( $page );

		// Walk every LIKE hit with an id cursor and erase the real matches in
		// one call. Offset paging does not survive deleting as you go (page 2
		// starts after rows that moved up into page 1), and a filtered count
		// below the page size is not the end of the table either. One data
		// subject's submissions are a small set, so a single pass is fine.
		$batch   = 200;
		$after   = 0;
		$removed = 0;
		$repo    = new Submissions\Repository();

		do {
			$result = self::query_by_email( $email_address, 0, $batch, $after );
			$ids    = array_map( static fn( array $s ): int => $s['id'], $result['matches'] );
			if ( ! empty( $ids ) ) {
				$removed += (int) $repo->delete_many( $ids );
			}
			$after = $result['last_id'];
		} while ( $result['raw'] >= $batch && $after > 0 );

		return [
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => [],
			'done'           => true,
		];
	}

	/**
	 * Public lookup for add-ons: submission IDs whose stored data contains
	 * the given email address.
	 *
	 * Lets Pro modules (e.g. the webhook delivery-log personal-data exporter)
	 * resolve a data subject's submissions without duplicating the email-match
	 * logic. Same pagination semantics as the exporter/eraser callbacks.
	 *
	 * @since 0.2.6
	 *
	 * @param string $email    Email address to search for.
	 * @param int    $page     Page (1-based).
	 * @param int    $per_page Results per page.
	 * @return array<int, int> Matching submission ids.
	 */
	public static function find_submission_ids_by_email( string $email, int $page = 1, int $per_page = 50 ): array {
		return array_map(
			static fn( array $submission ): int => (int) $submission['id'],
			self::find_by_email( $email, $page, $per_page )
		);
	}

	/**
	 * Find submissions whose JSON `data` column contains the given
	 * email address (one page, filtered to real matches).
	 *
	 * @param string $email    Email address to search for.
	 * @param int    $page     Page (1-based).
	 * @param int    $per_page Results per page.
	 * @return list<array{id: int, form_id: string, data: array<string, mixed>, created_at: string, status: string}>
	 */
	private static function find_by_email( string $email, int $page, int $per_page ): array {
		return self::query_by_email( $email, max( 0, ( $page - 1 ) * $per_page ), $per_page )['matches'];
	}

	/**
	 * Run the LIKE query against the serialised JSON and keep the rows whose
	 * field values really contain the address. Pragmatic for the data
	 * volumes Flinkform targets.
	 *
	 * Returns the raw row count next to the filtered matches: callers that
	 * page through the table must decide "done" on the raw count, because a
	 * substring false positive shrinks the filtered list without meaning the
	 * table is exhausted.
	 *
	 * @param string $email    Email address to search for.
	 * @param int    $offset   Rows to skip (offset paging).
	 * @param int    $limit    Rows to read.
	 * @param int    $after_id Only rows with a higher id (cursor paging); 0 = off.
	 * @return array{matches: list<array{id: int, form_id: string, data: array<string, mixed>, created_at: string, status: string}>, raw: int, last_id: int}
	 */
	private static function query_by_email( string $email, int $offset, int $limit, int $after_id = 0 ): array {
		global $wpdb;

		$table = Schema::table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom submissions table; only the controlled table name is interpolated, the email is prepared + esc_like'd, this is a rare on-demand GDPR query.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, form_id, data, created_at, status FROM {$table} WHERE data LIKE %s AND id > %d ORDER BY id ASC LIMIT %d OFFSET %d",
				'%' . $wpdb->esc_like( $email ) . '%',
				max( 0, $after_id ),
				$limit,
				max( 0, $offset )
			),
			ARRAY_A
		);
		// phpcs:enable

		$empty = [
			'matches' => [],
			'raw'     => 0,
			'last_id' => 0,
		];
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return $empty;
		}

		$results = [];
		$last_id = 0;
		foreach ( $rows as $row ) {
			$last_id = max( $last_id, (int) ( $row['id'] ?? 0 ) );

			$decoded = json_decode( (string) ( $row['data'] ?? '' ), true );
			if ( ! is_array( $decoded ) ) {
				$decoded = [];
			}

			// Verify the email actually appears in a field value (not
			// just a coincidental substring match in a label or name).
			if ( ! self::submission_contains_email( $decoded, $email ) ) {
				continue;
			}

			$results[] = [
				'id'         => (int) ( $row['id'] ?? 0 ),
				'form_id'    => (string) ( $row['form_id'] ?? '' ),
				'data'       => $decoded,
				'created_at' => (string) ( $row['created_at'] ?? '' ),
				'status'     => (string) ( $row['status'] ?? 'unread' ),
			];
		}

		return [
			'matches' => $results,
			'raw'     => count( $rows ),
			'last_id' => $last_id,
		];
	}

	/**
	 * Check whether a decoded submission payload contains the given
	 * email address in any field value (case-insensitive).
	 *
	 * @param array<string, mixed> $data  Decoded submission data.
	 * @param string               $email Email to match.
	 * @return bool
	 */
	private static function submission_contains_email( array $data, string $email ): bool {
		$fields = $data['fields'] ?? [];
		$lower  = strtolower( $email );

		foreach ( $fields as $field ) {
			$value = $field['value'] ?? '';

			if ( is_array( $value ) ) {
				foreach ( $value as $v ) {
					if ( strtolower( (string) $v ) === $lower ) {
						return true;
					}
				}
			} elseif ( strtolower( (string) $value ) === $lower ) {
				return true;
			}
		}

		return false;
	}
}
