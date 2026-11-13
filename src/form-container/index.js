/**
 * Form Container — block registration entry.
 *
 * Dynamic block (server-rendered via render.php), but `save` MUST emit
 * `<InnerBlocks.Content />` so the child field blocks get serialised into
 * the post_content's block comments. Returning `null` here would drop
 * every child block on save — they'd appear in the editor until the next
 * reload, then vanish, and the frontend would render an empty form.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

import metadata from './block.json';
import Edit from './edit';
import './conditional-badge';
import './style.scss';
import './editor.scss';

/**
 * Branded inserter icon — the form glyph filled with the official
 * dbw-media brand gradient (135°, 5 stops: red #ea2b1f → pink #ff3c6f →
 * magenta #ff4fdd → violet #7e56ff → blue #00b2ff). Single source of
 * truth: Second-Brain/dbw-media.md. Only the main Form block carries the
 * gradient; the field blocks keep their plain currentColor dashicons so
 * the in-form inserter stays calm and theme-adaptive.
 *
 * The glyph is the Flinkform mark (2026-11): an "F" built from a form, two
 * input fields plus a consent checkbox, with the submit button sitting in
 * the F's empty corner. No background tile, so it reads on both the light
 * inserter and the dark list view. The gradient ID is namespaced to avoid
 * colliding with any other inline SVG on the page.
 */
const icon = (
	<svg
		viewBox="0 0 24 24"
		xmlns="http://www.w3.org/2000/svg"
		aria-hidden="true"
		focusable="false"
	>
		<defs>
			<linearGradient
				id="flinkform-icon-grad"
				x1="2"
				y1="4"
				x2="21"
				y2="14"
				gradientUnits="userSpaceOnUse"
			>
				<stop offset="0" stopColor="#ea2b1f" />
				<stop offset="0.25" stopColor="#ff3c6f" />
				<stop offset="0.5" stopColor="#ff4fdd" />
				<stop offset="0.75" stopColor="#7e56ff" />
				<stop offset="1" stopColor="#00b2ff" />
			</linearGradient>
		</defs>
		<rect
			x="2"
			y="2.5"
			width="19"
			height="5"
			rx="2"
			fill="url(#flinkform-icon-grad)"
		/>
		<rect
			x="2"
			y="9.5"
			width="12"
			height="5"
			rx="2"
			fill="url(#flinkform-icon-grad)"
		/>
		<rect
			x="2"
			y="16.5"
			width="5"
			height="5"
			rx="2"
			fill="url(#flinkform-icon-grad)"
		/>
		<circle cx="17.5" cy="17.5" r="4.5" fill="url(#flinkform-icon-grad)" />
		<path
			d="M15.6 17.6l1.3 1.3 2.6-2.7"
			fill="none"
			stroke="#fff"
			strokeWidth="1.6"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
