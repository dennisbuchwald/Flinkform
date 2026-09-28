<?php
/**
 * Validation texts the browser shows before a submit ever leaves the page.
 *
 * Rendered onto the form as `data-flinkform-messages` (JSON) and picked by
 * `src/shared/validation-messages.js` from the control's `validity`. They
 * come from here, not from the browser, so they are in the site's language
 * (and in du or Sie, whatever the site's locale says) instead of the
 * visitor's browser language (1.15.0).
 *
 * Static per locale, so safe in cached HTML.
 *
 * @package Flinkform
 * @since 1.15.0
 */

declare( strict_types = 1 );

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound
namespace Flinkform\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Message catalogue for client-side validation.
 */
final class ClientMessages {

	/**
	 * @return array<string, string> Keys match validation-messages.js.
	 */
	public static function all(): array {
		return [
			'invalid'  => __( 'Please check this field.', 'flinkform' ),
			'required' => __( 'Please fill in this field.', 'flinkform' ),
			'choose'   => __( 'Please choose an option.', 'flinkform' ),
			'check'    => __( 'Please tick this box to continue.', 'flinkform' ),
			'email'    => __( 'Please enter a valid email address.', 'flinkform' ),
			'url'      => __( 'Please enter a valid web address (https://…).', 'flinkform' ),
			'tel'      => __( 'Please enter a valid phone number.', 'flinkform' ),
			'number'   => __( 'Please enter a number.', 'flinkform' ),
			'date'     => __( 'Please enter a valid date.', 'flinkform' ),
			/* translators: %s: smallest allowed number. */
			'min'      => __( 'Please enter a value of at least %s.', 'flinkform' ),
			/* translators: %s: largest allowed number. */
			'max'      => __( 'Please enter a value of at most %s.', 'flinkform' ),
			/* translators: %s: earliest allowed date, already formatted. */
			'dateMin'  => __( 'Please choose a date on or after %s.', 'flinkform' ),
			/* translators: %s: latest allowed date, already formatted. */
			'dateMax'  => __( 'Please choose a date on or before %s.', 'flinkform' ),
			/* translators: %s: minimum number of characters. */
			'tooShort' => __( 'Please use at least %s characters.', 'flinkform' ),
			/* translators: %s: maximum number of characters. */
			'tooLong'  => __( 'Please use at most %s characters.', 'flinkform' ),
			'step'     => __( 'Please enter a valid value.', 'flinkform' ),
		];
	}

	/**
	 * JSON for the data attribute (escape with esc_attr at the call site).
	 *
	 * @return string
	 */
	public static function json(): string {
		$json = wp_json_encode( self::all() );
		return is_string( $json ) ? $json : '{}';
	}
}
