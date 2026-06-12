import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

import { generateFieldName } from '../shared/field-name';
import FullWidthPanel from '../shared/full-width-panel';
import ConditionalLogicPanel from '../shared/conditional-logic-panel';

export default function Edit( { attributes, setAttributes, context, clientId } ) {
	const { label, placeholder, required, helpText, fieldName, min, max, step } = attributes;
	const blockProps = useBlockProps( { className: 'flinkform-field flinkform-field--number' } );

	useEffect( () => {
		if ( ! fieldName ) {
			setAttributes( { fieldName: generateFieldName( 'number' ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Field Settings', 'flinkform' ) }>
					<TextControl
						label={ __( 'Label', 'flinkform' ) }
						value={ label }
						onChange={ ( v ) => setAttributes( { label: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Placeholder', 'flinkform' ) }
						value={ placeholder }
						onChange={ ( v ) => setAttributes( { placeholder: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<ToggleControl
						label={ __( 'Required', 'flinkform' ) }
						checked={ !! required }
						onChange={ ( v ) => setAttributes( { required: v } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Min', 'flinkform' ) }
						value={ min }
						onChange={ ( v ) => setAttributes( { min: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Max', 'flinkform' ) }
						value={ max }
						onChange={ ( v ) => setAttributes( { max: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Step', 'flinkform' ) }
						help={ __( 'Decimal step, e.g. 0.01 for currency. Leave empty for whole numbers.', 'flinkform' ) }
						value={ step }
						onChange={ ( v ) => setAttributes( { step: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Help Text', 'flinkform' ) }
						value={ helpText }
						onChange={ ( v ) => setAttributes( { helpText: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Field Name', 'flinkform' ) }
						help={ __( 'Key used in submission data. Auto-generated; change with care.', 'flinkform' ) }
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
				<label className="flinkform-field__label">
					{ label }
					{ required && <span className="flinkform-field__required" aria-hidden="true"> *</span> }
				</label>
				<input
					type="number"
					className="flinkform-field__input"
					placeholder={ placeholder }
					disabled
					aria-disabled="true"
				/>
				{ helpText && <p className="flinkform-field__help">{ helpText }</p> }
			</div>
		</>
	);
}
