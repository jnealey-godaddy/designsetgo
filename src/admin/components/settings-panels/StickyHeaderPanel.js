/**
 * Sticky Header Settings Panel
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Card,
	CardHeader,
	CardBody,
	ToggleControl,
	RangeControl,
	SelectControl,
	TextControl,
	Button,
} from '@wordpress/components';
import { Icon, chevronDown, chevronUp } from '@wordpress/icons';
import ColorDropdownControl from '../ColorDropdownControl';

const StickyHeaderPanel = ({ settings, updateSetting }) => {
	const [showAdvanced, setShowAdvanced] = useState(false);
	const [showMobileSettings, setShowMobileSettings] = useState(false);

	return (
		<Card className="designsetgo-settings-panel">
			<CardHeader>
				<h2>{__('Sticky Header', 'designsetgo')}</h2>
			</CardHeader>
			<CardBody>
				<p className="designsetgo-panel-description">
					{__(
						'Enable sticky header functionality for blocks with position:sticky applied. These settings enhance the default WordPress sticky positioning with additional features.',
						'designsetgo'
					)}
				</p>

				<ToggleControl
					label={__('Enable Sticky Header', 'designsetgo')}
					help={__(
						'Enable enhanced sticky header functionality.',
						'designsetgo'
					)}
					checked={settings?.sticky_header?.enable || false}
					onChange={(value) =>
						updateSetting('sticky_header', 'enable', value)
					}
					__nextHasNoMarginBottom
				/>

				{settings?.sticky_header?.enable && (
					<div className="designsetgo-settings-group">
						{/* Basic Settings - Two Column Layout */}
						<div className="designsetgo-settings-grid">
							<RangeControl
								label={__(
									'Scroll Threshold (px)',
									'designsetgo'
								)}
								help={__(
									'Pixels to scroll before effects activate.',
									'designsetgo'
								)}
								value={
									settings?.sticky_header?.scroll_threshold ||
									50
								}
								onChange={(value) =>
									updateSetting(
										'sticky_header',
										'scroll_threshold',
										value
									)
								}
								min={0}
								max={500}
								step={10}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>

							<RangeControl
								label={__(
									'Transition Speed (ms)',
									'designsetgo'
								)}
								help={__(
									'Animation speed for effects.',
									'designsetgo'
								)}
								value={
									settings?.sticky_header?.transition_speed ||
									300
								}
								onChange={(value) =>
									updateSetting(
										'sticky_header',
										'transition_speed',
										value
									)
								}
								min={0}
								max={1000}
								step={50}
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</div>

						<div className="designsetgo-settings-grid">
							<div>
								<ToggleControl
									label={__(
										'Shadow on Scroll',
										'designsetgo'
									)}
									help={__(
										'Add shadow when scrolled.',
										'designsetgo'
									)}
									checked={
										settings?.sticky_header
											?.shadow_on_scroll || false
									}
									onChange={(value) =>
										updateSetting(
											'sticky_header',
											'shadow_on_scroll',
											value
										)
									}
									__nextHasNoMarginBottom
								/>

								{settings?.sticky_header?.shadow_on_scroll && (
									<SelectControl
										label={__('Shadow Size', 'designsetgo')}
										value={
											settings?.sticky_header
												?.shadow_size || 'medium'
										}
										options={[
											{
												label: __(
													'Small',
													'designsetgo'
												),
												value: 'small',
											},
											{
												label: __(
													'Medium',
													'designsetgo'
												),
												value: 'medium',
											},
											{
												label: __(
													'Large',
													'designsetgo'
												),
												value: 'large',
											},
										]}
										onChange={(value) =>
											updateSetting(
												'sticky_header',
												'shadow_size',
												value
											)
										}
										__nextHasNoMarginBottom
										__next40pxDefaultSize
									/>
								)}
							</div>

							<div>
								<ToggleControl
									label={__(
										'Shrink Logo on Scroll',
										'designsetgo'
									)}
									help={__(
										'Scale down logos inside the sticky header while preserving aspect ratio.',
										'designsetgo'
									)}
									checked={
										settings?.sticky_header
											?.shrink_on_scroll || false
									}
									onChange={(value) =>
										updateSetting(
											'sticky_header',
											'shrink_on_scroll',
											value
										)
									}
									__nextHasNoMarginBottom
								/>

								{settings?.sticky_header?.shrink_on_scroll && (
									<RangeControl
										label={__(
											'Shrink Amount (%)',
											'designsetgo'
										)}
										help={__(
											'40% means the logo is scaled to 60% of its original size.',
											'designsetgo'
										)}
										value={
											settings?.sticky_header
												?.shrink_amount ?? 50
										}
										onChange={(value) =>
											updateSetting(
												'sticky_header',
												'shrink_amount',
												value
											)
										}
										min={5}
										max={70}
										step={5}
										__nextHasNoMarginBottom
										__next40pxDefaultSize
									/>
								)}
							</div>
						</div>

						<ToggleControl
							label={__(
								'Hide Header on Scroll Down',
								'designsetgo'
							)}
							help={__(
								'Hide header when scrolling down, show when scrolling up.',
								'designsetgo'
							)}
							checked={
								settings?.sticky_header?.hide_on_scroll_down ||
								false
							}
							onChange={(value) =>
								updateSetting(
									'sticky_header',
									'hide_on_scroll_down',
									value
								)
							}
							__nextHasNoMarginBottom
						/>

						<ToggleControl
							label={__(
								'Background Color on Scroll',
								'designsetgo'
							)}
							help={__(
								'Apply background color when scrolled.',
								'designsetgo'
							)}
							checked={
								settings?.sticky_header?.background_on_scroll ||
								false
							}
							onChange={(value) =>
								updateSetting(
									'sticky_header',
									'background_on_scroll',
									value
								)
							}
							__nextHasNoMarginBottom
						/>

						{settings?.sticky_header?.background_on_scroll && (
							<div className="designsetgo-scroll-colors">
								<div className="designsetgo-scroll-colors__row">
									<div className="designsetgo-scroll-colors__item">
										<ColorDropdownControl
											label={__(
												'Background',
												'designsetgo'
											)}
											value={
												settings?.sticky_header
													?.background_scroll_color
											}
											onChange={(color) =>
												updateSetting(
													'sticky_header',
													'background_scroll_color',
													color
												)
											}
											colors={[
												{
													name: __(
														'White',
														'designsetgo'
													),
													color: '#ffffff',
												},
												{
													name: __(
														'Black',
														'designsetgo'
													),
													color: '#000000',
												},
												{
													name: __(
														'Gray',
														'designsetgo'
													),
													color: '#f0f0f0',
												},
												{
													name: __(
														'Blue',
														'designsetgo'
													),
													color: '#2271b1',
												},
												{
													name: __(
														'Dark',
														'designsetgo'
													),
													color: '#1e1e1e',
												},
											]}
										/>
									</div>

									<div className="designsetgo-scroll-colors__item">
										<ColorDropdownControl
											label={__(
												'Text Color',
												'designsetgo'
											)}
											value={
												settings?.sticky_header
													?.text_scroll_color
											}
											onChange={(color) =>
												updateSetting(
													'sticky_header',
													'text_scroll_color',
													color
												)
											}
											colors={[
												{
													name: __(
														'Black',
														'designsetgo'
													),
													color: '#000000',
												},
												{
													name: __(
														'White',
														'designsetgo'
													),
													color: '#ffffff',
												},
												{
													name: __(
														'Dark Gray',
														'designsetgo'
													),
													color: '#1e1e1e',
												},
												{
													name: __(
														'Gray',
														'designsetgo'
													),
													color: '#757575',
												},
												{
													name: __(
														'Blue',
														'designsetgo'
													),
													color: '#2271b1',
												},
											]}
											help={__(
												'Change text color when scrolled. Useful for transparent headers with light text over hero images.',
												'designsetgo'
											)}
										/>
									</div>
								</div>

								<RangeControl
									label={__(
										'Background Opacity (%)',
										'designsetgo'
									)}
									value={
										settings?.sticky_header
											?.background_scroll_opacity || 100
									}
									onChange={(value) =>
										updateSetting(
											'sticky_header',
											'background_scroll_opacity',
											value
										)
									}
									min={0}
									max={100}
									step={5}
									__nextHasNoMarginBottom
									__next40pxDefaultSize
								/>
							</div>
						)}

						{/* Advanced Settings */}
						<div className="designsetgo-collapsible-section">
							<Button
								variant="link"
								onClick={() => setShowAdvanced(!showAdvanced)}
								className="designsetgo-collapsible-toggle"
							>
								<Icon
									icon={
										showAdvanced ? chevronUp : chevronDown
									}
								/>
								{__('Advanced Settings', 'designsetgo')}
							</Button>

							{showAdvanced && (
								<div className="designsetgo-collapsible-content">
									<TextControl
										label={__(
											'Custom CSS Selector',
											'designsetgo'
										)}
										help={__(
											'Override the default selector if using a custom theme or page builder. Leave empty to use: .wp-block-template-part:has(.is-position-sticky)',
											'designsetgo'
										)}
										value={
											settings?.sticky_header
												?.custom_selector || ''
										}
										onChange={(value) =>
											updateSetting(
												'sticky_header',
												'custom_selector',
												value
											)
										}
										placeholder=".wp-block-template-part:has(.is-position-sticky)"
										__nextHasNoMarginBottom
										__next40pxDefaultSize
									/>

									<RangeControl
										label={__('Z-Index', 'designsetgo')}
										help={__(
											'Stacking order of the sticky header.',
											'designsetgo'
										)}
										value={
											settings?.sticky_header?.z_index ||
											100
										}
										onChange={(value) =>
											updateSetting(
												'sticky_header',
												'z_index',
												value
											)
										}
										min={1}
										max={9999}
										step={1}
										__nextHasNoMarginBottom
										__next40pxDefaultSize
									/>
								</div>
							)}
						</div>

						{/* Mobile Settings */}
						<div className="designsetgo-collapsible-section">
							<Button
								variant="link"
								onClick={() =>
									setShowMobileSettings(!showMobileSettings)
								}
								className="designsetgo-collapsible-toggle"
							>
								<Icon
									icon={
										showMobileSettings
											? chevronUp
											: chevronDown
									}
								/>
								{__('Mobile Settings', 'designsetgo')}
							</Button>

							{showMobileSettings && (
								<div className="designsetgo-collapsible-content">
									<ToggleControl
										label={__(
											'Enable on Mobile',
											'designsetgo'
										)}
										help={__(
											'Enable sticky header on mobile devices.',
											'designsetgo'
										)}
										checked={
											settings?.sticky_header
												?.mobile_enabled || false
										}
										onChange={(value) =>
											updateSetting(
												'sticky_header',
												'mobile_enabled',
												value
											)
										}
										__nextHasNoMarginBottom
									/>

									{settings?.sticky_header
										?.mobile_enabled && (
										<RangeControl
											label={__(
												'Mobile Breakpoint (px)',
												'designsetgo'
											)}
											help={__(
												'Screen width below which mobile settings apply.',
												'designsetgo'
											)}
											value={
												settings?.sticky_header
													?.mobile_breakpoint || 768
											}
											onChange={(value) =>
												updateSetting(
													'sticky_header',
													'mobile_breakpoint',
													value
												)
											}
											min={320}
											max={1024}
											step={1}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>
									)}
								</div>
							)}
						</div>
					</div>
				)}
			</CardBody>
		</Card>
	);
};

export default StickyHeaderPanel;
