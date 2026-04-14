/**
 * Sticky Header Controls Extension - Editor Panel
 *
 * Inspector controls for sticky header configuration.
 * Lazy-loaded to reduce initial bundle size.
 *
 * @package
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	SelectControl,
	Notice,
} from '@wordpress/components';

/**
 * Sticky header inspector controls panel
 *
 * @param {Object} props Block props
 */
export default function StickyHeaderPanel(props) {
	const { attributes, setAttributes } = props;

	// Check if global sticky header is enabled.
	const globalEnabled =
		window.dsgoStickyHeaderGlobalSettings?.enabled ?? true;

	return (
		<InspectorControls>
			<PanelBody
				title={__('Sticky Header', 'designsetgo')}
				initialOpen={false}
			>
				{!globalEnabled && (
					<Notice status="warning" isDismissible={false}>
						{__(
							'Sticky header is disabled in DesignSetGo Settings. Enable it in Settings > DesignSetGo to use these controls.',
							'designsetgo'
						)}
					</Notice>
				)}

				<p className="components-base-control__help">
					{__(
						'Configure sticky header behavior for this template part.',
						'designsetgo'
					)}
				</p>

				<ToggleControl
					label={__('Enable Sticky Header', 'designsetgo')}
					help={__(
						'Make this header stick to the top when scrolling.',
						'designsetgo'
					)}
					checked={attributes.dsgoStickyEnabled || false}
					disabled={!globalEnabled}
					onChange={(value) =>
						setAttributes({ dsgoStickyEnabled: value })
					}
				/>

				{attributes.dsgoStickyEnabled && globalEnabled && (
					<>
						<SelectControl
							label={__('Shadow Size', 'designsetgo')}
							value={attributes.dsgoStickyShadow || 'medium'}
							options={[
								{
									label: __('None', 'designsetgo'),
									value: 'none',
								},
								{
									label: __('Small', 'designsetgo'),
									value: 'small',
								},
								{
									label: __('Medium', 'designsetgo'),
									value: 'medium',
								},
								{
									label: __('Large', 'designsetgo'),
									value: 'large',
								},
							]}
							onChange={(value) =>
								setAttributes({
									dsgoStickyShadow: value,
								})
							}
							help={__(
								'Shadow depth when scrolled.',
								'designsetgo'
							)}
						/>

						<ToggleControl
							label={__('Shrink Logo on Scroll', 'designsetgo')}
							help={__(
								'Scale down images (site logo, image blocks) inside the header when scrolled. Keeps aspect ratio — works for wide or tall logos.',
								'designsetgo'
							)}
							checked={attributes.dsgoStickyShrink || false}
							onChange={(value) =>
								setAttributes({
									dsgoStickyShrink: value,
								})
							}
						/>

						{attributes.dsgoStickyShrink && (
							<RangeControl
								label={__('Shrink Amount (%)', 'designsetgo')}
								help={__(
									'How much to shrink the logo. 40% means scaled to 60% of original size.',
									'designsetgo'
								)}
								value={attributes.dsgoStickyShrinkAmount ?? 50}
								onChange={(value) =>
									setAttributes({
										dsgoStickyShrinkAmount: value,
									})
								}
								min={5}
								max={70}
								step={5}
							/>
						)}

						<ToggleControl
							label={__('Hide on Scroll Down', 'designsetgo')}
							checked={attributes.dsgoStickyHideOnScroll || false}
							onChange={(value) =>
								setAttributes({
									dsgoStickyHideOnScroll: value,
								})
							}
							help={__(
								'Auto-hide when scrolling down, show when scrolling up.',
								'designsetgo'
							)}
						/>

						<ToggleControl
							label={__('Background on Scroll', 'designsetgo')}
							checked={attributes.dsgoStickyBackground || false}
							onChange={(value) =>
								setAttributes({
									dsgoStickyBackground: value,
								})
							}
							help={__(
								'Use global background color setting when scrolled.',
								'designsetgo'
							)}
						/>

						<ToggleControl
							label={__('Skip Top Bar on Scroll', 'designsetgo')}
							checked={attributes.dsgoStickySkipTopBar !== false}
							onChange={(value) =>
								setAttributes({
									dsgoStickySkipTopBar: value,
								})
							}
							help={__(
								'If your header has a top bar (e.g. social links), it scrolls away before the nav sticks.',
								'designsetgo'
							)}
						/>

						<p
							className="components-base-control__help"
							style={{ marginTop: '16px' }}
						>
							{__(
								'Additional settings like z-index, transition speed, and background color can be configured in DesignSetGo Settings.',
								'designsetgo'
							)}
						</p>
					</>
				)}
			</PanelBody>
		</InspectorControls>
	);
}
