/**
 * Countdown Timer Block - Frontend Unit Tests
 *
 * Tests for the countdown timer view.js frontend script.
 * Since view.js has no exports (side-effect module), each test uses
 * jest.isolateModules() to re-require a fresh instance.
 *
 * @package
 */

/**
 * Create a countdown timer DOM fixture.
 *
 * @param {Object} options                   Configuration options.
 * @param {string} options.targetDatetime    ISO 8601 datetime string.
 * @param {string} options.completionAction  Completion action ('hide' or 'message').
 * @param {string} options.completionMessage Completion message text.
 * @param {Array}  options.units             Unit types to include.
 * @return {HTMLElement} The timer element.
 */
function createTimerFixture({
	targetDatetime = '',
	completionAction = 'message',
	completionMessage = 'Time is up!',
	units = ['days', 'hours', 'minutes', 'seconds'],
} = {}) {
	const timer = document.createElement('div');
	timer.classList.add('dsgo-countdown-timer');

	if (targetDatetime) {
		timer.dataset.targetDatetime = targetDatetime;
	}
	timer.dataset.completionAction = completionAction;
	timer.dataset.completionMessage = completionMessage;

	const unitsContainer = document.createElement('div');
	unitsContainer.classList.add('dsgo-countdown-timer__units');

	units.forEach((unitType) => {
		const unit = document.createElement('div');
		unit.classList.add('dsgo-countdown-timer__unit');
		unit.dataset.unitType = unitType;

		const number = document.createElement('span');
		number.classList.add('dsgo-countdown-timer__number');
		number.textContent = '00';

		const label = document.createElement('span');
		label.classList.add('dsgo-countdown-timer__label');

		unit.appendChild(number);
		unit.appendChild(label);
		unitsContainer.appendChild(unit);
	});

	timer.appendChild(unitsContainer);

	const messageEl = document.createElement('div');
	messageEl.classList.add('dsgo-countdown-timer__completion-message');
	messageEl.style.display = 'none';
	// The message text is server-rendered into this element (sourced into the
	// completionMessage attribute); view.js just reveals it on completion.
	messageEl.textContent = completionMessage;
	timer.appendChild(messageEl);

	document.body.appendChild(timer);
	return timer;
}

/**
 * Get the displayed number text for a given unit type.
 *
 * @param {HTMLElement} timer    Timer element.
 * @param {string}      unitType Unit type (days, hours, minutes, seconds).
 * @return {string} The text content of the number element.
 */
function getUnitNumber(timer, unitType) {
	const unit = timer.querySelector(
		`.dsgo-countdown-timer__unit[data-unit-type="${unitType}"]`
	);
	return unit.querySelector('.dsgo-countdown-timer__number').textContent;
}

/**
 * Get the displayed label text for a given unit type.
 *
 * @param {HTMLElement} timer    Timer element.
 * @param {string}      unitType Unit type (days, hours, minutes, seconds).
 * @return {string} The text content of the label element.
 */
function getUnitLabel(timer, unitType) {
	const unit = timer.querySelector(
		`.dsgo-countdown-timer__unit[data-unit-type="${unitType}"]`
	);
	return unit.querySelector('.dsgo-countdown-timer__label').textContent;
}

/**
 * Load the view.js module in isolation and capture the IntersectionObserver
 * instance so we can simulate intersection entries.
 */
function loadView() {
	let observer;
	const OriginalObserver = global.IntersectionObserver;

	global.IntersectionObserver = class extends OriginalObserver {
		constructor(callback, options) {
			super(callback, options);
			observer = this;
		}
	};

	jest.isolateModules(() => {
		require('../../../src/blocks/countdown-timer/view.js');
	});

	global.IntersectionObserver = OriginalObserver;
	return observer;
}

/**
 * Simulate an element becoming visible via IntersectionObserver.
 *
 * @param {Object}      observer The captured IntersectionObserver instance.
 * @param {HTMLElement} element  The element to intersect.
 */
function simulateIntersection(observer, element) {
	observer.simulateIntersection([{ isIntersecting: true, target: element }]);
}

/**
 * Clean up DOM and restore defaults between tests.
 */
function cleanup() {
	while (document.body.firstChild) {
		document.body.removeChild(document.body.firstChild);
	}
	global.setMatchMedia(false);
	jest.restoreAllMocks();
}

/**
 * Build an ISO datetime string for a date in the future.
 *
 * @param {Object} offset         Time offset from now.
 * @param {number} offset.days    Days in the future.
 * @param {number} offset.hours   Hours in the future.
 * @param {number} offset.minutes Minutes in the future.
 * @param {number} offset.seconds Seconds in the future.
 * @return {string} ISO 8601 datetime string.
 */
function futureDate({ days = 0, hours = 0, minutes = 0, seconds = 0 } = {}) {
	const ms =
		days * 86400000 + hours * 3600000 + minutes * 60000 + seconds * 1000;
	return new Date(Date.now() + ms).toISOString();
}

