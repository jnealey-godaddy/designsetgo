import { render, screen, fireEvent } from '@testing-library/react';
import { InteractionsPanel } from '../../../src/extensions/interactions/components/InteractionsPanel';

const TWO = [
	{
		id: 'a',
		trigger: 'click',
		action: 'toggleClass',
		value: 'one',
		targetMode: 'self',
	},
	{
		id: 'b',
		trigger: 'hover',
		action: 'addClass',
		value: 'two',
		targetMode: 'self',
	},
];

describe('InteractionsPanel', () => {
	it('explains what interactions are when the list is empty', () => {
		render(<InteractionsPanel value={[]} onChange={() => {}} />);
		expect(screen.getByText(/clicks it, hovers it/i)).toBeInTheDocument();
	});

	it('renders an add button', () => {
		render(<InteractionsPanel value={[]} onChange={() => {}} />);
		expect(
			screen.getByRole('button', { name: /add interaction/i })
		).toBeInTheDocument();
	});

	it('appends a default interaction with a generated id on add', () => {
		const onChange = jest.fn();
		render(<InteractionsPanel value={[]} onChange={onChange} />);
		fireEvent.click(
			screen.getByRole('button', { name: /add interaction/i })
		);
		expect(onChange).toHaveBeenCalledTimes(1);
		const next = onChange.mock.calls[0][0];
		expect(next).toHaveLength(1);
		expect(next[0].trigger).toBe('click');
		expect(next[0].id).toEqual(expect.any(String));
		expect(next[0].id.length).toBeGreaterThan(0);
	});

	it('renders one collapsed row per interaction', () => {
		render(<InteractionsPanel value={TWO} onChange={() => {}} />);
		expect(
			screen.getAllByRole('button', { name: /remove interaction/i })
		).toHaveLength(2);
		// Collapsed: no controls rendered, only the summaries.
		expect(screen.queryByLabelText('When')).not.toBeInTheDocument();
	});

	it('summarises each row so two interactions are distinguishable', () => {
		render(<InteractionsPanel value={TWO} onChange={() => {}} />);
		expect(
			screen.getByText(/Click → Toggle class one/)
		).toBeInTheDocument();
		expect(screen.getByText(/Hover → Add class two/)).toBeInTheDocument();
	});

	it('expands a row when its summary is clicked', () => {
		render(<InteractionsPanel value={TWO} onChange={() => {}} />);
		fireEvent.click(screen.getByText(/Click → Toggle class one/));
		expect(screen.getByLabelText('When')).toBeInTheDocument();
	});

	it('keeps only one row expanded at a time', () => {
		render(<InteractionsPanel value={TWO} onChange={() => {}} />);
		fireEvent.click(screen.getByText(/Click → Toggle class one/));
		fireEvent.click(screen.getByText(/Hover → Add class two/));
		// Still exactly one "When" control on screen, not two.
		expect(screen.getAllByLabelText('When')).toHaveLength(1);
	});

	it('removes the correct interaction by id', () => {
		const onChange = jest.fn();
		render(<InteractionsPanel value={TWO} onChange={onChange} />);
		fireEvent.click(
			screen.getAllByRole('button', { name: /remove interaction/i })[0]
		);
		expect(onChange).toHaveBeenCalledWith([TWO[1]]);
	});

	it('tolerates a non-array value', () => {
		render(<InteractionsPanel value={undefined} onChange={() => {}} />);
		expect(
			screen.getByRole('button', { name: /add interaction/i })
		).toBeInTheDocument();
	});
});
