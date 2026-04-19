import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps, InspectorControls } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

import useQueryId from './hooks/useQueryId';
import QuerySourcePanel from './components/QuerySourcePanel';

export default function QueryEdit({ attributes, setAttributes, clientId }) {
	const blockProps = useBlockProps( { className: 'dsgo-query' } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		// Real template + templateLock arrive in Task 12.
	} );

	useQueryId( {
		clientId,
		queryId: attributes.queryId,
		setAttributes,
	} );

	return (
		<>
			<InspectorControls>
				<QuerySourcePanel
					attributes={ attributes }
					setAttributes={ setAttributes }
					clientId={ clientId }
				/>
			</InspectorControls>
			<div { ...innerBlocksProps }>
				<Placeholder
					icon="editor-table"
					label={ __( 'Dynamic Query', 'designsetgo' ) }
					instructions={ __(
						'Configure this block\'s query in the inspector. Default template will be added in Task 12.',
						'designsetgo'
					) }
				/>
			</div>
		</>
	);
}
