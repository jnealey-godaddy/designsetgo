import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useRef } from '@wordpress/element';
import {
	useBlockProps,
	useInnerBlocksProps,
	BlockControls,
	InspectorControls,
	BlockContextProvider,
} from '@wordpress/block-editor';
import { Notice, Button, Tooltip } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock, cloneBlock } from '@wordpress/blocks';
import { copy, trash, plus } from '@wordpress/icons';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import SliderPlaceholder from './components/SliderPlaceholder';
import SliderInspector from './components/inspector/SliderInspector';
import DsgoChildToolbar from '../../components/shared/DsgoChildToolbar';
import useQueryHostPreview, {
	buildItemContext,
} from '../query/hooks/useQueryHostPreview';
import useParentQueryAttrs from '../query/hooks/useParentQueryAttrs';
import QueryHostReadOnlyItem from '../query/components/QueryHostReadOnlyItem';
import { ARROW_PATHS, ARROW_SVG_ATTRS } from './arrow-icon';

/**
 * The editor's inert arrow chevron — same path data the frontend builds, so
 * the placeholder the author sees matches what actually renders.
 *
 * @param {Object} props           Component props.
 * @param {string} props.direction Either `prev` or `next`.
 */
function ArrowIcon({ direction }) {
	return (
		<svg
			{...ARROW_SVG_ATTRS}
			width="1em"
			height="1em"
			aria-hidden="true"
			focusable="false"
		>
			<path d={ARROW_PATHS[direction]} />
		</svg>
	);
}

const SINGLE_SLIDE_EFFECTS = ['fade', 'zoom'];

