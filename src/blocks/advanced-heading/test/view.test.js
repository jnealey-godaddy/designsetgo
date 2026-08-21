/**
 * Advanced Heading frontend animation.
 *
 * The saved first word is deliberately readable without JavaScript. These
 * tests exercise the small progressive-enhancement controller separately from
 * block serialization.
 */

import { initAnimatedHeadlines } from '../view';

function renderHeadline({
	words = ['First', 'Second', 'Final'],
	effect = 'typing',
	duration = '250',
	delay = '0',
	direction = 'forward',
	loop = 'true',
} = {}) {
	document.body.innerHTML = `
		<div class="dsgo-advanced-heading">
			<h2
				class="dsgo-advanced-heading__inner dsgo-advanced-heading__inner--rotating"
				data-dsgo-animated-headline="true"
				data-dsgo-animated-headline-mode="rotating"
				data-dsgo-animated-headline-effect="${effect}"
				data-dsgo-animated-headline-duration="${duration}"
				data-dsgo-animated-headline-delay="${delay}"
				data-dsgo-animated-headline-direction="${direction}"
				data-dsgo-animated-headline-loop="${loop}"
			>
				<span class="dsgo-heading-segment"><span class="dsgo-heading-segment__animated" data-dsgo-animated-words='${JSON.stringify(words)}'>${words[0]}</span></span>
			</h2>
		</div>`;

	return document.querySelector('.dsgo-advanced-heading__inner');
}

describe('advanced heading frontend animation', () => {
	let reduceMotion = false;
	let intervalSpy;
	let clearIntervalSpy;
	let hidden = false;

	beforeEach(() => {
		jest.useFakeTimers();
		reduceMotion = false;
		hidden = false;
		intervalSpy = jest.spyOn(window, 'setInterval');
		clearIntervalSpy = jest.spyOn(window, 'clearInterval');
		window.matchMedia = jest.fn().mockImplementation(() => ({
			matches: reduceMotion,
			addEventListener: jest.fn(),
			removeEventListener: jest.fn(),
		}));
		Object.defineProperty(document, 'hidden', {
			configurable: true,
			get: () => hidden,
		});
	});

	afterEach(() => {
		intervalSpy.mockRestore();
		clearIntervalSpy.mockRestore();
		jest.useRealTimers();
		document.body.replaceChildren();
	});

	it('keeps exactly one active, readable word for every rotating effect', () => {
		const heading = renderHeadline({ effect: 'flip' });
		heading.removeAttribute('data-dsgo-animated-headline-direction');

		initAnimatedHeadlines();

		const active = heading.querySelectorAll(
			'.dsgo-heading-segment__animated.is-active'
		);
		expect(active).toHaveLength(1);
		expect(active[0]).toHaveTextContent('First');
		expect(active[0]).not.toHaveAttribute('aria-hidden');
		expect(active[0]).toHaveAttribute('aria-live', 'polite');
	});

	it('stops on the final word when looping is disabled', () => {
		const heading = renderHeadline({ loop: 'false' });

		initAnimatedHeadlines();
		jest.advanceTimersByTime(1000);

		expect(
			heading.querySelector('.dsgo-heading-segment__animated')
		).toHaveTextContent('Final');
		expect(intervalSpy).toHaveBeenCalledTimes(1);
	});

	it('rotates the authored words in reverse when selected', () => {
		const heading = renderHeadline({ direction: 'reverse' });

		initAnimatedHeadlines();
		expect(
			heading.querySelector('.dsgo-heading-segment__animated')
		).toHaveTextContent('Final');
		jest.advanceTimersByTime(250);

		expect(
			heading.querySelector('.dsgo-heading-segment__animated')
		).toHaveTextContent('Second');
	});

	it('keeps the static first word and creates no timer under reduced motion', () => {
		reduceMotion = true;
		const heading = renderHeadline();

		initAnimatedHeadlines();
		jest.advanceTimersByTime(1000);

		expect(
			heading.querySelector('.dsgo-heading-segment__animated')
		).toHaveTextContent('First');
		expect(intervalSpy).not.toHaveBeenCalled();
	});

	it('keeps the saved first word under reduced motion when reverse is selected', () => {
		reduceMotion = true;
		const heading = renderHeadline({ direction: 'reverse' });

		initAnimatedHeadlines();
		jest.advanceTimersByTime(1000);

		expect(
			heading.querySelector('.dsgo-heading-segment__animated')
		).toHaveTextContent('First');
		expect(intervalSpy).not.toHaveBeenCalled();
	});

	it('does not create a second timer when initialized repeatedly', () => {
		renderHeadline();

		initAnimatedHeadlines();
		initAnimatedHeadlines();

		expect(intervalSpy).toHaveBeenCalledTimes(1);
	});

	it('disposes a detached heading timer and lets the reattached heading initialize again', () => {
		const heading = renderHeadline();

		initAnimatedHeadlines();
		heading.remove();
		jest.advanceTimersByTime(250);

		expect(clearIntervalSpy).toHaveBeenCalledTimes(1);
		document.body.appendChild(heading);
		initAnimatedHeadlines();
		expect(intervalSpy).toHaveBeenCalledTimes(2);
	});

	it('pauses while the document is hidden and resumes when it becomes visible', () => {
		const heading = renderHeadline();

		initAnimatedHeadlines();
		hidden = true;
		document.dispatchEvent(new Event('visibilitychange'));
		jest.advanceTimersByTime(250);
		expect(
			heading.querySelector('.dsgo-heading-segment__animated')
		).toHaveTextContent('First');

		hidden = false;
		document.dispatchEvent(new Event('visibilitychange'));
		jest.advanceTimersByTime(250);
		expect(
			heading.querySelector('.dsgo-heading-segment__animated')
		).toHaveTextContent('Second');
	});

	it('rejects unsafe saved data rather than creating an animation timer', () => {
		renderHeadline({ effect: 'untrusted', duration: '1' });

		initAnimatedHeadlines();

		expect(intervalSpy).not.toHaveBeenCalled();
	});

	it('rejects a tampered loop value rather than treating it as looping', () => {
		const heading = renderHeadline({ loop: 'anything' });

		initAnimatedHeadlines();
		jest.advanceTimersByTime(1000);

		expect(
			heading.querySelector('.dsgo-heading-segment__animated')
		).toHaveTextContent('First');
		expect(intervalSpy).not.toHaveBeenCalled();
	});

	it('uses Text Reveal splitting only for the compatible typing effect', () => {
		const typing = renderHeadline({ effect: 'typing', words: ['Hello'] });
		initAnimatedHeadlines();
		expect(typing.querySelectorAll('.dsgo-text-reveal-unit')).toHaveLength(
			5
		);

		const clip = renderHeadline({ effect: 'clip', words: ['Hello'] });
		initAnimatedHeadlines();
		expect(clip.querySelectorAll('.dsgo-text-reveal-unit')).toHaveLength(0);
	});

	it('does not load Text Reveal frontend globals when the heading view loads', () => {
		delete window.dsgoTextReveal;
		jest.resetModules();
		jest.doMock('../../../extensions/text-reveal/frontend', () => {
			throw new Error(
				'Advanced Heading must not import Text Reveal frontend'
			);
		});

		expect(() => {
			jest.isolateModules(() => {
				require('../view');
			});
		}).not.toThrow();
		expect(window.dsgoTextReveal).toBeUndefined();
		jest.dontMock('../../../extensions/text-reveal/frontend');
	});
});
