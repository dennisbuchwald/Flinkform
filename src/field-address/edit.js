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
	const { label, required, helpText, fieldName, showCountry, showAddressLine2, countryDefault, fullWidth } = attributes;
	const blockProps = useBlockProps( {
		className: `flinkform-field flinkform-field--address${ fullWidth === false ? '' : ' flinkform-field--full-width' }`,
	} );

	// Same label-position handling as render.php — the preview has to show
	// what the frontend will do, floating labels included.
	const labelPosition = context?.[ 'flinkform/appearance' ]?.labelPosition ?? 'above';

	useEffect( () => {
		if ( ! fieldName ) {
			setAttributes( { fieldName: generateFieldName( 'address' ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	// Label / placeholder split matches render.php exactly.
	const subFields = [
		{ key: 'street', label: __( 'Street', 'flinkform' ), placeholder: __( 'Street + house number', 'flinkform' ), full: true, show: true },
		{ key: 'line2', label: __( 'Address line 2', 'flinkform' ), placeholder: __( 'Apartment, suite, floor etc.', 'flinkform' ), full: true, show: showAddressLine2 },
		{ key: 'zip', label: __( 'Postal code', 'flinkform' ), placeholder: __( 'Postal code', 'flinkform' ), full: false, show: true },
		{ key: 'city', label: __( 'City', 'flinkform' ), placeholder: __( 'City', 'flinkform' ), full: false, show: true },
		{ key: 'country', label: __( 'Country', 'flinkform' ), placeholder: __( 'Country', 'flinkform' ), full: true, show: showCountry },
	];

	const subPlaceholder = ( sf, subRequired ) => {
		if ( labelPosition === 'floating' ) {
			return '';
		}
		if ( labelPosition === 'placeholder' && subRequired ) {
			return `${ sf.placeholder }*`;
		}
		return sf.placeholder;
	};

	// block.json defaults never pass through i18n — translate the untouched
	// English default so the editor legend reads "Adresse" on a German site.
	const legendLabel = label === 'Address' ? __( 'Address', 'flinkform' ) : label;

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
					{ legendLabel }
					{ required && <span className="flinkform-field__required" aria-hidden="true"> *</span> }
				</legend>
				<div className="flinkform-field-address__grid">
					{ subFields.filter( ( sf ) => sf.show ).map( ( sf ) => {
						// Line 2 stays optional even on a required address.
						const subRequired = required && sf.key !== 'line2';
						return (
							<div
								key={ sf.key }
								className={ `flinkform-field flinkform-field--text${ sf.full ? ' flinkform-field-address__sub--full' : '' }` }
							>
								<label className="flinkform-field__label">
									{ sf.label }
									{ subRequired && <span className="flinkform-field__required" aria-hidden="true"> *</span> }
								</label>
								<input
									type="text"
									className="flinkform-field__input"
									placeholder={ subPlaceholder( sf, subRequired ) }
									disabled
									aria-disabled="true"
								/>
							</div>
						);
					} ) }
				</div>
				{ helpText && <p className="flinkform-field__help">{ helpText }</p> }
			</fieldset>
		</>
	);
}
