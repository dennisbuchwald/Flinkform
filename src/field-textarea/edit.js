/**
 * Field — Textarea — editor component.
 */
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

import { generateFieldName } from '../shared/field-name';
import FullWidthPanel from '../shared/full-width-panel';

export default function Edit( { attributes, setAttributes, context } ) {
	const { label, placeholder, required, helpText, fieldName, rows } = attributes;
	const blockProps = useBlockProps( { className: 'perform-field perform-field--textarea' } );

	useEffect( () => {
		if ( ! fieldName ) {
			setAttributes( { fieldName: generateFieldName( 'textarea' ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Field Settings', 'perform-forms' ) }>
					<TextControl
						label={ __( 'Label', 'perform-forms' ) }
						value={ label }
						onChange={ ( v ) => setAttributes( { label: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Placeholder', 'perform-forms' ) }
						value={ placeholder }
						onChange={ ( v ) => setAttributes( { placeholder: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<ToggleControl
						label={ __( 'Required', 'perform-forms' ) }
						checked={ !! required }
						onChange={ ( v ) => setAttributes( { required: v } ) }
						__nextHasNoMarginBottom
					/>
					<RangeControl
						label={ __( 'Rows', 'perform-forms' ) }
						value={ rows }
						min={ 2 }
						max={ 20 }
						onChange={ ( v ) => setAttributes( { rows: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
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
			</InspectorControls>

			<div { ...blockProps }>
				<label className="perform-field__label">
					{ label }
					{ required && <span className="perform-field__required" aria-hidden="true"> *</span> }
				</label>
				<textarea
					className="perform-field__input"
					placeholder={ placeholder }
					rows={ rows }
					disabled
					aria-disabled="true"
				/>
				{ helpText && <p className="perform-field__help">{ helpText }</p> }
			</div>
		</>
	);
}
