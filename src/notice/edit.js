/**
 * Notice — editor component.
 *
 * A non-submitting content block: a highlighted note placed between
 * fields. Its real value is the conditional-logic panel, which lets an
 * author surface guidance only when it applies ("Ab 30 km berechne ich
 * eine Anfahrtspauschale").
 *
 * The variant icons live in style.scss as CSS masks rather than as
 * markup, so the editor and the frontend cannot drift and there is only
 * one place to touch when a variant is added.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

import FullWidthPanel from '../shared/full-width-panel';
import ConditionalLogicPanel from '../shared/conditional-logic-panel';

export const NOTICE_VARIANTS = [ 'info', 'success', 'warning', 'important' ];

export default function Edit( { attributes, setAttributes, context, clientId } ) {
	const { message, variant, showIcon } = attributes;
	const safeVariant = NOTICE_VARIANTS.includes( variant ) ? variant : 'info';

	const blockProps = useBlockProps( {
		className: `flinkform-notice flinkform-notice--${ safeVariant }${ showIcon ? '' : ' flinkform-notice--no-icon' }`,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Notice', 'flinkform' ) }>
					<SelectControl
						label={ __( 'Type', 'flinkform' ) }
						help={ __( 'Sets the colour and icon. Pick by meaning, not by colour — that keeps notices consistent across your forms.', 'flinkform' ) }
						value={ safeVariant }
						options={ [
							{ label: __( 'Info', 'flinkform' ), value: 'info' },
							{ label: __( 'Success', 'flinkform' ), value: 'success' },
							{ label: __( 'Warning', 'flinkform' ), value: 'warning' },
							{ label: __( 'Important', 'flinkform' ), value: 'important' },
						] }
						onChange={ ( v ) => setAttributes( { variant: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<ToggleControl
						label={ __( 'Show icon', 'flinkform' ) }
						checked={ !! showIcon }
						onChange={ ( v ) => setAttributes( { showIcon: v } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<FullWidthPanel attributes={ attributes } setAttributes={ setAttributes } context={ context } />
				<ConditionalLogicPanel
					attributes={ attributes }
					setAttributes={ setAttributes }
					clientId={ clientId }
					toggleHelp={ __( 'Show this notice only when the rules below match — for example a delivery surcharge that applies from a certain distance.', 'flinkform' ) }
				/>
			</InspectorControls>

			<div { ...blockProps }>
				{ showIcon && <span className="flinkform-notice__icon" aria-hidden="true" /> }
				<RichText
					tagName="p"
					className="flinkform-notice__text"
					value={ message }
					onChange={ ( v ) => setAttributes( { message: v } ) }
					placeholder={ __( 'Write your note…', 'flinkform' ) }
					allowedFormats={ [ 'core/bold', 'core/italic', 'core/link' ] }
				/>
			</div>
		</>
	);
}
