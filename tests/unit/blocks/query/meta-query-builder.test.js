import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

// ─── WordPress module mocks ───────────────────────────────────────────────────

jest.mock('@wordpress/i18n', () => ({
	__: (text) => text,
}));

// MetaQueryBuilder has no coreStore lookups — useSelect is not used.
// Mock it as a no-op in case any transitive dependency references it.
jest.mock('@wordpress/data', () => ({
	useSelect: () => undefined,
	useDispatch: () => ({}),
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

	const TextControl = ({ label, value, onChange }) => (
		<label>
			{label}
			<input
				type="text"
				value={value}
				onChange={(e) => onChange(e.target.value)}
				aria-label={label}
			/>
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

	return {
		SelectControl,
		TextControl,
		Button,
		__experimentalToolsPanel: ToolsPanel,
		__experimentalToolsPanelItem: ToolsPanelItem,
		__experimentalHStack: HStack,
		__experimentalVStack: VStack,
	};
});

jest.mock('@wordpress/element', () => jest.requireActual('@wordpress/element'));

// ─── Component under test ─────────────────────────────────────────────────────
import MetaQueryBuilder from '../../../../src/blocks/query/components/MetaQueryBuilder';

// ─── Helpers ─────────────────────────────────────────────────────────────────

const DEFAULT_ATTRIBUTES = {
	metaQuery: { relation: 'AND', clauses: [] },
};

function renderWith(attributeOverrides = {}) {
	const setAttributes = jest.fn();
	const utils = render(
		<MetaQueryBuilder
			attributes={{ ...DEFAULT_ATTRIBUTES, ...attributeOverrides }}
			setAttributes={setAttributes}
			clientId="test-client"
		/>
	);
	return { ...utils, setAttributes };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('MetaQueryBuilder', () => {
	it('renders + Clause button when no clauses', () => {
		renderWith();
		expect(
			screen.getByRole('button', { name: /\+ clause/i })
		).toBeInTheDocument();
	});

	it('renders clause row with Key, Compare, Type, and Value controls when clauses > 0', () => {
		renderWith({
			metaQuery: {
				relation: 'AND',
				clauses: [
					{
						key: 'my_field',
						compare: '=',
						value: 'foo',
						type: 'CHAR',
					},
				],
			},
		});
		expect(screen.getByLabelText(/^key$/i)).toBeInTheDocument();
		expect(screen.getByLabelText(/^compare$/i)).toBeInTheDocument();
		expect(screen.getByLabelText(/^type$/i)).toBeInTheDocument();
		expect(screen.getByLabelText(/^value$/i)).toBeInTheDocument();
	});

	it('hides the Value field when compare is EXISTS', () => {
		renderWith({
			metaQuery: {
				relation: 'AND',
				clauses: [
					{
						key: 'my_field',
						compare: 'EXISTS',
						value: '',
						type: 'CHAR',
					},
				],
			},
		});
		expect(screen.queryByLabelText(/^value$/i)).not.toBeInTheDocument();
	});

	it('hides the Value field when compare is NOT EXISTS', () => {
		renderWith({
			metaQuery: {
				relation: 'AND',
				clauses: [
					{
						key: 'my_field',
						compare: 'NOT EXISTS',
						value: '',
						type: 'CHAR',
					},
				],
			},
		});
		expect(screen.queryByLabelText(/^value$/i)).not.toBeInTheDocument();
	});

	it('renders Remove button for each clause', () => {
		renderWith({
			metaQuery: {
				relation: 'AND',
				clauses: [
					{
						key: 'field_a',
						compare: '=',
						value: '1',
						type: 'NUMERIC',
					},
					{ key: 'field_b', compare: '!=', value: '2', type: 'CHAR' },
				],
			},
		});
		const removeButtons = screen.getAllByRole('button', {
			name: /^remove$/i,
		});
		expect(removeButtons).toHaveLength(2);
	});

	it('renders Match selector only when more than 1 clause is present', () => {
		// 0 clauses — no match selector.
		const { rerender } = renderWith();
		expect(screen.queryByLabelText(/match/i)).not.toBeInTheDocument();

		// 1 clause — no match selector.
		rerender(
			<MetaQueryBuilder
				attributes={{
					metaQuery: {
						relation: 'AND',
						clauses: [
							{
								key: 'a',
								compare: '=',
								value: '1',
								type: 'CHAR',
							},
						],
					},
				}}
				setAttributes={jest.fn()}
				clientId="test-client"
			/>
		);
		expect(screen.queryByLabelText(/match/i)).not.toBeInTheDocument();

		// 2 clauses — match selector appears.
		rerender(
			<MetaQueryBuilder
				attributes={{
					metaQuery: {
						relation: 'OR',
						clauses: [
							{
								key: 'a',
								compare: '=',
								value: '1',
								type: 'CHAR',
							},
							{
								key: 'b',
								compare: '!=',
								value: '2',
								type: 'CHAR',
							},
						],
					},
				}}
				setAttributes={jest.fn()}
				clientId="test-client"
			/>
		);
		expect(screen.getByLabelText(/match/i)).toBeInTheDocument();
	});

	it('adds a nested group when clicking + Group', () => {
		const setAttributes = jest.fn();
		const { getByRole } = render(
			<MetaQueryBuilder
				attributes={{ metaQuery: { relation: 'AND', clauses: [] } }}
				setAttributes={setAttributes}
				clientId="test"
			/>
		);
		fireEvent.click(getByRole('button', { name: /\+ group/i }));
		const call = setAttributes.mock.calls[0][0];
		expect(call.metaQuery.clauses[0]).toHaveProperty('clauses');
	});
});
