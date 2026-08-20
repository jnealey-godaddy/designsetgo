import { __ } from '@wordpress/i18n';
import {
	RangeControl,
	Notice,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../components/shared/DsgoInspectorPanel';

export const ROTATING_EFFECTS = [
	'typing',
	'clip',
	'flip',
	'swirl',
	'blinds',
	'drop-in',
	'wave',
	'slide',
	'slide-down',
];

export const HIGHLIGHT_SHAPES = [
	'circle',
	'curly',
	'underline',
	'double',
	'double-underline',
	'underline-zigzag',
	'zigzag',
	'diagonal',
	'strikethrough',
	'x',
];

export const DEFAULT_ANIMATED_HEADLINE = {
	mode: 'rotating',
	effect: 'typing',
	shape: 'circle',
	duration: 2500,
	delay: 0,
	loop: true,
};

const DURATION_MIN = 250;
const DURATION_MAX = 10000;
const DELAY_MIN = 0;
const DELAY_MAX = 10000;

function clamp(value, minimum, maximum, fallback) {
	const number = Number(value);

	if (!Number.isFinite(number)) {
		return fallback;
	}

	return Math.min(maximum, Math.max(minimum, Math.round(number)));
}

/**
 * Normalize the parent-owned animated headline data before it is saved.
 *
 * @param {Object|null} value Candidate settings.
 * @return {Object|null} Safe animated headline settings, or null when absent.
 */
export function normalizeAnimatedHeadline(value) {
	if (!value || typeof value !== 'object' || Array.isArray(value)) {
		return null;
	}

	const mode = value.mode === 'highlighted' ? 'highlighted' : 'rotating';

	return {
		mode,
		effect: ROTATING_EFFECTS.includes(value.effect)
			? value.effect
			: DEFAULT_ANIMATED_HEADLINE.effect,
		shape: HIGHLIGHT_SHAPES.includes(value.shape)
			? value.shape
			: DEFAULT_ANIMATED_HEADLINE.shape,
		duration: clamp(
			value.duration,
			DURATION_MIN,
			DURATION_MAX,
			DEFAULT_ANIMATED_HEADLINE.duration
		),
		delay: clamp(
			value.delay,
			DELAY_MIN,
			DELAY_MAX,
			DEFAULT_ANIMATED_HEADLINE.delay
		),
		loop: value.loop !== false,
	};
}

/**
 * Controls for the parent-owned animated headline settings.
 *
 * @param {Object}   props
 * @param {Object}   props.value        Normalized headline settings.
 * @param {Function} props.onChange     Receives a complete next settings object.
 * @param {number}   props.segmentCount Number of animated child segments.
 * @return {JSX.Element} Animation settings controls or a concise prerequisite notice.
 */
export default function AnimatedHeadlinePanel({
	value,
	onChange,
	segmentCount,
}) {
	if (segmentCount !== 1) {
		return (
			<Notice
				className="dsgo-advanced-heading__animation-notice"
				status="warning"
				isDismissible={false}
			>
				{segmentCount > 1
					? __(
							'Choose one animated heading segment before configuring this headline.',
							'designsetgo'
						)
					: __(
							'Select Animated words in one heading segment to configure this headline.',
							'designsetgo'
						)}
			</Notice>
		);
	}

	const update = (next) => onChange({ ...value, ...next });

	return (
		<>
			<DsgoInspectorPanel.Item
				label={__('Headline mode', 'designsetgo')}
				hasValue={() => value.mode !== DEFAULT_ANIMATED_HEADLINE.mode}
				onDeselect={() =>
					update({ mode: DEFAULT_ANIMATED_HEADLINE.mode })
				}
				isShownByDefault
			>
				<SelectControl
					label={__('Headline mode', 'designsetgo')}
					value={value.mode}
					options={[
						{
							label: __('Rotating', 'designsetgo'),
							value: 'rotating',
						},
						{
							label: __('Highlighted', 'designsetgo'),
							value: 'highlighted',
						},
					]}
					onChange={(mode) => update({ mode })}
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</DsgoInspectorPanel.Item>

			{value.mode === 'rotating' ? (
				<>
					<DsgoInspectorPanel.Item
						label={__('Effect', 'designsetgo')}
						hasValue={() =>
							value.effect !== DEFAULT_ANIMATED_HEADLINE.effect
						}
						onDeselect={() =>
							update({ effect: DEFAULT_ANIMATED_HEADLINE.effect })
						}
						isShownByDefault
					>
						<SelectControl
							label={__('Effect', 'designsetgo')}
							value={value.effect}
							options={ROTATING_EFFECTS.map((effect) => ({
								label: effect.replace(/-/g, ' '),
								value: effect,
							}))}
							onChange={(effect) => update({ effect })}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
					<DsgoInspectorPanel.Item
						label={__('Duration', 'designsetgo')}
						hasValue={() =>
							value.duration !==
							DEFAULT_ANIMATED_HEADLINE.duration
						}
						onDeselect={() =>
							update({
								duration: DEFAULT_ANIMATED_HEADLINE.duration,
							})
						}
						isShownByDefault
					>
						<RangeControl
							label={__('Duration (ms)', 'designsetgo')}
							value={value.duration}
							onChange={(duration) => update({ duration })}
							min={DURATION_MIN}
							max={DURATION_MAX}
							step={50}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
					<DsgoInspectorPanel.Item
						label={__('Delay', 'designsetgo')}
						hasValue={() =>
							value.delay !== DEFAULT_ANIMATED_HEADLINE.delay
						}
						onDeselect={() =>
							update({ delay: DEFAULT_ANIMATED_HEADLINE.delay })
						}
						isShownByDefault
					>
						<RangeControl
							label={__('Delay (ms)', 'designsetgo')}
							value={value.delay}
							onChange={(delay) => update({ delay })}
							min={DELAY_MIN}
							max={DELAY_MAX}
							step={50}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
					<DsgoInspectorPanel.Item
						label={__('Loop', 'designsetgo')}
						hasValue={() =>
							value.loop !== DEFAULT_ANIMATED_HEADLINE.loop
						}
						onDeselect={() =>
							update({ loop: DEFAULT_ANIMATED_HEADLINE.loop })
						}
						isShownByDefault
					>
						<ToggleControl
							label={__('Loop animation', 'designsetgo')}
							checked={value.loop}
							onChange={(loop) => update({ loop })}
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
				</>
			) : (
				<DsgoInspectorPanel.Item
					label={__('Highlight shape', 'designsetgo')}
					hasValue={() =>
						value.shape !== DEFAULT_ANIMATED_HEADLINE.shape
					}
					onDeselect={() =>
						update({ shape: DEFAULT_ANIMATED_HEADLINE.shape })
					}
					isShownByDefault
				>
					<SelectControl
						label={__('Highlight shape', 'designsetgo')}
						value={value.shape}
						options={HIGHLIGHT_SHAPES.map((shape) => ({
							label: shape.replace(/-/g, ' '),
							value: shape,
						}))}
						onChange={(shape) => update({ shape })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
			)}
		</>
	);
}
