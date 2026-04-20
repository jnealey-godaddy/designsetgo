import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import VisibilityPanel from '../../../../src/extensions/visibility/VisibilityPanel';

jest.mock('@wordpress/i18n', () => ({ __: (t) => t, sprintf: (t, a) => String(t).replace('%s', a) }));
jest.mock('@wordpress/components', () => ({
	SelectControl: ({ label, value, onChange, options }) => (
		<label>{label}<select value={value} onChange={(e) => onChange(e.target.value)}>{(options || []).map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}</select></label>
	),
	TextControl: ({ label, value, onChange }) => (
		<label>{label}<input value={value || ''} onChange={(e) => onChange(e.target.value)} /></label>
	),
	Button: ({ children, onClick }) => <button type="button" onClick={onClick}>{children}</button>,
	__experimentalVStack: ({ children }) => <div>{children}</div>,
	__experimentalHStack: ({ children }) => <div>{children}</div>,
}));

describe('VisibilityPanel', () => {
	it('shows empty state when no rules exist', () => {
		render(<VisibilityPanel value={null} onChange={jest.fn()} />);
		expect(screen.getByText(/always visible/i)).toBeInTheDocument();
	});

	it('renders a row per rule', () => {
		const value = {
			operator: 'AND',
			rules: [{ type: 'meta', key: 'foo', op: 'equals', value: 'bar' }],
		};
		render(<VisibilityPanel value={value} onChange={jest.fn()} />);
		expect(screen.getByDisplayValue('foo')).toBeInTheDocument();
	});

	it('calls onChange with a new rule when Add clicked', () => {
		const onChange = jest.fn();
		render(<VisibilityPanel value={null} onChange={onChange} />);
		fireEvent.click(screen.getByText(/add rule/i));
		expect(onChange).toHaveBeenCalledWith(expect.objectContaining({
			operator: 'AND',
			rules: expect.arrayContaining([expect.objectContaining({ type: 'meta' })]),
		}));
	});
});
