/**
 * Start screen of an empty form (1.15.0): pick a template or start empty.
 *
 * Shown instead of silently inserting Name/Email/Message. Choosing a
 * template replaces the (empty) inner blocks in one step, so a single
 * Undo takes the form back to this screen.
 */
import { __ } from '@wordpress/i18n';
import { Button, Placeholder } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { createBlocksFromInnerBlocksTemplate } from '@wordpress/blocks';
import { buildTemplates } from './templates';

export default function StartPicker( { clientId, setAttributes, onStartEmpty } ) {
	const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );
	const templates = buildTemplates( __ );

	const apply = ( template ) => {
		setAttributes( template.attributes );
		replaceInnerBlocks( clientId, createBlocksFromInnerBlocksTemplate( template.innerBlocks ), false );
	};

	return (
		<Placeholder
			className="flinkform-start"
			label={ __( 'Start with a template', 'flinkform' ) }
			instructions={ __( 'Every template includes the GDPR consent field. You can change everything afterwards.', 'flinkform' ) }
		>
			<ul className="flinkform-start__list">
				{ templates.map( ( t ) => (
					<li key={ t.name }>
						<Button className="flinkform-start__item" onClick={ () => apply( t ) } __next40pxDefaultSize>
							<strong>{ t.title }</strong>
							<span>{ t.description }</span>
						</Button>
					</li>
				) ) }
			</ul>
			<Button variant="link" onClick={ onStartEmpty }>
				{ __( 'Start empty', 'flinkform' ) }
			</Button>
		</Placeholder>
	);
}
