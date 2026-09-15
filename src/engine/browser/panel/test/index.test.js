/**
 * The Agent build sidebar's registration guard (U6): it must render nothing
 * outside a finishable post editor context — the Site Editor above all,
 * since it renders `PluginArea` too and would otherwise pick this plugin up
 * — and render normally in a real post editor.
 *
 * `@wordpress/editor`, `@wordpress/plugins`, and `@wordpress/data` are
 * stubbed locally rather than using the project's real/mocked packages:
 * `@wordpress/editor`'s real `PluginSidebar` needs a full
 * `ComplementaryArea`/preferences bootstrap this Jest environment doesn't
 * set up (see `AgentBuildPanel.test.js`'s doc comment for the same
 * reasoning), and `tests/unit/__mocks__/wordpressEditorMock.js` exports only
 * a bare store id, not the components this file registers.
 */
jest.mock('@wordpress/plugins', () => ({ registerPlugin: jest.fn() }));
jest.mock('@wordpress/editor', () => ({
	PluginSidebar: ({ children }) => (
		<div data-testid="sidebar">{children}</div>
	),
	PluginSidebarMoreMenuItem: ({ children }) => (
		<div data-testid="menu-item">{children}</div>
	),
}));
jest.mock('@wordpress/data', () => ({ useSelect: jest.fn() }));
jest.mock('../AgentBuildPanel', () => () => <div data-testid="panel" />);
jest.mock('../editor.scss', () => ({}));

import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { registerPlugin } from '@wordpress/plugins';
import { useSelect } from '@wordpress/data';
// Importing for its module-level registration side effect: `panel/index.js`
// calls `registerPlugin()` once, at import time, guarded only by
// `isTopWindow()` — the per-render finishable-context decision lives in the
// `render` function it registers, captured below. A single top-level import
// (rather than `jest.resetModules()` + `require()` per test) avoids the mock
// module for `@wordpress/plugins` being re-instantiated with a different
// `registerPlugin` jest.fn() than the one this file imported.
import '../index';

/**
 * @param {Object} [editorSelectors] `core/editor` selector stub, e.g.
 *                                   `{ getCurrentPostId: () => 12, getCurrentPostType: () => 'page' }`; omit to simulate no `core/editor` store at all (e.g. the widgets editor).
 */
function selectEditorAs(editorSelectors) {
	useSelect.mockImplementation((mapSelect) =>
		mapSelect((storeName) =>
			storeName === 'core/editor' ? editorSelectors : {}
		)
	);
}

describe('Agent build sidebar registration guard (U6)', () => {
	test('registers the plugin once, in the top window', () => {
		expect(registerPlugin).toHaveBeenCalledTimes(1);
		expect(registerPlugin).toHaveBeenCalledWith(
			'designsetgo-agent-build',
			expect.objectContaining({ render: expect.any(Function) })
		);
	});

	test('renders the sidebar and its more-menu item in a real post editor', () => {
		selectEditorAs({
			getCurrentPostId: () => 12,
			getCurrentPostType: () => 'page',
		});
		const Render = registerPlugin.mock.calls[0][1].render;

		render(<Render />);

		expect(screen.getByTestId('sidebar')).toBeInTheDocument();
		expect(screen.getByTestId('menu-item')).toBeInTheDocument();
		expect(screen.getByTestId('panel')).toBeInTheDocument();
	});

	test('renders nothing in the Site Editor (template post type, string post id)', () => {
		selectEditorAs({
			getCurrentPostId: () => 'twentytwentyfive//home',
			getCurrentPostType: () => 'wp_template',
		});
		const Render = registerPlugin.mock.calls[0][1].render;

		render(<Render />);

		expect(screen.queryByTestId('sidebar')).not.toBeInTheDocument();
		expect(screen.queryByTestId('menu-item')).not.toBeInTheDocument();
	});

	test('renders nothing when the post id/type have not resolved yet', () => {
		selectEditorAs({
			getCurrentPostId: () => undefined,
			getCurrentPostType: () => undefined,
		});
		const Render = registerPlugin.mock.calls[0][1].render;

		render(<Render />);

		expect(screen.queryByTestId('sidebar')).not.toBeInTheDocument();
	});

	test('renders nothing when the core/editor store is unavailable (e.g. widgets editor)', () => {
		selectEditorAs(undefined);
		const Render = registerPlugin.mock.calls[0][1].render;

		render(<Render />);

		expect(screen.queryByTestId('sidebar')).not.toBeInTheDocument();
	});
});
