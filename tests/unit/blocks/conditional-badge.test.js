/**
 * Form field "Conditional" badge in the editor canvas.
 */
import { render } from '@testing-library/react';
import useFormFields from '../../../src/blocks/form-builder/utils/use-form-fields';
import ConditionalBadge from '../../../src/blocks/form-builder/components/ConditionalBadge';

jest.mock('../../../src/blocks/form-builder/utils/use-form-fields', () => ({
	__esModule: true,
	default: jest.fn(),
}));

const fields = [
	{
		clientId: 'a',
		name: 'type',
		label: 'Customer type',
		type: 'select',
		options: [],
	},
];

const rules = (...list) => ({ operator: 'AND', rules: list });

describe('ConditionalBadge', () => {
	beforeEach(() => {
		useFormFields.mockReset();
		useFormFields.mockReturnValue(fields);
	});

	it('does not read the form fields for a field without rules', () => {
		const { container } = render(
			<ConditionalBadge conditions={null} clientId="self" />
		);
		expect(container.innerHTML).toBe('');
		expect(useFormFields).not.toHaveBeenCalled();
	});

	it('renders nothing when every rule points at a missing field', () => {
		const { container } = render(
			<ConditionalBadge
				conditions={rules({ field: 'gone', op: 'is', value: 'x' })}
				clientId="self"
			/>
		);
		expect(container.innerHTML).toBe('');
	});

	it('summarises the first rule that points at an existing field', () => {
		const { container } = render(
			<ConditionalBadge
				conditions={rules(
					{ field: 'gone', op: 'is', value: 'x' },
					{ field: 'type', op: 'is', value: 'business' }
				)}
				clientId="self"
			/>
		);
		const badge = container.querySelector('.dsgo-conditional-badge');
		expect(badge).toBeTruthy();
		expect(badge.hasAttribute('aria-label')).toBe(false);
		expect(
			badge.querySelector('.dsgo-conditional-badge__summary').textContent
		).toBe('Shown when Customer type is business');
		expect(badge.textContent).toContain('Conditional');
	});
});
