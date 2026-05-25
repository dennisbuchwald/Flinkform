<?php
/**
 * Server-side render for the Page Break block.
 *
 * Page Break is a structural marker, not a visible element. The form
 * container's render.php iterates over its inner blocks and uses page-break
 * positions to slice the form into steps — page-break itself emits nothing
 * into the page on its own. Returning empty here is the intentional contract:
 * the container is the single source of truth for step markup, label
 * resolution, and (later) ARIA wiring.
 *
 * @package PerForm
 * @since 0.1.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

return;
