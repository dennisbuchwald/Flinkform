/**
 * Form Container — editor component.
 *
 * Generates a stable UUID for `formId` on first mount and never mutates it.
 * If a block is duplicated, both copies inherit the same UUID — Phase 1
 * accepts this; later phases may detect and re-key duplicates explicitly.
 */
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl } from '@wordpress/components';

const ALLOWED_BLOCKS = [
	'perform/section-heading',
	'perform/field-text',
	'perform/field-email',
	'perform/field-textarea',
	'perform/field-number',
	'perform/field-select',
	'perform/field-radio',
	'perform/field-checkbox',
	'perform/field-toggle',
	'perform/field-hidden',
];

const TEMPLATE = [
	[ 'perform/field-text', { label: __( 'Name', 'perform-forms' ), required: true } ],
	[ 'perform/field-email', { label: __( 'Email', 'perform-forms' ), required: true } ],
	[ 'perform/field-textarea', { label: __( 'Message', 'perform-forms' ), required: true } ],
];

function generateUuid() {
	if ( typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function' ) {
		return crypto.randomUUID();
	}
	// RFC 4122-ish fallback for ancient environments. Crypto.randomUUID has
	// been available in every browser shipped since 2022, so this is paranoia.
	return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, ( c ) => {
		const r = ( Math.random() * 16 ) | 0;
		const v = c === 'x' ? r : ( r & 0x3 ) | 0x8;
		return v.toString( 16 );
	} );
}

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { formId, submitLabel, successMessage } = attributes;
	const blockProps = useBlockProps( { className: 'perform-form-editor' } );

	useEffect( () => {
		if ( ! formId ) {
			setAttributes( { formId: generateUuid() } );
		}
		// Intentionally only runs once per mount — formId is immutable
		// after first assignment.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form Settings', 'perform-forms' ) }>
					<TextControl
						label={ __( 'Submit Button Label', 'perform-forms' ) }
						value={ submitLabel }
						onChange={ ( value ) => setAttributes( { submitLabel: value } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextareaControl
						label={ __( 'Success Message', 'perform-forms' ) }
						help={ __( 'Shown after a successful submission.', 'perform-forms' ) }
						value={ successMessage }
						onChange={ ( value ) => setAttributes( { successMessage: value } ) }
						__nextHasNoMarginBottom
					/>
					<p style={ { fontSize: '12px', opacity: 0.7, marginTop: '12px' } }>
						{ __( 'Form ID:', 'perform-forms' ) } <code>{ formId || '…' }</code>
					</p>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<InnerBlocks
					allowedBlocks={ ALLOWED_BLOCKS }
					template={ TEMPLATE }
					templateLock={ false }
				/>
			</div>
		</>
	);
}
