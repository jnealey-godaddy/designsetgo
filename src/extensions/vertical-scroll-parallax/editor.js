/**
 * Vertical Scroll Parallax - Editor Extension
 *
 * Adds inspector controls and save props for parallax effects
 *
 * @package
 * @since 1.0.0
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { Fragment } from '@wordpress/element';

import ParallaxPanel from './components/ParallaxPanel';
import { ALLOWED_BLOCKS, DEFAULT_PARALLAX_SETTINGS } from './constants';
import { isExtensionEnabled } from '../../utils/is-extension-enabled';

/**
 * Add parallax controls to block inspector
 */
const withParallaxControls = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		const { name, attributes, setAttributes } = props;

		// Only render for allowed blocks
		if (!ALLOWED_BLOCKS.includes(name)) {
			return <BlockEdit {...props} />;
		}

		return (
			<Fragment>
				<BlockEdit {...props} />
				<InspectorControls>
					<ParallaxPanel
						attributes={attributes}
						setAttributes={setAttributes}
					/>
				</InspectorControls>
			</Fragment>
		);
	};
}, 'withParallaxControls');

// Controls only: attributes and saved markup stay registered so existing
// content still validates when the extension is switched off.
if (isExtensionEnabled('vertical-scroll-parallax')) {
	addFilter(
		'editor.BlockEdit',
		'designsetgo/vertical-scroll-parallax/controls',
		withParallaxControls,
		20
	);
}

/**
 * Add parallax data attributes to saved block content
 *
 * @param {Object} extraProps Additional props
 * @param {Object} blockType  Block type
 * @param {Object} attributes Block attributes
 * @return {Object} Modified props
 */
function addParallaxSaveProps(extraProps, blockType, attributes) {
	// Only process allowed blocks
	if (!ALLOWED_BLOCKS.includes(blockType.name)) {
		return extraProps;
	}

	const {
		dsgoParallaxEnabled = DEFAULT_PARALLAX_SETTINGS.enabled,
		dsgoParallaxDirection = DEFAULT_PARALLAX_SETTINGS.direction,
		dsgoParallaxSpeed = DEFAULT_PARALLAX_SETTINGS.speed,
		dsgoParallaxViewportStart = DEFAULT_PARALLAX_SETTINGS.viewportStart,
		dsgoParallaxViewportEnd = DEFAULT_PARALLAX_SETTINGS.viewportEnd,
		dsgoParallaxRelativeTo = DEFAULT_PARALLAX_SETTINGS.relativeTo,
		dsgoParallaxDesktop = DEFAULT_PARALLAX_SETTINGS.enableDesktop,
		dsgoParallaxTablet = DEFAULT_PARALLAX_SETTINGS.enableTablet,
		dsgoParallaxMobile = DEFAULT_PARALLAX_SETTINGS.enableMobile,
		dsgoParallaxRotateEnabled = DEFAULT_PARALLAX_SETTINGS.rotateEnabled,
		dsgoParallaxRotateDirection = DEFAULT_PARALLAX_SETTINGS.rotateDirection,
		dsgoParallaxRotateSpeed = DEFAULT_PARALLAX_SETTINGS.rotateSpeed,
	} = attributes;

	// Only add attributes if parallax is enabled
	if (!dsgoParallaxEnabled) {
		return extraProps;
	}

	// Add data attributes for frontend JS
	const dataAttributes = {
		'data-dsgo-parallax-enabled': 'true',
		'data-dsgo-parallax-direction': dsgoParallaxDirection,
		'data-dsgo-parallax-speed': String(dsgoParallaxSpeed),
		'data-dsgo-parallax-viewport-start': String(dsgoParallaxViewportStart),
		'data-dsgo-parallax-viewport-end': String(dsgoParallaxViewportEnd),
		'data-dsgo-parallax-relative-to': dsgoParallaxRelativeTo,
		'data-dsgo-parallax-desktop': dsgoParallaxDesktop ? 'true' : 'false',
		'data-dsgo-parallax-tablet': dsgoParallaxTablet ? 'true' : 'false',
		'data-dsgo-parallax-mobile': dsgoParallaxMobile ? 'true' : 'false',
		'data-dsgo-parallax-rotate-enabled': dsgoParallaxRotateEnabled
			? 'true'
			: 'false',
		'data-dsgo-parallax-rotate-direction': dsgoParallaxRotateDirection,
		'data-dsgo-parallax-rotate-speed': String(dsgoParallaxRotateSpeed),
	};

	// Add class for CSS targeting
	const className = [extraProps.className, 'dsgo-has-parallax']
		.filter(Boolean)
		.join(' ');

	return {
		...extraProps,
		...dataAttributes,
		className,
	};
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/vertical-scroll-parallax/save-props',
	addParallaxSaveProps
);
