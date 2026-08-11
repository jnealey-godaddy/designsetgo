/**
 * DSG Grid Block - Edit Component
 *
 * Responsive multi-column grid layouts.
 *
 * @since 1.0.0
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InnerBlocks,
	InspectorControls,
	BlockControls,
	AlignmentControl,
	store as blockEditorStore,
	useSettings,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	RangeControl,
	SelectControl,
	ToggleControl,
	ToolbarGroup,
	ToolbarDropdownMenu,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUnitControl as UnitControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseCustomUnits as useCustomUnits,
} from '@wordpress/components';
import { grid as gridIcon } from '@wordpress/icons';
import { DsgoInspectorPanel } from '../../components/shared';
import { useState, useEffect, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import {
	hasOverlayStyleClass,
	hoverVariationClasses,
} from '../../utils/style-variation-classes';
import { useGridRowMatch } from './utils/use-grid-row-match';
import { getGridTemplateColumns } from './grid-columns';
import { useRenderedColumns } from './utils/use-rendered-columns';

/**
 * Grid Container Edit Component
 *
 * @param {Object}   props               Component props
 * @param {Object}   props.attributes    Block attributes
 * @param {Function} props.setAttributes Function to update attributes
 * @param {string}   props.clientId      Block client ID
 * @return {JSX.Element} Edit component
 */
