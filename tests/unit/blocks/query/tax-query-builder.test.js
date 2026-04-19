import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

// ─── WordPress module mocks ───────────────────────────────────────────────────

jest.mock('@wordpress/i18n', () => ({
	__: (text) => text,
}));

// useSelect is called twice per render: once in TaxQueryBuilder (getTaxonomies)
// and once in TermPicker (getEntityRecords). We invoke the selector callback
// with a mock select() so the actual component logic runs unchanged.
jest.mock('@wordpress/data', () => ({
	useSelect: (cb) =>
		cb((storeName) => {
			if (storeName !== 'core') return {};
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
				onChange={(e) => onChange(e.target.value.split(', ').filter(Boolean))}
				list={`${label}-suggestions`}
			/>
			<datalist id={`${label}-suggestions`}>
				{(suggestions || []).map((s) => (
					<option key={s} value={s} />
				))}
			</datalist>
		</label>
	);

	const Button = ({ children, onClick, disabled, 'aria-label': ariaLabel, isDestructive, variant }) => (
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

	return {
		SelectControl,
		FormTokenField,
		Button,
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
	it('renders with no clauses — shows Add button', () => {
		renderWith();
		expect(
			screen.getByRole('button', { name: /add taxonomy filter/i })
		).toBeInTheDocument();
	});

	it('Add button is enabled when relevant taxonomies exist', () => {
		renderWith();
		const addBtn = screen.getByRole('button', { name: /add taxonomy filter/i });
		expect(addBtn).not.toBeDisabled();
	});

	it('Add button is disabled when no relevant taxonomies for postType', () => {
		// 'event' is not in any taxonomy's types list returned by our mock.
		renderWith({ postType: 'event' });
		const addBtn = screen.getByRole('button', { name: /add taxonomy filter/i });
		expect(addBtn).toBeDisabled();
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

	it('renders Relation selector only when more than 1 clause is present', () => {
		// 0 clauses — no relation selector.
		const { rerender } = renderWith();
		expect(screen.queryByLabelText(/relation/i)).not.toBeInTheDocument();

		// 1 clause — still no relation selector.
		rerender(
			<TaxQueryBuilder
				attributes={{
					...DEFAULT_ATTRIBUTES,
					taxQuery: {
						relation: 'AND',
						clauses: [{ taxonomy: 'category', terms: [], operator: 'IN' }],
					},
				}}
				setAttributes={jest.fn()}
				clientId="test-client"
			/>
		);
		expect(screen.queryByLabelText(/relation/i)).not.toBeInTheDocument();

		// 2 clauses — relation selector appears.
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
		expect(screen.getByLabelText(/relation/i)).toBeInTheDocument();
	});
});
