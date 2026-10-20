import { buildTree } from '../utils/hierarchy';

/**
 * Hierarchical List Component
 *
 * Renders a hierarchical (nested) list structure from headings.
 */

/**
 * Recursively render hierarchical structure
 *
 * @param {Array}  headings  - Array of heading objects
 * @param {number} _minLevel - Unused; kept for call compatibility
 * @param {string} ListTag   - The list tag to use ('ul' or 'ol')
 * @return {Array} Array of React elements
 */
export function renderHierarchical(headings, _minLevel, ListTag) {
	const render = (nodes) =>
		nodes.map(({ heading, children }, index) => (
			<li
				key={`${heading.id}-${index}`}
				className={`dsgo-table-of-contents__item dsgo-table-of-contents__item--level-${heading.level}`}
			>
				<a
					href={`#${heading.id}`}
					className="dsgo-table-of-contents__link"
				>
					{heading.text}
				</a>
				{children.length > 0 && (
					<ListTag className="dsgo-table-of-contents__sublist">
						{render(children)}
					</ListTag>
				)}
			</li>
		));

	return render(buildTree(headings));
}
