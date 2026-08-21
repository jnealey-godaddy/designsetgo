import { getTextPathData } from '../../../utils/svg-paths';
import { getSafeTextPathColor, getTextPathId } from '../utils';

const clamp = (value, minimum, maximum, fallback) => {
	const number = Number(value);
	return Number.isFinite(number)
		? Math.max(minimum, Math.min(maximum, number))
		: fallback;
};

export default function TextPathGraphic({ attributes }) {
	const path = getTextPathData(attributes);
	const pathId = getTextPathId(attributes.uniqueId);
	const startOffset = `${clamp(attributes.startOffset, -100, 100, 0)}%`;
	const fontSize = clamp(attributes.pathFontSize, 1, 400, 54);
	const wordSpacing = clamp(attributes.wordSpacing, -40, 100, 0);
	const hasCircleBackground = Boolean(
		attributes.pathType === 'circle' &&
			getSafeTextPathColor(attributes.circleBackgroundColor)
	);

	return (
		<svg
			viewBox={path.viewBox}
			role="img"
			aria-label={attributes.text || undefined}
		>
			{hasCircleBackground && (
				<circle
					className="dsgo-text-path__circle-background"
					cx="500"
					cy="500"
					r="500"
					aria-hidden="true"
				/>
			)}
			<defs>
				<path id={pathId} d={path.d} />
			</defs>
			{attributes.showPath && (
				<path className="dsgo-text-path__guide" d={path.d} />
			)}
			<text
				direction={attributes.direction === 'rtl' ? 'rtl' : 'ltr'}
				style={{
					fontSize: `${fontSize}px`,
					wordSpacing: `${wordSpacing}px`,
				}}
			>
				<textPath
					href={`#${pathId}`}
					startOffset={startOffset}
					data-dsgo-text-path-offset={startOffset.replace('%', '')}
				>
					{attributes.text}
				</textPath>
			</text>
		</svg>
	);
}
