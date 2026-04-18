---
name: add-block
description: Create a new Gutenberg block with scaffolding
argument-hint: [block-name]
allowed-tools: Read, Write, Edit, Glob, Grep, Bash(mkdir *), Bash(npm run *)
---


Create a new Gutenberg block following WordPress best practices.

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

Before generating any block code, check `src/hooks/` and `src/components/shared/` for primitives that already cover the patterns you're about to write. The plugin maintains shared building blocks specifically to keep new blocks consistent with the rest of the codebase. See the **Shared Primitives First** and **Variation vs New Block** sections of `.claude/CLAUDE.md` for the full list and the variation-vs-block decision rule.

If a new block differs from an existing one only by 1–3 attributes and shares the same `save()` output, register a variation in the existing block's `block.json` instead of creating a new block.

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

## After Creation

Block will be auto-detected by webpack - no need to modify `src/index.js`.

If dynamic rendering is used, add PHP registration in `includes/class-plugin.php`.

## Build and Test

```bash
npm run build
```

Test in both editor and frontend.

## Reference

See [BEST-PRACTICES-SUMMARY.md](../../docs/BEST-PRACTICES-SUMMARY.md) for complete patterns.
