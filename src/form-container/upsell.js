/**
 * Flinkform Pro hints in the editor (1.15.0). Information and a link only:
 * no Pro block is registered, no Pro control is rendered locked. Whether to
 * show anything at all is decided server-side (Admin\Upsell::enabled():
 * off when Pro is active or `flinkform_show_pro_upsell` says so) and
 * handed over as window.flinkformUpsell.
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as preferencesStore } from '@wordpress/preferences';
import { Button, ExternalLink, Modal, PanelBody } from '@wordpress/components';

const PREF_SCOPE = 'flinkform';
const PREF_HIDE_FIELDS = 'hideProFields';

function upsellData() {
	const data = typeof window !== 'undefined' ? window.flinkformUpsell : null;
	return data && data.show ? data : null;
}

function proFields() {
	return [
		{
			key: 'payment',
			title: __( 'Payment', 'flinkform' ),
			text: __( 'Take payments right in the form with Stripe: card, SEPA direct debit, Apple Pay and Google Pay. Fixed amounts or totals from a calculation field.', 'flinkform' ),
		},
		{
			key: 'upload',
			title: __( 'File upload', 'flinkform' ),
			text: __( 'Let people attach photos, PDFs or documents, several files per field, with type and size limits. Files are stored protected and attached to the mail.', 'flinkform' ),
		},
		{
			key: 'calculation',
			title: __( 'Calculation', 'flinkform' ),
			text: __( 'Show live prices and totals calculated from other fields, for quotes, price calculators and order forms.', 'flinkform' ),
		},
	];
}

/**
 * Greyed-out Pro fields below "Add field".
 */
export function ProFieldsRow() {
	const data = upsellData();
	const hidden = useSelect( ( select ) => !! select( preferencesStore ).get( PREF_SCOPE, PREF_HIDE_FIELDS ), [] );
	const { set } = useDispatch( preferencesStore );
	const [ open, setOpen ] = useState( null );

	if ( ! data || hidden ) {
		return null;
	}
	const fields = proFields();
	const current = fields.find( ( f ) => f.key === open );

	return (
		<div className="flinkform-pro-fields">
			<span className="flinkform-pro-fields__label">{ __( 'More fields with Pro:', 'flinkform' ) }</span>
			{ fields.map( ( f ) => (
				<Button key={ f.key } className="flinkform-pro-fields__item" onClick={ () => setOpen( f.key ) } size="small">
					{ f.title } <span className="flinkform-pro-badge">{ __( 'Pro', 'flinkform' ) }</span>
				</Button>
			) ) }
			<Button variant="link" className="flinkform-pro-fields__hide" onClick={ () => set( PREF_SCOPE, PREF_HIDE_FIELDS, true ) }>
				{ __( 'Hide', 'flinkform' ) }
			</Button>
			{ current && (
				<Modal title={ `${ current.title } · Flinkform Pro` } onRequestClose={ () => setOpen( null ) } size="small">
					<p>{ current.text }</p>
					<p>{ __( 'This field comes with the Flinkform Pro add-on. Your free forms keep working exactly as they are.', 'flinkform' ) }</p>
					<ExternalLink href={ `${ data.urls.fields }&utm_term=${ current.key }` }>
						{ __( 'See Flinkform Pro', 'flinkform' ) }
					</ExternalLink>
				</Modal>
			) }
		</div>
	);
}

/**
 * Collapsed "Pro" entries in the form's sidebar.
 */
export function ProPanels() {
	const data = upsellData();
	if ( ! data ) {
		return null;
	}
	const panels = [
		[ 'webhooks', __( 'Webhooks', 'flinkform' ), __( 'Send every submission to Zapier, Make, n8n or your own endpoint, with automatic retries and a delivery log.', 'flinkform' ) ],
		[ 'newsletter', __( 'Newsletter', 'flinkform' ), __( 'Add people who tick the consent box straight to Brevo, Mailchimp or CleverReach.', 'flinkform' ) ],
		[ 'smtp', __( 'SMTP sending', 'flinkform' ), __( 'Send notifications through a real mailbox (SMTP) so they reach the inbox, with a send log. A site-wide setting.', 'flinkform' ) ],
		[ 'custom-css', __( 'Custom CSS', 'flinkform' ), __( 'Style this one form with your own CSS, scoped to it.', 'flinkform' ) ],
	];
	return panels.map( ( [ key, title, text ] ) => (
		<PanelBody
			key={ key }
			className="flinkform-pro-panel"
			title={ <>{ title } <span className="flinkform-pro-badge">{ __( 'Pro', 'flinkform' ) }</span></> }
			initialOpen={ false }
		>
			<p>{ text }</p>
			<ExternalLink href={ `${ data.urls.sidebar }&utm_term=${ key }` }>
				{ __( 'See Flinkform Pro', 'flinkform' ) }
			</ExternalLink>
		</PanelBody>
	) );
}
