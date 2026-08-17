import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock('@wordpress/i18n', () => ({
	__: (text) => text,
}));

jest.mock('@wordpress/components', () => ({
	ToggleControl: ({ label, checked, onChange }) => (
		<label>
			{label}
			<input
				type="checkbox"
				checked={checked}
				onChange={(event) => onChange(event.target.checked)}
			/>
		</label>
	),
	CheckboxControl: ({ label, checked, onChange }) => (
		<label>
			{label}
			<input
				type="checkbox"
				checked={checked}
				onChange={(event) => onChange(event.target.checked)}
			/>
		</label>
	),
}));

// Renders children only, so assertions below target what the controls
// themselves render rather than the ToolsPanelItem chrome.
jest.mock('../../../../src/components/shared', () => ({
	DsgoInspectorPanel: {
		Item: ({ children }) => <div>{children}</div>,
	},
}));

import WooQueryControls from '../../../../src/blocks/query/components/WooQueryControls';

const setAttributes = jest.fn();

const productAttrs = { source: 'posts', postType: 'product' };

describe('WooQueryControls', () => {
	afterEach(() => {
		delete global.window.wcSettings;
		delete global.window.wooCommerceBlocksConfig;
		setAttributes.mockClear();
	});

	it('renders nothing when WooCommerce is not active', () => {
		const { container } = render(
			<WooQueryControls
				attributes={productAttrs}
				setAttributes={setAttributes}
			/>
		);
		expect(container.firstChild).toBeNull();
	});

	it('renders nothing when the query does not target products', () => {
		global.window.wcSettings = {};
		const { container } = render(
			<WooQueryControls
				attributes={{ source: 'posts', postType: 'post' }}
				setAttributes={setAttributes}
			/>
		);
		expect(container.firstChild).toBeNull();
	});

	it('renders nothing for a non-posts source even with postType product', () => {
		global.window.wcSettings = {};
		const { container } = render(
			<WooQueryControls
				attributes={{ source: 'terms', postType: 'product' }}
				setAttributes={setAttributes}
			/>
		);
		expect(container.firstChild).toBeNull();
	});

	it('renders the Woo controls for a product query when Woo is active', () => {
		global.window.wcSettings = {};
		render(
			<WooQueryControls
				attributes={productAttrs}
				setAttributes={setAttributes}
			/>
		);

		expect(
			screen.getByText('Respect catalog visibility')
		).toBeInTheDocument();
		expect(screen.getByText('Featured products only')).toBeInTheDocument();
		expect(screen.getByText('On sale only')).toBeInTheDocument();
		expect(screen.getByText('In stock')).toBeInTheDocument();
	});

	it('defaults catalog visibility to on', () => {
		global.window.wcSettings = {};
		render(
			<WooQueryControls
				attributes={productAttrs}
				setAttributes={setAttributes}
			/>
		);

		const toggle = screen
			.getByText('Respect catalog visibility')
			.querySelector('input');

		expect(toggle).toBeChecked();
	});

	it('offers stock status as a fixed set of checkboxes, not free text', () => {
		global.window.wcSettings = {};
		render(
			<WooQueryControls
				attributes={productAttrs}
				setAttributes={setAttributes}
			/>
		);

		// Exactly the three WooCommerce stock statuses, and no text input that
		// could accept a typo that silently matches nothing.
		expect(screen.getByText('In stock')).toBeInTheDocument();
		expect(screen.getByText('Out of stock')).toBeInTheDocument();
		expect(screen.getByText('On backorder')).toBeInTheDocument();
		expect(document.querySelector('input[type="text"]')).toBeNull();
	});

	it('toggles a stock status value on and off', () => {
		global.window.wcSettings = {};
		const { rerender } = render(
			<WooQueryControls
				attributes={productAttrs}
				setAttributes={setAttributes}
			/>
		);

		screen.getByText('In stock').querySelector('input').click();
		expect(setAttributes).toHaveBeenCalledWith({
			wooStockStatus: ['instock'],
		});

		rerender(
			<WooQueryControls
				attributes={{ ...productAttrs, wooStockStatus: ['instock'] }}
				setAttributes={setAttributes}
			/>
		);

		screen.getByText('In stock').querySelector('input').click();
		expect(setAttributes).toHaveBeenLastCalledWith({ wooStockStatus: [] });
	});

	it('exposes no filter controls — WooCommerce ships those', () => {
		global.window.wcSettings = {};
		render(
			<WooQueryControls
				attributes={productAttrs}
				setAttributes={setAttributes}
			/>
		);

		expect(screen.queryByText(/price range/i)).toBeNull();
		expect(screen.queryByText(/attribute filter/i)).toBeNull();
	});
});
