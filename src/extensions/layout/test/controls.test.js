import { render, screen, fireEvent } from '@testing-library/react';
import LayoutControls from '../controls';

jest.mock('@wordpress/data', () => ({ useSelect: () => null }));
jest.mock('@wordpress/components', () => ({
	SelectControl: ({ label, value, onChange, options }) => (
		<label>
			{label}
			<select
				value={value}
				onChange={(event) => onChange(event.target.value)}
			>
				{options.map((option) => (
					<option key={option.value} value={option.value}>
						{option.label}
					</option>
				))}
			</select>
		</label>
	),
	TextControl: ({ label, value, onChange, help }) => (
		<div>
			<input
				aria-label={label}
				value={value}
				onChange={(event) => onChange(event.target.value)}
			/>
			<span>{help}</span>
		</div>
	),
}));
jest.mock('../../../components/shared/DsgoInspectorPanel', () => {
	const Panel = ({ children }) => <div>{children}</div>;
	Panel.Item = ({ children, label, onDeselect }) => (
		<div>
			{children}
			<button onClick={onDeselect}>Reset {label}</button>
		</div>
	);
	return { DsgoInspectorPanel: Panel };
});

describe('responsive composition controls', () => {
	it('keeps intermediate expressions editable and only saves valid values', () => {
		const setAttributes = jest.fn();
		render(
			<LayoutControls
				name="designsetgo/section"
				attributes={{}}
				setAttributes={setAttributes}
				clientId="a"
			/>
		);
		fireEvent.change(screen.getByLabelText('Property group'), {
			target: { value: 'size' },
		});
		const input = screen.getByLabelText('Width');
		fireEvent.change(input, { target: { value: 'clamp(' } });
		expect(input).toHaveValue('clamp(');
		expect(setAttributes).not.toHaveBeenCalled();
		fireEvent.click(screen.getByRole('button', { name: 'Reset Width' }));
		expect(screen.getByLabelText('Width')).toHaveValue('');
		setAttributes.mockClear();
		fireEvent.change(screen.getByLabelText('Width'), {
			target: { value: 'clamp(20rem, 60vw, 70rem)' },
		});
		expect(setAttributes).toHaveBeenLastCalledWith({
			dsgoLayout: { desktop: { width: 'clamp(20rem, 60vw, 70rem)' } },
		});
	});
	it('sets only the selected breakpoint and resets to inheritance', () => {
		const setAttributes = jest.fn();
		const attributes = {
			dsgoLayout: { desktop: { gap: '2rem' }, tablet: { gap: '1rem' } },
		};
		render(
			<LayoutControls
				name="designsetgo/section"
				attributes={attributes}
				setAttributes={setAttributes}
				clientId="b"
			/>
		);
		fireEvent.change(screen.getByLabelText('Viewport'), {
			target: { value: 'tablet' },
		});
		expect(screen.getByLabelText('Gap')).toHaveValue('1rem');
		fireEvent.click(screen.getByRole('button', { name: 'Reset Gap' }));
		expect(setAttributes).toHaveBeenLastCalledWith({
			dsgoLayout: { desktop: { gap: '2rem' } },
		});
	});
});
