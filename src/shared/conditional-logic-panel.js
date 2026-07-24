/**
 * Shared "Conditional Logic" inspector panel.
 *
 * Mounted by every field block + the page-break block + (in Slice 7d)
 * the form-container's submit-condition slot. Reads the block's
 * `conditionalLogic` attribute and writes back through `setAttributes`.
 *
 * Layout: single rule group, ALL/ANY toggle, repeatable rule rows.
 * Each row picks a sibling field name, an operator, and (for value-
 * based operators) a comparison value. Empty-state operators
 * (is_empty / is_not_empty) hide the value input because it would
 * be meaningless.
 *
 * Sibling-field list is computed via `useSelect` against the parent
 * form-container's inner blocks — same pattern page-break/edit.js uses
 * to derive its step number. Fields without a `fieldName` attribute
 * (page-breaks, section-headings) are filtered out: nothing to condition
 * against. The current block itself is filtered out too — it'd be
 * a paradox to gate a field's visibility on its own value.
 *
 * Shape of the persisted attribute:
 *
 *   {
 *     enabled: true,
 *     logic: 'all' | 'any',
 *     rules: [
 *       { field: 'email', operator: 'contains', value: '@dbw-media.de' },
 *       { field: 'role',  operator: 'is',       value: 'admin' },
 *     ],
 *   }
 *
 * @package Flinkform
 * @since 0.1.0
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import {
	Button,
	Notice,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';

const DEFAULT_RULE_SET = {
	enabled: false,
	logic: 'all',
	rules: [],
};

const BLANK_RULE = {
	field: '',
	operator: 'is',
	value: '',
};

const EMPTY_STATE_OPERATORS = new Set( [ 'is_empty', 'is_not_empty' ] );

/**
 * @param {object}   props
 * @param {object}   props.attributes
 * @param {Function} props.setAttributes
 * @param {string}   props.clientId       — current block's clientId.
 * @param {string}   [props.attributeName='conditionalLogic'] — block attribute key the panel reads/writes.
 * @param {string}   [props.fieldSource='siblings']           — `'siblings'` walks the parent's inner blocks (field + page-break case); `'inner'` walks the block's own inner blocks (form-container submit-condition case).
 * @param {string}   [props.title]        — panel title, defaults to "Conditional Logic".
 * @param {string}   [props.toggleLabel]  — main toggle label, defaults to "Enable conditional logic".
 * @param {string}   [props.toggleHelp]   — help text under the main toggle.
 */
export default function ConditionalLogicPanel( {
	attributes,
	setAttributes,
	clientId,
	attributeName = 'conditionalLogic',
	fieldSource = 'siblings',
	title,
	toggleLabel,
	toggleHelp,
} ) {
	const ruleSet = normaliseRuleSet( attributes[ attributeName ] );

	// Field list source: `siblings` for field + page-break blocks
	// (walk the parent form's inner blocks, filter to fields,
	// filter out self). `inner` for the form-container itself
	// (walk our own inner blocks, no self-filter needed because
	// the form-container isn't a submittable field).
	const fieldList = useSelect(
		( select ) => {
			const { getBlockRootClientId, getBlocks } = select( 'core/block-editor' );

			const scopeClientId = fieldSource === 'inner'
				? clientId
				: getBlockRootClientId( clientId );

			if ( ! scopeClientId ) {
				return [];
			}
			const blocks = getBlocks( scopeClientId );
			return blocks
				.filter( ( b ) => b.clientId !== clientId )
				.filter( ( b ) => typeof b.attributes?.fieldName === 'string' && b.attributes.fieldName !== '' )
				.map( ( b ) => ( {
					name: String( b.attributes.fieldName ),
					label: typeof b.attributes?.label === 'string' ? b.attributes.label : '',
				} ) );
		},
		[ clientId, fieldSource ]
	);
	const siblingFields = fieldList;

	const fieldOptions = useMemo(
		() => [
			{ value: '', label: __( '— Select a field —', 'flinkform' ) },
			...siblingFields.map( ( f ) => ( {
				value: f.name,
				label: f.label ? `${ f.label } (${ f.name })` : f.name,
			} ) ),
		],
		[ siblingFields ]
	);

	const update = ( patch ) => {
		setAttributes( {
			[ attributeName ]: {
				...ruleSet,
				...patch,
			},
		} );
	};

	const updateRule = ( index, patch ) => {
		const next = ruleSet.rules.map( ( rule, i ) =>
			i === index ? { ...rule, ...patch } : rule
		);
		update( { rules: next } );
	};

	const addRule = () => {
		update( {
			rules: [
				...ruleSet.rules,
				{
					...BLANK_RULE,
					field: siblingFields[ 0 ]?.name ?? '',
				},
			],
		} );
	};

	const removeRule = ( index ) => {
		const next = ruleSet.rules.filter( ( _, i ) => i !== index );
		update( { rules: next } );
	};

	const toggleEnabled = ( on ) => {
		if ( ! on ) {
			setAttributes( { [ attributeName ]: { ...DEFAULT_RULE_SET } } );
			return;
		}
		setAttributes( {
			[ attributeName ]: {
				enabled: true,
				logic: 'all',
				rules: ruleSet.rules.length > 0
					? ruleSet.rules
					: [
						{ ...BLANK_RULE, field: siblingFields[ 0 ]?.name ?? '' },
					],
			},
		} );
	};

	return (
		<PanelBody
			title={ title ?? __( 'Conditional Logic', 'flinkform' ) }
			initialOpen={ false }
		>
			<ToggleControl
				label={ toggleLabel ?? __( 'Enable conditional logic', 'flinkform' ) }
				help={ toggleHelp ?? __( 'Show this only when the rules below match. Hidden fields are excluded from the submission, both client- and server-side.', 'flinkform' ) }
				checked={ ruleSet.enabled }
				onChange={ toggleEnabled }
				__nextHasNoMarginBottom
			/>

			{ ruleSet.enabled && (
				<>
					{ siblingFields.length === 0 && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Add at least one other field block to the form to use conditional logic.', 'flinkform' ) }
						</Notice>
					) }

					<ToggleGroupControl
						label={ __( 'Match', 'flinkform' ) }
						value={ ruleSet.logic }
						onChange={ ( value ) => update( { logic: value === 'any' ? 'any' : 'all' } ) }
						isBlock
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					>
						<ToggleGroupControlOption value="all" label={ __( 'ALL', 'flinkform' ) } />
						<ToggleGroupControlOption value="any" label={ __( 'ANY', 'flinkform' ) } />
					</ToggleGroupControl>

					{ ruleSet.rules.map( ( rule, index ) => (
						<RuleRow
							key={ index }
							rule={ rule }
							fieldOptions={ fieldOptions }
							onChange={ ( patch ) => updateRule( index, patch ) }
							onRemove={ () => removeRule( index ) }
						/>
					) ) }

					<Button
						variant="secondary"
						onClick={ addRule }
						__next40pxDefaultSize
						style={ { marginTop: '8px' } }
						disabled={ siblingFields.length === 0 }
					>
						{ __( '+ Add rule', 'flinkform' ) }
					</Button>
				</>
			) }
		</PanelBody>
	);
}

