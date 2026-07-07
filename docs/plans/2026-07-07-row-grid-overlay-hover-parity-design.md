# Row/Grid overlay + hover-variation parity — design

**Date**: 2026-07-07
**Status**: Approved
**Origin**: Code review on [PR #445](https://github.com/jnealey-godaddy/designsetgo/pull/445) (section overlay/hover style-class work). The reviewer flagged that `designsetgo/row` and `designsetgo/grid` share the section's overlay/hover attributes but were never wired to detect style-kit overlay/hover variations, and suggested a fast-follow.

## Background

PR #445 taught `designsetgo/section` to detect style-kit block-style variations
(`is-style-overlay-*`, `is-style-hover-text-*`, `is-style-hover-icon-*`,
`is-style-hover-button-*`) applied via `className` and emit the matching
activation class (`dsgo-stack--has-overlay`, `dsgo-stack--has-hover-text`,
etc.), because `Section_Styles` (`includes/features/class-section-styles.php`)
already broadcasts any section-style variation registered on `core/group` /
`core/columns` / `core/column` onto `designsetgo/section`, `designsetgo/row`,
and `designsetgo/grid` alike. That means a variation is selectable in the
Styles panel for Row and Grid *today*, but only Section's `save()`/`edit.js`
actually derive a visual effect from it.

Investigation into current Row/Grid state:

- **Row** already has `overlayColor` and all four hover-color attributes
  (`hoverBackgroundColor`, `hoverTextColor`, `hoverIconBackgroundColor`,
  `hoverButtonBackgroundColor`), and its stylesheets already have the
  `.dsgo-flex--has-overlay` `::before` block. It is missing only the
  style-variation *detection* — the class is emitted solely when the
  attribute is set.
- **Grid** has the four hover-color attributes but **no `overlayColor`
  attribute or overlay CSS at all**. Per user decision, this fast-follow adds
  full overlay support to Grid (not just hover parity), so Grid ends up at
  full parity with Row/Section rather than a subset.
- Neither block has a class-gated hover-text rule (`&--has-hover-text:hover`)
  — only the inline `[style*="--dsgo-hover-text-color"]` gate exists.
- The shared icon/icon-button hover-background CSS
  (`src/blocks/icon/style.scss`, `src/blocks/icon-button/style.scss`, and
  their `editor.scss` counterparts) only recognizes
  `.dsgo-stack--has-hover-icon` / `.dsgo-stack--has-hover-button` — not the
  Row/Grid equivalents.

## Goals

1. Row and Grid detect `is-style-overlay-*` and the three
   `is-style-hover-{text,icon,button}-*` families on `className` and emit the
   matching activation class, exactly like Section.
2. Grid gains full overlay support (attribute, inspector control, CSS,
   style-variation detection) — reaching parity with Row/Section.
3. Existing saved Row/Grid content is not broken by the new detection logic —
   each block gets a deprecation covering the "detection didn't exist yet"
   case.
4. Icon and Icon Button blocks recognize the new Row/Grid activation classes,
   not just Section's.

## Non-goals

- No change to Section's own behavior or attribute schema (already shipped in
  #445).
- No new hover/overlay *attributes* beyond `overlayColor` for Grid — the four
  hover-color attributes already exist on both blocks.
- No retroactive full test-suite backfill for Row/Grid — new tests are scoped
  to the overlay/hover-variation behavior this change introduces.

## Design

### 1. Shared utility extraction

This is the third block to need this exact logic (Section, then Row, then
Grid), so per the project's "second time you write a pattern, extract it"
convention, promote it out of `src/blocks/section/utils/has-overlay-style.js`
into a new shared module: `src/utils/style-variation-classes.js`.

```js
export function hasOverlayStyleClass(className) { /* unchanged logic */ }

export function hoverVariationClasses(className, blockClassName) {
  // same HOVER_VARIATION_FAMILIES table, but the emitted class is
  // `${blockClassName}--has-hover-{text,icon,button}` instead of a
  // hardcoded `dsgo-stack--...` prefix.
}
```

`src/blocks/section/utils/has-overlay-style.js` becomes a thin wrapper that
re-exports `hasOverlayStyleClass` unchanged and calls the shared
`hoverVariationClasses(className, 'dsgo-stack')` — so Section's existing
imports, tests, and deprecations keep working with no behavior change.

Row and Grid import directly from `src/utils/style-variation-classes.js` and
pass their own prefix (`dsgo-flex`, `dsgo-grid`).

### 2. Row changes

- `edit.js` / `save.js`: `hasOverlay = !!overlayColor || hasOverlayStyleClass(attributes.className)`
  (already partially there — currently only checks `overlayColor`). Append
  `...hoverVariationClasses(attributes.className, 'dsgo-flex')` to the
  className array. Mirrors Section's save.js structure exactly.
- `style.scss` / `editor.scss`: add a `&--has-hover-text:hover` rule next to
  the existing `[style*="--dsgo-hover-text-color"]` gate, applying
  `var(--dsgo-hover-text-color)` the same way.
- New deprecation prepended to `src/blocks/row/deprecated.js`, reproducing
  today's Row `save()` (overlay from `overlayColor` only, no hover activation
  classes). `isEligible` checks: stored HTML contains `dsgo-flex`, and
  `hoverVariationClasses(attributes.className, 'dsgo-flex')` yields an
  activation class missing from `innerHTML`, OR `hasOverlayStyleClass` is
  true while `dsgo-flex--has-overlay` is missing from `innerHTML` (and
  `overlayColor` is unset). `migrate()` is a passthrough — only the
  serialized class differs.

