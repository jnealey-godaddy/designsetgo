/**
 * DsgoBlockPlaceholder Tests
 *
 * @package
 */

import { render, screen, fireEvent } from '@testing-library/react';

const mockReplaceInnerBlocks = jest.fn();

jest.mock('@wordpress/data', () => ({
	useDispatch: () => ({ replaceInnerBlocks: mockReplaceInnerBlocks }),
}));

jest.mock('@wordpress/block-editor', () => ({
	store: 'core/block-editor',
}));

jest.mock('@wordpress/blocks', () => ({
	createBlocksFromInnerBlocksTemplate: (template) =>
		template.map(([name, attrs]) => ({ name, attrs })),
}));

jest.mock('@wordpress/components', () => ({
	Placeholder: ({ label, instructions, className, children }) => (
		<div className={className} data-testid="placeholder">
			<span>{label}</span>
			<span>{instructions}</span>
			{children}
		</div>
	),
	Button: ({ className, onClick, children }) => (
		<button className={className} onClick={onClick} type="button">
			{children}
		</button>
	),
	Icon: ({ icon }) => <span data-testid="icon">{icon}</span>,
}));

import DsgoBlockPlaceholder from '../../../../src/components/shared/DsgoBlockPlaceholder';

const templates = [
	{
		name: 'horizontal',
		title: 'Horizontal',
		description: 'Side-by-side',
		icon: 'align-center',
		attributes: { layout: 'horizontal' },
		innerBlocks: [['core/paragraph', { content: 'h' }]],
	},
	{
		name: 'vertical',
		title: 'Vertical',
		description: 'Stacked',
		icon: 'align-left',
		innerBlocks: [['core/paragraph', { content: 'v' }]],
	},
];

describe('DsgoBlockPlaceholder', () => {
	beforeEach(() => {
		mockReplaceInnerBlocks.mockClear();
	});

	test('renders label and instructions', () => {
		render(
			<DsgoBlockPlaceholder
				clientId="abc"
				setAttributes={jest.fn()}
				icon="block-default"
				label="Tabs"
				instructions="Pick a starting layout"
				templates={templates}
			/>
		);
		expect(screen.getByText('Tabs')).toBeInTheDocument();
		expect(screen.getByText('Pick a starting layout')).toBeInTheDocument();
	});

	test('renders one button per template', () => {
		render(
			<DsgoBlockPlaceholder
				clientId="abc"
				setAttributes={jest.fn()}
				icon="block-default"
				label="Tabs"
				instructions="Pick"
				templates={templates}
			/>
		);
		expect(
			screen.getByRole('button', { name: /Horizontal/ })
		).toBeInTheDocument();
		expect(
			screen.getByRole('button', { name: /Vertical/ })
		).toBeInTheDocument();
	});

	test('applies template attributes and seeds inner blocks on select', () => {
		const setAttributes = jest.fn();
		render(
			<DsgoBlockPlaceholder
				clientId="abc"
				setAttributes={setAttributes}
				icon="block-default"
				label="Tabs"
				instructions="Pick"
				templates={templates}
			/>
		);
		fireEvent.click(screen.getByRole('button', { name: /Horizontal/ }));
		expect(setAttributes).toHaveBeenCalledWith({ layout: 'horizontal' });
		expect(mockReplaceInnerBlocks).toHaveBeenCalledWith(
			'abc',
			[{ name: 'core/paragraph', attrs: { content: 'h' } }],
			false
		);
	});

	test('applies variant modifier class when supplied', () => {
		const { container } = render(
			<DsgoBlockPlaceholder
				clientId="abc"
				setAttributes={jest.fn()}
				icon="block-default"
				label="Tabs"
				instructions="Pick"
				templates={templates}
				variant="slim"
			/>
		);
		expect(
			container.querySelector('.dsgo-block-placeholder--slim')
		).not.toBeNull();
	});
});
