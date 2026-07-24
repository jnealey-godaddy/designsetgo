/**
 * Admin AnimationsPanel — per-block-type defaults repeater.
 */
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@wordpress/i18n', () => ({
	__: (t) => t,
	sprintf: (template, ...args) => {
		let i = 0;
		return template.replace(/%s/g, () => args[i++]);
	},
}));

// The picker fetches registered block types on mount; resolve with two so the
// combobox has options without hitting a real endpoint.
jest.mock('@wordpress/api-fetch', () => ({
	__esModule: true,
	default: jest.fn(() =>
		Promise.resolve([
			{ name: 'core/button', title: 'Button' },
			{ name: 'core/image', title: 'Image' },
		])
	),
}));

jest.mock('@wordpress/components', () => {
	const Picker = ({ label, value, options = [], onChange }) => (
		<label>
			{label}
			<select
				data-testid="block-picker"
				value={value || ''}
				onChange={(e) => onChange(e.target.value)}
			>
				{options.map((o) => (
					<option key={o.value} value={o.value}>
						{o.label}
					</option>
				))}
			</select>
		</label>
	);
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
		ComboboxControl: Picker,
		TextControl: ({ label, value, onChange }) => (
			<label>
				{label}
				<input
					value={value || ''}
					onChange={(e) => onChange(e.target.value)}
				/>
			</label>
		),
		Button: ({ children, label, onClick }) => (
			<button aria-label={label} onClick={onClick}>
				{children}
			</button>
		),
	};
});

import AnimationsPanel from '../../../src/admin/components/settings-panels/AnimationsPanel';

describe('Admin AnimationsPanel — block-type defaults', () => {
	it('shows the master toggle and adds a row when enabled', async () => {
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

		// Flush the on-mount block-types fetch so its state update settles
		// inside act() rather than warning after the test.
		await screen.findByRole('button', { name: /Add block type/i });
	});

	it('renders a searchable block-type picker for each row', async () => {
		const settings = {
			animations: {
				enable_animations: true,
				block_animations_enabled: true,
				block_animations: [
					{
						block: 'core/button',
						entrance: 'fadeInUp',
						trigger: 'scroll',
						duration: 600,
					},
				],
			},
		};

		render(
			<AnimationsPanel settings={settings} updateSetting={jest.fn()} />
		);

		// The picker renders as a combobox (not the free-text fallback), and
		// once block types resolve it offers the fetched blocks + wildcards.
		const picker = await screen.findByTestId('block-picker');
		expect(picker).toBeInTheDocument();
		expect(picker.value).toBe('core/button');
		expect(
			await screen.findByRole('option', { name: /Button — core\/button/ })
		).toBeInTheDocument();
		expect(
			screen.getByRole('option', { name: /core\/\* — all core blocks/ })
		).toBeInTheDocument();
	});
});
