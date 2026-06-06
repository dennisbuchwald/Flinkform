<?php
/**
 * Forms discovery and aggregation.
 *
 * Scans every post that embeds a `perform/form` block, aggregates the
 * results by form UUID, and caches the index in a transient. A
 * lightweight LIKE query on post_content keeps the scan cheap for sites
 * with thousands of posts; the cache absorbs repeat hits inside one
 * admin session, and save_post / delete_post invalidate it precisely
 * for posts that actually touch a form.
 *
 * Architectural note: we deliberately don't model forms as a CPT in
 * Phase 2. Forms live where they're inserted (the page or post). This
 * keeps the data model simple and consistent with Phase 1's "form lives
 * in the markup" decision — at the cost of a scan-based admin list,
 * which we mitigate with caching.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Builds + caches the site's form index.
 */
final class Indexer {

	/**
	 * Block name of the form container — the only one we look for.
	 */
	private const FORM_BLOCK = 'perform/form';

	/**
	 * Cache key.
	 */
	private const CACHE_KEY = 'perffo_forms_index';

	/**
	 * Cache lifetime in seconds. Five minutes is short enough that a stale
	 * index isn't a problem in practice — most edits also invalidate via
	 * save_post.
	 */
	private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Post statuses we consider live enough to include in the index.
	 * Trashed and auto-draft posts are ignored.
	 */
	private const INDEXED_STATUSES = [ 'publish', 'private', 'draft', 'pending', 'future' ];

	/**
	 * Hook the cache invalidators.
	 *
	 * Called from Plugin::init(). No filters/actions are needed for the
	 * read path — callers use ::all() directly.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post', [ $this, 'maybe_invalidate' ], 10, 2 );
		add_action( 'delete_post', [ $this, 'invalidate' ], 10 );
		add_action( 'wp_trash_post', [ $this, 'invalidate' ], 10 );
		add_action( 'untrash_post', [ $this, 'invalidate' ], 10 );
	}

	/**
	 * Return the full index, building (and caching) it on first access.
	 *
	 * @return array<int, array<string, mixed>> Each entry: form_id, title,
	 *         submit_label, sources (array of post records), submission_count,
	 *         last_submission_at.
	 */
	public function all(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$forms = $this->build();
		set_transient( self::CACHE_KEY, $forms, self::CACHE_TTL );

		return $forms;
	}

	/**
	 * Lookup a single form by UUID. Walks the cached index — cheap.
	 *
	 * @param string $form_id
	 * @return array<string, mixed>|null
	 */
	public function find( string $form_id ): ?array {
		foreach ( $this->all() as $form ) {
			if ( ( $form['form_id'] ?? '' ) === $form_id ) {
				return $form;
			}
		}
		return null;
	}

	/**
	 * Drop the cached index. Called by the invalidation hooks and by the
	 * admin "refresh" action.
	 *
	 * @return void
	 */
	public function invalidate(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * save_post handler — only invalidates if the post actually contains
	 * a PerForm form block. Avoids stomping the cache on every unrelated
	 * post save.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 * @return void
	 */
	public function maybe_invalidate( int $post_id, $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( false === strpos( $post->post_content, '<!-- wp:' . self::FORM_BLOCK ) ) {
			return;
		}
		$this->invalidate();
	}

	/**
	 * Scan + aggregate the index from scratch.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build(): array {
		$post_ids = $this->candidate_post_ids();

		$by_form_id = [];

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$blocks = parse_blocks( $post->post_content );
			$this->collect_forms( $blocks, $post, $by_form_id );
		}

		return $this->finalise( $by_form_id );
	}

	/**
	 * Find every post likely to contain a form block via a LIKE query on
	 * post_content. Keeps the result set small even on big sites — a
	 * full `WP_Query` with `s=` would also search post_excerpt etc., and
	 * a meta lookup would need an indexer we don't maintain.
	 *
	 * @return array<int, int>
	 */
	private function candidate_post_ids(): array {
		global $wpdb;

		$statuses     = self::INDEXED_STATUSES;
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$needle       = '%' . $wpdb->esc_like( '<!-- wp:' . self::FORM_BLOCK ) . '%';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- core posts table; only %s placeholders are interpolated, all values prepared.
		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_status IN ({$placeholders})
			 AND post_content LIKE %s",
			array_merge( $statuses, [ $needle ] )
		);

		$ids = $wpdb->get_col( $sql );
		// phpcs:enable

		return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
	}

