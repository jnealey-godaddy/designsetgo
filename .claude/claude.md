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
- **render.php**: Always wrap all logic in a named `designsetgo_render_{block_slug}()` function (guarded by `function_exists`) and call it at the bottom. This keeps variables function-scoped and avoids `NonPrefixedVariableFound` PCP warnings. See `dynamic-image/render.php` as the canonical example.

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

### Query block family (Dynamic Query v1)

- Container: `designsetgo/query`. Siblings bind via `queryId` (context key: `designsetgo/queryId`).
- Siblings: `designsetgo/query-pagination` (numbered/loadmore), `designsetgo/query-filter` (variations: checkbox/select/search/sort/active/reset), `designsetgo/query-no-results`.
- Dynamic data: WP 6.5+ Block Bindings. Built-in sources: `designsetgo/post-meta` (always), `designsetgo/acf` (only when `function_exists('get_field')`). Do NOT invent a token parser.
- Server render: `src/blocks/query/render-helpers.php` owns `designsetgo_query_render()`; REST endpoint `designsetgo/v1/query/render` reuses it so first-paint and AJAX paths are byte-identical.
- Per-item context: `postId` + `postType` for Posts (matches core blocks); `designsetgo/currentItemId` + `designsetgo/currentItemType` for users/terms.
- IAPI store: `'designsetgo/query'`. Actions: `loadMore`, `setFilter`, `setFilterDebounced`, `toggleFilter`, `removeActiveFilter`, `resetAll`.
- Filter hooks: `designsetgo_query_args` (all sources), `designsetgo/query/{queryId}/args` (scoped — fires after the global hook).
- URL params: `q`, `sort`, `filter_<taxonomy>`. Extend via `designsetgo_query_url_params` filter.
- Frontend data contract: `[data-dsgo-query-id]` on the wrapper; `[data-dsgo-blobs-for]` sibling holds attributes + innerBlocks JSON blobs for IAPI requests.
- See `.claude/docs/QUERY-BLOCK-GUIDE.md` for recipes + extension points.

### Query block family (Dynamic Query v2.2)

- Persistent filter index at `{$wpdb->prefix}dsgo_query_filter_index`. Auto-maintained via `FilterIndexHooks` on `save_post` / taxonomy / meta events.
- `FilterRegistry` at option `dsgo_query_filters` tracks `{key: {type, source, label}}`. Filterable via `designsetgo_query_registered_filters`.
- `FilterIndexRebuilder::rebuild_all/rebuild_filter/status` — batched rebuild primitives.
- WP-CLI: `wp dsgo query index {rebuild|rebuild-filter|status|drop}`.
- Filter counts: `showCounts` attribute on `designsetgo/query-filter` (default `true`); rendered via `.dsgo-query-filter__count` span.
- Infinite scroll: `paginationKind: 'infinite'` variation on `designsetgo/query-pagination`. Auto-pauses after `autoPauseAfter` (default 3), reveals button. Reduced-motion safe.
- Editor live preview: Posts via `useEntityRecords`, users/terms via `/designsetgo/v1/query/preview` REST route. First item wraps editable `InnerBlocks`; items 2..N render `BlockPreview`.
- Admin dashboard: Settings → DesignSetGo → Dynamic Query (requires `manage_options`).

### Query block family (Dynamic Query v2.3)

- Relationship source (`source: 'relationship'`) reads `relationshipField` from the nearest parent-stack item and iterates referenced post IDs via `post__in`.
- `relationshipFallback`: `'empty'` | `'all'` | `'parent'` controls behavior when the field yields zero IDs.
- DSGo bindings (`designsetgo/post-meta`, `designsetgo/acf`) accept a `scope` arg: `'self'` (default), `'parent'`, `'root'`. Reads from `$GLOBALS['designsetgo_parent_stack']` pushed by `designsetgo_query_render_item()`.
- Every block has a `dsgoVisibility` attribute (registered via `blocks.registerBlockType` filter in `src/extensions/visibility/filters.js`). Rule shape: `{ operator: 'AND'|'OR', rules: [{ type: 'meta'|'taxonomy'|'index'|'auth', op, key?, value }] }`. Server evaluator: `DesignSetGo\BlockVisibility::matches()`. Editor mirror: `src/extensions/visibility/evaluateRules.js`.
- New sibling block `designsetgo/query-group-header` renders once per group inside a parent Query when `groupBy` is set.
- `groupBy` attribute on the Query block — shape `{ field: 'taxonomy'|'meta'|'date', key: string }`. Server partitions items in `designsetgo_query_partition_items()` (in `render-helpers.php`).
- Custom visibility rule types via `designsetgo_visibility_rule` filter (`($match, $rule, $context)` → bool|null).

### Query block family (Dynamic Query v2.4)

