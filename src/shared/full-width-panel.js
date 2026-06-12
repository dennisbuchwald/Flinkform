/**
 * Shared Inspector panel exposing the field's `fullWidth` attribute.
 *
 * Renders nothing unless the parent form is set to a 2-column layout —
 * that intent flows in via the `flinkform/appearance` block context the
 * form-container provides. The attribute itself stays in storage on
 * 1-column forms (the panel just doesn't show), so flipping the form
 * back to 2 columns restores any prior per-field choice.
 */

import { __ } from '@wordpress/i18n';
import { PanelBody, ToggleControl } from '@wordpress/components';

export default function FullWidthPanel( { attributes, setAttributes, context } ) {
	const columns = context?.[ 'flinkform/appearance' ]?.columns;
	if ( columns !== 2 ) {
		return null;
	}

	return (
		<PanelBody title={ __( 'Layout', 'flinkform' ) } initialOpen={ false }>
			<ToggleControl
				label={ __( 'Full width', 'flinkform' ) }
				help={ __( 'Make this field span both columns of the form.', 'flinkform' ) }
				checked={ !! attributes.fullWidth }
				onChange={ ( value ) => setAttributes( { fullWidth: value } ) }
				__nextHasNoMarginBottom
			/>
		</PanelBody>
	);
}
