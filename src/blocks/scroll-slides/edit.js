/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
	useSettings,
	InspectorControls,
	BlockContextProvider,
} from '@wordpress/block-editor';
import { Notice } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useMemo, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './editor.scss';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import ScrollSlidesPlaceholder from './components/ScrollSlidesPlaceholder';
import ScrollSlidesInspector from './components/ScrollSlidesInspector';
import useQueryHostPreview, {
	buildItemContext,
} from '../query/hooks/useQueryHostPreview';
import useParentQueryAttrs from '../query/hooks/useParentQueryAttrs';
import QueryHostReadOnlyItem from '../query/components/QueryHostReadOnlyItem';

const ALLOWED_BLOCKS = ['designsetgo/scroll-slide'];
const MAX_SLIDES = 10;

export default function Edit({ attributes, setAttributes, clientId, context }) {
	const {
		minHeight,
		maxHeight,
		constrainWidth,
		contentWidth,
		overlayColor,
		overlayOpacity,
		navColor,
		navActiveColor,
	} = attributes;
	const [activeSlide, setActiveSlide] = useState(0);

	// Dynamic mode: a parent designsetgo/query sets queryId in context. In
	// that mode only the first scroll-slide is used as the per-item template;
	// extras are ignored server-side. Lock add/remove at this level so the
	// editor reflects the server contract.
	const queryId =
		typeof context === 'object' && context
			? context['designsetgo/queryId'] || ''
			: '';
	const inQueryMode = !!queryId;

	const [themeContentSize] = useSettings('layout.contentSize');

	// Read inner blocks to build nav and show/hide panels
	const { innerBlocks, hasInnerBlocks } = useSelect(
		(select) => {
			const { getBlock } = select(blockEditorStore);
			const block = getBlock(clientId);
			return {
				innerBlocks: block?.innerBlocks || [],
				hasInnerBlocks: block?.innerBlocks?.length > 0,
			};
		},
		[clientId]
	);

	const { updateBlockAttributes, selectBlock } =
		useDispatch(blockEditorStore);

	// Parent query's attributes (only resolved when bound via context).
	const parentQueryAttrs = useParentQueryAttrs(clientId, inQueryMode);

	// Only the first scroll-slide is used as the template at render time.
	// Memoize to preserve the array reference across renders — useRenderedItems
	// re-serializes the template tree on every identity change.
	const templateSlideBlocks = useMemo(
		() => (innerBlocks.length > 0 ? [innerBlocks[0]] : []),
		[innerBlocks]
	);

	const preview = useQueryHostPreview({
		attributes: parentQueryAttrs,
		queryId,
		innerBlocks: templateSlideBlocks,
		enabled: inQueryMode && !!parentQueryAttrs,
	});

	// Clamp active slide to valid range. In query mode the panel count is
	// driven by the preview record count; outside of query mode by the
	// authored inner blocks count.
	const previewRecords = Array.isArray(preview?.records)
		? preview.records
		: [];
	const slideCount = inQueryMode ? previewRecords.length : innerBlocks.length;
	const clampedActive =
		slideCount > 0 ? Math.min(activeSlide, slideCount - 1) : 0;

	const blockClassName = [
		'dsgo-scroll-slides',
		overlayColor && 'dsgo-scroll-slides--has-overlay',
		!constrainWidth && 'dsgo-scroll-slides--no-width-constraint',
		(navColor || navActiveColor) && 'dsgo-scroll-slides--has-nav-color',
	]
		.filter(Boolean)
		.join(' ');

	// Compute effective height for editor preview (mirrors frontend behavior)
	const effectiveMinHeight = minHeight || '100vh';
	const editorHeight = maxHeight
		? `min(${effectiveMinHeight}, ${maxHeight})`
		: effectiveMinHeight;

	// Build background preview from the active slide's block supports.
	// On the frontend, view.js extracts per-slide backgrounds into full-width
	// crossfading layers. This mirrors that behavior in the editor.
	const editorBgStyle = {};
	if (hasInnerBlocks) {
		const activeBlock = innerBlocks[clampedActive];
		const slideAttrs = activeBlock?.attributes || {};
		const slideStyle = slideAttrs.style || {};

		// Background color: preset slug or custom hex
		if (slideStyle?.color?.background) {
			editorBgStyle.backgroundColor = slideStyle.color.background;
		} else if (slideAttrs.backgroundColor) {
			editorBgStyle.backgroundColor = `var(--wp--preset--color--${slideAttrs.backgroundColor})`;
		}

		// Gradient: custom CSS or preset slug
		if (slideStyle?.color?.gradient) {
			editorBgStyle.backgroundImage = slideStyle.color.gradient;
		} else if (slideAttrs.gradient) {
			editorBgStyle.backgroundImage = `var(--wp--preset--gradient--${slideAttrs.gradient})`;
		}

		// Background image (overrides gradient if set)
		if (slideStyle?.background?.backgroundImage?.url) {
			editorBgStyle.backgroundImage = `url(${slideStyle.background.backgroundImage.url})`;
			editorBgStyle.backgroundSize =
				slideStyle.background?.backgroundSize || 'cover';
			editorBgStyle.backgroundPosition =
				slideStyle.background?.backgroundPosition || 'center';
			editorBgStyle.backgroundRepeat =
				slideStyle.background?.backgroundRepeat || 'no-repeat';
		}
	}

	// In query mode, the active record's featured image becomes the panel
	// background — mirroring what scroll-slide/render.php injects on the
	// frontend when the template has no authored background.
	if (inQueryMode && !editorBgStyle.backgroundImage) {
		const activeRecord = previewRecords[clampedActive];
		const featuredUrl =
			activeRecord?._embedded?.['wp:featuredmedia']?.[0]?.source_url;
		if (featuredUrl) {
			editorBgStyle.backgroundImage = `url(${featuredUrl})`;
			editorBgStyle.backgroundSize = 'cover';
			editorBgStyle.backgroundPosition = 'center';
			editorBgStyle.backgroundRepeat = 'no-repeat';
		}
	}

	const hasEditorBg = Object.keys(editorBgStyle).length > 0;

	const blockProps = useBlockProps({
		className: blockClassName,
		'data-dsgo-active-slide': clampedActive,
		style: {
			minHeight: editorHeight,
			...(overlayColor && {
				'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
				'--dsgo-overlay-opacity': String(overlayOpacity / 100),
			}),
			...(navColor && {
				'--dsgo-nav-color': convertColorToCSSVar(navColor),
			}),
			...(navActiveColor && {
				'--dsgo-nav-active-color': convertColorToCSSVar(navActiveColor),
			}),
		},
	});

	const innerStyle = {};
	if (constrainWidth) {
		innerStyle.maxWidth = contentWidth || themeContentSize || '1140px';
		innerStyle.marginLeft = 'auto';
		innerStyle.marginRight = 'auto';
	}

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: inQueryMode
				? 'dsgo-scroll-slides__editor-template-slot'
				: 'dsgo-scroll-slides__editor-panels',
		},
		{
			allowedBlocks: ALLOWED_BLOCKS,
			orientation: 'vertical',
			templateLock: inQueryMode ? 'insert' : false,
			renderAppender:
				inQueryMode || innerBlocks.length >= MAX_SLIDES
					? false
					: undefined,
		}
	);

	/**
	 * Handle nav heading click — switch active slide and select child block
	 *
	 * @param {number} index Slide index
	 */
	const handleNavClick = (index) => {
		setActiveSlide(index);
		// Only select the authored child when it exists (outside query mode
		// there is one block per nav item; in query mode there is only a
		// single template block regardless of the active preview record).
		if (!inQueryMode && innerBlocks[index]) {
			selectBlock(innerBlocks[index].clientId);
		}
	};

	/**
	 * Handle inline editing of nav heading text
	 *
	 * @param {number} index Slide index
	 * @param {string} value New heading text
	 */
	const handleNavHeadingChange = (index, value) => {
		if (innerBlocks[index]) {
			updateBlockAttributes(innerBlocks[index].clientId, {
				navHeading: value,
			});
		}
	};

	// Show template chooser when block is first inserted
	if (!hasInnerBlocks) {
		return (
			<div {...blockProps}>
				<ScrollSlidesPlaceholder
					clientId={clientId}
					setAttributes={setAttributes}
				/>
			</div>
		);
	}

	return (
		<>
			{inQueryMode && (
				<InspectorControls>
					<Notice status="info" isDismissible={false}>
						{__(
							'This block is bound to a parent Dynamic Query. Only the first scroll slide is rendered for each iterated item; extras are ignored.',
							'designsetgo'
						)}
					</Notice>
					{innerBlocks.length > 1 && (
						<Notice status="warning" isDismissible={false}>
							{sprintf(
								/* translators: %d: number of slides that will not render */
								__(
									'%d extra slide(s) will be ignored at render. Only the first slide is used as the template.',
									'designsetgo'
								),
								innerBlocks.length - 1
							)}
						</Notice>
					)}
				</InspectorControls>
			)}
			<ScrollSlidesInspector
				attributes={attributes}
				setAttributes={setAttributes}
				clientId={clientId}
				themeContentSize={themeContentSize}
			/>

			<div
				{...blockProps}
				onClickCapture={(event) => {
					// Kill link navigation inside the editor — real anchors come
					// from authored post-title/featured-image blocks and from
					// server-rendered readonly slides in query mode.
					const anchor = event.target.closest?.('a[href]');
					if (anchor) {
						event.preventDefault();
					}
				}}
			>
				{hasEditorBg && (
					<div
						className="dsgo-scroll-slides__editor-bg"
						style={editorBgStyle}
						aria-hidden="true"
					/>
				)}
				<div className="dsgo-scroll-slides__inner" style={innerStyle}>
					{/* Navigation — editable headings outside query mode; in
					    query mode, one read-only heading per preview record
					    (post title, user name, term name, etc.). */}
					{inQueryMode
						? slideCount > 0 && (
								<div className="dsgo-scroll-slides__editor-nav">
									{previewRecords.map((record, index) => {
										const heading =
											record?.title?.rendered ||
											record?.name ||
											sprintf(
												/* translators: %d: slide number */
												__('Slide %d', 'designsetgo'),
												index + 1
											);
										return (
											<div
												key={record?.id ?? index}
												className={`dsgo-scroll-slides__editor-nav-item${
													index === clampedActive
														? ' is-active'
														: ''
												}`}
											>
												<button
													type="button"
													className="dsgo-scroll-slides__editor-nav-input"
													onClick={() =>
														handleNavClick(index)
													}
												>
													{heading}
												</button>
											</div>
										);
									})}
								</div>
							)
						: innerBlocks.length > 0 && (
								<div className="dsgo-scroll-slides__editor-nav">
									{innerBlocks.map((block, index) => (
										<div
											key={block.clientId}
											className={`dsgo-scroll-slides__editor-nav-item${
												index === clampedActive
													? ' is-active'
													: ''
											}`}
										>
											<input
												type="text"
												className="dsgo-scroll-slides__editor-nav-input"
												value={
													block.attributes
														.navHeading || ''
												}
												placeholder={sprintf(
													/* translators: %d: slide number */
													__(
														'Slide %d',
														'designsetgo'
													),
													index + 1
												)}
												onChange={(e) =>
													handleNavHeadingChange(
														index,
														e.target.value
													)
												}
												onFocus={() =>
													handleNavClick(index)
												}
											/>
										</div>
									))}
								</div>
							)}

					{/* Slide panels — all rendered, CSS shows only active */}
					{inQueryMode ? (
						<QueryModePanels
							innerBlocksProps={innerBlocksProps}
							preview={preview}
							parentQueryAttrs={parentQueryAttrs}
							outerContext={context}
						/>
					) : (
						<div {...innerBlocksProps} />
					)}
				</div>
			</div>
		</>
	);
}

