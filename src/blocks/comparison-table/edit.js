/**
 * Comparison Table Block - Edit Component
 *
 * Provides a table editor with inline editing for column headers,
 * row labels, and cell content. Supports multiple cell types
 * (text, check, cross) and column management.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	ToggleControl,
	SelectControl,
	Button,
	TextControl,
	Tooltip,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../components/shared';
import { useState } from '@wordpress/element';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../utils/encode-color-value';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

const DEFAULT_COLUMNS = [
	{ name: 'Basic', link: '', linkText: 'Get Started', featured: false },
	{ name: 'Pro', link: '', linkText: 'Get Started', featured: true },
	{ name: 'Enterprise', link: '', linkText: 'Contact Us', featured: false },
];
const DEFAULT_ROWS = [
	{
		label: 'Storage',
		tooltip: '',
		cells: [
			{ type: 'text', value: '5 GB' },
			{ type: 'text', value: '50 GB' },
			{ type: 'text', value: 'Unlimited' },
		],
	},
	{
		label: 'Users',
		tooltip: '',
		cells: [
			{ type: 'text', value: '1' },
			{ type: 'text', value: '10' },
			{ type: 'text', value: 'Unlimited' },
		],
	},
	{
		label: 'Priority Support',
		tooltip: 'Get faster response times from our team',
		cells: [
			{ type: 'cross', value: '' },
			{ type: 'check', value: '' },
			{ type: 'check', value: '' },
		],
	},
	{
		label: 'API Access',
		tooltip: '',
		cells: [
			{ type: 'cross', value: '' },
			{ type: 'check', value: '' },
			{ type: 'check', value: '' },
		],
	},
];

/**
 * Check icon SVG for cell display
 *
 * @return {JSX.Element} Check icon
 */
const CheckIcon = () => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="20"
		height="20"
		fill="none"
		stroke="currentColor"
		strokeWidth="2.5"
		strokeLinecap="round"
		strokeLinejoin="round"
		className="dsgo-comparison-table__icon dsgo-comparison-table__icon--check"
	>
		<polyline points="20 6 9 17 4 12" />
	</svg>
);

/**
 * Cross icon SVG for cell display
 *
 * @return {JSX.Element} Cross icon
 */
const CrossIcon = () => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="20"
		height="20"
		fill="none"
		stroke="currentColor"
		strokeWidth="2.5"
		strokeLinecap="round"
		strokeLinejoin="round"
		className="dsgo-comparison-table__icon dsgo-comparison-table__icon--cross"
	>
		<line x1="18" y1="6" x2="6" y2="18" />
		<line x1="6" y1="6" x2="18" y2="18" />
	</svg>
);

/**
 * Renders the content of a single table cell based on its type
 *
 * @param {Object}   props              - Component props
 * @param {Object}   props.cell         - Cell data object
 * @param {number}   props.rowIndex     - Row index
 * @param {number}   props.colIndex     - Column index
 * @param {Function} props.onCellChange - Callback for cell changes
 * @return {JSX.Element} Cell content
 */
function CellContent({ cell, rowIndex, colIndex, onCellChange }) {
	if (cell.type === 'check') {
		return <CheckIcon />;
	}

	if (cell.type === 'cross') {
		return <CrossIcon />;
	}

	return (
		<RichText
			tagName="span"
			className="dsgo-comparison-table__cell-text"
			value={cell.value}
			onChange={(value) => onCellChange(rowIndex, colIndex, { value })}
			placeholder={__('--', 'designsetgo')}
			allowedFormats={['core/bold', 'core/italic']}
		/>
	);
}

/**
 * Edit component for the Comparison Table block
 *
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @param {string}   props.clientId      - Block client ID
 * @return {JSX.Element} Comparison Table edit component
 */
