/**
 * SVG Patterns Extension - Panel Component
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { Fragment, useMemo } from '@wordpress/element';
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	Button,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalHStack as HStack,
} from '@wordpress/components';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import { RANGES, DEFAULTS, INHERIT } from '../constants';
import {
	PATTERNS,
	PATTERN_IDS,
	CATEGORIES,
	getPatternBackground,
} from '../patterns';
import {
	encodeColorValue,
	decodeColorValue,
} from '../../../utils/encode-color-value';
import { useInheritedSvgPattern } from '../use-inherited-svg-pattern';

/**
 * Pattern thumbnail preview
 *
 * @param {Object}   props           Component props
 * @param {string}   props.patternId Pattern ID
 * @param {boolean}  props.isActive  Whether this pattern is selected
 * @param {Function} props.onClick   Click handler
 * @param {string}   [props.label]   Optional label override for the tooltip
 * @return {JSX.Element} Pattern thumbnail
 */
function PatternThumbnail({ patternId, isActive, onClick, label }) {
	const pattern = PATTERNS[patternId];
	const bg = useMemo(
		() => getPatternBackground(patternId, '#6b7280', 0.6, 1),
		[patternId]
	);

	if (!bg) {
		return null;
	}

	return (
		<Button
			className={`dsgo-svg-pattern-thumb${isActive ? ' is-active' : ''}`}
			onClick={onClick}
			label={label || pattern.label}
			showTooltip
		>
			<span
				className="dsgo-svg-pattern-thumb__preview"
				style={{
					backgroundImage: bg.backgroundImage,
					backgroundSize: bg.backgroundSize,
					backgroundRepeat: 'repeat',
				}}
			/>
		</Button>
	);
}

/**
 * SVG Patterns Panel Component
 *
 * @param {Object}   props               Component props
 * @param {Object}   props.attributes    Block attributes
 * @param {Function} props.setAttributes Function to update attributes
 * @param {string}   props.clientId      Block client ID
 * @return {JSX.Element} Panel component
 */
