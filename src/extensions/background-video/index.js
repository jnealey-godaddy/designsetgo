/**
 * Background Video Extension
 *
 * Adds background video capability to DesignSetGo container blocks.
 * Editor controls are lazy-loaded to reduce initial bundle size.
 *
 * @package
 * @since 1.0.0
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { lazy, Suspense } from '@wordpress/element';
import { shouldExtendBlock } from '../../utils/should-extend-block';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

/**
 * Container blocks that support background video
 */
const ALLOWED_BLOCKS = [
	'designsetgo/section',
	'designsetgo/row',
	'designsetgo/grid',
	'designsetgo/reveal',
	'designsetgo/flip-card',
	'designsetgo/flip-card-front',
	'designsetgo/flip-card-back',
	'designsetgo/accordion',
	'designsetgo/accordion-item',
	'designsetgo/tabs',
	'designsetgo/tab',
	'designsetgo/scroll-accordion',
	'designsetgo/scroll-accordion-item',
	'designsetgo/scroll-marquee',
	'designsetgo/image-accordion',
	'designsetgo/image-accordion-item',
];

// Lazy-load editor components
const BackgroundVideoPanel = lazy(() =>
	import(/* webpackChunkName: "ext-background-video" */ './edit').then(
		(m) => ({ default: m.BackgroundVideoPanel })
	)
);
const BackgroundVideoPreview = lazy(() =>
	import(/* webpackChunkName: "ext-background-video" */ './edit').then(
		(m) => ({ default: m.BackgroundVideoPreview })
	)
);

/**
 * Add background video attributes to allowed container blocks
 *
 * @param {Object} settings Block settings
 * @param {string} name     Block name
 */
function addBackgroundVideoAttributes(settings, name) {
	if (!shouldExtendBlock(name)) {
		return settings;
	}

	if (!ALLOWED_BLOCKS.includes(name)) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			dsgoVideoUrl: { type: 'string', default: '' },
			dsgoVideoPoster: { type: 'string', default: '' },
			dsgoVideoMuted: { type: 'boolean', default: true },
			dsgoVideoLoop: { type: 'boolean', default: true },
			dsgoVideoAutoplay: { type: 'boolean', default: true },
			dsgoVideoMobileHide: { type: 'boolean', default: true },
			dsgoVideoOverlayColor: { type: 'string', default: '' },
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/background-video-attributes',
	addBackgroundVideoAttributes
);

/**
 * Add background video controls to block inspector (lazy-loaded)
 */
const withBackgroundVideoControls = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		if (!ALLOWED_BLOCKS.includes(props.name)) {
			return <BlockEdit {...props} />;
		}

		return (
			<>
				<BlockEdit {...props} />
				<Suspense fallback={null}>
					<BackgroundVideoPanel {...props} />
				</Suspense>
			</>
		);
	};
}, 'withBackgroundVideoControls');

addFilter(
	'editor.BlockEdit',
	'designsetgo/background-video-controls',
	withBackgroundVideoControls,
	5
);

/**
 * Add background video wrapper in editor (lazy-loaded)
 */
const withBackgroundVideoEdit = createHigherOrderComponent((BlockListBlock) => {
	return (props) => {
		const { attributes, name } = props;

		if (!ALLOWED_BLOCKS.includes(name) || !attributes.dsgoVideoUrl) {
			return <BlockListBlock {...props} />;
		}

		return (
			<Suspense fallback={<BlockListBlock {...props} />}>
				<BackgroundVideoPreview
					BlockListBlock={BlockListBlock}
					{...props}
				/>
			</Suspense>
		);
	};
}, 'withBackgroundVideoEdit');

addFilter(
	'editor.BlockListBlock',
	'designsetgo/background-video-edit',
	withBackgroundVideoEdit
);

/**
 * Add background video classes and data attributes to save
 *
 * @param {Object} props      Extra props
 * @param {Object} blockType  Block type
 * @param {Object} attributes Block attributes
 */
function addBackgroundVideoSaveProps(props, blockType, attributes) {
	const { dsgoVideoUrl } = attributes;

	if (!dsgoVideoUrl) {
		return props;
	}

	return {
		...props,
		className: `${props.className || ''} dsgo-has-video-background`.trim(),
		'data-video-url': dsgoVideoUrl,
		'data-video-poster': attributes.dsgoVideoPoster || '',
		'data-video-muted': attributes.dsgoVideoMuted ? 'true' : 'false',
		'data-video-loop': attributes.dsgoVideoLoop ? 'true' : 'false',
		'data-video-autoplay': attributes.dsgoVideoAutoplay ? 'true' : 'false',
		'data-video-mobile-hide': attributes.dsgoVideoMobileHide
			? 'true'
			: 'false',
		// Convert WordPress preset format (`var:preset|color|slug`) and bare
		// slugs to a real CSS color string so the frontend can validate and
		// apply the value directly, without parsing preset syntax at runtime.
		'data-video-overlay-color':
			convertColorToCSSVar(attributes.dsgoVideoOverlayColor) || '',
	};
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/background-video-save-props',
	addBackgroundVideoSaveProps
);
