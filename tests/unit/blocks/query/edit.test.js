import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

// ─── WordPress module mocks ───────────────────────────────────────────────────
// All mocks must be declared before the component import so Jest hoists them.

jest.mock('@wordpress/i18n', () => ({
	__: (text) => text,
}));

// Mock @wordpress/data — only useSelect / useDispatch needed.
jest.mock('@wordpress/data', () => ({
	useSelect: () => [
		{ slug: 'post', labels: { singular_name: 'Post' }, viewable: true },
		{ slug: 'page', labels: { singular_name: 'Page' }, viewable: true },
	],
	useDispatch: () => ({}),
}));

// Stub @wordpress/core-data — only the store token is needed.
jest.mock('@wordpress/core-data', () => ({
	store: 'core',
}));

// Block-editor mock — useBlockProps / useInnerBlocksProps / InspectorControls.
jest.mock('@wordpress/block-editor', () => ({
	useBlockProps: () => ({ className: 'wp-block-designsetgo-query' }),
	useInnerBlocksProps: (p = {}) => ({ ...p, children: null }),
	InspectorControls: ({ children }) => <div data-testid="inspector">{children}</div>,
	InnerBlocks: () => <div data-testid="inner-blocks" />,
	store: 'core/block-editor',
}));

// @wordpress/blocks stub — avoids pulling in @wordpress/rich-text store.
jest.mock('@wordpress/blocks', () => ({
	createBlocksFromInnerBlocksTemplate: jest.fn((t) => t),
}));

// Minimal @wordpress/components stubs — only what QuerySourcePanel + Placeholder need.
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

	return {
		SelectControl,
		RangeControl,
		TextControl,
		__experimentalNumberControl: NumberControl,
		__experimentalToolsPanel: ToolsPanel,
		__experimentalToolsPanelItem: ToolsPanelItem,
		Placeholder,
		Button: ({ children, onClick }) => (
			<button type="button" onClick={onClick}>
				{children}
			</button>
		),
		Icon: ({ icon }) => <span>{icon}</span>,
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
