# WooCommerce Surface — Spec

**Date:** 2026-08-17
**Status:** Spec. Five units derive from this document; each ships on its own branch and PR.
**Parent:** [GreenShift Gap Roadmap](2026-08-16-greenshift-gap-roadmap.md) — this is Plan 8.

## Context

The GreenShift roadmap ruled "WooCommerce depth" out of scope. That was right about the
*depth* features and wrong about the *surface* ones. GreenShift ships ~26 Woo blocks;
roughly a third of them are single-field display blocks (price, SKU, rating, availability,
discount, taxonomy) which — in an architecture that already has Block Bindings, a Query
block, and style bindings — are not blocks at all. They are bindings.

This plan builds the surface. It does not build the depth.

### Already in the tree

- `designsetgo/product-showcase-hero` and `designsetgo/product-categories-grid`, gated by
  `Plugin::gate_woocommerce_blocks()` via the `designsetgo_register_block` filter.
- `designsetgo_register_bindings_source()` — wraps `register_block_bindings_source()` with
  the shared post-password / viewable / protected-meta gates and the `scope` arg.
- `designsetgo_resolve_bindings_post_id()` — resolves `self` from `postId` context (with an
  `available_context` reflection fallback), and `parent` / `root` from
  `$GLOBALS['designsetgo_parent_stack']`. Honours a pre-resolved `__dsgo_post_id` so the
  Dynamic Tags REST preview works.
- `designsetgo/query` supplies core `postId` + `postType` per item
  (`render-posts.php`), explicitly so core blocks and Bindings work inside the loop.
- Off-canvas is already shipped as a `designsetgo/modal` variation (`panelEdge`,
  `panelSize`). The cart drawer is a *consumer* of it, not new machinery.
- `dsgo_query_filter_index` — `filter_key` / `filter_value` both `VARCHAR(190)`. No
  numeric column.

### Explicitly out of scope

Named specifically so the boundary is defensible later, rather than the vague
"WooCommerce depth":

