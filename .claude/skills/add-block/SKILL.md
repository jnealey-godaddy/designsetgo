---
name: add-block
description: Use when adding a new DesignSetGo block or a variation of an existing one — decides block vs. variation, then scaffolds following the plugin's conventions
argument-hint: [block-name]
allowed-tools: Read, Write, Edit, Glob, Grep, Bash(mkdir *), Bash(npm run *)
---


Create a new Gutenberg block following WordPress best practices.

## Pre-flight: block or variation?

Search `src/blocks/*` for anything conceptually similar. If the idea differs from an existing block only by 1–3 attributes **and shares the same `save()` output**, register a variation instead — no new block, no deprecation debt (see "Variations vs. new blocks" in `.claude/claude.md`):

- Put the variations in `src/blocks/{block}/variations.js`.
- Register them the way that block's `index.js` already does: pass `variations` into `registerBlockType()` (see `modal/`), or loop `registerBlockVariation( metadata.name, variation )` after registration (see `query-filter/`).
- Use WordPress presets in variation attributes (`var(--wp--preset--spacing--50)`), never hardcoded values.

Only when markup, inner-block structure, or behaviour actually differs, scaffold a new block below.

## Ask the User For

- **Block name** (e.g., "accordion", "testimonial-slider")
- **Block category** (e.g., "design", "widgets", "text", "media")
- **Needs frontend JavaScript?** (Yes/No)
- **Needs dynamic rendering (PHP)?** (Yes/No)

## What Gets Created

1. Block directory: `src/blocks/[block-name]/`
2. `block.json` with proper metadata and attributes
3. `index.js` to register the block
4. `edit.js` with editor controls
5. `save.js` with frontend markup
6. `style.scss` for frontend styles
7. `editor.scss` for editor-only styles
8. `frontend.js` (if needed for interactivity)
9. `render.php` (if dynamic rendering needed)

## Before Scaffolding — Check Shared Primitives

Check `src/hooks/` and `src/components/shared/` first. See **Shared Primitives First** in `.claude/claude.md` for the list.

## Critical Patterns to Follow

**ALWAYS use these in edit.js:**
- `useBlockProps()` for block wrapper
- `useInnerBlocksProps()` for nested blocks (NOT plain `<InnerBlocks />`)
- Declarative styling (NO `useEffect` for styles)

**ALWAYS include in block.json:**
- Comprehensive `supports` for FSE compatibility
- `example` property for pattern library
- WordPress presets (no hardcoded colors/spacing)

**Color controls:**
- Use `ColorGradientSettingsDropdown` (NOT `PanelColorSettings`)
- Place in `<InspectorControls group="color">`
- Require `clientId` parameter in edit function

**Inspector layout (Theme 3 IA):**
- Custom controls go in `<DsgoInspectorPanel>` (a `ToolsPanel` wrapper), never bare `PanelBody`
- Exactly two panels, in order: `panelName="settings"` (title `Settings`) then `panelName="style"` (title `Style`) — no block-name prefix
- Pass `panelId={clientId}`; wrap each control in `<DsgoInspectorPanel.Item label hasValue onDeselect isShownByDefault>` with `isShownByDefault` always `true`
- Color / HTML element / anchor / class stay in `<InspectorControls group="color">` / `group="advanced">` — don't duplicate them inside Settings or Style

**Horizontal positioning:** never use `supports.align: ["left","center","right"]` to position a block — that's for `wide`/`full` bleed only. Use the justification pattern instead: block root gets `.dsgo-justify`/`.dsgo-justify--{left|center|right}` from a `justification` attribute (see `getJustificationClass()` in `src/utils/justification.js` and `<DsgoJustificationToolbar>`), with the visible element shrink-wrapped inside it.

## After Creation

The block is auto-detected from `build/blocks/*/block.json` by `includes/blocks/class-loader.php` — no manual PHP registration needed, including for dynamic rendering: a `render.php` file in the block's own directory is picked up automatically. No changes to `src/index.js` or any PHP file are required for a standard block.

## Build and Test

```bash
npm run build
```

Test in both editor and frontend.

## Reference

See [BEST-PRACTICES-SUMMARY.md](../../../docs/guides/BEST-PRACTICES-SUMMARY.md) for complete patterns.
