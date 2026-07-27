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
// token field has suggestions without hitting a real endpoint.
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
	// Stands in for FormTokenField: renders the current tokens and suggestions,
	// and exposes adding one through a text input so tests can drive the same
	// validate/onChange contract the real control uses.
	const TokenField = ({
		label,
		value = [],
		suggestions = [],
		onChange,
		__experimentalValidateInput,
	}) => (
		<div>
			<span>{label}</span>
			<ul data-testid="tokens">
				{value.map((token) => (
					<li key={token}>{token}</li>
				))}
			</ul>
			<datalist data-testid="suggestions">
				{suggestions.map((s) => (
					<option key={s} value={s} />
				))}
			</datalist>
			<input
				aria-label="add-token"
				onChange={(e) => {
					const token = e.target.value;
					if (
						__experimentalValidateInput &&
						!__experimentalValidateInput(token)
					) {
						return;
					}
					onChange([...value, token]);
				}}
			/>
		</div>
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
		FormTokenField: TokenField,
		Button: ({ children, label, onClick }) => (
			<button aria-label={label} onClick={onClick}>
				{children}
			</button>
		),
	};
});

import AnimationsPanel from '../../../src/admin/components/settings-panels/AnimationsPanel';

const settingsWith = (rows) => ({
	animations: {
		enable_animations: true,
		block_animations_enabled: true,
		block_animations: rows,
	},
});

const ROW = {
	blocks: ['core/button'],
	entrance: 'fadeInUp',
	trigger: 'scroll',
	duration: 600,
};

describe('Admin AnimationsPanel — block-type defaults', () => {
	it('shows the master toggle and adds a row when enabled', async () => {
		const updateSetting = jest.fn();

		render(
			<AnimationsPanel
				settings={settingsWith([])}
				updateSetting={updateSetting}
			/>
		);

		fireEvent.click(
			screen.getByRole('button', { name: /Add animation rule/i })
		);

		expect(updateSetting).toHaveBeenCalledWith(
			'animations',
			'block_animations',
			[expect.objectContaining({ blocks: [], entrance: 'fadeInUp' })]
		);

		// Flush the on-mount block-types fetch so its state update settles
		// inside act() rather than warning after the test.
		await screen.findByRole('button', { name: /Add animation rule/i });
	});

	it('renders each targeted block as a token with a friendly label', async () => {
		render(
			<AnimationsPanel
				settings={settingsWith([
					{ ...ROW, blocks: ['core/button', 'core/image'] },
				])}
				updateSetting={jest.fn()}
			/>
		);

		// Once block types resolve, stored names display as "Title — name".
		expect(
			await screen.findByText('Button — core/button')
		).toBeInTheDocument();
		expect(screen.getByText('Image — core/image')).toBeInTheDocument();

		// Namespace wildcards are offered as suggestions alongside blocks.
		const options = [
			...screen.getByTestId('suggestions').querySelectorAll('option'),
		].map((o) => o.value);
		expect(options).toEqual(
			expect.arrayContaining([
				'core/* — all core blocks',
				'Button — core/button',
			])
		);
	});

	it('stores the canonical block name when a labelled suggestion is picked', async () => {
		const updateSetting = jest.fn();

		render(
			<AnimationsPanel
				settings={settingsWith([ROW])}
				updateSetting={updateSetting}
			/>
		);
		await screen.findByText('Button — core/button');

		fireEvent.change(screen.getByLabelText('add-token'), {
			target: { value: 'Image — core/image' },
		});

		expect(updateSetting).toHaveBeenCalledWith(
			'animations',
			'block_animations',
			[
				expect.objectContaining({
					blocks: ['core/button', 'core/image'],
				}),
			]
		);
	});

	it('refuses a token that is not a valid block name', async () => {
		const updateSetting = jest.fn();

		render(
			<AnimationsPanel
				settings={settingsWith([ROW])}
				updateSetting={updateSetting}
			/>
		);
		await screen.findByText('Button — core/button');

		// Mirrors the server's regex — a name the sanitizer would drop never
		// becomes a token in the first place.
		fireEvent.change(screen.getByLabelText('add-token'), {
			target: { value: 'not a block name' },
		});
		expect(updateSetting).not.toHaveBeenCalled();

		// A hand-typed, well-formed name is accepted even without a suggestion.
		fireEvent.change(screen.getByLabelText('add-token'), {
			target: { value: 'acme/widget' },
		});
		expect(updateSetting).toHaveBeenCalledWith(
			'animations',
			'block_animations',
			[
				expect.objectContaining({
					blocks: ['core/button', 'acme/widget'],
				}),
			]
		);
	});

	it('hides blocks another rule already claims from a row’s suggestions', async () => {
		render(
			<AnimationsPanel
				settings={settingsWith([
					{ ...ROW, blocks: ['core/button'] },
					{ ...ROW, blocks: ['core/image'] },
				])}
				updateSetting={jest.fn()}
			/>
		);
		await screen.findByText('Button — core/button');

		const optionsFor = (rowIndex) =>
			[
				...screen
					.getAllByTestId('suggestions')
					[rowIndex].querySelectorAll('option'),
			].map((o) => o.value);

		// Each row may still offer its own targets, but never the other row's —
		// the server would resolve that double claim by silently stripping it.
		expect(optionsFor(0)).not.toContain('Image — core/image');
		expect(optionsFor(1)).not.toContain('Button — core/button');

		// A wildcard is a distinct key server-side (exact beats wildcard), so
		// claiming `core/button` must not withdraw `core/*` from either row.
		expect(optionsFor(0)).toContain('core/* — all core blocks');
		expect(optionsFor(1)).toContain('core/* — all core blocks');
	});

	it('refuses a hand-typed block name that another rule already claims', async () => {
		const updateSetting = jest.fn();

		render(
			<AnimationsPanel
				settings={settingsWith([
					{ ...ROW, blocks: ['core/button'] },
					{ ...ROW, blocks: ['core/image'] },
				])}
				updateSetting={updateSetting}
			/>
		);
		await screen.findByText('Button — core/button');

		// Filtering suggestions alone would leave the name typeable, so the
		// per-row validator refuses it too.
		fireEvent.change(screen.getAllByLabelText('add-token')[0], {
			target: { value: 'core/image' },
		});
		expect(updateSetting).not.toHaveBeenCalled();

		// An unclaimed name still goes through.
		fireEvent.change(screen.getAllByLabelText('add-token')[0], {
			target: { value: 'acme/widget' },
		});
		expect(updateSetting).toHaveBeenCalledWith(
			'animations',
			'block_animations',
			[
				expect.objectContaining({
					blocks: ['core/button', 'acme/widget'],
				}),
				expect.objectContaining({ blocks: ['core/image'] }),
			]
		);
	});

	it('warns which targets a row loses when the stored list already conflicts', async () => {
		// A list saved through the REST route / abilities API can arrive with a
		// double claim this UI never allowed — the sanitizer will hand the block
		// to the last rule, so say so rather than letting it vanish on reload.
		render(
			<AnimationsPanel
				settings={settingsWith([
					{ ...ROW, blocks: ['core/button', 'core/image'] },
					{ ...ROW, blocks: ['core/image'] },
				])}
				updateSetting={jest.fn()}
			/>
		);

		expect(
			await screen.findByText(/Also claimed by a later rule/i)
		).toHaveTextContent('core/image');

		// Only the losing (earlier) row is warned about, not the winner.
		expect(
			screen.getAllByText(/Also claimed by a later rule/i)
		).toHaveLength(1);
	});
});
