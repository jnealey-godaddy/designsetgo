# DesignSetGo Plugin - Quick Reference

**Core Principle**: Use WordPress defaults first. Ask "Does WordPress already provide this?" before building custom solutions.

## Code Standards

- **Indentation**: Tabs for JS/SCSS/PHP, 2 spaces for JSON/YAML
- **Prefix**: `dsgo-` for CSS/data attributes, `dsgoAttributeName` for JS, `designsetgo_` for PHP
- **File Size**: Max 300 lines (excluding data/constants)
- **Block Props**: Always use `useBlockProps()` and `useInnerBlocksProps()`
- **Block JSON**: Use `apiVersion: 3` and `textdomain: "designsetgo"`
- **Color Controls**: Use `ColorGradientSettingsDropdown` in `<InspectorControls group="color">` (requires `clientId` prop)
- **Future-Proof**: Add `__next40pxDefaultSize` and `__nextHasNoMarginBottom` to form components
- **Block Supports**: Use `supports` in block.json before custom controls
- **No console.log**: Remove all `console.log` statements before commit

## Architecture

- **Categories**: Use WordPress core categories (`"category": "design"`), plus custom collection
- **Extensions**: Use `addFilter()` with explicit block name allowlist
- **File Structure**: `src/blocks/{block}/` → index.js (registration), edit.js, save.js, components/, utils/
- **Asset Loading**: Enqueue in `enqueue_block_assets` with `is_admin()` check

### Shared Primitives First

Before adding a pattern to a block, check `src/hooks/` and `src/components/shared/`. If it's the second time you're writing a pattern, extract it. Available primitives:

- `useUniqueBlockId({ clientId, attributeName, value, setAttributes, prefix?, length? })` — seeds a stable id attribute from clientId.
- `useBlockColors({ attributes, setAttributes, entries })` — wraps `ColorGradientSettingsDropdown` boilerplate.
- `useTablistKeyboard({ count, activeIndex, onChange, orientation? })` — ARIA tablist keyboard nav.
- `cssVars(attributes, map)` — pure attribute → CSS-var inline-style mapper (in `src/utils/`).
- `<DsgoInspectorPanel>` — `ToolsPanel` wrapper enforcing the 3-panel inspector convention (Settings / Style / Advanced).
- `<DsgoBlockPlaceholder>` — first-insert wizard for compound blocks.
- `<DsgoChildToolbar>` — Add/Duplicate/Move/Remove for child blocks of compound parents.

### Variations vs. new blocks

Before registering a new block, check whether the idea fits a variation on an existing one. **If a new block would differ from an existing block only by 1–3 attributes and share the same `save()` output structure, register a variation (`registerBlockVariation`), not a new block.** The shared-`save()` constraint is the real technical gate: variations cannot carry differing markup, so differing output always forces a separate block + deprecations.

When in doubt prefer variations — they have no migration cost, no deprecation debt, and stay invisible to `save()` validation. Reach for a new block only when markup, inner-block structure, or block-level behaviour actually differs.

For sibling blocks that already exist and meet the "1–3 attribute difference + shared save" rule (e.g. `flip-card-front` / `flip-card-back` → `flip-card-face` with a `side` attribute), consolidate via a new block + `inserter: false` on the legacy names + `transforms.to` on each legacy block pointing to the new one. Keep the legacy blocks registered so existing content keeps rendering.

### Inspector IA (Theme 3)

Three panels per block, in this order: **Settings** → **Style** → **Advanced**. Use `<DsgoInspectorPanel>` (the `ToolsPanel` wrapper) for all custom inspector controls; never reach for `PanelBody` directly.

- `title` is `__('Settings', 'designsetgo')` or `__('Style', 'designsetgo')` — no block-name prefix (no more "Grid Settings", "Tab Settings").
- `panelName` is `'settings'` or `'style'` — DsgoInspectorPanel warns once per unrecognised value.
- `panelId={clientId}` — required so reset state scopes per block instance.
- Wrap every control in `<DsgoInspectorPanel.Item label hasValue onDeselect isShownByDefault>`. `hasValue` returns `true` when the attribute differs from the `block.json` default; `onDeselect` resets it. **`isShownByDefault` is `true` on every item** — authors need every control visible without hunting through `ToolsPanel`'s kebab menu. The ⋮ reset-per-control affordance still works.
- Color stays in `<InspectorControls group="color">`; HTML element / anchor / class stay in `<InspectorControls group="advanced">`. Do not duplicate them inside Settings or Style.
- Prefer native `supports` (color / typography / spacing / border) over custom controls whenever possible.

