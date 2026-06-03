<?php
/**
 * Server-side render for the Section Heading block.
 *
 * Pure presentation — no fieldName, no POST data, no validation.
 *
 * @var array<string, mixed> $attributes
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

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

$heading_class = 'perform-section-heading';
if ( $full_width ) {
	$heading_class .= ' perform-section-heading--full-width';
}
?>
<div class="<?php echo esc_attr( $heading_class ); ?>"<?php echo \PerForm\Conditions\Wrapper::data_attribute( $attributes['conditionalLogic'] ?? [] ); ?>>
	<?php if ( '' !== $title ) : ?>
		<<?php echo esc_attr( $heading_tag ); ?> class="perform-section-heading__title"><?php echo wp_kses_post( $title ); ?></<?php echo esc_attr( $heading_tag ); ?>>
	<?php endif; ?>
	<?php if ( '' !== $description ) : ?>
		<p class="perform-section-heading__description"><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>
</div>
