/**
 * DsgoBlockPlaceholder Tests
 *
 * @package
 */

import { render, screen, fireEvent } from '@testing-library/react';
import { DsgoBlockPlaceholder } from '../../../../src/components/shared/DsgoBlockPlaceholder';

const variations = [
	{
		name: 'horizontal',
		title: 'Horizontal',
		description: 'Side-by-side',
		icon: 'align-center',
	},
	{
		name: 'vertical',
		title: 'Vertical',
		description: 'Stacked',
		icon: 'align-left',
	},
];

describe('DsgoBlockPlaceholder', () => {
	test('renders label and instructions', () => {
		render(
			<DsgoBlockPlaceholder
				icon="block-default"
				label="Tabs"
				instructions="Pick a starting layout"
				variations={variations}
				onSelect={jest.fn()}
			/>
		);
		expect(screen.getByText('Tabs')).toBeInTheDocument();
		// WordPress Placeholder sends instructions to a11y-speak region too — use getAllByText
		expect(
			screen.getAllByText('Pick a starting layout').length
		).toBeGreaterThan(0);
	});

	test('renders one button per variation', () => {
		render(
			<DsgoBlockPlaceholder
				icon="block-default"
				label="Tabs"
				instructions="Pick a starting layout"
				variations={variations}
				onSelect={jest.fn()}
			/>
		);
		expect(screen.getByRole('button', { name: /Horizontal/ })).toBeInTheDocument();
		expect(screen.getByRole('button', { name: /Vertical/ })).toBeInTheDocument();
	});

	test('invokes onSelect with the chosen variation', () => {
		const onSelect = jest.fn();
		render(
			<DsgoBlockPlaceholder
				icon="block-default"
				label="Tabs"
				instructions="Pick a starting layout"
				variations={variations}
				onSelect={onSelect}
			/>
		);
		fireEvent.click(screen.getByRole('button', { name: /Vertical/ }));
		expect(onSelect).toHaveBeenCalledWith(variations[1]);
	});

	test('renders nothing for variations when array is empty', () => {
		render(
			<DsgoBlockPlaceholder
				icon="block-default"
				label="Tabs"
				instructions="Pick"
				variations={[]}
				onSelect={jest.fn()}
			/>
		);
		expect(screen.queryByRole('button')).not.toBeInTheDocument();
	});
});
