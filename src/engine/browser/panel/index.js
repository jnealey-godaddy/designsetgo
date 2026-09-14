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
 */
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import AgentBuildPanel from './AgentBuildPanel';
import './editor.scss';

const SIDEBAR_NAME = 'designsetgo-agent-build-sidebar';

registerPlugin('designsetgo-agent-build', {
	render: () => (
		<>
			<PluginSidebarMoreMenuItem target={SIDEBAR_NAME}>
				{__('Agent build', 'designsetgo')}
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name={SIDEBAR_NAME}
				title={__('Agent build', 'designsetgo')}
			>
				<AgentBuildPanel />
			</PluginSidebar>
		</>
	),
	icon: 'insert',
});
