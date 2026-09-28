<?php
/**
 * Contact Form 7 → Flinkform, the WordPress side (1.15.0).
 *
 * Reads CF7 forms straight from the database (post type + meta), so it
 * works whether CF7 is active or already deactivated. CF7's data is only
 * ever READ: nothing here writes to a wpcf7_contact_form post or its meta,
 * and CF7 is never deactivated (FLINKFORM_CF7_IMPORT.md §8).
 *
 * One CF7 form becomes one synced pattern (wp_block) holding a Flinkform
 * form, so every page that embedded it keeps sharing one form and one
 * submissions list. Pages are changed through wp_update_post(), which
 * leaves a revision. Every change is logged (option flinkform_cf7_imports)
 * as original/replacement pairs, which is what undo reverses.
 *
 * @package Flinkform
 * @since 1.15.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Import\Cf7;

defined( 'ABSPATH' ) || exit;

/**
 * Import, preview and undo.
 */
final class Importer {

	public const POST_TYPE   = 'wpcf7_contact_form';
	public const SOURCE_META = '_flinkform_cf7_source';
	public const LOG_OPTION  = 'flinkform_cf7_imports';

	/**
	 * All CF7 forms, oldest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function forms(): array {
		$posts = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => [ 'publish', 'draft', 'private', 'pending', 'future' ],
				'posts_per_page'   => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			]
		);
		return array_map( [ $this, 'read_form' ], $posts );
	}

	/**
	 * Number of CF7 forms (cheap, for the notice and the menu).
	 *
	 * @return int
	 */
	public static function count_forms(): int {
		$counts = wp_count_posts( self::POST_TYPE );
		$total  = 0;
		foreach ( [ 'publish', 'draft', 'private', 'pending', 'future' ] as $s ) {
			$total += (int) ( $counts->$s ?? 0 );
		}
		return $total;
	}

	/**
	 * One CF7 form as plain data.
	 *
	 * @param \WP_Post $post
	 * @return array<string, mixed>
	 */
	public function read_form( \WP_Post $post ): array {
		$meta = static fn ( string $key ) => get_post_meta( $post->ID, $key, true );
		$form = $meta( '_form' );
		return [
			'id'       => (int) $post->ID,
			'title'    => (string) $post->post_title,
			'markup'   => is_string( $form ) && '' !== $form ? $form : (string) $post->post_content,
			'mail'     => is_array( $meta( '_mail' ) ) ? $meta( '_mail' ) : [],
			'mail_2'   => is_array( $meta( '_mail_2' ) ) ? $meta( '_mail_2' ) : [],
			'messages' => is_array( $meta( '_messages' ) ) ? $meta( '_messages' ) : [],
			'settings' => is_string( $meta( '_additional_settings' ) ) ? $meta( '_additional_settings' ) : '',
			'hash'     => (string) $meta( '_hash' ),
			'old_id'   => (int) $meta( '_old_cf7_unit_id' ),
		];
	}

	/**
	 * Dry run for one form: what would be created, what would change.
	 *
	 * @param array<string, mixed> $form From read_form().
	 * @return array<string, mixed>
	 */
	public function preview( array $form ): array {
		$plan  = $this->plan( $form, 'preview' );
		$scan  = $this->scan_embeds( $form, 0 );
		$log   = $this->log();
		return [
			'id'       => $form['id'],
			'title'    => $form['title'],
			'status'   => $plan['status'],
			'fields'   => $plan['fields'],
			'notes'    => array_merge( $plan['notes'], $scan['notes'] ),
			'pages'    => $scan['pages'],
			'imported' => isset( $log[ $form['id'] ] ) ? $this->log_summary( $log[ $form['id'] ] ) : null,
		];
	}

	/**
	 * Import one form.
	 *
	 * @param int $cf7_id
	 * @return array<string, mixed>|\WP_Error
	 */
	public function import( int $cf7_id ) {
		$post = get_post( $cf7_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'flinkform_cf7_missing', __( 'This Contact Form 7 form no longer exists.', 'flinkform' ) );
		}
		$log = $this->log();
		// Also when the pattern was trashed by hand: its log entry still
		// knows which pages point at it, and only undo puts those back.
		if ( isset( $log[ $cf7_id ] ) ) {
			return new \WP_Error( 'flinkform_cf7_done', __( 'This form was already imported. Undo the import first to import it again.', 'flinkform' ) );
		}

		$form    = $this->read_form( $post );
		$form_id = wp_generate_uuid4();
		$plan    = $this->plan( $form, $form_id );

