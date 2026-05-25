/**
 * Page Break — editor component.
 *
 * Renders a slim horizontal divider with an auto-computed step number and
 * an optional author-supplied label. The step number is derived from the
 * block's position among its siblings inside the parent form: page-breaks
 * are counted up to and including this block, so the first page-break is
 * the boundary that opens Step 2, the second opens Step 3, and so on.
 *
 * The block has no save() output and emits nothing on the frontend itself
 * — the form container's render.php is the single source of truth for
 * step markup. Page Break is purely a structural marker in the block tree.
 */
import { __, sprintf } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

import ConditionalLogicPanel from '../shared/conditional-logic-panel';

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { label } = attributes;
	const blockProps = useBlockProps( { className: 'perform-page-break' } );

	// The step number this break opens. Step 1 is everything before the
	// first break, so the first break opens Step 2; this number is one
	// more than the count of page-breaks at-or-before this block in the
	// parent form's inner-block list.
	const stepNumber = useSelect(
		( select ) => {
			const {
				getBlockRootClientId,
				getBlocks,
				getBlockIndex,
			} = select( 'core/block-editor' );

			const parentClientId = getBlockRootClientId( clientId );
			if ( ! parentClientId ) {
				return 2;
			}
			const siblings = getBlocks( parentClientId );
			const ownIndex = getBlockIndex( clientId );

			let breaksSoFar = 0;
			for ( let i = 0; i <= ownIndex && i < siblings.length; i += 1 ) {
				if ( siblings[ i ].name === 'perform/page-break' ) {
					breaksSoFar += 1;
				}
			}
			return breaksSoFar + 1;
		},
		[ clientId ]
	);

	const stepText = label
		? sprintf(
			/* translators: 1: step number, 2: author-supplied step label */
			__( 'Step %1$d — %2$s', 'perform-forms' ),
			stepNumber,
			label
		)
		: sprintf(
			/* translators: %d: step number */
			__( 'Step %d', 'perform-forms' ),
			stepNumber
		);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Page Break', 'perform-forms' ) }>
					<TextControl
						label={ __( 'Step label', 'perform-forms' ) }
						help={ __( 'Optional. Shown on the progress indicator on the frontend (Phase 5c). Leave empty to fall back to "Step N".', 'perform-forms' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</PanelBody>
				<ConditionalLogicPanel attributes={ attributes } setAttributes={ setAttributes } clientId={ clientId } />
			</InspectorControls>
			<div { ...blockProps }>
				<span className="perform-page-break__rule" aria-hidden="true" />
				<span className="perform-page-break__label">{ stepText }</span>
				<span className="perform-page-break__rule" aria-hidden="true" />
			</div>
		</>
	);
}
