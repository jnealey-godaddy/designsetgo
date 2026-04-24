import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import { Placeholder } from '@wordpress/components';

import EditorPreviewList from '../query/components/EditorPreviewList';
import { RESULT_TEMPLATE } from '../query/edit-template';
import ResultsLayoutControls from '../query/components/ResultsLayoutControls';

const EMPTY_BLOCKS = Object.freeze([]);

/**
 * Walk up the block tree to find the enclosing designsetgo/query parent and
 * return its attributes. The query-results block depends on the parent for
 * source / postType / perPage / taxQuery / etc. — everything needed by the
 * REST render endpoint that powers the editor preview.
 * @param {string} clientId The block clientId to walk up from.
 */
function useParentQueryAttributes(clientId) {
	return useSelect(
		(select) => {
			const { getBlockParents, getBlock } = select(blockEditorStore);
			const parents = getBlockParents(clientId);
			for (const parentId of parents) {
				const parent = getBlock(parentId);
				if (parent?.name === 'designsetgo/query') {
					return parent.attributes;
				}
			}
			return null;
		},
		[clientId]
	);
}

export default function QueryResultsEdit({
	attributes,
	setAttributes,
	clientId,
	context,
}) {
	const parentAttrs = useParentQueryAttributes(clientId);

	const taxonomyOptions = useSelect((select) => {
		const taxes = select(coreStore).getTaxonomies({ per_page: -1 }) || [];
		return taxes
			.filter((t) => t.show_in_rest !== false)
			.map((t) => ({
				value: t.slug,
				label: t.labels?.singular_name || t.name || t.slug,
			}));
	}, []);

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

	const variantClass = attributes.layoutVariant
		? `is-layout-${attributes.layoutVariant}`
		: '';
	const blockProps = useBlockProps({
		className: `dsgo-query-results ${variantClass}`.trim(),
		style: {
			'--dsgo-query-columns': attributes.columns || 1,
			'--dsgo-query-columns-tablet':
				attributes.columnsTablet || attributes.columns || 1,
			'--dsgo-query-columns-mobile': attributes.columnsMobile || 1,
			'--dsgo-query-first-col-span': attributes.firstItemColumnSpan || 1,
			'--dsgo-query-first-row-span': attributes.firstItemRowSpan || 1,
		},
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'dsgo-query-results__inner' },
		{ template: RESULT_TEMPLATE, templateLock: false }
	);

	if (!parentAttrs) {
		return (
			<div {...blockProps}>
				<Placeholder
					label={__('Query Results', 'designsetgo')}
					instructions={__(
						'This block must be placed inside a Dynamic Query block.',
						'designsetgo'
					)}
				/>
			</div>
		);
	}

	const source = parentAttrs.source || 'posts';
	const queryId = parentAttrs.queryId || '';

	// Build the "effective" attributes the preview endpoint needs — the parent
	// block owns query config (source, perPage, filters) while this block
	// owns presentation (columns, groupBy, tagNames).
	const effectiveAttributes = {
		...parentAttrs,
		tagName: attributes.tagName,
		itemTagName: attributes.itemTagName,
		columns: attributes.columns,
		columnsTablet: attributes.columnsTablet,
		columnsMobile: attributes.columnsMobile,
		firstItemColumnSpan: attributes.firstItemColumnSpan,
		firstItemRowSpan: attributes.firstItemRowSpan,
		groupBy: attributes.groupBy,
	};

	// Manual / current sources can't show a live preview — the first skips the
	// live query altogether (hand-picked IDs) and the second binds to the
	// current post context which doesn't exist in the block-editor canvas.
	const showLivePreview =
		source !== 'manual' && source !== 'current' && !!queryId;

	return (
		<>
			<InspectorControls>
				<ResultsLayoutControls
					attributes={attributes}
					set={setAttributes}
					panelId={clientId}
					taxonomyOptions={taxonomyOptions}
				/>
			</InspectorControls>
			<div
				{...blockProps}
				onClickCapture={(event) => {
					// Prevent navigation when authors click post-title, featured-image,
					// or any bound link inside the editor preview. Real anchors land
					// here from server-rendered readonly items and from core blocks
					// like core/post-title{isLink:true}. Scope to real hrefs so we
					// don't interfere with in-editor anchor tooling.
					const anchor = event.target.closest?.('a[href]');
					if (anchor) {
						event.preventDefault();
					}
				}}
			>
				<div className="dsgo-query-results__editor-grid">
					{showLivePreview && hasInnerBlocks ? (
						<EditorPreviewList
							attributes={effectiveAttributes}
							innerBlocks={innerBlocks}
							innerBlocksProps={innerBlocksProps}
							context={context}
						/>
					) : (
						<>
							<div {...innerBlocksProps} />
							{Array.from(
								{
									length: Math.max(
										0,
										Math.min(
											23,
											(parentAttrs.perPage || 6) - 1
										)
									),
								},
								(_, i) => (
									<div
										key={i}
										className="dsgo-query-results__ghost-item"
										aria-hidden="true"
										contentEditable={false}
									>
										<div className="dsgo-query-results__ghost-image" />
										<div className="dsgo-query-results__ghost-line dsgo-query-results__ghost-line--title" />
										<div className="dsgo-query-results__ghost-line" />
										<div className="dsgo-query-results__ghost-line dsgo-query-results__ghost-line--short" />
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