export default function ComparisonTableEdit({
	attributes,
	setAttributes,
	clientId,
}) {
	const {
		columns,
		rows,
		alternatingRows,
		responsiveMode,
		featuredColumnColor,
		headerBackgroundColor,
		headerTextColor,
		showCtaButtons,
		ctaStyle,
	} = attributes;

	const [selectedCell, setSelectedCell] = useState(null);

	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	/**
	 * Updates a specific column attribute
	 *
	 * @param {number} colIndex - Column index to update
	 * @param {Object} changes  - Object of attribute changes
	 */
	const updateColumn = (colIndex, changes) => {
		const newColumns = columns.map((col, i) =>
			i === colIndex ? { ...col, ...changes } : col
		);
		setAttributes({ columns: newColumns });
	};

	/**
	 * Updates a specific row attribute
	 *
	 * @param {number} rowIndex - Row index to update
	 * @param {Object} changes  - Object of attribute changes
	 */
	const updateRow = (rowIndex, changes) => {
		const newRows = rows.map((row, i) =>
			i === rowIndex ? { ...row, ...changes } : row
		);
		setAttributes({ rows: newRows });
	};

	/**
	 * Updates a specific cell within a row
	 *
	 * @param {number} rowIndex - Row index
	 * @param {number} colIndex - Column index
	 * @param {Object} changes  - Object of attribute changes
	 */
	const updateCell = (rowIndex, colIndex, changes) => {
		const newRows = rows.map((row, rIdx) => {
			if (rIdx !== rowIndex) {
				return row;
			}
			const newCells = row.cells.map((cell, cIdx) =>
				cIdx === colIndex ? { ...cell, ...changes } : cell
			);
			return { ...row, cells: newCells };
		});
		setAttributes({ rows: newRows });
	};

	/**
	 * Adds a new column to the table
	 */
	const addColumn = () => {
		if (columns.length >= 6) {
			return;
		}
		const newColumns = [
			...columns,
			{
				name: __('Plan', 'designsetgo'),
				link: '',
				linkText: __('Get Started', 'designsetgo'),
				featured: false,
			},
		];
		const newRows = rows.map((row) => ({
			...row,
			cells: [...row.cells, { type: 'text', value: '' }],
		}));
		setAttributes({ columns: newColumns, rows: newRows });
	};

	/**
	 * Removes a column from the table
	 *
	 * @param {number} colIndex - Column index to remove
	 */
	const removeColumn = (colIndex) => {
		if (columns.length <= 2) {
			return;
		}
		const newColumns = columns.filter((_, i) => i !== colIndex);
		const newRows = rows.map((row) => ({
			...row,
			cells: row.cells.filter((_, i) => i !== colIndex),
		}));
		setAttributes({ columns: newColumns, rows: newRows });
	};

	/**
	 * Adds a new row to the table
	 */
	const addRow = () => {
		const newRow = {
			label: __('Feature', 'designsetgo'),
			tooltip: '',
			cells: columns.map(() => ({ type: 'text', value: '' })),
		};
		setAttributes({ rows: [...rows, newRow] });
	};

	/**
	 * Removes a row from the table
	 *
	 * @param {number} rowIndex - Row index to remove
	 */
	const removeRow = (rowIndex) => {
		if (rows.length <= 1) {
			return;
		}
		setAttributes({ rows: rows.filter((_, i) => i !== rowIndex) });
	};

	/**
	 * Moves a row up or down in the table
	 *
	 * @param {number} rowIndex  - Row index to move
	 * @param {string} direction - 'up' or 'down'
	 */
	const moveRow = (rowIndex, direction) => {
		const newRows = [...rows];
		const targetIndex = direction === 'up' ? rowIndex - 1 : rowIndex + 1;
		if (targetIndex < 0 || targetIndex >= rows.length) {
			return;
		}
		[newRows[rowIndex], newRows[targetIndex]] = [
			newRows[targetIndex],
			newRows[rowIndex],
		];
		setAttributes({ rows: newRows });
	};

	const blockProps = useBlockProps({
		className: [
			'dsgo-comparison-table',
			alternatingRows && 'dsgo-comparison-table--alternating',
			responsiveMode === 'stack' &&
				'dsgo-comparison-table--responsive-stack',
			responsiveMode === 'scroll' &&
				'dsgo-comparison-table--responsive-scroll',
		]
			.filter(Boolean)
			.join(' '),
		style: {
			...(featuredColumnColor && {
				'--dsgo-comparison-featured-color':
					convertColorToCSSVar(featuredColumnColor),
			}),
			...(headerBackgroundColor && {
				'--dsgo-comparison-header-bg': convertColorToCSSVar(
					headerBackgroundColor
				),
			}),
			...(headerTextColor && {
				'--dsgo-comparison-header-text':
					convertColorToCSSVar(headerTextColor),
			}),
		},
	});

	return (
		<>
			{/* Color Controls */}
			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					panelId={clientId}
					title={__('Table Colors', 'designsetgo')}
					settings={[
						{
							label: __('Header Background', 'designsetgo'),
							colorValue: decodeColorValue(
								headerBackgroundColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									headerBackgroundColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __('Header Text', 'designsetgo'),
							colorValue: decodeColorValue(
								headerTextColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									headerTextColor:
										encodeColorValue(
											color,
											colorGradientSettings
										) || '',
								}),
							enableAlpha: true,
							clearable: true,
						},
						{
							label: __(
								'Featured Column Highlight',
								'designsetgo'
							),
							colorValue: decodeColorValue(
								featuredColumnColor,
								colorGradientSettings
							),
							onColorChange: (color) =>
								setAttributes({
									featuredColumnColor:
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

			{/* Table Settings */}
			<InspectorControls>
				<DsgoInspectorPanel
					title={__('Settings', 'designsetgo')}
					panelName="settings"
					panelId={clientId}
					resetAll={() =>
						setAttributes({
							alternatingRows: true,
							responsiveMode: 'scroll',
							showCtaButtons: true,
							ctaStyle: 'filled',
							columns: DEFAULT_COLUMNS,
							rows: DEFAULT_ROWS,
						})
					}
				>
					<DsgoInspectorPanel.Item
						label={__('Alternating Row Colors', 'designsetgo')}
						hasValue={() => alternatingRows !== true}
						onDeselect={() =>
							setAttributes({ alternatingRows: true })
						}
						isShownByDefault
					>
						<ToggleControl
							label={__('Alternating Row Colors', 'designsetgo')}
							checked={alternatingRows}
							onChange={(value) =>
								setAttributes({ alternatingRows: value })
							}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Responsive Mode', 'designsetgo')}
						hasValue={() => responsiveMode !== 'scroll'}
						onDeselect={() =>
							setAttributes({ responsiveMode: 'scroll' })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Responsive Mode', 'designsetgo')}
							value={responsiveMode}
							options={[
								{
									label: __(
										'Horizontal Scroll',
										'designsetgo'
									),
									value: 'scroll',
								},
								{
									label: __('Stack on Mobile', 'designsetgo'),
									value: 'stack',
								},
							]}
							onChange={(value) =>
								setAttributes({ responsiveMode: value })
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Show CTA Buttons', 'designsetgo')}
						hasValue={() => showCtaButtons !== true}
						onDeselect={() =>
							setAttributes({ showCtaButtons: true })
						}
						isShownByDefault
					>
						<ToggleControl
							label={__('Show CTA Buttons', 'designsetgo')}
							checked={showCtaButtons}
							onChange={(value) =>
								setAttributes({ showCtaButtons: value })
							}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>

					{showCtaButtons && (
						<DsgoInspectorPanel.Item
							label={__('CTA Style', 'designsetgo')}
							hasValue={() => ctaStyle !== 'filled'}
							onDeselect={() =>
								setAttributes({ ctaStyle: 'filled' })
							}
							isShownByDefault
						>
							<SelectControl
								label={__('CTA Style', 'designsetgo')}
								value={ctaStyle}
								options={[
									{
										label: __('Filled', 'designsetgo'),
										value: 'filled',
									},
									{
										label: __('Outlined', 'designsetgo'),
										value: 'outlined',
									},
								]}
								onChange={(value) =>
									setAttributes({ ctaStyle: value })
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)}

					<DsgoInspectorPanel.Item
						label={__('Columns', 'designsetgo')}
						hasValue={() =>
							JSON.stringify(columns) !==
							JSON.stringify(DEFAULT_COLUMNS)
						}
						onDeselect={() =>
							setAttributes({ columns: DEFAULT_COLUMNS })
						}
						isShownByDefault
					>
						{columns.map((col, colIndex) => (
							<div
								key={colIndex}
								className="dsgo-comparison-table-editor__column-settings"
							>
								<h4
									style={{
										margin: '0 0 8px',
										display: 'flex',
										alignItems: 'center',
										justifyContent: 'space-between',
									}}
								>
									{col.name || `Column ${colIndex + 1}`}
									{columns.length > 2 && (
										<Button
											icon="no-alt"
											label={__(
												'Remove column',
												'designsetgo'
											)}
											onClick={() =>
												removeColumn(colIndex)
											}
											isDestructive
											size="small"
										/>
									)}
								</h4>

								<ToggleControl
									label={__('Featured', 'designsetgo')}
									checked={col.featured}
									onChange={(value) =>
										updateColumn(colIndex, {
											featured: value,
										})
									}
									__nextHasNoMarginBottom
								/>

								{showCtaButtons && (
									<>
										<TextControl
											label={__(
												'CTA Link',
												'designsetgo'
											)}
											value={col.link}
											onChange={(value) =>
												updateColumn(colIndex, {
													link: value,
												})
											}
											placeholder="https://"
											__next40pxDefaultSize
											__nextHasNoMarginBottom
										/>
										<TextControl
											label={__(
												'CTA Text',
												'designsetgo'
											)}
											value={col.linkText}
											onChange={(value) =>
												updateColumn(colIndex, {
													linkText: value,
												})
											}
											__next40pxDefaultSize
											__nextHasNoMarginBottom
										/>
									</>
								)}

								{colIndex < columns.length - 1 && (
									<hr style={{ margin: '16px 0' }} />
								)}
							</div>
						))}

						{columns.length < 6 && (
							<Button
								variant="secondary"
								onClick={addColumn}
								style={{ marginTop: '12px', width: '100%' }}
							>
								{__('Add Column', 'designsetgo')}
							</Button>
						)}
					</DsgoInspectorPanel.Item>

					<DsgoInspectorPanel.Item
						label={__('Row Tooltips', 'designsetgo')}
						hasValue={() =>
							JSON.stringify(rows) !==
							JSON.stringify(DEFAULT_ROWS)
						}
						onDeselect={() => setAttributes({ rows: DEFAULT_ROWS })}
						isShownByDefault
					>
						{rows.map((row, rowIndex) => (
							<TextControl
								key={rowIndex}
								label={row.label || `Row ${rowIndex + 1}`}
								value={row.tooltip}
								onChange={(value) =>
									updateRow(rowIndex, { tooltip: value })
								}
								placeholder={__('Tooltip text…', 'designsetgo')}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						))}
					</DsgoInspectorPanel.Item>
				</DsgoInspectorPanel>
			</InspectorControls>

			{/* Block Content */}
			<div {...blockProps}>
				<div className="dsgo-comparison-table__wrapper">
					<table className="dsgo-comparison-table__table">
						{/* Header Row */}
						<thead className="dsgo-comparison-table__header">
							<tr>
								{/* Feature label column header */}
								<th className="dsgo-comparison-table__header-cell dsgo-comparison-table__header-cell--label">
									<span className="dsgo-comparison-table__header-label">
										{__('Features', 'designsetgo')}
									</span>
								</th>

								{/* Column headers */}
								{columns.map((col, colIndex) => (
									<th
										key={colIndex}
										className={[
											'dsgo-comparison-table__header-cell',
											col.featured &&
												'dsgo-comparison-table__header-cell--featured',
										]
											.filter(Boolean)
											.join(' ')}
									>
										{col.featured && (
											<span className="dsgo-comparison-table__featured-badge">
												{__('Popular', 'designsetgo')}
											</span>
										)}
										<RichText
											tagName="span"
											className="dsgo-comparison-table__column-name"
											value={col.name}
											onChange={(value) =>
												updateColumn(colIndex, {
													name: value,
												})
											}
											placeholder={__(
												'Plan Name',
												'designsetgo'
											)}
											allowedFormats={[]}
										/>

										{showCtaButtons && (
											<RichText
												tagName="span"
												className={`dsgo-comparison-table__cta dsgo-comparison-table__cta--${ctaStyle}`}
												value={col.linkText}
												onChange={(value) =>
													updateColumn(colIndex, {
														linkText: value,
													})
												}
												placeholder={__(
													'CTA Text',
													'designsetgo'
												)}
												allowedFormats={[]}
											/>
										)}
									</th>
								))}
							</tr>
						</thead>

						{/* Data Rows */}
						<tbody className="dsgo-comparison-table__body">
							{rows.map((row, rowIndex) => (
								<tr
									key={rowIndex}
									className="dsgo-comparison-table__row"
								>
									{/* Feature label */}
									<td className="dsgo-comparison-table__cell dsgo-comparison-table__cell--label">
										<div className="dsgo-comparison-table__label-wrapper">
											<RichText
												tagName="span"
												className="dsgo-comparison-table__row-label"
												value={row.label}
												onChange={(value) =>
													updateRow(rowIndex, {
														label: value,
													})
												}
												placeholder={__(
													'Feature name',
													'designsetgo'
												)}
												allowedFormats={[
													'core/bold',
													'core/italic',
												]}
											/>
											{row.tooltip && (
												<Tooltip text={row.tooltip}>
													<span className="dsgo-comparison-table__tooltip-trigger">
														?
													</span>
												</Tooltip>
											)}
										</div>

										{/* Row controls */}
										<div className="dsgo-comparison-table-editor__row-controls">
											<button
												type="button"
												className="dsgo-comparison-table-editor__row-btn"
												onClick={() =>
													moveRow(rowIndex, 'up')
												}
												disabled={rowIndex === 0}
												aria-label={__(
													'Move up',
													'designsetgo'
												)}
											>
												<svg
													width="16"
													height="16"
													viewBox="0 0 24 24"
													fill="none"
													stroke="currentColor"
													strokeWidth="2"
													strokeLinecap="round"
													strokeLinejoin="round"
												>
													<polyline points="18 15 12 9 6 15" />
												</svg>
											</button>
											<button
												type="button"
												className="dsgo-comparison-table-editor__row-btn"
												onClick={() =>
													moveRow(rowIndex, 'down')
												}
												disabled={
													rowIndex === rows.length - 1
												}
												aria-label={__(
													'Move down',
													'designsetgo'
												)}
											>
												<svg
													width="16"
													height="16"
													viewBox="0 0 24 24"
													fill="none"
													stroke="currentColor"
													strokeWidth="2"
													strokeLinecap="round"
													strokeLinejoin="round"
												>
													<polyline points="6 9 12 15 18 9" />
												</svg>
											</button>
											{rows.length > 1 && (
												<button
													type="button"
													className="dsgo-comparison-table-editor__row-btn dsgo-comparison-table-editor__row-btn--delete"
													onClick={() =>
														removeRow(rowIndex)
													}
													aria-label={__(
														'Remove row',
														'designsetgo'
													)}
												>
													<svg
														width="16"
														height="16"
														viewBox="0 0 24 24"
														fill="none"
														stroke="currentColor"
														strokeWidth="2"
														strokeLinecap="round"
														strokeLinejoin="round"
													>
														<line
															x1="18"
															y1="6"
															x2="6"
															y2="18"
														/>
														<line
															x1="6"
															y1="6"
															x2="18"
															y2="18"
														/>
													</svg>
												</button>
											)}
										</div>
									</td>

									{/* Cells */}
									{row.cells.map((cell, colIndex) => (
										<td
											key={colIndex}
											className={[
												'dsgo-comparison-table__cell',
												columns[colIndex]?.featured &&
													'dsgo-comparison-table__cell--featured',
											]
												.filter(Boolean)
												.join(' ')}
											onClick={() =>
												setSelectedCell({
													row: rowIndex,
													col: colIndex,
												})
											}
											onKeyDown={(e) => {
												if (
													e.key === 'Enter' ||
													e.key === ' '
												) {
													setSelectedCell({
														row: rowIndex,
														col: colIndex,
													});
												}
											}}
											role="button"
											tabIndex="0"
											aria-label={`${row.label || __('Feature', 'designsetgo')}, ${columns[colIndex]?.name || __('Column', 'designsetgo')}`}
										>
											<div className="dsgo-comparison-table__cell-content">
												<CellContent
													cell={cell}
													rowIndex={rowIndex}
													colIndex={colIndex}
													onCellChange={updateCell}
												/>
											</div>

											{/* Cell type toggle */}
											{selectedCell?.row === rowIndex &&
												selectedCell?.col ===
													colIndex && (
													<div className="dsgo-comparison-table-editor__cell-toolbar">
														<button
															type="button"
															className={`dsgo-comparison-table-editor__type-btn ${cell.type === 'text' ? 'is-active' : ''}`}
															onClick={() =>
																updateCell(
																	rowIndex,
																	colIndex,
																	{
																		type: 'text',
																	}
																)
															}
														>
															{__(
																'Aa',
																'designsetgo'
															)}
														</button>
														<button
															type="button"
															className={`dsgo-comparison-table-editor__type-btn ${cell.type === 'check' ? 'is-active' : ''}`}
															onClick={() =>
																updateCell(
																	rowIndex,
																	colIndex,
																	{
																		type: 'check',
																		value: '',
																	}
																)
															}
														>
															&#10003;
														</button>
														<button
															type="button"
															className={`dsgo-comparison-table-editor__type-btn ${cell.type === 'cross' ? 'is-active' : ''}`}
															onClick={() =>
																updateCell(
																	rowIndex,
																	colIndex,
																	{
																		type: 'cross',
																		value: '',
																	}
																)
															}
														>
															&#10005;
														</button>
													</div>
												)}
										</td>
									))}
								</tr>
							))}
						</tbody>
					</table>

					{/* Add Row Button */}
					<div className="dsgo-comparison-table-editor__add-row">
						<Button
							variant="secondary"
							onClick={addRow}
							icon="plus"
						>
							{__('Add Feature Row', 'designsetgo')}
						</Button>
					</div>
				</div>
			</div>
		</>
	);
}
