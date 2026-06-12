/**
 * Shared option editor for select, radio and checkbox-group fields.
 *
 * Renders a compact UI for managing a list of {label, value} options:
 * label + value text inputs side by side, plus add/remove/reorder controls.
 * Auto-derives `value` from `label` on first edit so a user who doesn't
 * care about machine-readable values never has to touch the value column.
 */
import { __ } from '@wordpress/i18n';
import { Button, TextControl } from '@wordpress/components';

function slugify( text ) {
	return String( text )
		.toLowerCase()
		.replace( /[äöüß]/g, ( c ) => ( { ä: 'ae', ö: 'oe', ü: 'ue', ß: 'ss' } )[ c ] || c )
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' )
		.slice( 0, 32 );
}

export function OptionsEditor( { options, onChange } ) {
	const safeOptions = Array.isArray( options ) ? options : [];

	const update = ( index, patch ) => {
		const next = safeOptions.map( ( opt, i ) => ( i === index ? { ...opt, ...patch } : opt ) );
		onChange( next );
	};

	const updateLabel = ( index, label ) => {
		const current = safeOptions[ index ] || {};
		const valueWasAuto = ! current.value || current.value === slugify( current.label || '' );
		const patch = { label };
		if ( valueWasAuto ) {
			patch.value = slugify( label ) || `option-${ index + 1 }`;
		}
		update( index, patch );
	};

	const add = () => {
		const next = [ ...safeOptions, { label: '', value: '' } ];
		onChange( next );
	};

	const remove = ( index ) => {
		const next = safeOptions.filter( ( _, i ) => i !== index );
		onChange( next );
	};

	const move = ( index, direction ) => {
		const target = index + direction;
		if ( target < 0 || target >= safeOptions.length ) {
			return;
		}
		const next = [ ...safeOptions ];
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		onChange( next );
	};

	return (
		<div className="flinkform-options-editor">
			{ safeOptions.length === 0 && (
				<p style={ { fontSize: '12px', opacity: 0.7 } }>
					{ __( 'No options yet — add the first one below.', 'flinkform' ) }
				</p>
			) }

			{ safeOptions.map( ( opt, index ) => (
				<div
					key={ index }
					style={ {
						display: 'grid',
						gridTemplateColumns: '1fr 1fr auto',
						gap: '6px',
						alignItems: 'end',
						marginBottom: '8px',
					} }
				>
					<TextControl
						label={ index === 0 ? __( 'Label', 'flinkform' ) : undefined }
						value={ opt.label || '' }
						onChange={ ( v ) => updateLabel( index, v ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ index === 0 ? __( 'Value', 'flinkform' ) : undefined }
						value={ opt.value || '' }
						onChange={ ( v ) => update( index, { value: v } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<div style={ { display: 'flex', gap: '2px' } }>
						<Button
							size="small"
							icon="arrow-up-alt2"
							label={ __( 'Move up', 'flinkform' ) }
							onClick={ () => move( index, -1 ) }
							disabled={ index === 0 }
						/>
						<Button
							size="small"
							icon="arrow-down-alt2"
							label={ __( 'Move down', 'flinkform' ) }
							onClick={ () => move( index, 1 ) }
							disabled={ index === safeOptions.length - 1 }
						/>
						<Button
							size="small"
							icon="trash"
							isDestructive
							label={ __( 'Remove option', 'flinkform' ) }
							onClick={ () => remove( index ) }
						/>
					</div>
				</div>
			) ) }

			<Button variant="secondary" size="small" onClick={ add }>
				{ __( '+ Add option', 'flinkform' ) }
			</Button>
		</div>
	);
}