		$tree    = [
			'blockName'    => 'flinkform/form',
			'attrs'        => $plan['attrs'],
			'innerBlocks'  => $plan['blocks'],
			'innerHTML'    => '',
			'innerContent' => [],
		];
		foreach ( $plan['blocks'] as $i => $child ) {
			$tree['innerContent'][] = "\n";
			$tree['innerContent'][] = null;
			$tree['innerBlocks'][ $i ] += [ 'innerHTML' => '', 'innerContent' => [] ];
		}
		$tree['innerContent'][] = "\n";

		$pattern_id = wp_insert_post(
			[
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => '' !== $form['title'] ? $form['title'] : __( 'Imported form', 'flinkform' ),
				'post_content' => wp_slash( serialize_blocks( [ $tree ] ) ),
			],
			true
		);
		if ( is_wp_error( $pattern_id ) ) {
			return $pattern_id;
		}
		update_post_meta( $pattern_id, self::SOURCE_META, $cf7_id );

		$scan = $this->scan_embeds( $form, (int) $pattern_id, true );

		$log[ $cf7_id ] = [
			'pattern' => (int) $pattern_id,
			'form_id' => $form_id,
			'time'    => time(),
			'user'    => get_current_user_id(),
			'posts'   => $scan['changed'],
		];
		update_option( self::LOG_OPTION, $log, false );

		( new \Flinkform\Forms\Indexer() )->invalidate();

