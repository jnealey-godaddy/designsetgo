# Blocks Editor UX — Cross-Cutting Design

**Date**: 2026-04-16
**Scope**: Editor authoring UX + cross-cutting consistency across ~60 blocks
**Status**: Design, pre-implementation

## Context

The plugin ships ~60 blocks across six families (interactive, form, layout, scroll effects, content, data). A diagnostic audit surfaced that while individual blocks are solid, the authoring experience drifts block-to-block: first-insert onboarding, inspector panel naming, toolbar vs. canvas affordances, and metadata (icons, categories, titles) were all implemented ad-hoc by whoever shipped each block.

This document proposes six themed improvements, ordered by leverage. Themes 1, 3, and 5 depend on the primitives in Theme 6, so build order differs from priority order — see the summary table.

---

## Theme 1 — Placeholder & Onboarding Parity

### Finding

Only `modal` and `form-builder` present a first-insert wizard via `<Placeholder>`. Every other compound block (accordion, tabs, slider, scroll-slides, scroll-accordion, image-accordion, sticky-sections, flip-card) drops the user into a pre-seeded template with no guidance. `reveal` (if it remains — see Theme 4) is worst: `renderAppender: false` plus an empty template means the block silently disappears when cleared.

### Direction

- Adopt the modal pattern as the convention. Extract `<ModalPlaceholder>` into a shared `src/components/shared/BlockPlaceholder/` taking `{ icon, label, instructions, variations, onSelect }`.
- Every compound block gets a placeholder offering 2–4 starter layouts (tabs: Horizontal / Vertical / Pill; slider: Hero / Testimonial / Gallery; accordion: FAQ / Content / Icon List).
- No block renders an empty `<div>` with no affordance. Kill silent-empty states.

### Scope

- 8 blocks need placeholders added.
- Depends on Theme 6 `<BlockPlaceholder>` primitive.
- Effort: ~4 hours each including variation skeletons; ~1 sprint total.

### Success signal

A new user drops Tabs in, sees 3 visual choices, picks one, ends with a populated block. No "what now?" moment.

---

## Theme 2 — Consolidation via Variations (Narrowly Scoped)

### Finding

The form-field family has 5 near-identical blocks (`form-text-field`, `form-email-field`, `form-url-field`, `form-phone-field`, `form-number-field`) that differ only by HTML `type`. Each carries its own v1 deprecation. `flip-card-front` / `flip-card-back` are two near-empty sibling blocks enforcing semantic structure.

### Decision

**Do not consolidate form fields.** Migration risk is too high — thousands of live forms hold these block names in serialized post content, and the user-facing benefit (one inserter tile vs. five) is marginal since the inserter already groups them visually.

### Direction

- Consolidate `flip-card-front` + `flip-card-back` → single `flip-card-face` with `side: 'front' | 'back'` attribute. Child-only block, contained blast radius, tiny migration payload. Deprecations with `isEligible` cover live content.
- **Forward-looking rule**: add to `CLAUDE.md` and the `add-block` skill — *"If a new block differs from an existing one only by 1–3 attributes, register a variation, not a new block."* Applies to all future work.
- Audit in-flight (unshipped) blocks for sibling patterns before they land.

### Scope

- 2 blocks affected.
- Effort: ~2 hours + doc update.

---

## Theme 3 — Inspector IA Standardization

### Finding

Panel naming is ad-hoc. Accordion uses `"Accordion Settings" / "Icon Settings" / "Style Settings"`. Tabs uses `"Tab Settings" / "Mobile Settings" / "Advanced"`. Flip-card uses `"Flip Card Settings" / "How to Use"`. Form-builder has six panels. Scroll-accordion has none. Worse, `ToolsPanel` (the WordPress standard providing reset-to-default on every control) is used in exactly one place: `countdown-timer/components/inspector/UnitBorderPanel.js`. Every other numeric/toggle control across 60 blocks lacks the reset affordance core blocks now offer.

### Direction

- **3-panel convention**: `Settings` (behavior/content), `Style` (visuals not covered by native `supports`), `Advanced` (auto-provided by WP). Color stays in native `group="color"`. Prefer native `supports` over custom panels whenever possible.
- Migrate all custom controls to `ToolsPanel` + `ToolsPanelItem`. Users gain universal reset-to-default.
- Ship a `<DsgoInspectorPanel>` wrapper that enforces naming + ToolsPanel. New blocks can't drift.
- **Ordering rule**: Settings → Style → (native color/typography/spacing) → Advanced. Documented in `CLAUDE.md`.

### Rollout — big bang

Pure editor-facing refactor. `save()` output untouched → no deprecations, no content migration risk. Half-migrated state would defeat the consistency goal, so execute in one tight sequence.

1. Land `<DsgoInspectorPanel>` + convention doc (no block changes).
2. One PR per block family (interactive / form / layout / scroll / content / data) — 5 PRs, all merged within a sprint.
3. Screenshot-diff CI check for editor sidebar regressions.
4. Changelog flags the reorganization; version bump to signal the breaking editor-UX change.

### Scope

- ~30 blocks.
- Effort: 30–60 min per block post-primitive.

---

## Theme 4 — Discoverability Polish

### Findings

1. Only 2 of ~55 blocks (`modal`, `modal-trigger`) define an explicit `icon` in `block.json`. Everything else shows the generic puzzle-piece.
2. Categories are randomly split. Within the form-field family alone, 5 blocks use `category: "designsetgo"` and 6 use `category: "design"`.
3. **`reveal` investigation**: Reveal's real functionality is an extension (via `addFilter`), not a block. The `src/blocks/reveal/` registration with `parent: ["designsetgo/hidden-blocks"]` is likely stale code from an abandoned architectural direction. Needs investigation to confirm dead, then removal.
4. `grid` has title `"Grid (DSG)"` — a one-off disambiguation hack. `row` / `section` use a keyword workaround.

