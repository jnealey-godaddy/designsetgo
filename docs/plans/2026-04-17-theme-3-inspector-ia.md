# Theme 3 — Inspector IA Standardization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate the editor inspector for ~30 plugin blocks onto the canonical 3-panel convention (Settings / Style / Advanced) using `<DsgoInspectorPanel>` (the `ToolsPanel`-backed primitive shipped in Theme 6). Users get reset-to-default on every control; new blocks can't drift from the convention.

**Source:** [`docs/plans/2026-04-16-blocks-editor-ux-design.md`](./2026-04-16-blocks-editor-ux-design.md) Theme 3.

**Depends on:** [Theme 6](./2026-04-17-theme-6-shared-authoring-primitives.md) (`<DsgoInspectorPanel>`). This plan assumes Theme 6 is merged before any family-migration PR lands.

**Tech Stack:** WordPress block editor (`@wordpress/components` `__experimentalToolsPanel` / `__experimentalToolsPanelItem`, `@wordpress/block-editor` `InspectorControls`), Jest 29 via `@wordpress/scripts`, React Testing Library through `tests/unit/setup.js`.

**Source-survey findings driving the plan:**

| Doc estimate | Survey reality |
|---|---|
| ~30 blocks affected | 48 blocks register at least one `PanelBody`; ~30 carry custom (non-native) controls that warrant migration |
| Naming is ad-hoc | Confirmed — `"Accordion Settings"`, `"Tab Settings"`, `"Flip Card Settings"`, `"Layout"`, `"Media"`, `"Grid Settings"` / `"Gap Settings"` / `"Width Settings"` all coexist today |
| `ToolsPanel` used in 1 place | Confirmed — only `countdown-timer/components/inspector/UnitBorderPanel.js` |
| `<DsgoInspectorPanel>` exists | Yes — `src/components/shared/DsgoInspectorPanel/index.js` (Theme 6) |

---

## Convention

### Three panels, in this order

1. **Settings** (`panelName="settings"`) — behavior, content, structural choices.
2. **Style** (`panelName="style"`) — visuals not covered by native `supports`. Skip if everything visual is handled by native color / typography / spacing.
3. **Advanced** (auto-provided by WP via `<InspectorControls group="advanced">`) — HTML element, anchor, custom CSS class.

Color stays in `<InspectorControls group="color">` so it slots into the native Color panel (do not duplicate inside `Style`).

### Naming rules

- **`title` is `__('Settings', 'designsetgo')` / `__('Style', 'designsetgo')`** — no block-name prefix (no more `"Grid Settings"`, `"Tab Settings"`).
- **`panelName` is one of `'settings'` / `'style'`** — DsgoInspectorPanel warns once per unrecognised value.
- **`panelId={clientId}`** — required for `ToolsPanel` to scope reset state per block instance.

### Control rules

- Every `<DsgoInspectorPanel.Item>` declares `hasValue`, `onDeselect`, and `isShownByDefault`.
- `hasValue` returns `true` when the attribute differs from the `block.json` default.
- `onDeselect` resets the attribute back to the `block.json` default (via `setAttributes`).
- `isShownByDefault` is `true` for the primary 1–3 controls per panel; the rest are revealed via the panel's "+" menu.

### Display order within Settings

Within a Settings panel, place primary behavior toggles first (e.g. "Constrain Inner Width"), then dependent inputs (e.g. "Max Content Width"), then layout choices (alignment, position).

---

## File Structure

**No new directories.** All work lives in existing `src/blocks/{block}/edit.js` files plus `tests/unit/blocks/{block}/`.

**Modified files (per family):** see individual tasks below.

**Documentation update (lands with Task 1):**
- `docs/plans/2026-04-17-theme-3-inspector-ia.md` (this doc).
- `.claude/claude.md` — add an **Inspector IA** section under `## Architecture` documenting the 3-panel convention, naming rules, and control rules. Back-references `<DsgoInspectorPanel>` from the Shared Primitives section.

---

## Task 1 — Convention doc + layout family POC

**PR title:** `feat(blocks): Theme 3 — inspector IA convention + layout family migration`

**Files:**
- Create: `docs/plans/2026-04-17-theme-3-inspector-ia.md` (this doc).
- Modify: `.claude/claude.md` — add Inspector IA section.
- Modify: `src/blocks/grid/edit.js` — migrate 3 panels (Grid Settings / Gap Settings / Width Settings) into a single Settings panel.
- Modify: `src/blocks/section/edit.js` — migrate 1 panel (Section Settings) into a Settings panel.
- Create: `tests/unit/blocks/grid/inspector.test.js` — verify panel renders and reset-all clears non-default attributes.
- Create: `tests/unit/blocks/section/inspector.test.js` — same coverage shape.

