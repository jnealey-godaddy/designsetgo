/**
 * Responsive Visibility Extension
 *
 * Adds device-based visibility controls to all blocks (core and custom).
 * Editor panel is lazy-loaded to reduce initial bundle size.
 *
 * @since 1.0.0
 */

import './editor.scss';

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { lazy, Suspense } from '@wordpress/element';
import { shouldExtendBlock } from '../../utils/should-extend-block';
import { isExtensionEnabled } from '../../utils/is-extension-enabled';

// Lazy-load editor panel
const ResponsiveVisibilityPanel = lazy(
	() => import(/* webpackChunkName: "ext-responsive" */ './edit')
);

/**
 * Add responsive visibility attributes to all blocks
 *
 * @param {Object} settings Block settings
 * @param {string} name     Block name
 */
function addResponsiveVisibilityAttributes(settings, name) {
	if (!shouldExtendBlock(name)) {
		return settings;
	}
	return {
		...settings,
		attributes: {
			...settings.attributes,
			dsgoHideOnDesktop: { type: 'boolean', default: false },
			dsgoHideOnTablet: { type: 'boolean', default: false },
			dsgoHideOnMobile: { type: 'boolean', default: false },
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'designsetgo/add-responsive-visibility-attributes',
	addResponsiveVisibilityAttributes
);

/**
 * Add responsive visibility controls to block inspector (lazy-loaded)
 */
const withResponsiveVisibilityControl = createHigherOrderComponent(
	(BlockEdit) => {
		return (props) => {
			if (!shouldExtendBlock(props.name)) {
				return <BlockEdit {...props} />;
			}

			return (
				<>
					<BlockEdit {...props} />
					<Suspense fallback={null}>
						<ResponsiveVisibilityPanel {...props} />
					</Suspense>
				</>
			);
		};
	},
	'withResponsiveVisibilityControl'
);

// Controls only: attributes and saved markup stay registered so existing
// content still validates when the extension is switched off.
if (isExtensionEnabled('responsive')) {
	addFilter(
		'editor.BlockEdit',
		'designsetgo/add-responsive-visibility-control',
		withResponsiveVisibilityControl,
		20
	);
}

/**
 * Add visual styling in editor when block is hidden on any device
 */
const withResponsiveVisibilityIndicator = createHigherOrderComponent(
	(BlockListBlock) => {
		return (props) => {
			const { attributes, wrapperProps = {}, name } = props;
			const { dsgoHideOnDesktop, dsgoHideOnTablet, dsgoHideOnMobile } =
				attributes;

			if (!shouldExtendBlock(name)) {
				return <BlockListBlock {...props} />;
			}

			const hiddenDevices = [];
			if (dsgoHideOnDesktop) {
				hiddenDevices.push('D');
			}
			if (dsgoHideOnTablet) {
				hiddenDevices.push('T');
			}
			if (dsgoHideOnMobile) {
				hiddenDevices.push('M');
			}

			if (hiddenDevices.length === 0) {
				return <BlockListBlock {...props} />;
			}

			// Use wrapperProps for both class and data attribute — passing className
			// as a separate prop is silently dropped when wrapperProps is present.
			// Include props.className so classes from other filters are preserved.
			const updatedWrapperProps = {
				...wrapperProps,
				className: [
					props.className,
					wrapperProps.className,
					'dsgo-has-responsive-visibility',
				]
					.filter(Boolean)
					.join(' '),
				'data-hidden-devices': hiddenDevices.join(''),
			};

			return (
				<BlockListBlock {...props} wrapperProps={updatedWrapperProps} />
			);
		};
	},
	'withResponsiveVisibilityIndicator'
);

addFilter(
	'editor.BlockListBlock',
	'designsetgo/add-responsive-visibility-indicator',
	withResponsiveVisibilityIndicator,
	20
);

/**
 * Apply responsive visibility CSS classes on frontend
 *
 * @param {Object} props      Extra props
 * @param {Object} blockType  Block type
 * @param {Object} attributes Block attributes
 */
function applyResponsiveVisibilityClasses(props, blockType, attributes) {
	const { dsgoHideOnDesktop, dsgoHideOnTablet, dsgoHideOnMobile } =
		attributes;

	if (!shouldExtendBlock(blockType.name)) {
		return props;
	}

	const visibilityClasses = [];
	if (dsgoHideOnDesktop) {
		visibilityClasses.push('dsgo-hide-desktop');
	}
	if (dsgoHideOnTablet) {
		visibilityClasses.push('dsgo-hide-tablet');
	}
	if (dsgoHideOnMobile) {
		visibilityClasses.push('dsgo-hide-mobile');
	}

	if (visibilityClasses.length === 0) {
		return props;
	}

	return {
		...props,
		className:
			`${props.className || ''} ${visibilityClasses.join(' ')}`.trim(),
	};
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'designsetgo/apply-responsive-visibility-classes',
	applyResponsiveVisibilityClasses
);
