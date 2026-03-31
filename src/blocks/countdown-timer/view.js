/**
 * Frontend JavaScript for Countdown Timer block
 */

/**
 * Calculate time remaining until target date
 *
 * @param {string} targetDateTime - ISO 8601 datetime string
 * @return {Object} Object with days, hours, minutes, seconds, isComplete
 */
function calculateTimeRemaining(targetDateTime) {
	if (!targetDateTime) {
		return {
			days: 0,
			hours: 0,
			minutes: 0,
			seconds: 0,
			isComplete: true,
		};
	}

	const targetDate = new Date(targetDateTime);
	const now = new Date();
	const difference = targetDate.getTime() - now.getTime();

	if (difference <= 0) {
		return {
			days: 0,
			hours: 0,
			minutes: 0,
			seconds: 0,
			isComplete: true,
		};
	}

	const days = Math.floor(difference / (1000 * 60 * 60 * 24));
	const hours = Math.floor(
		(difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)
	);
	const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
	const seconds = Math.floor((difference % (1000 * 60)) / 1000);

	return {
		days,
		hours,
		minutes,
		seconds,
		isComplete: false,
	};
}

/**
 * Format time unit with leading zero
 *
 * @param {number} value - Time unit value
 * @return {string} Formatted value
 */
function formatTimeUnit(value) {
	return value < 10 ? `0${value}` : `${value}`;
}

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
	const completionMessage = timer.dataset.completionMessage;

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
			messageContainer.style.display = 'block';
			messageContainer.textContent = completionMessage;
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

	// Check for reduced motion preference
	const prefersReducedMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;

	// Initial update
	let timeData = calculateTimeRemaining(targetDateTime);
	updateCountdownDisplay(timer, timeData);

	if (timeData.isComplete) {
		handleCompletion(timer);
		return;
	}

	// Update every second
	const interval = setInterval(
		() => {
			timeData = calculateTimeRemaining(targetDateTime);
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
