import { __ } from '@wordpress/i18n';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextareaControl } from '@wordpress/components';
import FullWidthPanel from '../shared/full-width-panel';
import ConditionalLogicPanel from '../shared/conditional-logic-panel';

export default function Edit( { attributes, setAttributes, context, clientId } ) {
	const { title, description, headingLevel } = attributes;
	const level = headingLevel || 2;
	const blockProps = useBlockProps( { className: 'flinkform-section-heading' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Section', 'flinkform' ) }>
					<SelectControl
						label={ __( 'Heading level', 'flinkform' ) }
						help={ __( 'Pick the level that keeps your page’s heading order correct (H1 belongs to the page).', 'flinkform' ) }
						value={ String( level ) }
						options={ [
							{ label: 'H2', value: '2' },
							{ label: 'H3', value: '3' },
							{ label: 'H4', value: '4' },
							{ label: 'H5', value: '5' },
							{ label: 'H6', value: '6' },
						] }
						onChange={ ( v ) => setAttributes( { headingLevel: parseInt( v, 10 ) } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextareaControl
						label={ __( 'Description', 'flinkform' ) }
						value={ description }
						onChange={ ( v ) => setAttributes( { description: v } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<FullWidthPanel attributes={ attributes } setAttributes={ setAttributes } context={ context } />
				<ConditionalLogicPanel attributes={ attributes } setAttributes={ setAttributes } clientId={ clientId } />
			</InspectorControls>
			<div { ...blockProps }>
				<RichText
					tagName={ `h${ level }` }
					className="flinkform-section-heading__title"
					value={ title }
					onChange={ ( v ) => setAttributes( { title: v } ) }
					placeholder={ __( 'Section title…', 'flinkform' ) }
				/>
				{ description && (
					<p className="flinkform-section-heading__description">{ description }</p>
				) }
			</div>
		</>
	);
}
