/**
 * Registers the "Agent build" editor sidebar: a person or assistant pastes
 * a JSON block tree, checks it against `window.designsetgoEngine` (see
 * `../index.js`), and inserts it.
 *
 * `PluginSidebar`/`PluginSidebarMoreMenuItem` come from `@wordpress/editor`
 * — moved there from `@wordpress/edit-post` in the Gutenberg release that
 * shipped with WordPress 6.6, and this plugin's minimum is 6.7 (see
 * readme.txt), so no `@wordpress/edit-post` fallback is needed here, unlike
 * older DSGo code written against a lower minimum.
 *
 * The plugin itself is registered once, in every top-level editor window
 * (the Site Editor renders `PluginArea` too, for back-compat with plugins
 * like this one). `AgentBuildPlugin` below is what actually decides whether
 * anything renders: it reuses `isFinishableContext()` — the same guard
 * `../finish/context.js` uses to decide whether a pending build may be
 * finished here — so the sidebar and its more-menu item stay entirely
 * absent in the Site Editor, the widgets editor, or any `wp_*` post type
 * (U6). `useSelect` makes this reactive: if the post id/type aren't
 * resolved yet at mount, the sidebar appears as soon as they are, rather
 * than never appearing at all.
 */
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import AgentBuildPanel from './AgentBuildPanel';
import { isTopWindow, isFinishableContext } from '../finish/context';
import {
	AGENT_BUILD_PLUGIN_NAME,
	AGENT_BUILD_SIDEBAR_NAME,
} from '../constants';
import './editor.scss';

/**
 * @return {JSX.Element|null} The sidebar + its more-menu item, or `null`
 *   outside a finishable post editor context.
 */
function AgentBuildPlugin() {
	const finishable = useSelect((select) => {
		const editor = select('core/editor');
		return isFinishableContext({
			isTop: true,
			postId: editor?.getCurrentPostId?.(),
			postType: editor?.getCurrentPostType?.(),
		});
	}, []);

	if (!finishable) {
		return null;
	}

	return (
		<>
			<PluginSidebarMoreMenuItem target={AGENT_BUILD_SIDEBAR_NAME}>
				{__('Agent build', 'designsetgo')}
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name={AGENT_BUILD_SIDEBAR_NAME}
				title={__('Agent build', 'designsetgo')}
			>
				<AgentBuildPanel />
			</PluginSidebar>
		</>
	);
}

// Only in a top-level editor window — never again inside the canvas iframe,
// which loads this bundle too.
if (isTopWindow(window)) {
	registerPlugin(AGENT_BUILD_PLUGIN_NAME, {
		render: AgentBuildPlugin,
		icon: 'insert',
	});
}
