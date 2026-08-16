/**
 * Interaction Layers - Selector derivation
 *
 * Turns a block the author clicked on the canvas into a CSS selector that
 * will still match on the frontend.
 *
 * The order matters. An HTML anchor is the only thing an author explicitly
 * chose as an identifier, so it wins. A custom class comes next. If a block
 * has neither there is nothing stable to target — a generated class is
 * written onto that block so the selector is guaranteed to resolve rather
 * than silently matching nothing.
 *
 * @package
 */

/** Classes WordPress puts on blocks itself; never usable as a target. */
const GENERATED_CLASS = /^(wp-block|is-layout|is-style|has-|dsgo-interaction)/;

/**
 * Pick the first author-authored class from a className attribute.
 *
 * @param {string} className Space-separated class list.
 * @return {string} A single class name, or an empty string.
 */
export function firstCustomClass(className) {
	return (
		(className || '')
			.split(/\s+/)
			.filter(Boolean)
			.find((cls) => !GENERATED_CLASS.test(cls)) || ''
	);
}

/**
 * Build a short, collision-resistant target class.
 *
 * @return {string} For example `dsgo-target-3f9a1c`.
 */
export function makeTargetClass() {
	return `dsgo-target-${Math.random().toString(16).slice(2, 8)}`;
}

/**
 * Derive a selector for a block, tagging the block if it has no identifier.
 *
 * @param {Object}   block    The block object from the block editor store.
 * @param {Function} tagBlock Called as ( clientId, attributes ) when the
 *                            block must be given a class to be targetable.
 * @return {string} A CSS selector such as `#hero` or `.my-panel`.
 */
export function deriveSelector(block, tagBlock) {
	if (!block) {
		return '';
	}

	const { anchor, className } = block.attributes || {};

	if (anchor) {
		return `#${anchor}`;
	}

	const existing = firstCustomClass(className);
	if (existing) {
		return `.${existing}`;
	}

	// Nothing stable to hook onto — give the block a class of its own.
	const generated = makeTargetClass();
	tagBlock(block.clientId, {
		className: [className, generated].filter(Boolean).join(' '),
	});

	return `.${generated}`;
}