- Three new Block Bindings sources registered only when the host plugin is active: `designsetgo/metabox` (via `rwmb_meta()`), `designsetgo/pods` (via `pods_field()`), `designsetgo/jetengine` (via `jet_engine()->listings->data->get_meta()` with `get_post_meta` fallback). Each delegates to the plugin's formatting API so dates / files / relations render correctly.
- `designsetgo_register_bindings_source( $slug, callable $callback, array $options )` — public PHP helper. Wraps `register_block_bindings_source()` with DSGo's shared post-password / viewable / protected-meta gates and the `scope` arg (`self`/`parent`/`root`). Use this instead of `register_block_bindings_source()` to inherit all security + scope behavior.
- Post ID resolution exposed via `designsetgo_resolve_bindings_post_id( $args, $block )` (free function) — mirrors the helper's internal resolution.
- Template export/import REST routes at `/designsetgo/v1/query/template` (GET for export, POST for import). JSON format with `schemaVersion: 1`, attribute allowlist via `WP_Block_Type_Registry`, fresh `queryId` generated on import to avoid sibling-binding collisions.
- Inspector → Settings panel → Template I/O section exposes Export + Import buttons backed by those routes.
- `designsetgo/post-meta` and `designsetgo/acf` sources (v2.1) refactored to use the new helper internally — output is identical.

### Query block family (Dynamic Query v2.5)

- `dateQuery` attribute on `designsetgo/query` — shape `{ relation, clauses: [{ column, mode, after, before, inclusive }] }`. Supported modes: `after`, `before`, `between`. Date values: ISO `YYYY-MM-DD` or PHP relative expressions (`-30 days`, `today`).
- `taxQuery.clauses[]` entries may now be leaf clauses OR nested groups (`{ relation, clauses: [...] }`). PHP builder: `designsetgo_build_tax_query_entry()` (recursive). Same pattern for `metaQuery` via `designsetgo_build_meta_query_entry()`.
- `include_children` field on each `taxQuery` leaf clause — boolean, defaults `true`. Pass-through to WP_Query `tax_query`.
- `<ClauseGroupShell>` (`src/blocks/query/components/ClauseGroupShell.js`) — shared recursive group chrome (relation selector, + Clause, + Group, Remove group). Used by both TaxQueryBuilder and MetaQueryBuilder via `renderClause` render prop.
- Query Monitor panel: `includes/class-query-qm-collector.php` + `includes/class-query-qm-output.php`. Loaded only when `defined('QM_VERSION')`. Collects data via `designsetgo_query_did_render` action fired from `render-posts.php` after each WP_Query.
- Dynamic CSS style bindings: `dsgoStyleBinding` global attribute (`src/extensions/style-binding/filters.js`) maps CSS property names (including custom properties `--foo`) → binding source+key. PHP: `DesignSetGo\StyleBinding` (`includes/features/class-style-binding.php`) resolves via `designsetgo_style_binding_resolve` filter and injects via `WP_HTML_Tag_Processor`. Honours `$GLOBALS['designsetgo_parent_stack']` for nested loop context. Dangerous values (`url(`, `expression(`, `javascript:`) rejected.

### WooCommerce surface (Plan 8)

See [`docs/plans/2026-08-17-woocommerce-surface.md`](../docs/plans/2026-08-17-woocommerce-surface.md).

