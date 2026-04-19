import { __ } from '@wordpress/i18n';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

export default function QueryEdit() {
	const blockProps = useBlockProps( { className: 'dsgo-query' } );
	return (
		<div { ...blockProps }>
			<Placeholder
				icon="editor-table"
				label={ __( 'Dynamic Query', 'designsetgo' ) }
				instructions={ __( 'Configure this block\'s query in the inspector. Default template will be added by Task 8.', 'designsetgo' ) }
			/>
			<InnerBlocks />
		</div>
	);
}
