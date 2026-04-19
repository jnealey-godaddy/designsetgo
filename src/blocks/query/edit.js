import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps, InspectorControls, store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

import useQueryId from './hooks/useQueryId';
import useQueryPreview from './hooks/useQueryPreview';
import QuerySourcePanel from './components/QuerySourcePanel';
import TaxQueryBuilder from './components/TaxQueryBuilder';
import MetaQueryBuilder from './components/MetaQueryBuilder';
import AdvancedPanel from './components/AdvancedPanel';
import ResultCountBadge from './components/ResultCountBadge';
import { DEFAULT_TEMPLATE } from './edit-template';

export default function QueryEdit({ attributes, setAttributes, clientId }) {
	useQueryId({ clientId, queryId: attributes.queryId, setAttributes });

	const hasInnerBlocks = useSelect(
		(select) => (select(blockEditorStore).getBlock(clientId)?.innerBlocks?.length || 0) > 0,
		[clientId]
	);

	const blockProps = useBlockProps({ className: 'dsgo-query' });
	const innerBlocksProps = useInnerBlocksProps(blockProps, {
		template: hasInnerBlocks ? undefined : DEFAULT_TEMPLATE,
		templateLock: false,
	});

	const preview = useQueryPreview({ attributes, queryId: attributes.queryId });

	const showPostsOnlyPanels = attributes.source === 'posts';

	return (
		<>
			<InspectorControls>
				<QuerySourcePanel
					attributes={attributes}
					setAttributes={setAttributes}
					clientId={clientId}
				/>
				{showPostsOnlyPanels && (
					<>
						<TaxQueryBuilder
							attributes={attributes}
							setAttributes={setAttributes}
							clientId={clientId}
						/>
						<MetaQueryBuilder
							attributes={attributes}
							setAttributes={setAttributes}
							clientId={clientId}
						/>
					</>
				)}
				<AdvancedPanel
					attributes={attributes}
					setAttributes={setAttributes}
					clientId={clientId}
				/>
			</InspectorControls>

			<div {...innerBlocksProps}>
				<div className="dsgo-query__editor-header" contentEditable={false}>
					<ResultCountBadge
						totalItems={preview.totalItems}
						loading={preview.loading}
						error={preview.error}
					/>
				</div>
			</div>
		</>
	);
}
