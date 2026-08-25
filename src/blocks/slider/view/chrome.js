/**
 * Slider — navigation chrome builders.
 *
 * Arrows, dots and the screen-reader announcer are all built here so the
 * slider itself deals only in indices. Everything is assembled with DOM APIs
 * rather than innerHTML: the strings are static today, but appendChild keeps
 * the pattern safe if a label ever interpolates author content.
 */

/**
 * Replace an editor-only chrome placeholder with a real one.
 *
 * edit.js renders inert arrows/dots so the author can see the layout; on the
 * frontend they would sit behind the live controls.
 *
 * @param {HTMLElement} slider   Slider root.
 * @param {string}      selector Placeholder selector to remove.
 */
function removeEditorPlaceholder(slider, selector) {
	slider.querySelector(selector)?.remove();
}

/**
 * Build the previous / next arrow buttons.
 *
 * @param {HTMLElement} slider          Slider root.
 * @param {Object}      handlers        Callbacks.
 * @param {Function}    handlers.onPrev Invoked when the previous arrow is clicked.
 * @param {Function}    handlers.onNext Invoked when the next arrow is clicked.
 * @return {{container: HTMLElement, prev: HTMLElement, next: HTMLElement}} The built arrows.
 */
export function buildArrows(slider, { onPrev, onNext }) {
	removeEditorPlaceholder(slider, '.dsgo-slider__arrows--editor-only');

	const container = document.createElement('div');
	container.className = 'dsgo-slider__arrows';

	const makeArrow = (direction, glyph, label, onClick) => {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = `dsgo-slider__arrow dsgo-slider__arrow--${direction}`;
		button.setAttribute('aria-label', label);
		const span = document.createElement('span');
		span.textContent = glyph;
		button.appendChild(span);
		button.addEventListener('click', onClick);
		container.appendChild(button);
		return button;
	};

	const prev = makeArrow('prev', '‹', 'Previous slide', onPrev);
	const next = makeArrow('next', '›', 'Next slide', onNext);

	slider.appendChild(container);
	return { container, prev, next };
}

/**
 * Build the dot navigation.
 *
 * Dots are a listbox of destinations, not tabs: there are no tabpanels for
 * them to control, so `role="tab"` inside `role="tablist"` describes a
 * relationship that does not exist in the markup. `aria-current` on a plain
 * button group says the same thing truthfully.
 *
 * @param {HTMLElement}      slider      Slider root.
 * @param {number}           count       How many dots to build.
 * @param {number}           activeIndex Zero-based dot to mark current.
 * @param {Function}         onSelect    Called with the dot index on click.
 * @param {HTMLElement|null} replaces    An existing dots container to swap out,
 *                                       so a rebuild keeps its place in the
 *                                       DOM — dot-position="outside" renders
 *                                       them in flow, where order shows.
 * @return {{container: HTMLElement, dots: HTMLElement[]}} The built dots.
 */
export function buildDots(
	slider,
	count,
	activeIndex,
	onSelect,
	replaces = null
) {
	removeEditorPlaceholder(slider, '.dsgo-slider__dots--editor-only');

	const container = document.createElement('div');
	container.className = 'dsgo-slider__dots';
	container.setAttribute('role', 'group');
	container.setAttribute('aria-label', 'Slide navigation');

	const dots = [];
	for (let i = 0; i < count; i++) {
		const dot = document.createElement('button');
		dot.type = 'button';
		dot.className = 'dsgo-slider__dot';
		dot.setAttribute('aria-label', `Go to slide ${i + 1}`);
		if (i === activeIndex) {
			dot.setAttribute('aria-current', 'true');
			dot.classList.add('dsgo-slider__dot--active');
		}
		dot.addEventListener('click', () => onSelect(i));
		container.appendChild(dot);
		dots.push(dot);
	}

	if (replaces?.parentNode) {
		replaces.replaceWith(container);
	} else {
		slider.appendChild(container);
	}
	return { container, dots };
}

/**
 * Mark which dot is current.
 *
 * @param {HTMLElement[]} dots        The dot buttons.
 * @param {number}        activeIndex Zero-based dot to mark current.
 */
export function updateDots(dots, activeIndex) {
	dots.forEach((dot, index) => {
		const isActive = index === activeIndex;
		dot.classList.toggle('dsgo-slider__dot--active', isActive);
		if (isActive) {
			dot.setAttribute('aria-current', 'true');
		} else {
			dot.removeAttribute('aria-current');
		}
	});
}

/**
 * Build the visually hidden live region used to announce slide changes.
 *
 * @param {HTMLElement} slider Slider root.
 * @return {HTMLElement} The announcer element.
 */
export function buildAnnouncer(slider) {
	const announcer = document.createElement('div');
	announcer.className = 'dsgo-slider__announcer';
	announcer.setAttribute('role', 'status');
	announcer.setAttribute('aria-live', 'polite');
	announcer.setAttribute('aria-atomic', 'true');
	Object.assign(announcer.style, {
		position: 'absolute',
		left: '-9999px',
		width: '1px',
		height: '1px',
		overflow: 'hidden',
	});

	slider.appendChild(announcer);
	return announcer;
}
