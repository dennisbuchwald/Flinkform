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
 * Branded inserter icon — the form glyph filled with the Flinkform logo
 * gradient (Magenta #FD3965 → violet → Indigo #6070F0, sampled straight
 * from the logo artwork). Only the main Form block carries the gradient;
 * the field blocks keep their plain currentColor dashicons so the in-form
 * inserter stays calm and theme-adaptive.
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
				<stop offset="0" stopColor="#FD3965" />
				<stop offset="0.55" stopColor="#B84BD9" />
				<stop offset="1" stopColor="#6070F0" />
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
