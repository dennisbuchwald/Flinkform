import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl } from '@wordpress/components';

import { generateFieldName } from '../shared/field-name';
import FullWidthPanel from '../shared/full-width-panel';
import ConditionalLogicPanel from '../shared/conditional-logic-panel';

const EN_DEFAULT = 'I agree to the processing of my personal data for the purpose of contact in accordance with the {privacy_policy}.';
const OLD_EN_DEFAULT = 'I consent to the processing of my data as described in the privacy policy.';

export default function Edit( { attributes, setAttributes, context, clientId } ) {
	const { consentText, fieldName } = attributes;
	const blockProps = useBlockProps( { className: 'flinkform-field flinkform-field--consent' } );

	useEffect( () => {
		if ( ! fieldName ) {
			setAttributes( { fieldName: generateFieldName( 'consent' ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	// Translate the default for display in the editor. The stored
	// attribute stays English so render.php's i18n fallback works
	// across all locales. Only the editor UI shows the translated text.
	const isDefault = consentText === EN_DEFAULT || consentText === OLD_EN_DEFAULT;
	const displayText = isDefault
		? __( 'I agree to the processing of my personal data for the purpose of contact in accordance with the {privacy_policy}.', 'flinkform' )
		: consentText;

	// Build the preview label: replace {privacy_policy} with a visual hint.
	const previewParts = displayText.split( '{privacy_policy}' );
	const hasPlaceholder = previewParts.length > 1;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Consent Settings', 'flinkform' ) }>
					<TextareaControl
						label={ __( 'Consent text', 'flinkform' ) }
						help={ __( 'Use {privacy_policy} as placeholder — it will be replaced with a link to your privacy policy page (Settings → Privacy).', 'flinkform' ) }
						value={ displayText }
						onChange={ ( v ) => setAttributes( { consentText: v } ) }
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
						{ hasPlaceholder ? (
							<>
								{ previewParts[ 0 ] }
								<em style={ { textDecoration: 'underline' } }>{ __( 'privacy policy', 'flinkform' ) }</em>
								{ previewParts[ 1 ] }
							</>
						) : displayText }
						<span className="flinkform-field__required" aria-hidden="true"> *</span>
					</span>
				</label>
			</div>
		</>
	);
}
