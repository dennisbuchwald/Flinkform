/**
 * Form Container — editor component.
 *
 * Generates a stable UUID for `formId` on first mount and never mutates it.
 * If a block is duplicated, both copies inherit the same UUID — Phase 1
 * accepts this; later phases may detect and re-key duplicates explicitly.
 */
import { Fragment, useCallback, useEffect, useMemo, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import {
	ColorPalette,
	InnerBlocks,
	Inserter,
	InspectorControls,
	URLInput,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	BaseControl,
	Button,
	Notice,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import ConditionalLogicPanel from '../shared/conditional-logic-panel';

// Inline plus glyph for the "Add field" appender button. Inline (not
// @wordpress/icons) to avoid pulling in an extra dependency; the white
// fill comes from the button's CSS (svg { fill: #fff }).
const ADD_FIELD_ICON = (
	<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
		<path d="M11 11V5h2v6h6v2h-6v6h-2v-6H5v-2z" />
	</svg>
);

const CORE_ALLOWED_BLOCKS = [
	'flinkform/section-heading',
	'flinkform/page-break',
	'flinkform/field-text',
	'flinkform/field-email',
	'flinkform/field-textarea',
	'flinkform/field-number',
	'flinkform/field-date',
	'flinkform/field-url',
	'flinkform/field-phone',
	'flinkform/field-select',
	'flinkform/field-radio',
	'flinkform/field-checkbox',
	'flinkform/field-toggle',
	'flinkform/field-hidden',
	'flinkform/field-consent',
];

// Bridge seam: add-ons append their field blocks (e.g. Pro's file upload)
// via this JS filter — same mechanism as the inspector-panels seam below.
// Evaluated lazily (at render time, not import time) so the result doesn't
// depend on script load order between the core and add-on editor bundles.
function getAllowedBlocks() {
	const filtered = applyFilters( 'flinkform.formContainer.allowedBlocks', CORE_ALLOWED_BLOCKS );
	return Array.isArray( filtered ) && filtered.length > 0 ? filtered : CORE_ALLOWED_BLOCKS;
}

const TEMPLATE = [
	[ 'flinkform/field-text', { label: __( 'Name', 'flinkform' ), required: true } ],
	[ 'flinkform/field-email', { label: __( 'Email', 'flinkform' ), required: true } ],
	[ 'flinkform/field-textarea', { label: __( 'Message', 'flinkform' ), required: true } ],
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

// English block.json defaults — used to detect untouched attributes
// so the editor can show the translated version instead.
const SUBMIT_LABEL_DEFAULT = 'Send';
const SUCCESS_MSG_DEFAULT = 'Thank you! Your message has been sent successfully.';
const SUCCESS_MSG_OLD = 'Thank you! Your message has been sent.';

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { formId, title, submitLabel, successMessage, notifications, appearance, afterSubmit, retentionDays } = attributes;

	// Translate defaults for display in the editor. The stored attribute
	// stays English so render.php's i18n fallback works across locales.
	const isDefaultSubmitLabel = submitLabel === SUBMIT_LABEL_DEFAULT;
	const displaySubmitLabel = isDefaultSubmitLabel ? __( 'Send', 'flinkform' ) : submitLabel;

	const isDefaultSuccessMsg = successMessage === SUCCESS_MSG_DEFAULT || successMessage === SUCCESS_MSG_OLD;
	const displaySuccessMsg = isDefaultSuccessMsg
		? __( 'Thank you! Your message has been sent successfully.', 'flinkform' )
		: successMessage;

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

	// Editor preview: emit the same --flinkform-* overrides + modifier
	// classes the frontend gets so the in-editor block visually mirrors
	// the saved settings. Without this the inspector previewing a custom
	// primary colour or spacing wouldn't show it on the canvas.
	const editorStyle = {};
	if ( typeof primaryColor === 'string' && primaryColor !== '' ) {
		editorStyle[ '--flinkform-color-primary' ] = primaryColor;
	}
	if ( typeof borderRadius === 'number' ) {
		editorStyle[ '--flinkform-border-radius' ] = `${ borderRadius }px`;
	}
	if ( typeof buttonColor === 'string' && buttonColor !== '' ) {
		editorStyle[ '--flinkform-button-bg' ] = buttonColor;
	}
	if ( typeof buttonTextColor === 'string' && buttonTextColor !== '' ) {
		editorStyle[ '--flinkform-button-color' ] = buttonTextColor;
	}
	if ( typeof buttonBorderColor === 'string' && buttonBorderColor !== '' ) {
		editorStyle[ '--flinkform-button-border-color' ] = buttonBorderColor;
	}

	const editorClassName = [
		'flinkform-form-editor',
		'flinkform-form',
		`flinkform-form--button-${ submitButtonStyle }`,
		`flinkform-form--field-style-${ fieldStyle }`,
		`flinkform-form--spacing-${ fieldSpacing }`,
		`flinkform-form--labels-${ labelPosition }`,
		`flinkform-form--columns-${ columns }`,
	].join( ' ' );

	// Floating-label background auto-detect (editor preview).
	// Mirrors the frontend's initFloatingLabelBackground(): walks up
	// the DOM from the block wrapper and picks up the nearest ancestor's
	// non-transparent background-color so the label notch matches.
	const blockRef = useRef( null );
	const mergedRef = useCallback( ( node ) => {
		blockRef.current = node;
	}, [] );

	useEffect( () => {
		const node = blockRef.current;
		if ( ! node || labelPosition !== 'floating' ) {
			return;
		}
		let el = node.parentElement;
		while ( el ) {
			const bg = getComputedStyle( el ).backgroundColor;
			if ( bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent' ) {
				node.style.setProperty( '--flinkform-page-background', bg );
				return;
			}
			el = el.parentElement;
		}
	}, [ labelPosition ] );

	const blockProps = useBlockProps( {
		ref: mergedRef,
		className: editorClassName,
		style: editorStyle,
		// Mirror the frontend's data-flinkform-id so Custom CSS rules
		// scoped to [data-flinkform-id="…"] take effect in the editor too.
		'data-flinkform-id': formId || undefined,
	} );

	// Duplicate detection: duplicating a form block copies its attributes,
	// formId included — two forms with the same UUID would share their
	// submissions, notifications and spam tokens. The first occurrence in
	// the document keeps the UUID; every later copy re-keys itself.
	const isDuplicateFormId = useSelect(
		( select ) => {
			if ( ! formId ) {
				return false;
			}
			const { getBlocks } = select( 'core/block-editor' );
			const matches = [];
			const walk = ( blocks ) => {
				for ( const block of blocks ) {
					if ( block.name === 'flinkform/form' && block.attributes?.formId === formId ) {
						matches.push( block.clientId );
					}
					if ( block.innerBlocks?.length ) {
						walk( block.innerBlocks );
					}
				}
			};
			walk( getBlocks() );
			return matches.length > 1 && matches[ 0 ] !== clientId;
		},
		[ formId, clientId ]
	);

	useEffect( () => {
		if ( ! formId || isDuplicateFormId ) {
			setAttributes( { formId: generateUuid() } );
		}
		// formId is immutable after assignment except when this block is a
		// duplicate of another form in the same document.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ formId, isDuplicateFormId ] );

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
			.filter( ( b ) => b.name === 'flinkform/field-email' )
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
		return innerBlocks.filter( ( b ) => b.name === 'flinkform/page-break' ).length + 1;
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
				if ( b.name === 'flinkform/page-break' ) {
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
				<PanelBody title={ __( 'Form Settings', 'flinkform' ) }>
					<TextControl
						label={ __( 'Form Title', 'flinkform' ) }
						help={ __( 'Internal name shown in the Forms admin overview. Not visible to visitors.', 'flinkform' ) }
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Submit Button Label', 'flinkform' ) }
						value={ displaySubmitLabel }
						onChange={ ( value ) => setAttributes( { submitLabel: value } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<p style={ { fontSize: '12px', opacity: 0.7, marginTop: '12px' } }>
						{ __( 'Form ID:', 'flinkform' ) } <code>{ formId || '…' }</code>
					</p>
				</PanelBody>

				<PanelBody
					title={ __( 'After Submit', 'flinkform' ) }
					initialOpen={ false }
				>
					<ToggleGroupControl
						label={ __( 'On successful submission', 'flinkform' ) }
						value={ afterSubmitBehaviour }
						onChange={ ( value ) => updateAfterSubmit( { behaviour: value } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="message" label={ __( 'Show message', 'flinkform' ) } />
						<ToggleGroupControlOption value="redirect" label={ __( 'Redirect to URL', 'flinkform' ) } />
					</ToggleGroupControl>

					{ afterSubmitBehaviour === 'message' && (
						<TextareaControl
							label={ __( 'Success Message', 'flinkform' ) }
							help={ __( 'Shown in place of the form after a successful submission.', 'flinkform' ) }
							value={ displaySuccessMsg }
							onChange={ ( value ) => setAttributes( { successMessage: value } ) }
							__nextHasNoMarginBottom
						/>
					) }

					{ afterSubmitBehaviour === 'redirect' && (
						<>
							<BaseControl
								label={ __( 'Redirect URL', 'flinkform' ) }
								help={ __( 'Same-origin only. Enter a path (/danke) or a full URL on this site. External URLs are silently rejected by the safe-redirect filter — operators who need cross-domain thank-you pages get a site-wide opt-in toggle in a later release.', 'flinkform' ) }
								id="flinkform-after-submit-url"
								__nextHasNoMarginBottom
							>
								<URLInput
									id="flinkform-after-submit-url"
									value={ afterSubmitConfig.redirectUrl ?? '' }
									onChange={ ( value ) => updateAfterSubmit( { redirectUrl: value } ) }
									placeholder={ __( 'https://example.com/danke', 'flinkform' ) }
									className="flinkform-after-submit__url"
								/>
							</BaseControl>

							<ToggleControl
								label={ __( 'Append submission ID', 'flinkform' ) }
								help={ __( 'Adds ?flinkform_submission_id=N to the redirect URL — useful for analytics events on the thank-you page (gtag, GA4, Plausible, Matomo). Default off because submission IDs are PII-adjacent and end up in browser history.', 'flinkform' ) }
								checked={ !! afterSubmitConfig.appendSubmissionId }
								onChange={ ( value ) => updateAfterSubmit( { appendSubmissionId: value } ) }
								__nextHasNoMarginBottom
							/>

							<ToggleControl
								label={ __( 'Append form ID', 'flinkform' ) }
								help={ __( 'Adds ?flinkform_form_id=UUID — lets a shared thank-you page differentiate conversions by source form.', 'flinkform' ) }
								checked={ !! afterSubmitConfig.appendFormId }
								onChange={ ( value ) => updateAfterSubmit( { appendFormId: value } ) }
								__nextHasNoMarginBottom
							/>
						</>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Style', 'flinkform' ) }
					initialOpen={ false }
					className="flinkform-style-panel"
				>
					<BaseControl
						label={ __( 'Primary color', 'flinkform' ) }
						help={ __( 'Used for submit button, focus rings, and accent details. Leave unset to inherit from your theme.', 'flinkform' ) }
						id="flinkform-style-primary-color"
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
						label={ __( 'Field style', 'flinkform' ) }
						help={ __( 'Bordered: full outline. Soft: light gray border. Underline: bottom border only. Minimal: no border, subtle background.', 'flinkform' ) }
						value={ fieldStyle }
						onChange={ ( value ) => updateAppearance( { fieldStyle: value } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="bordered" label={ __( 'Bordered', 'flinkform' ) } />
						<ToggleGroupControlOption value="soft" label={ __( 'Soft', 'flinkform' ) } />
						<ToggleGroupControlOption value="underline" label={ __( 'Underline', 'flinkform' ) } />
						<ToggleGroupControlOption value="minimal" label={ __( 'Minimal', 'flinkform' ) } />
					</ToggleGroupControl>
					<RangeControl
						label={ __( 'Border radius', 'flinkform' ) }
						help={ __( 'Applies to fields and the submit button. Leave at the default to inherit from your theme.', 'flinkform' ) }
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
						label={ __( 'Field spacing', 'flinkform' ) }
						value={ fieldSpacing }
						onChange={ ( value ) => updateAppearance( { fieldSpacing: value } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="compact" label={ __( 'Compact', 'flinkform' ) } />
						<ToggleGroupControlOption value="normal" label={ __( 'Normal', 'flinkform' ) } />
						<ToggleGroupControlOption value="relaxed" label={ __( 'Relaxed', 'flinkform' ) } />
					</ToggleGroupControl>
					<ToggleGroupControl
						label={ __( 'Label position', 'flinkform' ) }
						help={
							labelPosition === 'floating'
								? __( 'Material-style notched label: rests inside the input, then slides onto the top border on focus, cutting a notch through it. Applies to text-style fields.', 'flinkform' )
								: labelPosition === 'placeholder'
									? __( 'Labels are visually hidden (still accessible to screen readers). The label text is used as placeholder inside the field. Applies to text-style fields only.', 'flinkform' )
									: __( 'Beside, Floating and Hidden apply to text-style fields only.', 'flinkform' )
						}
						value={ labelPosition }
						onChange={ ( value ) => updateAppearance( { labelPosition: value } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="above" label={ __( 'Above', 'flinkform' ) } />
						<ToggleGroupControlOption value="beside" label={ __( 'Beside', 'flinkform' ) } />
						<ToggleGroupControlOption value="floating" label={ __( 'Floating', 'flinkform' ) } />
						<ToggleGroupControlOption value="placeholder" label={ __( 'Hidden', 'flinkform' ) } />
					</ToggleGroupControl>
					<ToggleGroupControl
						label={ __( 'Columns', 'flinkform' ) }
						help={ __( 'Two-column layout collapses to a single column on mobile. Individual fields can be set to span both columns via their own inspector.', 'flinkform' ) }
						value={ columns }
						onChange={ ( value ) => updateAppearance( { columns: value === 2 ? 2 : 1 } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value={ 1 } label={ __( '1 column', 'flinkform' ) } />
						<ToggleGroupControlOption value={ 2 } label={ __( '2 columns', 'flinkform' ) } />
					</ToggleGroupControl>
					<ToggleGroupControl
						label={ __( 'Submit button style', 'flinkform' ) }
						value={ submitButtonStyle }
						onChange={ ( value ) => updateAppearance( { submitButtonStyle: value } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="fill" label={ __( 'Fill', 'flinkform' ) } />
						<ToggleGroupControlOption value="outline" label={ __( 'Outline', 'flinkform' ) } />
						<ToggleGroupControlOption value="ghost" label={ __( 'Ghost', 'flinkform' ) } />
					</ToggleGroupControl>
					<BaseControl
						label={ __( 'Button background', 'flinkform' ) }
						help={ __( 'Leave unset to use the primary colour.', 'flinkform' ) }
						id="flinkform-style-button-color"
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
						label={ __( 'Button text colour', 'flinkform' ) }
						help={ __( 'Leave unset for automatic contrast.', 'flinkform' ) }
						id="flinkform-style-button-text-color"
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
						label={ __( 'Button border colour', 'flinkform' ) }
						help={ __( 'Only visible when button style is "Outline". Leave unset to match the background colour.', 'flinkform' ) }
						id="flinkform-style-button-border-color"
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
						label={ __( 'Progress indicator', 'flinkform' ) }
						help={ __( 'Shown on multi-step forms only. Bar fills as the user advances; Dots marks each step; Numbers reads "Step X of Y".', 'flinkform' ) }
						value={ progressIndicator }
						onChange={ ( value ) => updateAppearance( { progressIndicator: value } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="bar" label={ __( 'Bar', 'flinkform' ) } />
						<ToggleGroupControlOption value="dots" label={ __( 'Dots', 'flinkform' ) } />
						<ToggleGroupControlOption value="numbers" label={ __( 'Numbers', 'flinkform' ) } />
						<ToggleGroupControlOption value="none" label={ __( 'None', 'flinkform' ) } />
					</ToggleGroupControl>
					{ progressIndicator !== 'none' && (
						<ToggleControl
							label={ __( 'Show step labels', 'flinkform' ) }
							help={ __( 'Display the current step’s label (from the Page Break block) beneath the indicator. Only shows when at least one Page Break has a label.', 'flinkform' ) }
							checked={ showStepLabels }
							onChange={ ( value ) => updateAppearance( { showStepLabels: value } ) }
							__nextHasNoMarginBottom
						/>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Notifications', 'flinkform' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __( 'Send admin notification', 'flinkform' ) }
						help={ __( 'Email the site admin when a submission is received.', 'flinkform' ) }
						checked={ adminEnabled }
						onChange={ ( value ) => updateAdminConfig( { enabled: value } ) }
						__nextHasNoMarginBottom
					/>
					{ adminEnabled && (
						<>
							<TextControl
								label={ __( 'To', 'flinkform' ) }
								help={ __( 'Comma-separated. Leave empty to use the site admin email. Supports merge tags like {field:email}.', 'flinkform' ) }
								value={ adminConfig.to ?? '' }
								placeholder={ __( 'Site admin email', 'flinkform' ) }
								onChange={ ( value ) => updateAdminConfig( { to: value } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<TextControl
								label={ __( 'Subject', 'flinkform' ) }
								help={ __( 'Supports merge tags. Leave empty for the default.', 'flinkform' ) }
								value={ adminConfig.subject ?? '' }
								placeholder={ __( 'New submission: {form:title}', 'flinkform' ) }
								onChange={ ( value ) => updateAdminConfig( { subject: value } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<TextareaControl
								label={ __( 'Body', 'flinkform' ) }
								help={ __( 'Available tags: {form:title}, {site:name}, {site:url}, {submission:id}, {submission:date}, {field:<fieldName>}. Leave empty for an auto-generated list of all fields.', 'flinkform' ) }
								value={ adminConfig.body ?? '' }
								placeholder={ __( 'Auto-generated field list', 'flinkform' ) }
								onChange={ ( value ) => updateAdminConfig( { body: value } ) }
								rows={ 8 }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Reply-To', 'flinkform' ) }
								help={ __( 'Often set to {field:<emailFieldName>} so replies go to the submitter. Leave empty to use the site default.', 'flinkform' ) }
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
						label={ __( 'Send confirmation to submitter', 'flinkform' ) }
						help={ __( 'Email a copy of the submission back to the person who filled out the form.', 'flinkform' ) }
						checked={ submitterEnabled }
						onChange={ ( value ) => updateSubmitterConfig( { enabled: value } ) }
						__nextHasNoMarginBottom
					/>
					{ submitterEnabled && ! hasEmailFields && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Add an email field to this form to enable submitter confirmations.', 'flinkform' ) }
						</Notice>
					) }
					{ submitterEnabled && hasEmailFields && (
						<>
							<SelectControl
								label={ __( 'Email field', 'flinkform' ) }
								help={ __( 'Which field contains the submitter’s email address.', 'flinkform' ) }
								value={ submitterConfig.emailField ?? '' }
								options={ [
									{ value: '', label: __( '— Select a field —', 'flinkform' ) },
									...emailFieldOptions,
								] }
								onChange={ ( value ) => updateSubmitterConfig( { emailField: value } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<TextControl
								label={ __( 'Subject', 'flinkform' ) }
								help={ __( 'Supports merge tags. Leave empty for the default.', 'flinkform' ) }
								value={ submitterConfig.subject ?? '' }
								placeholder={ __( 'We received your submission', 'flinkform' ) }
								onChange={ ( value ) => updateSubmitterConfig( { subject: value } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<TextareaControl
								label={ __( 'Body', 'flinkform' ) }
								help={ __( 'Available tags: {form:title}, {site:name}, {site:url}, {submission:id}, {submission:date}, {field:<fieldName>}. Leave empty for an auto-generated thank-you with the submitted values.', 'flinkform' ) }
								value={ submitterConfig.body ?? '' }
								placeholder={ __( 'Auto-generated thank-you with submitted values', 'flinkform' ) }
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
					title={ __( 'Submit Condition', 'flinkform' ) }
					toggleLabel={ __( 'Gate the submit button', 'flinkform' ) }
					toggleHelp={ __( 'Disable the submit button until the rules below match. Useful for "I agree to terms" checkboxes.', 'flinkform' ) }
				/>

				{ /*
				 * Pro inspector-panel extension point. Flinkform Pro injects its
				 * own form-container inspector panels here via:
				 *   addFilter( 'flinkform.formContainer.inspectorPanels', … )
				 * The free core passes the full editing context; the default is
				 * an empty list, so with no add-on nothing extra renders.
				 * Contract: see includes/Bridge/README.md (frozen once Pro ships).
				 */ }
				{ applyFilters(
					'flinkform.formContainer.inspectorPanels',
					[],
					{ attributes, setAttributes, clientId, formId, formFields }
				).map( ( panel, index ) => (
					<Fragment key={ `flinkform-pro-panel-${ index }` }>
						{ panel }
					</Fragment>
				) ) }

				<PanelBody
					title={ __( 'Data Retention', 'flinkform' ) }
					initialOpen={ false }
				>
					<RangeControl
						label={ __( 'Auto-delete submissions after (days)', 'flinkform' ) }
						help={ __(
							'GDPR storage limitation: automatically delete this form’s submissions older than the chosen number of days. 0 = keep forever. Purges run daily and also remove any linked Flinkform Pro webhook delivery records.',
							'flinkform'
						) }
						value={ retentionDays ?? 0 }
						onChange={ ( value ) => setAttributes( { retentionDays: value ?? 0 } ) }
						min={ 0 }
						max={ 365 }
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				</InspectorControls>

			<div { ...blockProps }>
				{ isMultiStep && progressIndicator !== 'none' && (
					/* Editor preview of the progress indicator. Mirrors
					 * the server-rendered markup with currentStep = 0
					 * (Step 1 of N) so authors can see the chosen
					 * variant without leaving the editor. Class names
					 * are identical to render.php's output so the same
					 * SCSS styles them in both places. */
					<div
						className={ `flinkform-form__progress flinkform-form__progress--${ progressIndicator }` }
						role="progressbar"
						aria-valuemin={ 1 }
						aria-valuemax={ stepCount }
						aria-valuenow={ 1 }
					>
						{ progressIndicator === 'bar' && (
							<div className="flinkform-form__progress-track">
								<div
									className="flinkform-form__progress-fill"
									style={ { '--flinkform-progress-percent': `${ ( ( 1 / stepCount ) * 100 ).toFixed( 2 ) }%` } }
								/>
							</div>
						) }
						{ progressIndicator === 'dots' && (
							Array.from( { length: stepCount } ).map( ( _, i ) => (
								<span
									key={ i }
									className={ `flinkform-form__progress-dot${ i === 0 ? ' is-current' : '' }` }
									aria-hidden="true"
								/>
							) )
						) }
						{ progressIndicator === 'numbers' && (
							<span className="flinkform-form__progress-label">
								{ /* translators: 1: current step number, 2: total step count */
									sprintf( __( 'Step %1$s of %2$s', 'flinkform' ), '1', String( stepCount ) ) }
							</span>
						) }
						{ showStepLabels && hasAnyStepLabel && (
							<span className="flinkform-form__progress-step-label">
								{ stepLabels[ 0 ] }
							</span>
						) }
					</div>
				) }
				<InnerBlocks
					allowedBlocks={ getAllowedBlocks() }
					template={ TEMPLATE }
					templateLock={ false }
					renderAppender={ () => (
						<Inserter
							rootClientId={ clientId }
							isAppender
							// __experimentalIsQuick opens the compact quick
							// inserter (only the form's allowed field blocks +
							// a "Browse all" link to the full library) instead
							// of the full inserter. Restores the pre-button
							// behaviour, just behind a clearly visible branded
							// button so authors actually find the other fields.
							__experimentalIsQuick
							renderToggle={ ( { onToggle, disabled } ) => (
								<Button
									className="flinkform-add-field"
									onClick={ onToggle }
									disabled={ disabled }
									icon={ ADD_FIELD_ICON }
									__next40pxDefaultSize
								>
									{ __( 'Add field', 'flinkform' ) }
								</Button>
							) }
						/>
					) }
				/>
				{ /* Submit-button preview — mirrors the frontend's actions
				     row so authors see the button (label, style, colours)
				     while editing. Non-interactive on purpose. */ }
				<div className="flinkform-form__actions">
					<button
						type="button"
						className="flinkform-form__submit"
						disabled
						aria-disabled="true"
						style={ { cursor: 'default' } }
					>
						{ displaySubmitLabel || __( 'Send', 'flinkform' ) }
					</button>
				</div>
			</div>
		</>
	);
}
