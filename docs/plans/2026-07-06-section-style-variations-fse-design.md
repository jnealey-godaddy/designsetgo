# Section-style variations + FSE color inheritance

**Date**: 2026-07-06
**Branch**: `claude/section-style-variations-fse`

## Goal

1. **Part A** — Extend the theme "section style" variations (core Group's
   "Style 1–5" etc.) so they can be applied to more DesignSetGo container-like
   blocks, not just `section`/`row`/`grid`. Container "item" blocks
   (accordion item, counter, timeline item, …) should be able to inherit the
   same global style variations as core Group / DSGo Section.
2. **Part B** — Stop forcing hardcoded hex color defaults into block `save()`
   output so unset colors inherit from FSE global styles instead of a baked
   literal.

## Part A — Extend section-style mirroring

### Mechanism (already in place)

`DesignSetGo\Section_Styles`
([includes/features/class-section-styles.php](../../includes/features/class-section-styles.php))
mirrors every block-style variation registered for the core container blocks
(`core/group`, `core/columns`, `core/column`) onto DSGo container blocks by:

1. `register_block_style()` — registers the variation *name* for each DSGo
   target (makes it selectable in the editor Styles panel, emits the
   `is-style-{slug}` class on selection).
2. `wp_theme_json_data_theme` / `wp_theme_json_data_user` filters — copies the
   resolved variation styles onto `styles.blocks.{target}.variations.{slug}`
   so WordPress emits `.wp-block-designsetgo-{target}.is-style-{slug}` CSS on
   both frontend and editor.

**This is CSS-injection + name-registration only. It never touches `save()`,
so no deprecations are required.** WP emits the variation stylesheet regardless
of a block's `supports`; every target already renders a `useBlockProps`
wrapper that receives the `is-style-*` class and already declares color +
spacing supports.

### Change

Widen the `$container_blocks` target list to the curated container set:

```php
private $container_blocks = array(
    // existing
    'designsetgo/section',
    'designsetgo/row',
    'designsetgo/grid',
    // added — curated container-like blocks
    'designsetgo/card',
    'designsetgo/fifty-fifty',
    'designsetgo/modal',
    'designsetgo/slide',
    'designsetgo/scroll-slide',
    'designsetgo/tab',
    'designsetgo/accordion-item',
    'designsetgo/scroll-accordion-item',
    'designsetgo/timeline-item',
    'designsetgo/counter',
    'designsetgo/flip-card-face',
);
```

`image-accordion-item` is deliberately **not** in the list. It sets
`color.background: false` in its block.json — its surface is a background
image + overlay — so a mirrored section style that sets a background color
would paint behind/around the image with no author-facing control to undo it.
Mirroring injects into `styles.blocks.…​.variations.…` independent of block
`supports`, so the only way to honour the opt-out is to exclude the block.

Source blocks unchanged (`core/group`/`columns`/`column`). The existing
"flatten + broadcast" comment already documents the trade-off (a variation
registered for one core container reaches all DSGo targets), which is the
desired behaviour here.

### Verification

- Select "Style 1–5" on a counter / accordion item / card in Twenty
  Twenty-Five; confirm it paints in editor **and** frontend.
- Confirm the mirrored variation CSS (`.wp-block-…​.is-style-…`, specificity
  0-2-0) wins over each block's own `style.scss`. Note that several targets
  ship their own default background at the same specificity — `card`'s
  `&:not([style*="background"])` is ~0-2-0, tying the mirrored selector — so
  when specificity ties, source/cascade order decides. Spot-check every target
  that ships a default background, not just `flip-card-face` / `counter`:
  `card`, `accordion-item`, `scroll-accordion-item`, `tab`, `modal`, `slide`,
  `scroll-slide`, `fifty-fifty`, `timeline-item`.

## Part B — Stop baking hex defaults into save()

Emit the inline color **only when the user set a value**. When unset, emit
nothing and let the block's `style.scss` provide the visual default via a
low-specificity `:where()` rule that references a preset var — so the block
still looks right out-of-box while serialized HTML stays free to inherit from
FSE global styles.

Scope: only the two blocks that bake a *pure* hex (no preset var).
`card` / `timeline` / `timeline-item` already use
`var(--wp--preset--color--X, #hex)` — they inherit already; hex is a harmless
last-resort. **Left as-is.**

### progress-bar — [save.js:43,59](../../src/blocks/progress-bar/save.js)

```js
// before
backgroundColor: convertColorToCSSVar(barColor) || '#2563eb',
// after — omit key entirely when unset
...(convertColorToCSSVar(barColor) && {
    backgroundColor: convertColorToCSSVar(barColor),
}),
```

Move the fallback into `style.scss`:

```scss
.wp-block-designsetgo-progress-bar__bar:where(:not([style*="background"])) {
    background-color: var(--wp--preset--color--primary, #2563eb);
}
```

Same treatment for `barBackgroundColor` (`#e5e7eb`) → track color default.

### image-accordion-item — [save.js:14](../../src/blocks/image-accordion-item/save.js)

Overlay color comes from parent context; when the parent sets none, emit no
overlay color inline and let CSS default it.

### Deprecations (required — save() output changes)

Old content has the literal hex in its HTML, so it will not match the new
`save()`. Each changed block needs a full deprecation
(`isEligible` + `save` + `migrate`). Per the WP 6.9 note
(`reference_wp_deprecation_iseligible_mechanics`), the deprecation `save()`
must reproduce the stored HTML exactly.

- `isEligible(attributes, _, { innerHTML })` — the pre-change save baked the
  hex literal into the markup, so match on `innerHTML` alone:
  `innerHTML.includes('#2563eb') || innerHTML.includes('#e5e7eb')`. No
  attribute check is needed: a block whose color was a genuine custom
  `#2563eb` round-trips through the *current* save (matching the stored
  markup), so it validates and never reaches this path — and even if it did,
  `migrate` is a passthrough no-op.
- `save()` — the current (pre-change) save function verbatim.
- `migrate()` — passthrough (`return attributes;`); attribute schema is
  unchanged, only the emitted default moves to CSS.

progress-bar has two color defaults, so its deprecation must cover both.

### Verification

- Insert a progress bar / image accordion with default colors on an existing
  saved page; confirm silent auto-migration (no "Attempt Recovery").
- `npm run build && npm run lint:js && npm run lint:css && npm run lint:php`.
- Editor + frontend + responsive; browser console clean.
- Run the e2e `dynamic-render` / content-reset guardrail specs.
```
