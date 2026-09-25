/**
 * Comparison Table Block - Save Component
 *
 * Generates the static frontend HTML for the comparison table.
 * Includes data attributes for tooltip behavior handled by view.js.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

/**
 * Save component for the Comparison Table block
 *
 * @param {Object} props            - Component props
 * @param {Object} props.attributes - Block attributes
 * @return {JSX.Element} Comparison Table save markup
 */
export default function ComparisonTableSave({ attributes }) {
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
		featuredBadgeText,
		savedCtaTexts,
	} = attributes;

	// Fallback labels are read back from the stored markup, so opening the
	// post in another editor language keeps the block valid. Stored CTA links
	// appear in column order, one per column that has a link.
	const ctaFallback = (colIndex) =>
		savedCtaTexts?.[
			columns.slice(0, colIndex).filter((col) => col.link).length
		]?.text || __('Get Started', 'designsetgo');

	const blockProps = useBlockProps.save({
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
		<div {...blockProps}>
			<div className="dsgo-comparison-table__wrapper">
				<table className="dsgo-comparison-table__table">
					{/* Header Row */}
					<thead className="dsgo-comparison-table__header">
						<tr>
							{/* Feature label column header */}
							<th className="dsgo-comparison-table__header-cell dsgo-comparison-table__header-cell--label"></th>

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
											{featuredBadgeText ||
												__('Popular', 'designsetgo')}
										</span>
									)}

									<RichText.Content
										tagName="span"
										className="dsgo-comparison-table__column-name"
										value={col.name}
									/>

									{showCtaButtons && col.link && (
										<a
											href={col.link}
											className={`dsgo-comparison-table__cta dsgo-comparison-table__cta--${ctaStyle}`}
											rel="noopener noreferrer"
										>
											{col.linkText ||
												ctaFallback(colIndex)}
										</a>
									)}

									{showCtaButtons &&
										!col.link &&
										col.linkText && (
											<span
												className={`dsgo-comparison-table__cta dsgo-comparison-table__cta--${ctaStyle}`}
											>
												{col.linkText}
											</span>
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
										<RichText.Content
											tagName="span"
											className="dsgo-comparison-table__row-label"
											value={row.label}
										/>
										{row.tooltip && (
											<span
												className="dsgo-comparison-table__tooltip-trigger"
												data-tooltip={row.tooltip}
												aria-label={row.tooltip}
												role="button"
												tabIndex="0"
											>
												?
											</span>
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
										data-label={
											columns[colIndex]?.name || ''
										}
									>
										<div className="dsgo-comparison-table__cell-content">
											{cell.type === 'check' && (
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
													aria-label="Yes"
													role="img"
												>
													<polyline points="20 6 9 17 4 12" />
												</svg>
											)}

											{cell.type === 'cross' && (
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
													aria-label="No"
													role="img"
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
											)}

											{cell.type === 'text' && (
												<RichText.Content
													tagName="span"
													className="dsgo-comparison-table__cell-text"
													value={cell.value}
												/>
											)}
										</div>
									</td>
								))}
							</tr>
						))}
					</tbody>
				</table>
			</div>
		</div>
	);
}
