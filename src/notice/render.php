<?php
/**
 * Server-side render for the Notice block.
 *
 * A highlighted note between fields. Submits nothing, so it never
 * reaches the Locator, the Handler or the CSV export — it is a sibling
 * of section-heading and page-break, not a field.
 *
 * Output contract: ECHOES markup directly — see form-container/render.php
 * for why nested ob_start() is unsafe here.
 *
 * @var array<string, mixed> $attributes
 * @var string               $content
 * @var WP_Block             $block
 *
 * @package Flinkform
 * @since 1.7.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$message = isset( $attributes['message'] ) && is_string( $attributes['message'] ) ? $attributes['message'] : '';

// An empty notice would render as a coloured strip with nothing in it.
if ( '' === trim( wp_strip_all_tags( $message ) ) ) {
	return;
}

$allowed_variants = [ 'info', 'success', 'warning', 'important' ];
$variant          = isset( $attributes['variant'] ) && in_array( $attributes['variant'], $allowed_variants, true )
	? $attributes['variant']
	: 'info';

// `showIcon` defaults to true in block.json, and Gutenberg only serialises
// attributes that differ from their default — so a missing key means the
// author left it on. Checking `! empty()` would silently drop the icon.
$show_icon = ! isset( $attributes['showIcon'] ) || true === $attributes['showIcon'];

$classes = 'flinkform-notice flinkform-notice--' . $variant;
if ( ! $show_icon ) {
	$classes .= ' flinkform-notice--no-icon';
}
if ( ! empty( $attributes['fullWidth'] ) ) {
	$classes .= ' flinkform-notice--full-width';
}

// Inline formatting only. The author writes prose here, not markup, so a
// tight allowlist keeps a pasted <script> or a layout-breaking <div> out
// while still supporting the bold/italic/link the RichText editor offers.
$allowed_html = [
	'strong' => [],
	'b'      => [],
	'em'     => [],
	'i'      => [],
	'br'     => [],
	'a'      => [
		'href'   => true,
		'title'  => true,
		'target' => true,
		'rel'    => true,
	],
];
?>
<div class="<?php echo esc_attr( $classes ); ?>"<?php echo \Flinkform\Conditions\Wrapper::condition_attributes( $attributes['conditionalLogic'] ?? [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- returns a pre-escaped attribute string (esc_attr applied inside). ?>>
	<?php if ( $show_icon ) : ?>
		<span class="flinkform-notice__icon" aria-hidden="true"></span>
	<?php endif; ?>
	<p class="flinkform-notice__text"><?php echo wp_kses( $message, $allowed_html ); ?></p>
</div>
