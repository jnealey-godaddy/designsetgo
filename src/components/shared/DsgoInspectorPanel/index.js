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
import { createContext, useContext } from '@wordpress/element';

const CANONICAL_PANEL_NAMES = ['settings', 'style', 'advanced'];

const _warnedPanelNames = new Set();

// `ToolsPanelItem` only registers with its parent panel when its own
// `panelId` prop matches the panel's `panelId` (see
// `@wordpress/components` `tools-panel-item/hook`). Without a matching
// value, items render but never appear in the panel's reset-to-default
// ⋮ menu. This context lets every `DsgoInspectorPanel.Item` inherit the
// parent panel's `panelId` automatically, so callers can't forget.
const PanelIdContext = createContext(null);

export function DsgoInspectorPanel({
	title,
	panelName,
	panelId,
	resetAll,
	children,
	// Explicitly drop nested-only layout props if a caller passes them —
	// see comment on the render below. Stripping here (rather than just
	// documenting) prevents the ~50% width regression from sneaking back
	// in via `...rest` during the Theme 3 migration rollout.
	// eslint-disable-next-line no-unused-vars
	hasInnerWrapper: _hasInnerWrapper,
	// eslint-disable-next-line no-unused-vars
	shouldRenderPlaceholderItems: _shouldRenderPlaceholderItems,
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
	// `hasInnerWrapper` and `shouldRenderPlaceholderItems` are intended for
	// ToolsPanels nested inside a parent ToolsPanel (e.g. sub-panels rendered
	// via `<InspectorControls group="border">`). On a top-level panel they
	// wrap children in an inner grid cell that shrinks controls to a single
	// column — the convention here targets standalone Settings/Style/Advanced
	// panels, so both are left off.
	return (
		<PanelIdContext.Provider value={panelId}>
			<ToolsPanel
				label={title}
				panelId={panelId}
				resetAll={resetAll}
				{...rest}
			>
				{children}
			</ToolsPanel>
		</PanelIdContext.Provider>
	);
}

function DsgoInspectorPanelItem({ panelId: panelIdProp, ...rest }) {
	const contextPanelId = useContext(PanelIdContext);
	return <ToolsPanelItem panelId={panelIdProp ?? contextPanelId} {...rest} />;
}

DsgoInspectorPanel.Item = DsgoInspectorPanelItem;

// Test-only helper — clears the deduped warn cache between tests.
// Not part of the public API.
export const _resetWarnCache =
	process.env.NODE_ENV === 'test'
		? () => _warnedPanelNames.clear()
		: undefined;
