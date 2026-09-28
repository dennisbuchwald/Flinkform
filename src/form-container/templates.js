/**
 * Starter templates for a new form (1.15.0).
 *
 * A freshly inserted form offers these instead of silently filling in
 * Name/Email/Message. Every template ends in the GDPR consent field, so the
 * two-minute promise includes the part people forget.
 *
 * `__` is passed in (not imported) so tests/form-templates.mjs can check the
 * structure in plain Node without the WordPress packages.
 *
 * Field names are set on purpose (name, email, …) instead of the random
 * `text_ab12cd` a field gets on its own: they show up in merge tags
 * ({field:email}) and in webhooks, where readable beats random.
 *
 * @param {Function} __ Translation function.
 * @returns {Array<{name: string, title: string, description: string, attributes: Object, innerBlocks: Array}>}
 */
export function buildTemplates( __ ) {
	const opt = ( label, value ) => ( { label, value } );
	const consent = ( text ) => [
		'flinkform/field-consent',
		text ? { fieldName: 'consent', consentText: text } : { fieldName: 'consent' },
	];
	const name = [ 'flinkform/field-text', { fieldName: 'name', label: __( 'Name', 'flinkform' ), required: true, autocomplete: 'name' } ];
	const email = [ 'flinkform/field-email', { fieldName: 'email', label: __( 'Email', 'flinkform' ), required: true } ];

	return [
		{
			name: 'contact',
			title: __( 'Contact', 'flinkform' ),
			description: __( 'Name, email, message.', 'flinkform' ),
			attributes: { title: __( 'Contact form', 'flinkform' ) },
			innerBlocks: [
				name,
				email,
				[ 'flinkform/field-textarea', { fieldName: 'message', label: __( 'Message', 'flinkform' ), required: true } ],
				consent(),
			],
		},
		{
			name: 'callback',
			title: __( 'Callback request', 'flinkform' ),
			description: __( 'Name, phone number and the best time to call.', 'flinkform' ),
			attributes: { title: __( 'Callback request', 'flinkform' ), submitLabel: __( 'Request a callback', 'flinkform' ) },
			innerBlocks: [
				name,
				[ 'flinkform/field-phone', { fieldName: 'phone', label: __( 'Phone', 'flinkform' ), required: true } ],
				[ 'flinkform/field-radio', {
					fieldName: 'best_time',
					label: __( 'Best time to call', 'flinkform' ),
					display: 'buttons',
					options: [
						opt( __( 'Morning', 'flinkform' ), 'morning' ),
						opt( __( 'Afternoon', 'flinkform' ), 'afternoon' ),
						opt( __( 'Evening', 'flinkform' ), 'evening' ),
					],
				} ],
				[ 'flinkform/field-textarea', { fieldName: 'topic', label: __( 'What is it about?', 'flinkform' ) } ],
				consent(),
			],
		},
		{
			name: 'project',
			title: __( 'Project inquiry (3 steps)', 'flinkform' ),
			description: __( 'Project, details, contact. Short steps convert better than one long form.', 'flinkform' ),
			attributes: { title: __( 'Project inquiry', 'flinkform' ), submitLabel: __( 'Send inquiry', 'flinkform' ) },
			innerBlocks: [
				[ 'flinkform/field-radio', {
					fieldName: 'project_type',
					label: __( 'What are you planning?', 'flinkform' ),
					required: true,
					display: 'buttons',
					options: [
						opt( __( 'New website', 'flinkform' ), 'new-website' ),
						opt( __( 'Relaunch', 'flinkform' ), 'relaunch' ),
						opt( __( 'Online shop', 'flinkform' ), 'shop' ),
						opt( __( 'Something else', 'flinkform' ), 'other' ),
					],
				} ],
				[ 'flinkform/field-select', {
					fieldName: 'budget',
					label: __( 'Budget', 'flinkform' ),
					options: [
						opt( __( 'Not sure yet', 'flinkform' ), 'unsure' ),
						opt( __( 'Up to 5,000', 'flinkform' ), 'up-to-5000' ),
						opt( __( '5,000 to 15,000', 'flinkform' ), '5000-15000' ),
						opt( __( 'More than 15,000', 'flinkform' ), 'over-15000' ),
					],
				} ],
				[ 'flinkform/page-break', { label: __( 'Details', 'flinkform' ) } ],
				[ 'flinkform/field-textarea', { fieldName: 'description', label: __( 'Tell us about the project', 'flinkform' ), required: true } ],
				[ 'flinkform/field-url', { fieldName: 'website', label: __( 'Current website (if any)', 'flinkform' ) } ],
				[ 'flinkform/page-break', { label: __( 'Contact', 'flinkform' ) } ],
				name,
				[ 'flinkform/field-text', { fieldName: 'company', label: __( 'Company', 'flinkform' ), autocomplete: 'organization' } ],
				email,
				[ 'flinkform/field-phone', { fieldName: 'phone', label: __( 'Phone', 'flinkform' ) } ],
				consent(),
			],
		},
		{
			name: 'appointment',
			title: __( 'Appointment request', 'flinkform' ),
			description: __( 'Preferred date and time of day, plus contact details.', 'flinkform' ),
			attributes: { title: __( 'Appointment request', 'flinkform' ), submitLabel: __( 'Request appointment', 'flinkform' ) },
			innerBlocks: [
				name,
				email,
				[ 'flinkform/field-phone', { fieldName: 'phone', label: __( 'Phone', 'flinkform' ) } ],
				[ 'flinkform/field-date', { fieldName: 'date', label: __( 'Preferred date', 'flinkform' ), required: true } ],
				[ 'flinkform/field-radio', {
					fieldName: 'time_of_day',
					label: __( 'Time of day', 'flinkform' ),
					display: 'buttons',
					options: [
						opt( __( 'Morning', 'flinkform' ), 'morning' ),
						opt( __( 'Afternoon', 'flinkform' ), 'afternoon' ),
					],
				} ],
				[ 'flinkform/field-textarea', { fieldName: 'message', label: __( 'Anything we should know?', 'flinkform' ) } ],
				consent(),
			],
		},
		{
			name: 'newsletter',
			title: __( 'Newsletter sign-up', 'flinkform' ),
			description: __( 'First name and email with an explicit opt-in.', 'flinkform' ),
			attributes: { title: __( 'Newsletter sign-up', 'flinkform' ), submitLabel: __( 'Subscribe', 'flinkform' ) },
			innerBlocks: [
				[ 'flinkform/field-text', { fieldName: 'first_name', label: __( 'First name', 'flinkform' ), autocomplete: 'given-name' } ],
				email,
				consent( __( 'Yes, I would like to receive the newsletter. I can unsubscribe at any time. Details in the {privacy_policy}.', 'flinkform' ) ),
			],
		},
	];
}
