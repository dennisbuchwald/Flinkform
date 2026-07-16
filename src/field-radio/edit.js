import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';

import { generateFieldName } from '../shared/field-name';
import { OptionsEditor } from '../shared/options-editor';
import FullWidthPanel from '../shared/full-width-panel';
import ConditionalLogicPanel from '../shared/conditional-logic-panel';

export default function Edit( { attributes, setAttributes, context, clientId } ) {
	const { label, required, helpText, fieldName, options, display } = attributes;
	const asButtons = 'buttons' === display;
	const blockProps = useBlockProps( {
		className: `flinkform-field flinkform-field--radio${ asButtons ? ' is-buttons' : '' }`,
	} );

	useEffect( () => {
		if ( ! fieldName ) {
			setAttributes( { fieldName: generateFieldName( 'radio' ) } );
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
					<ToggleControl
						label={ __( 'Required', 'flinkform' ) }
						checked={ !! required }
						onChange={ ( v ) => setAttributes( { required: v } ) }
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
					<SelectControl
						label={ __( 'Display', 'flinkform' ) }
						help={ __( 'Show the choices as a plain list or as selectable buttons.', 'flinkform' ) }
						value={ display || 'list' }
						options={ [
							{ label: __( 'List', 'flinkform' ), value: 'list' },
							{ label: __( 'Buttons', 'flinkform' ), value: 'buttons' },
						] }
						onChange={ ( v ) => setAttributes( { display: v } ) }
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

			<fieldset { ...blockProps }>
				<legend className="flinkform-field__label">
					{ label }
					{ required && <span className="flinkform-field__required" aria-hidden="true"> *</span> }
				</legend>
				{ safeOptions.map( ( opt, i ) => (
					<label key={ i } className="flinkform-field__option">
						<input type="radio" name={ `preview-${ fieldName }` } defaultChecked={ i === 0 } disabled aria-disabled="true" />
						<span>{ opt.label || opt.value || '' }</span>
					</label>
				) ) }
				{ helpText && <p className="flinkform-field__help">{ helpText }</p> }
			</fieldset>
		</>
	);
}
