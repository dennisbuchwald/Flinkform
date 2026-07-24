/**
 * Field — Address — editor component.
 *
 * Composite field: street (+ optional line 2), postal code + city side by
 * side, optional country dropdown. All sub-inputs are disabled in the
 * editor (pure preview). The inspector exposes the standard settings plus
 * toggles for the optional sub-fields.
 */
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

import { generateFieldName } from '../shared/field-name';
import FullWidthPanel from '../shared/full-width-panel';
import ConditionalLogicPanel from '../shared/conditional-logic-panel';

export default function Edit( { attributes, setAttributes, context, clientId } ) {
	const { label, required, helpText, fieldName, showCountry, showAddressLine2, countryDefault } = attributes;
	const blockProps = useBlockProps( { className: 'flinkform-field flinkform-field--address flinkform-field--full-width' } );

	useEffect( () => {
		if ( ! fieldName ) {
			setAttributes( { fieldName: generateFieldName( 'address' ) } );
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
					<ToggleControl
						label={ __( 'Required', 'flinkform' ) }
						help={ __( 'When enabled, street, postal code and city are all required.', 'flinkform' ) }
						checked={ !! required }
						onChange={ ( v ) => setAttributes( { required: v } ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show address line 2', 'flinkform' ) }
						help={ __( 'Additional line for apartment, suite, floor etc.', 'flinkform' ) }
						checked={ !! showAddressLine2 }
						onChange={ ( v ) => setAttributes( { showAddressLine2: v } ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Show country', 'flinkform' ) }
						checked={ !! showCountry }
						onChange={ ( v ) => setAttributes( { showCountry: v } ) }
						__nextHasNoMarginBottom
					/>
					{ showCountry && (
						<TextControl
							label={ __( 'Default country', 'flinkform' ) }
							help={ __( 'Pre-filled value, e.g. "Deutschland". Leave empty for no default.', 'flinkform' ) }
							value={ countryDefault }
							onChange={ ( v ) => setAttributes( { countryDefault: v } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					) }
					<TextControl
						label={ __( 'Help Text', 'flinkform' ) }
						value={ helpText }
						onChange={ ( v ) => setAttributes( { helpText: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Field Name', 'flinkform' ) }
						help={ __( 'Prefix for sub-field keys (e.g. address → address_street, address_zip, address_city). Auto-generated; change with care.', 'flinkform' ) }
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
				<div className="flinkform-field-address__grid">
					<input
						type="text"
						className="flinkform-field__input flinkform-field-address__street"
						placeholder={ __( 'Street + house number', 'flinkform' ) }
						disabled
						aria-disabled="true"
					/>
					{ showAddressLine2 && (
						<input
							type="text"
							className="flinkform-field__input flinkform-field-address__line2"
							placeholder={ __( 'Address line 2', 'flinkform' ) }
							disabled
							aria-disabled="true"
						/>
					) }
					<input
						type="text"
						className="flinkform-field__input flinkform-field-address__zip"
						placeholder={ __( 'Postal code', 'flinkform' ) }
						disabled
						aria-disabled="true"
					/>
					<input
						type="text"
						className="flinkform-field__input flinkform-field-address__city"
						placeholder={ __( 'City', 'flinkform' ) }
						disabled
						aria-disabled="true"
					/>
					{ showCountry && (
						<input
							type="text"
							className="flinkform-field__input flinkform-field-address__country"
							placeholder={ __( 'Country', 'flinkform' ) }
							value={ countryDefault }
							disabled
							aria-disabled="true"
						/>
					) }
				</div>
				{ helpText && <p className="flinkform-field__help">{ helpText }</p> }
			</div>
		</>
	);
}
