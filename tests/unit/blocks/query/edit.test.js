import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

// ─── WordPress module mocks ───────────────────────────────────────────────────
// All mocks must be declared before the component import so Jest hoists them.

jest.mock('@wordpress/i18n', () => ({
	__: (text) => text,
	_n: (singular, plural, count) => (count === 1 ? singular : plural),
	sprintf: (template, ...args) => {
		let result = template;
		args.forEach((arg) => {
			result = result.replace(/%d/, String(arg));
		});
		return result;
	},
}));

// Mock @wordpress/api-fetch — useQueryPreview calls this to fetch totalItems.
// Use a never-resolving Promise so the async state update doesn't fire during
// the synchronous render phase and trigger "not wrapped in act()" warnings.
jest.mock('@wordpress/api-fetch', () => jest.fn(() => new Promise(() => {})));

// Mock @wordpress/data — useSelect invokes the callback with a mock select()
// so QuerySourcePanel, TaxQueryBuilder, TermPicker, and hasInnerBlocks all
// get correct data. The block-editor store returns a stub block with no
// innerBlocks so the default template seeds.
jest.mock('@wordpress/data', () => ({
	useSelect: (cb) =>
		cb((storeName) => {
			if (storeName === 'core/block-editor') {
				// Non-empty innerBlocks so QueryEdit renders the Inspector
				// instead of the first-insert placeholder (gated by hasInnerBlocks).
				const stubChild = {
					name: 'designsetgo/query-results',
					innerBlocks: [],
					attributes: {},
				};
				return {
					getBlock: () => ({ innerBlocks: [stubChild] }),
					getBlocks: () => [stubChild],
					getBlockParents: () => [],
				};
			}
			// TemplateIO calls getCurrentPostId() on the editor store.
			if (storeName === 'core/editor') {
				return { getCurrentPostId: () => 1 };
			}
			if (storeName !== 'core') {
				return {};
			}
			return {
				getPostTypes: () => [
					{
						slug: 'post',
						labels: { singular_name: 'Post' },
						viewable: true,
					},
					{
						slug: 'page',
						labels: { singular_name: 'Page' },
						viewable: true,
					},
				],
				getTaxonomies: () => [
					{
						slug: 'category',
						labels: { singular_name: 'Category' },
						types: ['post'],
					},
				],
				getEntityRecords: () => [],
			};
		}),
	useDispatch: () => ({
		insertBlocks: jest.fn(),
		replaceBlocks: jest.fn(),
		createErrorNotice: jest.fn(),
		createSuccessNotice: jest.fn(),
	}),
}));

// Stub @wordpress/core-data — store token + useEntityRecords for EditorPreviewList.
jest.mock('@wordpress/core-data', () => ({
	store: 'core',
	useEntityRecords: () => ({ records: [], hasResolved: true }),
}));

// Block-editor mock — useBlockProps / useInnerBlocksProps / InspectorControls.
jest.mock('@wordpress/block-editor', () => ({
	useBlockProps: () => ({ className: 'wp-block-designsetgo-query' }),
	useInnerBlocksProps: (p = {}) => ({ ...p, children: null }),
	InspectorControls: ({ children }) => (
		<div data-testid="inspector">{children}</div>
	),
	InnerBlocks: () => <div data-testid="inner-blocks" />,
	BlockPreview: () => <div data-testid="block-preview" />,
	BlockContextProvider: ({ children }) => <>{children}</>,
	store: 'core/block-editor',
}));

// @wordpress/blocks stub — avoids pulling in @wordpress/rich-text store.
jest.mock('@wordpress/blocks', () => ({
	createBlocksFromInnerBlocksTemplate: jest.fn((t) => t),
	createBlock: jest.fn((name, attrs) => ({ name, attributes: attrs || {} })),
}));

