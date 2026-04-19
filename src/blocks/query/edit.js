import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps, InspectorControls, store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
import { createBlock } from '@wordpress/blocks';

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

	// Commit the default template as real inner blocks on first insert so the
	// frontend has something to render. `useInnerBlocksProps({ template })`
	// only shows a visual preview; it does NOT persist to post_content, so a
	// user who inserted the block and published without interacting would
	// save `<!-- wp:designsetgo/query {} /-->` and see empty <li>s live.
	// The ref guards against React 18 StrictMode's intentional double-invoke
	// of effects on mount, which would otherwise seed the template twice.
	const { insertBlocks } = useDispatch(blockEditorStore);
	const seededRef = useRef(false);
	useEffect(() => {
		if (hasInnerBlocks || seededRef.current) {
			return;
		}
		seededRef.current = true;
		const seeded = DEFAULT_TEMPLATE.map(([name, attrs]) =>
			createBlock(name, attrs || {})
		);
		insertBlocks(seeded, 0, clientId, false);
	}, [hasInnerBlocks, clientId, insertBlocks]);

	const blockProps = useBlockProps({
		className: 'dsgo-query',
		style: {
			'--dsgo-query-columns': attributes.columns || 1,
			'--dsgo-query-columns-tablet':
				attributes.columnsTablet || attributes.columns || 1,
			'--dsgo-query-columns-mobile':
				attributes.columnsMobile || 1,
			'--dsgo-query-gap': attributes.columnGap || undefined,
		},
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'dsgo-query__inner' },
		{
			// `template` alone is unreliable for persistence on dynamic blocks —
			// Gutenberg treats it as a visual hint only. The useEffect above is
			// the authoritative seeder; the template here just ensures the
			// initial editor render shows the structure before the effect fires.
			template: hasInnerBlocks ? undefined : DEFAULT_TEMPLATE,
			templateLock: false,
		}
	);

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

			<div {...blockProps}>
				<div className="dsgo-query__editor-header" contentEditable={false}>
					<ResultCountBadge
						totalItems={preview.totalItems}
						loading={preview.loading}
						error={preview.error}
					/>
				</div>
				<div className="dsgo-query__editor-grid">
					<div {...innerBlocksProps} />
					{Array.from(
						{ length: Math.max(0, (attributes.columns || 1) - 1) },
						(_, i) => (
							<div
								key={ i }
								className="dsgo-query__ghost-item"
								aria-hidden="true"
								contentEditable={ false }
							>
								<div className="dsgo-query__ghost-image" />
								<div className="dsgo-query__ghost-line dsgo-query__ghost-line--title" />
								<div className="dsgo-query__ghost-line" />
								<div className="dsgo-query__ghost-line dsgo-query__ghost-line--short" />
							</div>
						)
					)}
				</div>
			</div>
		</>
	);
}