	/**
	 * Walk a parsed block tree, recording every perform/form block we hit.
	 *
	 * @param array<int, array<string, mixed>> $blocks
	 * @param \WP_Post                         $post
	 * @param array<string, array<string, mixed>> $by_form_id Mutated in place.
	 * @return void
	 */
	private function collect_forms( array $blocks, \WP_Post $post, array &$by_form_id ): void {
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';

			if ( self::FORM_BLOCK === $name ) {
				$attrs   = $block['attrs'] ?? [];
				$form_id = isset( $attrs['formId'] ) && is_string( $attrs['formId'] ) ? $attrs['formId'] : '';
				if ( '' !== $form_id ) {
					if ( ! isset( $by_form_id[ $form_id ] ) ) {
						$by_form_id[ $form_id ] = [
							'form_id'        => $form_id,
							'title'          => isset( $attrs['title'] ) && is_string( $attrs['title'] ) ? trim( $attrs['title'] ) : '',
							'submit_label'   => isset( $attrs['submitLabel'] ) && is_string( $attrs['submitLabel'] ) ? $attrs['submitLabel'] : '',
							'retention_days' => isset( $attrs['retentionDays'] ) ? max( 0, (int) $attrs['retentionDays'] ) : 0,
							'sources'        => [],
						];
					}
					// Always prefer the first non-empty title we see — if
					// the user named the form in one place and left it
					// blank elsewhere, we use the name.
					if ( '' === $by_form_id[ $form_id ]['title'] && ! empty( $attrs['title'] ) ) {
						$by_form_id[ $form_id ]['title'] = trim( (string) $attrs['title'] );
					}
					$by_form_id[ $form_id ]['sources'][] = [
						'post_id'     => (int) $post->ID,
						'post_title'  => $post->post_title,
						'post_status' => $post->post_status,
					];
				}
			}

			$inner = $block['innerBlocks'] ?? [];
			if ( ! empty( $inner ) ) {
				$this->collect_forms( $inner, $post, $by_form_id );
			}
		}
	}

	/**
	 * Enrich each form record with submission stats and produce a
	 * deterministic, sortable list.
	 *
	 * @param array<string, array<string, mixed>> $by_form_id
	 * @return array<int, array<string, mixed>>
	 */
	private function finalise( array $by_form_id ): array {
		global $wpdb;

		// Pull per-form submission counts + last-submission times in one
		// query — cheaper than N round-trips through the Repository.
		$stats_by_form = [];
		$submissions_table = $wpdb->prefix . 'perffo_submissions';
		if ( $this->table_exists( $submissions_table ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom submissions table; only the controlled table name is interpolated, no user input in this aggregate.
			$rows = $wpdb->get_results(
				"SELECT form_id, COUNT(*) AS count, MAX(created_at) AS last_at FROM {$submissions_table} GROUP BY form_id",
				ARRAY_A
			);
			// phpcs:enable
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$stats_by_form[ (string) $row['form_id'] ] = [
						'count'   => (int) $row['count'],
						'last_at' => (string) $row['last_at'],
					];
				}
			}
		}

		$out = [];
		foreach ( $by_form_id as $form ) {
			$stats = $stats_by_form[ $form['form_id'] ] ?? [ 'count' => 0, 'last_at' => '' ];

			$out[] = [
				'form_id'           => $form['form_id'],
				'title'             => '' !== $form['title'] ? $form['title'] : $this->fallback_title( $form ),
				'has_explicit_title' => '' !== $form['title'],
				'submit_label'      => $form['submit_label'],
				'retention_days'    => $form['retention_days'] ?? 0,
				'sources'           => $form['sources'],
				'submission_count'  => $stats['count'],
				'last_submission_at' => $stats['last_at'],
			];
		}

		// Also surface orphan forms — UUIDs that have submissions but no
		// surviving page in post_content. Lets the admin still find and
		// manage them rather than vanishing silently.
		$known = array_flip( array_column( $out, 'form_id' ) );
		foreach ( $stats_by_form as $form_id => $stats ) {
			if ( ! isset( $known[ $form_id ] ) ) {
				$out[] = [
					'form_id'            => (string) $form_id,
					'title'              => '',
					'has_explicit_title' => false,
					'submit_label'       => '',
					'sources'            => [],
					'submission_count'   => $stats['count'],
					'last_submission_at' => $stats['last_at'],
				];
			}
		}

		// Stable order: most-recently-submitted first, then by title.
		usort(
			$out,
			static function ( array $a, array $b ): int {
				$cmp = strcmp( (string) $b['last_submission_at'], (string) $a['last_submission_at'] );
				if ( 0 !== $cmp ) {
					return $cmp;
				}
				return strcasecmp( (string) $a['title'], (string) $b['title'] );
			}
		);

		return $out;
	}

	/**
	 * Compose a sensible display title when the user didn't set one.
	 *
	 * @param array<string, mixed> $form
	 * @return string
	 */
	private function fallback_title( array $form ): string {
		$source = $form['sources'][0] ?? null;
		if ( is_array( $source ) && ! empty( $source['post_title'] ) ) {
			return sprintf(
				/* translators: %s: source post title */
				__( 'Form on "%s"', 'perform-forms' ),
				(string) $source['post_title']
			);
		}
		return __( '(Untitled form)', 'perform-forms' );
	}

	/**
	 * Detect whether the submissions table exists yet. Activation has
	 * usually run by the time the indexer is consulted, but during
	 * fresh-install previews it might not have.
	 *
	 * @param string $table
	 * @return bool
	 */
	private function table_exists( string $table ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name controlled.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}
}
