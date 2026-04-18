/**
 * DsgoInspectorPanel
 *
 * Wraps WordPress's __experimentalToolsPanel to enforce the plugin's
 * 3-panel inspector convention (Settings, Style, Advanced) and to give
 * users a consistent reset-to-default affordance on every control.
 *
 * Theme 3 of the editor UX design migrates ~30 blocks onto this primitive.
 * This component is intentionally thin — it warns (does not throw) when
 * given an unrecognised panelName so existing blocks can adopt it
 * incrementally without breaking renders.
 *
 * Usage:
 *
 *   <InspectorControls>
 *     <DsgoInspectorPanel
 *       title={__('Settings', 'designsetgo')}
 *       panelName="settings"
 *       panelId={clientId}
 *       resetAll={() => setAttributes({ ... })}
 *     >
 *       <DsgoInspectorPanel.Item ... />
 *     </DsgoInspectorPanel>
 *   </InspectorControls>
 *
 * Props:
 *   title     {string} — User-visible panel label. Should be wrapped in __()
 *                        so it is translated for non-English locales.
 *   panelName {string} — Canonical, non-translated key for the panel.
 *                        Must be one of: 'settings', 'style', 'advanced'.
 *                        If omitted the guardrail is skipped (incremental
 *                        adoption — existing callers that only pass title
 *                        continue to render without warning).
 */
/* eslint-disable @wordpress/no-unsafe-wp-apis */
import {
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
/* eslint-enable @wordpress/no-unsafe-wp-apis */

const CANONICAL_PANEL_NAMES = ['settings', 'style', 'advanced'];

const _warnedPanelNames = new Set();

export function DsgoInspectorPanel({
	title,
	panelName,
	panelId,
	resetAll,
	children,
	...rest
}) {
	if (
		panelName &&
		!CANONICAL_PANEL_NAMES.includes(panelName) &&
		!_warnedPanelNames.has(panelName)
	) {
		_warnedPanelNames.add(panelName);
		// eslint-disable-next-line no-console
		console.warn(
			`DsgoInspectorPanel: panelName "${panelName}" is not one of the canonical values (${CANONICAL_PANEL_NAMES.join(', ')}). See docs/plans/2026-04-16-blocks-editor-ux-design.md Theme 3.`
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
export const _resetWarnCache = () => _warnedPanelNames.clear();
