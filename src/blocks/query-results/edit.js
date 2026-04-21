import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	Placeholder,
} from '@wordpress/components';

import EditorPreviewList from '../query/components/EditorPreviewList';
import { RESULT_TEMPLATE } from '../query/edit-template';

const EMPTY_BLOCKS = Object.freeze([]);

/**
 * Walk up the block tree to find the enclosing designsetgo/query parent and
 * return its attributes. The query-results block depends on the parent for
 * source / postType / perPage / taxQuery / etc. — everything needed by the
 * REST render endpoint that powers the editor preview.
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
		className: 'dsgo-query-results',
		style: {
			'--dsgo-query-columns': attributes.columns || 1,
			'--dsgo-query-columns-tablet':
				attributes.columnsTablet || attributes.columns || 1,
			'--dsgo-query-columns-mobile': attributes.columnsMobile || 1,
			'--dsgo-query-gap': attributes.columnGap || undefined,
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
		columnGap: attributes.columnGap,
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
				<PanelBody title={__('Layout', 'designsetgo')} initialOpen>
					<RangeControl
						label={__('Columns', 'designsetgo')}
						value={attributes.columns || 1}
						min={1}
						max={6}
						onChange={(v) => setAttributes({ columns: v })}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<RangeControl
						label={__('Columns (tablet)', 'designsetgo')}
						help={__(
							'0 inherits the desktop column count.',
							'designsetgo'
						)}
						value={attributes.columnsTablet || 0}
						min={0}
						max={6}
						onChange={(v) => setAttributes({ columnsTablet: v })}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<RangeControl
						label={__('Columns (mobile)', 'designsetgo')}
						value={attributes.columnsMobile || 1}
						min={1}
						max={3}
						onChange={(v) => setAttributes({ columnsMobile: v })}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={__('Column gap', 'designsetgo')}
						help={__(
							'e.g. 1.5rem, 24px. Leave blank for theme default.',
							'designsetgo'
						)}
						value={attributes.columnGap || ''}
						onChange={(v) => setAttributes({ columnGap: v })}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<SelectControl
						label={__('List tag', 'designsetgo')}
						value={attributes.tagName || 'ul'}
						options={[
							{ label: 'ul', value: 'ul' },
							{ label: 'ol', value: 'ol' },
							{ label: 'div', value: 'div' },
						]}
						onChange={(v) => setAttributes({ tagName: v })}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<SelectControl
						label={__('Item tag', 'designsetgo')}
						value={attributes.itemTagName || 'li'}
						options={[
							{ label: 'li', value: 'li' },
							{ label: 'div', value: 'div' },
							{ label: 'article', value: 'article' },
						]}
						onChange={(v) => setAttributes({ itemTagName: v })}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
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
