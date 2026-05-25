import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

import { generateFieldName } from '../shared/field-name';
import { OptionsEditor } from '../shared/options-editor';
import FullWidthPanel from '../shared/full-width-panel';

export default function Edit( { attributes, setAttributes, context } ) {
	const { label, placeholder, required, helpText, fieldName, multiple, options } = attributes;
	const blockProps = useBlockProps( { className: 'perform-field perform-field--select' } );

	useEffect( () => {
		if ( ! fieldName ) {
			setAttributes( { fieldName: generateFieldName( 'select' ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const safeOptions = Array.isArray( options ) ? options : [];

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
						label={ __( 'Placeholder (single select only)', 'perform-forms' ) }
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
					<ToggleControl
						label={ __( 'Allow multiple selections', 'perform-forms' ) }
						checked={ !! multiple }
						onChange={ ( v ) => setAttributes( { multiple: v } ) }
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
						value={ fieldName }
						onChange={ ( v ) => setAttributes( { fieldName: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</PanelBody>
				<PanelBody title={ __( 'Options', 'perform-forms' ) }>
					<OptionsEditor
						options={ safeOptions }
						onChange={ ( next ) => setAttributes( { options: next } ) }
					/>
				</PanelBody>
				<FullWidthPanel attributes={ attributes } setAttributes={ setAttributes } context={ context } />
			</InspectorControls>

			<div { ...blockProps }>
				<label className="perform-field__label">
					{ label }
					{ required && <span className="perform-field__required" aria-hidden="true"> *</span> }
				</label>
				<select className="perform-field__input" multiple={ !! multiple } disabled aria-disabled="true">
					{ placeholder && ! multiple && <option>{ placeholder }</option> }
					{ safeOptions.map( ( opt, i ) => (
						<option key={ i } value={ opt.value || '' }>
							{ opt.label || opt.value || '' }
						</option>
					) ) }
				</select>
				{ helpText && <p className="perform-field__help">{ helpText }</p> }
			</div>
		</>
	);
}
