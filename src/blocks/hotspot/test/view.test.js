/* global FocusEvent, KeyboardEvent, MouseEvent */

import { initHotspots } from '../view';

let parentNumber = 0;

const parentMarkup = ({
	trigger = 'click',
	link = false,
	itemTrigger = '',
} = {}) => {
	parentNumber += 1;
	const id = `hotspot-${parentNumber}`;
	const marker = link
		? `<a class="dsgo-hotspot-item__marker" data-dsgo-hotspot-marker="true" href="/destination">One</a>`
		: `<button class="dsgo-hotspot-item__marker" data-dsgo-hotspot-marker="true" type="button">One</button>`;

	return `
		<div class="dsgo-hotspot" data-dsgo-hotspot="true" data-dsgo-hotspot-trigger="${trigger}" id="${id}">
			<div class="dsgo-hotspot-item" data-dsgo-hotspot-item="true" ${
				itemTrigger ? `data-dsgo-hotspot-trigger="${itemTrigger}"` : ''
			}>
				${marker}
				<div class="dsgo-hotspot-item__tooltip" data-dsgo-hotspot-tooltip="true" id="${id}-first-tooltip" role="tooltip">First</div>
			</div>
			<div class="dsgo-hotspot-item" data-dsgo-hotspot-item="true">
				<button class="dsgo-hotspot-item__marker" data-dsgo-hotspot-marker="true" type="button">Two</button>
				<div class="dsgo-hotspot-item__tooltip" data-dsgo-hotspot-tooltip="true" id="${id}-second-tooltip" role="tooltip" tabindex="-1">Second</div>
			</div>
		</div>
	`;
};

const mount = (options) => {
	document.body.innerHTML = parentMarkup(options);
	initHotspots();
	return document.querySelector('[data-dsgo-hotspot]');
};

const itemsFor = (parent) =>
	Array.from(parent.querySelectorAll('[data-dsgo-hotspot-item]'));

