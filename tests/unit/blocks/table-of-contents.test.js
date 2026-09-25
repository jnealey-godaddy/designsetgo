/**
 * Table of Contents Block - Frontend Unit Tests
 *
 * @package
 */

/**
 * Build a TOC block plus a few headings inside <main>.
 *
 * @param {Object}  options              Options.
 * @param {boolean} options.scrollSmooth Value of data-scroll-smooth.
 * @return {HTMLElement} The TOC element.
 */
function createToc({ scrollSmooth }) {
	const main = document.createElement('main');
	['Intro', 'Setup'].forEach((text) => {
		const heading = document.createElement('h2');
		heading.textContent = text;
		main.appendChild(heading);
	});

	const toc = document.createElement('div');
	toc.className = 'dsgo-table-of-contents';
	toc.dataset.headingLevels = 'h2';
	toc.dataset.scrollSmooth = String(scrollSmooth);
	const list = document.createElement('ul');
	list.className = 'dsgo-table-of-contents__list';
	toc.appendChild(list);
	main.prepend(toc);

	document.body.appendChild(main);
	return toc;
}

function loadView() {
	jest.isolateModules(() => {
		require('../../../src/blocks/table-of-contents/view.js');
	});
	document.dispatchEvent(new Event('DOMContentLoaded'));
}

describe('Table of Contents - Frontend', () => {
	let observers;
	const RealObserver = global.IntersectionObserver;

	beforeEach(() => {
		observers = [];
		global.IntersectionObserver = class extends RealObserver {
			constructor(...args) {
				super(...args);
				observers.push(this);
			}
		};
	});

	afterEach(() => {
		global.IntersectionObserver = RealObserver;
		document.body.innerHTML = '';
	});

	test.each([true, false])(
		'scroll spy watches every heading when smooth scroll is %s',
		(scrollSmooth) => {
			createToc({ scrollSmooth });
			loadView();

			expect(observers).toHaveLength(1);
			expect(observers[0].observe).toHaveBeenCalledTimes(2);
		}
	);

	test('scroll spy marks the intersecting heading active', () => {
		const toc = createToc({ scrollSmooth: false });
		loadView();

		const heading = document.querySelectorAll('h2')[1];
		observers[0].simulateIntersection([
			{ isIntersecting: true, target: heading },
		]);

		const active = toc.querySelector(
			'.dsgo-table-of-contents__link--active'
		);
		expect(active.getAttribute('href')).toBe(`#${heading.id}`);
	});
});
