import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

// ─── WordPress module mocks ───────────────────────────────────────────────────

jest.mock('@wordpress/i18n', () => ({
	__: (text) => text,
}));

// AdvancedPanel does NOT call useSelect — mock as a simple no-op.
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

	// ToolsPanelItem: isShownByDefault=false items are hidden (like the real WP component).
	const ToolsPanelItem = ({ children, isShownByDefault }) =>
		isShownByDefault !== false ? <>{children}</> : null;

	const ToolsPanel = ({ children, label }) => (
		<fieldset aria-label={label}>{children}</fieldset>
	);

	return {
		SelectControl,
		TextControl,
		TextareaControl,
		ToggleControl,
		__experimentalToolsPanel: ToolsPanel,
		__experimentalToolsPanelItem: ToolsPanelItem,
	};
});

jest.mock('@wordpress/element', () => jest.requireActual('@wordpress/element'));

// ─── Component under test ─────────────────────────────────────────────────────
import AdvancedPanel from '../../../../src/blocks/query/components/AdvancedPanel';

// ─── Helpers ─────────────────────────────────────────────────────────────────

const DEFAULT_ATTRIBUTES = {
	source: 'posts',
	search: '',
	bindSearchTo: '',
	excludeCurrent: false,
	ignoreSticky: true,
	manualIds: [],
	tagName: 'ul',
	itemTagName: 'li',
};

function renderWith(attributeOverrides = {}) {
	const setAttributes = jest.fn();
	const utils = render(
		<AdvancedPanel
			attributes={{ ...DEFAULT_ATTRIBUTES, ...attributeOverrides }}
			setAttributes={setAttributes}
			clientId="test-client"
		/>
	);
	return { ...utils, setAttributes };
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('AdvancedPanel', () => {
	it('renders without Manual IDs field when source is posts', () => {
		renderWith({ source: 'posts' });
		expect(
			screen.queryByLabelText(/manual post ids/i)
		).not.toBeInTheDocument();
	});

	it('shows Manual IDs textarea when source is manual', () => {
		renderWith({ source: 'manual' });
		expect(screen.getByLabelText(/manual post ids/i)).toBeInTheDocument();
	});

	it('Manual IDs textarea parses comma-separated integers on change', () => {
		const { setAttributes } = renderWith({
			source: 'manual',
			manualIds: [],
		});
		const textarea = screen.getByLabelText(/manual post ids/i);
		fireEvent.change(textarea, { target: { value: '1, 2, 3' } });
		expect(setAttributes).toHaveBeenCalledWith({ manualIds: [1, 2, 3] });
	});

	it('Search TextControl updates via change event', () => {
		const { setAttributes } = renderWith();
		const searchInput = screen.getByLabelText(/search text/i);
		fireEvent.change(searchInput, { target: { value: 'hello' } });
		expect(setAttributes).toHaveBeenCalledWith({ search: 'hello' });
	});

	it('Bind search to URL param TextControl is present', () => {
		renderWith();
		expect(
			screen.getByLabelText(/url parameter name/i)
		).toBeInTheDocument();
	});

	it('Exclude current post toggle calls setAttributes on change', () => {
		const { setAttributes } = renderWith({ excludeCurrent: false });
		const toggle = screen.getByLabelText(/exclude current post/i);
		fireEvent.click(toggle);
		expect(setAttributes).toHaveBeenCalledWith({ excludeCurrent: true });
	});

	it('Ignore sticky posts toggle is rendered', () => {
		renderWith();
		expect(
			screen.getByLabelText(/ignore sticky posts/i)
		).toBeInTheDocument();
	});

	// Wrapper / Item tag SelectControls moved out of AdvancedPanel into the
	// shared ResultsLayoutControls when the Results layout panel was added to
	// both the parent query inspector and the query-results child inspector.
	// Those controls are now covered by the ResultsLayoutControls-level tests.

	it('Manual IDs filters out non-positive integers', () => {
		const { setAttributes } = renderWith({
			source: 'manual',
			manualIds: [],
		});
		const textarea = screen.getByLabelText(/manual post ids/i);
		fireEvent.change(textarea, { target: { value: '0, abc, -5, 10' } });
		expect(setAttributes).toHaveBeenCalledWith({ manualIds: [10] });
	});
});
