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
