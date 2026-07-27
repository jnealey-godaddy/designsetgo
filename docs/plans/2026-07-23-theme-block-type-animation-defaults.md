# Design: Global per-block-type animation defaults ("theme animations")

**Date:** 2026-07-23
**Status:** Approved design — ready for implementation plan
**Author:** jnealey

## Problem

Animations are currently set **per block instance** via the block-animations extension
(`src/extensions/block-animations/`). There is no way to say "every button on this site
should fade in" once, at a theme level, and have blocks inherit it. Authors must enable and
configure animation on each block by hand.

We want a **global, per-block-type** animation default: set `core/button → Fade In Up` once,
and every button inherits it automatically unless that specific button overrides or opts out.
These defaults must be authorable in **theme.json global styles** (by theme devs / Style Kits)
**and** in the plugin's Admin Settings UI (by site builders).

## Decisions (locked during brainstorming)

1. **Granularity:** per block *type* — a map of `blockName → full animation config`.
2. **Config richness:** each entry carries the **full** animation config (entrance, exit,
   trigger, duration, delay, easing, offset, once), mirroring the per-block panel.
3. **Config surface:** **both** — theme.json `settings.custom.designsetgo.blockAnimations`
   is a first-class source, and the Admin Settings UI edits the same namespace by projecting
   its stored option into the theme.json layer (the existing `defaultIconButtonHover`
   mechanism in `Admin\Global_Styles`).
4. **Inherit model:** **opt-out / automatic** — a block type's default applies to *every*
   block of that type on the site, including existing content never re-saved, unless the
   block overrides (Custom) or opts out (Off).
5. **Type keying:** **exact block name** (e.g. `core/button`), with optional
   `namespace/*` wildcards (same syntax `excludedBlocks` already uses). Respects the
   existing `excludedBlocks` list. One entry may target **several** block names, so a
   single animation rule can cover e.g. every button variant on the site.
6. **Editor behavior:** inherited animation is shown as an **indicator** in the inspector,
   not played live in the canvas (consistent with today, where animations don't run in the
   editor).
7. **Master gate:** a `blockAnimationsEnabled` boolean, default **off** — the feature is a
   deliberate opt-in.

## Non-goals (YAGNI)

- Named/reusable animation presets ("Subtle" / "Bold").
- Per-post or per-template overrides of the global defaults.
- Playing inherited animations live in the editor canvas.
- Changing the existing per-block animation controls or their runtime.

---

## Existing architecture this builds on

- **Extension:** `src/extensions/block-animations/` — three-filter WP pattern
  (`blocks.registerBlockType` adds attributes; `editor.BlockEdit` adds controls;
  `blocks.getSaveContent.extraProps` bakes classes/`data-*` at save). Applies to all blocks
  except a skip list and the user `excludedBlocks` list (via
  `src/utils/should-extend-block.js`).
- **Per-block attributes** (all default-valued, so absent from block comments when default):
  `dsgoAnimationEnabled` (bool, `false`), `dsgoEntranceAnimation` (`''`),
  `dsgoExitAnimation` (`''`), `dsgoAnimationTrigger` (`'scroll'`),
  `dsgoAnimationDuration` (`600`), `dsgoAnimationDelay` (`0`),
  `dsgoAnimationEasing` (`'ease-out'`), `dsgoAnimationOffset` (`100`),
  `dsgoAnimationOnce` (`true`).
- **Runtime:** save-time classes (`has-dsgo-animation`, `dsgo-animation-{entrance}`,
  `dsgo-animation-exit-{exit}`) + `data-*` attributes, driven by vanilla JS
  (`frontend.js`, IntersectionObserver) and gated CSS (`animations.scss`,
  scoped to `body:not(.block-editor-page):not(.wp-admin)`, with a
  `prefers-reduced-motion` bail).
- **Server-side helper (currently dead code we will now use):**
  `designsetgo_get_animation_attributes( $attributes )` in
  `includes/data/block-animation-attributes.php` — returns `{ classes, attrs }` matching the
  save-time output. **Returns empty unless `$attributes['dsgoAnimationEnabled']` is truthy.**
