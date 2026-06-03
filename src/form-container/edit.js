/**
 * Form Container — editor component.
 *
 * Generates a stable UUID for `formId` on first mount and never mutates it.
 * If a block is duplicated, both copies inherit the same UUID — Phase 1
 * accepts this; later phases may detect and re-key duplicates explicitly.
 */
import { Fragment, useEffect, useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import {
	ColorPalette,
	InnerBlocks,
	InspectorControls,
	URLInput,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	BaseControl,
	Notice,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import ConditionalLogicPanel from '../shared/conditional-logic-panel';

const ALLOWED_BLOCKS = [
	'perform/section-heading',
	'perform/page-break',
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
	const { formId, title, submitLabel, successMessage, notifications, appearance, customCSS, spamProtection, afterSubmit, retentionDays } = attributes;

	const afterSubmitConfig = afterSubmit ?? {};
	const afterSubmitBehaviour = afterSubmitConfig.behaviour === 'redirect' ? 'redirect' : 'message';
	const updateAfterSubmit = ( patch ) =>
		setAttributes( { afterSubmit: { ...afterSubmitConfig, ...patch } } );

	const adminConfig = notifications?.admin ?? {};
	const submitterConfig = notifications?.submitter ?? {};
	const appearanceConfig = appearance ?? {};
	const primaryColor = appearanceConfig.primaryColor;
	const buttonColor = appearanceConfig.buttonColor;
	const buttonTextColor = appearanceConfig.buttonTextColor;
	const buttonBorderColor = appearanceConfig.buttonBorderColor;
	const submitButtonStyle = appearanceConfig.submitButtonStyle ?? 'fill';
	const fieldStyle = appearanceConfig.fieldStyle ?? 'bordered';
	const fieldSpacing = appearanceConfig.fieldSpacing ?? 'normal';
	const labelPosition = appearanceConfig.labelPosition ?? 'above';
	const columns = appearanceConfig.columns === 2 ? 2 : 1;
	const progressIndicator = appearanceConfig.progressIndicator ?? 'bar';
	const showStepLabels = appearanceConfig.showStepLabels === true;
	const borderRadius = typeof appearanceConfig.borderRadius === 'number'
		? appearanceConfig.borderRadius
		: undefined;

	// Editor preview: emit the same --perform-* overrides + modifier
	// classes the frontend gets so the in-editor block visually mirrors
	// the saved settings. Without this the inspector previewing a custom
	// primary colour or spacing wouldn't show it on the canvas.
	const editorStyle = {};
	if ( typeof primaryColor === 'string' && primaryColor !== '' ) {
		editorStyle[ '--perform-color-primary' ] = primaryColor;
	}
	if ( typeof borderRadius === 'number' ) {
		editorStyle[ '--perform-border-radius' ] = `${ borderRadius }px`;
	}
	if ( typeof buttonColor === 'string' && buttonColor !== '' ) {
		editorStyle[ '--perform-button-bg' ] = buttonColor;
	}
	if ( typeof buttonTextColor === 'string' && buttonTextColor !== '' ) {
		editorStyle[ '--perform-button-color' ] = buttonTextColor;
	}
	if ( typeof buttonBorderColor === 'string' && buttonBorderColor !== '' ) {
		editorStyle[ '--perform-button-border-color' ] = buttonBorderColor;
	}

	const editorClassName = [
		'perform-form-editor',
		'perform-form',
		`perform-form--button-${ submitButtonStyle }`,
		`perform-form--field-style-${ fieldStyle }`,
		`perform-form--spacing-${ fieldSpacing }`,
		`perform-form--labels-${ labelPosition }`,
		`perform-form--columns-${ columns }`,
	].join( ' ' );

	const blockProps = useBlockProps( {
		className: editorClassName,
		style: editorStyle,
		// Mirror the frontend's data-perform-id so Custom CSS rules
		// scoped to [data-perform-id="…"] take effect in the editor too.
		'data-perform-id': formId || undefined,
	} );

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

	// All email fields in this form, in document order. Powers the
	// submitter-confirmation field picker and feeds the firstEmailFieldName
	// used by the admin Reply-To auto-fill.
	const emailFieldOptions = useMemo( () => {
		if ( ! Array.isArray( innerBlocks ) ) {
			return [];
		}
		return innerBlocks
			.filter( ( b ) => b.name === 'perform/field-email' )
			.map( ( b ) => {
				const fieldName = b.attributes?.fieldName;
				const fieldLabel = b.attributes?.label;
				const value = typeof fieldName === 'string' ? fieldName : '';
				const label = typeof fieldLabel === 'string' && fieldLabel
					? `${ fieldLabel } (${ value })`
					: value;
				return { value, label };
			} )
			.filter( ( opt ) => opt.value !== '' );
	}, [ innerBlocks ] );

	const firstEmailFieldName = emailFieldOptions[ 0 ]?.value ?? '';

	// Step count from page-break markers — feeds the editor's preview
	// rendering of the progress indicator. Matches the same `count + 1`
	// derivation render.php uses on the server. A form with no
	// page-breaks is single-step and the indicator stays hidden, mirroring
	// the server-side `$is_multi_step` gate.
	const stepCount = useMemo( () => {
		if ( ! Array.isArray( innerBlocks ) ) {
			return 1;
		}
		return innerBlocks.filter( ( b ) => b.name === 'perform/page-break' ).length + 1;
	}, [ innerBlocks ] );
	const isMultiStep = stepCount > 1;

	// Step labels mirror the same data render.php derives — first step
	// has no opening page-break so its label is empty, the labels of
	// subsequent steps come from the matching page-break's `label`
	// attribute. Used to preview the Step Labels feature in the
	// editor exactly as it'll appear on the frontend.
	const stepLabels = useMemo( () => {
		const labels = [ '' ];
		if ( Array.isArray( innerBlocks ) ) {
			innerBlocks.forEach( ( b ) => {
				if ( b.name === 'perform/page-break' ) {
					const raw = b.attributes?.label;
					labels.push( typeof raw === 'string' ? raw : '' );
				}
			} );
		}
		return labels;
	}, [ innerBlocks ] );
	const hasAnyStepLabel = stepLabels.some( ( label ) => label !== '' );

	// Flat list of all submitting field blocks in this form — feeds
	// the webhook condition / field-mapping selectors so the author
	// can pick from real field names rather than typing UUIDs.
	// Excludes the page-break + section-heading blocks (no `fieldName`
	// attribute, nothing to map).
	const formFields = useMemo( () => {
		if ( ! Array.isArray( innerBlocks ) ) {
			return [];
		}
		return innerBlocks
			.filter( ( b ) => typeof b.attributes?.fieldName === 'string' && b.attributes.fieldName !== '' )
			.map( ( b ) => ( {
				name: String( b.attributes.fieldName ),
				label: typeof b.attributes?.label === 'string' ? b.attributes.label : '',
			} ) );
	}, [ innerBlocks ] );

	// Patch a subset of admin notification config without losing siblings.
	// setAttributes is shallow — without the spread we'd wipe other keys
	// inside `notifications.admin` and `notifications.submitter`.
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

	const updateSubmitterConfig = ( patch ) => {
		setAttributes( {
			notifications: {
				...notifications,
				submitter: {
					...submitterConfig,
					...patch,
				},
			},
		} );
	};

	const updateAppearance = ( patch ) => {
		setAttributes( {
			appearance: {
				...appearanceConfig,
				...patch,
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
	const submitterEnabled = submitterConfig.enabled === true;
	const hasEmailFields = emailFieldOptions.length > 0;

	// Submitter email-field auto-fill: the first time confirmations are
	// turned on (and at least one email field exists), pre-select the
	// first email field. The user can switch via the SelectControl below.
	// Once `emailField` is a string the effect leaves it alone — even an
	// empty string counts as touched, mirroring the admin Reply-To rule.
	useEffect( () => {
		if ( ! submitterEnabled ) {
			return;
		}
		if ( submitterConfig.emailField !== undefined ) {
			return;
		}
		if ( ! firstEmailFieldName ) {
			return;
		}
		updateSubmitterConfig( { emailField: firstEmailFieldName } );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ submitterEnabled, submitterConfig.emailField, firstEmailFieldName ] );

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
					<p style={ { fontSize: '12px', opacity: 0.7, marginTop: '12px' } }>
						{ __( 'Form ID:', 'perform-forms' ) } <code>{ formId || '…' }</code>
					</p>
				</PanelBody>

				<PanelBody
					title={ __( 'After Submit', 'perform-forms' ) }
					initialOpen={ false }
				>
					<ToggleGroupControl
						label={ __( 'On successful submission', 'perform-forms' ) }
						value={ afterSubmitBehaviour }
						onChange={ ( value ) => updateAfterSubmit( { behaviour: value } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="message" label={ __( 'Show message', 'perform-forms' ) } />
						<ToggleGroupControlOption value="redirect" label={ __( 'Redirect to URL', 'perform-forms' ) } />
					</ToggleGroupControl>

					{ afterSubmitBehaviour === 'message' && (
						<TextareaControl
							label={ __( 'Success Message', 'perform-forms' ) }
							help={ __( 'Shown in place of the form after a successful submission.', 'perform-forms' ) }
							value={ successMessage }
							onChange={ ( value ) => setAttributes( { successMessage: value } ) }
							__nextHasNoMarginBottom
						/>
					) }

					{ afterSubmitBehaviour === 'redirect' && (
						<>
							<BaseControl
								label={ __( 'Redirect URL', 'perform-forms' ) }
								help={ __( 'Same-origin only. Enter a path (/danke) or a full URL on this site. External URLs are silently rejected by the safe-redirect filter — operators who need cross-domain thank-you pages get a site-wide opt-in toggle in a later release.', 'perform-forms' ) }
								id="perform-after-submit-url"
								__nextHasNoMarginBottom
							>
								<URLInput
									id="perform-after-submit-url"
									value={ afterSubmitConfig.redirectUrl ?? '' }
									onChange={ ( value ) => updateAfterSubmit( { redirectUrl: value } ) }
									placeholder={ __( 'https://example.com/danke', 'perform-forms' ) }
									className="perform-after-submit__url"
								/>
							</BaseControl>

							<ToggleControl
								label={ __( 'Append submission ID', 'perform-forms' ) }
								help={ __( 'Adds ?perform_submission_id=N to the redirect URL — useful for analytics events on the thank-you page (gtag, GA4, Plausible, Matomo). Default off because submission IDs are PII-adjacent and end up in browser history.', 'perform-forms' ) }
								checked={ !! afterSubmitConfig.appendSubmissionId }
								onChange={ ( value ) => updateAfterSubmit( { appendSubmissionId: value } ) }
								__nextHasNoMarginBottom
							/>

							<ToggleControl
								label={ __( 'Append form ID', 'perform-forms' ) }
								help={ __( 'Adds ?perform_form_id=UUID — lets a shared thank-you page differentiate conversions by source form.', 'perform-forms' ) }
								checked={ !! afterSubmitConfig.appendFormId }
								onChange={ ( value ) => updateAfterSubmit( { appendFormId: value } ) }
								__nextHasNoMarginBottom
							/>
						</>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Style', 'perform-forms' ) }
					initialOpen={ false }
				>
					<BaseControl
						label={ __( 'Primary color', 'perform-forms' ) }
						help={ __( 'Used for submit button, focus rings, and accent details. Leave unset to inherit from your theme.', 'perform-forms' ) }
						id="perform-style-primary-color"
						__nextHasNoMarginBottom
					>
						<ColorPalette
							value={ primaryColor }
							onChange={ ( value ) => updateAppearance( { primaryColor: value || undefined } ) }
							clearable
							enableAlpha={ false }
						/>
					</BaseControl>
					<ToggleGroupControl
						label={ __( 'Field style', 'perform-forms' ) }
						help={ __( 'Bordered: full outline. Soft: light gray border. Underline: bottom border only. Minimal: no border, subtle background.', 'perform-forms' ) }
						value={ fieldStyle }
						onChange={ ( value ) => updateAppearance( { fieldStyle: value } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="bordered" label={ __( 'Bordered', 'perform-forms' ) } />
						<ToggleGroupControlOption value="soft" label={ __( 'Soft', 'perform-forms' ) } />
						<ToggleGroupControlOption value="underline" label={ __( 'Underline', 'perform-forms' ) } />
						<ToggleGroupControlOption value="minimal" label={ __( 'Minimal', 'perform-forms' ) } />
					</ToggleGroupControl>
					<RangeControl
						label={ __( 'Border radius', 'perform-forms' ) }
						help={ __( 'Applies to fields and the submit button. Leave at the default to inherit from your theme.', 'perform-forms' ) }
						value={ borderRadius }
						onChange={ ( value ) => updateAppearance( {
							borderRadius: typeof value === 'number' ? value : undefined,
						} ) }
						min={ 0 }
						max={ 32 }
						step={ 1 }
						allowReset
						resetFallbackValue={ undefined }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<ToggleGroupControl
						label={ __( 'Field spacing', 'perform-forms' ) }
						value={ fieldSpacing }
						onChange={ ( value ) => updateAppearance( { fieldSpacing: value } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="compact" label={ __( 'Compact', 'perform-forms' ) } />
						<ToggleGroupControlOption value="normal" label={ __( 'Normal', 'perform-forms' ) } />
						<ToggleGroupControlOption value="relaxed" label={ __( 'Relaxed', 'perform-forms' ) } />
					</ToggleGroupControl>
					<ToggleGroupControl
						label={ __( 'Label position', 'perform-forms' ) }
						help={
							labelPosition === 'floating'
								? __( 'Material-style notched label: rests inside the input, then slides onto the top border on focus, cutting a notch through it. Applies to text-style fields.', 'perform-forms' )
								: labelPosition === 'placeholder'
									? __( 'Labels are visually hidden (still accessible to screen readers). The label text is used as placeholder inside the field. Applies to text-style fields only.', 'perform-forms' )
									: __( 'Beside, Floating and Hidden apply to text-style fields only.', 'perform-forms' )
						}
						value={ labelPosition }
						onChange={ ( value ) => updateAppearance( { labelPosition: value } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="above" label={ __( 'Above', 'perform-forms' ) } />
						<ToggleGroupControlOption value="beside" label={ __( 'Beside', 'perform-forms' ) } />
						<ToggleGroupControlOption value="floating" label={ __( 'Floating', 'perform-forms' ) } />
						<ToggleGroupControlOption value="placeholder" label={ __( 'Hidden', 'perform-forms' ) } />
					</ToggleGroupControl>
					<ToggleGroupControl
						label={ __( 'Columns', 'perform-forms' ) }
						help={ __( 'Two-column layout collapses to a single column on mobile. Individual fields can be set to span both columns via their own inspector.', 'perform-forms' ) }
						value={ columns }
						onChange={ ( value ) => updateAppearance( { columns: value === 2 ? 2 : 1 } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value={ 1 } label={ __( '1 column', 'perform-forms' ) } />
						<ToggleGroupControlOption value={ 2 } label={ __( '2 columns', 'perform-forms' ) } />
					</ToggleGroupControl>
					<ToggleGroupControl
						label={ __( 'Submit button style', 'perform-forms' ) }
						value={ submitButtonStyle }
						onChange={ ( value ) => updateAppearance( { submitButtonStyle: value } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="fill" label={ __( 'Fill', 'perform-forms' ) } />
						<ToggleGroupControlOption value="outline" label={ __( 'Outline', 'perform-forms' ) } />
						<ToggleGroupControlOption value="ghost" label={ __( 'Ghost', 'perform-forms' ) } />
					</ToggleGroupControl>
					<BaseControl
						label={ __( 'Button background', 'perform-forms' ) }
						help={ __( 'Leave unset to use the primary colour.', 'perform-forms' ) }
						id="perform-style-button-color"
						__nextHasNoMarginBottom
					>
						<ColorPalette
							value={ buttonColor }
							onChange={ ( value ) => updateAppearance( { buttonColor: value || undefined } ) }
							clearable
							enableAlpha={ false }
						/>
					</BaseControl>
					<BaseControl
						label={ __( 'Button text colour', 'perform-forms' ) }
						help={ __( 'Leave unset for automatic contrast.', 'perform-forms' ) }
						id="perform-style-button-text-color"
						__nextHasNoMarginBottom
					>
						<ColorPalette
							value={ buttonTextColor }
							onChange={ ( value ) => updateAppearance( { buttonTextColor: value || undefined } ) }
							clearable
							enableAlpha={ false }
						/>
					</BaseControl>
					<BaseControl
						label={ __( 'Button border colour', 'perform-forms' ) }
						help={ __( 'Only visible when button style is "Outline". Leave unset to match the background colour.', 'perform-forms' ) }
						id="perform-style-button-border-color"
						__nextHasNoMarginBottom
					>
						<ColorPalette
							value={ buttonBorderColor }
							onChange={ ( value ) => updateAppearance( { buttonBorderColor: value || undefined } ) }
							clearable
							enableAlpha={ false }
						/>
					</BaseControl>
					<ToggleGroupControl
						label={ __( 'Progress indicator', 'perform-forms' ) }
						help={ __( 'Shown on multi-step forms only. Bar fills as the user advances; Dots marks each step; Numbers reads "Step X of Y".', 'perform-forms' ) }
						value={ progressIndicator }
						onChange={ ( value ) => updateAppearance( { progressIndicator: value } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="bar" label={ __( 'Bar', 'perform-forms' ) } />
						<ToggleGroupControlOption value="dots" label={ __( 'Dots', 'perform-forms' ) } />
						<ToggleGroupControlOption value="numbers" label={ __( 'Numbers', 'perform-forms' ) } />
						<ToggleGroupControlOption value="none" label={ __( 'None', 'perform-forms' ) } />
					</ToggleGroupControl>
					{ progressIndicator !== 'none' && (
						<ToggleControl
							label={ __( 'Show step labels', 'perform-forms' ) }
							help={ __( 'Display the current step’s label (from the Page Break block) beneath the indicator. Only shows when at least one Page Break has a label.', 'perform-forms' ) }
							checked={ showStepLabels }
							onChange={ ( value ) => updateAppearance( { showStepLabels: value } ) }
							__nextHasNoMarginBottom
						/>
					) }
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

					<hr style={ { margin: '16px 0', opacity: 0.25 } } />

					<ToggleControl
						label={ __( 'Send confirmation to submitter', 'perform-forms' ) }
						help={ __( 'Email a copy of the submission back to the person who filled out the form.', 'perform-forms' ) }
						checked={ submitterEnabled }
						onChange={ ( value ) => updateSubmitterConfig( { enabled: value } ) }
						__nextHasNoMarginBottom
					/>
					{ submitterEnabled && ! hasEmailFields && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Add an email field to this form to enable submitter confirmations.', 'perform-forms' ) }
						</Notice>
					) }
					{ submitterEnabled && hasEmailFields && (
						<>
							<SelectControl
								label={ __( 'Email field', 'perform-forms' ) }
								help={ __( 'Which field contains the submitter’s email address.', 'perform-forms' ) }
								value={ submitterConfig.emailField ?? '' }
								options={ [
									{ value: '', label: __( '— Select a field —', 'perform-forms' ) },
									...emailFieldOptions,
								] }
								onChange={ ( value ) => updateSubmitterConfig( { emailField: value } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<TextControl
								label={ __( 'Subject', 'perform-forms' ) }
								help={ __( 'Supports merge tags. Leave empty for the default.', 'perform-forms' ) }
								value={ submitterConfig.subject ?? '' }
								placeholder={ __( 'We received your submission', 'perform-forms' ) }
								onChange={ ( value ) => updateSubmitterConfig( { subject: value } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<TextareaControl
								label={ __( 'Body', 'perform-forms' ) }
								help={ __( 'Available tags: {form:title}, {site:name}, {site:url}, {submission:id}, {submission:date}, {field:<fieldName>}. Leave empty for an auto-generated thank-you with the submitted values.', 'perform-forms' ) }
								value={ submitterConfig.body ?? '' }
								placeholder={ __( 'Auto-generated thank-you with submitted values', 'perform-forms' ) }
								onChange={ ( value ) => updateSubmitterConfig( { body: value } ) }
								rows={ 8 }
								__nextHasNoMarginBottom
							/>
						</>
					) }
				</PanelBody>

				<ConditionalLogicPanel
					attributes={ attributes }
					setAttributes={ setAttributes }
					clientId={ clientId }
					attributeName="submitCondition"
					fieldSource="inner"
					title={ __( 'Submit Condition', 'perform-forms' ) }
					toggleLabel={ __( 'Gate the submit button', 'perform-forms' ) }
					toggleHelp={ __( 'Disable the submit button until the rules below match. Useful for "I agree to terms" checkboxes.', 'perform-forms' ) }
				/>

				{ /*
				 * Pro inspector-panel extension point. PerForm Pro injects its
				 * own form-container inspector panels here via:
				 *   addFilter( 'perform.formContainer.inspectorPanels', … )
				 * The free core passes the full editing context; the default is
				 * an empty list, so with no add-on nothing extra renders.
				 * Contract: see includes/Bridge/README.md (frozen once Pro ships).
				 */ }
				{ applyFilters(
					'perform.formContainer.inspectorPanels',
					[],
					{ attributes, setAttributes, clientId, formId, formFields }
				).map( ( panel, index ) => (
					<Fragment key={ `perform-pro-panel-${ index }` }>
						{ panel }
					</Fragment>
				) ) }

				<PanelBody
					title={ __( 'Spam Protection', 'perform-forms' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Strategy', 'perform-forms' ) }
						value={ spamProtection ?? 'auto' }
						options={ [
							{
								label: __( 'Auto — built-in challenge (recommended)', 'perform-forms' ),
								value: 'auto',
							},
							{
								label: __( 'Built-in challenge (force on)', 'perform-forms' ),
								value: 'builtin',
							},
							{
								label: __( 'None — honeypot + time-check only', 'perform-forms' ),
								value: 'none',
							},
						] }
						help={ __(
							'PerForm’s built-in challenge runs a tiny proof-of-work in the visitor’s browser (transparent, ~50–500 ms) plus a math fallback for visitors without JavaScript. No external services, no cookies — 100% DSGVO-compliant.',
							'perform-forms'
						) }
						onChange={ ( value ) => setAttributes( { spamProtection: value } ) }
						__nextHasNoMarginBottom
					/>
					{ spamProtection === 'none' && (
						<Notice status="warning" isDismissible={ false } className="perform-spam-notice">
							{ __(
								'Honeypot + time-check from Phase 1 still apply, but the active challenge is off. Use only for trusted-context forms (logged-in users, internal tools).',
								'perform-forms'
							) }
						</Notice>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Data Retention', 'perform-forms' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __( 'Auto-delete submissions after (days)', 'perform-forms' ) }
						help={ __(
							'GDPR storage limitation: automatically delete this form’s submissions older than the chosen number of days. 0 = keep forever. Purges run daily and also remove any linked PerForm Pro webhook delivery records.',
							'perform-forms'
						) }
						value={ retentionDays ?? 0 }
						onChange={ ( value ) => setAttributes( { retentionDays: value ?? 0 } ) }
						min={ 0 }
						max={ 365 }
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Custom CSS', 'perform-forms' ) }
					initialOpen={ false }
				>
					<TextareaControl
						label={ __( 'CSS rules', 'perform-forms' ) }
						help={ __( 'Scope rules to this form by prefixing selectors with [data-perform-id="<id>"]. Otherwise the rules apply to every PerForm on the page.', 'perform-forms' ) }
						value={ customCSS ?? '' }
						onChange={ ( value ) => setAttributes( { customCSS: value } ) }
						rows={ 10 }
						className="perform-custom-css-input"
						__nextHasNoMarginBottom
					/>
					{ formId && (
						<p style={ { fontSize: '12px', opacity: 0.7, marginTop: '8px' } }>
							{ __( 'Form ID for scoping:', 'perform-forms' ) }
							<br />
							<code style={ { userSelect: 'all', wordBreak: 'break-all' } }>
								{ `[data-perform-id="${ formId }"]` }
							</code>
						</p>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ customCSS && (
					/* Editor live-preview: render the same <style> tag the
					 * frontend gets. dangerouslySetInnerHTML is fine here — the
					 * only writer is the editing user (capability edit_posts),
					 * and the frontend output applies an extra sanitisation
					 * pass before echoing. */
					<style dangerouslySetInnerHTML={ { __html: customCSS } } />
				) }
				{ isMultiStep && progressIndicator !== 'none' && (
					/* Editor preview of the progress indicator. Mirrors
					 * the server-rendered markup with currentStep = 0
					 * (Step 1 of N) so authors can see the chosen
					 * variant without leaving the editor. Class names
					 * are identical to render.php's output so the same
					 * SCSS styles them in both places. */
					<div
						className={ `perform-form__progress perform-form__progress--${ progressIndicator }` }
						role="progressbar"
						aria-valuemin={ 1 }
						aria-valuemax={ stepCount }
						aria-valuenow={ 1 }
					>
						{ progressIndicator === 'bar' && (
							<div className="perform-form__progress-track">
								<div
									className="perform-form__progress-fill"
									style={ { '--perform-progress-percent': `${ ( ( 1 / stepCount ) * 100 ).toFixed( 2 ) }%` } }
								/>
							</div>
						) }
						{ progressIndicator === 'dots' && (
							Array.from( { length: stepCount } ).map( ( _, i ) => (
								<span
									key={ i }
									className={ `perform-form__progress-dot${ i === 0 ? ' is-current' : '' }` }
									aria-hidden="true"
								/>
							) )
						) }
						{ progressIndicator === 'numbers' && (
							<span className="perform-form__progress-label">
								{ /* translators: 1: current step number, 2: total step count */
									sprintf( __( 'Step %1$s of %2$s', 'perform-forms' ), '1', String( stepCount ) ) }
							</span>
						) }
						{ showStepLabels && hasAnyStepLabel && (
							<span className="perform-form__progress-step-label">
								{ stepLabels[ 0 ] }
							</span>
						) }
					</div>
				) }
				<InnerBlocks
					allowedBlocks={ ALLOWED_BLOCKS }
					template={ TEMPLATE }
					templateLock={ false }
				/>
			</div>
		</>
	);
}
