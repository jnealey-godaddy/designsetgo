/**
 * Star Rating — canvas preview.
 *
 * Reproduces render.php's markup so the editor and the frontend agree. The
 * block is server-rendered (bindings resolve there), so nothing here is saved
 * — this exists purely to be looked at.
 *
 * @since 2.8.0
 */

import { __, sprintf } from '@wordpress/i18n';
import { getIcon } from '../../icon/utils/svg-icons';
import {
	clampMaxRating,
	clampRating,
	formatCount,
	formatRatingValue,
	getFillPercent,
} from '../utils/rating';

/**
 * One row of icons. Track and fill render identical rows; the fill row is
 * clipped by width, which is what produces fractional stars.
 *
 * @param {Object} props           Component props.
 * @param {string} props.className Row class.
 * @param {number} props.count     Number of icons.
 * @param {string} props.icon      Icon name.
 * @param {string} props.iconStyle 'filled' | 'outlined'.
 * @return {JSX.Element} Icon row.
 */
function IconRow({ className, count, icon, iconStyle }) {
	return (
		<span className={className} aria-hidden="true">
			{Array.from({ length: count }, (_, index) => (
				<span className="dsgo-star-rating__star" key={index}>
					{getIcon(icon, iconStyle)}
				</span>
			))}
		</span>
	);
}

/**
 * Sentence read out in place of the icons.
 *
 * @param {number}  rating    Clamped rating.
 * @param {number}  maxRating Clamped maximum.
 * @param {number}  count     Rating count.
 * @param {boolean} showCount Whether the count is displayed.
 * @return {string} Screen-reader text.
 */
export function getRatingLabel(rating, maxRating, count, showCount) {
	const label = sprintf(
		/* translators: 1: rating value, 2: maximum rating. */
		__('Rated %1$s out of %2$s', 'designsetgo'),
		formatRatingValue(rating),
		formatRatingValue(maxRating)
	);

	if (!showCount || !count) {
		return label;
	}

	return sprintf(
		/* translators: 1: "Rated 4.5 out of 5", 2: number of ratings. */
		__('%1$s, based on %2$s ratings', 'designsetgo'),
		label,
		formatRatingValue(count)
	);
}

/**
 * @param {Object} props            Component props.
 * @param {Object} props.attributes Block attributes.
 * @param {string} props.className  Extra classes for the inner element.
 * @param {Object} props.style      Inline styles for the inner element.
 * @return {JSX.Element} Preview markup.
 */
export default function StarRatingPreview({ attributes, className, style }) {
	const {
		rating,
		maxRating,
		precision,
		icon,
		iconStyle,
		showValue,
		showMax,
		ratingCount,
		showCount,
		countTemplate,
	} = attributes;

	const max = clampMaxRating(maxRating);
	const value = clampRating(rating, max);
	const fill = getFillPercent(rating, maxRating, precision);

	return (
		<div
			className={['dsgo-star-rating__inner', className]
				.filter(Boolean)
				.join(' ')}
			style={style}
		>
			<span className="dsgo-star-rating__sr-text">
				{getRatingLabel(value, max, ratingCount, showCount)}
			</span>
			<span className="dsgo-star-rating__stars" aria-hidden="true">
				<IconRow
					className="dsgo-star-rating__track"
					count={max}
					icon={icon}
					iconStyle={iconStyle}
				/>
				<span
					className="dsgo-star-rating__fill-clip"
					style={{ width: `${fill}%` }}
				>
					<IconRow
						className="dsgo-star-rating__fill"
						count={max}
						icon={icon}
						iconStyle={iconStyle}
					/>
				</span>
			</span>
			{showValue && (
				<span className="dsgo-star-rating__value" aria-hidden="true">
					{formatRatingValue(value)}
					{showMax && (
						<span className="dsgo-star-rating__max">
							{`/${formatRatingValue(max)}`}
						</span>
					)}
				</span>
			)}
			{showCount && (
				<span className="dsgo-star-rating__count" aria-hidden="true">
					{formatCount(countTemplate, ratingCount)}
				</span>
			)}
		</div>
	);
}
