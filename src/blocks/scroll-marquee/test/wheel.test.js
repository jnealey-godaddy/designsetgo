/* global WheelEvent */
describe('scroll marquee wheel handling', () => {
	function setup() {
		window.matchMedia = jest.fn(() => ({ matches: false }));
		global.IntersectionObserver = class {
			observe() {}
			disconnect() {}
		};
		global.requestAnimationFrame = jest.fn(() => 1);
		document.body.innerHTML = `
			<div class="dsgo-scroll-marquee" data-scroll-speed="0.5">
				<div class="dsgo-scroll-marquee__row" data-direction="left">
					<div class="dsgo-scroll-marquee__track"><div class="dsgo-scroll-marquee__track-segment"></div></div>
				</div>
			</div>`;
		jest.isolateModules(() => require('../view'));
		return document.querySelector('.dsgo-scroll-marquee');
	}

	function wheel(el, init) {
		const e = new WheelEvent('wheel', {
			bubbles: true,
			cancelable: true,
			...init,
		});
		el.dispatchEvent(e);
		return e.defaultPrevented;
	}

	it('leaves vertical-dominant swipes to the page', () => {
		const el = setup();
		expect(wheel(el, { deltaX: 3, deltaY: 40 })).toBe(false);
	});

	it('captures horizontal-dominant swipes', () => {
		const el = setup();
		expect(wheel(el, { deltaX: 40, deltaY: 3 })).toBe(true);
	});

	it('captures shift+wheel', () => {
		const el = setup();
		expect(wheel(el, { deltaY: 40, shiftKey: true })).toBe(true);
	});
});
