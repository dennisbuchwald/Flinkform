import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';

import { generateFieldName } from '../shared/field-name';
import FullWidthPanel from '../shared/full-width-panel';
import ConditionalLogicPanel from '../shared/conditional-logic-panel';

export default function Edit( { attributes, setAttributes, context, clientId } ) {
	const { consentText, linkPrivacyPolicy, fieldName } = attributes;
	const blockProps = useBlockProps( { className: 'flinkform-field flinkform-field--consent' } );

	useEffect( () => {
		if ( ! fieldName ) {
			setAttributes( { fieldName: generateFieldName( 'consent' ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Consent Settings', 'flinkform' ) }>
					<TextareaControl
						label={ __( 'Consent text', 'flinkform' ) }
						help={ __( 'Shown next to the checkbox. The visitor must tick it to submit.', 'flinkform' ) }
						value={ consentText }
						onChange={ ( v ) => setAttributes( { consentText: v } ) }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( 'Append a link to the privacy policy', 'flinkform' ) }
						help={ __( 'Links to the page set under Settings → Privacy.', 'flinkform' ) }
						checked={ !! linkPrivacyPolicy }
						onChange={ ( v ) => setAttributes( { linkPrivacyPolicy: v } ) }
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( 'Field Name', 'flinkform' ) }
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
				<label className="flinkform-field__option" style={ { display: 'flex', alignItems: 'flex-start', gap: '8px' } }>
					<input type="checkbox" disabled aria-disabled="true" />
					<span>
						{ consentText }
						<span className="flinkform-field__required" aria-hidden="true"> *</span>
						{ linkPrivacyPolicy && (
							<>
								{ ' ' }
								<em>{ __( '(privacy policy link)', 'flinkform' ) }</em>
							</>
						) }
					</span>
				</label>
			</div>
		</>
	);
}
