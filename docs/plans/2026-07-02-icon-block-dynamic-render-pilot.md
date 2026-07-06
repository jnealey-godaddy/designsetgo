# Icon Block → Dynamic Render (Pilot)

**Date:** 2026-07-02
**Status:** Design — pending approval to implement
**Related:** [Block Authorability Audit](../reviews/2026-07-02-block-authorability-audit.md)

## Goal

Convert `designsetgo/icon` from a static block to a **server-rendered (dynamic)** block so its saved form collapses to a single self-closing comment:

```html
<!-- wp:designsetgo/icon {"icon":"star"} /-->
```

This is the reference implementation for a broader "dynamic-first for leaf visual blocks" standard. Success = an LLM (or any programmatic author) can hand-write that one line and it renders as valid, correct HTML, with no "unexpected/invalid content" warning and no client-side icon injection.

## Why this is cheap (key finding)

The SVG library **already exists in PHP** and is already the frontend's source of truth:

- `includes/data/icon-svg-library.php` → `designsetgo_get_icon_svg( $name )` returns SVG markup (handles alias resolution).
- `includes/features/class-icon-injector.php` already localizes `dsgoIcons` and computes theme default style via `IconInjector::get_icon_defaults()`.

Today the flow is: `save()` emits an empty `.dsgo-lazy-icon` placeholder → `lazy-icon-injector.js` swaps in the SVG **client-side** (risking FOUC/CLS). We are moving that swap to the server. No new icon data is authored.

## Design decisions

### D1 — Rendering model
Add `"render": "file:./render.php"` to `block.json` (matching the 13 existing dynamic blocks). Remove `save.js`; the block serializes attributes only. Block supports (color/border/spacing/align) are applied server-side via `get_block_wrapper_attributes()` — the PHP equivalent of `useBlockProps.save()`.

### D2 — What `render.php` emits
Keep the existing structural classes so current CSS and parent-hover selectors (`.dsgo-icon`, `.dsgo-icon__wrapper`) keep working, but:
- Real SVG inline (not a placeholder), from `designsetgo_get_icon_svg()`.
- Static layout styles (`display:flex; align-items/justify-content:center`) move **out of inline style into `style.scss`** (they were identical on every instance).
- Per-instance values become CSS: `--dsgo-icon-size` for size; `transform: rotate()` only when rotation ≠ 0.

Sketch:
```php
$icon    = $attributes['icon'] ?? 'star';
$style   = $attributes['iconStyle'] ?? IconInjector::get_icon_defaults()['style'];
$size    = (int) ( $attributes['iconSize'] ?? 48 );
$svg     = designsetgo_render_icon_svg( $icon, $style, $attributes['strokeWidth'] ?? 1.5 );
$wrapper = get_block_wrapper_attributes( array(
    'class' => 'dsgo-icon',
    'style' => "--dsgo-icon-size:{$size}px;",
) );
// aria + optional <a> wrap, then echo.
```

### D3 — Outlined style + strokeWidth (the one real porting task)
The JS `getIcon(name, 'outlined', strokeWidth)` transforms the stored (filled) SVG into an outlined variant. PHP's `designsetgo_get_icon_svg()` currently returns the **filled** markup only. We add a small `designsetgo_render_icon_svg( $name, $style, $stroke )` helper that applies the same fill→stroke transform server-side (fill="none", stroke="currentColor", stroke-width=$stroke). **This is the item to verify against the JS output byte-for-behavior.**

### D4 — Link / aria / decorative
Port from `save.js` to PHP: `esc_url()` (covers the dangerous-protocol check), `target`/`rel` handling, and the aria logic (decorative → `role=presentation` + `aria-hidden`; explicit `ariaLabel`; else humanized icon-name fallback).

### D5 — Theme default style inheritance
When `iconStyle` is unset, inherit `settings.custom.designsetgo.icon.defaultStyle` via the existing `IconInjector::get_icon_defaults()`. Preserves current behavior.

### D6 — Existing content migration (one deprecation)
Switching static→dynamic makes stored inner-HTML mismatch the now-null `save()`, which the **editor** flags as invalid (frontend is fine — dynamic render ignores stored HTML). Per project standards, add ONE new entry to `deprecated.js` with all three:
- `isEligible`: `innerHTML && innerHTML.includes('dsgo-lazy-icon')` (old placeholder signature).
- `save`: the current `save.js` output verbatim (moved in).
- `migrate`: passthrough `return attributes;` (schema unchanged).

### D7 — Frontend injector stays (scoped out for icon only)
`icon-button`, `modal-trigger`, `icon-list-item`, `divider`, `tabs` still use `dsgo-lazy-icon`. Do **not** remove the injector. Just remove `designsetgo/icon` from the injector's placeholder-trigger list in `class-icon-injector.php`; since `render.php` no longer emits `.dsgo-lazy-icon`, the injector simply no-ops for icon.

### D8 — Editor unchanged
`edit.js` already renders the SVG client-side via `getIcon()`. No `ServerSideRender` needed. JS `svg-icons.js` and PHP `icon-svg-library.php` are both generated from the same source, so editor preview and frontend stay visually identical. Keep `svg-icons.js` (editor still needs it).

## Files touched

| File | Change |
|---|---|
| `src/blocks/icon/render.php` | **new** — server render |
| `src/blocks/icon/block.json` | add `"render"`, drop save-based fields as needed |
| `src/blocks/icon/save.js` | **removed** (logic preserved in deprecation) |
| `src/blocks/icon/deprecated.js` | +1 entry (isEligible/save/migrate) |
| `src/blocks/icon/style.scss` | absorb static layout styles from old save |
| `includes/data/icon-svg-library.php` | add `designsetgo_render_icon_svg()` (outlined transform) |
| `includes/features/class-icon-injector.php` | drop `designsetgo/icon` from placeholder-trigger list |
| `src/blocks/icon/edit.js` | unchanged |

## Test plan

1. **Fresh insert** — insert icon, set size/rotation/outlined/stroke/link/decorative; confirm frontend renders identically to pre-change (screenshot diff).
2. **Authorability** — paste `<!-- wp:designsetgo/icon {"icon":"heart","iconSize":72} /-->` into code editor → renders, no warning.
3. **Existing content** — open a pre-change post containing icons → silent migration, no "Attempt Recovery."
4. **Outlined parity** — compare outlined+strokeWidth output vs current JS render for several icons.
5. **Block supports** — color (text/background), border radius, spacing, align (left/center/right/wide/full) all still apply.
6. **Theme default** — with `iconStyle` unset and a kit setting `defaultStyle: outlined`, confirm inheritance.
7. **No injector for icon** — network/DOM shows no `.dsgo-lazy-icon` for icon; other icon-using blocks still inject.
8. **Build/lint** — `npm run build`, `lint:js`, `lint:css`, `lint:php`; check editor + frontend consoles.

## Rollout after pilot
If clean, this render.php + deprecation shape becomes the template for the ranked leaf conversions: `icon-button` (1,295 dep lines), `countdown-timer`, `modal-trigger`, `map`, `counter`/`counter-group`, `table-of-contents`, `progress-bar`, `scroll-marquee`, `divider`, `pill`.
