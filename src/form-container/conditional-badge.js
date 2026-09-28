/**
 * "Conditional" badge on field blocks with an active rule (1.15.0).
 *
 * A hidden-by-default field looks like any other in the editor, so a form
 * that "loses" a field on the frontend was a common head-scratcher. The
 * badge sits on the block wrapper through a data attribute (translated
 * here, printed by CSS), so no field block had to change.
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';
import { hasActiveCondition } from '../shared/condition-choices';

const withConditionalBadge = createHigherOrderComponent(
	( BlockListBlock ) => ( props ) => {
		if ( ! hasActiveCondition( props.name, props.attributes ) ) {
			return <BlockListBlock { ...props } />;
		}
		const wrapperProps = {
			...( props.wrapperProps || {} ),
			'data-flinkform-conditional': props.name === 'flinkform/page-break'
				? __( 'Step shown conditionally', 'flinkform' )
				: __( 'Conditional', 'flinkform' ),
		};
		return <BlockListBlock { ...props } wrapperProps={ wrapperProps } />;
	},
	'withFlinkformConditionalBadge'
);

addFilter( 'editor.BlockListBlock', 'flinkform/conditional-badge', withConditionalBadge );
