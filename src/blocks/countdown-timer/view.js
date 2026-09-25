/**
 * Frontend JavaScript for Countdown Timer block
 */

/**
 * Internal dependencies
 */
import {
	calculateTimeRemaining,
	formatTimeUnit,
} from './utils/time-calculator';

/**
 * Get unit label (singular/plural)
 *
 * @param {string} unitType - Type of unit
 * @param {number} value    - Current value
 * @return {string} Label text
 */
function getUnitLabel(unitType, value) {
	const labels = {
		days: value === 1 ? 'Day' : 'Days',
		hours: value === 1 ? 'Hour' : 'Hours',
		minutes: 'Min',
		seconds: 'Sec',
	};
	return labels[unitType] || '';
}

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
 * Initialize a countdown timer
 *
 * @param {Element} timer - Timer element
 */
function initCountdownTimer(timer) {
	const targetDateTime = timer.dataset.targetDatetime;

	if (!targetDateTime) {
		return;
	}

	// `timezone` is the block's own timezone attribute (empty means "use
	// the WordPress site timezone"). `siteTimezone` is stamped onto the
	// markup at render time — see
	// includes/features/class-countdown-timer-timezone.php — because PHP is
	// the only place `timezone_string` / `gmt_offset` live; the frontend has
	// no other way to know the site's configured timezone.
	const timezone = timer.dataset.timezone || '';
	const siteTimezone = timer.dataset.siteTimezone || '';

	// Check for reduced motion preference
	const prefersReducedMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;

	// Initial update
	let timeData = calculateTimeRemaining(
		targetDateTime,
		timezone,
		siteTimezone
	);
	updateCountdownDisplay(timer, timeData);

	if (timeData.isComplete) {
		handleCompletion(timer);
		return;
	}

	// Update every second
	const interval = setInterval(
		() => {
			timeData = calculateTimeRemaining(
				targetDateTime,
				timezone,
				siteTimezone
			);
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
