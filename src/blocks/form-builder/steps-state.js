/**
 * Form Builder — what each step of a multi-step form currently holds.
 *
 * Queries over a step's fields, and the bookkeeping that keeps a step's
 * "completed" mark honest when conditional fields change behind the visitor.
 *
 * @since 2.10.0
 */

const CONTROLS = 'input, select, textarea';

export function stepTitle(step) {
	const heading = step.querySelector('.dsgo-form-step__title');
	return heading ? heading.textContent.trim() : '';
}

// A Hidden field's wrapper is hidden by a class, never the attribute, and the
// visitor never sees it, so it doesn't make a step worth showing.
function isHiddenType(wrapper) {
	const controls = Array.from(wrapper.querySelectorAll(CONTROLS));
	return controls.length > 0 && controls.every((el) => el.type === 'hidden');
}

function visibleFieldCount(step) {
	return Array.from(step.querySelectorAll('[data-dsgo-field]')).filter(
		(wrapper) => !wrapper.hidden && !isHiddenType(wrapper)
	).length;
}

/**
 * A step is active when at least one of its fields is visible.
 *
 * @param {HTMLElement} step Step element.
 * @return {boolean} Whether the step should be shown.
 */
export function isActive(step) {
	return visibleFieldCount(step) > 0;
}

function checkableControls(step) {
	return Array.from(step.querySelectorAll(CONTROLS)).filter(
		(el) =>
			!el.disabled &&
			el.type !== 'hidden' &&
			!el.closest('[data-dsgo-field][hidden]')
	);
}

export function firstInvalid(step) {
	return checkableControls(step).find((el) => !el.checkValidity()) || null;
}

/**
 * Track each step's visible fields between renders and un-complete any step
 * that gained some: a step that just became active (from zero fields), or a
 * completed step that now asks for more. The visitor hasn't seen those yet.
 *
 * @param {HTMLElement[]}           steps     All steps.
 * @param {Map<HTMLElement,number>} counts    Visible-field counts from the last render (updated).
 * @param {Set<HTMLElement>}        completed Completed steps (updated).
 */
export function forgetGrownSteps(steps, counts, completed) {
	steps.forEach((step) => {
		const count = visibleFieldCount(step);
		if (counts.has(step) && count > counts.get(step)) {
			completed.delete(step);
		}
		counts.set(step, count);
	});
}
