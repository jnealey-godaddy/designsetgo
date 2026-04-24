import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

// ─── WordPress module mocks ───────────────────────────────────────────────────

jest.mock('@wordpress/i18n', () => ({
	__: (text) => text,
	sprintf: (fmt, ...args) => {
		let i = 0;
		return fmt.replace(/%[sd]/g, () => String(args[i++] ?? ''));
	},
}));

// useSelect is called twice per render: once in TaxQueryBuilder (getTaxonomies)
// and once in TermPicker (getEntityRecords). We invoke the selector callback
// with a mock select() so the actual component logic runs unchanged.
jest.mock('@wordpress/data', () => ({
	useSelect: (cb) =>
		cb((storeName) => {
			if (storeName !== 'core') {
				return {};
			}
			return {
				getTaxonomies: () => [
					{
						slug: 'category',
						labels: { singular_name: 'Category' },
						types: ['post'],
					},
					{
						slug: 'post_tag',
						labels: { singular_name: 'Tag' },
						types: ['post'],
					},
				],
				getEntityRecords: (_kind, _taxonomy) => [
					{ id: 1, name: 'News' },
					{ id: 2, name: 'Sports' },
				],
			};
		}),
}));

jest.mock('@wordpress/core-data', () => ({ store: 'core' }));

jest.mock('@wordpress/block-editor', () => ({
	useBlockProps: () => ({}),
	useInnerBlocksProps: (p = {}) => ({ ...p, children: null }),
	InspectorControls: ({ children }) => <div>{children}</div>,
	store: 'core/block-editor',
}));

jest.mock('@wordpress/blocks', () => ({
	createBlocksFromInnerBlocksTemplate: jest.fn((t) => t),
}));

jest.mock('@wordpress/components', () => {
	const SelectControl = ({ label, value, options, onChange }) => (
		<label>
			{label}
			<select
				value={value}
				onChange={(e) => onChange(e.target.value)}
				aria-label={label}
			>
				{(options || []).map((opt) => (
					<option key={opt.value} value={opt.value}>
						{opt.label}
					</option>
				))}
			</select>
		</label>
	);

	const FormTokenField = ({ label, value, suggestions, onChange }) => (
		<label>
			{label}
			<input
				type="text"
				aria-label={label}
				value={(value || []).join(', ')}
				onChange={(e) =>
					onChange(e.target.value.split(', ').filter(Boolean))
				}
				list={`${label}-suggestions`}
			/>
			<datalist id={`${label}-suggestions`}>
				{(suggestions || []).map((s) => (
					<option key={s} value={s} />
				))}
			</datalist>
		</label>
	);

	const Button = ({
		children,
		onClick,
		disabled,
		'aria-label': ariaLabel,
		isDestructive,
		variant,
	}) => (
		<button
			type="button"
			onClick={onClick}
			disabled={disabled}
			aria-label={ariaLabel}
			data-destructive={isDestructive ? 'true' : undefined}
			data-variant={variant}
		>
			{children}
		</button>
	);

	// ToolsPanelItem: isShownByDefault=false items are hidden.
	const ToolsPanelItem = ({ children, isShownByDefault }) =>
		isShownByDefault !== false ? <>{children}</> : null;

	const ToolsPanel = ({ children, label }) => (
		<fieldset aria-label={label}>{children}</fieldset>
	);

	const VStack = ({ children }) => <div>{children}</div>;
	const HStack = ({ children }) => <div>{children}</div>;

	const ToggleControl = ({ label, checked, onChange }) => (
		<label>
			{label}
			<input
				type="checkbox"
				aria-label={label}
				checked={checked}
				onChange={(e) => onChange(e.target.checked)}
			/>
		</label>
	);

	return {
		SelectControl,
		FormTokenField,
		Button,
		ToggleControl,
		__experimentalToolsPanel: ToolsPanel,
		__experimentalToolsPanelItem: ToolsPanelItem,
		__experimentalHStack: HStack,
		__experimentalVStack: VStack,
	};
});

jest.mock('@wordpress/element', () => jest.requireActual('@wordpress/element'));

// ─── Component under test ─────────────────────────────────────────────────────
import TaxQueryBuilder from '../../../../src/blocks/query/components/TaxQueryBuilder';

// ─── Helpers ─────────────────────────────────────────────────────────────────

const DEFAULT_ATTRIBUTES = {
	postType: 'post',
	taxQuery: { relation: 'AND', clauses: [] },
};

