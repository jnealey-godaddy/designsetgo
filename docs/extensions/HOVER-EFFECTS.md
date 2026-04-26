# Hover Effects Extension

**Added in 2.1.0**

Adds a single-pick CSS hover micro-interaction to any supported block. No JavaScript — effects are pure CSS transitions applied via class names on the block's root element.

## How it works

The extension registers a `dsgoHoverEffect` attribute on each supported block. When saved, the chosen effect's class (`dsgo-hover-effect dsgo-hover-effect--{value}`) is written directly into the block's markup. The editor canvas applies the same classes so the hover state is previewable while editing.

## Inspector controls

Select a block, then open **Hover Effect** in the block sidebar (collapsed by default).

**Effect** — choose one preset from the dropdown:

| Option | Behavior |
|--------|----------|
| None (default) | No hover animation |
| Lift | Block translates upward and casts a shadow |
| Sink | Block translates downward |
| Grow | Block scales up slightly |
| Shrink | Block scales down slightly |
| Tilt | Block rotates slightly on the Z axis |
| Glow | Block emits a color glow |

## Supported blocks

The extension applies to the following core blocks:

- `core/group`
- `core/cover`
- `core/column` / `core/columns`
- `core/image`
- `core/button` / `core/buttons`
- `core/media-text`
- `core/post-template`
- `core/query`

## Frontend behavior

Effect classes are serialized into saved markup. The CSS transitions live in the plugin's shared stylesheet. No view script is loaded — nothing runs on the frontend beyond the CSS rules that respond to `:hover`.

## Notes

- Only one effect can be active per block. To clear an effect, set the dropdown back to **None**.
- The extension respects the Block Manager's enabled-extensions allowlist.
- The extension does not apply to `core/freeform`, `core/missing`, or template-part blocks.
