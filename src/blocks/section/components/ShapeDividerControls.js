/**
 * Shape Divider Controls Component
 *
 * Inspector panel controls for configuring shape dividers.
 *
 * @since 1.4.2
 */

import { __, sprintf } from '@wordpress/i18n';
import { useSettings } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	ToggleControl,
	Flex,
	FlexItem,
	Notice,
	Button,
} from '@wordpress/components';
import {
	getShapeDividerOptions,
	getShapeDivider,
} from '../utils/shape-dividers';
import { sanitizeColor } from '../utils/sanitize-color';
import { convertColorToCSSVar } from '../../../utils/convert-preset-to-css-var';

/**
 * Shape preview component showing a small preview of the selected shape
 *
 * @param {Object}  props                        Component props
 * @param {string}  props.shape                  Shape name
 * @param {string}  props.color                  Fill color
 * @param {string}  props.backgroundColor        Background color behind the shape
 * @param {boolean} props.flipX                  Flip horizontally
 * @param {boolean} props.flipY                  Flip vertically
 * @param {boolean} props.isBottom               Whether this is a bottom divider
 * @param {string}  props.sectionBackgroundColor Section background color
 * @param {string}  props.sectionTextColor       Section text color
 * @return {JSX.Element|null} Preview element
 */
function ShapePreview({
	shape,
	color,
	backgroundColor,
	flipX,
	flipY,
	isBottom,
	sectionBackgroundColor = '',
	sectionTextColor = '',
}) {
	if (!shape) {
		return null;
	}

	const shapeElement = getShapeDivider(shape);
	if (!shapeElement) {
		return null;
	}

	// Calculate transform based on flip settings
	const transforms = [];
	if (flipX) {
		transforms.push('scaleX(-1)');
	}
	// Bottom dividers are rotated 180 degrees by default, flipY inverts this
	if (isBottom ? !flipY : flipY) {
		transforms.push('scaleY(-1)');
	}

	// Convert preset format to CSS variable before sanitization
	const safeColor = sanitizeColor(convertColorToCSSVar(color));
	const safeBackgroundColor = sanitizeColor(
		convertColorToCSSVar(backgroundColor)
	);

	return (
		<div
			style={{
				width: '100%',
				height: '40px',
				overflow: 'hidden',
				borderRadius: '4px',
				backgroundColor:
					safeBackgroundColor ||
					sanitizeColor(sectionTextColor) ||
					'#f0f0f0',
				marginBottom: '12px',
			}}
		>
			<svg
				viewBox="0 0 1200 120"
				preserveAspectRatio="none"
				style={{
					width: '100%',
					height: '100%',
					fill: safeColor || sectionBackgroundColor || 'transparent',
					transform:
						transforms.length > 0
							? transforms.join(' ')
							: undefined,
				}}
			>
				{shapeElement}
			</svg>
		</div>
	);
}

/**
 * Reusable Shape Divider Panel Component
 * Renders controls for a single shape divider (top or bottom)
 * Note: Color controls are in the main color panel, not here.
 *
 * @param {Object}   props                        Component props
 * @param {string}   props.title                  Panel title
 * @param {string}   props.shape                  Selected shape value
 * @param {string}   props.color                  Shape color (for preview only)
 * @param {string}   props.backgroundColor        Background color (for preview only)
 * @param {number}   props.height                 Shape height
 * @param {number}   props.width                  Shape width percentage
 * @param {boolean}  props.flipX                  Flip horizontal
 * @param {boolean}  props.flipY                  Flip vertical
 * @param {boolean}  props.front                  Bring to front
 * @param {string}   props.spacing                Inner content clearance (spacing token)
 * @param {Array}    props.spacingOptions         SelectControl options for the clearance
 * @param {boolean}  props.isBottom               Whether this is a bottom divider
 * @param {Function} props.onChange               Callback for attribute changes
 * @param {string}   props.sectionBackgroundColor Section background color
 * @param {string}   props.sectionTextColor       Section text color
 * @return {JSX.Element} Shape divider panel
 */
