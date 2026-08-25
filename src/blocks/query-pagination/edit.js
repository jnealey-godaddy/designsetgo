import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import InfiniteScrollControls from './components/InfiniteScrollControls';
import CarouselNotice from './components/CarouselNotice';
import useQueryItemHost, {
	hostSupportsInfiniteScroll,
} from '../query/hooks/useQueryItemHost';

/**
 * Canvas preview for the pagination block in the editor.
 * Extracted to avoid nested ternary expressions (no-nested-ternary rule).
 *
 * @param {Object}  root0
 * @param {string}  root0.effectiveKind         The resolved pagination kind.
 * @param {boolean} root0.showPrevNext          Whether to show prev/next arrows.
 * @param {string}  root0.labelLoadMore         Label for the load-more button.
 * @param {string}  root0.buttonLabelWhenPaused Label for the infinite-scroll pause button.
 */
function PaginationPreview({
	effectiveKind,
	showPrevNext,
	labelLoadMore,
	buttonLabelWhenPaused,
}) {
	if (effectiveKind === 'infinite') {
		return (
			<div className="dsgo-query-pagination--infinite is-editor-preview">
				<button
					type="button"
					className="dsgo-query-pagination__loadmore wp-element-button"
					disabled
				>
					{buttonLabelWhenPaused || __('Load more', 'designsetgo')}
				</button>
				<div
					className="dsgo-query-pagination__sentinel"
					aria-hidden="true"
				/>
			</div>
		);
	}
	if (effectiveKind === 'loadmore') {
		return (
			<button
				type="button"
				className="dsgo-query-pagination__loadmore"
				disabled
			>
				{labelLoadMore || __('Load more', 'designsetgo')}
			</button>
		);
	}
	return (
		<span className="dsgo-query-pagination__preview">
			{showPrevNext ? '\u2190 ' : ''}1 2 3{showPrevNext ? ' \u2192' : ''}
		</span>
	);
}

const DEFAULTS = {
	mode: 'numbered',
	paginationKind: 'numbered',
	labelLoadMore: '',
	labelLoading: '',
	showPrevNext: true,
	autoPauseAfter: 3,
	sentinelOffsetPx: 200,
	buttonLabelWhenPaused: 'Load more',
	alignment: 'left',
};

const ALIGNMENT_OPTIONS = [
	{ value: 'left', label: __('Left', 'designsetgo') },
	{ value: 'center', label: __('Center', 'designsetgo') },
	{ value: 'right', label: __('Right', 'designsetgo') },
];

export default function QueryPaginationEdit({
	attributes,
	setAttributes,
	clientId,
}) {
	const {
		mode,
		paginationKind,
		labelLoadMore,
		labelLoading,
		showPrevNext,
		buttonLabelWhenPaused,
		alignment,
	} = attributes;

	// Determine the requested kind: paginationKind takes precedence when set
	// to a non-default value; fall back to mode for backwards compatibility.
	const requestedKind = paginationKind !== 'numbered' ? paginationKind : mode;

	// Carousel presentation wins over infinite scroll — see CarouselNotice and
	// designsetgo_query_host_supports_infinite_scroll(). Preview what the front
	// end will actually render rather than a sentinel that never fires.
	const itemHost = useQueryItemHost(clientId);
	const degradesToLoadMore =
		requestedKind === 'infinite' && !hostSupportsInfiniteScroll(itemHost);
	const effectiveKind = degradesToLoadMore ? 'loadmore' : requestedKind;

	const blockProps = useBlockProps({
		className: `dsgo-query-pagination is-editor is-align-${
			alignment || 'left'
		}`,
	});

	return (
		<>
			<InspectorControls>
				{degradesToLoadMore && (
					<CarouselNotice
						itemHost={itemHost}
						setAttributes={setAttributes}
					/>
				)}
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() => setAttributes(DEFAULTS)}
				>
					<DsgoInspectorPanel.Item
						label={__('Mode', 'designsetgo')}
						hasValue={() => mode !== 'numbered'}
						onDeselect={() => setAttributes({ mode: 'numbered' })}
						isShownByDefault
					>
						<SelectControl
							label={__('Mode', 'designsetgo')}
							value={mode}
							options={[
								{
									value: 'numbered',
									label: __('Numbered', 'designsetgo'),
								},
								{
									value: 'loadmore',
									label: __('Load more', 'designsetgo'),
								},
							]}
							onChange={(v) => setAttributes({ mode: v })}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{mode === 'numbered' && paginationKind !== 'infinite' && (
						<DsgoInspectorPanel.Item
							label={__('Show prev/next arrows', 'designsetgo')}
							hasValue={() => showPrevNext !== true}
							onDeselect={() =>
								setAttributes({ showPrevNext: true })
							}
						>
							<ToggleControl
								label={__(
									'Show prev/next arrows',
									'designsetgo'
								)}
								checked={!!showPrevNext}
								onChange={(v) =>
									setAttributes({ showPrevNext: !!v })
								}
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					{mode === 'loadmore' && paginationKind !== 'infinite' && (
						<>
							<DsgoInspectorPanel.Item
								label={__('Load more label', 'designsetgo')}
								hasValue={() => labelLoadMore !== ''}
								onDeselect={() =>
									setAttributes({ labelLoadMore: '' })
								}
								isShownByDefault
							>
								<TextControl
									label={__(
										'Load more button label',
										'designsetgo'
									)}
									value={labelLoadMore}
									onChange={(v) =>
										setAttributes({
											labelLoadMore: v,
										})
									}
									placeholder={__('Load more', 'designsetgo')}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</DsgoInspectorPanel.Item>
							<DsgoInspectorPanel.Item
								label={__('Loading label', 'designsetgo')}
								hasValue={() => labelLoading !== ''}
								onDeselect={() =>
									setAttributes({ labelLoading: '' })
								}
							>
								<TextControl
									label={__(
										'Loading state label',
										'designsetgo'
									)}
									value={labelLoading}
									onChange={(v) =>
										setAttributes({
											labelLoading: v,
										})
									}
									placeholder={__(
										'Loading\u2026',
										'designsetgo'
									)}
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								/>
							</DsgoInspectorPanel.Item>
						</>
					)}

					{paginationKind === 'infinite' && (
						<InfiniteScrollControls
							attributes={attributes}
							setAttributes={setAttributes}
							panelId={clientId}
							sentinelDisabled={degradesToLoadMore}
						/>
					)}

					<DsgoInspectorPanel.Item
						label={__('Alignment', 'designsetgo')}
						hasValue={() => (alignment || 'left') !== 'left'}
						onDeselect={() => setAttributes({ alignment: 'left' })}
						isShownByDefault
					>
						<SelectControl
							label={__('Alignment', 'designsetgo')}
							value={alignment || 'left'}
							options={ALIGNMENT_OPTIONS}
							onChange={(v) => setAttributes({ alignment: v })}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>

			<div {...blockProps}>
				<PaginationPreview
					effectiveKind={effectiveKind}
					showPrevNext={showPrevNext}
					labelLoadMore={
						degradesToLoadMore
							? labelLoadMore || buttonLabelWhenPaused
							: labelLoadMore
					}
					buttonLabelWhenPaused={buttonLabelWhenPaused}
				/>
				{degradesToLoadMore && (
					<span
						className="dsgo-query-pagination__fallback-hint"
						contentEditable={false}
					>
						{__(
							'Infinite scroll falls back to Load more inside a carousel.',
							'designsetgo'
						)}
					</span>
				)}
			</div>
		</>
	);
}
