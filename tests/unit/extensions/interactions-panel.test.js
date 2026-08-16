import { render, screen, fireEvent } from '@testing-library/react';
import { InteractionsPanel } from '../../../src/extensions/interactions/components/InteractionsPanel';

describe('InteractionsPanel', () => {
	it('renders an empty state with an add button', () => {
		render(<InteractionsPanel value={[]} onChange={() => {}} />);
		expect(
			screen.getByRole('button', { name: /add interaction/i })
		).toBeInTheDocument();
	});

	it('appends a default interaction with a generated id on add', () => {
		const onChange = jest.fn();
		render(<InteractionsPanel value={[]} onChange={onChange} />);
		fireEvent.click(screen.getByRole('button', { name: /add interaction/i }));
		expect(onChange).toHaveBeenCalledTimes(1);
		const next = onChange.mock.calls[0][0];
		expect(next).toHaveLength(1);
		expect(next[0].trigger).toBe('click');
		expect(next[0].id).toEqual(expect.any(String));
		expect(next[0].id.length).toBeGreaterThan(0);
	});

	it('renders one row per interaction', () => {
		render(
			<InteractionsPanel
				value={[
					{ id: 'a', trigger: 'click', action: 'toggleClass' },
					{ id: 'b', trigger: 'hover', action: 'addClass' },
				]}
				onChange={() => {}}
			/>
		);
		expect(screen.getAllByRole('button', { name: /remove/i })).toHaveLength(2);
	});

	it('removes the correct interaction by id', () => {
		const onChange = jest.fn();
		render(
			<InteractionsPanel
				value={[
					{ id: 'a', trigger: 'click', action: 'toggleClass' },
					{ id: 'b', trigger: 'hover', action: 'addClass' },
				]}
				onChange={onChange}
			/>
		);
		fireEvent.click(screen.getAllByRole('button', { name: /remove/i })[0]);
		expect(onChange).toHaveBeenCalledWith([
			{ id: 'b', trigger: 'hover', action: 'addClass' },
		]);
	});

	it('tolerates a non-array value', () => {
		render(<InteractionsPanel value={undefined} onChange={() => {}} />);
		expect(
			screen.getByRole('button', { name: /add interaction/i })
		).toBeInTheDocument();
	});
});
