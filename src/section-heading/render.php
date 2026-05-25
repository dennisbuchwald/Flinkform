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

if ( '' === $title && '' === $description ) {
	return;
}

$heading_class = 'perform-section-heading';
if ( $full_width ) {
	$heading_class .= ' perform-section-heading--full-width';
}
?>
<div class="<?php echo esc_attr( $heading_class ); ?>">
	<?php if ( '' !== $title ) : ?>
		<h2 class="perform-section-heading__title"><?php echo wp_kses_post( $title ); ?></h2>
	<?php endif; ?>
	<?php if ( '' !== $description ) : ?>
		<p class="perform-section-heading__description"><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>
</div>
