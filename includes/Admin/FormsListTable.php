<?php
/**
 * Forms list table.
 *
 * WP_List_Table extension that powers wp-admin "Flinkform → Forms". Pulls
 * the aggregate index from Forms\Indexer and presents it as a familiar
 * wp-admin grid with search, sort and per-row navigation actions.
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Admin;

use Flinkform\Forms\Indexer;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the forms overview in wp-admin.
 */
final class FormsListTable extends \WP_List_Table {

	private Indexer $indexer;

	/**
	 * Cached search term parsed from the request.
	 */
	private string $search = '';

	public function __construct( Indexer $indexer ) {
		parent::__construct(
			[
				'singular' => 'form',
				'plural'   => 'forms',
				'ajax'     => false,
			]
		);
		$this->indexer = $indexer;
	}

	/**
	 * Column definitions.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'title'             => __( 'Title', 'flinkform' ),
			'form_id'           => __( 'Form ID', 'flinkform' ),
			'source'            => __( 'Source page', 'flinkform' ),
			'submission_count'  => __( 'Submissions', 'flinkform' ),
			'last_submission'   => __( 'Last submission', 'flinkform' ),
		];
	}

	/**
	 * Columns the user can click to sort by.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns(): array {
		return [
			'title'            => [ 'title', false ],
			'submission_count' => [ 'submission_count', false ],
			'last_submission'  => [ 'last_submission', true ],
		];
	}

	/**
	 * No bulk actions in Phase 2c — forms aren't a bulk-managed entity yet.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return [];
	}

	/**
	 * Pull data, apply search/sort, populate $items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only listing parameters.
		$this->search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby      = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'last_submission';
		$order        = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'asc' : 'desc';
		// phpcs:enable

		$forms = $this->indexer->all();

		if ( '' !== $this->search ) {
			$needle = strtolower( $this->search );
			$forms  = array_values( array_filter(
				$forms,
				static function ( array $form ) use ( $needle ): bool {
					if ( false !== stripos( (string) $form['title'], $needle ) ) {
						return true;
					}
					if ( false !== stripos( (string) $form['form_id'], $needle ) ) {
						return true;
					}
					foreach ( $form['sources'] as $source ) {
						if ( is_array( $source ) && false !== stripos( (string) ( $source['post_title'] ?? '' ), $needle ) ) {
							return true;
						}
					}
					return false;
				}
			) );
		}

		// PHP-side sort — list is small (one row per form), so the cost
		// is negligible and we avoid building a per-column SQL story.
		usort(
			$forms,
			static function ( array $a, array $b ) use ( $orderby, $order ): int {
				switch ( $orderby ) {
					case 'title':
						$cmp = strcasecmp( (string) $a['title'], (string) $b['title'] );
						break;
					case 'submission_count':
						$cmp = ( (int) $a['submission_count'] ) <=> ( (int) $b['submission_count'] );
						break;
					case 'last_submission':
					default:
						$cmp = strcmp( (string) $a['last_submission_at'], (string) $b['last_submission_at'] );
						break;
				}
				return 'asc' === $order ? $cmp : -$cmp;
			}
		);

		$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
		$this->items           = $forms;
	}

	/**
	 * Empty-state message.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No forms found. Add a Flinkform Form block to a page to get started.', 'flinkform' );
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param array<string, mixed> $item
	 * @param string               $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}

	/**
	 * Title column (primary) — bold title + row actions.
	 *
	 * @param array<string, mixed> $item
	 * @return string
	 */
	public function column_title( $item ): string {
		$form_id   = (string) ( $item['form_id'] ?? '' );
		$title     = (string) ( $item['title'] ?? '' );
		$is_orphan = empty( $item['sources'] );

		$primary = esc_html( '' !== $title ? $title : __( '(Untitled form)', 'flinkform' ) );
		if ( ! ( $item['has_explicit_title'] ?? false ) && ! $is_orphan ) {
			$primary .= ' <span class="flinkform-tag" title="' . esc_attr__( 'No explicit title set — falling back to the source page name.', 'flinkform' ) . '">' . esc_html__( 'auto', 'flinkform' ) . '</span>';
		}
		if ( $is_orphan ) {
			$primary .= ' <span class="flinkform-tag flinkform-tag--warning">' . esc_html__( 'orphan', 'flinkform' ) . '</span>';
		}

		$submissions_url = add_query_arg(
			[
				'page'    => Menu::PARENT_SLUG,
				'form_id' => $form_id,
			],
			admin_url( 'admin.php' )
		);

		$actions = [
			'submissions' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $submissions_url ),
				esc_html__( 'View submissions', 'flinkform' )
			),
		];

		$first_source = $item['sources'][0] ?? null;
		if ( is_array( $first_source ) && ! empty( $first_source['post_id'] ) ) {
			$actions['edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_edit_post_link( (int) $first_source['post_id'] ) ?? '#' ),
				esc_html__( 'Edit page', 'flinkform' )
			);
			$permalink = get_permalink( (int) $first_source['post_id'] );
			if ( false !== $permalink ) {
				$actions['view'] = sprintf(
					'<a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( $permalink ),
					esc_html__( 'View on site', 'flinkform' )
				);
			}
		}

		return '<strong>' . $primary . '</strong>' . $this->row_actions( $actions );
	}

	/**
	 * Form-ID column (short UUID with tooltip).
	 *
	 * @param array<string, mixed> $item
	 * @return string
	 */
	public function column_form_id( $item ): string {
		$uuid = (string) ( $item['form_id'] ?? '' );
		if ( '' === $uuid ) {
			return '<em>' . esc_html__( 'unknown', 'flinkform' ) . '</em>';
		}
		return sprintf(
			'<code title="%s">%s…</code>',
			esc_attr( $uuid ),
			esc_html( substr( $uuid, 0, 8 ) )
		);
	}

	/**
	 * Source column — list of pages embedding this form.
	 *
	 * @param array<string, mixed> $item
	 * @return string
	 */
	public function column_source( $item ): string {
		$sources = isset( $item['sources'] ) && is_array( $item['sources'] ) ? $item['sources'] : [];
		if ( empty( $sources ) ) {
			return '<em>' . esc_html__( 'no live page', 'flinkform' ) . '</em>';
		}

		$links = [];
		foreach ( $sources as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$post_id    = (int) ( $source['post_id'] ?? 0 );
			$post_title = (string) ( $source['post_title'] ?? __( '(no title)', 'flinkform' ) );
			$status     = (string) ( $source['post_status'] ?? '' );
			$edit_url   = get_edit_post_link( $post_id );

			$label = esc_html( $post_title );
			if ( 'publish' !== $status ) {
				$label .= ' <span class="flinkform-tag">' . esc_html( $status ) . '</span>';
			}

			$links[] = $edit_url
				? '<a href="' . esc_url( $edit_url ) . '">' . $label . '</a>'
				: $label;
		}

		return implode( '<br />', $links );
	}

	/**
	 * Submission-count column.
	 *
	 * @param array<string, mixed> $item
	 * @return string
	 */
	public function column_submission_count( $item ): string {
		$count = (int) ( $item['submission_count'] ?? 0 );
		if ( 0 === $count ) {
			return '<span style="opacity:0.6;">0</span>';
		}
		$url = add_query_arg(
			[
				'page'    => Menu::PARENT_SLUG,
				'form_id' => (string) ( $item['form_id'] ?? '' ),
			],
			admin_url( 'admin.php' )
		);
		return sprintf( '<a href="%s"><strong>%d</strong></a>', esc_url( $url ), $count );
	}

	/**
	 * Last-submission column (formatted local time).
	 *
	 * @param array<string, mixed> $item
	 * @return string
	 */
	public function column_last_submission( $item ): string {
		$gmt = (string) ( $item['last_submission_at'] ?? '' );
		if ( '' === $gmt ) {
			return '<em>' . esc_html__( 'never', 'flinkform' ) . '</em>';
		}
		return esc_html( get_date_from_gmt( $gmt, 'Y-m-d H:i' ) );
	}
}
