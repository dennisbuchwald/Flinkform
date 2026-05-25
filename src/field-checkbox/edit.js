import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

import { generateFieldName } from '../shared/field-name';
import { OptionsEditor } from '../shared/options-editor';
import FullWidthPanel from '../shared/full-width-panel';

export default function Edit( { attributes, setAttributes, context } ) {
	const { label, required, helpText, fieldName, options } = attributes;
	const blockProps = useBlockProps( { className: 'perform-field perform-field--checkbox' } );

	useEffect( () => {
		if ( ! fieldName ) {
			setAttributes( { fieldName: generateFieldName( 'checkbox' ) } );
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
					<ToggleControl
						label={ __( 'Required (at least one)', 'perform-forms' ) }
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

			<fieldset { ...blockProps }>
				<legend className="perform-field__label">
					{ label }
					{ required && <span className="perform-field__required" aria-hidden="true"> *</span> }
				</legend>
				{ safeOptions.map( ( opt, i ) => (
					<label key={ i } style={ { display: 'flex', alignItems: 'center', gap: '6px', margin: '4px 0' } }>
						<input type="checkbox" disabled aria-disabled="true" />
						<span>{ opt.label || opt.value || '' }</span>
					</label>
				) ) }
				{ helpText && <p className="perform-field__help">{ helpText }</p> }
			</fieldset>
		</>
	);
}
