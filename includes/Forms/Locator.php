<?php
/**
 * Locate a form definition inside a source post.
 *
 * Phase 1 stores form definitions as block markup inside the page they're
 * embedded on. To validate a submission we parse the source post, walk the
 * block tree, find the `perform/form` block whose `formId` attribute
 * matches the submitted UUID, and extract its field children.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves form UUIDs to their authoritative field definitions.
 */
final class Locator {

	/**
	 * Block name of the form container.
	 */
	private const FORM_BLOCK = 'perform/form';

	/**
	 * Map of supported field block names to their canonical type.
	 *
	 * @var array<string, string>
	 */
	private const FIELD_BLOCKS = [
		'perform/field-text'     => 'text',
		'perform/field-email'    => 'email',
		'perform/field-textarea' => 'textarea',
	];

	/**
	 * Locate a form's attributes + field list inside a post.
	 *
	 * Returns null when the post does not contain a matching form block.
	 *
	 * @param int    $post_id Source post that embeds the form.
	 * @param string $form_id UUID stored in the form block's `formId` attr.
	 * @return array{
	 *     attributes: array<string, mixed>,
	 *     fields: array<int, array{name: string, type: string, label: string, required: bool}>
	 * }|null
	 */
	public function locate( int $post_id, string $form_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$blocks = parse_blocks( $post->post_content );
		$found  = $this->find_form( $blocks, $form_id );

		if ( null === $found ) {
			return null;
		}

		return [
			'attributes' => isset( $found['attrs'] ) && is_array( $found['attrs'] ) ? $found['attrs'] : [],
			'fields'     => $this->collect_fields( $found['innerBlocks'] ?? [] ),
		];
	}

	/**
	 * Walk the block tree depth-first, return the matching form block.
	 *
	 * @param array<int, array<string, mixed>> $blocks  Parsed blocks.
	 * @param string                           $form_id Target form UUID.
	 * @return array<string, mixed>|null
	 */
	private function find_form( array $blocks, string $form_id ): ?array {
		foreach ( $blocks as $block ) {
			$name  = $block['blockName'] ?? '';
			$attrs = $block['attrs'] ?? [];

			if ( self::FORM_BLOCK === $name && ( $attrs['formId'] ?? '' ) === $form_id ) {
				return $block;
			}

			$inner = $block['innerBlocks'] ?? [];
			if ( ! empty( $inner ) ) {
				$nested = $this->find_form( $inner, $form_id );
				if ( null !== $nested ) {
					return $nested;
				}
			}
		}

		return null;
	}

	/**
	 * Reduce a form's inner blocks to a flat, validated field list.
	 *
	 * Unknown block types (e.g. a stray core/paragraph the user dropped in)
	 * are skipped silently — they exist in the markup but are not part of
	 * the submission contract.
	 *
	 * @param array<int, array<string, mixed>> $inner_blocks Raw inner blocks.
	 * @return array<int, array{name: string, type: string, label: string, required: bool}>
	 */
	private function collect_fields( array $inner_blocks ): array {
		$fields = [];

		foreach ( $inner_blocks as $block ) {
			$block_name = $block['blockName'] ?? '';
			if ( ! isset( self::FIELD_BLOCKS[ $block_name ] ) ) {
				continue;
			}

			$attrs = $block['attrs'] ?? [];
			$name  = isset( $attrs['fieldName'] ) && is_string( $attrs['fieldName'] ) ? $attrs['fieldName'] : '';
			if ( '' === $name ) {
				continue;
			}

			$fields[] = [
				'name'     => $name,
				'type'     => self::FIELD_BLOCKS[ $block_name ],
				'label'    => $this->resolve_label( $block_name, $attrs, $name ),
				'required' => ! empty( $attrs['required'] ),
			];
		}

		return $fields;
	}

	/**
	 * Resolve the user-facing label for a field.
	 *
	 * Gutenberg only writes a block attribute into the post_content when it
	 * differs from the block.json default — so a field whose label matches
	 * the type's default ("Email", "Message") never carries an explicit
	 * `label` in $attrs. We pull the default from the registered block type
	 * before falling all the way back to the (cryptic) fieldName.
	 *
	 * @param string               $block_name e.g. "perform/field-email".
	 * @param array<string, mixed> $attrs      Parsed attrs from post_content.
	 * @param string               $field_name fieldName as last-resort label.
	 * @return string
	 */
	private function resolve_label( string $block_name, array $attrs, string $field_name ): string {
		if ( isset( $attrs['label'] ) && is_string( $attrs['label'] ) && '' !== $attrs['label'] ) {
			return $attrs['label'];
		}

		$registry   = \WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block_name );

		if ( $block_type && isset( $block_type->attributes['label']['default'] ) && is_string( $block_type->attributes['label']['default'] ) ) {
			return $block_type->attributes['label']['default'];
		}

		return $field_name;
	}
}
