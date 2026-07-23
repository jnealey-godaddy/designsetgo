/**
 * Admin AnimationsPanel — per-block-type defaults repeater.
 */
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@wordpress/i18n', () => ({ __: (t) => t }));

jest.mock('@wordpress/components', () => {
	return {
		Card: ({ children }) => <div>{children}</div>,
		CardHeader: ({ children }) => <div>{children}</div>,
		CardBody: ({ children }) => <div>{children}</div>,
		ToggleControl: ({ label, checked, onChange }) => (
			<label>
				{label}
				<input
					type="checkbox"
					checked={!!checked}
					onChange={(e) => onChange(e.target.checked)}
				/>
			</label>
		),
		RangeControl: ({ label }) => <label>{label}</label>,
		SelectControl: ({ label, value, options = [], onChange }) => (
			<label>
				{label}
				<select
					value={value}
					onChange={(e) => onChange(e.target.value)}
				>
					{options.map((o) => (
						<option key={o.value} value={o.value}>
							{o.label}
						</option>
					))}
				</select>
			</label>
		),
		TextControl: ({ label, value, onChange }) => (
			<label>
				{label}
				<input
					value={value || ''}
					onChange={(e) => onChange(e.target.value)}
				/>
			</label>
		),
		Button: ({ children, onClick }) => (
			<button onClick={onClick}>{children}</button>
		),
	};
});

import AnimationsPanel from '../../../src/admin/components/settings-panels/AnimationsPanel';

describe('Admin AnimationsPanel — block-type defaults', () => {
	it('shows the master toggle and adds a row when enabled', () => {
		const updateSetting = jest.fn();
		const settings = {
			animations: {
				enable_animations: true,
				block_animations_enabled: true,
				block_animations: [],
			},
		};

		render(
			<AnimationsPanel
				settings={settings}
				updateSetting={updateSetting}
			/>
		);

		fireEvent.click(
			screen.getByRole('button', { name: /Add block type/i })
		);

		expect(updateSetting).toHaveBeenCalledWith(
			'animations',
			'block_animations',
			[expect.objectContaining({ block: '', entrance: 'fadeInUp' })]
		);
	});
});
