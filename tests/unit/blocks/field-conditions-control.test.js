/**
 * Form field conditional-logic editor control.
 */
import { render, screen, fireEvent } from '@testing-library/react';
import FieldConditionsControl, {
	opsForType,
} from '../../../src/blocks/form-builder/components/FieldConditionsControl';
import { collectFormFields } from '../../../src/blocks/form-builder/utils/use-form-fields';

const fields = [
	{
		clientId: 'a',
		name: 'type',
		label: 'Customer type',
		type: 'select',
		options: [
			{ label: 'Business', value: 'business' },
			{ label: 'Personal', value: 'personal' },
		],
	},
	{
		clientId: 'b',
		name: 'agree',
		label: 'I agree',
		type: 'checkbox',
		options: [],
	},
	{
		clientId: 'c',
		name: 'qty',
		label: 'Quantity',
		type: 'number',
		options: [],
	},
	{ clientId: 'd', name: 'name', label: 'Name', type: 'text', options: [] },
];

describe('opsForType', () => {
	const ops = (type) => opsForType(type).map((o) => o.value);
	it('filters operators by source type', () => {
		expect(ops('checkbox')).toEqual(['not_empty', 'empty']);
		expect(ops('select')).toEqual(['is', 'is_not', 'empty', 'not_empty']);
		expect(ops('number')).toEqual([
			'is',
			'is_not',
			'gt',
			'lt',
			'empty',
			'not_empty',
		]);
		expect(ops('date')).toContain('gt');
		expect(ops('text')).toEqual([
			'is',
			'is_not',
			'contains',
			'empty',
			'not_empty',
		]);
	});
});

describe('collectFormFields', () => {
	it('walks nested blocks and excludes the current block', () => {
		const blocks = [
			{
				clientId: 'x',
				name: 'designsetgo/form-text-field',
				attributes: { fieldName: 'first', label: 'First' },
				innerBlocks: [],
			},
			{
				clientId: 'g',
				name: 'core/group',
				attributes: {},
				innerBlocks: [
					{
						clientId: 'y',
						name: 'designsetgo/form-select-field',
						attributes: {
							fieldName: 'type',
							label: 'Type',
							options: [{ label: 'B', value: 'b' }],
						},
						innerBlocks: [],
					},
				],
			},
		];
		expect(collectFormFields(blocks, 'x')).toEqual([
			{
				clientId: 'y',
				name: 'type',
				label: 'Type',
				type: 'select',
				options: [{ label: 'B', value: 'b' }],
			},
		]);
	});
});

describe('FieldConditionsControl', () => {
	it('adds a rule, and removing the last rule yields null', () => {
		const onChange = jest.fn();
		const { rerender } = render(
			<FieldConditionsControl
				value={null}
				onChange={onChange}
				fields={fields}
				currentName="company"
			/>
		);
		fireEvent.click(screen.getByRole('button', { name: /add rule/i }));
		expect(onChange).toHaveBeenLastCalledWith({
			operator: 'AND',
			rules: [{ field: '', op: 'is', value: '' }],
		});

		rerender(
			<FieldConditionsControl
				value={{
					operator: 'AND',
					rules: [{ field: 'name', op: 'is', value: 'x' }],
				}}
				onChange={onChange}
				fields={fields}
				currentName="company"
			/>
		);
		fireEvent.click(screen.getByRole('button', { name: /remove rule/i }));
		expect(onChange).toHaveBeenLastCalledWith(null);
	});

	it('offers the select source options as values', () => {
		render(
			<FieldConditionsControl
				value={{
					operator: 'AND',
					rules: [{ field: 'type', op: 'is', value: '' }],
				}}
				onChange={jest.fn()}
				fields={fields}
				currentName="company"
			/>
		);
		expect(screen.getByRole('option', { name: 'Business' })).toBeTruthy();
	});

	it('resets an operator the new source does not support', () => {
		const onChange = jest.fn();
		render(
			<FieldConditionsControl
				value={{
					operator: 'AND',
					rules: [{ field: 'name', op: 'contains', value: 'x' }],
				}}
				onChange={onChange}
				fields={fields}
				currentName="company"
			/>
		);
		fireEvent.change(screen.getByLabelText(/^field$/i), {
			target: { value: 'agree' },
		});
		expect(onChange).toHaveBeenLastCalledWith({
			operator: 'AND',
			rules: [{ field: 'agree', op: 'not_empty', value: '' }],
		});
	});

	it('warns about a rule whose source no longer exists', () => {
		render(
			<FieldConditionsControl
				value={{
					operator: 'AND',
					rules: [{ field: 'gone', op: 'is', value: 'x' }],
				}}
				onChange={jest.fn()}
				fields={fields}
				currentName="company"
			/>
		);
		expect(screen.getByText(/no longer exists/i)).toBeTruthy();
	});
});
