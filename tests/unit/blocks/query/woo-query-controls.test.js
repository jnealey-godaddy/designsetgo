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
	FormTokenField: ({ label }) => <div>{label}</div>,
}));

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
		expect(screen.getByText('Stock status')).toBeInTheDocument();
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
