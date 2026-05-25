/**
 * Integrations / Webhooks inspector panel.
 *
 * Loads the webhook list for the current form from the
 * `/perform/v1/webhooks` REST endpoint on mount, lets the author
 * add / edit / delete entries, and persists every change immediately
 * — no "Save" button on the parent block needed, because webhooks
 * live in their own DB table outside the block tree (Phase 6
 * architecture decision, see PERFORM_ROADMAP.md Phase 6).
 *
 * Each webhook renders as its own nested PanelBody so authors get
 * the familiar WordPress accordion UX. Header editing is a tiny
 * key/value list with add / remove buttons — fine for a handful
 * of auth tokens, no full table component needed.
 *
 * @package PerForm
 * @since 0.1.0
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	Button,
	Notice,
	PanelBody,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

const BLANK_WEBHOOK = {
	label: '',
	url: '',
	method: 'POST',
	format: 'json',
	headers: {},
	field_mapping: {},
	condition_field: '',
	condition_operator: '',
	condition_value: '',
	is_active: true,
};

export default function IntegrationsPanel( { formId, formFields = [] } ) {
	const [ webhooks, setWebhooks ] = useState( [] );
	const [ status, setStatus ] = useState( 'loading' ); // loading | ready | error
	const [ error, setError ] = useState( '' );

	// Fetch the existing webhook list whenever the form id changes
	// (in practice once, since formId is immutable after first mount).
	useEffect( () => {
		if ( ! formId ) {
			setStatus( 'ready' );
			return;
		}

		let cancelled = false;
		setStatus( 'loading' );
		apiFetch( { path: `/perform/v1/webhooks?form_id=${ encodeURIComponent( formId ) }` } )
			.then( ( data ) => {
				if ( cancelled ) {
					return;
				}
				setWebhooks( Array.isArray( data ) ? data : [] );
				setStatus( 'ready' );
			} )
			.catch( ( err ) => {
				if ( cancelled ) {
					return;
				}
				setError( err?.message ?? __( 'Could not load webhooks.', 'perform-forms' ) );
				setStatus( 'error' );
			} );

		return () => {
			cancelled = true;
		};
	}, [ formId ] );

	const createWebhook = useCallback( async () => {
		setError( '' );
		try {
			const created = await apiFetch( {
				path: '/perform/v1/webhooks',
				method: 'POST',
				data: { ...BLANK_WEBHOOK, form_id: formId, url: 'https://' },
			} );
			setWebhooks( ( prev ) => [ ...prev, created ] );
		} catch ( err ) {
			setError( err?.message ?? __( 'Could not create webhook.', 'perform-forms' ) );
		}
	}, [ formId ] );

	const updateWebhook = useCallback( async ( id, patch ) => {
		setError( '' );
		try {
			const updated = await apiFetch( {
				path: `/perform/v1/webhooks/${ id }`,
				method: 'PUT',
				data: patch,
			} );
			setWebhooks( ( prev ) => prev.map( ( wh ) => ( wh.id === id ? updated : wh ) ) );
		} catch ( err ) {
			setError( err?.message ?? __( 'Could not update webhook.', 'perform-forms' ) );
		}
	}, [] );

	const deleteWebhook = useCallback( async ( id ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this webhook? Its delivery log will also be removed.', 'perform-forms' ) ) ) {
			return;
		}
		setError( '' );
		try {
			await apiFetch( {
				path: `/perform/v1/webhooks/${ id }`,
				method: 'DELETE',
			} );
			setWebhooks( ( prev ) => prev.filter( ( wh ) => wh.id !== id ) );
		} catch ( err ) {
			setError( err?.message ?? __( 'Could not delete webhook.', 'perform-forms' ) );
		}
	}, [] );

	return (
		<PanelBody title={ __( 'Integrations', 'perform-forms' ) } initialOpen={ false }>
			{ status === 'loading' && (
				<div style={ { textAlign: 'center', padding: '8px 0' } }>
					<Spinner />
				</div>
			) }

			{ status === 'error' && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ status === 'ready' && webhooks.length === 0 && (
				<p style={ { fontSize: '13px', opacity: 0.75, marginTop: 0 } }>
					{ __( 'No webhooks yet. Add one to send submissions to an external URL (Zapier, n8n, Make, your own API).', 'perform-forms' ) }
				</p>
			) }

			{ status === 'ready' && webhooks.map( ( webhook ) => (
				<WebhookCard
					key={ webhook.id }
					webhook={ webhook }
					formFields={ formFields }
					onChange={ ( patch ) => updateWebhook( webhook.id, patch ) }
					onDelete={ () => deleteWebhook( webhook.id ) }
				/>
			) ) }

			{ status === 'ready' && (
				<Button
					variant="secondary"
					onClick={ createWebhook }
					style={ { marginTop: '8px' } }
					__next40pxDefaultSize
				>
					{ __( 'Add webhook', 'perform-forms' ) }
				</Button>
			) }
		</PanelBody>
	);
}

/**
 * Single webhook card — wraps every input in its own nested PanelBody so
 * the form fields are tucked behind a single click and the inspector
 * doesn't drown when an author has three or four webhooks set up.
 */
