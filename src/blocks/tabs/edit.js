/**
 * Tabs Block - Edit Component
 *
 * Parent block that manages tab navigation and panels
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	useInnerBlocksProps,
	store as blockEditorStore,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	RangeControl,
	Button,
	Tooltip,
} from '@wordpress/components';
import { createBlock, cloneBlock } from '@wordpress/blocks';
import { copy, trash, plus } from '@wordpress/icons';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { getIcon } from '../icon/utils/svg-icons';
import { useUniqueBlockId } from '../../hooks';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

const ALLOWED_BLOCKS = ['designsetgo/tab'];

const TEMPLATE = [
	['designsetgo/tab', { title: __('Tab 1', 'designsetgo') }],
	['designsetgo/tab', { title: __('Tab 2', 'designsetgo') }],
	['designsetgo/tab', { title: __('Tab 3', 'designsetgo') }],
];

export default function Edit({ attributes, setAttributes, clientId }) {
	const {
		uniqueId,
		orientation,
		activeTab,
		alignment,
		mobileBreakpoint,
		mobileMode,
		enableDeepLinking,
		gap,
		tabStyle,
		tabColor,
		tabBackgroundColor,
		tabContentBackgroundColor,
		activeTabColor,
		activeTabBackgroundColor,
		tabBorderColor,
		tabHoverColor,
		tabHoverBackgroundColor,
		showNavBorder,
	} = attributes;

	// Get theme color palette and gradient settings
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	useUniqueBlockId({
		clientId,
		attributeName: 'uniqueId',
		value: uniqueId,
		setAttributes,
	});

	// Get inner blocks (tabs)
	const { innerBlocks } = useSelect(
		(select) => {
			const { getBlock } = select(blockEditorStore);
			return {
				innerBlocks: getBlock(clientId)?.innerBlocks || [],
			};
		},
		[clientId]
	);

	const { insertBlock, removeBlock, updateBlockAttributes } =
		useDispatch(blockEditorStore);

	useEffect(() => {
		// Keep the active tab index valid when authors remove every child and
		// then reseed via the placeholder, or when malformed content restores
		// with an out-of-range index.
		if (innerBlocks.length === 0) {
			if (activeTab !== 0) {
				setAttributes({ activeTab: 0 });
			}
			return;
		}

		if (activeTab < 0 || activeTab >= innerBlocks.length) {
			setAttributes({ activeTab: 0 });
		}
	}, [activeTab, innerBlocks.length, setAttributes]);

	// Handle tab chip click — only switch which tab is active. We intentionally
	// do NOT call selectBlock() on the child Tab, so the Gutenberg inline
	// toolbar stays anchored to the Tabs parent (above the whole block) instead
	// of hovering between the nav and the panel. Authors who want to edit a
	// specific Tab's attributes can click into its panel content below.
	const handleTabClick = (index) => {
		setAttributes({ activeTab: index });
	};

	const handleTitleChange = (tab, value) => {
		updateBlockAttributes(tab.clientId, { title: value });
	};

	const handleAddTab = () => {
		const tabCount = innerBlocks.length;
		const newTab = createBlock('designsetgo/tab', {
			title: sprintf(
				/* translators: %d: tab number */
				__('Tab %d', 'designsetgo'),
				tabCount + 1
			),
		});
		// updateSelection: false — keep the Tabs parent selected so the inline
		// toolbar doesn't jump to the new child Tab and overlap the nav.
		insertBlock(newTab, tabCount, clientId, false);
		setAttributes({ activeTab: tabCount });
	};

	const handleDuplicateTab = (tab, index) => {
		// cloneBlock produces a deep clone with fresh clientIds at every level.
		// Reset uniqueId so the duplicated tab regenerates its own via the
		// child block's onMount effect.
		const clone = cloneBlock(tab, { uniqueId: '' });
		insertBlock(clone, index + 1, clientId, false);
		setAttributes({ activeTab: index + 1 });
	};

	const handleRemoveTab = (tab, index) => {
		if (innerBlocks.length <= 1) {
			return;
		}
		removeBlock(tab.clientId, false);
		// If we removed the active tab (or an earlier one), keep a valid index.
		if (index <= activeTab) {
			const next = Math.max(0, activeTab - 1);
			setAttributes({ activeTab: next });
		}
	};

	// Handle keyboard navigation
	const handleKeyDown = (e, index) => {
		let newIndex = index;

		if (orientation === 'horizontal') {
			if (e.key === 'ArrowLeft') {
				newIndex = index > 0 ? index - 1 : innerBlocks.length - 1;
				e.preventDefault();
			} else if (e.key === 'ArrowRight') {
				newIndex = index < innerBlocks.length - 1 ? index + 1 : 0;
				e.preventDefault();
			}
		} else if (e.key === 'ArrowUp') {
			newIndex = index > 0 ? index - 1 : innerBlocks.length - 1;
			e.preventDefault();
		} else if (e.key === 'ArrowDown') {
			newIndex = index < innerBlocks.length - 1 ? index + 1 : 0;
			e.preventDefault();
		}

		if (e.key === 'Home') {
			newIndex = 0;
			e.preventDefault();
		} else if (e.key === 'End') {
			newIndex = innerBlocks.length - 1;
			e.preventDefault();
		}

		if (newIndex !== index) {
			setAttributes({ activeTab: newIndex });

			// Focus the new tab
			setTimeout(() => {
				const tabButton = document.querySelector(
					`.dsgo-tabs-${uniqueId} [data-tab-index="${newIndex}"]`
				);
				if (tabButton) {
					tabButton.focus();
				}
			}, 0);
		}
	};

	const blockProps = useBlockProps({
		className: `dsgo-tabs dsgo-tabs-${uniqueId} dsgo-tabs--${orientation} dsgo-tabs--${tabStyle} dsgo-tabs--align-${alignment}${showNavBorder ? ' dsgo-tabs--show-nav-border' : ''}`,
		style: {
			'--dsgo-tabs-gap': gap,
			...(tabColor && {
				'--dsgo-tab-color': convertColorToCSSVar(tabColor),
			}),
			...(tabBackgroundColor && {
				'--dsgo-tab-bg': convertColorToCSSVar(tabBackgroundColor),
			}),
			...(tabContentBackgroundColor && {
				'--dsgo-tab-content-bg': convertColorToCSSVar(
					tabContentBackgroundColor
				),
			}),
			...(activeTabColor && {
				'--dsgo-tab-color-active': convertColorToCSSVar(activeTabColor),
			}),
			...(activeTabBackgroundColor && {
				'--dsgo-tab-bg-active': convertColorToCSSVar(
					activeTabBackgroundColor
				),
			}),
			...(tabBorderColor && {
				'--dsgo-tab-border-color': convertColorToCSSVar(tabBorderColor),
			}),
			...(tabHoverColor && {
				'--dsgo-tab-color-hover': convertColorToCSSVar(tabHoverColor),
			}),
			...(tabHoverBackgroundColor && {
				'--dsgo-tab-bg-hover': convertColorToCSSVar(
					tabHoverBackgroundColor
				),
			}),
		},
	});

	// Use useInnerBlocksProps for tab panels
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'dsgo-tabs__panels',
		},
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			orientation: orientation === 'vertical' ? 'vertical' : 'horizontal',
		}
	);

	return (
		<>
			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Tab Colors', 'designsetgo')}
					settings={[
						{
							label: __('Tab Text', 'designsetgo'),
							colorValue: decodeColorValue(
								tabColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									tabColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Tab Background', 'designsetgo'),
							colorValue: decodeColorValue(
								tabBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									tabBackgroundColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Tab Text Hover', 'designsetgo'),
							colorValue: decodeColorValue(
								tabHoverColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									tabHoverColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Tab Background Hover', 'designsetgo'),
							colorValue: decodeColorValue(
								tabHoverBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									tabHoverBackgroundColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Active Tab Text', 'designsetgo'),
							colorValue: decodeColorValue(
								activeTabColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									activeTabColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Active Tab Background', 'designsetgo'),
							colorValue: decodeColorValue(
								activeTabBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									activeTabBackgroundColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Tab Border', 'designsetgo'),
							colorValue: decodeColorValue(
								tabBorderColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									tabBorderColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Tab Content Background', 'designsetgo'),
							colorValue: decodeColorValue(
								tabContentBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									tabContentBackgroundColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
					]}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<InspectorControls>
				<PanelBody
					title={__('Tab Settings', 'designsetgo')}
					initialOpen={true}
				>
					<SelectControl
						label={__('Orientation', 'designsetgo')}
						value={orientation}
						options={[
							{
								label: __('Horizontal', 'designsetgo'),
								value: 'horizontal',
							},
							{
								label: __('Vertical', 'designsetgo'),
								value: 'vertical',
							},
						]}
						onChange={(value) =>
							setAttributes({ orientation: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Tab Style', 'designsetgo')}
						value={tabStyle}
						options={[
							{
								label: __('Default', 'designsetgo'),
								value: 'default',
							},
							{
								label: __('Pills', 'designsetgo'),
								value: 'pills',
							},
							{
								label: __('Underline', 'designsetgo'),
								value: 'underline',
							},
							{
								label: __('Minimal', 'designsetgo'),
								value: 'minimal',
							},
						]}
						onChange={(value) => setAttributes({ tabStyle: value })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					{orientation === 'horizontal' && (
						<SelectControl
							label={__('Alignment', 'designsetgo')}
							value={alignment}
							options={[
								{
									label: __('Left', 'designsetgo'),
									value: 'left',
								},
								{
									label: __('Center', 'designsetgo'),
									value: 'center',
								},
								{
									label: __('Right', 'designsetgo'),
									value: 'right',
								},
								{
									label: __('Justified', 'designsetgo'),
									value: 'justified',
								},
							]}
							onChange={(value) =>
								setAttributes({ alignment: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					)}

					<RangeControl
						label={__('Gap Between Tabs', 'designsetgo')}
						value={parseInt(gap)}
						onChange={(value) =>
							setAttributes({ gap: `${value}px` })
						}
						min={0}
						max={40}
						step={1}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<ToggleControl
						label={__('Show Border Below Tabs', 'designsetgo')}
						checked={showNavBorder}
						onChange={(value) =>
							setAttributes({ showNavBorder: value })
						}
						help={__(
							'Add a divider line between tab navigation and content',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={__('Mobile Settings', 'designsetgo')}
					initialOpen={false}
				>
					<RangeControl
						label={__('Mobile Breakpoint (px)', 'designsetgo')}
						value={mobileBreakpoint}
						onChange={(value) =>
							setAttributes({ mobileBreakpoint: value })
						}
						min={320}
						max={1024}
						step={1}
						help={__(
							'Screen width below which mobile mode activates',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Mobile Mode', 'designsetgo')}
						value={mobileMode}
						options={[
							{
								label: __('Accordion', 'designsetgo'),
								value: 'accordion',
							},
							{
								label: __('Dropdown', 'designsetgo'),
								value: 'dropdown',
							},
							{
								label: __('Tabs (Scrollable)', 'designsetgo'),
								value: 'tabs',
							},
						]}
						onChange={(value) =>
							setAttributes({ mobileMode: value })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				<PanelBody
					title={__('Advanced', 'designsetgo')}
					initialOpen={false}
				>
					<ToggleControl
						label={__('Enable Deep Linking', 'designsetgo')}
						checked={enableDeepLinking}
						onChange={(value) =>
							setAttributes({ enableDeepLinking: value })
						}
						help={__(
							'Allow tabs to be accessed via URL hash (e.g., #tab-name)',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{/* Tab Navigation. The tablist + the editor-only "Add tab"
				    button are wrapped in a single flex row so the add
				    control reads as adjacent to the tabs without sitting
				    inside role="tablist". */}
				<div className="dsgo-tabs__nav-row">
					<div
						className="dsgo-tabs__nav"
						role="tablist"
						aria-label={__('Tabs', 'designsetgo')}
					>
						{innerBlocks.map((block, index) => {
							const {
								title,
								icon,
								iconPosition,
								uniqueId: tabId,
							} = block.attributes;
							const isActive = index === activeTab;

							const placeholderLabel = sprintf(
								/* translators: %d: tab number */
								__('Tab %d', 'designsetgo'),
								index + 1
							);
							return (
								<div
									key={block.clientId}
									className={`dsgo-tabs__tab dsgo-tabs__tab--editor ${
										isActive ? 'is-active' : ''
									} ${
										icon
											? `has-icon has-icon-${iconPosition}`
											: ''
									}`}
									id={`tab-${tabId}`}
									role="tab"
									aria-selected={isActive}
									aria-controls={`panel-${tabId}`}
									tabIndex={isActive ? 0 : -1}
									data-tab-index={index}
									onClick={(e) => {
										// Ignore clicks routed through the inline
										// edit input or an action button — they
										// handle their own focus/click behavior.
										if (
											e.target.closest(
												'.dsgo-tabs__tab-title--editor, .dsgo-tabs__tab-actions'
											)
										) {
											return;
										}
										handleTabClick(index);
									}}
									onKeyDown={(e) => handleKeyDown(e, index)}
								>
									{icon && iconPosition === 'left' && (
										<span className="dsgo-tabs__tab-icon">
											{getIcon(icon)}
										</span>
									)}

									{icon && iconPosition === 'top' && (
										<span className="dsgo-tabs__tab-icon-top">
											{getIcon(icon)}
										</span>
									)}

									<input
										type="text"
										className="dsgo-tabs__tab-title dsgo-tabs__tab-title--editor"
										value={title || ''}
										placeholder={placeholderLabel}
										onFocus={() => handleTabClick(index)}
										onChange={(e) =>
											handleTitleChange(
												block,
												e.target.value
											)
										}
										onKeyDown={(e) => {
											// Don't let navigation keys from the
											// title input bubble up and trigger
											// the tab's arrow-key navigation —
											// those should move the text caret.
											if (
												[
													'ArrowLeft',
													'ArrowRight',
													'ArrowUp',
													'ArrowDown',
													'Home',
													'End',
												].includes(e.key)
											) {
												e.stopPropagation();
											}
										}}
										aria-label={__(
											'Tab title',
											'designsetgo'
										)}
									/>

									{icon && iconPosition === 'right' && (
										<span className="dsgo-tabs__tab-icon">
											{getIcon(icon)}
										</span>
									)}

									<span className="dsgo-tabs__tab-actions">
										<Tooltip
											text={__(
												'Duplicate tab',
												'designsetgo'
											)}
										>
											<Button
												size="small"
												icon={copy}
												label={__(
													'Duplicate tab',
													'designsetgo'
												)}
												onClick={() =>
													handleDuplicateTab(
														block,
														index
													)
												}
											/>
										</Tooltip>
										<Tooltip
											text={__(
												'Remove tab',
												'designsetgo'
											)}
										>
											<Button
												size="small"
												icon={trash}
												isDestructive
												label={__(
													'Remove tab',
													'designsetgo'
												)}
												onClick={() =>
													handleRemoveTab(
														block,
														index
													)
												}
											/>
										</Tooltip>
									</span>
								</div>
							);
						})}
					</div>
					{/* "Add tab" sits outside the tablist so it doesn't violate the
				    ARIA tab pattern (a tablist should only contain role="tab"
				    children). */}
					<Button
						size="small"
						icon={plus}
						className="dsgo-tabs__add-tab"
						onClick={handleAddTab}
					>
						{__('Add tab', 'designsetgo')}
					</Button>
				</div>

				{/* Tab Panels - Use spread props pattern */}
				<div {...innerBlocksProps} />
			</div>
		</>
	);
}
