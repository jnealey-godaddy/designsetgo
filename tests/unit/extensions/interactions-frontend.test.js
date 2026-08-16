/* global KeyboardEvent */

import { initInteractions } from '../../../src/extensions/interactions/frontend';

const mount = (interactions) => {
	document.body.innerHTML = `
		<div id="src" data-dsgo-interactions='${JSON.stringify(interactions)}'></div>
		<div id="panel"></div>
	`;
	initInteractions();
	return document.getElementById('src');
};

describe('interactions frontend runtime', () => {
	afterEach(() => {
		document.body.innerHTML = '';
	});

	it('toggles a class on the target when the source is clicked', () => {
		const src = mount([
			{
				id: 'a',
				trigger: 'click',
				targetMode: 'selector',
				targetSelector: '#panel',
				action: 'toggleClass',
				value: 'is-open',
			},
		]);
		src.click();
		expect(
			document.getElementById('panel').classList.contains('is-open')
		).toBe(true);
	});

	it('fires when a descendant of the source is clicked', () => {
		mount([
			{
				id: 'a',
				trigger: 'click',
				targetMode: 'selector',
				targetSelector: '#panel',
				action: 'addClass',
				value: 'on',
			},
		]);
		const child = document.createElement('span');
		document.getElementById('src').appendChild(child);
		child.click();
		expect(document.getElementById('panel').classList.contains('on')).toBe(
			true
		);
	});

	it('honours "once"', () => {
		const src = mount([
			{
				id: 'a',
				trigger: 'click',
				targetMode: 'selector',
				targetSelector: '#panel',
				action: 'toggleClass',
				value: 'is-open',
				once: true,
			},
		]);
		src.click();
		src.click();
		expect(
			document.getElementById('panel').classList.contains('is-open')
		).toBe(true);
	});

	it('runs every interaction on a block, not just the first', () => {
		const src = mount([
			{
				id: 'a',
				trigger: 'click',
				targetMode: 'selector',
				targetSelector: '#panel',
				action: 'addClass',
				value: 'one',
			},
			{
				id: 'b',
				trigger: 'click',
				targetMode: 'selector',
				targetSelector: '#panel',
				action: 'addClass',
				value: 'two',
			},
		]);
		src.click();
		const panel = document.getElementById('panel');
		expect(panel.classList.contains('one')).toBe(true);
		expect(panel.classList.contains('two')).toBe(true);
	});

	it('drives every element independently, not just the first on the page', () => {
		const spec = JSON.stringify([
			{
				id: 'a',
				trigger: 'click',
				targetMode: 'self',
				action: 'addClass',
				value: 'hit',
			},
		]);
		document.body.innerHTML = `
			<div class="e" data-dsgo-interactions='${spec}'></div>
			<div class="e" data-dsgo-interactions='${spec}'></div>
		`;
		initInteractions();
		document.querySelectorAll('.e').forEach((el) => el.click());
		expect(document.querySelectorAll('.e.hit')).toHaveLength(2);
	});

	it('ignores an interaction whose trigger does not match the event', () => {
		const src = mount([
			{
				id: 'a',
				trigger: 'hover',
				targetMode: 'selector',
				targetSelector: '#panel',
				action: 'addClass',
				value: 'nope',
			},
		]);
		src.click();
		expect(
			document.getElementById('panel').classList.contains('nope')
		).toBe(false);
	});

	it('makes a click-triggered element keyboard operable', () => {
		const src = mount([
			{
				id: 'a',
				trigger: 'click',
				targetMode: 'self',
				action: 'addClass',
				value: 'hit',
			},
		]);
		expect(src.getAttribute('tabindex')).toBe('0');
		expect(src.getAttribute('role')).toBe('button');
	});

	it('does not make an existing button keyboard-redundant', () => {
		document.body.innerHTML = `
			<button id="b" data-dsgo-interactions='${JSON.stringify([
				{
					id: 'a',
					trigger: 'click',
					targetMode: 'self',
					action: 'addClass',
					value: 'x',
				},
			])}'></button>
		`;
		initInteractions();
		expect(document.getElementById('b').hasAttribute('tabindex')).toBe(
			false
		);
	});

	it('fires a keydown interaction only for the configured key', () => {
		const src = mount([
			{
				id: 'a',
				trigger: 'keydown',
				attributeName: 'Escape',
				targetMode: 'selector',
				targetSelector: '#panel',
				action: 'addClass',
				value: 'closed',
			},
		]);
		src.dispatchEvent(
			new KeyboardEvent('keydown', { key: 'a', bubbles: true })
		);
		expect(
			document.getElementById('panel').classList.contains('closed')
		).toBe(false);
		src.dispatchEvent(
			new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })
		);
		expect(
			document.getElementById('panel').classList.contains('closed')
		).toBe(true);
	});

	it('survives malformed JSON without throwing', () => {
		document.body.innerHTML = `<div id="bad" data-dsgo-interactions='{oops'></div>`;
		expect(() => initInteractions()).not.toThrow();
		expect(() => document.getElementById('bad').click()).not.toThrow();
	});

	it('is idempotent — calling twice does not double-fire', () => {
		const src = mount([
			{
				id: 'a',
				trigger: 'click',
				targetMode: 'selector',
				targetSelector: '#panel',
				action: 'toggleClass',
				value: 'is-open',
			},
		]);
		initInteractions();
		src.click();
		expect(
			document.getElementById('panel').classList.contains('is-open')
		).toBe(true);
	});
});
