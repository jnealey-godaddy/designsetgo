/**
 * Chart Block - DataEditor tests
 */

import { render, screen, fireEvent } from '@testing-library/react';
import { DataEditor } from '../../../src/blocks/chart/components/DataEditor';

describe('DataEditor', () => {
	it('renders one label input per row', () => {
		render(
			<DataEditor
				value={[
					{ label: 'A', value: 1 },
					{ label: 'B', value: 2 },
				]}
				onChange={() => {}}
			/>
		);
		expect(screen.getAllByLabelText(/label/i)).toHaveLength(2);
	});

	it('appends an empty row on add', () => {
		const onChange = jest.fn();
		render(<DataEditor value={[]} onChange={onChange} />);
		fireEvent.click(screen.getByRole('button', { name: /add row/i }));
		expect(onChange).toHaveBeenCalledWith([{ label: '', value: 0 }]);
	});

	it('removes the row at the given index', () => {
		const onChange = jest.fn();
		render(
			<DataEditor
				value={[
					{ label: 'A', value: 1 },
					{ label: 'B', value: 2 },
				]}
				onChange={onChange}
			/>
		);
		fireEvent.click(
			screen.getAllByRole('button', { name: /remove row/i })[0]
		);
		expect(onChange).toHaveBeenCalledWith([{ label: 'B', value: 2 }]);
	});

	it('coerces a typed value to a number', () => {
		const onChange = jest.fn();
		render(
			<DataEditor
				value={[{ label: 'A', value: 1 }]}
				onChange={onChange}
			/>
		);
		fireEvent.change(screen.getByLabelText(/value/i), {
			target: { value: '42' },
		});
		expect(onChange).toHaveBeenCalledWith([{ label: 'A', value: 42 }]);
	});

	it('coerces unparseable input to zero rather than NaN', () => {
		const onChange = jest.fn();
		render(
			<DataEditor
				value={[{ label: 'A', value: 1 }]}
				onChange={onChange}
			/>
		);
		fireEvent.change(screen.getByLabelText(/value/i), {
			target: { value: 'abc' },
		});
		expect(onChange).toHaveBeenCalledWith([{ label: 'A', value: 0 }]);
	});

	it('survives a missing value prop', () => {
		render(<DataEditor value={undefined} onChange={() => {}} />);
		expect(
			screen.getByRole('button', { name: /add row/i })
		).toBeInTheDocument();
	});

	it('edits a label without disturbing the other rows', () => {
		const onChange = jest.fn();
		render(
			<DataEditor
				value={[
					{ label: 'A', value: 1 },
					{ label: 'B', value: 2 },
				]}
				onChange={onChange}
			/>
		);
		fireEvent.change(screen.getAllByLabelText(/label/i)[1], {
			target: { value: 'Bee' },
		});
		expect(onChange).toHaveBeenCalledWith([
			{ label: 'A', value: 1 },
			{ label: 'Bee', value: 2 },
		]);
	});
});