// Minimal @wordpress/components stubs — covers QuerySourcePanel, TaxQueryBuilder,
// MetaQueryBuilder, AdvancedPanel, and ResultCountBadge needs.
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

	const RangeControl = ({ label, value, onChange }) => (
		<label>
			{label}
			<input
				type="range"
				value={value}
				onChange={(e) => onChange(Number(e.target.value))}
				aria-label={label}
			/>
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

	const NumberControl = ({ label, value, onChange }) => (
		<label>
			{label}
			<input
				type="number"
				value={value}
				onChange={(e) => onChange(e.target.value)}
				aria-label={label}
			/>
		</label>
	);

	const FormTokenField = ({
		label,
		value,
		suggestions: _suggestions,
		onChange,
	}) => (
		<label>
			{label}
			<input
				type="text"
				aria-label={label}
				value={(value || []).join(', ')}
				onChange={(e) =>
					onChange(e.target.value.split(', ').filter(Boolean))
				}
			/>
		</label>
	);

	// ToolsPanelItem: isShownByDefault=false items are hidden (like the real WP component).
	const ToolsPanelItem = ({ children, isShownByDefault }) =>
		isShownByDefault !== false ? <>{children}</> : null;

	const ToolsPanel = ({ children, label }) => (
		<fieldset aria-label={label}>{children}</fieldset>
	);

	const Placeholder = ({ label, instructions }) => (
		<div data-testid="placeholder">
			<span>{label}</span>
			<span>{instructions}</span>
		</div>
	);

	const TextareaControl = ({ label, value, onChange }) => (
		<label>
			{label}
			<textarea
				value={value}
				onChange={(e) => onChange(e.target.value)}
				aria-label={label}
			/>
		</label>
	);

	const ToggleControl = ({ label, checked, onChange }) => (
		<label>
			{label}
			<input
				type="checkbox"
				checked={checked}
				onChange={(e) => onChange(e.target.checked)}
				aria-label={label}
			/>
		</label>
	);

	const VStack = ({ children }) => <div>{children}</div>;
	const HStack = ({ children }) => <div>{children}</div>;

	return {
		SelectControl,
		RangeControl,
		TextControl,
		TextareaControl,
		ToggleControl,
		FormTokenField,
		__experimentalNumberControl: NumberControl,
		__experimentalToolsPanel: ToolsPanel,
		__experimentalToolsPanelItem: ToolsPanelItem,
		__experimentalHStack: HStack,
		__experimentalVStack: VStack,
		Placeholder,
		Button: ({ children, onClick, disabled, 'aria-label': ariaLabel }) => (
			<button
				type="button"
				onClick={onClick}
				disabled={disabled}
				aria-label={ariaLabel}
			>
				{children}
			</button>
		),
		Icon: ({ icon }) => <span>{icon}</span>,
		Spinner: () => <span data-testid="spinner" />,
	};
});

// @wordpress/element — use real React so hooks work correctly.
jest.mock('@wordpress/element', () => jest.requireActual('@wordpress/element'));

// ─── Component under test ─────────────────────────────────────────────────────
import QueryEdit from '../../../../src/blocks/query/edit';

// ─── Helpers ─────────────────────────────────────────────────────────────────

const DEFAULT_ATTRIBUTES = {
	queryId: 'q-1',
	source: 'posts',
	postType: 'post',
	perPage: 6,
	offset: 0,
	orderBy: 'date',
	orderByMetaKey: '',
	order: 'DESC',
	taxQuery: { relation: 'AND', clauses: [] },
	metaQuery: { relation: 'AND', clauses: [] },
	search: '',
	bindSearchTo: '',
	excludeCurrent: false,
	ignoreSticky: true,
	manualIds: [],
	tagName: 'ul',
	itemTagName: 'li',
	relationshipField: '',
	relationshipFallback: 'empty',
	groupBy: null,
};

function renderWith(attributeOverrides = {}, propOverrides = {}) {
	return render(
		<QueryEdit
			attributes={{ ...DEFAULT_ATTRIBUTES, ...attributeOverrides }}
			setAttributes={jest.fn()}
			clientId="test-client"
			context={{}}
			{...propOverrides}
		/>
	);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('QueryEdit — Settings panel', () => {
	it('renders the inspector container', () => {
		renderWith();
		expect(screen.getByTestId('inspector')).toBeInTheDocument();
	});

	it('renders a Source selector', () => {
		renderWith();
		expect(screen.getByLabelText(/^source$/i)).toBeInTheDocument();
	});

	it('renders a Post type selector when source is posts', () => {
		renderWith({ source: 'posts' });
		expect(screen.getByLabelText(/post type/i)).toBeInTheDocument();
	});

	it('does not render Post type selector when source is users', () => {
		renderWith({ source: 'users' });
		expect(screen.queryByLabelText(/post type/i)).not.toBeInTheDocument();
	});

	it('renders the Items per page control', () => {
		renderWith();
		expect(screen.getByLabelText(/items per page/i)).toBeInTheDocument();
	});

	it('renders the relationship field input when source is relationship', () => {
		renderWith({ source: 'relationship' });
		expect(
			screen.getByLabelText(/relationship field/i)
		).toBeInTheDocument();
	});

	it('renders the fallback select when source is relationship', () => {
		renderWith({ source: 'relationship' });
		expect(
			screen.getByLabelText(/when no related items/i)
		).toBeInTheDocument();
	});

	it('does not render relationship field input when source is posts', () => {
		renderWith({ source: 'posts' });
		expect(
			screen.queryByLabelText(/relationship field/i)
		).not.toBeInTheDocument();
	});

	it('shows the meta-key input only when orderBy is meta_value', () => {
		// When orderBy is date, meta key item is absent (showMetaKey=false).
		const { rerender } = renderWith({ orderBy: 'date' });
		expect(screen.queryByLabelText(/meta key/i)).not.toBeInTheDocument();

		// After switching to meta_value, the item becomes visible (isShownByDefault).
		rerender(
			<QueryEdit
				attributes={{
					...DEFAULT_ATTRIBUTES,
					orderBy: 'meta_value',
				}}
				setAttributes={jest.fn()}
				clientId="c"
				context={{}}
			/>
		);
		expect(screen.getByLabelText(/meta key/i)).toBeInTheDocument();
	});
});

// v2.6: Group-by inspector moved to designsetgo/query-results (the child
// block that now owns presentation attrs). Tests for the new controls
// should live alongside that block's edit component.
