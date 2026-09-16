/**
 * `image-alt` (error): flags `core/image` with empty/missing `alt`, and
 * `designsetgo/dynamic-image` when it explicitly sets an empty
 * `altOverride` (see the rule module for why an *absent* `altOverride` is
 * trusted to defer to the bound source's own alt text). Either block is
 * exempt when `className` contains `is-decorative`.
 */
import { lint } from '../../index';
import rule from '../image-alt';

function tree(blocks) {
	return { version: 1, blocks };
}

function run(blocks) {
	return lint(tree(blocks), {}, { rules: [rule] });
}

describe('image-alt', () => {
	test('has the documented id/severity', () => {
		expect(rule.id).toBe('image-alt');
		expect(rule.severity).toBe('error');
	});

	test('flags core/image with a missing alt attribute', () => {
		const findings = run([
			{ name: 'core/image', attributes: { url: 'https://x/y.jpg' } },
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0]).toMatchObject({
			rule: 'image-alt',
			severity: 'error',
			path: 'blocks[0]',
		});
	});

	test('flags core/image with an empty alt attribute', () => {
		const findings = run([
			{
				name: 'core/image',
				attributes: { url: 'https://x/y.jpg', alt: '' },
			},
		]);
		expect(findings).toHaveLength(1);
	});

	test('does not flag core/image with real alt text', () => {
		const findings = run([
			{
				name: 'core/image',
				attributes: { url: 'https://x/y.jpg', alt: 'A red fox' },
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag core/image marked decorative via className', () => {
		const findings = run([
			{
				name: 'core/image',
				attributes: {
					url: 'https://x/y.jpg',
					className: 'is-decorative',
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag core/image decorative among other classes', () => {
		const findings = run([
			{
				name: 'core/image',
				attributes: {
					url: 'https://x/y.jpg',
					className: 'foo is-decorative bar',
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag designsetgo/dynamic-image with no altOverride (bound source supplies alt)', () => {
		const findings = run([
			{
				name: 'designsetgo/dynamic-image',
				attributes: { source: 'featured-image' },
			},
		]);
		expect(findings).toEqual([]);
	});

	test('flags designsetgo/dynamic-image with an explicit empty altOverride', () => {
		const findings = run([
			{
				name: 'designsetgo/dynamic-image',
				attributes: { source: 'featured-image', altOverride: '' },
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0].path).toBe('blocks[0]');
	});

	test('does not flag designsetgo/dynamic-image with a real altOverride', () => {
		const findings = run([
			{
				name: 'designsetgo/dynamic-image',
				attributes: {
					source: 'featured-image',
					altOverride: 'A red fox',
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag designsetgo/dynamic-image marked decorative even with empty altOverride', () => {
		const findings = run([
			{
				name: 'designsetgo/dynamic-image',
				attributes: {
					source: 'featured-image',
					altOverride: '',
					className: 'is-decorative',
				},
			},
		]);
		expect(findings).toEqual([]);
	});

	test('does not flag unrelated blocks', () => {
		const findings = run([{ name: 'core/paragraph', attributes: {} }]);
		expect(findings).toEqual([]);
	});

	test('does not crash on a block with no attributes', () => {
		expect(() => run([{ name: 'core/image' }])).not.toThrow();
		expect(run([{ name: 'core/image' }])).toHaveLength(1);
		expect(() =>
			run([{ name: 'designsetgo/dynamic-image' }])
		).not.toThrow();
		expect(run([{ name: 'designsetgo/dynamic-image' }])).toEqual([]);
	});
});
