import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

import useQueryId from './hooks/useQueryId';
import useQueryPreview from './hooks/useQueryPreview';
import QuerySourcePanel from './components/QuerySourcePanel';
import TaxQueryBuilder from './components/TaxQueryBuilder';
import MetaQueryBuilder from './components/MetaQueryBuilder';
import QueryPlaceholder from './components/QueryPlaceholder';
import DateQueryBuilder from './components/DateQueryBuilder';
import AdvancedPanel from './components/AdvancedPanel';
import ResultCountBadge from './components/ResultCountBadge';
import EditorPreviewList from './components/EditorPreviewList';

// Stable reference returned when the block has no inner blocks yet. Using
// an ad-hoc `[]` literal in the selector would produce a fresh array each
// render, which trips useSelect's shallow equality check and floods the
// console with "`useSelect` returns different values" warnings.
const EMPTY_BLOCKS = Object.freeze([]);

export default function QueryEdit({
	attributes,
	setAttributes,
	clientId,
	context,
}) {
	useQueryId({ clientId, queryId: attributes.queryId, setAttributes });

	// Two narrow selectors instead of one composite object: hasInnerBlocks is
	// a primitive (always shallow-equal) and innerBlocks is the block editor's
	// own stable array reference (mutated only on genuine tree changes).
	const hasInnerBlocks = useSelect(
		(select) =>
			(select(blockEditorStore).getBlock(clientId)?.innerBlocks?.length ||
				0) > 0,
		[clientId]
	);
	const innerBlocks = useSelect(
		(select) =>
			select(blockEditorStore).getBlock(clientId)?.innerBlocks ||
			EMPTY_BLOCKS,
		[clientId]
	);

	const blockProps = useBlockProps({
		className: 'dsgo-query',
		style: {
			'--dsgo-query-columns': attributes.columns || 1,
			'--dsgo-query-columns-tablet':
				attributes.columnsTablet || attributes.columns || 1,
			'--dsgo-query-columns-mobile': attributes.columnsMobile || 1,
			'--dsgo-query-gap': attributes.columnGap || undefined,
		},
	});

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

	// Show live preview (real posts / users / terms) for all sources except
	// 'manual' (hand-picked IDs) and 'current' (context-bound single post).
	// Also skip if the queryId hasn't been assigned yet (first render tick)
	// so we don't fire a network request with an empty queryId.
	const showLivePreview =
		attributes.source !== 'manual' &&
		attributes.source !== 'current' &&
		!!attributes.queryId;

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
				<div
					className="dsgo-query__editor-header"
					contentEditable={false}
				>
					<ResultCountBadge
						totalItems={preview.totalItems}
						loading={preview.loading}
						error={preview.error}
					/>
				</div>
				<div className="dsgo-query__editor-grid">
					{showLivePreview ? (
						<EditorPreviewList
							attributes={attributes}
							innerBlocks={innerBlocks}
							innerBlocksProps={innerBlocksProps}
							context={context}
						/>
					) : (
						<>
							<div {...innerBlocksProps} />
							{Array.from(
								// perPage - 1 ghost tiles (the real prototype fills
								// slot #1) so the editor preview matches the total
								// item count the frontend will render. Capped at 23
								// (24 cells total) to keep the editor DOM cheap when
								// someone sets perPage to 48.
								{
									length: Math.max(
										0,
										Math.min(
											23,
											(attributes.perPage || 6) - 1
										)
									),
								},
								(_, i) => (
									<div
										key={i}
										className="dsgo-query__ghost-item"
										aria-hidden="true"
										contentEditable={false}
									>
										<div className="dsgo-query__ghost-image" />
										<div className="dsgo-query__ghost-line dsgo-query__ghost-line--title" />
										<div className="dsgo-query__ghost-line" />
										<div className="dsgo-query__ghost-line dsgo-query__ghost-line--short" />
									</div>
								)
							)}
						</>
					)}
				</div>
			</div>
		</>
	);
}
