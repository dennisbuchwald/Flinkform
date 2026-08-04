import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import { choiceTransforms } from '../shared/choice-transforms';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
	// Radio ↔ checkbox group ↔ select via the block switcher — options,
	// fieldName and conditional logic survive the switch. See
	// shared/choice-transforms.js.
	transforms: choiceTransforms( metadata.name ),
} );