See [`docs/plans/2026-04-17-theme-3-inspector-ia.md`](../docs/plans/2026-04-17-theme-3-inspector-ia.md).

## Security

- **Input**: Validate all user input
- **Output**: Escape with `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- **Forms**: Use nonce verification and capability checks
- **Direct Access**: Add `defined('ABSPATH') || exit;` to PHP files
- **No XSS**: Never use `innerHTML` with unsanitized data
- **No SQL Injection**: Use `$wpdb->prepare()` for all queries

## Accessibility

- **Keyboard**: All interactive elements accessible via keyboard
- **Screen Readers**: Use alt text, aria-labels, semantic HTML
- **Contrast**: WCAG AA minimum (4.5:1 normal text, 3:1 large text)
- **Focus**: Visible focus indicators on all interactive elements
- **Headings**: Proper hierarchy (don't skip levels)

## Internationalization

- **Text Domain**: `designsetgo`
- **PHP Strings**: `__('text', 'designsetgo')`, `esc_html__()`, `esc_attr__()`
- **JS Strings**: Import from `@wordpress/i18n`
- **No Concatenation**: Use `sprintf(__('Hello %s', 'designsetgo'), $name)`

## Safety Rules

### Shared Code Changes

1. `grep -r "ComponentName" src/` to find ALL usages
2. Test affected blocks (Container: Stack/Flex/Grid, Interactive: Accordion/Tabs, Styled: Icon/Pill, List: Icon List)
3. `npm run build` + check console (editor + frontend)

### CSS/JS Scope

- **CSS**: Use `:where()` for low specificity, scope to `.wp-block-designsetgo-{block}`
- **JS**: Use `[data-dsgo-*]` selectors, event delegation with `.closest()`

### Deprecations

Required when changing: attribute schema, HTML structure, or removing attributes.

**Every deprecation MUST have all three**: `isEligible`, `save`, and `migrate`.

- `isEligible(attributes, innerBlocks, { innerHTML })` — Lets WordPress skip save validation and go straight to migration (silent, no warning). Use attribute checks or innerHTML pattern matching to identify the old version.
- `save()` — The old save function (best effort reproduction of old output).
- `migrate(attributes, innerBlocks)` — Transforms old attributes to new format. Use passthrough `return attributes;` if only HTML structure changed.

Without `isEligible`, users see "Unexpected or invalid content" warnings with an "Attempt Recovery" button instead of silent auto-migration.

```javascript
const v1 = {
	attributes: { /* old attribute schema */ },
	isEligible(attributes, innerBlocks, { innerHTML }) {
		// Identify old blocks by attribute signature or HTML patterns
		return !Object.prototype.hasOwnProperty.call(attributes, 'newAttribute');
		// or: return innerHTML && !innerHTML.includes('new-class');
	},
	save({ attributes }) { /* old save output */ },
	migrate(attributes) {
		return { ...attributes, newAttribute: 'default' };
	},
};
export default [v1];
```

**Detection strategies for `isEligible`**:
- Missing new attribute: `!Object.prototype.hasOwnProperty.call(attributes, 'newAttr')` (not `!attributes.newAttr` — falsy values like `false`/`0`/`""` would match incorrectly)
- Old HTML pattern: `innerHTML && !innerHTML.includes('new-class')`
- Removed attribute: `Object.prototype.hasOwnProperty.call(attributes, 'removedAttr')`
- Combined: use `&&` / `||` to narrow matches when multiple versions exist

### Style Imports (MANDATORY)

Add to `src/styles/style.scss` (frontend) AND `src/styles/editor.scss` (editor)
Verify: `grep -i "class-name" build/style-index.css`

### Pre-Commit

```bash
npm run build
npm run lint:js
npm run lint:css
npm run lint:php
# Test editor + frontend + responsive
# Check browser console for errors
```

## Common Pitfalls

1. Frontend imports missing → Add to `src/styles/style.scss`
2. style.scss ≠ editor.scss → Edit BOTH
3. Plain `<InnerBlocks />` → Use `useInnerBlocksProps()`
4. Only test editor → Test frontend too
5. Change shared utility → Test ALL consumers
6. Broad CSS selectors → Scope to block
7. Change attributes → Create deprecation first
8. Deprecation without `isEligible` → Users see "Attempt Recovery" warning instead of silent migration

## Key Patterns

- **Clickable Groups**: Check `!e.target.closest('a, button')` before navigation
- **External Links**: `window.open(url, '_blank'); win.opener = null`
- **Context**: `providesContext` in parent, `usesContext` in child
- **!important**: Only for accessibility, user expectation, or WP core override

## Shared Authoring Primitives (Theme 5/6)

Canonical homes: `src/hooks/` for hooks, `src/components/shared/` for
editor-only React components. Before adding a pattern to a block, check
these directories; the second time you write the same pattern, extract it.

- **`useTablistKeyboard`** (`src/hooks/useTablistKeyboard.js`) — WAI-ARIA
  tablist keyboard nav (ArrowLeft/Right/Up/Down/Home/End, with wrapping)
  for parent blocks that manage tab-like children. Pass `{ itemCount,
  orientation, onIndexChange, focusItem }`. Use in tabs, slider,
  scroll-slides, accordion, image-accordion.
- **`<DsgoChildToolbar>`** (`src/components/shared/DsgoChildToolbar/`) —
  Add/Duplicate/Move/Remove controls for a parent block's children,
  rendered inside `<BlockControls>`. Preferred over bespoke inline
  canvas buttons; keeps authoring a11y consistent across compound blocks.

**Editor interaction conventions (Theme 5):**

- *Toolbar-led*: Add/Remove/Reorder live in `<BlockControls>` via
  `<DsgoChildToolbar>`. Default for most compound blocks.
- *Canvas-led*: Inline `+` may stay on the canvas for tab/slide-like
  blocks where child position is visually meaningful — but hide it
  unless `.is-selected`, `.has-child-selected`, `:hover`, or
  `:focus-within` on the block wrapper. Destructive and reorder
  actions still belong in the toolbar.

## Container Width Pattern

**Two-div structure** (outer: full-width/backgrounds, inner: constrained):

```jsx
<div className="dsgo-block">
  <div className="dsgo-block__inner" style={innerStyle}>
