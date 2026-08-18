/**
 * Block Animations - SVG path drawing
 *
 * Stroke length is only knowable at runtime, so this is the one animation
 * feature that genuinely needs JavaScript. Measurement happens once; the
 * animation itself is CSS.
 *
 * @package
 * @since 1.0.0
 */

/* global IntersectionObserver */

const SELECTOR = '[data-dsgo-svg-draw="true"]';
const SHAPES = 'path, line, polyline, circle, ellipse, rect';
const READY_CLASS = 'dsgo-svg-draw-ready';
const ACTIVE_CLASS = 'dsgo-svg-draw-active';

let observer = null;

/**
 * Whether the visitor has asked for reduced motion.
 *
 * @return {boolean} True when motion should be suppressed.
 */
function prefersReducedMotion() {
	return !!window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
}

/**
 * Measure a container's shapes and prime them for drawing.
 *
 * @param {Element} container Element carrying the data attribute.
 */
function prepare(container) {
	if (container.classList.contains(READY_CLASS)) {
		return;
	}

	container.querySelectorAll(SHAPES).forEach((shape) => {
		// jsdom, and any element without real geometry, has no measurer.
		if (typeof shape.getTotalLength !== 'function') {
			return;
		}

		let length;
		try {
			length = shape.getTotalLength();
		} catch (e) {
			return;
		}

		if (!length || !Number.isFinite(length)) {
			return;
		}

		const rounded = Math.ceil(length);
		shape.style.strokeDasharray = String(rounded);
		shape.style.strokeDashoffset = String(rounded);
	});

	container.classList.add(READY_CLASS);
}

/**
 * Initialise SVG drawing. Idempotent, safe after a DOM swap.
 *
 * @param {Document|Element} root Subtree to scan.
 */
export function initSvgDraw(root = document) {
	if (prefersReducedMotion()) {
		return;
	}

	const containers = root.querySelectorAll(SELECTOR);

	if (!containers.length) {
		return;
	}

	containers.forEach(prepare);

	if (!('IntersectionObserver' in window)) {
		// No observer: draw immediately rather than leaving strokes hidden.
		containers.forEach((el) => el.classList.add(ACTIVE_CLASS));
		return;
	}

	if (!observer) {
		observer = new IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					if (entry.isIntersecting) {
						entry.target.classList.add(ACTIVE_CLASS);
						observer.unobserve(entry.target);
					}
				});
			},
			{ rootMargin: '0px 0px -10% 0px' }
		);
	}

	containers.forEach((el) => {
		if (!el.classList.contains(ACTIVE_CLASS)) {
			observer.observe(el);
		}
	});
}

if (typeof document !== 'undefined') {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', () => initSvgDraw());
	} else {
		initSvgDraw();
	}
	document.addEventListener('dsgo-content-loaded', () => initSvgDraw());
}
