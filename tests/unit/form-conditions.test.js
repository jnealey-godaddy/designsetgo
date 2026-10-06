/**
 * Conditional field logic evaluator — parity cases shared with PHPUnit.
 */
import fixture from '../fixtures/form-conditions-cases.json';
import {
	normalizeRules,
	hasActiveRules,
	visibleFields,
	CONDITION_OPS,
} from '../../src/blocks/form-builder/conditions';

describe('form conditions evaluator', () => {
	it.each(fixture.cases.map((c) => [c.description, c]))('%s', (_, c) => {
		expect(visibleFields(c.fields, c.conditions, c.values)).toEqual(
			c.expectedVisible
		);
	});

	it('normalizes to the known shape only', () => {
		expect(
			normalizeRules({
				operator: 'or',
				extra: 1,
				rules: [
					{ field: 'a', op: 'is', value: 5, junk: true },
					{ field: '', op: 'is', value: 'x' },
					{ field: 'b', op: 'bogus' },
				],
			})
		).toEqual({
			operator: 'OR',
			rules: [{ field: 'a', op: 'is', value: '5' }],
		});
		expect(normalizeRules(null)).toBeNull();
		expect(normalizeRules({ rules: [] })).toBeNull();
	});

	it('reports active rules', () => {
		expect(hasActiveRules({ rules: [{ field: 'a', op: 'empty' }] })).toBe(
			true
		);
		expect(hasActiveRules({ rules: [{ field: '', op: 'empty' }] })).toBe(
			false
		);
		expect(CONDITION_OPS).toEqual([
			'is',
			'is_not',
			'contains',
			'empty',
			'not_empty',
			'gt',
			'lt',
		]);
	});
});
