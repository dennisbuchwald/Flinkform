/**
 * Form Container — editor component.
 *
 * Generates a stable UUID for `formId` on first mount and never mutates it.
 * If a block is duplicated, both copies inherit the same UUID — Phase 1
 * accepts this; later phases may detect and re-key duplicates explicitly.
 */
import { useEffect, useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';

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
	const { formId, title, submitLabel, successMessage, notifications } = attributes;
	const blockProps = useBlockProps( { className: 'perform-form-editor' } );

	const adminConfig = notifications?.admin ?? {};

	useEffect( () => {
		if ( ! formId ) {
			setAttributes( { formId: generateUuid() } );
		}
		// Intentionally only runs once per mount — formId is immutable
		// after first assignment.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	// Walk the form's inner blocks to find the first email field. Powers
	// the Reply-To auto-fill below and will feed the submitter-confirmation
	// picker in Slice 3c. useSelect re-runs whenever the inner-block tree
	// changes (add/remove/rename), so the auto-fill effect always sees the
	// current state without us subscribing manually.
	const innerBlocks = useSelect(
		( select ) => select( 'core/block-editor' ).getBlocks( clientId ),
		[ clientId ]
	);

	const firstEmailFieldName = useMemo( () => {
		if ( ! Array.isArray( innerBlocks ) ) {
			return '';
		}
		const emailBlock = innerBlocks.find( ( b ) => b.name === 'perform/field-email' );
		const fieldName = emailBlock?.attributes?.fieldName;
		return typeof fieldName === 'string' ? fieldName : '';
	}, [ innerBlocks ] );

	// Patch a subset of admin notification config without losing siblings.
	// setAttributes is shallow — without the spread we'd wipe other keys
	// inside `notifications.admin` (and `notifications.submitter` once 3c
	// lands).
	const updateAdminConfig = ( patch ) => {
		setAttributes( {
			notifications: {
				...notifications,
				admin: {
					...adminConfig,
					...patch,
				},
			},
		} );
	};

	// Reply-To auto-fill: when an email field exists and the user has
	// never touched the Reply-To input, pre-fill it with a merge tag
	// pointing at that field. `replyTo === undefined` is the "never
	// touched" signal — once the user types into or clears the input we
	// switch to a string and this effect leaves it alone forever after.
	useEffect( () => {
		if ( adminConfig.replyTo !== undefined ) {
			return;
		}
		if ( ! firstEmailFieldName ) {
			return;
		}
		updateAdminConfig( { replyTo: `{field:${ firstEmailFieldName }}` } );
		// updateAdminConfig is intentionally not a dep — it'd be re-created
		// every render and trash the "fire once" semantics we want here.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ firstEmailFieldName, adminConfig.replyTo ] );

	const adminEnabled = adminConfig.enabled !== false;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form Settings', 'perform-forms' ) }>
					<TextControl
						label={ __( 'Form Title', 'perform-forms' ) }
						help={ __( 'Internal name shown in the Forms admin overview. Not visible to visitors.', 'perform-forms' ) }
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
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

				<PanelBody
					title={ __( 'Notifications', 'perform-forms' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __( 'Send admin notification', 'perform-forms' ) }
						help={ __( 'Email the site admin when a submission is received.', 'perform-forms' ) }
						checked={ adminEnabled }
						onChange={ ( value ) => updateAdminConfig( { enabled: value } ) }
						__nextHasNoMarginBottom
					/>
					{ adminEnabled && (
						<>
							<TextControl
								label={ __( 'To', 'perform-forms' ) }
								help={ __( 'Comma-separated. Leave empty to use the site admin email. Supports merge tags like {field:email}.', 'perform-forms' ) }
								value={ adminConfig.to ?? '' }
								placeholder={ __( 'Site admin email', 'perform-forms' ) }
								onChange={ ( value ) => updateAdminConfig( { to: value } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<TextControl
								label={ __( 'Subject', 'perform-forms' ) }
								help={ __( 'Supports merge tags. Leave empty for the default.', 'perform-forms' ) }
								value={ adminConfig.subject ?? '' }
								placeholder={ __( 'New submission: {form:title}', 'perform-forms' ) }
								onChange={ ( value ) => updateAdminConfig( { subject: value } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<TextareaControl
								label={ __( 'Body', 'perform-forms' ) }
								help={ __( 'Available tags: {form:title}, {site:name}, {site:url}, {submission:id}, {submission:date}, {field:<fieldName>}. Leave empty for an auto-generated list of all fields.', 'perform-forms' ) }
								value={ adminConfig.body ?? '' }
								placeholder={ __( 'Auto-generated field list', 'perform-forms' ) }
								onChange={ ( value ) => updateAdminConfig( { body: value } ) }
								rows={ 8 }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Reply-To', 'perform-forms' ) }
								help={ __( 'Often set to {field:<emailFieldName>} so replies go to the submitter. Leave empty to use the site default.', 'perform-forms' ) }
								value={ adminConfig.replyTo ?? '' }
								placeholder={ firstEmailFieldName ? `{field:${ firstEmailFieldName }}` : '' }
								onChange={ ( value ) => updateAdminConfig( { replyTo: value } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</>
					) }
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
