/**
 * Form Builder — multi-step forms in the browser.
 *
 * Without JavaScript every step renders stacked as one long form; this module
 * shows one step at a time, builds the Back/Next and progress UI, validates
 * the current step before advancing, and keeps focus and screen readers in
 * step. The server validates everything on submit regardless.
 *
 * @since 2.10.0
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	stepTitle,
	isActive,
	firstInvalid,
	forgetGrownSteps,
} from './steps-state';
import { revealBelowHeader } from './utils/reveal-below-header';

const PROGRESS_STYLES = ['steps', 'bar', 'none'];
const NOOP = {
	isMultiStep: false,
	beforeSubmit: () => true,
	reset() {},
	finish() {},
};

function makeButton(label, className) {
	const button = document.createElement('button');
	button.type = 'button';
	button.className = className;
	button.textContent = label;
	return button;
}

// Per-instance modifiers of the submit button that must not follow Next.
const SUBMIT_ONLY_CLASSES = [
	'dsgo-form__submit--inline',
	'dsgo-form__submit--loading',
];

/**
 * Next takes the submit button's classes and inline style, so it follows the
 * form's button colours, sizes, variation and hover animation, plus any Global
 * Styles / Style Kit rule on `.dsgo-form__submit`. It stays `type="button"`, so
 * `.dsgo-form__submit[type="submit"]` still finds the real submit.
 *
 * @param {HTMLElement|null} submit The form's submit button.
 * @return {HTMLElement} Next button.
 */
function makeNextButton(submit) {
	const label = __('Next', 'designsetgo');
	if (!submit) {
		return makeButton(label, 'dsgo-form-steps__next wp-element-button');
	}
	const next = makeButton(label, submit.className);
	SUBMIT_ONLY_CLASSES.forEach((cls) => next.classList.remove(cls));
	next.classList.add('dsgo-form-steps__next');
	const style = submit.getAttribute('style');
	if (style) {
		next.setAttribute('style', style);
	}
	return next;
}

/**
 * Wire multi-step behaviour for one form.
 *
 * @param {HTMLElement}      container .dsgo-form-builder wrapper.
 * @param {HTMLElement|null} form      The form element.
 * @return {{isMultiStep: boolean, beforeSubmit: Function, reset: Function, finish: Function}} Controller.
 */
export function initFormSteps(container, form) {
	let steps = [];
	let controller = NOOP;
	try {
		if (!form) {
			return NOOP;
		}
		steps = Array.from(form.querySelectorAll('[data-dsgo-step]'));
		if (!steps.length) {
			return NOOP;
		}
		controller = setUp(container, form, steps);
	} catch (error) {
		// Fail open: one long form that still submits.
		steps.forEach((step) => {
			step.hidden = false;
		});
		// The footer may already sit inside the nav: put it back first, or
		// removing the nav would take the submit button with it.
		form?.querySelectorAll('.dsgo-form-steps__nav').forEach((nav) => {
			nav.querySelectorAll('.dsgo-form__footer').forEach((el) =>
				nav.before(el)
			);
		});
		form?.querySelectorAll(
			'.dsgo-form-steps__nav, .dsgo-form-steps__progress, .dsgo-form-steps__bar'
		).forEach((el) => el.remove());
		form?.querySelectorAll(
			'.dsgo-form__footer, [data-dsgo-turnstile-container], .dsgo-form__submit--inline'
		).forEach((el) => {
			el.hidden = false;
		});
		controller = NOOP;
	} finally {
		container.dataset.dsgoStepsReady = '1';
	}
	return controller;
}

