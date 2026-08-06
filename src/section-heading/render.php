<?php
/**
 * Server-side render for the Section Heading block.
 *
 * Pure presentation — no fieldName, no POST data, no validation.
 *
 * @var array<string, mixed> $attributes
 *
 * @package Flinkform
 * @since 0.1.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$title       = isset( $attributes['title'] ) && is_string( $attributes['title'] ) ? $attributes['title'] : '';
$description = isset( $attributes['description'] ) && is_string( $attributes['description'] ) ? $attributes['description'] : '';
$full_width  = ! empty( $attributes['fullWidth'] );
// Author-chosen heading level keeps the page's heading hierarchy intact;
// clamped to h2–h6 (h1 belongs to the page, not a form section).
$level       = isset( $attributes['headingLevel'] ) ? max( 2, min( 6, (int) $attributes['headingLevel'] ) ) : 2;
$heading_tag = 'h' . $level;

if ( '' === $title && '' === $description ) {
	return;
}

$heading_class = 'flinkform-section-heading';
if ( $full_width ) {
	$heading_class .= ' flinkform-section-heading--full-width';
}

// get_block_wrapper_attributes() folds in the Gutenberg colour support
// (has-text-color class + inline style) the block declares in block.json —
// the author picks the heading colour in the block sidebar, WordPress
// serialises it, and this call emits it. Title and description both
// inherit it; the description keeps its opacity for hierarchy.
$wrapper_attributes = get_block_wrapper_attributes( [ 'class' => $heading_class ] );
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns a pre-escaped attribute string. ?><?php echo \Flinkform\Conditions\Wrapper::condition_attributes( $attributes['conditionalLogic'] ?? [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- returns a pre-escaped attribute string (esc_attr applied inside). ?>>
	<?php if ( '' !== $title ) : ?>
		<<?php echo esc_attr( $heading_tag ); ?> class="flinkform-section-heading__title"><?php echo wp_kses_post( $title ); ?></<?php echo esc_attr( $heading_tag ); ?>>
	<?php endif; ?>
	<?php if ( '' !== $description ) : ?>
		<p class="flinkform-section-heading__description"><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>
</div>
