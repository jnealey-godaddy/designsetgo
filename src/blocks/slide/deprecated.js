import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import { convertPresetToCSSVar } from '../../utils/convert-preset-to-css-var';

const sharedSupports = {
	anchor: false,
	html: false,
	reusable: false,
	spacing: {
		margin: true,
		padding: true,
		blockGap: true,
	},
	color: {
		background: true,
		gradient: true,
		text: true,
	},
	typography: {
		fontSize: true,
		lineHeight: true,
		__experimentalFontFamily: true,
		__experimentalFontWeight: true,
		__experimentalTextTransform: true,
		__experimentalLetterSpacing: true,
	},
};

/**
 * Deprecated version 1: Had enableOverlay attribute
 *
 * Changed in commit e5b9069:
 * - Removed enableOverlay attribute
 * - Overlay now automatically enabled when overlayColor is set
 * - Changed default overlayOpacity from 40 to 80
 */
const v1 = {
	apiVersion: 3,
	supports: sharedSupports,
	attributes: {
		backgroundImage: {
			type: 'object',
			default: {
				url: '',
				id: 0,
				alt: '',
			},
		},
		backgroundSize: {
			type: 'string',
			default: 'cover',
		},
		backgroundPosition: {
			type: 'string',
			default: 'center center',
		},
		backgroundRepeat: {
			type: 'string',
			default: 'no-repeat',
		},
		enableOverlay: {
			type: 'boolean',
			default: false,
		},
		overlayColor: {
			type: 'string',
			default: '#000000', // Old default - critical for migration
		},
		overlayOpacity: {
			type: 'number',
			default: 40, // Old default was 40
		},
		contentVerticalAlign: {
			type: 'string',
			default: 'center',
		},
		contentHorizontalAlign: {
			type: 'string',
			default: 'center',
		},
		minHeight: {
			type: 'string',
			default: '',
		},
	},
	isEligible(attributes) {
		// v1 blocks have the enableOverlay boolean attribute
		return Object.prototype.hasOwnProperty.call(
			attributes,
			'enableOverlay'
		);
	},
	save({ attributes }) {
		const {
			backgroundImage,
			backgroundSize,
			backgroundPosition,
			backgroundRepeat,
			enableOverlay,
			overlayColor,
			overlayOpacity,
			contentVerticalAlign,
			contentHorizontalAlign,
			minHeight,
		} = attributes;

		const slideClasses = classnames('dsgo-slide', {
			'dsgo-slide--has-background': backgroundImage?.url,
			'dsgo-slide--has-overlay': enableOverlay,
		});

		const backgroundStyles = backgroundImage?.url
			? {
					backgroundImage: `url(${backgroundImage.url})`,
					backgroundSize,
					backgroundPosition,
					backgroundRepeat,
				}
			: {};

		const overlayStyles = enableOverlay
			? {
					'--dsgo-slide-overlay-color':
						convertPresetToCSSVar(overlayColor),
					'--dsgo-slide-overlay-opacity': String(
						overlayOpacity / 100
					),
				}
			: {};

		const alignmentStyles = {
			'--dsgo-slide-content-vertical-align': contentVerticalAlign,
			'--dsgo-slide-content-horizontal-align': contentHorizontalAlign,
		};

		const heightStyles = minHeight ? { minHeight } : {};

		const blockProps = useBlockProps.save({
			className: slideClasses,
			style: {
				...backgroundStyles,
				...overlayStyles,
				...alignmentStyles,
				...heightStyles,
			},
			role: 'group',
			'aria-roledescription': 'slide',
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-slide__content',
		});

		return (
			<div {...blockProps}>
				{enableOverlay && (
					<div
						className="dsgo-slide__overlay"
						style={{
							backgroundColor:
								convertPresetToCSSVar(overlayColor),
							opacity: overlayOpacity / 100,
						}}
					/>
				)}
				<div {...innerBlocksProps} />
			</div>
		);
	},
	migrate(attributes) {
		const { enableOverlay, overlayColor, ...rest } = attributes;

		// If overlay was enabled but color is empty (using old default #000000),
		// explicitly set it so the overlay continues to display
		const migratedColor =
			enableOverlay && !overlayColor ? '#000000' : overlayColor;

		return {
			...rest,
			overlayColor: migratedColor,
		};
	},
};

export default [v1];