describe('Countdown Timer - Frontend', () => {
	beforeEach(() => {
		jest.useFakeTimers();
	});

	afterEach(() => {
		jest.useRealTimers();
		cleanup();
	});

	describe('Time display', () => {
		test('displays correct time for a future date', () => {
			const target = futureDate({
				days: 5,
				hours: 3,
				minutes: 12,
				seconds: 45,
			});
			const timer = createTimerFixture({
				targetDatetime: target,
			});

			const observer = loadView();
			simulateIntersection(observer, timer);

			expect(getUnitNumber(timer, 'days')).toBe('05');
			expect(getUnitNumber(timer, 'hours')).toBe('03');
			expect(getUnitNumber(timer, 'minutes')).toBe('12');
			expect(getUnitNumber(timer, 'seconds')).toBe('45');
		});

		test('shows 0 for all units when timer is complete', () => {
			const pastDate = new Date(
				Date.now() - 1000 * 60 * 60
			).toISOString();
			const timer = createTimerFixture({
				targetDatetime: pastDate,
			});

			const observer = loadView();
			simulateIntersection(observer, timer);

			expect(getUnitNumber(timer, 'days')).toBe('00');
			expect(getUnitNumber(timer, 'hours')).toBe('00');
			expect(getUnitNumber(timer, 'minutes')).toBe('00');
			expect(getUnitNumber(timer, 'seconds')).toBe('00');
		});
	});

	describe('Completion actions', () => {
		test('shows completion message when action is message', () => {
			const pastDate = new Date(Date.now() - 1000 * 60).toISOString();
			const timer = createTimerFixture({
				targetDatetime: pastDate,
				completionAction: 'message',
				completionMessage: 'Event has ended!',
			});

			const observer = loadView();
			simulateIntersection(observer, timer);

			const messageEl = timer.querySelector(
				'.dsgo-countdown-timer__completion-message'
			);
			expect(messageEl.style.display).toBe('block');
			expect(messageEl.textContent).toBe('Event has ended!');

			const unitsContainer = timer.querySelector(
				'.dsgo-countdown-timer__units'
			);
			expect(unitsContainer.style.display).toBe('none');
		});

		test('hides timer when completion action is hide', () => {
			const pastDate = new Date(Date.now() - 1000 * 60).toISOString();
			const timer = createTimerFixture({
				targetDatetime: pastDate,
				completionAction: 'hide',
			});

			const observer = loadView();
			simulateIntersection(observer, timer);

			expect(timer.style.display).toBe('none');
		});
	});

	describe('Number formatting', () => {
		test('formats single digits with leading zero', () => {
			const target = futureDate({
				days: 0,
				hours: 0,
				minutes: 0,
				seconds: 5,
			});
			const timer = createTimerFixture({
				targetDatetime: target,
			});

			const observer = loadView();
			simulateIntersection(observer, timer);

			expect(getUnitNumber(timer, 'seconds')).toBe('05');
		});

		test('formats double digits without padding', () => {
			const target = futureDate({
				days: 0,
				hours: 0,
				minutes: 12,
				seconds: 30,
			});
			const timer = createTimerFixture({
				targetDatetime: target,
			});

			const observer = loadView();
			simulateIntersection(observer, timer);

			expect(getUnitNumber(timer, 'minutes')).toBe('12');
		});
	});

	describe('Unit labels', () => {
		test('shows singular label for value of 1', () => {
			const target = futureDate({
				days: 1,
				hours: 1,
				minutes: 0,
				seconds: 30,
			});
			const timer = createTimerFixture({
				targetDatetime: target,
			});

			const observer = loadView();
			simulateIntersection(observer, timer);

			expect(getUnitLabel(timer, 'days')).toBe('Day');
			expect(getUnitLabel(timer, 'hours')).toBe('Hour');
		});

		test('shows plural label for values other than 1', () => {
			const target = futureDate({
				days: 5,
				hours: 3,
				minutes: 0,
				seconds: 30,
			});
			const timer = createTimerFixture({
				targetDatetime: target,
			});

			const observer = loadView();
			simulateIntersection(observer, timer);

			expect(getUnitLabel(timer, 'days')).toBe('Days');
			expect(getUnitLabel(timer, 'hours')).toBe('Hours');
		});

		test('uses Min and Sec labels for minutes and seconds', () => {
			const target = futureDate({
				days: 0,
				hours: 0,
				minutes: 15,
				seconds: 45,
			});
			const timer = createTimerFixture({
				targetDatetime: target,
			});

			const observer = loadView();
			simulateIntersection(observer, timer);

			expect(getUnitLabel(timer, 'minutes')).toBe('Min');
			expect(getUnitLabel(timer, 'seconds')).toBe('Sec');
		});
	});

	describe('Missing target datetime', () => {
		test('handles missing target datetime gracefully', () => {
			const timer = createTimerFixture({
				targetDatetime: '',
			});

			const observer = loadView();
			simulateIntersection(observer, timer);

			// Numbers remain at initial "00" values; no crash
			expect(getUnitNumber(timer, 'days')).toBe('00');
			expect(getUnitNumber(timer, 'hours')).toBe('00');
			expect(getUnitNumber(timer, 'minutes')).toBe('00');
			expect(getUnitNumber(timer, 'seconds')).toBe('00');
		});
	});

	describe('IntersectionObserver lazy initialization', () => {
		test('uses IntersectionObserver for lazy init', () => {
			createTimerFixture({
				targetDatetime: futureDate({ days: 1 }),
			});

			const observer = loadView();

			expect(observer).toBeDefined();
			expect(observer.observe).toHaveBeenCalled();
		});

		test('does not initialize until element is in viewport', () => {
			const target = futureDate({ days: 5 });
			const timer = createTimerFixture({
				targetDatetime: target,
			});

			const observer = loadView();

			// Before intersection: numbers should remain at the initial "00"
			expect(getUnitNumber(timer, 'days')).toBe('00');

			// Simulate entering viewport
			simulateIntersection(observer, timer);

			// After intersection: numbers should be updated
			expect(getUnitNumber(timer, 'days')).toBe('05');
		});
	});
});
