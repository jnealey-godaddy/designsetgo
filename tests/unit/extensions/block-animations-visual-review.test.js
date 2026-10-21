/**
 * Visual-review captures reveal every animated block at rest.
 *
 * Site Designer screenshots a generated page without scrolling it, with
 * sd-visual-review in the URL. The scroll observer would leave every block
 * below the fold at opacity 0, so the reviewer judged empty proof strips
 * and blank rows that a visitor never sees.
 */

describe('block-animations frontend: visual review capture', () => {
	beforeEach(() => {
		jest.resetModules();
		document.documentElement.className = '';
		document.body.innerHTML =
			'<div class="has-dsgo-animation" data-dsgo-animation-enabled="true" data-dsgo-entrance-animation="fadeInUp"></div>';
		window.matchMedia = jest.fn().mockReturnValue({ matches: false });
	});

	it('marks the document and leaves the observer unwired when the URL carries sd-visual-review', async () => {
		window.history.pushState({}, '', '/?sd-visual-review=1789000000');
		window.IntersectionObserver = jest.fn();

		await import('../../../src/extensions/block-animations/frontend');
		document.dispatchEvent(new Event('DOMContentLoaded'));

		expect(
			document.documentElement.classList.contains('dsgo-visual-review')
		).toBe(true);
		expect(window.IntersectionObserver).not.toHaveBeenCalled();
		expect(
			document.querySelector('.has-dsgo-animation').dataset
				.dsgoAnimationInitialized
		).toBeUndefined();
	});

	it('initializes animations as usual on an ordinary visit', async () => {
		window.history.pushState({}, '', '/');
		window.IntersectionObserver = jest.fn().mockImplementation(() => ({
			observe: jest.fn(),
			unobserve: jest.fn(),
		}));

		await import('../../../src/extensions/block-animations/frontend');
		document.dispatchEvent(new Event('DOMContentLoaded'));

		expect(
			document.documentElement.classList.contains('dsgo-visual-review')
		).toBe(false);
		expect(
			document.querySelector('.has-dsgo-animation').dataset
				.dsgoAnimationInitialized
		).toBe('true');
	});
});

describe('block-animations frontend: unreachable elements', () => {
	it('flags elements that sit wholly inside the observer dead band', async () => {
		const { isUnreachableByObserver } = await import(
			'../../../src/extensions/block-animations/frontend'
		);
		const mk = (top, height) => {
			const el = document.createElement('div');
			el.getBoundingClientRect = () => ({ top, height });
			return el;
		};
		Object.defineProperty(document.documentElement, 'scrollHeight', {
			configurable: true,
			value: 2000,
		});
		window.scrollY = 0;

		// 40px tall at the top of the page, offset 100: never reaches the band.
		expect(isUnreachableByObserver(mk(10, 40), 100)).toBe(true);
		// Starts in the last 100px of the document.
		expect(isUnreachableByObserver(mk(1950, 40), 100)).toBe(true);
		// Ordinary elements are left to the observer.
		expect(isUnreachableByObserver(mk(10, 400), 100)).toBe(false);
		expect(isUnreachableByObserver(mk(800, 100), 100)).toBe(false);
		expect(isUnreachableByObserver(mk(10, 40), 0)).toBe(false);
	});
});
