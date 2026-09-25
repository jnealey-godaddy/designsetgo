/**
 * Icon List Item - Style Panel
 *
 * Spacing between the blocks inside the item's content area.
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { RangeControl } from '@wordpress/components';
import { DsgoInspectorPanel } from '../../../../components/shared';
import { hasExplicitNumber } from '../../../../utils/has-explicit-value';

/**
 * @param {Object}   props               - Component props
 * @param {Object}   props.attributes    - Block attributes
 * @param {Function} props.setAttributes - Function to update attributes
 * @param {string}   props.clientId      - Block client ID
 * @return {JSX.Element} Style panel
 */
export const StylePanel = ({ attributes, setAttributes, clientId }) => {
	const { contentGap } = attributes;

	// Clearing (rather than zeroing) hands the gap back to the themeable
	// stylesheet default instead of pinning an explicit number.
	const clearContentGap = () => setAttributes({ contentGap: undefined });

	return (
		<DsgoInspectorPanel
			title={__('Style', 'designsetgo')}
			panelName="style"
			panelId={clientId}
			resetAll={clearContentGap}
		>
			<DsgoInspectorPanel.Item
				label={__('Content gap', 'designsetgo')}
				hasValue={() => hasExplicitNumber(contentGap)}
				onDeselect={clearContentGap}
				isShownByDefault
			>
				<RangeControl
					// The attribute is a bare number that save.js writes as px,
					// so the unit belongs in the label.
					label={__('Content gap (px)', 'designsetgo')}
					value={contentGap ?? 8}
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
			</DsgoInspectorPanel.Item>
		</DsgoInspectorPanel>
	);
};
