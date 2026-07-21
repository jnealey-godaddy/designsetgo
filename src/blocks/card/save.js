/**
 * WordPress dependencies
 */
import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import clsx from 'clsx';
import { isValidImageUrl } from '../../utils/is-valid-image-url';

/**
 * Save component for Card block
 *
 * @param {Object} props            - Component props
 * @param {Object} props.attributes - Block attributes
 * @return {Element} Save component
 */
export default function CardSave({ attributes }) {
	const {
		layoutPreset,
		imageUrl,
		imageAlt,
		imageAspectRatio,
		imageCustomAspectRatio,
		imageObjectFit,
		imageFocalPoint,
		badgeText,
		badgeStyle,
		badgeFloatingPosition,
		badgeInlinePosition,
		badgeBackgroundColor,
		badgeTextColor,
		title,
		subtitle,
		bodyText,
		overlayOpacity,
		overlayColor,
		contentAlignment,
		visualStyle,
		borderColor,
		showImage,
		showTitle,
		showSubtitle,
		showBody,
		showBadge,
		showCta,
	} = attributes;

	// Build block props with border color
	const blockStyles = {};
	// Only apply custom border color on styles that have borders (not minimal)
	if (borderColor && visualStyle !== 'minimal') {
		blockStyles.borderColor = borderColor;
		// Ensure border exists
		blockStyles.borderWidth = visualStyle === 'outlined' ? '2px' : '1px';
		blockStyles.borderStyle = 'solid';
	}

	const blockProps = useBlockProps.save({
		className: `dsgo-card dsgo-card--${layoutPreset} dsgo-card--style-${visualStyle}`,
		style: blockStyles,
	});

	// Inner blocks props for CTA area
	const innerBlocksProps = useInnerBlocksProps.save({
		className: 'dsgo-card__cta',
	});

	// Calculate image styles
	const imageStyles = {};
	if (imageAspectRatio !== 'original') {
		if (imageAspectRatio === 'custom' && imageCustomAspectRatio) {
			imageStyles.aspectRatio = imageCustomAspectRatio;
		} else if (imageAspectRatio === '16-9') {
			imageStyles.aspectRatio = '16 / 9';
		} else if (imageAspectRatio === '4-3') {
			imageStyles.aspectRatio = '4 / 3';
		} else if (imageAspectRatio === '1-1') {
			imageStyles.aspectRatio = '1 / 1';
		}
	}
	if (imageObjectFit) {
		imageStyles.objectFit = imageObjectFit;
	}
	if (imageObjectFit === 'cover' && imageFocalPoint) {
		imageStyles.objectPosition = `${Number(imageFocalPoint.x) * 100}% ${Number(imageFocalPoint.y) * 100}%`;
	}

	// Calculate badge styles
	const badgeStyles = {};
	if (badgeBackgroundColor) {
		badgeStyles.backgroundColor = badgeBackgroundColor;
	}
	if (badgeTextColor) {
		badgeStyles.color = badgeTextColor;
	}

	// Calculate overlay styles for background layout
	const overlayStyles = {};
	if (layoutPreset === 'background') {
		if (overlayColor) {
			overlayStyles.backgroundColor = overlayColor;
			overlayStyles.opacity = overlayOpacity / 100;
		} else {
			// Use theme contrast color at full opacity, let overlayOpacity control transparency
			overlayStyles.backgroundColor =
				'var(--wp--preset--color--contrast, #000)';
			overlayStyles.opacity = overlayOpacity / 100;
		}
	}

	// Content alignment class
	const contentAlignmentClass = `dsgo-card__content--${contentAlignment}`;

	// Render badge. The badge element stays in the markup whenever there is badge
	// text so `badgeText` always has an element to be sourced from; the "hide
	// badge" toggle applies a `--hidden` modifier (CSS display:none) instead of
	// dropping the element, which would reset the sourced text on reload.
	const renderBadge = () => {
		if (!badgeText) {
			return null;
		}

		const badgeClass = clsx(
			'dsgo-card__badge',
			badgeStyle === 'floating'
				? `dsgo-card__badge--floating dsgo-card__badge--${badgeFloatingPosition}`
				: `dsgo-card__badge--inline dsgo-card__badge--${badgeInlinePosition}`,
			!showBadge && 'dsgo-card__badge--hidden'
		);

		return (
			<span
				className={badgeClass}
				style={badgeStyles}
				role="status"
				aria-label={__('Badge', 'designsetgo')}
			>
				{badgeText}
			</span>
		);
	};

	// Render image
	const renderImage = () => {
		if (
			!showImage ||
			layoutPreset === 'minimal' ||
			!imageUrl ||
			!isValidImageUrl(imageUrl)
		) {
			return null;
		}

		if (layoutPreset === 'background') {
			return (
				<div
					className="dsgo-card__background"
					style={{ backgroundImage: `url(${imageUrl})` }}
				>
					<div className="dsgo-card__overlay" style={overlayStyles} />
				</div>
			);
		}

		// Provide fallback alt text for accessibility
		const altText = imageAlt || __('Card image', 'designsetgo');
		const imageProps = {
			src: imageUrl,
			alt: altText,
			className: 'dsgo-card__image',
			style: imageStyles,
			loading: 'lazy',
		};

		// Hide decorative images from screen readers
		if (!imageAlt) {
			imageProps['aria-hidden'] = 'true';
		}

		return (
			<div className="dsgo-card__image-wrapper">
				{/* eslint-disable-next-line jsx-a11y/alt-text */}
				<img {...imageProps} />
			</div>
		);
	};

	// Render content
	const renderContent = () => (
		<div
			className={`dsgo-card__content ${layoutPreset === 'background' ? contentAlignmentClass : ''}`}
		>
			{badgeStyle === 'inline' &&
				badgeInlinePosition === 'above-title' &&
				renderBadge()}

			{title && (
				<RichText.Content
					tagName="h3"
					className={clsx('dsgo-card__title', {
						'dsgo-card__title--hidden': !showTitle,
					})}
					value={title}
				/>
			)}

			{badgeStyle === 'inline' &&
				badgeInlinePosition === 'below-title' &&
				renderBadge()}

			{subtitle && (
				<RichText.Content
					tagName="p"
					className={clsx('dsgo-card__subtitle', {
						'dsgo-card__subtitle--hidden': !showSubtitle,
					})}
					value={subtitle}
				/>
			)}

			{bodyText && (
				<RichText.Content
					tagName="p"
					className={clsx('dsgo-card__body', {
						'dsgo-card__body--hidden': !showBody,
					})}
					value={bodyText}
				/>
			)}

			{showCta && <div {...innerBlocksProps} />}
		</div>
	);

	return (
		<div {...blockProps}>
			{badgeStyle === 'floating' && renderBadge()}
			{layoutPreset === 'background' && renderImage()}

			<div className="dsgo-card__inner">
				{layoutPreset !== 'background' && renderImage()}
				{renderContent()}
			</div>
		</div>
	);
}
