jest.mock('@wordpress/blocks', () => ({
	createBlock: (name, attributes = {}, innerBlocks = []) => ({
		name,
		attributes,
		innerBlocks,
	}),
	cloneBlock: (block) => ({ ...block, clientId: `${block.clientId}-clone` }),
}));

jest.mock('@wordpress/element', () => ({
	useMemo: (fn) => fn(),
}));

jest.mock('@wordpress/data', () => ({
	useSelect: (fn) => fn(global.__fakeSelect),
}));

import {
	hasStepChildren,
	splitIntoSteps,
	mergeSteps,
	STEP_BLOCK,
} from '../../../src/blocks/form-builder/utils/steps';
import { formBuilderTemplates } from '../../../src/blocks/form-builder/templates';
import useFormFields, {
	collectFormFields,
} from '../../../src/blocks/form-builder/utils/use-form-fields';

const field = (id, name = 'designsetgo/form-text-field') => ({
	clientId: id,
	name,
	attributes: { fieldName: id },
	innerBlocks: [],
});

describe('form steps editor helpers', () => {
	it('detects step children', () => {
		expect(hasStepChildren([field('a')])).toBe(false);
		expect(hasStepChildren([{ name: STEP_BLOCK, innerBlocks: [] }])).toBe(
			true
		);
	});

	it('wraps fields into one step, preserving order', () => {
		const [step, ...rest] = splitIntoSteps([field('a'), field('b')]);
		expect(rest).toHaveLength(0);
		expect(step.name).toBe(STEP_BLOCK);
		expect(step.attributes.title).toBe('Step 1');
		expect(step.innerBlocks.map((b) => b.attributes.fieldName)).toEqual([
			'a',
			'b',
		]);
	});

	it('merges steps back into one ordered list of fields', () => {
		const merged = mergeSteps([
			{ name: STEP_BLOCK, innerBlocks: [field('a'), field('b')] },
			{ name: STEP_BLOCK, innerBlocks: [field('c')] },
		]);
		expect(merged.map((b) => b.attributes.fieldName)).toEqual([
			'a',
			'b',
			'c',
		]);
		expect(
			merged.every((b) => b.name === 'designsetgo/form-text-field')
		).toBe(true);
	});
});

describe('multi-step template', () => {
	it('has two steps, each with fields', () => {
		const t = formBuilderTemplates.find((x) => x.name === 'multi-step');
		expect(t).toBeTruthy();
		const steps = t.innerBlocks.filter((b) => b[0] === STEP_BLOCK);
		expect(steps).toHaveLength(2);
		steps.forEach((s) => expect(s[2].length).toBeGreaterThan(0));
	});
});

describe('field collection across steps', () => {
	const tree = [
		{
			clientId: 's1',
			name: STEP_BLOCK,
			attributes: {},
			innerBlocks: [field('first')],
		},
		{
			clientId: 's2',
			name: STEP_BLOCK,
			attributes: {},
			innerBlocks: [field('second')],
		},
	];

	it('collects fields recursively and excludes the edited field', () => {
		expect(collectFormFields(tree, 'second').map((f) => f.name)).toEqual([
			'first',
		]);
	});

	it('useFormFields lets a step-2 field see step-1 fields', () => {
		global.__fakeSelect = () => ({
			getBlockParentsByBlockName: () => ['s2', 'form'],
			getBlocks: (id) => (id === 'form' ? tree : []),
		});
		expect(useFormFields('second').map((f) => f.name)).toEqual(['first']);
	});
});
