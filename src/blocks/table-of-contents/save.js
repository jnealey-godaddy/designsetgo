import { useBlockProps } from '@wordpress/block-editor';
import classnames from 'classnames';
import { convertColorToCSSVar } from '../../utils/convert-preset-to-css-var';

export default function Save({ attributes }) {
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

	// Build heading levels string for data attribute
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

	// Styles using CSS custom properties (only set if user has chosen colors)
	const customStyles = {};
	if (linkColor) {
		customStyles['--dsgo-toc-link-color'] = convertColorToCSSVar(linkColor);
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
				{/*
				 * The title element is ALWAYS rendered (matching the previous
				 * `showTitle`-gated markup for shown titles) so `titleText` has a
				 * stable element to be sourced from — otherwise toggling the title
				 * off would drop the element and reset the text on reload. When the
				 * title is hidden it carries a `--hidden` modifier (CSS display:none)
				 * instead of being omitted from the tree.
				 */}
				<div
					className={classnames('dsgo-table-of-contents__title', {
						'dsgo-table-of-contents__title--hidden': !showTitle,
					})}
				>
					{titleText}
				</div>
				{/* Placeholder - frontend JS will populate this */}
				<ListTag className="dsgo-table-of-contents__list" />
			</div>
		</div>
	);
}