### Why two blocks instead of the full layout family

This PR proves the convention against both shapes — multi-panel consolidation (`grid`) and single-panel rename (`section`) — without taking on the full migration scope. The remaining layout blocks (`row`, `fifty-fifty`) and the other 5 families are scheduled in Tasks 2–7 below. Each is its own PR so screenshot-diff CI (Task 0) can gate them family by family.

### Step 1.1: Verify Theme 6 is on `main`

- [ ] `git log main --oneline | grep "Theme 6"` shows the merge commit.
- [ ] `src/components/shared/DsgoInspectorPanel/index.js` exists.

If Theme 6 has not merged, this PR is stacked on top of the Theme 6 branch and will rebase to `main` after Theme 6 merges.

### Step 1.2: Migrate `grid`

- [ ] Replace `import { ... PanelBody, ... } from '@wordpress/components';` — drop `PanelBody`.
- [ ] Add `import { DsgoInspectorPanel } from '../../components/shared';`.
- [ ] Collapse the three `<PanelBody>` blocks (Grid Settings / Gap Settings / Width Settings) into one `<DsgoInspectorPanel title={__('Settings', 'designsetgo')} panelName="settings" panelId={clientId} resetAll={...}>`.
- [ ] Wrap each control in `<DsgoInspectorPanel.Item label hasValue onDeselect isShownByDefault>`. Defaults from `src/blocks/grid/block.json`:
  - `desktopColumns: 3`, `tabletColumns: 2`, `mobileColumns: 1` — **default-shown**.
  - `alignItems: 'stretch'` — **default-shown**.
  - `useCustomGaps` (local React state) gates `rowGap` / `columnGap` — keep the existing dependent-control pattern; the toggle itself is **default-shown**.
  - `constrainWidth: false`, `contentWidth: ''` — **not default-shown** (dependent inputs reveal on toggle).
- [ ] `resetAll` sets every Settings-panel attribute back to its `block.json` default.
- [ ] No change to `<InspectorControls group="advanced">` (HTML Element) or `<InspectorControls group="color">` (Hover Settings).

### Step 1.3: Migrate `section`

- [ ] Drop `PanelBody` import; add `DsgoInspectorPanel`.
- [ ] Replace the single `<PanelBody title={__('Section Settings', ...)}>` with `<DsgoInspectorPanel title={__('Settings', ...)} panelName="settings" panelId={clientId} resetAll={...}>`.
- [ ] Wrap `constrainWidth` (toggle) and `contentWidth` (UnitControl) as `<DsgoInspectorPanel.Item>` entries. Defaults: `constrainWidth: false`, `contentWidth: ''`.
- [ ] Leave the second `<InspectorControls>` block (ShapeDividerControls) alone — it is its own component and will be migrated when the shape-divider sub-component is rewritten.

### Step 1.4: Tests

- [ ] `tests/unit/blocks/grid/inspector.test.js` — render the migrated edit component; assert that the panel label is "Settings", that defaulted attributes do not show as "modified", and that `resetAll` resets every non-default attribute.
- [ ] `tests/unit/blocks/section/inspector.test.js` — same shape.

### Step 1.5: Verification

- [ ] `npm run build` succeeds.
- [ ] `npx jest tests/unit/blocks/grid tests/unit/blocks/section` passes.
- [ ] In the editor: insert Grid → Settings panel renders with reset-to-default ⋮ menu on each control. Change Desktop Columns → 5; click reset → returns to 3.
- [ ] Same flow for Section.
- [ ] No console warnings about unrecognised `panelName`.

---

## Task 0 — Screenshot-diff CI (prerequisite, separate PR)

**PR title:** `chore(ci): editor inspector screenshot-diff workflow`

Stand up Playwright-based visual regression for the editor sidebar so subsequent family PRs can be reviewed by diff. Estimated effort: ~1 day. Out of scope for Task 1 — the convention doc and POC migrations do not depend on it, but every family PR (Tasks 2–7) does.

- [ ] Add `tests/visual/inspector/{block}.spec.js` for each migrated block.
- [ ] Wire into `.github/workflows/` as a required check on PRs that touch `src/blocks/**/edit.js`.
- [ ] Document the snapshot-update flow in `.claude/claude.md`.

---

## Task 2 — Layout family completion

**PR title:** `feat(blocks): Theme 3 — layout family inspector IA (row, fifty-fifty)`

- [ ] Migrate `src/blocks/row/edit.js` (1 panel: "Row Settings").
- [ ] Migrate `src/blocks/fifty-fifty/edit.js` (2 panels: "Layout", "Media").
- [ ] Tests under `tests/unit/blocks/{row,fifty-fifty}/inspector.test.js`.

