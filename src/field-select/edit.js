import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

import { generateFieldName } from '../shared/field-name';
import { OptionsEditor } from '../shared/options-editor';
import FullWidthPanel from '../shared/full-width-panel';
import ConditionalLogicPanel from '../shared/conditional-logic-panel';

export default function Edit( { attributes, setAttributes, context, clientId } ) {
	const { label, placeholder, required, helpText, fieldName, multiple, options } = attributes;
	const blockProps = useBlockProps( { className: 'flinkform-field flinkform-field--select' } );

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
				<PanelBody title={ __( 'Field Settings', 'flinkform' ) }>
					<TextControl
						label={ __( 'Label', 'flinkform' ) }
						value={ label }
						onChange={ ( v ) => setAttributes( { label: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Placeholder (single select only)', 'flinkform' ) }
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
					<ToggleControl
						label={ __( 'Allow multiple selections', 'flinkform' ) }
						checked={ !! multiple }
						onChange={ ( v ) => setAttributes( { multiple: v } ) }
						__nextHasNoMarginBottom
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
						value={ fieldName }
						onChange={ ( v ) => setAttributes( { fieldName: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</PanelBody>
				<PanelBody title={ __( 'Options', 'flinkform' ) }>
					<OptionsEditor
						options={ safeOptions }
						onChange={ ( next ) => setAttributes( { options: next } ) }
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
				<select className="flinkform-field__input" multiple={ !! multiple } disabled aria-disabled="true">
					{ placeholder && ! multiple && <option>{ placeholder }</option> }
					{ safeOptions.map( ( opt, i ) => (
						<option key={ i } value={ opt.value || '' }>
							{ opt.label || opt.value || '' }
						</option>
					) ) }
				</select>
				{ helpText && <p className="flinkform-field__help">{ helpText }</p> }
			</div>
		</>
	);
}
