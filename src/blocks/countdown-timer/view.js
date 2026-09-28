/**
 * Frontend JavaScript for Countdown Timer block
 */

import { getUnitLabel } from './utils/format-time';

/**
 * Internal dependencies
 */
import {
	calculateTimeRemaining,
	formatTimeUnit,
} from './utils/time-calculator';

/**
 * Update countdown display
 *
 * @param {Element} timer    - Timer element
 * @param {Object}  timeData - Time data object
 */
function updateCountdownDisplay(timer, timeData) {
	const units = timer.querySelectorAll('.dsgo-countdown-timer__unit');

	units.forEach((unit) => {
		const unitType = unit.dataset.unitType;
		const numberElement = unit.querySelector(
			'.dsgo-countdown-timer__number'
		);
		const labelElement = unit.querySelector('.dsgo-countdown-timer__label');

		if (numberElement && timeData[unitType] !== undefined) {
			numberElement.textContent = formatTimeUnit(timeData[unitType]);
		}

		if (labelElement && timeData[unitType] !== undefined) {
			labelElement.textContent = getUnitLabel(
				unitType,
				timeData[unitType]
			);
		}
	});
}

/**
 * Handle countdown completion
 *
 * @param {Element} timer - Timer element
 */
function handleCompletion(timer) {
	const completionAction = timer.dataset.completionAction;

	if (completionAction === 'hide') {
		// Hide the entire timer
		timer.style.display = 'none';
	} else if (completionAction === 'message') {
		// Hide units and show completion message
		const unitsContainer = timer.querySelector(
			'.dsgo-countdown-timer__units'
		);
		const messageContainer = timer.querySelector(
			'.dsgo-countdown-timer__completion-message'
		);

		if (unitsContainer) {
			unitsContainer.style.display = 'none';
		}

		if (messageContainer) {
			// The message text is already server-rendered inside this element
			// (sourced into the `completionMessage` attribute); just reveal it.
			messageContainer.style.display = 'block';
		}
	}
}

/**
 * Time left for a timer, read the same way everywhere it is shown.
 *
 * `timezone` is the block's own timezone attribute (empty means "use the
 * WordPress site timezone"). `siteTimezone` is stamped onto the markup at
 * render time (includes/features/class-countdown-timer-timezone.php),
 * because PHP is the only place `timezone_string` / `gmt_offset` live. It is
 * passed through as-is: when the attribute is missing (cached or raw HTML)
 * the resolver keeps the old browser-local reading instead of assuming UTC.
 *
 * @param {Element} timer - Timer element.
 * @return {Object} calculateTimeRemaining() result.
 */
function getTimeData(timer) {
	return calculateTimeRemaining(
		timer.dataset.targetDatetime,
		timer.dataset.timezone || '',
		timer.dataset.siteTimezone ?? null
	);
}

/**
 * Initialize a countdown timer
 *
 * @param {Element} timer - Timer element
 */
function initCountdownTimer(timer) {
	if (!timer.dataset.targetDatetime) {
		return;
	}

	// Check for reduced motion preference
	const prefersReducedMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;

	// Initial update
	let timeData = getTimeData(timer);
	updateCountdownDisplay(timer, timeData);

	if (timeData.isComplete) {
		handleCompletion(timer);
		return;
	}

	// Update every second
	const interval = setInterval(
		() => {
			timeData = getTimeData(timer);
			updateCountdownDisplay(timer, timeData);

			if (timeData.isComplete) {
				clearInterval(interval);
				handleCompletion(timer);
			}
		},
		prefersReducedMotion ? 5000 : 1000
	); // Slower updates for reduced motion

	// Clean up on page unload
	window.addEventListener('beforeunload', () => {
		clearInterval(interval);
	});
}

/**
 * Initialize all countdown timers on the page
 */
function initAllCountdownTimers() {
	const timers = document.querySelectorAll('.dsgo-countdown-timer');

	timers.forEach((timer) => {
		// Prevent duplicate initialization (critical for bfcache — avoids setInterval leaks)
		if (timer.hasAttribute('data-dsgo-initialized')) {
			return;
		}
		timer.setAttribute('data-dsgo-initialized', 'true');

		// Show the current values and translated labels straight away:
		// save() stores untranslated literals, and a timer below the fold
		// would otherwise show them until it scrolls into view. Only the
		// ticking waits for the observer.
		updateCountdownDisplay(
			timer,
			timer.dataset.targetDatetime
				? getTimeData(timer)
				: { days: 0, hours: 0, minutes: 0, seconds: 0 }
		);

		// Use Intersection Observer for lazy initialization
		// eslint-disable-next-line no-undef
		const observer = new IntersectionObserver(
			(entries) => {
				entries.forEach((entry) => {
					if (entry.isIntersecting) {
						initCountdownTimer(entry.target);
						observer.unobserve(entry.target);
					}
				});
			},
			{
				rootMargin: '50px', // Start 50px before entering viewport
			}
		);

		observer.observe(timer);
	});
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initAllCountdownTimers);
} else {
	initAllCountdownTimers();
}

// Re-initialize after soft navigation (bfcache, AJAX)
document.addEventListener('dsgo-content-loaded', initAllCountdownTimers);