### 3. Grid changes

- `block.json`: add `"overlayColor": { "type": "string", "default": "" }`.
- `edit.js`: add the "Overlay Color" entry to the existing
  `ColorGradientSettingsDropdown` (same shape as Row's), add
  `hasOverlay`/`hoverVariationClasses` derivation, add `dsgo-grid--has-overlay`
  to the className string, add the `--dsgo-overlay-color`/
  `--dsgo-overlay-opacity` inline vars.
- `save.js`: same derivation, mirrored.
- `style.scss` / `editor.scss`: new `&.dsgo-grid--has-overlay` block:
  ```scss
  &.dsgo-grid--has-overlay {
    overflow: hidden;

    &::before {
      content: '';
      position: absolute;
      inset: 0;
      background-color: var(--dsgo-overlay-color);
      opacity: var(--dsgo-overlay-opacity, 0.8);
      pointer-events: none;
      z-index: 1;
    }

    > .dsgo-grid__inner {
      position: relative;
      z-index: 2;
    }
  }
  ```
  (Confirmed: `grid/editor.scss` already sets `position: relative` on
  `.dsgo-grid` at line 49, but `grid/style.scss`, the frontend stylesheet,
  does not — add `position: relative` to `.dsgo-grid` in `style.scss` so the
  `::before` overlay positions correctly on the frontend.) Also add the
  `&--has-hover-text:hover` class-gated rule.
- New deprecation prepended to `src/blocks/grid/deprecated.js`. Since Grid
  never had `overlayColor` before, `isEligible` targets: stored HTML contains
  `dsgo-grid`, and either (a) a hover-variation family present on `className`
  without its activation class in `innerHTML`, or (b) an overlay-style
  variation present on `className` (the only way Grid could have one pre-fix,
  since it had no `overlayColor` attribute) without `dsgo-grid--has-overlay`
  in `innerHTML`. `save()` reproduces current (pre-fix) Grid output —
  attributes minus `overlayColor` (add it to the deprecation's attribute
  schema with the same default so `migrate()` can pass through cleanly, since
  the live schema now includes it as a valid-but-absent attribute — no
  attribute-value change needed, only the serialized class differs).

### 4. Cross-block CSS (icon/icon-button parity)

Add Row/Grid equivalents next to each existing `.dsgo-stack--has-hover-*`
selector in:

- `src/blocks/icon/style.scss`, `src/blocks/icon/editor.scss`
- `src/blocks/icon-button/style.scss`, `src/blocks/icon-button/editor.scss`

E.g. in `icon/style.scss`:
```scss
.dsgo-flex[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-flex__inner > .dsgo-icon,
.dsgo-grid[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-grid__inner > .dsgo-icon,
.dsgo-stack[style*="--dsgo-parent-hover-icon-bg"]:hover .dsgo-stack__inner > .dsgo-icon,
.dsgo-flex--has-hover-icon:hover .dsgo-flex__inner > .dsgo-icon,
.dsgo-grid--has-hover-icon:hover .dsgo-grid__inner > .dsgo-icon,
.dsgo-stack--has-hover-icon:hover .dsgo-stack__inner > .dsgo-icon {
  background-color: var(--dsgo-parent-hover-icon-bg) !important;
  transition: background-color 0.3s ease;
}
```
(The `[style*=...]` selectors already exist for `.dsgo-flex`/`.dsgo-grid` —
only the new `--has-hover-icon`/`--has-hover-button` class selectors are
additions.)

### 5. Tests

New, scoped to this feature only:

- `src/blocks/row/test/save.test.js` — overlay-class + hover-activation-class
  behavior, mirroring `src/blocks/section/test/save.test.js`'s "overlay
  class" and "hover variation activation classes" `describe` blocks.
- `src/blocks/row/test/deprecated.test.js` — the new deprecation's
  `isEligible`/`migrate` round-trip.
- `src/blocks/grid/test/save.test.js` — same shape, plus a case verifying the
  new `overlayColor` attribute itself still works (inline var + class).
- `src/blocks/grid/test/deprecated.test.js` — same shape as Row's.

## Risks / open questions

- The new Grid deprecation's `isEligible` for the overlay case can only ever
  fire for a `className`-driven variation (never `overlayColor`, since that
  attribute didn't exist before this change) — this asymmetry versus Row's
  deprecation (which also has to handle explicit `overlayColor`) is
  intentional and should be called out in the deprecation's code comment, not
  "fixed" to look symmetrical.