- **Use WooCommerce's own blocks first.** This is "does WordPress already provide this?" applied to Woo, and it is the governing rule here. Verified by spike, all working with no DSGo code: `woocommerce/product-price`, `product-sku`, `product-rating`, `product-image`, `product-stock-indicator`, `product-sale-badge`, and `product-button` render correctly **inside a `designsetgo/query` loop** (they read the `postId` context `render-posts.php` supplies); `woocommerce/mini-cart` is a standalone cart count + drawer; `woocommerce/add-to-cart-with-options` is a full add-to-cart family **including variation, quantity, and grouped-product selectors**; `woocommerce/product-filters` is a full filter UI. Do NOT rebuild any of these.
- **DSGo therefore ships no cart blocks, no add-to-cart block, and no product filter UI.** That is deliberate, not an omission — see Plan 8's D0.
- Six bindings sources in `includes/dynamic-tags/class-dynamic-tags-sources-woo.php`, registered only when Woo is active, in a `woocommerce` registry group: `designsetgo/woo-price-html`, `woo-price`, `woo-regular-price`, `woo-discount-percent`, `woo-stock-quantity`, `woo-average-rating`. They take **no `key` arg**.
- These exist for what Woo exposes through no block: **raw scalars for `dsgoStyleBinding`**. A stock bar is `progress-bar` + `--dsgo-progress` bound to `woo-stock-quantity`.
- **Null, not zero.** Unmanaged stock, an unreviewed product, and a non-discounted product all return `null` — a zero would render a stock bar as empty-but-present, which is a different and wrong claim.
- `get_price()` returns the **minimum** for variable products. Use `woo-price-html` (or Woo's block) when a range matters.
- `StyleBinding::resolve()`'s `default` branch now delegates to any registered `designsetgo/`-prefixed bindings source. The `key` requirement applies only to `StyleBinding::KEYED_SOURCES` (post-meta / acf / metabox / pods / jetengine). Consequence: `post-*` and `archive-*` sources work in style bindings too.
- Never reimplement Woo formatting — prices, labels, stock strings, and add-to-cart URLs come from the product API. If DSGo is formatting money, that's a bug.
- **Product queries**: `src/blocks/query/render-woo.php` adds catalog visibility (`wooCatalogVisibility`, default true), `wooFeatured`, `wooOnSale`, `wooStockStatus`, and consumes the URL params Woo's filter blocks emit (`min_price`/`max_price`, `filter_stock_status`, `rating_filter`, `filter_<attr>`/`query_type_<attr>`) so `woocommerce/product-filters` can drive a DSGo loop. Woo strips `pa_` in URLs, so `filter_color` → `pa_color`; slugs that are already real taxonomies are left to the generic handler so the two never double-apply.
- **New PHP under `src/blocks/query/` must be added to the webpack copy patterns.** Production loads these from `build/blocks/query/`, not `src/`, so an uncopied file is silently inert — `file_exists()` guards turn it into a no-op rather than an error. Query tests load from `build/` with `assertFileExists()` precisely to catch this.
- Tests need real WooCommerce: `.wp-env.json` pins 11.0.1, and `tests/phpunit/bootstrap.php` must both require the plugin on `muplugins_loaded` **and** run `WC_Install::install()`. Without those, Woo tests silently *skip* while the suite reports OK. Fixtures: `DesignSetGo_Woo_Product_Factory`.

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

**`save()` is what makes migration silent — not `isEligible`.** WordPress applies a deprecation like this (`@wordpress/blocks` → `api/parser/apply-block-deprecated-versions.js`):

```js
const { isEligible = stubFalse } = deprecatedDefinitions[i];
if ( block.isValid && ! isEligible( parsedAttributes, block.innerBlocks, { blockNode, block } ) ) continue;
// ...then: does THIS version's save() reproduce the stored HTML? If not, skip it.
```

Read that `block.isValid &&` carefully — it drives everything:

- **The block is INVALID** (the normal case: markup changed, so stored HTML no longer matches the current `save()`). `block.isValid` is `false`, the condition short-circuits, and **`isEligible` is never called**. WordPress picks the version whose `save()` reproduces the stored HTML. Silent, no "Attempt Recovery". A markup-change deprecation therefore needs **no `isEligible` at all** — and adding one buys nothing.
- **The block is VALID.** `isEligible` is the *only* thing consulted, and returning `true` opts an otherwise-fine block into a re-migration. This is the *only* reason `isEligible` exists: an attribute-only migration where markup did not change.

So: **`isEligible` is a switch for migrating VALID blocks. It cannot rescue invalid ones, and it never suppresses an "Attempt Recovery" warning — only a `save()` that reproduces the stored HTML does that.**

Three traps that follow, all of which have bitten us:

0. **Every deprecation entry must redeclare `apiVersion`.** `apiVersion` is in `DEPRECATED_ENTRY_KEYS`, so WordPress strips it from the block type when building the deprecated type — without `apiVersion: 3` (or `2`) on the entry itself, the deprecation's `save()` runs under apiVersion ≤ 1 semantics, `useBlockProps.save()` produces different markup, and the entry **silently never validates** (the block stays invalid with no hint why). See `pill/deprecated.js` and `modal/deprecated.js`.

1. **The third argument is `{ blockNode, block }` — there is no `innerHTML` key.** Reach the markup via `blockNode.innerHTML` (or `block.originalContent`). Destructuring `{ innerHTML }` yields `undefined`, so a guard like `innerHTML && innerHTML.includes(...)` silently returns `false` forever. It *looks* like it works, because the invalid-block path above carries the migration regardless.

2. **"Attribute absent from the comment" does NOT mean "old block".** `attributes` here is the raw comment JSON, and WordPress never serializes an attribute whose value equals its default. `attributes.align === undefined` or `typeof attributes.iconPosition === 'undefined'` is therefore just as true of a brand-new block that left the value alone. Guards like that claim *current* content, and then `migrate()` runs it through a schema that predates the block's newer attributes — silently dropping them.

```javascript
const v1 = {
	apiVersion: 3, // REQUIRED — see trap 0
	attributes: { /* the FULL attribute schema this version had */ },
	supports: { /* the FULL support set this version had */ },
	save({ attributes }) { /* reproduce the old output byte-for-byte */ },
	migrate( attributes ) {
		return { ...attributes, newAttribute: 'default' };
	},
	// isEligible ONLY if markup did not change and you must migrate a VALID
	// block. Key it on the stored markup, never on an attribute's absence:
	isEligible( attributes, innerBlocks, { blockNode, block } = {} ) {
		const html = blockNode?.innerHTML ?? block?.originalContent ?? '';
		return html.includes( 'old-class' ) && ! html.includes( 'new-class' );
	},
};
export default [ v1 ];
```

`tests/unit/deprecations-isEligible.test.js` pins the invariant: no deprecation may claim a block's own current `save()` output, and every block must round-trip `createBlock → serialize → parse` without losing an attribute.

**A deprecation's `supports` block must declare the full support set that version actually had.** If it omits a group, WordPress strips those attributes (`backgroundColor`, `textColor`, `gradient`, `borderColor`, `fontSize`, `style`, …) **before `migrate()` runs** — silently and unrecoverably, with no warning. The specific trap that bit us: typography supports must use the `__experimental` keys (`__experimentalFontFamily`, `__experimentalFontWeight`, `__experimentalLetterSpacing`) — the un-prefixed names silently fail `hasBlockSupport()`, so the support looks declared but isn't. And **deprecations do not cascade**: exactly one entry runs for a given stored block, so every existing `migrate()` — not just the newest one — must land on the *current* attribute schema, or older content silently loses whatever the newest migrate() alone would have added.

### Style Imports (MANDATORY)

Add to `src/style.scss` (frontend — the real webpack `style-index` entry, see `webpack.config.js`) AND `src/styles/editor.scss` (editor). `src/styles/style.scss` looks like the frontend entry but is dead code — nothing imports it; don't add to it.
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

1. Frontend imports missing → Add to `src/style.scss` (not `src/styles/style.scss` — that file is dead code)
2. style.scss ≠ editor.scss → Edit BOTH
3. Plain `<InnerBlocks />` → Use `useInnerBlocksProps()`
4. Only test editor → Test frontend too
5. Change shared utility → Test ALL consumers
6. Broad CSS selectors → Scope to block
7. Change attributes → Create deprecation first
8. Deprecation whose `save()` doesn't reproduce the stored HTML → Users see "Attempt Recovery". (Adding `isEligible` does NOT fix this — see Deprecations above.)
9. `isEligible` keyed on an attribute being absent → claims *current* content, because WordPress omits default-valued attributes from the block comment

## Key Patterns

- **Clickable Groups**: Check `!e.target.closest('a, button')` before navigation
- **External Links**: `window.open(url, '_blank'); win.opener = null`
- **Context**: `providesContext` in parent, `usesContext` in child
- **!important**: Only for accessibility, user expectation, or WP core override

### Horizontal positioning: justification, not `align`

Never use `supports.align: ["left","center","right"]` to position a block horizontally. In WordPress, `align: left|right` means *float out toward the page edge* — core's constrained layout explicitly excludes `.alignleft` / `.alignright` from the content-size cap (`> :where(:not(.alignleft):not(.alignright):not(.alignfull))` in `wp-includes/block-supports/layout.php`), so an aligned block gets `max-width: none` and escapes the content column, pinning itself to the full-width container's padding edge instead. Icon Button and Modal Trigger had it worse: their block root *was* the `<a>`/`<button>` with `display: inline-flex`, and auto margins do nothing on an inline-level box, so core's `max-width` never bound at all.

Instead follow the `core/buttons` model:

- The block root is a **block-level positioning wrapper** — `.dsgo-justify` plus `.dsgo-justify--{left|center|right}` from a `justification` attribute. **Never give the wrapper `width: fit-content`** — a shrink-wrapped box makes core's `max-width` cap inert and the auto margins then resolve against the full-width container. That was the original bug.
- The **visible element sits inside** and shrink-wraps.
- All *visual* supports (color, border, typography, shadow, padding) must be routed to that inner element, never left on the wrapper: `__experimentalSkipSerialization` plus the `__experimentalUse*Props` / `__experimentalGet*ClassesAndStyles` helpers for static blocks, `designsetgo_route_visual_supports()` (`includes/block-support-routing.php`) for dynamic ones. A CSS "neutralizer" on the wrapper does **not** work — it ties on specificity with WordPress's own `.has-*-background-color { … !important }` rules and loses on source order, and it never fires for class-driven preset gradients at all.
- When the block root differs from the visually-styled element, declare `selectors.root` in `block.json` (core's `core/button` does exactly this) so theme.json / Global Styles target the right element.
- `align` keeps its real meaning: `wide` / `full` bleed only, on the wrapper.

Shared primitives: `getJustificationClass()` (`src/utils/justification.js`) and `<DsgoJustificationToolbar>` (`src/components/shared/DsgoJustificationToolbar`).

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
