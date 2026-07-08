/**
 * Blobs Block - Deprecated Versions
 *
 * Handles backwards compatibility for old blob block markup
 *
 * @since 1.0.0
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import {
	convertPresetToCSSVar,
	convertColorToCSSVar,
} from '../../utils/convert-preset-to-css-var';

/**
 * Shared supports for all deprecated versions.
 * Uses __experimentalBorder (the historical name) instead of border.
 */
const sharedSupports = {
	anchor: true,
	align: ['left', 'center', 'right', 'wide', 'full'],
	html: false,
	spacing: {
		margin: true,
		padding: true,
		__experimentalDefaultControls: {
			margin: true,
			padding: true,
		},
	},
	color: {
		background: true,
		text: true,
		gradients: true,
		__experimentalDefaultControls: {
			background: true,
			text: true,
		},
	},
	background: {
		backgroundImage: true,
		backgroundSize: true,
		backgroundPosition: true,
		__experimentalDefaultControls: {
			backgroundImage: true,
		},
	},
	typography: {
		fontSize: true,
		lineHeight: true,
		writingMode: true,
		fontFamily: true,
		fontWeight: true,
		__experimentalDefaultControls: {
			fontSize: true,
		},
	},
	__experimentalBorder: {
		radius: false,
		__experimentalDefaultControls: {
			radius: false,
		},
	},
};

/**
 * Version 3: Before the native, kit-controllable max-width control
 *
 * Blobs used to receive its max-width from the generic `max-width` extension
 * (src/extensions/max-width), which stored a `dsgoMaxWidth` attribute and, on
 * save, added the `dsgo-has-max-width` class plus an inline
 * `max-width;margin-left:auto;margin-right:auto` to the wrapper. Blobs now owns
 * a native `maxWidth` attribute that emits the class + a kit-controllable
 * `--dsgo-blob-max-width` custom property instead (the raw max-width / centering
 * margins moved to the stylesheet), and is excluded from the generic extension.
 *
 * This deprecation detects the old extension markup (wrapper carries
 * `dsgo-has-max-width` with a RAW inline `max-width:` — distinct from the new
 * `--dsgo-blob-max-width:` var) and migrates `dsgoMaxWidth` → `maxWidth` so
 * existing content upgrades silently instead of showing a recovery warning.
 * Blobs without a max-width are unaffected (they never carried the class) and
 * validate directly against the current save().
 */