- **theme.json projection:** `Admin\Global_Styles::extend_theme_json()`
  (`includes/admin/class-global-styles.php`) hooks `wp_theme_json_data_theme` and injects
  `settings.custom.designsetgo.*`, including `defaultIconButtonHover` sourced from the admin
  option. Uses a "only inject as a fallback when the theme hasn't defined it" guard for
  spacing/fonts.
- **Global settings read path:** PHP `wp_get_global_settings(['custom','designsetgo',...])`,
  JS `useSettings('custom.designsetgo...')` — both resolve the merged theme.json (theme +
  Style Kit + plugin-injected layer). Precedent: `Icon_Injector::get_icon_defaults()`.
- **render_block injector precedent:** `includes/features/class-svg-pattern-renderer.php`
  and `includes/features/class-button-global-styles.php` — hook `render_block`, mutate the
  outer element with `WP_HTML_Tag_Processor`.
- **Admin settings:** single option `designsetgo_settings`
  (`includes/admin/class-settings.php`, `OPTION_NAME`), `animations` section already holds
  `default_icon_button_hover`; REST `designsetgo/v1/settings`; typed sanitization schema in
  `get_sanitization_schema()`; JSON schema mirror in `class-settings-schema.php`. React
  panel: `src/admin/components/settings-panels/AnimationsPanel.js`.
- **Editor localization:** `window.dsgoSettings` is localized on `enqueue_block_assets`
  (NOT `enqueue_block_editor_assets`) so it reaches the editor iframe — see the known
  iframe-localize gotcha.

---

## Data model

### theme.json contract (first-class source)

```jsonc
"settings": { "custom": { "designsetgo": {
  "blockAnimationsEnabled": true,
  "blockAnimations": [
    { "blocks": ["core/button", "designsetgo/icon-button"],
      "entrance": "fadeInUp", "exit": "",
      "trigger": "scroll", "duration": 600, "delay": 0,
      "easing": "ease-out", "offset": 100, "once": true },
    { "blocks": ["core/image"],    "entrance": "zoomIn", "duration": 800 },
    { "blocks": ["designsetgo/*"], "entrance": "fadeIn" }
  ]
} } }
```

- **An array of entries**, not an object keyed by block name. Block names contain `/`, which
  would generate malformed `--wp--custom--designsetgo--…` CSS custom properties. Array
  indices (`--…--block-animations--0--entrance`) are valid and harmless.
- Each entry: `blocks` (required, a list of exact names and/or `namespace/*` wildcards) plus
  any subset of the animation config fields. Every block listed shares that one config.
  Omitted fields fall back to the extension's own attribute defaults
  (scroll / 600 / 0 / ease-out / 100 / once=true).
- The singular `block: "core/button"` form is still accepted and normalized to a
  one-element `blocks` list, so theme.json authored against the first shape keeps resolving.
- A block name may appear in only one entry. If two entries claim it, the later one wins
  (the resolver builds a `name → config` map); the admin sanitizer strips the earlier
  claim so the stored list can't contain a rule that never takes effect.
- `blockAnimationsEnabled` is the master gate.

### Admin option storage

Extend the `animations` section of `designsetgo_settings`:

```php
'animations' => array(
    'default_icon_button_hover'   => '…',   // existing, untouched
    'respect_reduced_motion'      => true,  // existing
    'block_animations_enabled'    => false, // NEW — master gate, default off
    'block_animations'            => array( // NEW — list of entries
        array(
            'block'    => 'core/button',
            'entrance' => 'fadeInUp',
            'exit'     => '',
            'trigger'  => 'scroll',
            'duration' => 600,
            'delay'    => 0,
            'easing'   => 'ease-out',
            'offset'   => 100,
            'once'     => true,
        ),
        // …
    ),
),
```

Sanitized in `get_sanitization_schema()` / `class-settings-schema.php`: `block` via a
block-name-safe sanitizer (allow `a-z0-9-/*`), `entrance`/`exit`/`trigger`/`easing` against
the known enum lists from `constants.js`, numeric fields clamped to their existing ranges,
`once` cast to bool. Unknown entries dropped.

### Admin → theme.json projection

`Global_Styles::extend_theme_json()` writes `blockAnimationsEnabled` + `blockAnimations` from
the admin option into `settings.custom.designsetgo`, exactly as it already does for
`defaultIconButtonHover`. See **Precedence** for the guard.

---

## Per-block state & backward compatibility

