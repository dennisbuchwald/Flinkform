import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';

import { generateFieldName } from '../shared/field-name';
import FullWidthPanel from '../shared/full-width-panel';
import ConditionalLogicPanel from '../shared/conditional-logic-panel';

export default function Edit( { attributes, setAttributes, context, clientId } ) {
	const { label, required, helpText, fieldName } = attributes;
	const blockProps = useBlockProps( { className: 'perform-field perform-field--toggle' } );

	useEffect( () => {
		if ( ! fieldName ) {
			setAttributes( { fieldName: generateFieldName( 'toggle' ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Field Settings', 'perform-forms' ) }>
					<TextareaControl
						label={ __( 'Label', 'perform-forms' ) }
						help={ __( 'Shown next to the checkbox. Plain HTML allowed.', 'perform-forms' ) }
						value={ label }
						onChange={ ( v ) => setAttributes( { label: v } ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Required (must be checked)', 'perform-forms' ) }
						checked={ !! required }
						onChange={ ( v ) => setAttributes( { required: v } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Help Text', 'perform-forms' ) }
						value={ helpText }
						onChange={ ( v ) => setAttributes( { helpText: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Field Name', 'perform-forms' ) }
						help={ __( 'Key used in submission data. Auto-generated; change with care.', 'perform-forms' ) }
						value={ fieldName }
						onChange={ ( v ) => setAttributes( { fieldName: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</PanelBody>
				<FullWidthPanel attributes={ attributes } setAttributes={ setAttributes } context={ context } />
				<ConditionalLogicPanel attributes={ attributes } setAttributes={ setAttributes } clientId={ clientId } />
			</InspectorControls>

			<div { ...blockProps }>
				<label
					className="perform-field__toggle-label"
					style={ { display: 'flex', alignItems: 'flex-start', gap: '8px', cursor: 'pointer' } }
				>
					<input type="checkbox" disabled aria-disabled="true" />
					<span>
						{ label }
						{ required && <span className="perform-field__required" aria-hidden="true"> *</span> }
					</span>
				</label>
				{ helpText && <p className="perform-field__help">{ helpText }</p> }
			</div>
		</>
	);
}
