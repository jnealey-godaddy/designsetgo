/**
 * Hierarchy building utilities for Table of Contents block
 */

/**
 * Nest headings into a tree. Every heading appears exactly once, under the
 * nearest preceding heading with a shallower level. A heading with no
 * shallower predecessor (e.g. a leading H3 before any H2) sits at the top
 * level, so skipped levels (H2 -> H4) and out-of-order starts never drop an
 * entry.
 *
 * @param {Array} headings - Array of heading objects with a numeric `level`
 * @return {Array} Array of `{ heading, children }` nodes
 */
export function buildTree(headings) {
	const roots = [];
	const stack = [];

	headings.forEach((heading) => {
		const node = { heading, children: [] };

		while (
			stack.length > 0 &&
			stack[stack.length - 1].heading.level >= heading.level
		) {
			stack.pop();
		}

		if (stack.length === 0) {
			roots.push(node);
		} else {
			stack[stack.length - 1].children.push(node);
		}

		stack.push(node);
	});

	return roots;
}

/**
 * Build a hierarchical structure of list items from headings.
 *
 * @param {Array}       headings       - Array of heading objects
 * @param {number}      _currentLevel  - Unused; kept for call compatibility
 * @param {Function}    createListItem - Function to create a list item element
 * @param {HTMLElement} listElement    - The list element to determine ordered/unordered
 * @return {Array} Array of list item elements
 */
export function buildHierarchy(
	headings,
	_currentLevel,
	createListItem,
	listElement
) {
	const listTag = listElement.tagName.toLowerCase() === 'ol' ? 'ol' : 'ul';

	const render = (nodes) =>
		nodes.map(({ heading, children }) => {
			const li = createListItem(heading);

			if (children.length > 0) {
				const subList = document.createElement(listTag);
				subList.className = 'dsgo-table-of-contents__sublist';
				render(children).forEach((child) => subList.appendChild(child));
				li.appendChild(subList);
			}

			return li;
		});

	return render(buildTree(headings));
}
