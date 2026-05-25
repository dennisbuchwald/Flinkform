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

export default function IntegrationsPanel( { formId } ) {
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
function WebhookCard( { webhook, onChange, onDelete } ) {
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

			<ToggleControl
				label={ __( 'Active', 'perform-forms' ) }
				help={ __( 'Disable to stop deliveries without losing the configuration.', 'perform-forms' ) }
				checked={ !! webhook.is_active }
				onChange={ ( value ) => onChange( { is_active: value } ) }
				__nextHasNoMarginBottom
			/>

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
