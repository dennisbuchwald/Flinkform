<?php
/**
 * Merge tag resolver for notification templates.
 *
 * Replaces `{field:<name>}`, `{form:title}`, `{site:name}`, `{site:url}`,
 * `{submission:date}`, and `{submission:id}` tokens in arbitrary strings.
 *
 * Designed to be shared across notification surfaces — the Mailer uses it
 * today, webhooks and integrations will reuse the same context in later
 * phases. The context build is exposed separately so listeners on
 * `perform_after_submission` can compose it once and reuse it.
 *
 * Unknown tags pass through the `perform_resolve_merge_tag` filter; if
 * still unresolved they remain in the output verbatim so authors notice
 * the typo instead of silently shipping empty strings.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

namespace PerForm\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless template engine for PerForm merge tags.
 */
final class MergeTags {

	/**
	 * Build the resolution context for a single submission.
	 *
	 * Multi-value fields (checkbox group, multi-select) are flattened to a
	 * comma-separated string for the `fields` lookup so a notification
	 * template can drop `{field:topics}` and get a human-readable line
	 * without conditional logic. The original definition stays available
	 * under `field_defs` for filter listeners that need richer access.
	 *
	 * @param int                  $submission_id Newly inserted row ID.
	 * @param string               $form_id       UUID of the form.
	 * @param array<string, mixed> $clean         Sanitised values keyed by field name.
	 * @param array{attributes: array<string, mixed>, fields: array<int, array<string, mixed>>} $form_def Authoritative form definition.
	 * @return array<string, mixed>
	 */
	public static function context( int $submission_id, string $form_id, array $clean, array $form_def ): array {
		$attrs  = isset( $form_def['attributes'] ) && is_array( $form_def['attributes'] ) ? $form_def['attributes'] : [];
		$fields = isset( $form_def['fields'] ) && is_array( $form_def['fields'] ) ? $form_def['fields'] : [];

		$field_values = [];
		$field_labels = [];
		foreach ( $fields as $field ) {
			$name = (string) ( $field['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$value                   = $clean[ $name ] ?? '';
			$field_values[ $name ]   = is_array( $value )
				? implode( ', ', array_map( 'strval', $value ) )
				: (string) $value;
			$field_labels[ $name ]   = (string) ( $field['label'] ?? $name );
		}

		$context = [
			'submission_id' => $submission_id,
			'form_id'       => $form_id,
			'form_title'    => (string) ( $attrs['title'] ?? '' ),
			'fields'        => $field_values,
			'field_labels'  => $field_labels,
			'field_defs'    => $fields,
			'site_name'     => (string) get_bloginfo( 'name' ),
			'site_url'      => (string) home_url( '/' ),
			'submitted_at'  => (string) current_time( 'mysql' ),
		];

		/**
		 * Filter the merge-tag resolution context before tokens are replaced.
		 *
		 * Lets integrations add (or rewrite) keys to expose custom merge
		 * tags. The returned array is the canonical context handed to
		 * render() for the remainder of the request.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $context       Default context.
		 * @param int                  $submission_id
		 * @param string               $form_id
		 * @param array<string, mixed> $clean
		 * @param array<string, mixed> $form_def
		 */
		return (array) apply_filters( 'perform_merge_tags_context', $context, $submission_id, $form_id, $clean, $form_def );
	}

	/**
	 * Replace merge tags in a template string.
	 *
	 * Returns the original template untouched when no tokens are present so
	 * callers can pass plain strings through without checks.
	 *
	 * @param string               $template Raw template, may contain merge tags.
	 * @param array<string, mixed> $context  Built via self::context().
	 * @return string
	 */
	public static function render( string $template, array $context ): string {
		if ( '' === $template || false === strpos( $template, '{' ) ) {
			return $template;
		}

		$result = preg_replace_callback(
			'/\{([a-z]+):([A-Za-z0-9_\-]+)\}/',
			static function ( array $m ) use ( $context ): string {
				return self::resolve_tag( $m[1], $m[2], $context );
			},
			$template
		);

		return is_string( $result ) ? $result : $template;
	}

	/**
	 * Resolve a single namespace:key tag against the context.
	 *
	 * Built-ins produce strings; an unknown tag yields null, which the
	 * filter may turn into a string. A still-null result is rendered
	 * verbatim (e.g. `{field:typo}` stays as `{field:typo}` in the output).
	 *
	 * @param string               $namespace
	 * @param string               $key
	 * @param array<string, mixed> $context
	 * @return string
	 */
	private static function resolve_tag( string $namespace, string $key, array $context ): string {
		$resolved = null;

		switch ( $namespace ) {
			case 'field':
				$fields = $context['fields'] ?? [];
				if ( is_array( $fields ) && array_key_exists( $key, $fields ) ) {
					$resolved = (string) $fields[ $key ];
				}
				break;
			case 'form':
				if ( 'title' === $key ) {
					$resolved = (string) ( $context['form_title'] ?? '' );
				}
				break;
			case 'site':
				if ( 'name' === $key ) {
					$resolved = (string) ( $context['site_name'] ?? '' );
				} elseif ( 'url' === $key ) {
					$resolved = (string) ( $context['site_url'] ?? '' );
				}
				break;
			case 'submission':
				if ( 'id' === $key ) {
					$resolved = (string) (int) ( $context['submission_id'] ?? 0 );
				} elseif ( 'date' === $key ) {
					$resolved = (string) ( $context['submitted_at'] ?? '' );
				}
				break;
		}

		/**
		 * Filter the resolution of a single merge tag.
		 *
		 * Listeners may handle an unknown namespace:key combination or
		 * override a built-in. Returning null leaves the tag verbatim in
		 * the output so authors notice the typo.
		 *
		 * @since 0.1.0
		 *
		 * @param string|null          $resolved  Built-in resolution, or null.
		 * @param string               $namespace
		 * @param string               $key
		 * @param array<string, mixed> $context
		 */
		$resolved = apply_filters( 'perform_resolve_merge_tag', $resolved, $namespace, $key, $context );

		if ( null === $resolved ) {
			return '{' . $namespace . ':' . $key . '}';
		}

		return (string) $resolved;
	}
}
