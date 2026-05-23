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

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