- Variation swatches and variation-level galleries
- Product bundles and combos
- Attribute groups
- 360° / 3D product viewers
- Quick-view modals (deferred, not rejected — the machinery exists, the demand doesn't yet)

These are WooCommerce **data-model** features, not layout features. They mean touching WC
data stores, the cart, and order line items; they carry an enormous support surface across
themes and payment gateways; and they compete head-on with entrenched paid plugins. That
is a different product, not a block library.

## Decisions

Every one of these was settled deliberately. Where a decision has a cost, the cost is
stated — those are accepted, not overlooked.

### D1 — Everything ships in-core, gated

No `designsetgo-woo` companion plugin. Items 1 and 2 are genuinely free when Woo is absent
(a source that never registers; a query branch that never runs), and splitting the plugin
for them would be ceremony. Item 3's support risk is accepted and mitigated by keeping its
surface deliberately small (D8, D9).

Gate on `class_exists( 'WooCommerce' )`, matching `gate_woocommerce_blocks()`.

### D2 — One bindings source per field

Matches `PostSources` (`designsetgo/post-title`, `post-excerpt`, …), not the open-keyspace
sources (`post-meta`, `acf`, `pods`) that take a `key` arg. A product's field vocabulary is
fixed and known, which makes it a `post-title` analogue.

Each source declares an honest `returns` type in the Dynamic Tags `Registry`, so the
field-discovery REST route can type-filter the editor picker. A single keyed source would
collapse `returns` into a union and make the picker dumber.

**Cost:** ~12 registrations. `class-dynamic-tags-sources-post.php` is already ~300 lines,
so the Woo equivalent splits across 2–3 files to respect the file-size cap.

### D3 — Price is `price-html` *and* scalars

Ship both:

- `designsetgo/woo-price-html` — delegates to `$product->get_price_html()`.
  `returns: ['html']`.
- `designsetgo/woo-regular-price`, `woo-sale-price`, `woo-discount-percent`,
  `woo-price-raw` — plain-text scalars for hand-composed layouts, with `dsgoVisibility`
  driving the on-sale branch.

`get_price_html()` is not a convenience — it is the only correct answer for variable
products. `$product->get_price()` on a variable product returns the **min price only**;
`get_price_html()` produces the `"$10.00 – $20.00"` range, the tax suffix, and the
`<del>/<ins>` sale treatment, all honouring the store's tax and currency display settings.
Reimplementing that from scalars ships subtly wrong prices.

**Cost:** two idioms for one concept, which has to be documented.

**Open risk — WP version floor.** Verified on WP **6.9.4**: `WP_Block::replace_html()`
uses `$block_reader->replace_rich_text( wp_kses_post( $source_value ) )` for `html` /
`rich-text` sourced attributes, so `<del>`, `<ins>`, `<bdi>`, `<span>` survive. The plugin
declares `Requires at least: 6.7`, and **6.7/6.8 have not been verified** — earlier
releases used `esc_html()` on that path, which would render the price markup as literal
text. Resolve this during Item 1; if 6.7/6.8 escape, `woo-price-html` needs a version
guard and a documented fallback to the scalars.

Note also that a bound value reaching a *dynamic* block arrives in `$block->attributes`
raw — the `render_callback` escapes it itself. Any DSGo dynamic block that wants to
consume `woo-price-html` must use `wp_kses_post()`, not `esc_html()`.

### D4 — Spike before building Item 1

WooCommerce ships `woocommerce/product-price`, `product-sku`, `product-rating`,
`product-image`, `product-stock-indicator`, and they consume `postId` context.
`designsetgo/query` supplies `postId`. **Those blocks may already render correctly inside a
DSGo product loop today.** Building 12 sources and discovering 6 were redundant is the
expensive version of this.

The spike is documentation, not code: build a DSGo Query with `postType: product`, drop
Woo's own product blocks inside, load the frontend, record what works. Item 1 is then
scoped to what the spike proves is missing.

What was expected to survive the spike regardless, and justify Item 1 no matter the
outcome:

1. **Style bindings.** `dsgoStyleBinding` driving `--dsgo-progress` from stock quantity.
   No Woo block can do this — it is the entire Stock Bar feature.
2. **`scope: 'parent' | 'root'`.** Woo's blocks read the nearest `postId`; they cannot
   reach up a nested relationship loop.
3. **Driving DSGo blocks.** A price inside a `pill` or `advanced-heading` with DSGo
   typography controls, rather than accepting Woo's markup.
4. **Fields Woo has no block for.** Discount percent, stock quantity as a *number*, raw
   unformatted price.

#### Spike result — run 2026-08-17, WooCommerce 11.0.1 / WP 6.9

**The suspicion was correct, and Item 1 shrinks substantially.** WooCommerce's own
product blocks render correctly inside a `designsetgo/query` product loop today, with no
DSGo work at all.

Method: `designsetgo_query_render()` called directly with `source: posts`,
`postType: product`, and an `inner_html` item template of Woo blocks — the same path the
front end takes. Plus each block rendered in isolation through `WP_Block` with only
`postId` / `postType` context, to confirm it is genuinely the context doing the work.

Woo registers **59** `woocommerce/product*` blocks. The display ones declare
`uses_context: query, queryId, postId` — and `postId` alone is sufficient, which is
exactly what `render-posts.php` supplies per item.

| Block | Result inside `designsetgo/query` |
|---|---|
| `product-price` | ✅ Full `<del>`/`<ins>` sale markup, currency, screen-reader text |
| `product-sku` | ✅ `SKU: SPIKE-001` |
| `product-image` | ✅ Image, links to product, nests its own sale badge |
| `product-rating` | ✅ `Rated 4.00 out of 5 based on 1 customer rating` |
| `product-rating-stars` | ✅ Renders |
| `product-stock-indicator` | ✅ `9 in stock` |
| `product-sale-badge` | ✅ Renders |
| `product-button` | ✅ **Renders a working Add to cart / View cart** |
| `core/post-title` | ✅ Renders |
| `product-summary` | Empty — product had no description. Correct, not broken. |

Two initially-ambiguous "empty" results were chased down rather than assumed:
`product-rating` was empty only until the product had an approved review, and
`product-summary` only because the fixture had no description. Both are correct
behaviour.

**Consequences for scope:**

- **Items 1's display sources are redundant.** `woo-sku`, availability text, rating,
  sale badge, and taxonomy sources would all duplicate a working Woo block. Do not build
  them.
- **`woo-price-html` is *mostly* redundant** with `woocommerce/product-price`. It retains
  a narrower justification — driving a *DSGo* block (a price inside a `pill` or
  `advanced-heading` with DSGo typography) rather than accepting Woo's markup — but it is
  no longer the centrepiece of Item 1.
- **The raw scalars are the real, unduplicated gap.** No Woo block exposes them, and they
  are what `dsgoStyleBinding` needs. Confirmed available on the product object:
  `get_price()` → `25.00`, `get_regular_price()` → `40.00`, `get_stock_quantity()` → `9`
  (int), `get_average_rating()` → `4.00`, computed discount → `38%`.
- **Item 3's button is partly redundant too.** `woocommerce/product-button` already works
  in the loop, including the AJAX add. DSGo's remaining justification for its own button
  is design-system styling and cart-drawer integration — *not* function. That materially
  weakens Item 3 and should be weighed before building it.

**Net:** Item 1 drops from ~12 sources to roughly 5 — `woo-price-raw`,
`woo-regular-price`, `woo-discount-percent`, `woo-stock-quantity`, `woo-average-rating`
— plus `woo-price-html` kept deliberately for DSGo-block-driving. Everything else defers
to Woo's own blocks, which should be documented as the recommended approach.

**Note:** D3's open WP 6.7/6.8 HTML-escaping risk shrinks with this, since `price-html`
is no longer load-bearing. The scalars are plain text and unaffected either way.

#### Item 1 — as built

`includes/dynamic-tags/class-dynamic-tags-sources-woo.php`, registered from
`Bootstrap::register_sources()`. Six sources, in a new `woocommerce` registry group
(order 60) that only registers when Woo is active, so the picker never offers a source
that cannot resolve:

`woo-price-html` (html), `woo-price`, `woo-regular-price`, `woo-discount-percent`,
`woo-stock-quantity`, `woo-average-rating`.

Deliberate null-vs-zero choices, each pinned by a test: an unmanaged-stock product returns
`null` rather than `0` (a stock bar rendered empty-but-present is a different and wrong
claim from "this product has no stock concept"), an unreviewed product returns `null`
rather than `0.00`, and a product not on sale returns `null` rather than `0`.

**A blocker surfaced that would have made Item 1 pointless.** `StyleBinding::resolve()`
was a hardcoded switch over the five *keyed* custom-field sources, fronted by
`if ( ! $key || ! $post_id ) return null;`. The Woo sources take no `key`, so every one of
them would have resolved to `null` on the style-binding path — meaning the stock bar, the
single justification for this unit after the spike, would not have worked.

Fixed by having the switch's `default` delegate to any registered `designsetgo/`-prefixed
bindings source (`resolve_registered_source()`), with the `key` requirement narrowed to a
`KEYED_SOURCES` list so the existing five behave byte-identically. This also means the
`post-*` and `archive-*` sources now work in style bindings, which they never did. Both
directions are pinned by tests.

One PHPStan ignore added: `WP_Block_Bindings_Source::get_value()`'s `$block_instance`
parameter is **untyped in core**, so passing `null` is legal — verified by reading
`wp-includes/class-wp-block-bindings-source.php` in the running container. Only the stub's
docblock claims `WP_Block`. The pre-existing bindings tests rely on the same call.

### D5 — Extend the `posts` query source; no new enum value

`source` stays `posts | users | terms | manual | current | relationship`. A seventh value
means either duplicating or mid-project refactoring everything that hangs off
`render-posts.php`: `taxQuery`, `metaQuery`, `dateQuery`, `groupBy`, the filter index,
pagination, relationship scope, template I/O.

Instead, add explicit attributes surfaced conditionally in `QuerySourcePanel` when
`postType` includes `product`:

- `wooCatalogVisibility` (bool, default `true`)
- `wooStockStatus`, `wooOnSale`, `wooFeatured`
- price-range clause

**Catalog visibility must be an explicit attribute, never implicit.** A plain `WP_Query`
with `post_type=product` is *not* a Woo catalog query: it ignores the `product_visibility`
taxonomy, so hidden and `exclude-from-catalog` products leak into the loop, and
`woocommerce_hide_out_of_stock_items` doesn't apply. Applying that automatically based on
a post-type string is undebuggable two years later — the author must be able to see and
toggle it.

### D6 — Price filtering joins `wc_product_meta_lookup`

`dsgo_query_filter_index.filter_value` is `VARCHAR(190)`. A range filter needs numeric
comparison, and varchar sorts `"100"` before `"9"`.

Woo already maintains `wc_product_meta_lookup` with indexed `min_price` / `max_price`
columns, kept in sync on every product save including variations. Copying prices into the
DSGo index would mean a `dbDelta` migration, a db-version bump, a full rebuild on upgrade,
and a permanent second source of truth that can drift.

Attribute filters stay on the DSGo index — Woo product attributes are taxonomies (`pa_*`),
`FilterRegistry` already handles taxonomy filters, and `FilterIndexHooks` already reindexes
on taxonomy events. A swatch filter is likely a *presentation* variation of the existing
checkbox filter plus a term-meta colour lookup, not new query plumbing. **Verify this
before building it.**

**Cost:** one filter type whose data path differs from every other filter type. Needs a
comment explaining why.

### D7 — Store API for JS, plain form as fallback, no `wc_fragments`

- **JS path:** `POST /wp-json/wc/store/v1/cart/add-item` with the `Nonce` header. Returns
  the whole cart object, so the drawer and the count both update from one response.
- **No-JS fallback:** plain `?add-to-cart=` form / link.
- **Classic `wc_fragments`: explicitly unsupported.** Documented as a known limitation —
  a classic theme's mini-cart elsewhere on the page will not live-update. Fragment caching
  is the ecosystem's most-reported source of "cart shows wrong count" bugs, and staying off
  it is what keeps Item 3 supportable.

**Cache consequence, mandatory:** under full-page caching, a Store API nonce printed into
server-rendered HTML goes stale. The cart count must be **client-fetched on load**, never
server-printed, or every cached page shows the previous visitor's count.

**Open:** minimum WooCommerce version. Both Store API `v1` and `wc_product_meta_lookup`
have floors that need pinning.

### D8 — The button delegates to Woo's product API; no per-type branching

Render entirely from `$product->add_to_cart_url()`, `add_to_cart_text()`,
`is_purchasable()`, `is_in_stock()`, and `supports( 'ajax_add_to_cart' )`.

This is correct-by-construction across every product type — simple, variable, grouped,
external — *and* across extension types DSGo has never heard of (Subscriptions, Bookings).
The button becomes a Store API call only when the product declares it supports that, and
otherwise degrades to a link to the product page carrying Woo's own correct label
("Select options", "Read more", "Buy product").

No DSGo-authored per-type logic at all. DSGo owns styling and the AJAX call; Woo owns
correctness.

**Cost:** the label is Woo's by default. An author label override exists but is opt-in,
and an author who overrides it to "Add to cart" will show that on a variable product where
it is a lie. Needs either an editor warning or a documented convention.

### D9 — Fixed drawer template driven by an IAPI store

**Acknowledged first:** WooCommerce already ships `woocommerce/mini-cart`, which already
*is* a cart drawer with a count badge. Item 3 is not filling a functional gap — it is
filling a design-consistency gap. That is a legitimate reason (Mini Cart drags in its own
`wc-blocks` frontend bundle, which cuts against the "no new runtime dependencies"
performance story, and its markup isn't ours), but it is a decision made with eyes open,
not a gap assumed to exist.

- **State:** a `designsetgo/woo-cart` Interactivity API store holding
  `{ itemCount, itemsTotal, items[] }`. Hydrated client-side on load (mandatory per D7),
  updated from every Store API response. Cart-count elements and the drawer both
  subscribe — exactly the cross-block-shared-state criterion CLAUDE.md names for reaching
  for IAPI.
- **Drawer body:** a deliberately **fixed** template — thumbnail, name, variation meta,
  quantity stepper, line total, remove, subtotal, View Cart / Checkout. Not
  author-composable.

Holding that line is what keeps Item 3 shippable. The moment line items become
author-composable inner blocks, you need a cart-item loop, per-item bindings, and a second
full templating surface — which is squarely the depth ruled out above.

**OPEN QUESTION — revisit at Item 3, deliberately deferred.** The fixed template means
DSGo owns cart line-item rendering permanently, including variation metadata display and
extension-added line-item data (subscription intervals, booking dates, add-on fields, gift
messages). Two live options:

- **(a) Fixed DSGo template** (current decision). On-brand, no `wc-blocks` bundle, bounded
  scope. Permanent ownership of line-item rendering; extension data needs an explicit
  filter hook to be displayable at all.
- **(b) Embed `woocommerce/mini-cart` contents** inside the DSGo panel. Near-zero cart
  code, Woo handles every edge case and extension. Costs the `wc-blocks` frontend bundle,
  Woo's markup inside the DSGo design system, and a likely double-drawer conflict with
  Mini Cart's own panel.

Item 3 lands last and alone specifically so this stays revertable. Decide it then, with
the benefit of whatever Items 1 and 2 teach us about how much Woo markup authors tolerate.

## Global Constraints

Inherited verbatim from the [GreenShift Gap Roadmap](2026-08-16-greenshift-gap-roadmap.md)
§ Global Constraints — indentation, prefixes, 300-line file cap, `apiVersion: 3`,
`useBlockProps()`, the Settings → Style → Advanced inspector IA via `<DsgoInspectorPanel>`,
supports-first, style-import reachability, **no new runtime dependencies**, Interactivity
API for cross-block state, escaping, i18n, WCAG AA, no `console.log`, `claude/` branch
prefix, and the pre-commit gate.

Two additions specific to this plan:

- **Every Woo call site is guarded.** No unguarded `wc_*()` / `WC()` / `$product->`
  anywhere. Woo absent must be indistinguishable from Woo present-but-empty.
- **Never reimplement Woo formatting.** Prices, labels, stock strings, and add-to-cart URLs
  come from the product API (D3, D8). If DSGo is formatting money, that's a bug.

## Units

Five units, each its own branch (`claude/woo-*`) and PR, off a clean `main`.

| # | Unit | Gates | Notes |
|---|------|-------|-------|
| 0 | Test infrastructure | Everything | Woo in wp-env, PHPStan stubs, product fixtures |
| — | Spike | Item 1's scope | ✅ Done — see D4. Woo's blocks already work in the loop. |
| 1 | Bindings sources | — | ✅ Done — 6 sources, re-scoped by the spike |
| 2 | Woo-aware Query + filters | — | Unaffected by the spike; independent of Item 3 |
| 3 | Button + count + drawer | — | **Weakened by the spike** — reconsider before building |

### Unit 0 — Test infrastructure

Prerequisite, because the strategy chosen in D3, D5, D6, and D8 is *delegation to
WooCommerce*, and the current test setup cannot verify any of it. Existing Woo tests stub
`wc_get_product()` to return `false`, exercising only the Woo-absent guard path.
WooCommerce is not in `.wp-env.json` at all, and there are no Woo stubs in
`composer.json` / `phpstan.neon`.

1. WooCommerce added to `.wp-env.json` — `tests` env (for PHPUnit) and `development`
   (for the spike and manual QA). Pin the version; that pin is the answer to D7's open
   minimum-version question.
2. `php-stubs/woocommerce-stubs` in `require-dev`, added to `phpstan.neon`
   `bootstrapFiles` alongside `wordpress-stubs`.
3. A product-fixture helper creating **simple, variable, grouped, and external** products —
   D8's delegation strategy is only meaningful if all four types are under test.
4. Existing `product-categories-grid` / `product-showcase-hero` tests keep their
   Woo-absent guard coverage; new tests assert real behaviour.

**Cost accepted:** slower wp-env boot, slower CI, and a WooCommerce version axis to
maintain alongside the WordPress one.

#### Unit 0 — as built

- **WooCommerce pinned to 11.0.1** in `.wp-env.json` top-level `plugins`, which covers
  both the `development` and `tests` environments. Woo 11.0.1 requires WP 6.9 / PHP 7.4;
  `.wp-env.json` already pins core to `WordPress/WordPress#6.9`, so they line up.
- **`php-stubs/woocommerce-stubs ^11.0`** (resolves to v11.0.0) in `require-dev`.
- **Stubs load via `scanFiles`, not `bootstrapFiles`.** PHPStan *executes* bootstrap files;
  the Woo stubs define thousands of classes and executing them is expensive. `scanFiles`
  registers the symbols without executing them. Verified resolving: a probe calling a
  non-existent method reports `Call to an undefined method WC_Product::…`, which is the
  known-class error, not the unknown-class one.
- **Pre-existing:** PHPStan already exceeded a 1G memory limit on this repo *before* the
  Woo stubs were added — confirmed by re-running the unmodified config. Not caused by this
  work, and not fixed by it. Worth its own issue; `phpstan/phpstan` is also two majors
  behind (1.12.34, with 2.x current).
- **Fixture helper** at `tests/phpunit/helpers/class-woo-product-factory.php`, loaded from
  `tests/phpunit/bootstrap.php` after the WordPress bootstrap (it guards on `ABSPATH`).
  Creates simple, variable, grouped, and external products, plus hidden-catalog-visibility
  and managed-stock variants for D5 and the stock bar. `skip_if_unavailable()` keeps the
  suite runnable on environments without WooCommerce.
- **Adding Woo to `.wp-env.json` is necessary but NOT sufficient**, and the failure is
  silent. The WordPress test suite runs against its own freshly-installed database in
  which no plugins are active — activation state from the dev site does not carry over.
  WooCommerce reported `active` by `wp plugin list` was still entirely absent from the
  PHPUnit run, so all eight fixture tests *skipped* while reporting `OK`. Green-because-
  skipped is worse than red. Two additions to `bootstrap.php` fix it:
  1. `tests_add_filter( 'muplugins_loaded', '_manually_load_woocommerce', 5 )` — requires
     `WP_CONTENT_DIR . '/plugins/woocommerce/woocommerce.php'` when present. Priority 5 so
     Woo is defined before DesignSetGo, whose blocks gate on `class_exists( 'WooCommerce' )`.
  2. `WC_Install::install()` after the WP bootstrap — product CRUD writes to
     `wc_product_meta_lookup` and friends, so loading the plugin alone leaves every fixture
     failing on a missing table.

  **Any future CI job must assert a non-zero assertion count for the `woocommerce` group**,
  not merely a zero exit code, or this regression returns unnoticed.
- **No regression to the existing suite.** Loading Woo globally does not disturb the
  `product-categories-grid` / `product-showcase-hero` tests that stub `wc_get_product()` —
  their `function_exists()` guards simply stop stubbing. Full suite: 1120 tests, 4702
  assertions, 0 failures, 1 pre-existing documented skip.
- **wp-env ports collide across worktrees.** Several DSGo worktrees run wp-env
  simultaneously (9451, 8889, 9461, 8899, 8989, 8990 were all taken). Give each worktree
  its own `port` / `testsPort` in `.wp-env.override.json`, which is gitignored. This
  worktree uses 9471 / 9472.
- **`composer.lock` stays uncommitted** — `.gitignore` excludes it deliberately, since the
  lock pins to the generating PHP version.

## Verification

Per the pre-commit gate, plus per-unit:

- **Unit 0:** ✅ Done. PHPStan clean with Woo stubs resolving; PHPCS clean; PHPUnit
  1120 tests / 4702 assertions / 0 failures with the eight fixture tests *asserting*
  (24 assertions) rather than skipping; `lint:js`, `lint:css`, and `test:unit`
  (2864 tests) clean.
- **Item 1:** every source returns `null` (not a fatal, not an empty string) when Woo is
  absent, when the post is not a product, and when the product is unpurchasable. WP 6.7
  price-HTML behaviour resolved.
- **Item 2:** hidden / `exclude-from-catalog` products absent from the loop with
  `wooCatalogVisibility` on and present with it off; price sort correct for variable
  products.
- **Item 3:** cart count correct on a full-page-cached response; no-JS fallback adds to
  cart with JS disabled; variable / grouped / external products link out rather than
  failing an AJAX add.