The per-block state is a **tri-state derived from one new attribute plus the existing one** —
chosen specifically so existing content and its stored HTML never change (no deprecation, no
"Attempt Recovery").

| State | Condition | Save-time output |
|-------|-----------|------------------|
| **Custom** | `dsgoAnimationEnabled === true` (existing attr, unchanged) | Bakes classes/`data-*` **exactly as today** |
| **Off** | new `dsgoAnimationOptOut === true` | Nothing |
| **Inherit** (default) | neither set | Nothing at save; classes injected at render |

- **New attribute:** `dsgoAnimationOptOut` (bool, default `false`), added to the extension's
  attribute set in both `attributes.js` and `includes/extension-configs/block-animations.php`.
- **Why no deprecation is needed:** existing animated blocks already carry
  `dsgoAnimationEnabled: true` and their baked classes; the Custom path is byte-for-byte
  unchanged. Inherit/Off blocks emit nothing at save — identical to today's non-animated
  blocks. The only new behavior is **render-time** injection, which never touches stored
  markup. A round-trip test will pin this invariant.
- **Accepted consequence of opt-out:** a block a user "turned off" before this feature is
  today indistinguishable from "untouched" (both = default/absent), so once a default exists
  for its type it will begin inheriting. There was no explicit "off" state before; the new
  **Off** control provides one going forward.

---

## Resolution & precedence

Effective animation for a rendered block — **first match wins**:

1. **Per-block Custom** (`dsgoAnimationEnabled === true`) → use the block's own config.
2. **Per-block Off** (`dsgoAnimationOptOut === true`) → no animation.
3. **Inherit** → look up the merged `blockAnimations` for the block's type
   (**exact name first, then `namespace/*` wildcard**); if the master gate is on and an entry
   matches → apply that config.
4. Otherwise → static.

### Global-source precedence (the one open decision)

Both the injector (PHP) and the editor (JS) read the **merged** value via
`wp_get_global_settings` / `useSettings`, so theme.json and Style Kits compose automatically.
The remaining question is admin-option vs theme.json ordering.

**Recommended default (matches `defaultIconButtonHover`):** the admin option, when it contains
entries, is projected into the theme layer and **overrides** a theme's own theme.json
`blockAnimations`; when the admin list is empty the projection is skipped so the theme's
theme.json value passes through untouched. Style Kits (injected at a higher layer) still win
over both. Net order, highest → lowest:

```
per-block Custom / Off
  → Style Kit  (settings.custom.designsetgo.blockAnimations)
    → Admin Settings option  (projected, only when non-empty)
      → theme theme.json  (settings.custom.designsetgo.blockAnimations)
        → none (static)
```

Rationale: a site owner editing wp-admin expects their setting to take effect; a theme ships
baseline defaults; a Style Kit is the most specific design-system intent. The exact injection
layer (`wp_theme_json_data_theme` unconditional-when-non-empty vs `wp_theme_json_data_user`)
will be chosen during implementation to realize this order, verified against how
`defaultIconButtonHover` resolves today. **This ordering is the single item to confirm at spec
review** — the alternative (theme.json overrides admin) is a one-line guard flip.

### Server-side injector

New feature class (e.g. `includes/features/class-animation-defaults-injector.php`,
`DesignSetGo\Animation_Defaults_Injector`) hooked on `render_block`:

1. Bail fast when the master gate is off or the merged `blockAnimations` list is empty
   (build the lookup map once per request; O(1) per block by name).
2. Skip blocks in Custom or Off state, blocks already carrying `has-dsgo-animation`, and any
   block in `excludedBlocks`.
3. Resolve the entry for `$block['blockName']` (exact, then wildcard).
4. **Synthesize** an attributes array from the resolved entry with
   `dsgoAnimationEnabled => true` + the entry's fields, pass it to
   `designsetgo_get_animation_attributes()` (which requires enabled=true — hence the
   synthesis), and apply the returned classes / `data-*` to the block's outer element via
   `WP_HTML_Tag_Processor` (add_class + set_attribute), following the
   `class-svg-pattern-renderer.php` pattern.

Reduced motion and the actual animation playback are handled by the **existing** frontend JS
and CSS unchanged (the injected markup is identical to hand-authored animated markup). The
existing conditional frontend-asset enqueue already fires when `dsgo-` classes/attributes are
present in rendered content.

