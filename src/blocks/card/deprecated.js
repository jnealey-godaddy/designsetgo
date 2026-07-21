/**
 * Card Block - Deprecations
 *
 * IMPORTANT: Add new deprecations to the TOP of the array.
 * WordPress tries them in order until one matches.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/
 */

import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { isValidImageUrl } from '../../utils/is-valid-image-url';
import { getDeprecatedBlockHTML } from '../../utils/deprecated-block-html';
import metadata from './block.json';

/**
 * V1 deprecation: before title / subtitle / bodyText / badgeText became sourced
 * from their elements.
 *
 * Each of those fields used to render ONLY when its `show*` toggle AND its text
 * were truthy (`{showTitle && title && ...}`), and the text was a plain
 * block-comment attribute. Once the text is sourced from the element, hiding a
 * field by omitting its element would reset the text to '' on reload. The current
 * save() therefore renders each field whenever its text is set and hides it with
 * a `--hidden` modifier when the toggle is off.
 *
 * Content saved with a field hidden but non-empty has the text in the block
 * comment and NO element in the markup. Under the current save() that block is
 * still "valid" (the field's text sources to '' → nothing renders → matches the
 * stored HTML), so a markup-match deprecation would never run and the text would
 * be silently lost. This entry uses isEligible to opt that VALID block into
 * migration: it fires when a field's comment text is present but the field's
 * element is absent from the stored markup, then migrate() carries the
 * comment-parsed text onto the current (sourced) schema so it re-serializes into
 * the always-present, `--hidden` element. Fields that were SHOWN are byte
 * identical under the new save() and validate directly (isEligible returns false
 * for them — their element is present).
 */

/**
 * Whether `className` appears as a whole class token inside a `class="..."`
 * attribute value. Scoping to the class attribute (not the whole markup) and
 * requiring token boundaries (start-of-value or whitespace on the left, quote or
 * whitespace on the right) avoids two false positives: a sibling class that
 * merely starts with `className` (e.g. a hypothetical `dsgo-card__body-x`), and
 * the class-name string happening to appear in a field's own RichText body text.
 *
 * @param {string} html      The stored block markup.
 * @param {string} className The class name to look for.
 * @return {boolean} Whether the class token is present.
 */
const hasClassToken = (html, className) =>
	new RegExp(`class="(?:[^"]*\\s)?${className}(?=[\\s"])`).test(html);

/**
 * True when a field has text (from the block comment) but its element is absent
 * from the stored markup — i.e. it was saved while the field was toggled off.
 *
 * @param {string} text      The field's text, parsed from the block comment.
 * @param {string} className The field element's class name to look for.
 * @param {string} html      The stored block markup.
 * @return {boolean} Whether the field's element is missing from the markup.
 */
const isTextMissingFromMarkup = (text, className, html) =>
	Boolean(text) && !hasClassToken(html, className);

const v1 = {
	apiVersion: metadata.apiVersion,
	supports: metadata.supports,
	attributes: {
		...metadata.attributes,
		// Historically plain comment attributes (not sourced from the DOM).
		title: { type: 'string', default: '' },
		subtitle: { type: 'string', default: '' },
		bodyText: { type: 'string', default: '' },
		badgeText: { type: 'string', default: '' },
	},
	isEligible(attributes, innerBlocks, extra) {
		const html = getDeprecatedBlockHTML(extra);
		if (!html) {
			return false;
		}
		return (
			isTextMissingFromMarkup(
				attributes.title,
				'dsgo-card__title',
				html
			) ||
			isTextMissingFromMarkup(
				attributes.subtitle,
				'dsgo-card__subtitle',
				html
			) ||
			isTextMissingFromMarkup(
				attributes.bodyText,
				'dsgo-card__body',
				html
			) ||
			isTextMissingFromMarkup(
				attributes.badgeText,
				'dsgo-card__badge',
				html
			)
		);
	},
	migrate(attributes) {
		return attributes;
	},
	save({ attributes }) {
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

		const blockStyles = {};
		if (borderColor && visualStyle !== 'minimal') {
			blockStyles.borderColor = borderColor;
			blockStyles.borderWidth =
				visualStyle === 'outlined' ? '2px' : '1px';
			blockStyles.borderStyle = 'solid';
		}

		const blockProps = useBlockProps.save({
			className: `dsgo-card dsgo-card--${layoutPreset} dsgo-card--style-${visualStyle}`,
			style: blockStyles,
		});

		const innerBlocksProps = useInnerBlocksProps.save({
			className: 'dsgo-card__cta',
		});

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

		const badgeStyles = {};
		if (badgeBackgroundColor) {
			badgeStyles.backgroundColor = badgeBackgroundColor;
		}
		if (badgeTextColor) {
			badgeStyles.color = badgeTextColor;
		}

		const overlayStyles = {};
		if (layoutPreset === 'background') {
			if (overlayColor) {
				overlayStyles.backgroundColor = overlayColor;
				overlayStyles.opacity = overlayOpacity / 100;
			} else {
				overlayStyles.backgroundColor =
					'var(--wp--preset--color--contrast, #000)';
				overlayStyles.opacity = overlayOpacity / 100;
			}
		}

		const contentAlignmentClass = `dsgo-card__content--${contentAlignment}`;

		const renderBadge = () => {
			if (!showBadge || !badgeText) {
				return null;
			}

			const badgeClass =
				badgeStyle === 'floating'
					? `dsgo-card__badge dsgo-card__badge--floating dsgo-card__badge--${badgeFloatingPosition}`
					: `dsgo-card__badge dsgo-card__badge--inline dsgo-card__badge--${badgeInlinePosition}`;

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
						<div
							className="dsgo-card__overlay"
							style={overlayStyles}
						/>
					</div>
				);
			}

			const altText = imageAlt || __('Card image', 'designsetgo');
			const imageProps = {
				src: imageUrl,
				alt: altText,
				className: 'dsgo-card__image',
				style: imageStyles,
				loading: 'lazy',
			};

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

		const renderContent = () => (
			<div
				className={`dsgo-card__content ${layoutPreset === 'background' ? contentAlignmentClass : ''}`}
			>
				{badgeStyle === 'inline' &&
					badgeInlinePosition === 'above-title' &&
					renderBadge()}

				{showTitle && title && (
					<RichText.Content
						tagName="h3"
						className="dsgo-card__title"
						value={title}
					/>
				)}

				{badgeStyle === 'inline' &&
					badgeInlinePosition === 'below-title' &&
					renderBadge()}

				{showSubtitle && subtitle && (
					<RichText.Content
						tagName="p"
						className="dsgo-card__subtitle"
						value={subtitle}
					/>
				)}

				{showBody && bodyText && (
					<RichText.Content
						tagName="p"
						className="dsgo-card__body"
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
	},
};

export default [v1];
