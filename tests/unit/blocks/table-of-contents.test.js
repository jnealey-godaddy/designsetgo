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

	describe('URL updates while scrolling', () => {
		afterEach(() => {
			window.history.replaceState(null, '', window.location.pathname);
		});

		test.each([
			[true, true],
			[false, false],
		])(
			'with smooth scroll %s, scroll spy rewrites the URL: %s',
			(scrollSmooth, rewrites) => {
				createToc({ scrollSmooth });
				loadView();

				const heading = document.querySelectorAll('h2')[1];
				observers[0].simulateIntersection([
					{ isIntersecting: true, target: heading },
				]);

				expect(window.location.hash).toBe(
					rewrites ? `#${heading.id}` : ''
				);
			}
		);
	});

	test('a link click asks hidden content to reveal its target first', () => {
		const toc = createToc({ scrollSmooth: false });
		loadView();

		const heading = document.querySelectorAll('h2')[1];
		const revealed = jest.fn();
		heading.addEventListener('dsgo-reveal', revealed);

		toc.querySelectorAll('.dsgo-table-of-contents__link')[1].click();

		expect(revealed).toHaveBeenCalledTimes(1);
	});

	test('a link to a heading in a closed accordion panel opens it', () => {
		// Both scripts together, as on a real page.
		const accordion = document.createElement('div');
		accordion.className = 'dsgo-accordion';
		const item = document.createElement('div');
		item.className = 'dsgo-accordion-item';
		const trigger = document.createElement('button');
		trigger.className = 'dsgo-accordion-item__trigger';
		const panel = document.createElement('div');
		panel.className = 'dsgo-accordion-item__panel';
		panel.hidden = true;
		const content = document.createElement('div');
		content.className = 'dsgo-accordion-item__content';
		const heading = document.createElement('h2');
		heading.textContent = 'Hidden details';
		content.appendChild(heading);
		panel.appendChild(content);
		item.append(trigger, panel);
		accordion.appendChild(item);

		const toc = createToc({ scrollSmooth: false });
		toc.parentElement.appendChild(accordion);
		window.HTMLElement.prototype.scrollIntoView = jest.fn();
		jest.isolateModules(() => {
			require('../../../src/blocks/accordion/view.js');
		});
		loadView();

		const link = [
			...toc.querySelectorAll('.dsgo-table-of-contents__link'),
		].find((a) => a.textContent === 'Hidden details');
		link.click();

		expect(panel.hidden).toBe(false);
		expect(item.classList.contains('dsgo-accordion-item--open')).toBe(true);
	});
});
