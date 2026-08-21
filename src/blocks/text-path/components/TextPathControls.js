import {
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { DsgoInspectorPanel } from '../../../components/shared/DsgoInspectorPanel';
import { getTextPathShapeOptions } from '../../../utils/svg-paths';
import CustomPathUpload from './CustomPathUpload';

const presetOptions = getTextPathShapeOptions();

const STYLE_CONTROLS = [
	{
		label: __('Font size', 'designsetgo'),
		attribute: 'pathFontSize',
		defaultValue: 54,
		min: 1,
		max: 400,
		step: 1,
	},
	{
		label: __('Rotation', 'designsetgo'),
		attribute: 'rotation',
		defaultValue: 0,
		min: -180,
		max: 180,
		step: 1,
	},
	{
		label: __('Start offset', 'designsetgo'),
		attribute: 'startOffset',
		defaultValue: 0,
		min: -100,
		max: 100,
		step: 1,
	},
	{
		label: __('Word spacing', 'designsetgo'),
		attribute: 'wordSpacing',
		defaultValue: 0,
		min: -40,
		max: 100,
		step: 1,
	},
	{
		label: __('Path padding', 'designsetgo'),
		help: __(
			'Move text away from the path. Use a negative value for the opposite side.',
			'designsetgo'
		),
		attribute: 'pathPadding',
		defaultValue: 0,
		min: -200,
		max: 200,
		step: 1,
	},
];

export default function TextPathControls({
	attributes,
	setAttributes,
	clientId,
}) {
	const resetSettings = () =>
		setAttributes({
			text: 'Text on a path',
			pathType: 'wave',
			arcSize: 100,
			customPath: null,
			showPath: false,
			guideColor: '',
			circleBackgroundColor: '',
			guideOpacity: 0.35,
			guideStrokeWidth: 2,
			pathWidth: 100,
			pathAlignment: 'left',
			direction: 'ltr',
			url: '',
			target: false,
			rel: '',
		});
	const resetStyle = () =>
		setAttributes({
			pathFontSize: 54,
			rotation: 0,
			startOffset: 0,
			wordSpacing: 0,
			pathPadding: 0,
			motion: false,
			motionDuration: 12,
			motionDirection: 'forward',
			guideOpacity: 0.35,
			guideStrokeWidth: 2,
			pathWidth: 100,
			pathAlignment: 'left',
		});

	return (
		<>
			<DsgoInspectorPanel
				title={__('Settings', 'designsetgo')}
				panelName="settings"
				panelId={clientId}
				resetAll={resetSettings}
			>
				<DsgoInspectorPanel.Item
					label={__('Text', 'designsetgo')}
					hasValue={() => attributes.text !== 'Text on a path'}
					onDeselect={() => setAttributes({ text: 'Text on a path' })}
					isShownByDefault
				>
					<TextControl
						label={__('Text', 'designsetgo')}
						value={attributes.text}
						onChange={(text) => setAttributes({ text })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Path', 'designsetgo')}
					hasValue={() => attributes.pathType !== 'wave'}
					onDeselect={() =>
						setAttributes({ pathType: 'wave', customPath: null })
					}
					isShownByDefault
				>
					<SelectControl
						label={__('Path', 'designsetgo')}
						value={attributes.pathType}
						options={[
							...presetOptions,
							{
								label: __('Custom SVG', 'designsetgo'),
								value: 'custom',
							},
						]}
						onChange={(pathType) => setAttributes({ pathType })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					{attributes.pathType === 'custom' && (
						<CustomPathUpload
							onChange={(customPath) =>
								setAttributes({
									pathType: 'custom',
									customPath,
								})
							}
						/>
					)}
				</DsgoInspectorPanel.Item>
				{attributes.pathType === 'arc' && (
					<DsgoInspectorPanel.Item
						label={__('Arc size', 'designsetgo')}
						hasValue={() => attributes.arcSize !== 100}
						onDeselect={() => setAttributes({ arcSize: 100 })}
						isShownByDefault
					>
						<RangeControl
							label={__('Arc size', 'designsetgo')}
							help={__(
								'0 is flat; 100 is the full curve.',
								'designsetgo'
							)}
							value={attributes.arcSize}
							onChange={(arcSize) =>
								setAttributes({ arcSize: arcSize ?? 100 })
							}
							min={0}
							max={100}
							step={1}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						/>
					</DsgoInspectorPanel.Item>
				)}
				<DsgoInspectorPanel.Item
					label={__('Show path', 'designsetgo')}
					hasValue={() => attributes.showPath}
					onDeselect={() => setAttributes({ showPath: false })}
					isShownByDefault
				>
					<ToggleControl
						label={__('Show path', 'designsetgo')}
						checked={attributes.showPath}
						onChange={(showPath) => setAttributes({ showPath })}
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Direction', 'designsetgo')}
					hasValue={() => attributes.direction !== 'ltr'}
					onDeselect={() => setAttributes({ direction: 'ltr' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Direction', 'designsetgo')}
						value={attributes.direction}
						options={[
							{
								label: __('Left to right', 'designsetgo'),
								value: 'ltr',
							},
							{
								label: __('Right to left', 'designsetgo'),
								value: 'rtl',
							},
						]}
						onChange={(direction) => setAttributes({ direction })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Link', 'designsetgo')}
					hasValue={() => !!attributes.url}
					onDeselect={() =>
						setAttributes({ url: '', target: false, rel: '' })
					}
					isShownByDefault
				>
					<TextControl
						label={__('Link', 'designsetgo')}
						type="url"
						value={attributes.url}
						onChange={(url) => setAttributes({ url })}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
					<div className="dsgo-text-path__link-target">
						<ToggleControl
							label={__('Open in new tab', 'designsetgo')}
							checked={attributes.target}
							onChange={(target) => setAttributes({ target })}
							__nextHasNoMarginBottom
						/>
					</div>
				</DsgoInspectorPanel.Item>
			</DsgoInspectorPanel>
			<DsgoInspectorPanel
				title={__('Style', 'designsetgo')}
				panelName="style"
				panelId={clientId}
				resetAll={resetStyle}
			>
				{STYLE_CONTROLS.map(
					({
						label,
						help,
						attribute,
						defaultValue,
						min,
						max,
						step,
					}) => (
						<DsgoInspectorPanel.Item
							key={attribute}
							label={label}
							hasValue={() =>
								attributes[attribute] !== defaultValue
							}
							onDeselect={() =>
								setAttributes({ [attribute]: defaultValue })
							}
							isShownByDefault
						>
							<RangeControl
								label={label}
								value={attributes[attribute]}
								onChange={(value) =>
									setAttributes({
										[attribute]: value ?? defaultValue,
									})
								}
								min={min}
								max={max}
								step={step}
								help={help}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					)
				)}
				<DsgoInspectorPanel.Item
					label={__('Path width', 'designsetgo')}
					hasValue={() => attributes.pathWidth !== 100}
					onDeselect={() => setAttributes({ pathWidth: 100 })}
					isShownByDefault
				>
					<RangeControl
						label={__('Path width', 'designsetgo')}
						value={attributes.pathWidth}
						onChange={(pathWidth) =>
							setAttributes({ pathWidth: pathWidth ?? 100 })
						}
						min={25}
						max={100}
						step={1}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				<DsgoInspectorPanel.Item
					label={__('Path alignment', 'designsetgo')}
					hasValue={() => attributes.pathAlignment !== 'left'}
					onDeselect={() => setAttributes({ pathAlignment: 'left' })}
					isShownByDefault
				>
					<SelectControl
						label={__('Path alignment', 'designsetgo')}
						value={attributes.pathAlignment}
						options={[
							{ label: __('Left', 'designsetgo'), value: 'left' },
							{
								label: __('Center', 'designsetgo'),
								value: 'center',
							},
							{
								label: __('Right', 'designsetgo'),
								value: 'right',
							},
						]}
						onChange={(pathAlignment) =>
							setAttributes({ pathAlignment })
						}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				{attributes.showPath && (
					<>
						<DsgoInspectorPanel.Item
							label={__('Guide opacity', 'designsetgo')}
							hasValue={() => attributes.guideOpacity !== 0.35}
							onDeselect={() =>
								setAttributes({ guideOpacity: 0.35 })
							}
							isShownByDefault
						>
							<RangeControl
								label={__('Guide opacity', 'designsetgo')}
								value={attributes.guideOpacity}
								onChange={(guideOpacity) =>
									setAttributes({
										guideOpacity: guideOpacity ?? 0.35,
									})
								}
								min={0}
								max={1}
								step={0.05}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
						<DsgoInspectorPanel.Item
							label={__('Guide line thickness', 'designsetgo')}
							hasValue={() => attributes.guideStrokeWidth !== 2}
							onDeselect={() =>
								setAttributes({ guideStrokeWidth: 2 })
							}
							isShownByDefault
						>
							<RangeControl
								label={__(
									'Guide line thickness',
									'designsetgo'
								)}
								value={attributes.guideStrokeWidth}
								onChange={(guideStrokeWidth) =>
									setAttributes({
										guideStrokeWidth: guideStrokeWidth ?? 2,
									})
								}
								min={0}
								max={24}
								step={1}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					</>
				)}
				<DsgoInspectorPanel.Item
					label={__('Animate text', 'designsetgo')}
					hasValue={() => attributes.motion}
					onDeselect={() => setAttributes({ motion: false })}
					isShownByDefault
				>
					<ToggleControl
						label={__('Animate text', 'designsetgo')}
						checked={attributes.motion}
						onChange={(motion) => setAttributes({ motion })}
						__nextHasNoMarginBottom
					/>
				</DsgoInspectorPanel.Item>
				{attributes.motion && (
					<>
						<DsgoInspectorPanel.Item
							label={__('Motion duration', 'designsetgo')}
							hasValue={() => attributes.motionDuration !== 12}
							onDeselect={() =>
								setAttributes({ motionDuration: 12 })
							}
							isShownByDefault
						>
							<RangeControl
								label={__(
									'Motion duration (seconds)',
									'designsetgo'
								)}
								value={attributes.motionDuration}
								onChange={(motionDuration) =>
									setAttributes({
										motionDuration: motionDuration ?? 12,
									})
								}
								min={2}
								max={120}
								step={1}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
						<DsgoInspectorPanel.Item
							label={__('Motion direction', 'designsetgo')}
							hasValue={() =>
								attributes.motionDirection !== 'forward'
							}
							onDeselect={() =>
								setAttributes({ motionDirection: 'forward' })
							}
							isShownByDefault
						>
							<SelectControl
								label={__('Motion direction', 'designsetgo')}
								value={attributes.motionDirection}
								options={[
									{
										label: __('Forward', 'designsetgo'),
										value: 'forward',
									},
									{
										label: __('Reverse', 'designsetgo'),
										value: 'reverse',
									},
								]}
								onChange={(motionDirection) =>
									setAttributes({ motionDirection })
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</DsgoInspectorPanel.Item>
					</>
				)}
			</DsgoInspectorPanel>
		</>
	);
}