const v3 = {
	supports: sharedSupports,
	attributes: {
		align: {
			type: 'string',
		},
		blobShape: {
			type: 'string',
			default: 'shape-1',
		},
		blobAnimation: {
			type: 'string',
			default: 'none',
		},
		animationDuration: {
			type: 'string',
			default: '8s',
		},
		animationEasing: {
			type: 'string',
			default: 'ease-in-out',
		},
		size: {
			type: 'string',
			default: '300px',
		},
		height: {
			type: 'string',
			default: '',
		},
		enableOverlay: {
			type: 'boolean',
			default: false,
		},
		overlayColor: {
			type: 'string',
			default: '',
		},
		overlayOpacity: {
			type: 'number',
			default: 80,
		},
		// Legacy attribute injected by the generic max-width extension.
		dsgoMaxWidth: {
			type: 'string',
			default: '',
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		if (!innerHTML) {
			return false;
		}
		// Scope the check to the Blobs wrapper's OWN opening tag. Blobs accepts
		// arbitrary nested blocks, and the generic max-width extension still
		// stamps the same `dsgo-has-max-width` class + a raw inline `max-width:`
		// onto any non-excluded nested child — so scanning the whole subtree
		// could false-match a valid *native* Blobs block that merely contains
		// such a child, dropping its maxWidth and marking it invalid. The
		// wrapper is the block's root element; isolate just its opening tag.
		// Old extension markup put the raw max-width on the wrapper root; the new
		// native format writes `--dsgo-blob-max-width` there instead (excluded by
		// the `[^-]` guard). The first `dsgo-blobs-wrapper` occurrence is the
		// root's class (children never carry it), so we take the element opening
		// tag around it.
		const wrapperIdx = innerHTML.indexOf('dsgo-blobs-wrapper');
		if (wrapperIdx === -1) {
			return false;
		}
		const tagStart = innerHTML.lastIndexOf('<', wrapperIdx);
		const tagEnd = innerHTML.indexOf('>', wrapperIdx);
		if (tagStart === -1 || tagEnd === -1) {
			return false;
		}
		const openingTag = innerHTML.slice(tagStart, tagEnd + 1);
		return (
			openingTag.includes('dsgo-has-max-width') &&
			/(?:^|[^-])max-width:/.test(openingTag)
		);
	},
	save({ attributes }) {
		const {
			blobShape,
			blobAnimation,
			animationDuration,
			animationEasing,
			size,
			height,
			enableOverlay,
			overlayColor,
			overlayOpacity,
			dsgoMaxWidth,
			align,
		} = attributes;

		const blobClasses = classnames('dsgo-blobs', {
			[`dsgo-blobs--${blobShape}`]: blobShape,
			[`dsgo-blobs--${blobAnimation}`]:
				blobAnimation && blobAnimation !== 'none',
		});

		const customStyles = {
			'--dsgo-blob-size': size,
			...(height ? { '--dsgo-blob-height': height } : {}),
			'--dsgo-blob-animation-duration': animationDuration,
			'--dsgo-blob-animation-easing': animationEasing,
		};

		// Reproduce the generic extension's align-aware centering margins.
		let marginLeft = 'auto';
		let marginRight = 'auto';
		if (align === 'left') {
			marginLeft = '0';
		} else if (align === 'right') {
			marginRight = '0';
		}

		const blockProps = useBlockProps.save({
			className: classnames('dsgo-blobs-wrapper', {
				'dsgo-has-max-width': !!dsgoMaxWidth,
			}),
			...(dsgoMaxWidth && {
				style: {
					maxWidth: dsgoMaxWidth,
					marginLeft,
					marginRight,
				},
			}),
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-blobs__content',
		});

		return (
			<div {...blockProps}>
				<div
					className={blobClasses}
					style={customStyles}
					data-blob-animation={blobAnimation}
				>
					{enableOverlay && (
						<div
							className="dsgo-blobs__overlay"
							style={{
								backgroundColor:
									convertColorToCSSVar(overlayColor),
								opacity: overlayOpacity / 100,
							}}
						/>
					)}
					<div className="dsgo-blobs__shape">
						<div {...innerBlocksProps} />
					</div>
				</div>
			</div>
		);
	},
	migrate(attributes) {
		// Move the extension value onto the native attribute and drop the
		// legacy key so the current save() owns the markup.
		const { dsgoMaxWidth, ...rest } = attributes;
		if (dsgoMaxWidth) {
			return { ...rest, maxWidth: dsgoMaxWidth };
		}
		return rest;
	},
};

// Version 1: Original structure without wrapper
const v1 = {
	supports: sharedSupports,
	attributes: {
		blobShape: {
			type: 'string',
			default: 'shape-1',
		},
		blobAnimation: {
			type: 'string',
			default: 'none',
		},
		animationDuration: {
			type: 'string',
			default: '8s',
		},
		animationEasing: {
			type: 'string',
			default: 'ease-in-out',
		},
		size: {
			type: 'string',
			default: '300px',
		},
		enableOverlay: {
			type: 'boolean',
			default: false,
		},
		overlayColor: {
			type: 'string',
			default: '#000000',
		},
		overlayOpacity: {
			type: 'number',
			default: 50,
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v1 blocks have no wrapper div - the dsgo-blobs class is directly on the block wrapper
		return innerHTML && !innerHTML.includes('dsgo-blobs-wrapper');
	},
	save: ({ attributes }) => {
		const {
			blobShape,
			blobAnimation,
			animationDuration,
			animationEasing,
			size,
			enableOverlay,
			overlayColor,
			overlayOpacity,
		} = attributes;

		const blobClasses = classnames('dsgo-blobs', {
			[`dsgo-blobs--${blobShape}`]: blobShape,
			[`dsgo-blobs--${blobAnimation}`]:
				blobAnimation && blobAnimation !== 'none',
		});

		const customStyles = {
			'--dsgo-blob-size': size,
			'--dsgo-blob-animation-duration': animationDuration,
			'--dsgo-blob-animation-easing': animationEasing,
		};

		const blockProps = useBlockProps.save({
			className: blobClasses,
			style: customStyles,
			'data-blob-animation': blobAnimation,
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-blobs__content',
		});

		return (
			<div {...blockProps}>
				{enableOverlay && (
					<div
						className="dsgo-blobs__overlay"
						style={{
							backgroundColor:
								convertPresetToCSSVar(overlayColor),
							opacity: overlayOpacity / 100,
						}}
					/>
				)}
				<div className="dsgo-blobs__shape">
					<div {...innerBlocksProps} />
				</div>
			</div>
		);
	},
	migrate(attributes) {
		return attributes;
	},
};

// Version 2: With wrapper but without align attribute
const v2 = {
	supports: sharedSupports,
	attributes: {
		blobShape: {
			type: 'string',
			default: 'shape-1',
		},
		blobAnimation: {
			type: 'string',
			default: 'none',
		},
		animationDuration: {
			type: 'string',
			default: '8s',
		},
		animationEasing: {
			type: 'string',
			default: 'ease-in-out',
		},
		size: {
			type: 'string',
			default: '300px',
		},
		enableOverlay: {
			type: 'boolean',
			default: false,
		},
		overlayColor: {
			type: 'string',
			default: '',
		},
		overlayOpacity: {
			type: 'number',
			default: 80,
		},
	},
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// v2 blocks have dsgo-blobs-wrapper but no align attribute
		return (
			innerHTML &&
			innerHTML.includes('dsgo-blobs-wrapper') &&
			attributes.align === undefined
		);
	},
	save({ attributes }) {
		const {
			blobShape,
			blobAnimation,
			animationDuration,
			animationEasing,
			size,
			enableOverlay,
			overlayColor,
			overlayOpacity,
		} = attributes;

		const blobClasses = classnames('dsgo-blobs', {
			[`dsgo-blobs--${blobShape}`]: blobShape,
			[`dsgo-blobs--${blobAnimation}`]:
				blobAnimation && blobAnimation !== 'none',
		});

		const customStyles = {
			'--dsgo-blob-size': size,
			'--dsgo-blob-animation-duration': animationDuration,
			'--dsgo-blob-animation-easing': animationEasing,
		};

		const blockProps = useBlockProps.save({
			className: 'dsgo-blobs-wrapper',
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-blobs__content',
		});

		return (
			<div {...blockProps}>
				<div
					className={blobClasses}
					style={customStyles}
					data-blob-animation={blobAnimation}
				>
					{enableOverlay && (
						<div
							className="dsgo-blobs__overlay"
							style={{
								backgroundColor:
									convertPresetToCSSVar(overlayColor),
								opacity: overlayOpacity / 100,
							}}
						/>
					)}
					<div className="dsgo-blobs__shape">
						<div {...innerBlocksProps} />
					</div>
				</div>
			</div>
		);
	},
	migrate(oldAttributes) {
		// Migrate to new version with align attribute
		return {
			...oldAttributes,
			align: undefined, // WordPress will use default from supports
		};
	},
};

export default [v3, v2, v1];