export default function SliderEdit({
	attributes,
	setAttributes,
	clientId,
	context,
}) {
	// When the slider sits inside a Dynamic Query, the parent provides a
	// queryId via context. In that mode the slider becomes an item host: the
	// first slide acts as the per-item template, extras are ignored
	// server-side, and the toolbar's Add/Remove controls are locked out.
	const queryId =
		typeof context === 'object' && context
			? context['designsetgo/queryId'] || ''
			: '';
	const inQueryMode = !!queryId;
	// Only attributes that drive editor markup (classes, CSS vars, data-*
	// attributes) are destructured here. Inspector-only attributes (autoplay
	// timing, drag/loop toggles, breakpoints, aria label, etc.) are read from
	// `attributes` as a whole by SliderInspector and its field components.
	const {
		slidesPerView,
		slidesPerViewTablet,
		slidesPerViewMobile,
		height,
		aspectRatio,
		useAspectRatio,
		gap,
		showArrows,
		showDots,
		arrowStyle,
		arrowPosition,
		arrowVerticalPosition,
		arrowColor,
		arrowBackgroundColor,
		arrowSize,
		arrowPadding,
		dotStyle,
		dotPosition,
		dotColor,
		effect,
		transitionDuration,
		freeMode,
		centeredSlides,
		styleVariation,
		scrollDriven,
	} = attributes;

	const requiresSingleSlideEffect = SINGLE_SLIDE_EFFECTS.includes(effect);

	const blockRef = useRef(null);

	// Slide list powers both the dot navigation and the editor-only navigator.
	const { slides, selectedSlideId } = useSelect(
		(select) => {
			const editor = select('core/block-editor');
			const children = editor.getBlocks(clientId) || [];
			const selectedBlock = editor.getSelectedBlockClientId();
			let selectedSlide = null;
			if (selectedBlock) {
				if (
					children.some((child) => child.clientId === selectedBlock)
				) {
					selectedSlide = selectedBlock;
				} else {
					const parents = editor.getBlockParents(selectedBlock);
					const match = parents.find((parent) =>
						children.some((child) => child.clientId === parent)
					);
					if (match) {
						selectedSlide = match;
					}
				}
			}
			return { slides: children, selectedSlideId: selectedSlide };
		},
		[clientId]
	);
	const slideCount = slides.length;
	const selectedSlideIndex = selectedSlideId
		? slides.findIndex((slide) => slide.clientId === selectedSlideId)
		: -1;
	const activeSlideIndex =
		selectedSlideIndex >= 0 ? selectedSlideIndex : undefined;

	// When bound to a parent Dynamic Query, walk up to the parent's attributes
	// so the editor preview uses the same query config (postType, perPage,
	// filters, orderBy) the frontend will. Runs only when inQueryMode.
	const parentQueryAttrs = useParentQueryAttrs(clientId, inQueryMode);

	// The per-item template is the first slide block. Memoize to avoid
	// allocating a new array on every render — useQueryHostPreview /
	// useRenderedItems depend on stable references to avoid re-serializing.
	const templateSlideBlocks = useMemo(
		() => (slides.length > 0 ? [slides[0]] : []),
		[slides]
	);

	const preview = useQueryHostPreview({
		attributes: parentQueryAttrs,
		queryId,
		innerBlocks: templateSlideBlocks,
		enabled: inQueryMode && !!parentQueryAttrs,
	});

	const { insertBlock, removeBlock, selectBlock } =
		useDispatch('core/block-editor');

	// Extract a readable label for a slide: first heading text if present, else "Slide N".
	// DOMParser handles malformed/unterminated tags safely (unlike a naive regex).
	const getSlideLabel = (slide, index) => {
		const heading = slide.innerBlocks?.find(
			(inner) => inner.name === 'core/heading'
		);
		const raw = heading?.attributes?.content ?? '';
		const parsed = new window.DOMParser().parseFromString(
			String(raw),
			'text/html'
		);
		const text = (parsed.body.textContent || '').trim();
		if (text) {
			return text.slice(0, 30);
		}
		return sprintf(
			/* translators: %d: slide number */
			__('Slide %d', 'designsetgo'),
			index + 1
		);
	};

	const handleAddSlide = () => {
		const newSlide = createBlock('designsetgo/slide');
		insertBlock(newSlide, slideCount, clientId, true);
	};

	const handleDuplicateSlide = (slide, index) => {
		// cloneBlock does a deep clone with fresh clientIds at every level.
		const clone = cloneBlock(slide);
		insertBlock(clone, index + 1, clientId, true);
	};

	const handleRemoveSlide = (slide) => {
		if (slides.length <= 1) {
			return;
		}
		removeBlock(slide.clientId, false);
	};

	useEffect(() => {
		if (
			!requiresSingleSlideEffect ||
			(slidesPerView === 1 &&
				slidesPerViewTablet === 1 &&
				slidesPerViewMobile === 1)
		) {
			return;
		}

		setAttributes({
			slidesPerView: 1,
			slidesPerViewTablet: 1,
			slidesPerViewMobile: 1,
		});
	}, [
		requiresSingleSlideEffect,
		slidesPerView,
		slidesPerViewTablet,
		slidesPerViewMobile,
		setAttributes,
	]);

	// Editor navigation: scroll the track using a ref (works inside iframed editor)
	const scrollToSlide = (direction) => {
		const track = blockRef.current?.querySelector('.dsgo-slider__track');
		if (!track) {
			return;
		}

		const slide = track.querySelector('.dsgo-slide');
		if (!slide) {
			return;
		}

		const slideWidth = slide.offsetWidth;
		const gapValue =
			parseFloat(
				track.ownerDocument.defaultView.getComputedStyle(track).gap
			) || 0;
		const scrollAmount = slideWidth + gapValue;

		track.scrollBy({
			left: direction === 'next' ? scrollAmount : -scrollAmount,
			behavior: 'smooth',
		});
	};

	const scrollToSlideIndex = (index) => {
		const track = blockRef.current?.querySelector('.dsgo-slider__track');
		if (!track) {
			return;
		}

		const slide = track.querySelector('.dsgo-slide');
		if (!slide) {
			return;
		}

		const slideWidth = slide.offsetWidth;
		const gapValue =
			parseFloat(
				track.ownerDocument.defaultView.getComputedStyle(track).gap
			) || 0;
		const scrollPosition = index * (slideWidth + gapValue);

		track.scrollTo({
			left: scrollPosition,
			behavior: 'smooth',
		});
	};

	// Declaratively calculate classes based on attributes
	const sliderClasses = classnames('dsgo-slider', {
		[`dsgo-slider--${styleVariation}`]: styleVariation,
		[`dsgo-slider--effect-${effect}`]: effect,
		'dsgo-slider--has-arrows': showArrows,
		'dsgo-slider--has-dots': showDots,
		'dsgo-slider--centered': centeredSlides,
		'dsgo-slider--free-mode': freeMode,
		'dsgo-slider--scroll-driven': scrollDriven,
	});

	const effectiveSlidesPerView = requiresSingleSlideEffect
		? 1
		: slidesPerView;
	const effectiveSlidesPerViewTablet = requiresSingleSlideEffect
		? 1
		: slidesPerViewTablet;
	const effectiveSlidesPerViewMobile = requiresSingleSlideEffect
		? 1
		: slidesPerViewMobile;
	const singleSlideNotice = __(
		'Fade and Zoom transitions show one slide per view. Switch back to the Slide effect to display multiple slides at once.',
		'designsetgo'
	);

	const handleEffectChange = (value) => {
		const updates = { effect: value };
		if (SINGLE_SLIDE_EFFECTS.includes(value)) {
			updates.slidesPerView = 1;
			updates.slidesPerViewTablet = 1;
			updates.slidesPerViewMobile = 1;
		}
		setAttributes(updates);
	};

	// Apply settings as CSS custom properties
	const customStyles = {
		...(height && { '--dsgo-slider-height': height }),
		'--dsgo-slider-aspect-ratio': aspectRatio,
		'--dsgo-slider-gap': gap,
		'--dsgo-slider-transition': transitionDuration,
		'--dsgo-slider-slides-per-view': String(effectiveSlidesPerView),
		'--dsgo-slider-slides-per-view-tablet': String(
			effectiveSlidesPerViewTablet
		),
		'--dsgo-slider-slides-per-view-mobile': String(
			effectiveSlidesPerViewMobile
		),
		...(arrowColor && {
			'--dsgo-slider-arrow-color': convertColorToCSSVar(arrowColor),
		}),
		...(arrowBackgroundColor && {
			'--dsgo-slider-arrow-bg-color':
				convertColorToCSSVar(arrowBackgroundColor),
		}),
		...(arrowSize && { '--dsgo-slider-arrow-size': arrowSize }),
		...(arrowPadding && { '--dsgo-slider-arrow-padding': arrowPadding }),
		...(dotColor && {
			'--dsgo-slider-dot-color': convertColorToCSSVar(dotColor),
		}),
	};

	// Block wrapper props
	// Data attributes for JavaScript configuration and CSS selectors (match save.js)
	const blockProps = useBlockProps({
		ref: blockRef,
		className: sliderClasses,
		style: customStyles,
		'data-slides-per-view': effectiveSlidesPerView,
		'data-slides-per-view-tablet': effectiveSlidesPerViewTablet,
		'data-slides-per-view-mobile': effectiveSlidesPerViewMobile,
		'data-use-aspect-ratio': useAspectRatio,
		'data-show-arrows': showArrows,
		'data-show-dots': showDots,
		'data-arrow-style': arrowStyle,
		'data-arrow-position': arrowPosition,
		'data-arrow-vertical-position': arrowVerticalPosition,
		'data-dot-style': dotStyle,
		'data-dot-position': dotPosition,
		'data-effect': effect,
	});

	// Inner blocks configuration - ONLY allow slide children. Initial seeding
	// is handled by SliderPlaceholder so authors pick a starter layout instead
	// of landing on a generic three-slide template. In query mode we also lock
	// add/remove at the slider level — slides beyond the first are ignored at
	// render, so allowing authors to add more would be misleading. The class
	// name also switches: in authored mode this IS the track; in query mode
	// it becomes a "display: contents" slot that sits inside a manually-built
	// track alongside read-only preview items.
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: inQueryMode
				? 'dsgo-slider__editor-template-slot'
				: 'dsgo-slider__track',
		},
		{
			allowedBlocks: ['designsetgo/slide'],
			orientation: 'horizontal',
			templateLock: inQueryMode ? 'insert' : false,
		}
	);

	if (slideCount === 0) {
		return (
			<div {...blockProps}>
				<SliderPlaceholder
					clientId={clientId}
					setAttributes={setAttributes}
				/>
			</div>
		);
	}

	return (
		<>
			<BlockControls>
				<DsgoChildToolbar
					parentClientId={clientId}
					childBlockName="designsetgo/slide"
					activeIndex={activeSlideIndex}
					onActiveIndexChange={(index, newClientId) => {
						// Prefer the clientId handed to us — the `slides`
						// closure here comes from the parent's useSelect and
						// doesn't include freshly-inserted children until
						// the next render. On Remove (null clientId) fall
						// through to the clamped neighbor in the stale list.
						if (newClientId) {
							selectBlock(newClientId);
							return;
						}
						const target = slides[index];
						if (target) {
							selectBlock(target.clientId);
						}
					}}
					addLabel={__('Add slide', 'designsetgo')}
					duplicateLabel={__('Duplicate slide', 'designsetgo')}
					removeLabel={__('Remove slide', 'designsetgo')}
					movePrevLabel={__('Move slide left', 'designsetgo')}
					moveNextLabel={__('Move slide right', 'designsetgo')}
					disableAdd={inQueryMode}
					disableDuplicate={inQueryMode}
					disableRemove={inQueryMode}
					disableMove={inQueryMode}
				/>
			</BlockControls>

			{inQueryMode && (
				<InspectorControls>
					<Notice status="info" isDismissible={false}>
						{__(
							'This slider is bound to a parent Dynamic Query. The first slide is the per-item template — extra slides are ignored at render. Slide management controls are disabled while bound.',
							'designsetgo'
						)}
					</Notice>
					{slideCount > 1 && (
						<Notice status="warning" isDismissible={false}>
							{sprintf(
								/* translators: %d: number of slides that will not render */
								__(
									'%d extra slide(s) will be ignored at render. Only the first slide is used as the template.',
									'designsetgo'
								),
								slideCount - 1
							)}
						</Notice>
					)}
				</InspectorControls>
			)}

			<SliderInspector
				attributes={attributes}
				setAttributes={setAttributes}
				clientId={clientId}
				onEffectChange={handleEffectChange}
				requiresSingleSlideEffect={requiresSingleSlideEffect}
				singleSlideNotice={singleSlideNotice}
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
				<div className="dsgo-slider__viewport">
					{inQueryMode ? (
						<QueryModeTrack
							innerBlocksProps={innerBlocksProps}
							preview={preview}
							parentQueryAttrs={parentQueryAttrs}
							outerContext={context}
						/>
					) : (
						<div {...innerBlocksProps} />
					)}
				</div>

				{/* Editor-only slide navigator */}
				{slides.length > 0 && (
					<div
						className="dsgo-slider__nav dsgo-slider__nav--editor-only"
						role="toolbar"
						aria-label={__('Slides', 'designsetgo')}
					>
						{slides.map((slide, index) => (
							<div
								key={slide.clientId}
								className={classnames('dsgo-slider__nav-chip', {
									'is-active':
										selectedSlideId === slide.clientId,
								})}
							>
								<button
									type="button"
									className="dsgo-slider__nav-chip-label"
									onClick={() => selectBlock(slide.clientId)}
								>
									<span className="dsgo-slider__nav-chip-index">
										{index + 1}
									</span>
									<span className="dsgo-slider__nav-chip-title">
										{getSlideLabel(slide, index)}
									</span>
								</button>
								<div className="dsgo-slider__nav-chip-actions">
									<Tooltip
										text={__(
											'Duplicate slide',
											'designsetgo'
										)}
									>
										<Button
											size="small"
											icon={copy}
											label={__(
												'Duplicate slide',
												'designsetgo'
											)}
											disabled={inQueryMode}
											onClick={() =>
												handleDuplicateSlide(
													slide,
													index
												)
											}
										/>
									</Tooltip>
									<Tooltip
										text={__('Remove slide', 'designsetgo')}
									>
										<Button
											size="small"
											icon={trash}
											isDestructive
											label={__(
												'Remove slide',
												'designsetgo'
											)}
											disabled={inQueryMode}
											onClick={() =>
												handleRemoveSlide(slide)
											}
										/>
									</Tooltip>
								</div>
							</div>
						))}
						<Button
							size="small"
							icon={plus}
							className="dsgo-slider__nav-add"
							disabled={inQueryMode}
							onClick={handleAddSlide}
						>
							{__('Add slide', 'designsetgo')}
						</Button>
					</div>
				)}

				{/* Editor-only navigation - functional scroll controls */}
				{showArrows && (
					<div className="dsgo-slider__arrows dsgo-slider__arrows--editor-only">
						<button
							type="button"
							className="dsgo-slider__arrow dsgo-slider__arrow--prev"
							aria-label={__('Previous slide', 'designsetgo')}
							onClick={() => scrollToSlide('prev')}
						>
							<ArrowIcon direction="prev" />
						</button>
						<button
							type="button"
							className="dsgo-slider__arrow dsgo-slider__arrow--next"
							aria-label={__('Next slide', 'designsetgo')}
							onClick={() => scrollToSlide('next')}
						>
							<ArrowIcon direction="next" />
						</button>
					</div>
				)}

				{showDots && (
					<div className="dsgo-slider__dots dsgo-slider__dots--editor-only">
						{Array.from({ length: slideCount }, (_, i) => (
							<button
								key={i}
								type="button"
								className="dsgo-slider__dot"
								onClick={() => scrollToSlideIndex(i)}
							>
								<span className="screen-reader-text">
									{sprintf(
										/* translators: %d: slide number */
										__('Slide %d', 'designsetgo'),
										i + 1
									)}
								</span>
							</button>
						))}
					</div>
				)}
			</div>
		</>
	);
}