function WebhookCard( { webhook, formFields, onChange, onDelete } ) {
	// Send-test state. Reset to null whenever the URL changes so an
	// old response doesn't sit there misleadingly while the author
	// is typing a new endpoint.
	const [ testState, setTestState ] = useState( null ); // null | "running" | { ok, code, body }

	useEffect( () => {
		setTestState( null );
	}, [ webhook.url ] );

	const runTest = async () => {
		setTestState( 'running' );
		try {
			const result = await apiFetch( {
				path: `/perform/v1/webhooks/${ webhook.id }/test`,
				method: 'POST',
			} );
			setTestState( {
				ok: !! result.ok,
				code: result.response_code,
				body: result.response_body ?? '',
			} );
		} catch ( err ) {
			setTestState( {
				ok: false,
				code: null,
				body: err?.message ?? __( 'Test request failed.', 'perform-forms' ),
			} );
		}
	};
	const title = webhook.label
		? webhook.label
		: ( webhook.url ? truncateUrl( webhook.url ) : __( 'Untitled webhook', 'perform-forms' ) );

	return (
		<PanelBody
			title={ `${ webhook.is_active ? '● ' : '○ ' }${ title }` }
			initialOpen={ false }
			className="perform-webhook-card"
		>
			<TextControl
				label={ __( 'Label', 'perform-forms' ) }
				help={ __( 'Optional. Used in the webhook log to identify this destination.', 'perform-forms' ) }
				value={ webhook.label ?? '' }
				onChange={ ( value ) => onChange( { label: value } ) }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>

			<TextControl
				label={ __( 'URL', 'perform-forms' ) }
				value={ webhook.url ?? '' }
				onChange={ ( value ) => onChange( { url: value } ) }
				type="url"
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>

			<ToggleGroupControl
				label={ __( 'Method', 'perform-forms' ) }
				value={ webhook.method ?? 'POST' }
				onChange={ ( value ) => onChange( { method: value } ) }
				isBlock
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			>
				<ToggleGroupControlOption value="POST" label="POST" />
				<ToggleGroupControlOption value="GET" label="GET" />
			</ToggleGroupControl>

			<ToggleGroupControl
				label={ __( 'Payload format', 'perform-forms' ) }
				value={ webhook.format ?? 'json' }
				onChange={ ( value ) => onChange( { format: value } ) }
				isBlock
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			>
				<ToggleGroupControlOption value="json" label={ __( 'JSON', 'perform-forms' ) } />
				<ToggleGroupControlOption value="form" label={ __( 'Form-encoded', 'perform-forms' ) } />
			</ToggleGroupControl>

			<HeadersEditor
				headers={ webhook.headers ?? {} }
				onChange={ ( headers ) => onChange( { headers } ) }
			/>

			<ConditionEditor
				webhook={ webhook }
				formFields={ formFields }
				onChange={ onChange }
			/>

			<FieldMappingEditor
				mapping={ webhook.field_mapping ?? {} }
				formFields={ formFields }
				onChange={ ( field_mapping ) => onChange( { field_mapping } ) }
			/>

			<ToggleControl
				label={ __( 'Active', 'perform-forms' ) }
				help={ __( 'Disable to stop deliveries without losing the configuration.', 'perform-forms' ) }
				checked={ !! webhook.is_active }
				onChange={ ( value ) => onChange( { is_active: value } ) }
				__nextHasNoMarginBottom
			/>

			<hr style={ { margin: '12px 0', opacity: 0.25 } } />

			<Button
				variant="secondary"
				onClick={ runTest }
				disabled={ testState === 'running' || ! webhook.url || webhook.url === 'https://' }
				__next40pxDefaultSize
			>
				{ testState === 'running'
					? __( 'Sending test…', 'perform-forms' )
					: __( 'Send test', 'perform-forms' ) }
			</Button>

			{ testState && testState !== 'running' && (
				<TestResult result={ testState } />
			) }

			<hr style={ { margin: '12px 0', opacity: 0.25 } } />

			<Button
				variant="link"
				isDestructive
				onClick={ onDelete }
				style={ { padding: 0 } }
			>
				{ __( 'Delete this webhook', 'perform-forms' ) }
			</Button>
		</PanelBody>
	);
}

