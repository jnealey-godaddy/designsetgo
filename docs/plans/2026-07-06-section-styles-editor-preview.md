# Section styles — editor preview for user-customized variations

**Date**: 2026-07-06
**Branch**: `claude/section-styles-editor-preview`

## Problem (verified)

Section-style variations mirror onto DSGo container blocks correctly on the
**frontend** and for **theme/plugin-registered** styles in the editor. But when
an author **customizes** a section style in Global Styles (e.g. adds a border /
radius), the DSGo block's **editor canvas preview** shows only the pre-existing
color, not the new border.

Root cause: the editor generates block-style-variation CSS **client-side**
(`@wordpress/block-editor` `hooks/block-style-variation.js` → `toStyles`),
reading `styles.blocks.{blockName}.variations.{slug}` from the browser-merged
global-styles config. That merge never runs our server PHP mirror
(`Section_Styles`), and the user's edit is keyed to `core/group` — so it never
reaches the DSGo block in the editor. The server merged data + frontend are
correct; only the in-editor preview lags.

Core's own remedy (`__unstableBlockStyleVariationOverridesWithConfig`) is a
locked private API — unusable by a plugin.

## Approach (spike-proven, public APIs only)

An **editor-only** module that:

1. Reactively reads the live user global-styles config via public core-data:
   `select('core').getEditedEntityRecord('root','globalStyles', id)` — verified
   returns live variation edits (incl. border).
2. Builds a CSS overlay: for each DSGo container block × each user-customized
   section-style variation on the core containers, emits box-decoration
   declarations (color, border, radius, spacing, shadow, font-size) scoped to
   the **stable** `.wp-block-designsetgo-{suffix}.is-style-{slug}` class.
   Specificity 0-2-0 cleanly beats core's `:root :where()` (0-1-0). Verified:
   injecting such a rule makes the border render; removing it reverts.
3. Injects/updates a single `<style>` in the editor canvas (iframe or
   non-iframed), reactively on config change and on canvas (re)mount.

Only the **user-layer delta** is emitted (theme/registry-layer variations
already preview correctly), so this is additive and low-risk.

## Files

- `src/extensions/section-styles-editor-preview/generate-css.js` — pure,
  unit-tested: `toCssValue`, `variationDeclarations`, `buildVariationCss`.
  `TARGET_SUFFIXES` mirrors `Section_Styles::$container_blocks` (minus
  `image-accordion-item`, which opts out of background color).
- `src/extensions/section-styles-editor-preview/index.js` — `registerPlugin`
  component: reactive read → `buildVariationCss` → canvas injection.
- `src/index.js` — add the import.
- `tests/unit/extensions/section-styles-editor-preview/generate-css.test.js`.

## Verification

- Unit: CSS generation for color/border(flat+split)/radius/spacing/shadow +
  `var:preset|…` resolution.
- Manual (editor, wp-env :9451): customize a section style's border in Global
  Styles → confirm a DSGo section previews the border live; confirm frontend
  unaffected; console clean.
- `npm run build && npm run lint:js && npm run lint:css`.
