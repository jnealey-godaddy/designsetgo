/**
 * Progress Bar Block - Frontend JavaScript
 *
 * Handles scroll-triggered animations for progress bars.
 *
 * @since 1.0.0
 */

import {
	LABEL_CLIPPED_CLASS,
	MEASURED_CLASS,
	insideLabelFits,
} from './utils/label-fit';

/**
 * Resolve the target fill width (0–100) for a progress bar.
 *
 * A `dsgoStyleBinding` on `--dsgo-progress` (and optionally
 * `--dsgo-progress-max`) is applied server-side as an inline custom property
 * on the block's root element, so by the time this runs it is available via
 * `getComputedStyle` — custom properties inherit, so reading it off the fill
 * element itself picks up the ancestor's bound value. When neither var is
 * set anywhere in the cascade, fall back to the block's static `percentage`
 * attribute (carried in `data-percentage`), unscaled by any
 * `--dsgo-progress-max`, matching the CSS formula save.js writes for the
 * non-animated case.
 *
 * `--dsgo-progress-max` is floored at 1 (never 0 or negative) — it can itself
 * be bound and resolve to 0, and dividing by it would otherwise produce
 * `Infinity`/`NaN`. This mirrors the `max(1, var(--dsgo-progress-max, 100))`
 * guard in save.js's STATIC_WIDTH_FORMULA and the PHP save mirror
 * (Progress_Bar_Serializer), so the animated and static paths always agree.
 *
 * @param {Element} fill               The `.dsgo-progress-bar__fill` element.
 * @param {number}  fallbackPercentage The static `percentage` attribute value.
 * @return {number} Target width, clamped 0–100.
 */
export function resolveTargetPercent(fill, fallbackPercentage) {
	// eslint-disable-next-line no-undef
	const computed = getComputedStyle(fill);
	const rawValue = computed.getPropertyValue('--dsgo-progress').trim();

	// No bound value: the static percentage is already a percentage, so it
	// is not scaled by `--dsgo-progress-max` (see save.js's fallback).
	if (rawValue === '' || Number.isNaN(parseFloat(rawValue))) {
		return Math.min(Math.max(fallbackPercentage, 0), 100);
	}
	const value = parseFloat(rawValue);
	const rawMax = computed.getPropertyValue('--dsgo-progress-max').trim();
	const parsedMax =
		rawMax !== '' && !Number.isNaN(parseFloat(rawMax))
			? parseFloat(rawMax)
			: 100;
	const max = Math.max(1, parsedMax);

	return Math.min(Math.max((value / max) * 100, 0), 100);
}

/**
 * Initialize progress bars with scroll animations
 */
function initProgressBars() {
	const progressBars = document.querySelectorAll(
		'.dsgo-progress-bar--animate'
	);

	if (!progressBars.length) {
		return;
	}

	// Use Intersection Observer for better performance
	const observerOptions = {
		root: null,
		rootMargin: '0px',
		threshold: 0.1, // Trigger when 10% of element is visible
	};

	// eslint-disable-next-line no-undef
	const observer = new IntersectionObserver((entries) => {
		entries.forEach((entry) => {
			if (entry.isIntersecting) {
				const progressBar = entry.target;
				const fill = progressBar.querySelector(
					'.dsgo-progress-bar__fill'
				);

				if (!fill) {
					return;
				}

				// Static fallback percentage from the block's own attribute.
				const fallbackPercentage = parseFloat(
					progressBar.getAttribute('data-percentage')
				);

				if (!Number.isNaN(fallbackPercentage)) {
					// Animate to the bound value (if any) or the static
					// percentage, resolved fresh at animation time.
					setTimeout(() => {
						const targetPercent = resolveTargetPercent(
							fill,
							fallbackPercentage
						);
						fill.style.width = `${targetPercent}%`;
					}, 100); // Small delay for better visual effect
				}

				// Unobserve after animation starts (only animate once)
				observer.unobserve(progressBar);
			}
		});
	}, observerOptions);

	// Observe all progress bars (skip already-initialized ones)
	progressBars.forEach((bar) => {
		if (!bar.hasAttribute('data-dsgo-initialized')) {
			bar.setAttribute('data-dsgo-initialized', 'true');
			observer.observe(bar);
		}
	});
}

/**
 * Hide inside labels that do not fit their fill (see utils/label-fit.js).
 *
 * Runs for every bar, animated or not. A ResizeObserver on each fill keeps it
 * current as an animated fill grows, the layout changes width, or a web font
 * finishes loading and changes the label's width.
 */
function fitInsideLabels() {
	document.querySelectorAll('.dsgo-progress-bar').forEach((bar) => {
		const fill = bar.querySelector('.dsgo-progress-bar__fill');
		const label = fill?.querySelector('.dsgo-progress-bar__label--inside');
		if (!label) {
			return;
		}

		const update = () =>
			label.classList.toggle(
				LABEL_CLIPPED_CLASS,
				!insideLabelFits(fill, label)
			);
		update();
		bar.classList.add(MEASURED_CLASS);

		if (
			typeof ResizeObserver !== 'undefined' &&
			!bar.hasAttribute('data-dsgo-label-observed')
		) {
			bar.setAttribute('data-dsgo-label-observed', 'true');
			// eslint-disable-next-line no-undef
			new ResizeObserver(update).observe(fill);
		}
	});
}

function init() {
	fitInsideLabels();
	initProgressBars();
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}

// A web font can change the label's width without resizing the fill.
document.fonts?.ready?.then(fitInsideLabels);

// Re-initialize after dynamic content loads (e.g., AJAX, soft navigation)
document.addEventListener('wp-blocks-post-content-loaded', init);
document.addEventListener('dsgo-content-loaded', init);
