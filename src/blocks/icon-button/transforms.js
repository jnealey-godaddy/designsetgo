/**
 * Icon Button Block - Transforms
 *
 * Allows transforming to core/buttons + core/button for plugin deactivation.
 * Note: core/button does not support icons, so the icon, iconPosition,
 * iconSize, iconGap, hoverAnimation, and modalCloseId attributes are lost.
 *
 * @since 1.0.0
 */

import { createBlock } from '@wordpress/blocks';

const transforms = {
	from: [],
	to: [
		{
			type: 'block',
			blocks: ['core/buttons'],
			transform: (attributes) => {
				const {
					text,
					url,
					linkTarget,
					rel,
					justification,
					fullWidth,
					style,
					backgroundColor,
					textColor,
					fontSize,
					anchor,
				} = attributes;

				// Note: DSG-specific features not available in core/button:
				// - icon, iconPosition, iconSize, iconGap (icon support)
				// - hoverAnimation, hoverBackgroundColor, hoverTextColor (hover effects)
				// - modalCloseId (modal integration)

				// Build core/button attributes
				const buttonAttributes = {
					text,
					...(url && { url }),
					...(linkTarget && linkTarget !== '_self' && { linkTarget }),
					...(rel && { rel }),
					...(backgroundColor && { backgroundColor }),
					...(textColor && { textColor }),
					...(fontSize && { fontSize }),
					...(anchor && { anchor }),
					...(style && { style }),
					// core/button width attribute accepts 25/50/75/100 (percentage)
					...(fullWidth && { width: 100 }),
				};

				// core/button must be wrapped in core/buttons
				const innerButton = createBlock(
					'core/button',
					buttonAttributes
				);

				return createBlock(
					'core/buttons',
					{
						layout: {
							type: 'flex',
							justifyContent: justification || 'left',
						},
					},
					[innerButton]
				);
			},
		},
	],
};

export default transforms;
