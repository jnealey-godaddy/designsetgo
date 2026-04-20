/**
 * Hover Effects Extension - Inspector Panel
 *
 * Lazy-loaded inspector panel that lets authors pick one of the
 * preset hover micro-interactions.
 *
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { HOVER_EFFECTS } from './constants';

export default function HoverEffectsPanel(props) {
	const { attributes, setAttributes } = props;
	const { dsgoHoverEffect = '' } = attributes;

	return (
		<InspectorControls>
			<PanelBody
				title={__('Hover Effect', 'designsetgo')}
				initialOpen={false}
			>
				<SelectControl
					label={__('Effect', 'designsetgo')}
					value={dsgoHoverEffect}
					options={HOVER_EFFECTS}
					onChange={(value) =>
						setAttributes({ dsgoHoverEffect: value })
					}
					help={__(
						'Subtle animation that plays when visitors hover this block.',
						'designsetgo'
					)}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</PanelBody>
		</InspectorControls>
	);
}