export default function GridEdit({ attributes, setAttributes, clientId }) {
	const {
		align,
		className,
		tagName = 'div',
		constrainWidth,
		contentWidth,
		columnMinWidth,
		desktopColumns,
		tabletColumns,
		mobileColumns,
		rowGap,
		columnGap,
		alignItems,
		matchRowHeights,
		textAlign,
		overlayColor,
		hoverBackgroundColor,
		hoverTextColor,
		hoverIconBackgroundColor,
		hoverButtonBackgroundColor,
		style,
	} = attributes;

	// Auto-migrate old blocks that use className for alignment
	useEffect(() => {
		if (!align && className) {
			let newAlign;
			if (className.includes('alignfull')) {
				newAlign = 'full';
			} else if (className.includes('alignwide')) {
				newAlign = 'wide';
			}

			if (newAlign) {
				const cleanClassName = className
					.split(' ')
					.filter((cls) => cls !== 'alignfull' && cls !== 'alignwide')
					.join(' ')
					.trim();

				setAttributes({
					align: newAlign,
					className: cleanClassName || undefined,
				});
			}
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []); // Run only once on mount

	// Get theme settings (WP 6.5+)
	const [themeContentSize] = useSettings('layout.contentSize');

	// Get theme color palette and gradient settings
	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Get available spacing units
	const units = useCustomUnits({
		availableUnits: ['px', 'em', 'rem', 'vh', 'vw', '%'],
	});

	const [useCustomGaps, setUseCustomGaps] = useState(!!(rowGap || columnGap));

	// Get inner blocks to determine if container is empty
	const hasInnerBlocks = useSelect(
		(select) => {
			const { getBlock } = select(blockEditorStore);
			const block = getBlock(clientId);
			return block?.innerBlocks?.length > 0;
		},
		[clientId]
	);

	// "Align Rows": derive whether the subgrid is active and the per-card row
	// count to publish (`--dsgo-row-count` + activation class). The frontend
	// measures this from the DOM in view.js; in the editor the hook reads it
	// from the block tree and the previewed device width.
	const { isRowMatchActive: isRowMatchConfigured, matchRowCount } =
		useGridRowMatch(clientId, {
			matchRowHeights,
			desktopColumns,
			tabletColumns,
			mobileColumns,
		});

	// The configured column count is an upper bound — a column min width can
	// drop a column rather than overflow (see ./grid-columns.js). Row matching
	// is only meaningful once the grid actually renders 2+ columns; below that
	// the row-track spans just absorb row gaps and inflate every card.
	const innerRef = useRef();
	const renderedColumns = useRenderedColumns(innerRef, desktopColumns || 3);
	const isRowMatchActive = isRowMatchConfigured && renderedColumns > 1;

	// Block wrapper props - outer div stays full width (must match save.js EXACTLY)
	const hasOverlay = !!overlayColor || hasOverlayStyleClass(className);
	const TagName = tagName || 'div';
	const blockProps = useBlockProps({
		className: [
			'dsgo-grid',
			`dsgo-grid-cols-${desktopColumns}`,
			`dsgo-grid-cols-tablet-${tabletColumns}`,
			`dsgo-grid-cols-mobile-${mobileColumns}`,
			matchRowHeights && 'dsgo-grid--match-rows',
			hasOverlay && 'dsgo-grid--has-overlay',
			...hoverVariationClasses(className, 'dsgo-grid'),
		]
			.filter(Boolean)
			.join(' '),
		style: {
			...(hoverBackgroundColor && {
				'--dsgo-hover-bg-color':
					convertColorToCSSVar(hoverBackgroundColor),
			}),
			...(hoverTextColor && {
				'--dsgo-hover-text-color': convertColorToCSSVar(hoverTextColor),
			}),
			...(hoverIconBackgroundColor && {
				'--dsgo-parent-hover-icon-bg': convertColorToCSSVar(
					hoverIconBackgroundColor
				),
			}),
			...(hoverButtonBackgroundColor && {
				'--dsgo-parent-hover-button-bg': convertColorToCSSVar(
					hoverButtonBackgroundColor
				),
			}),
			...(overlayColor && {
				'--dsgo-overlay-color': convertColorToCSSVar(overlayColor),
				'--dsgo-overlay-opacity': '0.8',
			}),
		},
	});

	// Calculate inner styles declaratively (must match save.js EXACTLY)
	// IMPORTANT: Always provide a default gap to prevent overlapping items
	// Priority: blockGap (WordPress spacing) → custom rowGap/columnGap → preset fallback
	// WordPress 6.1+ stores blockGap as object {top, left} for separate row/column gaps
	// Also need to convert preset format (var:preset|spacing|X) to CSS variable
	const blockGapValue = style?.spacing?.blockGap;
	const isBlockGapObject =
		typeof blockGapValue === 'object' && blockGapValue !== null;
	const blockGapRow = convertPresetToCSSVar(
		isBlockGapObject ? blockGapValue?.top : blockGapValue
	);
	const blockGapColumn = convertPresetToCSSVar(
		isBlockGapObject ? blockGapValue?.left : blockGapValue
	);
	const defaultGap = 'var(--wp--preset--spacing--50)';
	const resolvedColumnGap = blockGapColumn || columnGap || defaultGap;

	const innerStyles = {
		display: 'grid',
		gridTemplateColumns: getGridTemplateColumns(
			columnMinWidth,
			desktopColumns,
			resolvedColumnGap
		),
		alignItems: alignItems || 'stretch',
		rowGap: blockGapRow || rowGap || defaultGap,
		columnGap: resolvedColumnGap,
	};

	// Apply width constraints if enabled
	// Use custom contentWidth if set, otherwise fallback to theme's contentSize
	if (constrainWidth) {
		innerStyles.maxWidth = contentWidth || themeContentSize || '1140px';
		innerStyles.marginLeft = 'auto';
		innerStyles.marginRight = 'auto';
	}

	// Row-matching: publish the per-card span so the subgrid CSS can activate.
	if (isRowMatchActive) {
		innerStyles['--dsgo-row-count'] = matchRowCount;
	}

	// Merge inner blocks props with inner styles
	const innerBlocksProps = useInnerBlocksProps(
		{
			ref: innerRef,
			className: [
				'dsgo-grid__inner',
				isRowMatchActive && 'dsgo-grid__inner--rows-matched',
			]
				.filter(Boolean)
				.join(' '),
			style: innerStyles,
		},
		{
			templateLock: false,
			renderAppender: hasInnerBlocks
				? undefined
				: InnerBlocks.ButtonBlockAppender,
		}
	);

	return (
		<>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarDropdownMenu
						icon={gridIcon}
						label={__('Desktop columns', 'designsetgo')}
						controls={Array.from(
							{ length: 12 },
							(_unused, i) => i + 1
						).map((count) => ({
							title: sprintf(
								/* translators: %d: number of columns */
								_n(
									'%d Column',
									'%d Columns',
									count,
									'designsetgo'
								),
								count
							),
							isActive: desktopColumns === count,
							onClick: () => {
								const newTabletCols = Math.min(
									tabletColumns,
									count
								);
								const newMobileCols = Math.min(
									mobileColumns,
									newTabletCols
								);
								setAttributes({
									desktopColumns: count,
									...(newTabletCols !== tabletColumns && {
										tabletColumns: newTabletCols,
									}),
									...(newMobileCols !== mobileColumns && {
										mobileColumns: newMobileCols,
									}),
								});
							},
						}))}
					/>
				</ToolbarGroup>
				<AlignmentControl
					value={textAlign}
					onChange={(newAlign) =>
						setAttributes({ textAlign: newAlign })
					}
				/>
			</BlockControls>

			<InspectorControls group="advanced">
				<SelectControl
					label={__('HTML Element', 'designsetgo')}
					value={tagName}
					options={[
						{
							label: __('Default (<div>)', 'designsetgo'),
							value: 'div',
						},
						{ label: '<section>', value: 'section' },
						{ label: '<article>', value: 'article' },
						{ label: '<aside>', value: 'aside' },
						{ label: '<header>', value: 'header' },
						{ label: '<footer>', value: 'footer' },
						{ label: '<main>', value: 'main' },
					]}
					onChange={(value) => setAttributes({ tagName: value })}
					help={__(
						'Choose the HTML element for this block. Use semantic elements when appropriate for better accessibility.',
						'designsetgo'
					)}
					__nextHasNoMarginBottom
				/>
			</InspectorControls>

			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Hover Settings', 'designsetgo')}
					settings={[
						{
							label: __('Overlay Color', 'designsetgo'),
							colorValue: decodeColorValue(
								overlayColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									overlayColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Hover Background Color', 'designsetgo'),
							colorValue: decodeColorValue(
								hoverBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									hoverBackgroundColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Hover Text Color', 'designsetgo'),
							colorValue: decodeColorValue(
								hoverTextColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									hoverTextColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						// Only show icon background control if hover background is set
						...(hoverBackgroundColor
							? [
									{
										label: __(
											'Hover Icon Background Color',
											'designsetgo'
										),
										colorValue: decodeColorValue(
											hoverIconBackgroundColor,
											colorGradientSettings
										),
										onColorChange: (color) =>
											setAttributes({
												hoverIconBackgroundColor:
													encodeColorValue(
														color,
														colorGradientSettings
													) || '',
											}),
										enableAlpha: true,
										clearable: true,
									},
								]
							: []),
						// Only show button background control if hover background is set
						...(hoverBackgroundColor
							? [
									{
										label: __(
											'Hover Button Background Color',
											'designsetgo'
										),
										colorValue: decodeColorValue(
											hoverButtonBackgroundColor,
											colorGradientSettings
										),
										onColorChange: (color) =>
											setAttributes({
												hoverButtonBackgroundColor:
													encodeColorValue(
														color,
														colorGradientSettings
													) || '',
											}),
										enableAlpha: true,
										clearable: true,
									},
								]
							: []),
					]}
					{...colorGradientSettings}
				/>
			</InspectorControls>

			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() => {
						setUseCustomGaps(false);
						setAttributes({
							desktopColumns: 3,
							tabletColumns: 2,
							mobileColumns: 1,
							alignItems: 'stretch',
							matchRowHeights: false,
							rowGap: '',
							columnGap: '',
							constrainWidth: false,
							contentWidth: '',
							columnMinWidth: '',
						});
					}}
				>
					<DsgoInspectorPanel.Item
						label={__('Desktop Columns', 'designsetgo')}
						hasValue={() => desktopColumns !== 3}
						onDeselect={() =>
							setAttributes({
								desktopColumns: 3,
								tabletColumns: Math.min(tabletColumns, 3),
								mobileColumns: Math.min(
									mobileColumns,
									Math.min(tabletColumns, 3)
								),
							})
						}
						isShownByDefault
					>
						<RangeControl
							label={__('Desktop Columns', 'designsetgo')}
							value={desktopColumns}
							onChange={(value) => {
								// Ensure tablet columns don't exceed desktop
								const newTabletCols = Math.min(
									tabletColumns,
									value
								);
								// Ensure mobile columns don't exceed tablet
								const newMobileCols = Math.min(
									mobileColumns,
									newTabletCols
								);

								setAttributes({
									desktopColumns: value,
									...(newTabletCols !== tabletColumns && {
										tabletColumns: newTabletCols,
									}),
									...(newMobileCols !== mobileColumns && {
										mobileColumns: newMobileCols,
									}),
								});
							}}
							min={1}
							max={12}
							help={__(
								'Number of columns on desktop screens',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Tablet Columns', 'designsetgo')}
						hasValue={() => tabletColumns !== 2}
						onDeselect={() =>
							setAttributes({
								tabletColumns: 2,
								mobileColumns: Math.min(mobileColumns, 2),
							})
						}
						isShownByDefault
					>
						<RangeControl
							label={__('Tablet Columns', 'designsetgo')}
							value={tabletColumns}
							onChange={(value) => {
								// Ensure mobile columns don't exceed tablet
								const newMobileCols = Math.min(
									mobileColumns,
									value
								);

								setAttributes({
									tabletColumns: value,
									...(newMobileCols !== mobileColumns && {
										mobileColumns: newMobileCols,
									}),
								});
							}}
							min={1}
							max={desktopColumns}
							help={__(
								'Number of columns on tablet screens (≤ 1024px)',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Mobile Columns', 'designsetgo')}
						hasValue={() => mobileColumns !== 1}
						onDeselect={() => setAttributes({ mobileColumns: 1 })}
						isShownByDefault
					>
						<RangeControl
							label={__('Mobile Columns', 'designsetgo')}
							value={mobileColumns}
							onChange={(value) =>
								setAttributes({ mobileColumns: value })
							}
							min={1}
							max={tabletColumns}
							help={__(
								'Number of columns on mobile screens (≤ 767px)',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Align Items', 'designsetgo')}
						hasValue={() => alignItems !== 'stretch'}
						onDeselect={() =>
							setAttributes({ alignItems: 'stretch' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Align Items', 'designsetgo')}
							value={alignItems}
							options={[
								{
									label: __('Start', 'designsetgo'),
									value: 'start',
								},
								{
									label: __('Center', 'designsetgo'),
									value: 'center',
								},
								{
									label: __('End', 'designsetgo'),
									value: 'end',
								},
								{
									label: __('Stretch', 'designsetgo'),
									value: 'stretch',
								},
							]}
							onChange={(value) =>
								setAttributes({ alignItems: value })
							}
							help={__(
								'Vertical alignment of grid items',
								'designsetgo'
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Align Rows', 'designsetgo')}
						hasValue={() => matchRowHeights === true}
						onDeselect={() =>
							setAttributes({ matchRowHeights: false })
						}
						isShownByDefault
					>
						<ToggleControl
							label={__('Align Rows', 'designsetgo')}
							checked={!!matchRowHeights}
							onChange={(value) =>
								setAttributes({ matchRowHeights: value })
							}
							help={__(
								'Line up each row of card content (images, headings, buttons) across columns, so cards with different amounts of text stay aligned. Best with cards that share the same structure.',
								'designsetgo'
							)}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Custom Row/Column Gaps', 'designsetgo')}
						hasValue={() => useCustomGaps}
						onDeselect={() => {
							setUseCustomGaps(false);
							setAttributes({ rowGap: '', columnGap: '' });
						}}
						isShownByDefault
					>
						<ToggleControl
							label={__('Custom Row/Column Gaps', 'designsetgo')}
							checked={useCustomGaps}
							onChange={(value) => {
								setUseCustomGaps(value);
								if (!value) {
									// Reset to WordPress blockGap
									setAttributes({
										rowGap: '',
										columnGap: '',
									});
								}
							}}
							help={
								useCustomGaps
									? __(
											'Using separate row and column gaps',
											'designsetgo'
										)
									: __(
											'Using WordPress blockGap (configure in Spacing panel)',
											'designsetgo'
										)
							}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{useCustomGaps && (
						<DsgoInspectorPanel.Item
							label={__('Row Gap', 'designsetgo')}
							hasValue={() => rowGap !== ''}
							onDeselect={() => setAttributes({ rowGap: '' })}
							isShownByDefault
						>
							<UnitControl
								label={__('Row Gap', 'designsetgo')}
								value={rowGap}
								onChange={(value) =>
									setAttributes({ rowGap: value })
								}
								units={units}
								isResetValueOnUnitChange
								__unstableInputWidth="80px"
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					{useCustomGaps && (
						<DsgoInspectorPanel.Item
							label={__('Column Gap', 'designsetgo')}
							hasValue={() => columnGap !== ''}
							onDeselect={() => setAttributes({ columnGap: '' })}
							isShownByDefault
						>
							<UnitControl
								label={__('Column Gap', 'designsetgo')}
								value={columnGap}
								onChange={(value) =>
									setAttributes({ columnGap: value })
								}
								units={units}
								isResetValueOnUnitChange
								__unstableInputWidth="80px"
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					<DsgoInspectorPanel.Item
						label={__('Constrain Inner Width', 'designsetgo')}
						hasValue={() => constrainWidth !== false}
						onDeselect={() =>
							setAttributes({
								constrainWidth: false,
								contentWidth: '',
							})
						}
						isShownByDefault
					>
						<ToggleControl
							label={__('Constrain Inner Width', 'designsetgo')}
							checked={constrainWidth}
							onChange={(value) =>
								setAttributes({ constrainWidth: value })
							}
							help={
								constrainWidth
									? __(
											'Inner content is constrained to max width',
											'designsetgo'
										)
									: __(
											'Inner content spans full container width',
											'designsetgo'
										)
							}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{constrainWidth && (
						<DsgoInspectorPanel.Item
							label={__('Max Content Width', 'designsetgo')}
							hasValue={() => contentWidth !== ''}
							onDeselect={() =>
								setAttributes({ contentWidth: '' })
							}
							isShownByDefault
						>
							<UnitControl
								label={__('Max Content Width', 'designsetgo')}
								value={contentWidth}
								onChange={(value) =>
									setAttributes({ contentWidth: value })
								}
								placeholder={
									themeContentSize ||
									__('Theme default', 'designsetgo')
								}
								units={units}
								__unstableInputWidth="80px"
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								help={
									!contentWidth && themeContentSize
										? sprintf(
												/* translators: %s: theme content size value */
												__(
													'Using theme default: %s',
													'designsetgo'
												),
												themeContentSize
											)
										: ''
								}
							/>
						</DsgoInspectorPanel.Item>
					)}

					<DsgoInspectorPanel.Item
						label={__('Column Min Width', 'designsetgo')}
						hasValue={() => columnMinWidth !== ''}
						onDeselect={() => setAttributes({ columnMinWidth: '' })}
						isShownByDefault
					>
						<UnitControl
							label={__('Column Min Width', 'designsetgo')}
							value={columnMinWidth}
							onChange={(value) =>
								setAttributes({ columnMinWidth: value || '' })
							}
							units={units}
							isResetValueOnUnitChange
							__unstableInputWidth="80px"
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							help={__(
								'Sets a minimum width for each column. Columns never get narrower than this — if they would, one wraps to the next row instead of overflowing the container. Columns still drop to the tablet/mobile counts on smaller screens.',
								'designsetgo'
							)}
						/>
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>

			<TagName {...blockProps}>
				<div {...innerBlocksProps} />
			</TagName>
		</>
	);
}