```

**Width constraints**:

- Edit: `maxWidth: contentWidth || themeContentSize`
- Save: `maxWidth: contentWidth || 'var(--wp--style--global--content-size, 1140px)'`
- Nested: Reset constraints via CSS (`.dsgo-stack__inner > &`)

## FSE & Debugging

**FSE Checklist**: Comprehensive `supports`, `example` property, WordPress presets only, test Twenty Twenty-Five

**Debug**: `npx wp-env logs` (500 errors), `grep -i "class" build/style-index.css` (missing CSS)

## Documentation

- [REFACTORING-GUIDE.md](.claude/docs/REFACTORING-GUIDE.md)
- [FSE-COMPATIBILITY-GUIDE.md](.claude/docs/FSE-COMPATIBILITY-GUIDE.md)
- [EDITOR-STYLING-GUIDE.md](.claude/docs/EDITOR-STYLING-GUIDE.md)
- [KSES-ALLOWLIST-GUIDE.md](.claude/docs/KSES-ALLOWLIST-GUIDE.md)
- [Block Editor Handbook](https://developer.wordpress.org/block-editor/)

## Version Control

**Format**: `type: description` (`feat`, `fix`, `refactor`, `style`, `docs`, `chore`)

**Commit**: `src/`, `includes/`, `*.php`, `package.json`, `block.json`, `*.md`

**Ignore**: `build/`, `node_modules/`, `wp-env/`

## Branches

Branch prefixes should start with `claude/`

## Memory

As you work on an issue, add notes to memory, .claude/claude-memory.md, create an agent ID or session ID so as to not confuse other agents.
---

**Updated**: 2026-03-04 | **Version**: 1.2.0 | **WP**: 6.4+