/**
 * Render the scroll-slides panels in query-bound mode: panel 0 wraps the
 * editable InnerBlocks slot (the template scroll-slide); panels 1..N are
 * read-only server-rendered panels. Each panel is wrapped in a
 * BlockContextProvider so bindings resolve against the iterated post.
 * @param {Object} root0                  Props.
 * @param {Object} root0.innerBlocksProps useInnerBlocksProps result.
 * @param {Object} root0.preview          useQueryHostPreview result.
 * @param {Object} root0.parentQueryAttrs Parent query's attributes.
 * @param {Object} root0.outerContext     Outer block context.
 */
function QueryModePanels({
	innerBlocksProps,
	preview,
	parentQueryAttrs,
	outerContext,
}) {
	const source = parentQueryAttrs?.source || 'posts';
	const { records, hasResolved, serverHtml, loading } = preview;

	if (!hasResolved) {
		return (
			<div className="dsgo-scroll-slides__editor-panels dsgo-scroll-slides__editor-panels--query-loading">
				<div {...innerBlocksProps} />
			</div>
		);
	}

	const items = Array.isArray(records) ? records : [];

	if (items.length === 0) {
		return (
			<div className="dsgo-scroll-slides__editor-panels dsgo-scroll-slides__editor-panels--query-empty">
				<div {...innerBlocksProps} />
				<div
					className="dsgo-scroll-slides__editor-empty-hint"
					contentEditable={false}
					aria-hidden="true"
				>
					{__(
						'No posts match the parent query. Design the template panel above \u2014 it will render once per result at publish time.',
						'designsetgo'
					)}
				</div>
			</div>
		);
	}

	return (
		<div className="dsgo-scroll-slides__editor-panels dsgo-scroll-slides__editor-panels--query-mode">
			{items.map((item, idx) => {
				const itemContext = buildItemContext(
					item,
					source,
					idx,
					outerContext
				);
				return (
					<BlockContextProvider
						key={item.id ?? idx}
						value={itemContext}
					>
						{idx === 0 ? (
							<div {...innerBlocksProps} />
						) : (
							<QueryHostReadOnlyItem
								className="dsgo-scroll-slides__editor-readonly-item"
								html={serverHtml?.[idx] ?? null}
								loading={loading}
							/>
						)}
					</BlockContextProvider>
				);
			})}
		</div>
	);
}
