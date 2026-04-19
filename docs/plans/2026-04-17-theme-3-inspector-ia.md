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
- **`isShownByDefault` is `true` on every item.** The original plan hid tertiary controls behind `ToolsPanel`'s kebab ("+") menu to keep inspector panels short, but the menu is not discoverable to most authors using this plugin — users missed controls they previously saw in flat `PanelBody` groups. The revised policy is: show everything by default. The per-item ⋮ reset and the panel-level `resetAll` still work exactly the same; only the default-visibility changed. See the "should we have the settings all show by default?" thread on PR #363 for the decision.

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
- Create: `tests/unit/blocks/inspector-ia.test.js` — single static-analysis suite that iterates over `MIGRATED_BLOCKS = ['grid', 'section', ...]`, asserting the structural invariants of every migrated `edit.js`. Tasks 2–7 extend the array as their family lands; the static-analysis approach (over per-block render tests) keeps WP store mocking out of CI — full render/visual coverage lives in the screenshot-diff workflow scoped in Task 0.

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
- [ ] Wrap `constrainWidth` (toggle) and `contentWidth` (UnitControl) as `<DsgoInspectorPanel.Item>` entries. Defaults from `src/blocks/section/block.json`: `constrainWidth: true`, `contentWidth: ''`. (Note: section's default is `true` — opposite of grid's `false`. `resetAll` and `hasValue` must reflect that.)
- [ ] Leave the second `<InspectorControls>` block (ShapeDividerControls) alone — it is its own component and will be migrated when the shape-divider sub-component is rewritten.

### Step 1.4: Tests

- [ ] `tests/unit/blocks/inspector-ia.test.js` — single static-analysis file that verifies every migrated block (a) imports `DsgoInspectorPanel`, (b) drops `PanelBody`, (c) declares `panelName="settings"`, `panelId={clientId}`, `resetAll`, `hasValue`, `onDeselect`, and (d) marks at least one item as `isShownByDefault`. Add the new block names to the `MIGRATED_BLOCKS` array as Tasks 2–7 land.

> **Trade-off:** The plan originally specified per-block render tests. We switched to static analysis to avoid the heavy WP block-editor store mocking those would require. Full render coverage is deferred to the screenshot-diff workflow in Task 0.

### Step 1.5: Verification

- [ ] `npm run build` succeeds.
- [ ] `npx jest tests/unit/blocks/inspector-ia.test.js` passes.
- [ ] In the editor: insert Grid → Settings panel renders with reset-to-default ⋮ menu on each control. Change Desktop Columns → 5; click reset → returns to 3.
- [ ] Same flow for Section.
- [ ] No console warnings about unrecognised `panelName`.

> **Known issue carried into Task 2+:** `grid/edit.js` uses local React state (`useCustomGaps`) to gate its Row Gap / Column Gap controls. After migration, `hasValue` reflects that ephemeral state, which resets to `!!(rowGap || columnGap)` on reload. If a user toggled the gap on then cleared both inputs, the panel item flips from shown to hidden after a reload. The robust fix is to promote `useCustomGaps` to a block attribute. Audit other blocks with similar ephemeral-state toggles (e.g. accordion's icon toggle, slider's autoplay-derived flags) when they reach this rollout.

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
- [ ] Add `'row'` and `'fifty-fifty'` to the `MIGRATED_BLOCKS` array in `tests/unit/blocks/inspector-ia.test.js`.

## Task 3 — Interactive family

**PR title:** `feat(blocks): Theme 3 — interactive family inspector IA`

Blocks: `accordion`, `accordion-item`, `tabs`, `tab`, `slider`, `slide`, `modal`, `modal-trigger`, `flip-card`, `flip-card-face`, `image-accordion`, `image-accordion-item`.

