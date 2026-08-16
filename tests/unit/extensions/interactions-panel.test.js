import { render, screen, fireEvent } from '@testing-library/react';
import { InteractionsPanel } from '../../../src/extensions/interactions/components/InteractionsPanel';

// The modal's picker talks to the block-editor store, which has no provider
// in a unit test. Only the picker needs it, so it is stubbed here.
jest.mock('../../../src/extensions/interactions/useCanvasPicker', () => ({
	useCanvasPicker: () => ({
		isPicking: false,
		startPicking: jest.fn(),
		cancelPicking: jest.fn(),
	}),
}));

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

	it('renders one summary row per interaction, with no controls inline', () => {
		render(<InteractionsPanel value={TWO} onChange={() => {}} />);
		expect(
			screen.getAllByRole('button', { name: /remove interaction/i })
		).toHaveLength(2);
		// The sidebar is a list, not an editor.
		expect(screen.queryByLabelText('When')).not.toBeInTheDocument();
	});

	it('summarises each row so two interactions are distinguishable', () => {
		render(<InteractionsPanel value={TWO} onChange={() => {}} />);
		expect(
			screen.getByText(/Click → Toggle class one/)
		).toBeInTheDocument();
		expect(screen.getByText(/Hover → Add class two/)).toBeInTheDocument();
	});

	it('opens the detail modal when a row is clicked', () => {
		render(<InteractionsPanel value={TWO} onChange={() => {}} />);
		fireEvent.click(screen.getByText(/Click → Toggle class one/));
		expect(
			screen.getByRole('dialog', { name: /edit interaction/i })
		).toBeInTheDocument();
		expect(screen.getByLabelText('When')).toBeInTheDocument();
	});

	it('edits the interaction the modal was opened for', () => {
		const onChange = jest.fn();
		render(<InteractionsPanel value={TWO} onChange={onChange} />);
		fireEvent.click(screen.getByText(/Hover → Add class two/));
		fireEvent.change(screen.getByLabelText('When'), {
			target: { value: 'inView' },
		});
		const next = onChange.mock.calls[0][0];
		expect(next[1].trigger).toBe('inView');
		// The other interaction is untouched.
		expect(next[0]).toEqual(TWO[0]);
	});

	it('closes the modal on Done', () => {
		render(<InteractionsPanel value={TWO} onChange={() => {}} />);
		fireEvent.click(screen.getByText(/Click → Toggle class one/));
		fireEvent.click(screen.getByRole('button', { name: 'Done' }));
		expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
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