/**
 * Inline rendering of a Send-test response. Green for 2xx, red for
 * everything else. Response body shown truncated in a scrollable
 * pre-block so authors can verify the receiver actually saw the
 * payload they expected.
 */
function TestResult( { result } ) {
	const isOk = result.ok;
	const codeLabel = null === result.code
		? __( 'no response', 'perform-forms' )
		: String( result.code );

	return (
		<div
			style={ {
				marginTop: '8px',
				padding: '8px 10px',
				border: '1px solid',
				borderColor: isOk ? '#28a745' : '#cc0000',
				borderRadius: '4px',
				background: isOk ? 'rgba(40, 167, 69, 0.08)' : 'rgba(204, 0, 0, 0.08)',
				fontSize: '12px',
			} }
		>
			<strong>
				{ isOk
					? sprintf(
						/* translators: %s: HTTP status code */
						__( '✓ HTTP %s', 'perform-forms' ),
						codeLabel
					)
					: sprintf(
						/* translators: %s: HTTP status code or "no response" */
						__( '✕ HTTP %s', 'perform-forms' ),
						codeLabel
					) }
			</strong>
			{ result.body && (
				<pre
					style={ {
						marginTop: '6px',
						maxHeight: '120px',
						overflow: 'auto',
						whiteSpace: 'pre-wrap',
						wordBreak: 'break-word',
						background: 'transparent',
						margin: '6px 0 0',
						fontFamily: 'monospace',
						fontSize: '11px',
					} }
				>{ result.body }</pre>
			) }
		</div>
	);
}

/**
 * Inline key/value list for HTTP headers. State stays denormalised
 * so an empty key doesn't disappear while the author is mid-typing —
 * the parent commits via onChange only when both key and value are
 * non-empty (so we never send `"":"Bearer …"` to the API).
 */
