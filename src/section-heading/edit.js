import { __ } from '@wordpress/i18n';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextareaControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { title, description } = attributes;
	const blockProps = useBlockProps( { className: 'perform-section-heading' } );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Section', 'perform-forms' ) }>
					<TextareaControl
						label={ __( 'Description', 'perform-forms' ) }
						value={ description }
						onChange={ ( v ) => setAttributes( { description: v } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<RichText
					tagName="h2"
					className="perform-section-heading__title"
					value={ title }
					onChange={ ( v ) => setAttributes( { title: v } ) }
					placeholder={ __( 'Section title…', 'perform-forms' ) }
				/>
				{ description && (
					<p className="perform-section-heading__description">{ description }</p>
				) }
			</div>
		</>
	);
}
