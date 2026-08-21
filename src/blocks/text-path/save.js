import { useBlockProps } from '@wordpress/block-editor';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import TextPathGraphic from './components/TextPathGraphic';
import { getSafeTextPathColor, getSafeTextPathUrl } from './utils';

const clamp = (value, minimum, maximum, fallback) => {
	const number = Number(value);
	return Number.isFinite(number)
		? Math.max(minimum, Math.min(maximum, number))
		: fallback;
};

export default function TextPathSave({ attributes }) {
	const {
		guideColor,
		guideOpacity,
		guideStrokeWidth,
		motion,
		motionDirection,
		motionDuration,
		pathWidth,
		rotation,
		url,
		target,
	} = attributes;
	const safeUrl = getSafeTextPathUrl(url);
	const safeGuideColor = getSafeTextPathColor(guideColor);
	const safeRotation = clamp(rotation, -360, 360, 0);
	const blockProps = useBlockProps.save({
		className: 'dsgo-text-path',
		style: {
			'--dsgo-text-path-rotation': `${safeRotation}deg`,
			'--dsgo-text-path-guide-opacity': String(
				clamp(guideOpacity, 0, 1, 0.35)
			),
			'--dsgo-text-path-guide-stroke-width': String(
				clamp(guideStrokeWidth, 0, 24, 2)
			),
			'--dsgo-text-path-width': `${clamp(pathWidth, 25, 100, 100)}%`,
			...(safeGuideColor && {
				'--dsgo-text-path-guide-color':
					convertColorToCSSVar(safeGuideColor),
			}),
		},
		...(motion && {
			'data-dsgo-text-path-motion': 'true',
			'data-dsgo-text-path-motion-duration': String(
				Math.max(2, Math.min(120, Number(motionDuration) || 12))
			),
			'data-dsgo-text-path-motion-direction':
				motionDirection === 'reverse' ? 'reverse' : 'forward',
		}),
	});
	const svg = <TextPathGraphic attributes={attributes} />;

	return (
		<div {...blockProps}>
			{safeUrl ? (
				<a
					href={safeUrl}
					target={target ? '_blank' : undefined}
					rel="noopener noreferrer"
				>
					{svg}
				</a>
			) : (
				svg
			)}
		</div>
	);
}