function ShapeDividerPanel({
	title,
	shape,
	color,
	backgroundColor,
	height,
	width,
	flipX,
	flipY,
	front,
	spacing,
	spacingOptions,
	isBottom,
	onChange,
	sectionBackgroundColor = '',
	sectionTextColor = '',
}) {
	// A clearance carried over from legacy content is a raw length (e.g. "80px"
	// from a pre-2.6 divider height) that matches no preset token. Surface it as
	// a synthetic option so the control shows the active value instead of
	// appearing blank — otherwise an editor could silently overwrite a preserved
	// value while thinking nothing was set.
	const clearanceOptions =
		spacing && !spacingOptions.some((option) => option.value === spacing)
			? [
					...spacingOptions,
					{
						label: sprintf(
							/* translators: %s: the raw CSS length currently applied as clearance. */
							__('Custom (%s)', 'designsetgo'),
							spacing
						),
						value: spacing,
					},
				]
			: spacingOptions;

	return (
		<PanelBody title={title} initialOpen={false}>
			<SelectControl
				label={__('Shape', 'designsetgo')}
				value={shape}
				options={(() => {
					const [none, ...rest] = getShapeDividerOptions();
					return [
						none,
						{
							label: __('Theme default', 'designsetgo'),
							value: 'inherit',
						},
						...rest,
					];
				})()}
				onChange={(value) => onChange({ shape: value })}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>

			{shape && (
				<>
					<ShapePreview
						shape={shape}
						color={color}
						backgroundColor={backgroundColor}
						flipX={flipX}
						flipY={flipY}
						isBottom={isBottom}
						sectionBackgroundColor={sectionBackgroundColor}
						sectionTextColor={sectionTextColor}
					/>

					{/* Height and Width are nullable: clearing them falls back
					    to the theme.json tokens
					    settings.custom.designsetgo.shapeDivider.{height,width}
					    (then to 100px / 100%), which is why both use allowReset
					    and normalize an undefined reset back to null. */}
					<RangeControl
						label={__('Height', 'designsetgo')}
						value={height}
						onChange={(value) =>
							onChange({ height: value ?? null })
						}
						min={10}
						max={500}
						step={1}
						allowReset
						help={__(
							'Reset to inherit the theme’s divider height.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<RangeControl
						label={__('Width', 'designsetgo')}
						value={width}
						onChange={(value) => onChange({ width: value ?? null })}
						min={100}
						max={300}
						step={1}
						allowReset
						help={__(
							'Stretch the shape wider for more dramatic effect. Reset to inherit the theme’s divider width.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<SelectControl
						label={__('Content Clearance', 'designsetgo')}
						value={spacing || ''}
						options={clearanceOptions}
						onChange={(value) =>
							onChange({ spacing: value || undefined })
						}
						help={__(
							'Inner padding reserved so content clears the shape divider. Leave on Default to match the divider height, or pick a theme spacing preset.',
							'designsetgo'
						)}
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>

					<Flex>
						<FlexItem>
							<ToggleControl
								label={__('Flip Horizontal', 'designsetgo')}
								checked={flipX}
								onChange={(value) => onChange({ flipX: value })}
								__nextHasNoMarginBottom
							/>
						</FlexItem>
						<FlexItem>
							<ToggleControl
								label={__('Flip Vertical', 'designsetgo')}
								checked={flipY}
								onChange={(value) => onChange({ flipY: value })}
								__nextHasNoMarginBottom
							/>
						</FlexItem>
					</Flex>

					<ToggleControl
						label={__('Bring to Front', 'designsetgo')}
						checked={front}
						onChange={(value) => onChange({ front: value })}
						help={__(
							'Display the shape above the section content.',
							'designsetgo'
						)}
						__nextHasNoMarginBottom
					/>
				</>
			)}
		</PanelBody>
	);
}

/**
 * Shape Divider Controls
 * Note: Color controls are in the main color panel (InspectorControls group="color")
 *
 * @param {Object}   props                        Component props
 * @param {Object}   props.attributes             Block attributes
 * @param {Function} props.setAttributes          Function to update attributes
 * @param {string}   props.sectionBackgroundColor Section background color
 * @param {string}   props.sectionTextColor       Section text color
 * @return {JSX.Element} Shape divider controls
 */
export default function ShapeDividerControls({
	attributes,
	setAttributes,
	sectionBackgroundColor = '',
	sectionTextColor = '',
}) {
	const {
		shapeDividerTop,
		shapeDividerTopColor,
		shapeDividerTopBackgroundColor,
		shapeDividerTopHeight,
		shapeDividerTopWidth,
		shapeDividerTopFlipX,
		shapeDividerTopFlipY,
		shapeDividerTopFront,
		shapeDividerTopSpacing,
		shapeDividerBottom,
		shapeDividerBottomColor,
		shapeDividerBottomBackgroundColor,
		shapeDividerBottomHeight,
		shapeDividerBottomWidth,
		shapeDividerBottomFlipX,
		shapeDividerBottomFlipY,
		shapeDividerBottomFront,
		shapeDividerBottomSpacing,
		// Video background attribute (from extension)
		dsgoVideoUrl,
	} = attributes;

	// Check if video background is enabled
	const hasVideoBackground = !!dsgoVideoUrl;

	// Theme spacing presets → SelectControl options. Values use the standard
	// WordPress `var:preset|spacing|NN` token so save() can serialize the exact
	// same value all other padding uses.
	const [spacingSizes] = useSettings('spacing.spacingSizes');
	const spacingOptions = [
		// Not "None": clearing the value falls back to the height-matched CSS
		// default (see _shape-divider.scss), not zero padding — so the label
		// reflects that to avoid implying content will sit flush to the shape.
		{
			label: __('Default (matches divider height)', 'designsetgo'),
			value: '',
		},
		...(Array.isArray(spacingSizes) ? spacingSizes : []).map((size) => ({
			label: size.name,
			value: `var:preset|spacing|${size.slug}`,
		})),
	];

	// Handler for top shape divider changes
	const handleTopChange = (changes) => {
		const attrMap = {
			shape: 'shapeDividerTop',
			color: 'shapeDividerTopColor',
			backgroundColor: 'shapeDividerTopBackgroundColor',
			height: 'shapeDividerTopHeight',
			width: 'shapeDividerTopWidth',
			flipX: 'shapeDividerTopFlipX',
			flipY: 'shapeDividerTopFlipY',
			front: 'shapeDividerTopFront',
			spacing: 'shapeDividerTopSpacing',
		};
		const newAttrs = {};
		Object.entries(changes).forEach(([key, value]) => {
			if (attrMap[key]) {
				newAttrs[attrMap[key]] = value;
			}
		});
		setAttributes(newAttrs);
	};

	// Handler for bottom shape divider changes
	const handleBottomChange = (changes) => {
		const attrMap = {
			shape: 'shapeDividerBottom',
			color: 'shapeDividerBottomColor',
			backgroundColor: 'shapeDividerBottomBackgroundColor',
			height: 'shapeDividerBottomHeight',
			width: 'shapeDividerBottomWidth',
			flipX: 'shapeDividerBottomFlipX',
			flipY: 'shapeDividerBottomFlipY',
			front: 'shapeDividerBottomFront',
			spacing: 'shapeDividerBottomSpacing',
		};
		const newAttrs = {};
		Object.entries(changes).forEach(([key, value]) => {
			if (attrMap[key]) {
				newAttrs[attrMap[key]] = value;
			}
		});
		setAttributes(newAttrs);
	};

	// If video background is enabled, show notice instead of controls
	if (hasVideoBackground) {
		return (
			<PanelBody
				title={__('Shape Dividers', 'designsetgo')}
				initialOpen={false}
			>
				<Notice status="warning" isDismissible={false}>
					{__(
						'Shape dividers cannot be used with video backgrounds.',
						'designsetgo'
					)}
				</Notice>
				<Button
					variant="secondary"
					onClick={() =>
						setAttributes({
							dsgoVideoUrl: '',
							dsgoVideoPoster: '',
						})
					}
					style={{ marginTop: '12px' }}
				>
					{__('Remove Video Background', 'designsetgo')}
				</Button>
			</PanelBody>
		);
	}

	return (
		<>
			<ShapeDividerPanel
				title={__('Top Shape Divider', 'designsetgo')}
				shape={shapeDividerTop}
				color={shapeDividerTopColor}
				backgroundColor={shapeDividerTopBackgroundColor}
				height={shapeDividerTopHeight}
				width={shapeDividerTopWidth}
				flipX={shapeDividerTopFlipX}
				flipY={shapeDividerTopFlipY}
				front={shapeDividerTopFront}
				spacing={shapeDividerTopSpacing}
				spacingOptions={spacingOptions}
				isBottom={false}
				onChange={handleTopChange}
				sectionBackgroundColor={sectionBackgroundColor}
				sectionTextColor={sectionTextColor}
			/>
			<ShapeDividerPanel
				title={__('Bottom Shape Divider', 'designsetgo')}
				shape={shapeDividerBottom}
				color={shapeDividerBottomColor}
				backgroundColor={shapeDividerBottomBackgroundColor}
				height={shapeDividerBottomHeight}
				width={shapeDividerBottomWidth}
				flipX={shapeDividerBottomFlipX}
				flipY={shapeDividerBottomFlipY}
				front={shapeDividerBottomFront}
				spacing={shapeDividerBottomSpacing}
				spacingOptions={spacingOptions}
				isBottom={true}
				onChange={handleBottomChange}
				sectionBackgroundColor={sectionBackgroundColor}
				sectionTextColor={sectionTextColor}
			/>
		</>
	);
}
