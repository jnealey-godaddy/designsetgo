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
 * Expose the timer to assistive tech. `timer` carries an implicit
 * aria-live="off", so the per-second ticks are never announced; the
 * completion message is a polite status region, set up while still hidden
 * so its later reveal can be announced.
 *
 * @param {Element} timer - Timer element
 */
function setupTimerSemantics(timer) {
	const unitsContainer = timer.querySelector('.dsgo-countdown-timer__units');
	if (unitsContainer) {
		unitsContainer.setAttribute('role', 'timer');
	}

	const messageContainer = timer.querySelector(
		'.dsgo-countdown-timer__completion-message'
	);
	if (messageContainer && timer.dataset.completionAction === 'message') {
		messageContainer.setAttribute('role', 'status');
	}
}

/**
 * Handle countdown completion
 *
 * @param {Element} timer            - Timer element
 * @param {boolean} [announce=false] - Whether the visitor watched it finish,
 *                                   so the message should be announced.
 */
function handleCompletion(timer, announce = false) {
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

			// A live region announces changes, not content that merely
			// becomes visible, so re-insert the text once it is shown.
			if (announce) {
				const message = messageContainer.textContent;
				messageContainer.textContent = '';
				window.requestAnimationFrame(() => {
					messageContainer.textContent = message;
				});
			}
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

	setupTimerSemantics(timer);

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

	let interval = null;

	const stop = () => {
		clearInterval(interval);
		interval = null;
	};

	// A soft navigation can remove the timer; stop ticking and listening
	// rather than keep updating a detached element.
	const detach = () => {
		stop();
		document.removeEventListener('visibilitychange', onVisibility);
	};

	const tick = () => {
		if (!timer.isConnected) {
			detach();
			return;
		}
		timeData = getTimeData(timer);
		updateCountdownDisplay(timer, timeData);

		if (timeData.isComplete) {
			detach();
			handleCompletion(timer, true);
		}
	};

	// Update every second (slower for reduced motion).
	const start = () => {
		if (interval) {
			return;
		}
		interval = setInterval(tick, prefersReducedMotion ? 5000 : 1000);
	};

	// A hidden tab can't see the timer, so stop ticking. The remaining time is
	// recomputed from the clock on return, so nothing drifts while paused.
	function onVisibility() {
		if (document.hidden) {
			stop();
			return;
		}
		tick();
		if (timer.isConnected && !timeData.isComplete) {
			start();
		}
	}

	document.addEventListener('visibilitychange', onVisibility);

	if (!document.hidden) {
		start();
	}
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
