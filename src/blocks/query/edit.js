import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

import useQueryId from './hooks/useQueryId';
import useQueryPreview from './hooks/useQueryPreview';
import AdvancedPanel from './components/AdvancedPanel';
import DateQueryBuilder from './components/DateQueryBuilder';
import MetaQueryBuilder from './components/MetaQueryBuilder';
import QueryPlaceholder from './components/QueryPlaceholder';
import QuerySourcePanel from './components/QuerySourcePanel';
import ResultCountBadge from './components/ResultCountBadge';
import TaxQueryBuilder from './components/TaxQueryBuilder';

/**
 * Dynamic Query container.
 *
 * Post-restructure (v2.6) this block is a pure container: it owns query
 * configuration (source, perPage, taxQuery, etc.) and exposes queryId via
 * context. Actual item rendering lives in the child designsetgo/query-results
 * block. Filters, pagination, and no-results render in-place as siblings of
 * query-results in whatever tree position the author chooses.
 */
export default function QueryEdit({ attributes, setAttributes, clientId }) {
	useQueryId({ clientId, queryId: attributes.queryId, setAttributes });

	const hasInnerBlocks = useSelect(
		(select) =>
			(select(blockEditorStore).getBlock(clientId)?.innerBlocks?.length ||
				0) > 0,
		[clientId]
	);

	const blockProps = useBlockProps({ className: 'dsgo-query' });

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'dsgo-query__inner' },
		{ templateLock: false }
	);

	const preview = useQueryPreview({
		attributes,
		queryId: attributes.queryId,
	});

	if (!hasInnerBlocks) {
		return (
			<div {...blockProps}>
				<QueryPlaceholder
					clientId={clientId}
					setAttributes={setAttributes}
				/>
			</div>
		);
	}

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
						<DateQueryBuilder
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

			<div {...blockProps}>
				<div className="dsgo-query__editor-header" contentEditable={false}>
					<ResultCountBadge
						totalItems={preview.totalItems}
						loading={preview.loading}
						error={preview.error}
					/>
				</div>
				<div {...innerBlocksProps} />
			</div>
		</>
	);
}
