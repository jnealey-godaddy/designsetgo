import { resolveTarget } from '../../../src/extensions/interactions/resolve-target';

describe('resolveTarget', () => {
	beforeEach(() => {
		document.body.innerHTML = `
			<section class="outer">
				<div id="src" class="dsgo-src"></div>
				<div class="panel"></div>
				<div class="panel"></div>
			</section>
		`;
	});

	const src = () => document.getElementById('src');

	it('returns the source element for mode "self"', () => {
		expect(resolveTarget({ targetMode: 'self' }, src())).toEqual([src()]);
	});

	it('returns every match for mode "selector"', () => {
		const result = resolveTarget(
			{ targetMode: 'selector', targetSelector: '.panel' },
			src()
		);
		expect(result).toHaveLength(2);
	});

	it('returns the closest ancestor for mode "parent"', () => {
		const result = resolveTarget(
			{ targetMode: 'parent', targetSelector: '.outer' },
			src()
		);
		expect(result).toEqual([document.querySelector('.outer')]);
	});

	it('returns an empty array for an invalid selector instead of throwing', () => {
		expect(() =>
			resolveTarget(
				{ targetMode: 'selector', targetSelector: '>>>' },
				src()
			)
		).not.toThrow();
		expect(
			resolveTarget(
				{ targetMode: 'selector', targetSelector: '>>>' },
				src()
			)
		).toEqual([]);
	});

	it('returns an empty array when the selector matches nothing', () => {
		expect(
			resolveTarget(
				{ targetMode: 'selector', targetSelector: '.nope' },
				src()
			)
		).toEqual([]);
	});

	it('returns an empty array when there is no source element', () => {
		expect(resolveTarget({ targetMode: 'self' }, null)).toEqual([]);
	});

	it('returns an empty array when a selector mode has no selector', () => {
		expect(resolveTarget({ targetMode: 'selector' }, src())).toEqual([]);
	});

	it('defaults to self when no target mode is given', () => {
		expect(resolveTarget({}, src())).toEqual([src()]);
	});
});