		return [
			'id'      => $cf7_id,
			'pattern' => (int) $pattern_id,
			'edit'    => (string) get_edit_post_link( (int) $pattern_id, 'raw' ),
			'status'  => $plan['status'],
			'notes'   => array_merge( $plan['notes'], $scan['notes'] ),
			'pages'   => $scan['pages'],
		];
	}

	/**
	 * Undo one import: embeds back, pattern to the trash.
	 *
	 * @param int $cf7_id
	 * @return array<string, mixed>|\WP_Error
	 */
	public function undo( int $cf7_id ) {
		$log = $this->log();
		if ( ! isset( $log[ $cf7_id ] ) ) {
			return new \WP_Error( 'flinkform_cf7_not_imported', __( 'Nothing to undo for this form.', 'flinkform' ) );
		}
		$entry   = $log[ $cf7_id ];
		$missing = 0;
		$pages   = [];
		foreach ( (array) $entry['posts'] as $post_id => $replacements ) {
			$post = get_post( (int) $post_id );
			if ( ! $post ) {
				$missing += count( (array) $replacements );
				continue;
			}
			$back     = EmbedReplacer::revert( $post->post_content, (array) $replacements );
			$missing += $back['missing'];
			if ( $back['content'] !== $post->post_content ) {
				wp_update_post( [ 'ID' => $post->ID, 'post_content' => wp_slash( $back['content'] ) ] );
				$pages[] = $this->page_ref( $post );
			}
		}
		if ( get_post( (int) $entry['pattern'] ) ) {
			wp_trash_post( (int) $entry['pattern'] );
		}
		unset( $log[ $cf7_id ] );
		update_option( self::LOG_OPTION, $log, false );
		( new \Flinkform\Forms\Indexer() )->invalidate();

		return [ 'id' => $cf7_id, 'pages' => $pages, 'missing' => $missing ];
	}

	/**
	 * The import log: cf7 id → { pattern, form_id, time, user, posts: { post_id: [[orig, repl]…] } }.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function log(): array {
		$log = get_option( self::LOG_OPTION, [] );
		return is_array( $log ) ? $log : [];
	}

	/**
	 * @param array<string, mixed> $entry
	 * @return array<string, mixed>
	 */
	private function log_summary( array $entry ): array {
		$submissions = ( new \Flinkform\Submissions\Repository() )->count( [ 'form_id' => (string) $entry['form_id'], 'trashed' => 'any' ] );
		return [
			'pattern'     => (int) $entry['pattern'],
			'edit'        => (string) get_edit_post_link( (int) $entry['pattern'], 'raw' ),
			'time'        => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $entry['time'] ),
			'pages'       => count( (array) $entry['posts'] ),
			'submissions' => $submissions,
		];
	}

	/**
	 * Run the pure converter with this site's facts.
	 *
	 * @param array<string, mixed> $form
	 * @param string               $form_id
	 * @return array<string, mixed>
	 */
	private function plan( array $form, string $form_id ): array {
		$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
		return ( new Converter() )->convert(
			$form,
			[
				'file_block'  => \WP_Block_Type_Registry::get_instance()->is_registered( 'flinkform/field-file' ),
				'privacy_url' => $privacy > 0 && 'publish' === get_post_status( $privacy ) ? (string) get_permalink( $privacy ) : '',
				'form_id'     => $form_id,
			]
		);
	}

	/**
	 * Find (and optionally replace) the embeds of one form.
	 *
	 * @param array<string, mixed> $form
	 * @param int                  $pattern_id wp_block ID the embeds point to after import.
	 * @param bool                 $write      Change the posts (else dry run).
	 * @return array{pages: array<int, array<string, mixed>>, notes: array<int, array{level: string, text: string}>, changed: array<int, array<int, array{0: string, 1: string}>>}
	 */
	private function scan_embeds( array $form, int $pattern_id, bool $write = false ): array {
		global $wpdb;
		$pages   = [];
		$notes   = [];
		$changed = [];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off scan in an admin action; LIKE on post_content, like the Indexer.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ( %s, 'revision' ) AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' ) AND ( post_content LIKE %s OR post_content LIKE %s )",
				self::POST_TYPE,
				'%' . $wpdb->esc_like( '[contact-form' ) . '%',
				'%' . $wpdb->esc_like( 'contact-form-7/contact-form-selector' ) . '%'
			)
		);

		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$result = EmbedReplacer::replace( $post->post_content, $form, max( 1, $pattern_id ) );
			if ( empty( $result['replacements'] ) && 0 === $result['inline'] ) {
				continue;
			}
			$ref = $this->page_ref( $post );
			$ref['replaced'] = count( $result['replacements'] );
			$ref['inline']   = $result['inline'];
			$ref['classic']  = false === strpos( $post->post_content, '<!-- wp:' );
			$pages[]         = $ref;

			if ( $result['inline'] > 0 ) {
				$notes[] = [ 'level' => Converter::CHECK, 'text' => sprintf(
					/* translators: %s: page title. */
					__( '"%s" uses the form inside running text. That spot is left as it is; replace it by hand.', 'flinkform' ),
					$ref['title']
				) ];
			}
			if ( $ref['classic'] && ! empty( $result['replacements'] ) ) {
				$notes[] = [ 'level' => Converter::INFO, 'text' => sprintf(
					/* translators: %s: page title. */
					__( '"%s" uses the classic editor. The form shows on the page as usual, but the classic editor displays it as an HTML comment.', 'flinkform' ),
					$ref['title']
				) ];
			}
			if ( $write && ! empty( $result['replacements'] ) ) {
				$updated = wp_update_post( [ 'ID' => $post->ID, 'post_content' => wp_slash( $result['content'] ) ], true );
				if ( ! is_wp_error( $updated ) ) {
					$changed[ $post->ID ] = $result['replacements'];
				}
			}
		}

		foreach ( $this->other_embeds() as $where ) {
			$notes[] = [ 'level' => Converter::CHECK, 'text' => sprintf(
				/* translators: %s: where a CF7 form was found (page builder data, widget, …). */
				__( 'A Contact Form 7 form is also used in %s. That is not changed automatically; please swap it by hand.', 'flinkform' ),
				$where
			) ];
		}

		return [ 'pages' => $pages, 'notes' => $notes, 'changed' => $changed ];
	}

	/**
	 * Embeds we report but never touch: page-builder data in post meta and
	 * widgets. Not matched to a single form (builders store it their own
	 * way), so every form's preview mentions them.
	 *
	 * @return array<int, string>
	 */
	private function other_embeds(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		global $wpdb;
		$cache = [];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off scan in an admin action.
		$meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.post_id, m.meta_key FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type NOT IN ( %s, 'revision' ) AND p.post_status NOT IN ( 'trash', 'auto-draft' ) AND m.meta_value LIKE %s LIMIT 50",
				self::POST_TYPE,
				'%' . $wpdb->esc_like( 'contact-form-7' ) . '%'
			),
			ARRAY_A
		);
		foreach ( (array) $meta as $row ) {
			$cache[] = sprintf(
				/* translators: 1: post title, 2: meta key. */
				__( '"%1$s" (%2$s, e.g. a page builder)', 'flinkform' ),
				get_the_title( (int) $row['post_id'] ),
				(string) $row['meta_key']
			);
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off scan in an admin action.
		$widgets = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s",
				$wpdb->esc_like( 'widget_' ) . '%',
				'%' . $wpdb->esc_like( 'contact-form' ) . '%'
			)
		);
		foreach ( (array) $widgets as $name ) {
			$cache[] = sprintf(
				/* translators: %s: widget option name. */
				__( 'a widget (%s)', 'flinkform' ),
				(string) $name
			);
		}
		return $cache;
	}

	/**
	 * @param \WP_Post $post
	 * @return array<string, mixed>
	 */
	private function page_ref( \WP_Post $post ): array {
		return [
			'id'    => (int) $post->ID,
			'title' => '' !== $post->post_title ? $post->post_title : sprintf( '#%d', $post->ID ),
			'type'  => (string) $post->post_type,
			'view'  => (string) get_permalink( $post ),
			'edit'  => (string) get_edit_post_link( $post->ID, 'raw' ),
		];
	}
}