function renderWith(attributeOverrides = {}) {
	const setAttributes = jest.fn();
	const utils = render(
		<TaxQueryBuilder
			attributes={{ ...DEFAULT_ATTRIBUTES, ...attributeOverrides }}
			setAttributes={setAttributes}
			clientId="test-client"
		/>
	);
	return { ...utils, setAttributes };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('TaxQueryBuilder', () => {
	it('renders with no clauses — shows + Clause and + Group buttons', () => {
		renderWith();
		expect(
			screen.getByRole('button', { name: /add clause/i })
		).toBeInTheDocument();
		expect(
			screen.getByRole('button', { name: /add group/i })
		).toBeInTheDocument();
	});

	it('+ Clause button is enabled when relevant taxonomies exist', () => {
		renderWith();
		const addBtn = screen.getByRole('button', { name: /add clause/i });
		expect(addBtn).not.toBeDisabled();
	});

	it('renders clause row with taxonomy selector and Remove button when clauses > 0', () => {
		renderWith({
			taxQuery: {
				relation: 'AND',
				clauses: [{ taxonomy: 'category', terms: [], operator: 'IN' }],
			},
		});
		expect(screen.getByLabelText(/^taxonomy$/i)).toBeInTheDocument();
		expect(
			screen.getByRole('button', { name: /remove taxonomy filter/i })
		).toBeInTheDocument();
	});

	it('renders Terms picker for the selected taxonomy', () => {
		renderWith({
			taxQuery: {
				relation: 'AND',
				clauses: [{ taxonomy: 'category', terms: [], operator: 'IN' }],
			},
		});
		expect(screen.getByLabelText(/^terms$/i)).toBeInTheDocument();
	});

	it('renders Match selector only when more than 1 clause is present', () => {
		// 0 clauses — no match selector.
		const { rerender } = renderWith();
		expect(screen.queryByLabelText(/^match$/i)).not.toBeInTheDocument();

		// 1 clause — still no match selector.
		rerender(
			<TaxQueryBuilder
				attributes={{
					...DEFAULT_ATTRIBUTES,
					taxQuery: {
						relation: 'AND',
						clauses: [
							{ taxonomy: 'category', terms: [], operator: 'IN' },
						],
					},
				}}
				setAttributes={jest.fn()}
				clientId="test-client"
			/>
		);
		expect(screen.queryByLabelText(/^match$/i)).not.toBeInTheDocument();

		// 2 clauses — match selector appears.
		rerender(
			<TaxQueryBuilder
				attributes={{
					...DEFAULT_ATTRIBUTES,
					taxQuery: {
						relation: 'AND',
						clauses: [
							{ taxonomy: 'category', terms: [], operator: 'IN' },
							{ taxonomy: 'post_tag', terms: [], operator: 'IN' },
						],
					},
				}}
				setAttributes={jest.fn()}
				clientId="test-client"
			/>
		);
		expect(screen.getByLabelText(/^match$/i)).toBeInTheDocument();
	});

	it('addClause seeds include_children: true on the new clause', () => {
		const { setAttributes } = renderWith();
		const addBtn = screen.getByRole('button', { name: /add clause/i });
		fireEvent.click(addBtn);
		expect(setAttributes).toHaveBeenCalledTimes(1);
		const call = setAttributes.mock.calls[0][0];
		const newClause = call.taxQuery.clauses[0];
		expect(newClause).toHaveProperty('include_children', true);
	});

	it('renders include_children toggle checked by default for existing clause', () => {
		renderWith({
			taxQuery: {
				relation: 'AND',
				clauses: [{ taxonomy: 'category', terms: [], operator: 'IN' }],
			},
		});
		const toggle = screen.getByLabelText(/include child terms/i);
		expect(toggle).toBeInTheDocument();
		expect(toggle).toBeChecked();
	});

	it('renders include_children toggle unchecked when set to false', () => {
		renderWith({
			taxQuery: {
				relation: 'AND',
				clauses: [
					{
						taxonomy: 'category',
						terms: [],
						operator: 'IN',
						include_children: false,
					},
				],
			},
		});
		const toggle = screen.getByLabelText(/include child terms/i);
		expect(toggle).not.toBeChecked();
	});

	it('adds a nested group when clicking + Group', () => {
		const setAttributes = jest.fn();
		const { getByRole } = render(
			<TaxQueryBuilder
				attributes={{
					taxQuery: { relation: 'AND', clauses: [] },
					postType: 'post',
				}}
				setAttributes={setAttributes}
				clientId="test"
			/>
		);
		fireEvent.click(getByRole('button', { name: /add group/i }));
		const call = setAttributes.mock.calls[0][0];
		expect(call.taxQuery.clauses[0]).toHaveProperty('clauses');
		expect(call.taxQuery.clauses[0].relation).toBe('AND');
	});
});
