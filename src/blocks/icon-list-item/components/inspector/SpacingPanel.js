/**
 * Icon List Item - Spacing Panel Component
 *
 * Provides controls for content spacing within the icon list item.
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { PanelBody, RangeControl } from '@wordpress/components';

/**
 * Spacing Panel Component
 *
 * @param {Object}   props               - Component props
 * @param {number}   props.contentGap    - Gap between content blocks
 * @param {Function} props.setAttributes - Function to update attributes
 * @return {JSX.Element} Spacing Panel component
 */
export const SpacingPanel = ({ contentGap, setAttributes }) => {
	return (
		<PanelBody title={__('Spacing', 'designsetgo')} initialOpen={false}>
			<RangeControl
				label={__('Content Gap', 'designsetgo')}
				value={contentGap ?? 8}
				// Reset clears the value (onChange(undefined)) so the gap falls
				// back to the themeable stylesheet default instead of staying
				// pinned to an explicit number once the slider is touched.
				onChange={(value) => setAttributes({ contentGap: value })}
				allowReset
				min={0}
				max={64}
				help={__(
					'Space between content blocks (heading, paragraph, etc.). Reset to inherit the theme default.',
					'designsetgo'
				)}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
		</PanelBody>
	);
};
