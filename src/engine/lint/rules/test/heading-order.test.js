/**
 * `heading-order` (error, page-level): collects `core/heading` and
 * `designsetgo/advanced-heading` levels in document order (both default to
 * level 2 per their block.json — WP core for `core/heading`,
 * `src/blocks/advanced-heading/block.json` for the DSGo block). Flags a
 * second (or later) level-1 heading, and any downward jump of more than
 * one level (e.g. H2 straight to H4).
 */
import { lint } from '../../index';
import rule from '../heading-order';

function tree(blocks) {
	return { version: 1, blocks };
}

function run(blocks) {
	return lint(tree(blocks), {}, { rules: [rule] });
}

function heading(level, extra = {}) {
	return { name: 'core/heading', attributes: { level, ...extra } };
}

function advancedHeading(level, extra = {}) {
	return {
		name: 'designsetgo/advanced-heading',
		attributes: { level, ...extra },
	};
}

describe('heading-order', () => {
	test('has the documented id/severity', () => {
		expect(rule.id).toBe('heading-order');
		expect(rule.severity).toBe('error');
	});

	test('does not flag a single, well-ordered heading sequence', () => {
		const findings = run([heading(1), heading(2), heading(3)]);
		expect(findings).toEqual([]);
	});

	test('flags a second level-1 heading, on the second heading', () => {
		const findings = run([heading(1), heading(2), heading(1)]);
		expect(findings).toHaveLength(1);
		expect(findings[0]).toMatchObject({
			rule: 'heading-order',
			severity: 'error',
			path: 'blocks[2]',
		});
	});

	test('flags a downward jump of more than one level, on the later heading', () => {
		const findings = run([heading(1), heading(2), heading(4)]);
		expect(findings).toHaveLength(1);
		expect(findings[0]).toMatchObject({
			rule: 'heading-order',
			path: 'blocks[2]',
		});
	});

	test('does not flag an upward jump (H4 back to H2)', () => {
		const findings = run([heading(1), heading(2), heading(4), heading(2)]);
		// Only the 2 -> 4 downward jump should be flagged, not 4 -> 2.
		expect(findings).toHaveLength(1);
		expect(findings[0].path).toBe('blocks[2]');
	});

	test('treats designsetgo/advanced-heading the same as core/heading', () => {
		const findings = run([advancedHeading(2), advancedHeading(4)]);
		expect(findings).toHaveLength(1);
		expect(findings[0].path).toBe('blocks[1]');
	});

	test('mixes core/heading and designsetgo/advanced-heading in document order', () => {
		const findings = run([heading(2), advancedHeading(4)]);
		expect(findings).toHaveLength(1);
		expect(findings[0].path).toBe('blocks[1]');
	});

	test('treats a missing level as the block.json default of 2', () => {
		// A default-valued attribute is omitted from the tree.
		const findings = run([
			{ name: 'core/heading', attributes: {} },
			{ name: 'core/heading', attributes: {} },
		]);
		expect(findings).toEqual([]);
	});

	test('finds headings nested inside container blocks, in document order', () => {
		const findings = run([
			{
				name: 'core/group',
				innerBlocks: [heading(1), heading(4)],
			},
		]);
		expect(findings).toHaveLength(1);
		expect(findings[0].path).toBe('blocks[0].innerBlocks[1]');
	});

	test('ignores non-heading blocks', () => {
		const findings = run([
			heading(1),
			{ name: 'core/paragraph', attributes: {} },
			heading(2),
		]);
		expect(findings).toEqual([]);
	});

	test('does not crash on an empty tree', () => {
		expect(() => run([])).not.toThrow();
		expect(run([])).toEqual([]);
	});
});