export default function SvgPatternsPanel({
	attributes,
	setAttributes,
	clientId,
}) {
	const {
		dsgoSvgPatternEnabled,
		dsgoSvgPatternType,
		dsgoSvgPatternColor,
		dsgoSvgPatternOpacity,
		dsgoSvgPatternScale,
		dsgoSvgPatternFixed,
	} = attributes;

	const isInherit = dsgoSvgPatternType === INHERIT;

	// Resolved theme preset, shared with the editor preview HOC via this hook.
	const inherited = useInheritedSvgPattern();

	const colorGradientSettings = useMultipleOriginColorsAndGradients();

	// Group patterns by category
	const groupedPatterns = useMemo(() => {
		const groups = {};
		PATTERN_IDS.forEach((id) => {
			const cat = PATTERNS[id].category;
			if (!groups[cat]) {
				groups[cat] = [];
			}
			groups[cat].push(id);
		});
		return groups;
	}, []);

	return (
		<Fragment>
			{/* Color Settings - In Styles > Color Panel */}
			{dsgoSvgPatternEnabled && dsgoSvgPatternType && !isInherit && (
				<InspectorControls group="color">
					<ColorGradientSettingsDropdown
						panelId={clientId}
						title={__('SVG Pattern Color', 'designsetgo')}
						settings={[
							{
								label: __('Pattern Color', 'designsetgo'),
								colorValue:
									decodeColorValue(
										dsgoSvgPatternColor,
										colorGradientSettings
									) || DEFAULTS.color,
								onColorChange: (value) =>
									setAttributes({
										dsgoSvgPatternColor:
											encodeColorValue(
												value,
												colorGradientSettings
											) || '',
									}),
								clearable: true,
								enableAlpha: true,
							},
						]}
						{...colorGradientSettings}
					/>
				</InspectorControls>
			)}

			{/* Main Settings Panel */}
			<InspectorControls>
				<PanelBody
					title={__('SVG Pattern', 'designsetgo')}
					initialOpen={false}
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={__('Enable SVG pattern', 'designsetgo')}
						checked={dsgoSvgPatternEnabled}
						onChange={(value) =>
							setAttributes({ dsgoSvgPatternEnabled: value })
						}
						help={__(
							'Adds a repeatable SVG pattern as a background overlay on this section.',
							'designsetgo'
						)}
					/>

					{dsgoSvgPatternEnabled && (
						<>
							{/* Pattern Picker */}
							<div className="dsgo-svg-pattern-picker">
								{/* Theme default (inherit) tile */}
								<div className="dsgo-svg-pattern-picker__group">
									<div className="dsgo-svg-pattern-picker__group-label">
										{__('Theme', 'designsetgo')}
									</div>
									<div className="dsgo-svg-pattern-picker__grid">
										<PatternThumbnail
											patternId={inherited.type}
											isActive={isInherit}
											onClick={() =>
												setAttributes({
													dsgoSvgPatternType: INHERIT,
												})
											}
											label={__(
												'Theme default',
												'designsetgo'
											)}
										/>
									</div>
								</div>

								{Object.entries(CATEGORIES).map(
									([catKey, catLabel]) => {
										const ids = groupedPatterns[catKey];
										if (!ids || ids.length === 0) {
											return null;
										}
										return (
											<div
												key={catKey}
												className="dsgo-svg-pattern-picker__group"
											>
												<div className="dsgo-svg-pattern-picker__group-label">
													{catLabel}
												</div>
												<div className="dsgo-svg-pattern-picker__grid">
													{ids.map((id) => (
														<PatternThumbnail
															key={id}
															patternId={id}
															isActive={
																dsgoSvgPatternType ===
																id
															}
															onClick={() =>
																setAttributes({
																	dsgoSvgPatternType:
																		id,
																})
															}
														/>
													))}
												</div>
											</div>
										);
									}
								)}
							</div>

							{dsgoSvgPatternType && (
								<HStack
									className="dsgo-svg-pattern-picker__selected"
									alignment="center"
								>
									<span>
										{__('Selected:', 'designsetgo')}{' '}
										<strong>
											{isInherit
												? __(
														'Theme default',
														'designsetgo'
													)
												: PATTERNS[dsgoSvgPatternType]
														?.label}
										</strong>
									</span>
									<Button
										variant="link"
										isDestructive
										onClick={() =>
											setAttributes({
												dsgoSvgPatternType: '',
											})
										}
									>
										{__('Clear', 'designsetgo')}
									</Button>
								</HStack>
							)}

							{!isInherit && (
								<>
									{/* Opacity Control */}
									<RangeControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={__(
											'Pattern Opacity',
											'designsetgo'
										)}
										value={dsgoSvgPatternOpacity}
										onChange={(value) =>
											setAttributes({
												dsgoSvgPatternOpacity: value,
											})
										}
										min={RANGES.opacity.min}
										max={RANGES.opacity.max}
										step={RANGES.opacity.step}
									/>

									{/* Scale Control */}
									<RangeControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={__(
											'Pattern Scale',
											'designsetgo'
										)}
										value={dsgoSvgPatternScale}
										onChange={(value) =>
											setAttributes({
												dsgoSvgPatternScale: value,
											})
										}
										min={RANGES.scale.min}
										max={RANGES.scale.max}
										step={RANGES.scale.step}
										help={__(
											'Scale the pattern size. 1 = original size.',
											'designsetgo'
										)}
									/>
								</>
							)}

							{isInherit && (
								<p className="dsgo-svg-pattern-picker__inherit-note">
									{__(
										'Pattern, color, opacity and scale are inherited from your theme. Choose a pattern above to customize them.',
										'designsetgo'
									)}
								</p>
							)}

							{/* Fixed Background */}
							<ToggleControl
								__nextHasNoMarginBottom
								label={__('Fixed Background', 'designsetgo')}
								checked={dsgoSvgPatternFixed}
								onChange={(value) =>
									setAttributes({
										dsgoSvgPatternFixed: value,
									})
								}
								help={__(
									'Creates a parallax effect. May not work on mobile devices.',
									'designsetgo'
								)}
							/>
						</>
					)}
				</PanelBody>
			</InspectorControls>
		</Fragment>
	);
}
