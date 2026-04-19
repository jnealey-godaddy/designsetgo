import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

export default function QueryEdit() {
	const blockProps = useBlockProps( { className: 'dsgo-query' } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		// Real template + templateLock arrive in Task 12.
	} );

	return (
		<div { ...innerBlocksProps }>
			<Placeholder
				icon="editor-table"
				label={ __( 'Dynamic Query', 'designsetgo' ) }
				instructions={ __(
					'Configure this block\'s query in the inspector. Default template will be added in Task 8/12.',
					'designsetgo'
				) }
			/>
		</div>
	);
}