### Direction

- Register a custom `designsetgo` block category with a recognizable SVG icon. All parent/standalone blocks move there. Child blocks (not independently insertable) get `inserter: false` or stay behind their parent's appender.
- Assign a distinct icon to every block — Dashicons or a small custom SVG set. Family-level visual consistency (all form fields share a base shape, all scroll effects share a motion glyph).
- Investigate and remove the stale `reveal` block registration.
- Drop disambiguation hacks. Titles: `Grid`, `Row`, `Section`. The custom category + icon carry disambiguation.

### Scope

- All blocks (metadata-only change).
- Effort: 1–2 days.
- Risk: low — zero save-output changes.

---

## Theme 5 — Editor Interaction Patterns

### Findings

- **"Add child" affordances** are implemented three ways: tabs/scroll-marquee use inline canvas buttons; accordion uses the default appender; reveal has none.
- **BlockControls toolbar** is used in only 10 of ~55 blocks. Tabs, slider, modal, accordion, flip-card, form-builder, card — none — despite having block-level actions that belong in the toolbar.
- **Keyboard in the edit canvas**. Tabs is the only interactive block with ARIA tablist keyboard nav (`ArrowLeft/Right/Home/End`) in the editor. Slider, scroll-slides, accordion, image-accordion drop the keyboard user into default block-tree navigation, which is semantically wrong.
- **Inline chrome leaks**. Visible add-buttons, preview-only close buttons, `(DSG)` titles all make the edit view noisier than the rendered output.

### Direction

- Codify two patterns, pick per block:
  - *Toolbar-led*: add/remove/reorder go to `BlockControls`. Canvas stays clean. Default for most compound blocks.
  - *Canvas-led*: inline `+` appears on hover/select only. Canvas approximates frontend. Reserved for tab/slide-like blocks where child position matters visually.
- Ship `<DsgoChildToolbar>` with stock Add / Duplicate / Move / Remove controls wired to `useDispatch('core/block-editor')`. Parent blocks opt in with one import.
- Port tabs' keyboard handling into a `useTablistKeyboard` hook. Apply to tabs, slider, scroll-slides, accordion, image-accordion.
- Hover-only inline affordances — hidden until the block is selected or hovered.

### Scope

- ~10 blocks.
- Effort: 1–2 days for shared toolbar + hook; ~1 hour per block to adopt.
- Depends on Theme 6.

---

## Theme 6 — Shared Authoring Primitives

### Findings

Three patterns copy-pasted across the codebase:

1. **Color encode/decode + `ColorGradientSettingsDropdown`** — 10+ `edit.js` files with near-identical boilerplate.
2. **`uniqueId` from `clientId.substring(0, 8)` in `useEffect`** — duplicated in tabs, form-builder, modal, accordion-item, counter.
3. **`convertColorToCSSVar` + CSS-variable-as-inline-style** — ~15 blocks, unextracted.

Plus three missing shared components that Themes 1, 3, and 5 require.

### Direction

Stand up `src/hooks/` and `src/components/shared/` as canonical homes. Today utilities live in `src/utils/` (functions only) with no hooks or shared component directories.

Extract in this order, each as its own small PR:

1. `useUniqueBlockId(clientId)` — replaces 5 duplicates.
2. `useBlockColors(attributes, setAttributes, config)` — wraps the encode/decode/dropdown trio.
3. `cssVars(attributes, map)` — pure function returning the inline-style object from an attribute→variable map.
4. `<DsgoInspectorPanel>` — Theme 3 dependency.
5. `<BlockPlaceholder>` — Theme 1 dependency.
6. `<DsgoChildToolbar>` + `useTablistKeyboard` — Theme 5 dependencies.

Contribution rule added to `CLAUDE.md`: *"Before adding a pattern to a block, check `src/hooks/` and `src/components/shared/`. If it's the second time you're writing a pattern, extract it."* Back-referenced from the `add-block` skill so new blocks are born using shared primitives.

### Scope

- Foundation PRs; dependencies for Themes 1, 3, 5.
- Effort: ~1 sprint total. Hooks are small; components piggyback on Theme 3/5 rollouts.

---

## Summary

| # | Theme | Risk | Effort | Touches |
|---|---|---|---|---|
| 1 | Placeholder parity | Low | Medium | 8 blocks |
| 2 | Flip-card consolidation + guideline | Low | Small | 2 blocks + docs |
| 3 | Inspector IA (big-bang) | Low | Medium | ~30 blocks |
| 4 | Discoverability polish | Low | Small | All blocks (metadata) |
| 5 | Editor interaction patterns | Medium | Medium | ~10 blocks |
| 6 | Shared primitives | Low | Medium | Foundation for 1/3/5 |

### Build order

**6 → 4 → 3 → 1 → 5 → 2**

Primitives first, so everything downstream ships on solid foundations. Cheap metadata polish (Theme 4) next, to deliver visible improvement early. Inspector IA (Theme 3) before placeholders (Theme 1) because placeholder variation previews benefit from the normalized inspector. Theme 5 last among major themes because it depends most heavily on the other primitives. Theme 2 is decoupled and can slot in anywhere.

### Out of scope

- Form-field consolidation (migration risk too high on live content).
- Frontend UX / interactivity changes (separate deep-dive).
- Patterns library / global styles integration (separate deep-dive).
- Developer UX for plugin authors (separate deep-dive).
