import { render } from '@testing-library/react';
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

// Mock @wordpress/api-fetch — never-resolving to avoid async act() warnings.
jest.mock('@wordpress/api-fetch', () => jest.fn(() => new Promise(() => {})));

// Mock @wordpress/data — same as edit.test.js.
jest.mock('@wordpress/data', () => ({
	useSelect: (cb) =>
		cb((storeName) => {
			if (storeName === 'core/block-editor') {
				// v2.6: QueryResultsEdit walks up to find its parent
				// designsetgo/query block so it can read query attrs for the
				// preview REST call. Stub the parent lookup here; the current
				// block's getBlock returns a non-empty innerBlocks so the
				// preview path (not the placeholder) renders.
				const parentQuery = {
					name: 'designsetgo/query',
					attributes: {
						queryId: 'nested-1',
						source: 'posts',
						postType: 'post',
						perPage: 1,
						orderBy: 'date',
						order: 'DESC',
						taxQuery: { relation: 'AND', clauses: [] },
						metaQuery: { relation: 'AND', clauses: [] },
					},
				};
				return {
					getBlock: (clientId) =>
						clientId === 'parent-client-id'
							? parentQuery
							: {
									name: 'designsetgo/query-results',
									innerBlocks: [
										{
											name: 'core/paragraph',
											attributes: {},
										},
									],
								},
					getBlocks: () => [],
					getBlockParents: () => ['parent-client-id'],
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
				],
				getTaxonomies: () => [],
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

// Stub @wordpress/core-data — return a real record so BlockContextProvider fires.
jest.mock('@wordpress/core-data', () => ({
	store: 'core',
	useEntityRecords: () => ({
		records: [{ id: 42, type: 'post', meta: {} }],
		hasResolved: true,
	}),
}));

// Capture array — populated by the BlockContextProvider mock below.
// Must be declared here (module scope) so it is accessible inside the mock
// factory closure AND inside the test assertions.
const ctxCapture = [];

// Block-editor mock — capture BlockContextProvider values.
jest.mock('@wordpress/block-editor', () => ({
	useBlockProps: () => ({ className: 'wp-block-designsetgo-query' }),
	useInnerBlocksProps: (p = {}) => ({ ...p, children: null }),
	InspectorControls: ({ children }) => (
		<div data-testid="inspector">{children}</div>
	),
	InnerBlocks: () => <div data-testid="inner-blocks" />,
	BlockPreview: () => <div data-testid="block-preview" />,
	BlockContextProvider: ({ value, children }) => {
		ctxCapture.push(value);
		return <>{children}</>;
	},
	store: 'core/block-editor',
}));

// @wordpress/blocks stub.
jest.mock('@wordpress/blocks', () => ({
	createBlocksFromInnerBlocksTemplate: jest.fn((t) => t),
	createBlock: jest.fn((name, attrs) => ({ name, attributes: attrs || {} })),
	// useRenderedItems calls serialize() on innerBlocks for the REST payload.
	serialize: jest.fn(() => ''),
}));

// Minimal @wordpress/components stubs — same as edit.test.js.
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

	const PanelBody = ({ title, children }) => (
		<div data-testid="panel-body" aria-label={title}>
			{children}
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
		PanelBody,
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

// @wordpress/element — real React so hooks work correctly.
jest.mock('@wordpress/element', () => jest.requireActual('@wordpress/element'));

// ─── Component under test ─────────────────────────────────────────────────────
// v2.6: preview now lives in QueryResultsEdit (the inner grid child), not the
// outer QueryEdit container. Both render EditorPreviewList under the hood —
// the context-propagation assertions below target that component's behaviour.
import QueryResultsEdit from '../../../../src/blocks/query-results/edit';

// ─── Constants ────────────────────────────────────────────────────────────────

const DEFAULT_ATTRIBUTES = {
	queryId: 'nested-1',
	source: 'posts',
	postType: 'post',
	perPage: 1,
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
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('QueryResultsEdit nested preview', () => {
	beforeEach(() => {
		ctxCapture.length = 0;
	});

	it('provides parent context to BlockContextProvider when nested inside an outer Query', () => {
		render(
			<QueryResultsEdit
				attributes={DEFAULT_ATTRIBUTES}
				setAttributes={jest.fn()}
				clientId="cli-1"
				context={{
					'designsetgo/parentItem': { postId: 99, postType: 'post' },
				}}
			/>
		);
		// At least one BlockContextProvider must have received the outer parentItem.
		expect(
			ctxCapture.some((c) => c?.['designsetgo/parentItem']?.postId === 99)
		).toBe(true);
	});

	it('provides a self-referencing parentItem fallback for root-level Queries', () => {
		render(
			<QueryResultsEdit
				attributes={DEFAULT_ATTRIBUTES}
				setAttributes={jest.fn()}
				clientId="cli-2"
				context={{}}
			/>
		);
		// No outer parentItem — the provider should fall back to the current item
		// (id: 42, as returned by the useEntityRecords mock).
		expect(
			ctxCapture.some(
				(c) =>
					c?.['designsetgo/parentItem'] !== undefined &&
					c?.['designsetgo/parentItem']?.postId === 42
			)
		).toBe(true);
	});

	it('always provides designsetgo/itemIndex in BlockContextProvider', () => {
		render(
			<QueryResultsEdit
				attributes={DEFAULT_ATTRIBUTES}
				setAttributes={jest.fn()}
				clientId="cli-3"
				context={{}}
			/>
		);
		expect(
			ctxCapture.some(
				(c) => typeof c?.['designsetgo/itemIndex'] === 'number'
			)
		).toBe(true);
	});

	it('always provides designsetgo/itemMeta in BlockContextProvider', () => {
		render(
			<QueryResultsEdit
				attributes={DEFAULT_ATTRIBUTES}
				setAttributes={jest.fn()}
				clientId="cli-4"
				context={{}}
			/>
		);
		expect(
			ctxCapture.some(
				(c) =>
					c?.['designsetgo/itemMeta'] !== undefined &&
					typeof c?.['designsetgo/itemMeta'] === 'object'
			)
		).toBe(true);
	});

	it('provides designsetgo/isAuthenticated=true in BlockContextProvider', () => {
		render(
			<QueryResultsEdit
				attributes={DEFAULT_ATTRIBUTES}
				setAttributes={jest.fn()}
				clientId="cli-5"
				context={{}}
			/>
		);
		// Editor sessions are always authenticated; auth visibility rules should
		// never hide content from the admin in the editor preview.
		expect(
			ctxCapture.some((c) => c?.['designsetgo/isAuthenticated'] === true)
		).toBe(true);
	});
});