- [x] `accordion-item`, `tab`, `slide`, `modal-trigger`, `image-accordion-item`, `flip-card-face` — simple child/leaf blocks, single Settings panel each. (Tranche 1 of PR #363.)
- [x] `flip-card`, `accordion`, `image-accordion` — parent blocks that collapse 2–4 PanelBody groups into one Settings panel. (Tranche 2 of PR #363.)
- [x] `tabs` — three PanelBody groups (Tab Settings / Mobile Settings / Advanced) consolidated; the plugin-specific `enableDeepLinking` toggle lives as an off-by-default Settings item (WP's native Advanced group is HTML anchor / class only).
- [x] `modal` — the block's inspector is fragmented across seven sub-components under `src/blocks/modal/components/*Settings.js`. Each sub-component was refactored to render a React Fragment of `DsgoInspectorPanel.Item` entries; `modal/edit.js` now wraps all seven in a single Settings `DsgoInspectorPanel` with a shared `resetAll`. Structural test extended to concatenate sub-component sources for blocks listed in `COMPOSITE_INSPECTOR_BLOCKS`.
- [ ] **`slider` — deferred.** See [Task 3a](#task-3a--slider-sub-design-deferred) below; it requires a sub-design before migration.

### Task 3a — `slider` (sub-design deferred)

`src/blocks/slider/edit.js` currently has **eight** top-level panels (Layout, Transition, Arrows, Dots, Autoplay, Behavior, Style, Scroll-Driven) and uses the `useBlockColors` hook to feed inline `ColorGradientSettingsDropdown` controls for arrows and dots. Collapsing everything into one Settings panel would produce ~30 items — far past the "a few default-shown, the rest revealed via +" sweet spot the convention targets.

Open design questions before this can land:

1. **Split into Settings + Style.** Settings = behaviour (slides per view, transition, autoplay, loop, swipe, scroll-driven). Style = arrow/dot appearance + colors. The inline color dropdowns for arrows/dots would need to either become `.Item` entries inside the Style panel, or move into `<InspectorControls group="color">` alongside the existing native colors. The latter is closer to the convention but breaks the inline "colors live next to their control" affordance that `useBlockColors` was built for.
2. **Arrows / Dots nesting.** Today the arrow and dot panels are gated by `showArrows` / `showDots` toggles. In the Settings-panel world they'd become conditional `.Item` entries (same pattern as `image-accordion`'s overlay gating). Decide whether sub-headings are worth preserving for legibility.
3. **Single-slide-effect notice.** The current Transition panel shows a Notice when `fade` / `zoom` is picked. That maps cleanly to the Transition `.Item`'s body but needs reviewer buy-in on keeping Notices inside items.

Blocked on: the above. Not blocked on code — a `ToolsPanel` migration of slider is a day's work once the sub-design is signed off.

## Task 4 — Form family

**PR title:** `feat(blocks): Theme 3 — form family inspector IA`

Blocks: `form-builder`, `form-text-field`, `form-email-field`, `form-url-field`, `form-phone-field`, `form-number-field`, `form-date-field`, `form-time-field`, `form-textarea-field`, `form-select-field`, `form-checkbox-field`, `form-hidden-field`.

- [x] Eight text-like field blocks (`form-text-field` / `form-email-field` / `form-url-field` / `form-phone-field` / `form-number-field` / `form-date-field` / `form-time-field` / `form-textarea-field`) — each collapses its 1–4 `PanelBody` groups into one Settings panel. Every control is default-shown per the revised convention. `fieldName` `hasValue` predicates match on the block's specific auto-generated prefix (`field_`, `url-`, `phone-`, `number-`, `date-`, `time-`, `select-`, `checkbox-`, `hidden-`) so the reset doesn't falsely flag the generated name as "set".
- [x] `form-select-field` — three panels + dynamic options array. Options list becomes a single Settings item whose `hasValue` does a deep equality check against the `block.json` default triple.
- [x] `form-checkbox-field` — two panels (Field Settings / Additional Options) collapsed; `label` uses RichText, so the editor preview still renders via the existing label affordance below.
- [x] `form-hidden-field` — trivial two-item Settings panel (fieldName + value).
- [x] `form-builder` — **six** top-level `PanelBody` groups (Form Settings / Button Styling / Field Styling / Messages / Spam Protection / Email Notifications) consolidated into one Settings panel with ~25 items. Color dropdown stays in `<InspectorControls group="color">`. All items default-shown; conditional rate-limit and email items mount only when their parent toggle is on. `resetAll` returns every 25+ attribute to its `block.json` default in one click.

## Task 5 — Scroll family

**PR title:** `feat(blocks): Theme 3 — scroll family inspector IA`

Blocks: `scroll-marquee`, `scroll-slide`, `scroll-slides`, `scroll-accordion`, `scroll-accordion-item`, `sticky-sections`.

- [x] `scroll-slide` — single PanelBody ("Slide Settings", 1 control: `navHeading`) collapsed into Settings.
- [x] `sticky-sections` — single PanelBody (1 control: `stickyOffset`) collapsed into Settings.
- [x] `scroll-marquee` — four PanelBody groups (Performance / Scroll Settings / Image Dimensions / Spacing) consolidated into one Settings panel with six items. The "Performance" panel was info-only (Notice with image-count warning); it survives as a Notice rendered at the top of the Settings panel body, outside any `.Item`.
- [x] `scroll-slides` — `ScrollSlidesInspector` sub-component migrated to `DsgoInspectorPanel`. Four items (minHeight, maxHeight, constrainWidth, conditional contentWidth). Color group (Navigation + Overlay) stays untouched. Added to `COMPOSITE_INSPECTOR_BLOCKS` so the structural test scans the sub-component. Test regex widened to accept 2- or 3-level-up import paths.
- [ ] **`scroll-accordion` and `scroll-accordion-item` skipped** — neither block has a custom Settings `PanelBody`. `scroll-accordion` is toolbar-only; `scroll-accordion-item` only uses `<InspectorControls group="color">` for an overlay colour. Adding empty DsgoInspectorPanels would be worse than nothing; these are excluded from `MIGRATED_BLOCKS` for that reason.

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

// After (using section's defaults — `constrainWidth: true` per section/block.json)
<InspectorControls>
  <DsgoInspectorPanel
    title={__('Settings', 'designsetgo')}
    panelName="settings"
    panelId={clientId}
    resetAll={() => setAttributes({ constrainWidth: true, contentWidth: '' })}
  >
    <DsgoInspectorPanel.Item
      label={__('Constrain Inner Width', 'designsetgo')}
      hasValue={() => constrainWidth !== true}
      onDeselect={() => setAttributes({ constrainWidth: true, contentWidth: '' })}
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

> **Important — always read the target block's `block.json` first.** Defaults differ between blocks: `section.constrainWidth` is `true` while `grid.constrainWidth` is `false`. Copying this Pattern A example without verifying defaults will produce broken reset behaviour.

### Pattern B — multi-PanelBody consolidation

Merge sibling `<PanelBody>` titles into a single Settings panel. Group related controls visually using the existing `BaseControl`/heading pattern if separation is desired; the panel itself stays one.

### Pattern C — Settings + Style split

Used when a block has both behavior controls (e.g. autoplay, loop) and visual controls not covered by native `supports` (e.g. arrow icon size). Two panels, both `DsgoInspectorPanel`, ordered Settings → Style.
