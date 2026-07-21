/**
 * Table of Contents Block - Deprecations
 *
 * IMPORTANT: Add new deprecations to the TOP of the array.
 * WordPress tries them in order until one matches.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/
 */

import { useBlockProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';
import metadata from './block.json';

/**
 * V1 deprecation: before `titleText` became sourced from the title element.
 *
 * The title element used to be rendered only when the `showTitle` toggle was on
 * (`{showTitle && ...}`), and `titleText` was a plain block-comment attribute.
 * Once `titleText` is sourced from `.dsgo-table-of-contents__title`, hiding the
 * title by omitting the element would drop the source and reset the text on
 * reload. The current save() therefore ALWAYS renders the title element and
 * hides it with a `--hidden` modifier when the toggle is off.
 *
 * Content saved with the title hidden has no title element, so under the current
 * save() it is invalid (the always-rendered element is missing). This entry
 * reproduces the old `showTitle`-gated markup and parses `titleText` from the
 * block comment (where it lived), so those blocks stay valid and re-serialize
 * with the always-present, `--hidden` title — preserving the author's text.
 *
 * Markup-change deprecation: reached by save-matching on the now-invalid stored
 * HTML, so no isEligible is needed. Content that had the title SHOWN is byte
 * identical under the new save() and validates directly without this entry.
 */
const v1 = {
	apiVersion: metadata.apiVersion,
	supports: metadata.supports,
	attributes: {
		...metadata.attributes,
		// Historically a plain comment attribute (not sourced from the DOM).
		titleText: { type: 'string', default: 'Table of Contents' },
	},
	migrate(attributes) {
		return attributes;
	},
	save({ attributes }) {
		const {
			uniqueId,
			includeH2,
			includeH3,
			includeH4,
			includeH5,
			includeH6,
			displayMode,
			listStyle,
			showTitle,
			titleText,
			scrollSmooth,
			scrollOffset,
			stickyOffset,
			linkColor,
			activeLinkColor,
		} = attributes;

		const headingLevels = [];
		if (includeH2) {
			headingLevels.push('h2');
		}
		if (includeH3) {
			headingLevels.push('h3');
		}
		if (includeH4) {
			headingLevels.push('h4');
		}
		if (includeH5) {
			headingLevels.push('h5');
		}
		if (includeH6) {
			headingLevels.push('h6');
		}

		const customStyles = {};
		if (linkColor) {
			customStyles['--dsgo-toc-link-color'] =
				convertColorToCSSVar(linkColor);
		}
		if (activeLinkColor) {
			customStyles['--dsgo-toc-active-link-color'] =
				convertColorToCSSVar(activeLinkColor);
		}
		if (stickyOffset) {
			customStyles['--dsgo-toc-sticky-offset'] = `${stickyOffset}px`;
		}

		const ListTag = listStyle === 'ordered' ? 'ol' : 'ul';

		const blockProps = useBlockProps.save({
			className: classnames('dsgo-table-of-contents', {
				'dsgo-table-of-contents--hierarchical':
					displayMode === 'hierarchical',
				'dsgo-table-of-contents--flat': displayMode === 'flat',
				'dsgo-table-of-contents--ordered': listStyle === 'ordered',
				'dsgo-table-of-contents--smooth': scrollSmooth,
			}),
			style: customStyles,
			'data-unique-id': uniqueId,
			'data-heading-levels': headingLevels.join(','),
			'data-display-mode': displayMode,
			'data-scroll-smooth': scrollSmooth,
			'data-scroll-offset': scrollOffset,
		});

		return (
			<div {...blockProps}>
				<div className="dsgo-table-of-contents__content">
					{showTitle && (
						<div className="dsgo-table-of-contents__title">
							{titleText}
						</div>
					)}
					{/* Placeholder - frontend JS will populate this */}
					<ListTag className="dsgo-table-of-contents__list" />
				</div>
			</div>
		);
	},
};

export default [v1];
