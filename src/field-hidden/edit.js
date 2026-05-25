import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl, TextControl } from '@wordpress/components';

import { generateFieldName } from '../shared/field-name';

const SOURCE_OPTIONS = [
	{ label: __( 'Static value', 'perform-forms' ), value: 'static' },
	{ label: __( 'Current page URL', 'perform-forms' ), value: 'current_url' },
	{ label: __( 'Current post ID', 'perform-forms' ), value: 'current_post_id' },
	{ label: __( 'Current user ID (0 if logged out)', 'perform-forms' ), value: 'current_user_id' },
	{ label: __( 'Current date (Y-m-d)', 'perform-forms' ), value: 'current_date' },
	{ label: __( 'Current date + time (ISO)', 'perform-forms' ), value: 'current_datetime' },
];
import FullWidthPanel from '../shared/full-width-panel';
import ConditionalLogicPanel from '../shared/conditional-logic-panel';

export default function Edit( { attributes, setAttributes, context, clientId } ) {
	const { label, fieldName, valueSource, staticValue } = attributes;
	const blockProps = useBlockProps( { className: 'perform-field perform-field--hidden' } );

	useEffect( () => {
		if ( ! fieldName ) {
			setAttributes( { fieldName: generateFieldName( 'hidden' ) } );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Hidden Field', 'perform-forms' ) }>
					<TextControl
						label={ __( 'Editor label', 'perform-forms' ) }
						help={ __( 'Only shown in the editor for orientation. Not sent to visitors.', 'perform-forms' ) }
						value={ label }
						onChange={ ( v ) => setAttributes( { label: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<SelectControl
						label={ __( 'Value source', 'perform-forms' ) }
						value={ valueSource || 'static' }
						options={ SOURCE_OPTIONS }
						onChange={ ( v ) => setAttributes( { valueSource: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					{ valueSource === 'static' && (
						<TextControl
							label={ __( 'Static value', 'perform-forms' ) }
							value={ staticValue || '' }
							onChange={ ( v ) => setAttributes( { staticValue: v } ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					) }
					{ valueSource !== 'static' && (
						<Notice status="info" isDismissible={ false }>
							{ __( 'The value is computed on the server when the form is rendered — POST values from visitors are ignored.', 'perform-forms' ) }
						</Notice>
					) }
					<TextControl
						label={ __( 'Field Name', 'perform-forms' ) }
						help={ __( 'Key used in submission data.', 'perform-forms' ) }
						value={ fieldName }
						onChange={ ( v ) => setAttributes( { fieldName: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</PanelBody>
				<FullWidthPanel attributes={ attributes } setAttributes={ setAttributes } context={ context } />
				<ConditionalLogicPanel attributes={ attributes } setAttributes={ setAttributes } clientId={ clientId } />
			</InspectorControls>

			<div { ...blockProps } style={ { padding: '8px 12px', background: '#f0f0f1', borderRadius: '4px', fontSize: '12px' } }>
				<strong>{ __( 'Hidden:', 'perform-forms' ) }</strong> { label } <code>({ valueSource || 'static' })</code>
			</div>
		</>
	);
}
