/**
 * OverlayHeaderPanel — guard condition tests
 *
 * Pins the typeof meta === 'undefined' guard that prevents the panel from
 * rendering (and setMeta from crashing in core-data) on post types that
 * don't have the overlay meta keys registered in PHP.
 */

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

// Capture the component passed to registerPlugin so we can render it directly.
let OverlayHeaderPanel;
jest.mock('@wordpress/plugins', () => ({
	registerPlugin: jest.fn((_name, { render: Component }) => {
		OverlayHeaderPanel = Component;
	}),
}));

jest.mock('@wordpress/data', () => ({
	useSelect: jest.fn(),
}));

jest.mock('@wordpress/core-data', () => ({
	useEntityProp: jest.fn(),
}));

jest.mock('@wordpress/i18n', () => ({ __: (t) => t }));

jest.mock('@wordpress/components', () => ({
	ToggleControl: ({ label }) => <div data-testid="toggle">{label}</div>,
	Notice: ({ children }) => <div>{children}</div>,
	ColorPalette: () => <div data-testid="color-palette" />,
	BaseControl: ({ children }) => <div>{children}</div>,
}));

// PluginDocumentSettingPanel is stubbed at the module-name-mapper level;
// re-export a minimal version that renders children so we can assert on them.
jest.mock('@wordpress/editor', () => ({
	PluginDocumentSettingPanel: ({ children, title }) => (
		<div data-testid="overlay-panel">
			<span>{title}</span>
			{children}
		</div>
	),
}));

import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';

// Importing the module triggers registerPlugin, which populates OverlayHeaderPanel.
require('../../src/overlay-header/index.js');

describe('OverlayHeaderPanel guard', () => {
	beforeEach(() => {
		jest.clearAllMocks();
		// useSelect is called twice: once for postType, once for colors.
		useSelect.mockImplementation((selector) => {
			const fakeSelect = {
				'core/editor': { getCurrentPostType: () => 'page' },
				'core/block-editor': { getSettings: () => ({ colors: [] }) },
			};
			return selector((store) => fakeSelect[store]);
		});
	});

	it('renders null when meta is undefined (template part, unregistered CPT, loading)', () => {
		useEntityProp.mockReturnValue([undefined, jest.fn()]);
		const { container } = render(<OverlayHeaderPanel />);
		expect(container.firstChild).toBeNull();
	});

	it('renders the panel when meta is a registered object', () => {
		useEntityProp.mockReturnValue([
			{
				dsgo_overlay_header: false,
				dsgo_overlay_header_text_color: '',
				dsgo_overlay_skip_top_bar: false,
			},
			jest.fn(),
		]);
		render(<OverlayHeaderPanel />);
		expect(screen.getByTestId('overlay-panel')).toBeInTheDocument();
		expect(screen.getByTestId('toggle')).toBeInTheDocument();
	});
});
