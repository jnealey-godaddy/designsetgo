/**
 * DsgoInspectorPanel
 *
 * Wraps WordPress's __experimentalToolsPanel to enforce the plugin's
 * 3-panel inspector convention (Settings, Style, Advanced) and to give
 * users a consistent reset-to-default affordance on every control.
 *
 * Theme 3 of the editor UX design migrates ~30 blocks onto this primitive.
 * This component is intentionally thin — it warns (does not throw) when
 * given a non-canonical title so existing blocks can adopt it incrementally
 * without breaking renders.
 *
 * Usage:
 *
 *   <InspectorControls>
 *     <DsgoInspectorPanel
 *       title={__('Settings', 'designsetgo')}
 *       panelId={clientId}
 *       resetAll={() => setAttributes({ ... })}
 *     >
 *       <DsgoInspectorPanel.Item ... />
 *     </DsgoInspectorPanel>
 *   </InspectorControls>
 */
import {
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';

const CANONICAL_TITLES = ['Settings', 'Style', 'Advanced'];

const _warnedTitles = new Set();

export function DsgoInspectorPanel({
	title,
	panelId,
	resetAll,
	children,
	...rest
}) {
	if (!CANONICAL_TITLES.includes(title) && !_warnedTitles.has(title)) {
		_warnedTitles.add(title);
		// eslint-disable-next-line no-console
		console.warn(
			`DsgoInspectorPanel: title "${title}" is not one of the canonical panel names (${CANONICAL_TITLES.join(', ')}). See docs/plans/2026-04-16-blocks-editor-ux-design.md Theme 3.`
		);
	}
	return (
		<ToolsPanel
			label={title}
			panelId={panelId}
			resetAll={resetAll}
			hasInnerWrapper
			shouldRenderPlaceholderItems
			{...rest}
		>
			{children}
		</ToolsPanel>
	);
}

DsgoInspectorPanel.Item = ToolsPanelItem;

// Test-only helper — clears the deduped warn cache between tests.
// Not part of the public API.
export const _resetWarnCache = () => _warnedTitles.clear();