function setUp(container, form, steps) {
	const style = PROGRESS_STYLES.includes(container.dataset.dsgoStepProgress)
		? container.dataset.dsgoStepProgress
		: 'steps';
	const footer = form.querySelector('.dsgo-form__footer');
	const inlineSubmit = form.querySelector('.dsgo-form__submit--inline');
	const turnstile = form.querySelector('[data-dsgo-turnstile-container]');
	const message = form.querySelector('.dsgo-form__message');

	const status = document.createElement('div');
	status.className = 'screen-reader-text';
	status.setAttribute('aria-live', 'polite');
	status.dataset.dsgoStepsStatus = '';
	container.appendChild(status);

	const nav = document.createElement('div');
	nav.className = 'dsgo-form-steps__nav';
	const back = makeButton(__('Back', 'designsetgo'), 'dsgo-form-steps__back');
	const next = makeNextButton(
		form.querySelector('.dsgo-form__submit[type="submit"]')
	);
	nav.append(back, next);
	const anchor = footer || message;
	if (anchor && anchor.parentNode === form) {
		form.insertBefore(nav, anchor);
	} else {
		form.appendChild(nav);
	}
	// The footer joins the nav row so Submit takes Next's place on the last
	// step instead of dropping onto a row of its own below Back.
	if (footer && footer.parentNode === form) {
		nav.appendChild(footer);
	}

	let progress = null;
	if (style === 'steps') {
		progress = document.createElement('ol');
		progress.className = 'dsgo-form-steps__progress';
	} else if (style === 'bar') {
		progress = document.createElement('div');
		progress.className = 'dsgo-form-steps__bar';
		progress.innerHTML =
			'<span class="dsgo-form-steps__bar-label"></span><div role="progressbar" aria-valuemin="1"><span class="dsgo-form-steps__bar-fill"></span></div>';
		progress
			.querySelector('[role=progressbar]')
			.setAttribute('aria-label', __('Form progress', 'designsetgo'));
	}
	if (progress) {
		form.insertBefore(progress, form.firstChild);
	}

	let current = steps[0];
	let finished = false;
	const completed = new Set();
	const fieldCounts = new Map();

	const activeSteps = () => {
		const list = steps.filter(isActive);
		return list.length ? list : steps;
	};

	const renderProgress = (list, pos) => {
		if (!progress) {
			return;
		}
		progress.hidden = list.length < 2;
		if (style === 'steps') {
			progress.textContent = '';
			list.forEach((step, i) => {
				const item = document.createElement('li');
				item.className = 'dsgo-form-steps__item';
				item.textContent = stepTitle(step);
				if (i === pos) {
					item.setAttribute('aria-current', 'step');
				} else if (completed.has(step)) {
					item.classList.add('is-complete');
					const note = document.createElement('span');
					note.className = 'screen-reader-text';
					note.textContent = ' ' + __('(completed)', 'designsetgo');
					item.appendChild(note);
				}
				progress.appendChild(item);
			});
			return;
		}
		const text = sprintf(
			/* translators: 1: current step number, 2: total steps */
			__('Step %1$d of %2$d', 'designsetgo'),
			pos + 1,
			list.length
		);
		progress.querySelector('.dsgo-form-steps__bar-label').textContent =
			text;
		const bar = progress.querySelector('[role=progressbar]');
		bar.setAttribute('aria-valuemax', String(list.length));
		bar.setAttribute('aria-valuenow', String(pos + 1));
		bar.setAttribute('aria-valuetext', text);
		progress.querySelector('.dsgo-form-steps__bar-fill').style.width =
			Math.round(((pos + 1) / list.length) * 100) + '%';
	};

	const render = ({ focus = false, announce = false } = {}) => {
		if (finished) {
			// Sent: leave only the success message, not "Step 1 of 3" and a
			// Next button that read as "start again".
			[...steps, nav, progress, inlineSubmit, turnstile].forEach((el) => {
				if (el) {
					el.hidden = true;
				}
			});
			return;
		}
		forgetGrownSteps(steps, fieldCounts, completed);
		const list = activeSteps();
		if (!list.includes(current)) {
			// The current step was skipped: move to the nearest active one after it, else before it.
			const after = steps
				.slice(steps.indexOf(current))
				.find((s) => list.includes(s));
			current = after || list[list.length - 1];
		}
		const pos = list.indexOf(current);
		const isLast = pos === list.length - 1;
		steps.forEach((step) => {
			step.hidden = step !== current;
		});
		back.hidden = pos === 0;
		next.hidden = isLast;
		// With one step, Back and Next are both hidden; the nav stays only
		// when it holds the footer.
		nav.hidden = list.length < 2 && !nav.contains(footer);
		[footer, inlineSubmit, turnstile].forEach((el) => {
			if (el) {
				el.hidden = !isLast;
			}
		});
		renderProgress(list, pos);

		if (focus) {
			const heading = current.querySelector('.dsgo-form-step__title');
			if (heading) {
				// Focus without the browser's own scroll, which can leave the
				// progress indicator under a sticky header; reveal it with
				// the heading instead.
				heading.focus({ preventScroll: true });
				revealBelowHeader(
					progress && !progress.hidden ? progress : heading,
					heading
				);
			}
		}
		if (announce) {
			status.textContent = sprintf(
				/* translators: 1: current step number, 2: total steps, 3: step title */
				__('Step %1$d of %2$d: %3$s', 'designsetgo'),
				pos + 1,
				list.length,
				stepTitle(current)
			);
		}
	};

	const goTo = (step) => {
		current = step;
		render({ focus: true, announce: true });
	};

	const advance = () => {
		const invalid = firstInvalid(current);
		if (invalid) {
			invalid.reportValidity();
			return false;
		}
		const list = activeSteps();
		const pos = list.indexOf(current);
		if (pos < list.length - 1) {
			completed.add(current);
			goTo(list[pos + 1]);
		}
		return true;
	};

	// Listeners come after the first render succeeds, so a failed init (which
	// fails open) can't be undone by the next input event.
	render();

	next.addEventListener('click', advance);
	back.addEventListener('click', () => {
		const list = activeSteps();
		const pos = list.indexOf(current);
		if (pos > 0) {
			goTo(list[pos - 1]);
		}
	});
	form.addEventListener('input', () => render());
	form.addEventListener('change', () => render());

	const reset = () => {
		completed.clear();
		current = activeSteps()[0];
		render();
	};
	form.addEventListener('reset', () => setTimeout(reset, 0));

	return {
		isMultiStep: true,
		beforeSubmit() {
			const list = activeSteps();
			if (list.indexOf(current) < list.length - 1) {
				advance();
				return false;
			}
			for (const step of list) {
				const invalid = firstInvalid(step);
				if (invalid) {
					if (step !== current) {
						goTo(step);
					}
					invalid.reportValidity();
					return false;
				}
			}
			// A step that appeared (or grew) behind the visitor hasn't been seen.
			const unseen = list.slice(0, -1).find((s) => !completed.has(s));
			if (unseen) {
				goTo(unseen);
				return false;
			}
			return true;
		},
		reset,
		finish() {
			finished = true;
			render();
		},
	};
}