function HeadersEditor( { headers, onChange } ) {
	// Convert the incoming object to an editable [key, value] pair list.
	// Local state is the source of truth while the user is editing; we
	// reflect commits back up via onChange whenever a row has both
	// halves filled in (so the JSON we save stays clean).
	const [ pairs, setPairs ] = useState( () =>
		Object.keys( headers ).map( ( k ) => [ k, headers[ k ] ] )
	);

	const commit = ( nextPairs ) => {
		const obj = {};
		nextPairs.forEach( ( [ k, v ] ) => {
			const key = String( k ).trim();
			if ( key === '' ) {
				return;
			}
			obj[ key ] = String( v );
		} );
		onChange( obj );
	};

	const setPair = ( index, key, value ) => {
		const next = pairs.map( ( pair, i ) => ( i === index ? [ key, value ] : pair ) );
		setPairs( next );
		commit( next );
	};

	const addPair = () => {
		const next = [ ...pairs, [ '', '' ] ];
		setPairs( next );
		// Don't commit on add — empty pair has no value to persist yet.
	};

	const removePair = ( index ) => {
		const next = pairs.filter( ( _, i ) => i !== index );
		setPairs( next );
		commit( next );
	};

	return (
		<div style={ { marginBottom: '12px' } }>
			<p style={ { fontSize: '11px', textTransform: 'uppercase', fontWeight: 500, marginBottom: '4px' } }>
				{ __( 'Headers', 'perform-forms' ) }
			</p>
			{ pairs.length === 0 && (
				<p style={ { fontSize: '12px', opacity: 0.7, margin: '0 0 8px' } }>
					{ __( 'No custom headers.', 'perform-forms' ) }
				</p>
			) }
			{ pairs.map( ( [ key, value ], index ) => (
				<div
					key={ index }
					style={ { display: 'flex', gap: '4px', marginBottom: '4px' } }
				>
					<input
						type="text"
						value={ key }
						placeholder={ __( 'Header name', 'perform-forms' ) }
						onChange={ ( e ) => setPair( index, e.target.value, value ) }
						style={ { flex: 1, minWidth: 0 } }
					/>
					<input
						type="text"
						value={ value }
						placeholder={ __( 'Value', 'perform-forms' ) }
						onChange={ ( e ) => setPair( index, key, e.target.value ) }
						style={ { flex: 1, minWidth: 0 } }
					/>
					<Button
						isDestructive
						variant="tertiary"
						onClick={ () => removePair( index ) }
						label={ __( 'Remove header', 'perform-forms' ) }
						showTooltip
					>
						×
					</Button>
				</div>
			) ) }
			<Button
				variant="secondary"
				size="small"
				onClick={ addPair }
			>
				{ __( '+ Add header', 'perform-forms' ) }
			</Button>
		</div>
	);
}

/**
 * Trigger-condition editor. A single rule of the form
 *
 *   IF <field> <operator> [<value>]
 *
 * The author picks the field from the list of submitting field
 * blocks on this form, an operator from a fixed allow-list, and —
 * for value-based operators — types the comparison value. The
 * empty-state operators (is_empty / is_not_empty) hide the value
 * input because it would be meaningless. Saving an empty condition
 * reads server-side as "always fire", so toggling the rule off
 * doesn't need a separate "active" switch.
 */