/**
 * Single rule editor row — field selector, operator selector, and
 * (when the operator takes a value) a value input. Also a remove
 * button so empty rules don't pile up.
 */
function RuleRow( { rule, fieldOptions, onChange, onRemove } ) {
	const operatorOptions = [
		{ value: 'is', label: __( 'is', 'flinkform' ) },
		{ value: 'is_not', label: __( 'is not', 'flinkform' ) },
		{ value: 'contains', label: __( 'contains', 'flinkform' ) },
		{ value: 'not_contains', label: __( 'does not contain', 'flinkform' ) },
		{ value: 'is_empty', label: __( 'is empty', 'flinkform' ) },
		{ value: 'is_not_empty', label: __( 'is not empty', 'flinkform' ) },
		{ value: 'greater_than', label: __( 'greater than', 'flinkform' ) },
		{ value: 'less_than', label: __( 'less than', 'flinkform' ) },
		{ value: 'date_before', label: __( 'is before (date)', 'flinkform' ) },
		{ value: 'date_on_or_after', label: __( 'is on or after (date)', 'flinkform' ) },
	];

	const usesValue = ! EMPTY_STATE_OPERATORS.has( rule.operator );

	return (
		<div
			style={ {
				border: '1px solid #ddd',
				borderRadius: '4px',
				padding: '8px',
				marginTop: '8px',
			} }
		>
			<SelectControl
				label={ __( 'Field', 'flinkform' ) }
				value={ rule.field ?? '' }
				options={ fieldOptions }
				onChange={ ( value ) => onChange( { field: value } ) }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>

			<SelectControl
				label={ __( 'Operator', 'flinkform' ) }
				value={ rule.operator ?? 'is' }
				options={ operatorOptions }
				onChange={ ( value ) => {
					const patch = { operator: value };
					// Clear the value when switching to an empty-state
					// operator so the persisted rule doesn't carry a
					// stale comparison around.
					if ( EMPTY_STATE_OPERATORS.has( value ) ) {
						patch.value = '';
					}
					onChange( patch );
				} }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>

			{ usesValue && (
				<TextControl
					label={ __( 'Value', 'flinkform' ) }
					value={ rule.value ?? '' }
					onChange={ ( value ) => onChange( { value } ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			) }

			<div style={ { marginTop: '6px', textAlign: 'right' } }>
				<Button
					variant="link"
					isDestructive
					onClick={ onRemove }
					style={ { padding: 0 } }
				>
					{ __( 'Remove rule', 'flinkform' ) }
				</Button>
			</div>
		</div>
	);
}

/**
 * Defensive default for blocks that don't have the attribute set yet
 * (older posts, brand-new blocks before WP populates defaults). Keeps
 * the rest of the panel from having to null-check every access.
 *
 * @param {object|undefined} ruleSet Raw attribute value.
 * @returns {{enabled: boolean, logic: string, rules: Array}}
 */
function normaliseRuleSet( ruleSet ) {
	if ( ! ruleSet || typeof ruleSet !== 'object' ) {
		return { ...DEFAULT_RULE_SET };
	}
	return {
		enabled: !! ruleSet.enabled,
		logic: ruleSet.logic === 'any' ? 'any' : 'all',
		rules: Array.isArray( ruleSet.rules ) ? ruleSet.rules : [],
	};
}