/**
 * Render the slider track in query-bound mode: item 0 wraps the editable
 * InnerBlocks slot (the template slide), items 1..N are read-only server-
 * rendered slides. Each item is wrapped in a BlockContextProvider so any
 * Block Bindings inside the template resolve against the iterated post.
 * @param {Object} root0
 * @param {Object} root0.innerBlocksProps
 * @param {Object} root0.preview
 * @param {Object} root0.parentQueryAttrs
 * @param {Object} root0.outerContext
 */
function QueryModeTrack({
	innerBlocksProps,
	preview,
	parentQueryAttrs,
	outerContext,
}) {
	const source = parentQueryAttrs?.source || 'posts';
	const { records, hasResolved, serverHtml, loading } = preview;

	if (!hasResolved) {
		return (
			<div className="dsgo-slider__track dsgo-slider__track--query-loading">
				<div {...innerBlocksProps} />
			</div>
		);
	}

	const items = Array.isArray(records) ? records : [];

	if (items.length === 0) {
		return (
			<div className="dsgo-slider__track dsgo-slider__track--query-empty">
				<div {...innerBlocksProps} />
				<div
					className="dsgo-slider__editor-empty-hint"
					contentEditable={false}
					aria-hidden="true"
				>
					{__(
						'No posts match the parent query. Design the template slide above \u2014 it will render once per result at publish time.',
						'designsetgo'
					)}
				</div>
			</div>
		);
	}

	return (
		<div className="dsgo-slider__track">
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
								className="dsgo-slider__editor-readonly-item"
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
