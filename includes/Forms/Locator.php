<?php
/**
 * Locate a form definition inside a source post.
 *
 * Phase 1 stores form definitions as block markup inside the page they're
 * embedded on. To validate a submission we parse the source post, walk the
 * block tree, find the `flinkform/form` block whose `formId` attribute
 * matches the submitted UUID, and extract its field children.
 *
 * Phase 2b extends the field type map and carries per-type extras
 * (options, multi, min/max/step, hidden source) through to the handler
 * so server-side validation can lean on the authoritative definition
 * rather than trusting the POST.
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves form UUIDs to their authoritative field definitions.
 */
final class Locator {

	/**
	 * Object-cache group + TTL for the parsed form definition.
	 */
	private const CACHE_GROUP = 'flinkform_forms';
	private const CACHE_TTL   = 5 * MINUTE_IN_SECONDS;

	/**
	 * Block name of the form container.
	 */
	private const FORM_BLOCK = 'flinkform/form';

	/**
	 * Map of supported field block names to their canonical type.
	 * Section heading is intentionally NOT here — it isn't a field.
	 *
	 * @var array<string, string>
	 */
	private const FIELD_BLOCKS = [
		'flinkform/field-text'     => 'text',
		'flinkform/field-email'    => 'email',
		'flinkform/field-textarea' => 'textarea',
		'flinkform/field-number'   => 'number',
		'flinkform/field-date'     => 'date',
		'flinkform/field-url'      => 'url',
		'flinkform/field-phone'    => 'phone',
		'flinkform/field-toggle'   => 'toggle',
		'flinkform/field-hidden'   => 'hidden',
		'flinkform/field-select'   => 'select',
		'flinkform/field-radio'    => 'radio',
		'flinkform/field-checkbox' => 'checkbox',
		'flinkform/field-consent'  => 'consent',
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
	 *     fields: array<int, array<string, mixed>>,
	 *     steps:  array<int, array<string, mixed>>
	 * }|null
	 */
	public function locate( int $post_id, string $form_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		// Cache the parsed form definition. parse_blocks() over the full
		// post_content runs on every submission otherwise. The key includes a
		// content hash, so editing the post invalidates the entry automatically;
		// a stored `0` marks a verified "no matching form" so misses and
		// negatives stay distinct. On sites with a persistent object cache this
		// also spans requests.
		$cache_key = $post_id . ':' . $form_id . ':' . md5( $post->post_content );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$blocks = parse_blocks( $post->post_content );
		$found  = $this->find_form( $blocks, $form_id );

		if ( null === $found ) {
			wp_cache_set( $cache_key, 0, self::CACHE_GROUP, self::CACHE_TTL );
			return null;
		}

		$inner_blocks = $found['innerBlocks'] ?? [];

		$result = [
			'attributes' => isset( $found['attrs'] ) && is_array( $found['attrs'] ) ? $found['attrs'] : [],
			'fields'     => $this->collect_fields( $inner_blocks ),
			'steps'      => $this->collect_steps( $inner_blocks ),
		];

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL );

		return $result;
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
	 * Each returned record is the canonical contract handed to the
	 * Handler: type, name, label, required, plus type-specific extras.
	 *
	 * Unknown block types (section headings, stray core blocks) are
	 * skipped silently — they exist in the markup but don't take part
	 * in the submission contract.
	 *
	 * @param array<int, array<string, mixed>> $inner_blocks Raw inner blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_fields( array $inner_blocks ): array {
		$fields      = [];
		$step_index  = 0; // Step 0 is everything before the first page-break.

		foreach ( $inner_blocks as $block ) {
			$block_name = $block['blockName'] ?? '';

			if ( 'flinkform/page-break' === $block_name ) {
				$step_index++;
				continue;
			}

			if ( ! isset( self::FIELD_BLOCKS[ $block_name ] ) ) {
				continue;
			}

			$attrs = $block['attrs'] ?? [];
			$name  = isset( $attrs['fieldName'] ) && is_string( $attrs['fieldName'] ) ? $attrs['fieldName'] : '';
			if ( '' === $name ) {
				continue;
			}

			$type    = self::FIELD_BLOCKS[ $block_name ];
			$record  = [
				'name'             => $name,
				'type'             => $type,
				'label'            => $this->resolve_label( $block_name, $attrs, $name ),
				'required'         => ! empty( $attrs['required'] ),
				// Step index this field belongs to (0-based). Phase 7c
				// uses this to strip values when the containing step's
				// page-break condition skips the step entirely.
				'step'             => $step_index,
				// Carries the conditional-logic rule set as-stored on
				// the block so Handler::handle() can re-evaluate it
				// server-side and strip hidden field values from
				// $clean before validation + persistence. Empty when
				// no rule was configured — the evaluator reads that
				// as "always visible" and the strip is a no-op.
				'conditionalLogic' => isset( $attrs['conditionalLogic'] ) && is_array( $attrs['conditionalLogic'] ) ? $attrs['conditionalLogic'] : [],
				// Author-customised required-error message (checkbox group
				// today; empty string falls back to the generic message).
				'requiredMessage'  => isset( $attrs['requiredMessage'] ) && is_string( $attrs['requiredMessage'] ) ? trim( $attrs['requiredMessage'] ) : '',
			];

			// Carry type-specific extras through to the handler. Defaults
			// come from the registered block type so a user who never
			// touched an attribute still gets the right behaviour.
			$record += $this->type_extras( $block_name, $type, $attrs );

			$fields[] = $record;
		}

		return $fields;
	}