function ConditionEditor( { webhook, formFields, onChange } ) {
	const isEnabled = ( webhook.condition_field ?? '' ) !== '' && ( webhook.condition_operator ?? '' ) !== '';
	const operator = webhook.condition_operator ?? '';
	const usesValue = operator !== 'is_empty' && operator !== 'is_not_empty';

	const operatorOptions = [
		{ value: '', label: __( '— Select operator —', 'perform-forms' ) },
		{ value: 'equals', label: __( 'equals', 'perform-forms' ) },
		{ value: 'not_equals', label: __( 'does not equal', 'perform-forms' ) },
		{ value: 'contains', label: __( 'contains', 'perform-forms' ) },
		{ value: 'not_contains', label: __( 'does not contain', 'perform-forms' ) },
		{ value: 'is_empty', label: __( 'is empty', 'perform-forms' ) },
		{ value: 'is_not_empty', label: __( 'is not empty', 'perform-forms' ) },
	];

	const fieldOptions = [
		{ value: '', label: __( '— Select a field —', 'perform-forms' ) },
		...formFields.map( ( f ) => ( {
			value: f.name,
			label: f.label ? `${ f.label } (${ f.name })` : f.name,
		} ) ),
	];

	const handleToggle = ( on ) => {
		if ( on ) {
			// Pre-fill with the first field if available, so the
			// condition state isn't half-empty after enabling.
			onChange( {
				condition_field: formFields[ 0 ]?.name ?? '',
				condition_operator: 'equals',
				condition_value: webhook.condition_value ?? '',
			} );
		} else {
			onChange( {
				condition_field: '',
				condition_operator: '',
				condition_value: '',
			} );
		}
	};

	return (
		<div style={ { marginBottom: '12px' } }>
			<ToggleControl
				label={ __( 'Conditional delivery', 'perform-forms' ) }
				help={ __( 'Only send this webhook when the submission matches the rule below.', 'perform-forms' ) }
				checked={ isEnabled }
				onChange={ handleToggle }
				__nextHasNoMarginBottom
			/>

			{ isEnabled && (
				<div style={ { paddingLeft: '12px', borderLeft: '2px solid #ddd', marginTop: '8px' } }>
					{ formFields.length === 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Add at least one field block to this form to use conditional delivery.', 'perform-forms' ) }
						</Notice>
					) }

					<SelectControl
						label={ __( 'Field', 'perform-forms' ) }
						value={ webhook.condition_field ?? '' }
						options={ fieldOptions }
						onChange={ ( value ) => onChange( { condition_field: value } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>

					<SelectControl
						label={ __( 'Operator', 'perform-forms' ) }
						value={ operator }
						options={ operatorOptions }
						onChange={ ( value ) => {
							const patch = { condition_operator: value };
							// Clear the value when switching to an
							// empty-state operator so the persisted
							// row doesn't carry a stale comparison.
							if ( value === 'is_empty' || value === 'is_not_empty' ) {
								patch.condition_value = '';
							}
							onChange( patch );
						} }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>

					{ usesValue && (
						<TextControl
							label={ __( 'Value', 'perform-forms' ) }
							value={ webhook.condition_value ?? '' }
							onChange={ ( value ) => onChange( { condition_value: value } ) }
							help={ __( 'Comparison is case-insensitive for contains / not contains.', 'perform-forms' ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					) }
				</div>
			) }
		</div>
	);
}

/**
 * Field-name mapping editor. Optional rename map — internal field
 * name → external payload key — applied by the server before the
 * webhook fires. Missing or empty entries leave the field untouched,
 * so partial mappings are fine.
 */
function FieldMappingEditor( { mapping, formFields, onChange } ) {
	const hasAnyMapping = Object.keys( mapping ).some( ( k ) => ( mapping[ k ] ?? '' ) !== '' );

	const handleToggle = ( on ) => {
		if ( ! on ) {
			onChange( {} );
		} else if ( ! hasAnyMapping && formFields.length > 0 ) {
			// Seed the first field with an empty target so the editor
			// has a visible row to work with — author edits the value
			// and the persistence flow picks it up.
			onChange( { [ formFields[ 0 ].name ]: '' } );
		}
	};

	const setTarget = ( fieldName, target ) => {
		const next = { ...mapping };
		if ( ! target || target.trim() === '' ) {
			delete next[ fieldName ];
		} else {
			next[ fieldName ] = target;
		}
		onChange( next );
	};

	return (
		<div style={ { marginBottom: '12px' } }>
			<ToggleControl
				label={ __( 'Rename fields in payload', 'perform-forms' ) }
				help={ __( 'Send fields under different keys than their internal names. Leave a target empty to pass that field through unchanged.', 'perform-forms' ) }
				checked={ hasAnyMapping || ( formFields.length > 0 && Object.keys( mapping ).length > 0 ) }
				onChange={ handleToggle }
				__nextHasNoMarginBottom
			/>

			{ ( hasAnyMapping || Object.keys( mapping ).length > 0 ) && formFields.length > 0 && (
				<div style={ { paddingLeft: '12px', borderLeft: '2px solid #ddd', marginTop: '8px' } }>
					{ formFields.map( ( field ) => (
						<div
							key={ field.name }
							style={ { display: 'flex', gap: '6px', alignItems: 'center', marginBottom: '4px' } }
						>
							<code style={ { flex: '0 0 40%', fontSize: '11px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } }>
								{ field.name }
							</code>
							<span style={ { opacity: 0.5 } }>→</span>
							<input
								type="text"
								value={ mapping[ field.name ] ?? '' }
								placeholder={ field.name }
								onChange={ ( e ) => setTarget( field.name, e.target.value ) }
								style={ { flex: 1, minWidth: 0 } }
							/>
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}

/**
 * Cut a URL down to something readable in the PanelBody title — keeps
 * the host + first few path segments so authors recognise their
 * webhooks at a glance. The full URL still lives in the TextControl
 * inside the card.
 */
function truncateUrl( url ) {
	if ( url.length <= 48 ) {
		return url;
	}
	try {
		const u = new URL( url );
		const tail = u.pathname.length > 12 ? `${ u.pathname.slice( 0, 12 ) }…` : u.pathname;
		return `${ u.host }${ tail }`;
	} catch ( _ ) {
		return url.slice( 0, 48 ) + '…';
	}
}
