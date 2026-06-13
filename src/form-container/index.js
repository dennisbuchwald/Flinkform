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
import './style.scss';

/**
 * Branded inserter icon — the form glyph filled with the official
 * dbw-media brand gradient (135°, 5 stops: red #ea2b1f → pink #ff3c6f →
 * magenta #ff4fdd → violet #7e56ff → blue #00b2ff). Single source of
 * truth: Second-Brain/dbw-media.md. Only the main Form block carries the
 * gradient; the field blocks keep their plain currentColor dashicons so
 * the in-form inserter stays calm and theme-adaptive.
 *
 * The form rows are punched out with a <mask> (true transparency) rather
 * than painted, so the icon reads correctly on both the light inserter
 * and the dark list view. The gradient + mask IDs are namespaced to avoid
 * colliding with any other inline SVG on the page.
 */
const icon = (
	<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
		<defs>
			<linearGradient id="flinkform-icon-grad" x1="2" y1="2" x2="22" y2="22" gradientUnits="userSpaceOnUse">
				<stop offset="0" stopColor="#ea2b1f" />
				<stop offset="0.25" stopColor="#ff3c6f" />
				<stop offset="0.5" stopColor="#ff4fdd" />
				<stop offset="0.75" stopColor="#7e56ff" />
				<stop offset="1" stopColor="#00b2ff" />
			</linearGradient>
			<mask id="flinkform-icon-mask">
				<rect x="3.5" y="3" width="17" height="18" rx="3" fill="#fff" />
				<rect x="6.5" y="7" width="7" height="1.8" rx="0.9" fill="#000" />
				<rect x="6.5" y="11.1" width="11" height="1.8" rx="0.9" fill="#000" />
				<rect x="6.5" y="15.2" width="9" height="1.8" rx="0.9" fill="#000" />
			</mask>
		</defs>
		<rect x="3.5" y="3" width="17" height="18" rx="3" fill="url(#flinkform-icon-grad)" mask="url(#flinkform-icon-mask)" />
	</svg>
);

registerBlockType( metadata.name, {
	icon,
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