describe('Hotspot frontend interactions', () => {
	afterEach(() => {
		document.body.innerHTML = '';
	});

	it('toggles its own click tooltip, synchronizes ARIA, and closes its sibling', () => {
		const parent = mount();
		const [first, second] = itemsFor(parent);
		const firstMarker = first.querySelector('[data-dsgo-hotspot-marker]');
		const firstTooltip = first.querySelector('[data-dsgo-hotspot-tooltip]');
		const secondMarker = second.querySelector('[data-dsgo-hotspot-marker]');

		expect(firstMarker.getAttribute('aria-controls')).toBe(firstTooltip.id);
		expect(firstMarker.getAttribute('aria-expanded')).toBe('false');
		expect(firstMarker.hasAttribute('aria-describedby')).toBe(false);

		firstMarker.click();

		expect(first.classList.contains('is-active')).toBe(true);
		expect(firstTooltip.classList.contains('is-open')).toBe(true);
		expect(firstTooltip.hidden).toBe(false);
		expect(firstMarker.getAttribute('aria-expanded')).toBe('true');
		expect(second.classList.contains('is-active')).toBe(false);
		expect(secondMarker.getAttribute('aria-expanded')).toBe('false');

		secondMarker.click();

		expect(first.classList.contains('is-active')).toBe(false);
		expect(firstTooltip.hidden).toBe(true);
		expect(firstMarker.getAttribute('aria-expanded')).toBe('false');
		expect(second.classList.contains('is-active')).toBe(true);
	});

	it('closes click tooltips on Escape and outside click', () => {
		const parent = mount();
		const [first] = itemsFor(parent);
		const marker = first.querySelector('[data-dsgo-hotspot-marker]');
		const tooltip = first.querySelector('[data-dsgo-hotspot-tooltip]');

		marker.click();
		document.dispatchEvent(
			new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })
		);
		expect(tooltip.hidden).toBe(true);

		marker.click();
		document.body.dispatchEvent(
			new MouseEvent('click', { bubbles: true, cancelable: true })
		);
		expect(tooltip.hidden).toBe(true);
	});

	it('restores focus to the marker when Escape closes its tooltip', () => {
		const parent = mount();
		const [first] = itemsFor(parent);
		const marker = first.querySelector('[data-dsgo-hotspot-marker]');
		const tooltip = first.querySelector('[data-dsgo-hotspot-tooltip]');
		tooltip.innerHTML = '<a href="#details">More details</a>';
		const tooltipLink = tooltip.querySelector('a');

		marker.click();
		tooltipLink.focus();
		expect(document.activeElement).toBe(tooltipLink);

		document.dispatchEvent(
			new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })
		);

		expect(tooltip.hidden).toBe(true);
		expect(document.activeElement).toBe(marker);
	});

	it('opens hover tooltips on pointer and focus without closing inside the item', () => {
		const parent = mount({ trigger: 'hover' });
		const [first] = itemsFor(parent);
		const marker = first.querySelector('[data-dsgo-hotspot-marker]');
		const tooltip = first.querySelector('[data-dsgo-hotspot-tooltip]');

		expect(marker.getAttribute('aria-describedby')).toBe(tooltip.id);
		expect(marker.hasAttribute('aria-controls')).toBe(false);
		expect(marker.hasAttribute('aria-expanded')).toBe(false);

		marker.dispatchEvent(
			new MouseEvent('pointerover', {
				bubbles: true,
				relatedTarget: document.body,
			})
		);
		expect(tooltip.hidden).toBe(false);

		marker.dispatchEvent(
			new MouseEvent('pointerout', {
				bubbles: true,
				relatedTarget: tooltip,
			})
		);
		expect(tooltip.hidden).toBe(false);

		tooltip.dispatchEvent(
			new MouseEvent('pointerout', {
				bubbles: true,
				relatedTarget: document.body,
			})
		);
		expect(tooltip.hidden).toBe(true);

		marker.dispatchEvent(
			new FocusEvent('focusin', {
				bubbles: true,
				relatedTarget: document.body,
			})
		);
		expect(tooltip.hidden).toBe(false);

		marker.dispatchEvent(
			new FocusEvent('focusout', {
				bubbles: true,
				relatedTarget: tooltip,
			})
		);
		expect(tooltip.hidden).toBe(false);

		tooltip.dispatchEvent(
			new FocusEvent('focusout', {
				bubbles: true,
				relatedTarget: document.body,
			})
		);
		expect(tooltip.hidden).toBe(true);
	});

	it('applies a child trigger override to the initial ARIA contract', () => {
		const parent = mount({ trigger: 'click', itemTrigger: 'hover' });
		const [first, second] = itemsFor(parent);
		const firstMarker = first.querySelector('[data-dsgo-hotspot-marker]');
		const firstTooltip = first.querySelector('[data-dsgo-hotspot-tooltip]');
		const secondMarker = second.querySelector('[data-dsgo-hotspot-marker]');
		const secondTooltip = second.querySelector(
			'[data-dsgo-hotspot-tooltip]'
		);

		expect(firstMarker.getAttribute('aria-describedby')).toBe(
			firstTooltip.id
		);
		expect(firstMarker.hasAttribute('aria-controls')).toBe(false);
		expect(firstMarker.hasAttribute('aria-expanded')).toBe(false);
		expect(secondMarker.getAttribute('aria-controls')).toBe(
			secondTooltip.id
		);
		expect(secondMarker.getAttribute('aria-expanded')).toBe('false');
		expect(secondMarker.hasAttribute('aria-describedby')).toBe(false);
	});

	it('uses an item trigger override instead of reparsing generic interactions', () => {
		const parent = mount({ trigger: 'click' });
		const [first] = itemsFor(parent);
		const marker = first.querySelector('[data-dsgo-hotspot-marker]');
		const tooltip = first.querySelector('[data-dsgo-hotspot-tooltip]');

		first.setAttribute('data-dsgo-hotspot-trigger', 'hover');
		marker.click();
		expect(tooltip.hidden).toBe(true);

		marker.dispatchEvent(
			new MouseEvent('pointerover', {
				bubbles: true,
				relatedTarget: document.body,
			})
		);
		expect(tooltip.hidden).toBe(false);
	});

	it('keeps a linked marker navigable and exposes its tooltip on hover/focus', () => {
		const parent = mount({ link: true });
		const [first] = itemsFor(parent);
		const link = first.querySelector('[data-dsgo-hotspot-marker]');
		const tooltip = first.querySelector('[data-dsgo-hotspot-tooltip]');
		const event = new MouseEvent('click', {
			bubbles: true,
			cancelable: true,
		});

		expect(link.getAttribute('aria-describedby')).toBe(tooltip.id);
		expect(link.hasAttribute('aria-controls')).toBe(false);
		expect(link.hasAttribute('aria-expanded')).toBe(false);
		expect(link.dispatchEvent(event)).toBe(true);
		expect(event.defaultPrevented).toBe(false);
		expect(first.classList.contains('is-active')).toBe(false);

		link.dispatchEvent(
			new MouseEvent('pointerover', {
				bubbles: true,
				relatedTarget: document.body,
			})
		);
		expect(tooltip.hidden).toBe(false);

		link.dispatchEvent(
			new FocusEvent('focusin', {
				bubbles: true,
				relatedTarget: document.body,
			})
		);
		expect(tooltip.hidden).toBe(false);
	});

	it('adds one document delegate for each event type across parent blocks', () => {
		jest.resetModules();
		const listenerSpy = jest.spyOn(document, 'addEventListener');
		const { initHotspots: initFreshHotspots } = require('../view');
		document.body.innerHTML = `${parentMarkup()}${parentMarkup()}`;
		initFreshHotspots();
		initFreshHotspots();

		const hotspotEvents = listenerSpy.mock.calls
			.map(([type]) => type)
			.filter((type) =>
				[
					'click',
					'pointerover',
					'pointerout',
					'focusin',
					'focusout',
					'keydown',
				].includes(type)
			);

		expect(hotspotEvents).toEqual([
			'click',
			'pointerover',
			'pointerout',
			'focusin',
			'focusout',
			'keydown',
		]);
		listenerSpy.mockRestore();
	});
});