## Task 3 — Interactive family

**PR title:** `feat(blocks): Theme 3 — interactive family inspector IA`

Blocks: `accordion`, `accordion-item`, `tabs`, `tab`, `slider`, `slide`, `modal`, `modal-trigger`, `flip-card`, `image-accordion`, `image-accordion-item`.

- [ ] Family-wide audit and migration. Estimated 30–60 min per block.
- [ ] Special handling for `slider` — has 8 panels today; consolidation target needs a sub-design.

## Task 4 — Form family

**PR title:** `feat(blocks): Theme 3 — form family inspector IA`

Blocks: `form-builder`, `form-text-field`, `form-email-field`, `form-url-field`, `form-phone-field`, `form-number-field`, `form-date-field`, `form-time-field`, `form-textarea-field`, `form-select-field`, `form-checkbox-field`, `form-hidden-field`.

- [ ] `form-builder` has 7 panels — consolidation target needs a sub-design.

## Task 5 — Scroll family

**PR title:** `feat(blocks): Theme 3 — scroll family inspector IA`

Blocks: `scroll-marquee`, `scroll-slide`, `scroll-slides`, `scroll-accordion`, `scroll-accordion-item`, `sticky-sections`.

## Task 6 — Content family

**PR title:** `feat(blocks): Theme 3 — content family inspector IA`

Blocks: `card`, `icon`, `icon-button`, `icon-list`, `icon-list-item`, `pill`, `divider`, `advanced-heading`, `heading-segment`, `breadcrumbs`, `blobs`, `comparison-table`, `timeline`, `timeline-item`.

## Task 7 — Data family

**PR title:** `feat(blocks): Theme 3 — data family inspector IA`

Blocks: `countdown-timer`, `counter`, `counter-group`, `progress-bar`, `table-of-contents`, `product-categories-grid`, `product-showcase-hero`, `map`.

- [ ] `countdown-timer` already uses `ToolsPanel` for `UnitBorderPanel.js` — port the rest of its inspector for consistency.

---

## Out of scope for this plan

- Visual restyle of the inspector panels (this is a structural/IA migration only).
- Renaming or merging custom block attributes — `block.json` schema is untouched, so no deprecations.
- Frontend (`save.js`) changes — none required; `<DsgoInspectorPanel>` is editor-only.
- Migrating `countdown-timer/UnitBorderPanel.js` — it already uses `ToolsPanel`. It will adopt `DsgoInspectorPanel` in Task 7 for consistency, not for capability.
- Color panel reorganisation — colors stay in native `group="color"` per the convention. Theme 1 will revisit color-panel UX as part of placeholder variation work.

---

## Migration patterns reference

### Pattern A — single PanelBody → single DsgoInspectorPanel

```jsx
// Before
<InspectorControls>
  <PanelBody title={__('Section Settings', 'designsetgo')} initialOpen>
    <ToggleControl label={__('Constrain Inner Width', 'designsetgo')} ... />
    {constrainWidth && <UnitControl label={__('Max Content Width', 'designsetgo')} ... />}
  </PanelBody>
</InspectorControls>

// After
<InspectorControls>
  <DsgoInspectorPanel
    title={__('Settings', 'designsetgo')}
    panelName="settings"
    panelId={clientId}
    resetAll={() => setAttributes({ constrainWidth: false, contentWidth: '' })}
  >
    <DsgoInspectorPanel.Item
      label={__('Constrain Inner Width', 'designsetgo')}
      hasValue={() => constrainWidth !== false}
      onDeselect={() => setAttributes({ constrainWidth: false, contentWidth: '' })}
      isShownByDefault
    >
      <ToggleControl label={__('Constrain Inner Width', 'designsetgo')} ... />
    </DsgoInspectorPanel.Item>
    {constrainWidth && (
      <DsgoInspectorPanel.Item
        label={__('Max Content Width', 'designsetgo')}
        hasValue={() => contentWidth !== ''}
        onDeselect={() => setAttributes({ contentWidth: '' })}
      >
        <UnitControl label={__('Max Content Width', 'designsetgo')} ... />
      </DsgoInspectorPanel.Item>
    )}
  </DsgoInspectorPanel>
</InspectorControls>
```

### Pattern B — multi-PanelBody consolidation

Merge sibling `<PanelBody>` titles into a single Settings panel. Group related controls visually using the existing `BaseControl`/heading pattern if separation is desired; the panel itself stays one.

### Pattern C — Settings + Style split

Used when a block has both behavior controls (e.g. autoplay, loop) and visual controls not covered by native `supports` (e.g. arrow icon size). Two panels, both `DsgoInspectorPanel`, ordered Settings → Style.