	/**
	 * Build a per-step rule-set list. Step 0 (everything before the
	 * first page-break) is always present with an empty rule set; each
	 * subsequent step carries the conditional-logic configured on the
	 * page-break that opens it.
	 *
	 * Mirrors the structure form-container/render.php emits, and gives
	 * the submission handler what it needs to strip every field in a
	 * skipped step (not just the field's own conditional).
	 *
	 * @param array<int, array<string, mixed>> $inner_blocks Raw inner blocks.
	 * @return array<int, array{index: int, conditionalLogic: array<string, mixed>}>
	 */
	private function collect_steps( array $inner_blocks ): array {
		$steps = [ [ 'index' => 0, 'conditionalLogic' => [] ] ];

		foreach ( $inner_blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) !== 'flinkform/page-break' ) {
				continue;
			}
			$attrs = $block['attrs'] ?? [];
			$steps[] = [
				'index'            => count( $steps ),
				'conditionalLogic' => isset( $attrs['conditionalLogic'] ) && is_array( $attrs['conditionalLogic'] ) ? $attrs['conditionalLogic'] : [],
			];
		}

		return $steps;
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
	 * @param string               $block_name e.g. "flinkform/field-email".
	 * @param array<string, mixed> $attrs      Parsed attrs from post_content.
	 * @param string               $field_name fieldName as last-resort label.
	 * @return string
	 */
	private function resolve_label( string $block_name, array $attrs, string $field_name ): string {
		if ( isset( $attrs['label'] ) && is_string( $attrs['label'] ) && '' !== $attrs['label'] ) {
			return $attrs['label'];
		}

		$default = $this->default_attribute( $block_name, 'label' );
		if ( is_string( $default ) && '' !== $default ) {
			return $default;
		}

		return $field_name;
	}

	/**
	 * Build the type-specific extras the handler needs for validation.
	 *
	 * @param string               $block_name
	 * @param string               $type
	 * @param array<string, mixed> $attrs
	 * @return array<string, mixed>
	 */
	private function type_extras( string $block_name, string $type, array $attrs ): array {
		switch ( $type ) {
			case 'number':
				return [
					'min'  => isset( $attrs['min'] ) && '' !== $attrs['min'] ? (string) $attrs['min'] : '',
					'max'  => isset( $attrs['max'] ) && '' !== $attrs['max'] ? (string) $attrs['max'] : '',
					'step' => isset( $attrs['step'] ) && '' !== $attrs['step'] ? (string) $attrs['step'] : '',
				];
			case 'date':
				return [
					'minDate' => isset( $attrs['minDate'] ) && is_string( $attrs['minDate'] ) ? $attrs['minDate'] : '',
					'maxDate' => isset( $attrs['maxDate'] ) && is_string( $attrs['maxDate'] ) ? $attrs['maxDate'] : '',
				];
			case 'select':
				return [
					'multiple' => ! empty( $attrs['multiple'] ),
					'options'  => $this->normalise_options( $attrs['options'] ?? $this->default_attribute( $block_name, 'options' ) ),
				];
			case 'radio':
			case 'checkbox':
				return [
					'options' => $this->normalise_options( $attrs['options'] ?? $this->default_attribute( $block_name, 'options' ) ),
				];
			case 'hidden':
				return [
					'valueSource' => isset( $attrs['valueSource'] ) && is_string( $attrs['valueSource'] ) ? $attrs['valueSource'] : 'static',
					'staticValue' => isset( $attrs['staticValue'] ) && is_string( $attrs['staticValue'] ) ? $attrs['staticValue'] : '',
				];
		}
		return [];
	}

	/**
	 * Coerce an options attribute into a list of string values for the
	 * "allowed values" check, dropping any that aren't well-formed.
	 *
	 * @param mixed $raw
	 * @return array<int, string>
	 */
	private function normalise_options( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$values = [];
		foreach ( $raw as $opt ) {
			if ( ! is_array( $opt ) ) {
				continue;
			}
			$value = isset( $opt['value'] ) ? (string) $opt['value'] : '';
			if ( '' !== $value ) {
				$values[] = $value;
			}
		}
		return $values;
	}

	/**
	 * Pull an attribute default off the registered block type.
	 *
	 * @param string $block_name
	 * @param string $attr_name
	 * @return mixed Null if the attribute or block type isn't registered.
	 */
	private function default_attribute( string $block_name, string $attr_name ) {
		$registry   = \WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block_name );
		if ( ! $block_type || ! isset( $block_type->attributes[ $attr_name ]['default'] ) ) {
			return null;
		}
		return $block_type->attributes[ $attr_name ]['default'];
	}
}