---

## Editor inspector UX

In `AnimationPanel.js`, the current "Enable" toggle becomes a **three-way control:
Inherit (theme) · Custom · Off**.

- **Inherit** (default): show a read-only indicator — e.g.
  *"Inheriting theme animation: Fade In Up · scroll · 600ms"* — resolved from the localized
  defaults for this block's type. Mirrors the icon-block "Inheriting theme default" pattern
  (`placeholder` + `help` text). When no default exists for the type, indicate
  *"No theme animation for this block type."* No canvas playback.
- **Custom**: sets `dsgoAnimationEnabled = true` and reveals the existing full control set
  (unchanged).
- **Off**: sets `dsgoAnimationOptOut = true`, collapses the controls.

Switching between states clears the other state's attribute so the tri-state stays
well-defined (Inherit = both cleared).

The defaults map + master gate are localized to the editor as
`window.dsgoSettings.blockAnimations` / `.blockAnimationsEnabled` on **`enqueue_block_assets`**
(iframe reachability). The `AnimationToolbar` quick-picker keeps working; picking a preset
puts the block into Custom state.

---

## Admin Settings UI

Extend `src/admin/components/settings-panels/AnimationsPanel.js`:

- A master **"Enable theme animation defaults"** toggle (`block_animations_enabled`).
- A **repeater** of rows: each row is `[block-type picker] → [full animation config]`, reusing
  the existing animation-control components (entrance/exit/trigger/duration/delay/easing/
  offset/once). Add / remove / reorder rows.
- Block-type picker: searchable over registered block types (respecting `excludedBlocks`),
  and accepts a free-typed `namespace/*` wildcard.
- Persisted through the existing `designsetgo/v1/settings` REST route with the new
  sanitization branch.

---

## Testing

- **Resolution precedence:** Custom > Off > Inherit; exact name > wildcard; master-gate-off →
  no injection.
- **Global-source precedence:** admin option vs theme.json fixture vs Style-Kit layer resolve
  in the documented order.
- **Injector parity:** injected classes/`data-*` for an inherited block equal the save-time
  output for the same config (compare against `designsetgo_get_animation_attributes()`).
- **Backward-compat invariant:** existing animated content (Custom) round-trips
  `parse → serialize` byte-identical; no block becomes invalid; extend
  `tests/unit/deprecations-isEligible.test.js`-style coverage as needed.
- **PHP sanitization:** malformed entries in the option / theme.json are dropped; enums and
  numeric ranges enforced.
- **Editor:** tri-state control transitions; inherited indicator text; localization reaches
  the iframe canvas.
- **Reduced motion:** injected blocks respect `prefers-reduced-motion` (inherited from
  existing CSS/JS — assert markup, not motion).
- **Manual/e2e:** set `core/button → Fade In Up`, confirm an un-re-saved button animates on
  the front end and a per-button Off opts it out.

## Files touched (anticipated)

- `src/extensions/block-animations/attributes.js` — add `dsgoAnimationOptOut`.
- `src/extensions/block-animations/editor.js` + `components/AnimationPanel.js` — tri-state
  control + inherited indicator; read localized defaults.
- `includes/extension-configs/block-animations.php` — add `dsgoAnimationOptOut` to server
  attribute schema.
- `includes/features/class-animation-defaults-injector.php` — **new** render_block injector.
- `includes/data/block-animation-attributes.php` — reuse (possibly a small helper to apply
  computed classes/attrs via `WP_HTML_Tag_Processor`).
- `includes/admin/class-settings.php` + `class-settings-schema.php` — new option fields +
  sanitization.
- `includes/admin/class-global-styles.php` — project `blockAnimations` /
  `blockAnimationsEnabled` into theme.json (guarded per precedence decision).
- `includes/core/class-assets.php` — localize defaults to the editor on
  `enqueue_block_assets`.
- `src/admin/components/settings-panels/AnimationsPanel.js` — repeater UI.
- Plugin bootstrap (`includes/class-plugin.php`) — instantiate the injector.
- Tests as above; CHANGELOG entry.

## Open decision for review

**Admin option vs theme.json precedence** (see Resolution & precedence). Recommended default:
admin option (when non-empty) overrides theme theme.json; Style Kits override both. Confirm or
flip.
