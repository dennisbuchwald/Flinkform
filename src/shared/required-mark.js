/**
 * Editor twin of Fields\LabelMarks (PHP): "*" for required fields, and
 * "(optional)" for the rest when the form asks for it (1.15.0).
 */
import { __ } from '@wordpress/i18n';

export default function RequiredMark( { required, context } ) {
	if ( required ) {
		return <span className="flinkform-field__required" aria-hidden="true"> *</span>;
	}
	const appearance = ( context && context[ 'flinkform/appearance' ] ) || {};
	if ( ! appearance.markOptional ) {
		return null;
	}
	return <span className="flinkform-field__optional"> { __( '(optional)', 'flinkform' ) }</span>;
}
