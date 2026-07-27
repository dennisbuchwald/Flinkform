/**
 * Field — Address — editor component.
 *
 * Composite field: street (+ optional line 2), postal code + city side by
 * side, optional country. Each sub-field renders as a full
 * .flinkform-field--text wrapper so it inherits all form styles.
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

	const subFields = [
		{ key: 'street', label: __( 'Street + house number', 'flinkform' ), full: true, show: true },
		{ key: 'line2', label: __( 'Address line 2', 'flinkform' ), full: true, show: showAddressLine2 },
		{ key: 'zip', label: __( 'Postal code', 'flinkform' ), full: false, show: true },
		{ key: 'city', label: __( 'City', 'flinkform' ), full: false, show: true },
		{ key: 'country', label: __( 'Country', 'flinkform' ), full: true, show: showCountry },
	];

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

			<fieldset { ...blockProps }>
				<legend className="flinkform-field-address__legend">
					{ label }
					{ required && <span className="flinkform-field__required" aria-hidden="true"> *</span> }
				</legend>
				<div className="flinkform-field-address__grid">
					{ subFields.filter( ( sf ) => sf.show ).map( ( sf ) => (
						<div
							key={ sf.key }
							className={ `flinkform-field flinkform-field--text${ sf.full ? ' flinkform-field-address__sub--full' : '' }` }
						>
							<label className="flinkform-field__label">{ sf.label }</label>
							<input
								type="text"
								className="flinkform-field__input"
								placeholder={ sf.label }
								disabled
								aria-disabled="true"
							/>
						</div>
					) ) }
				</div>
				{ helpText && <p className="flinkform-field__help">{ helpText }</p> }
			</fieldset>
		</>
	);
}
