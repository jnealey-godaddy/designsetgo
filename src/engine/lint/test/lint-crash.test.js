/**
 * A rule that throws must not abort the lint pass: `lint()` catches it and
 * emits a `lint rule crashed: <message>` finding instead, for both `check`
 * and `checkTree`. Also covers the tree-shape tolerance `check()` needs —
 * a node missing `attributes`/`innerBlocks` must never throw.
 */
import { lint } from '../index';

function tree(blocks) {
	return { version: 1, blocks };
}

describe('lint rule crash handling', () => {
	test('a throwing check() rule does not abort lint and is reported as an error finding', () => {
		const throwingRule = {
			id: 'test/throws',
			severity: 'warning',
			check() {
				throw new Error('boom');
			},
		};
		const okRule = {
			id: 'test/ok',
			severity: 'warning',
			check(node, ctx) {
				ctx.report('fine');
			},
		};

		const findings = lint(
			tree([{ name: 'core/paragraph' }]),
			{},
			{ rules: [throwingRule, okRule] }
		);

		expect(findings).toContainEqual({
			rule: 'test/throws',
			severity: 'error',
			path: 'blocks[0]',
			message: 'lint rule crashed: boom',
		});
		expect(findings).toContainEqual({
			rule: 'test/ok',
			severity: 'warning',
			path: 'blocks[0]',
			message: 'fine',
		});
	});

	test('a throwing checkTree() rule does not abort lint', () => {
		const throwingRule = {
			id: 'test/tree-throws',
			severity: 'error',
			checkTree() {
				throw new Error('kaboom');
			},
		};

		const findings = lint(
			tree([{ name: 'core/paragraph' }]),
			{},
			{ rules: [throwingRule] }
		);

		expect(findings).toEqual([
			{
				rule: 'test/tree-throws',
				severity: 'error',
				path: '',
				message: 'lint rule crashed: kaboom',
			},
		]);
	});

	test('a crashing rule does not prevent other rules from reporting', () => {
		const throwingRule = {
			id: 'test/throws-again',
			severity: 'warning',
			check() {
				throw new TypeError('nope');
			},
		};
		const okRule = {
			id: 'test/still-ok',
			severity: 'error',
			check(node, ctx) {
				ctx.report('still works');
			},
		};

		const findings = lint(
			tree([{ name: 'core/paragraph' }, { name: 'core/paragraph' }]),
			{},
			{ rules: [throwingRule, okRule] }
		);

		expect(findings.filter((f) => f.rule === 'test/still-ok')).toHaveLength(
			2
		);
		expect(
			findings.filter((f) => f.rule === 'test/throws-again')
		).toHaveLength(2);
	});

	test('never throws on a tree missing attributes/innerBlocks', () => {
		const rule = {
			id: 'test/robust',
			severity: 'warning',
			check(node, ctx) {
				ctx.report(`saw ${node.name}, attrs=${typeof node.attributes}`);
			},
		};

		expect(() =>
			lint(tree([{ name: 'core/paragraph' }]), {}, { rules: [rule] })
		).not.toThrow();
	});
});
