---
Last updated: 2026-08-24
---

# DesignSetGo Abilities Verification — Runtime Mode

## Status: PASS (was FAIL — 3 accuracy failures found and fixed)

20 abilities registered, verified statically and then against a live
WordPress 7.1 + WooCommerce environment (`wp-env`, Abilities API present).

The audit found three accuracy failures, all in the **discovery** abilities
— the ones an agent calls first to decide what to do next. None were
security issues; all three made the plugin describe itself incorrectly. All
three are fixed, along with eight lower-severity findings.

Verification evidence: 1300 PHPUnit tests pass (4 pre-existing
`Map_Embed_Render_Test` failures, confirmed identical on a clean tree, plus
1 pre-existing skip), `phpcs` clean on all changed source, runtime
enumeration and permission roundtrip captured below.

## Inventory — static and runtime agree

Static enumeration by reflection found 20 concrete `Abstract_Ability`
subclasses; `wp_get_abilities()` on the live site returns the same 20 with
the same names. Registration fires correctly; no source-only or
runtime-only divergence.

Abilities are auto-discovered — `Abilities_Registry` globs
`includes/abilities/{info,inserters,configurators,settings}/class-*.php` —
so there is no hardcoded list to drift. (`generators/` is wired but empty.)
Registration goes through `Abstract_Ability::register()`, which calls
`wp_register_ability()` **indirectly** to dodge Plugin Check's static
"requires WP 6.9" scan, so the canonical `grep 'wp_register_ability('`
enumeration finds one call site rather than 20 — worth knowing for any
future audit of this tree.

| Registered category | Count | Abilities |
|---|---|---|
| `info` | 6 | `list-abilities`, `list-blocks`, `list-extensions`, `list-dynamic-tag-sources`, `get-post-blocks`, `find-blocks` |
| `blocks` | 10 | `add-block`, `add-child-block`, `add-accordion-item`, `add-tab`, `add-timeline-item`, `update-block`, `batch-update`, `configure-custom-css`, `configure-shape-divider`, `delete-block` |
| `settings` | 4 | `get-settings`, `update-settings`, `get-global-css`, `update-global-css` |

All 20 are `show_in_rest: true` and inherit `meta.public: true`.

## Annotation correctness

Every callback was read end-to-end and compared against its claim. **No
`readonly: true` ability writes anywhere** — no `$wpdb` calls, no option or
post writes, no filesystem or cron effects, no non-GET delegates. No
`verify-ignore` suppressions were needed anywhere in the tree.

| Ability | Claim | Result | Evidence |
|---|---|---|---|
| all 6 `info` abilities | readonly, idempotent | OK | read the block registry / post content / extension configs only |
| `get-settings`, `get-global-css` | readonly, idempotent | OK | `Settings::get_settings()`; theme `custom_css` post read |
| `delete-block` | destructive | OK | `wp_update_post` at `configurators/class-delete-block.php:197` |
| 5 inserters | idempotent=false | OK | each call appends another block |
| `update-block`, `batch-update`, `configure-*` | idempotent | OK | attribute merges / wholesale replacement; repeating is a no-op |
| `update-settings`, `update-global-css` | idempotent | OK | field-merge and wholesale replace respectively |

`delete-block` keeps `idempotent: false` on purpose, now documented in
code: targeting is by block **index**, and indices shift after a removal,
so a repeated call deletes a *different* block. (Declaring it idempotent
would also flip its REST routing from POST to DELETE, since core maps
`destructive && idempotent` → DELETE.)

## Permission gates — runtime roundtrip

Exercised via `WP_Ability::check_permissions()` against four real user
contexts. Every callback is Shape A (`current_user_can( '<cap>' )`); no
Shape B-bad (`WP_REST_Request` in a permission callback), no Shape E
(literal `true`), no `__return_true`.

| Ability | Capability | anon | subscriber | editor | admin |
|---|---|---|---|---|---|
| `list-abilities`, `list-blocks`, `list-extensions` | `read` | deny | **allow** | allow | allow |
| `list-dynamic-tag-sources`, `get-post-blocks`, `find-blocks` | `edit_posts` | deny | deny | allow | allow |
| all inserters + configurators | `edit_posts` | deny | deny | allow | allow |
| `get-settings`, `update-settings` | `manage_options` | deny | deny | **deny** | allow |
| `get-global-css`, `update-global-css` | `edit_css` | deny | deny | allow¹ | allow |

¹ `edit_css` maps to `unfiltered_html`, which editors hold on single-site
and only super-admins hold on multisite — the same gate as the Customizer's
Additional CSS panel.

Two things worth recording:

- **The layering is right.** Content-modifying abilities gate on
  `edit_posts` at the ability level and then re-check
  `current_user_can( 'edit_post', $post_id )` against the specific target
  post inside `Block_Inserter` (`class-block-inserter.php:49`) and
  `Block_Configurator` (`:48`, `:407`, `:577`). Holding `edit_posts` does
  not grant write access to a post the user cannot edit. This was checked
  specifically for `configure-custom-css` and `configure-shape-divider`,
  which have no `edit_post` call of their own and rely entirely on the
  helper — they are covered.
- **The three `read` gates are deliberate, not an oversight.**
  `tests/phpunit/abilities-security-test.php` explicitly asserts that a
  subscriber *can* list abilities and blocks. Left unchanged.

## Schema lints

Machine-checked across all 20. All six lints pass: `additionalProperties:
false` declared on every object schema, every entry in every `required`
array has a non-empty description, no empty or single-value enums, no
`$ref`, and every `default` is a static literal (`get-settings` correctly
uses `new \stdClass()` for its empty `properties`).

Note that enum *contents* are a separate axis from enum *shape* — F2 and F3
below were content failures on schemas that passed every structural lint.

## Findings and resolutions

### F1 — FAIL → fixed: `list-abilities` reported a category vocabulary that did not exist

`info/class-list-abilities.php` derived each ability's category from its
**name prefix** (`inserter` / `configurator` / `info` / `settings`) rather
than reading the `category` it was registered with. The two vocabularies
disagreed: no ability was ever reported as `blocks` (10 are registered
there), `get-global-css` was reported as `info` because the name starts
with `get-`, and `update-global-css` as `configurator`.

An agent filtering `{"category": "settings"}` got 2 abilities and never
discovered the two global-CSS ones.

**Fixed:** `infer_category()` deleted; the ability now reports
`$config['category']`, and the enum is `all | info | blocks | settings`.
Runtime confirmation — `category=settings` now returns 4, `category=blocks`
returns 10, unfiltered returns `{"blocks":10,"info":6,"settings":4}`.

### F2 — FAIL → fixed: `list-blocks`'s category filter could only ever return one bucket

`normalize_category()` mapped blocks into `layout` / `interactive` /
`visual` / `dynamic` using legacy `designsetgo-*` slugs that no block uses
any more. 66 of 68 blocks declare `"category": "designsetgo"` → all became
`layout`; `flip-card-face` and `section-divider` declare `"design"` → fell
through to the `visual` default. `interactive` and `dynamic` returned zero
blocks, and every block's reported category was wrong.

**Fixed:** the synthetic remap is gone. Blocks now report `category`
verbatim from `block.json`, plus a `group` sourced from
`includes/admin/blocks-registry.json` — the same file behind the Blocks &
Extensions admin screen, so the grouping matches what site owners already
see. The input filter is now `group`, with its enum derived from the
registry so a new group becomes filterable without touching the ability.

Runtime confirmation:

```
total=68  groups={"containers":3,"forms":12,"interactive":7,
                  "ui":18,"uncategorized":20,"widgets":8}
verbatim categories={"designsetgo":66,"design":2}
```

### F3 — FAIL → fixed: `list-dynamic-tag-sources` could not filter the WooCommerce group

The `group` enum hardcoded five groups. A sixth, `woocommerce`, is
registered by `class-dynamic-tags-sources-woo.php:52` whenever WooCommerce
is active. Because the schema also sets `additionalProperties: false`,
`{"group": "woocommerce"}` was rejected by schema validation *before* the
callback ran — so the six Woo bindings sources could not be scoped to at
all, which is exactly the filter an agent building a product loop reaches
for.

**Fixed:** the enum is now read from the live registry
(`Registry::instance()->all_groups()`), guarded by `class_exists()` with a
fallback to the built-in five. Sources register on `init` priority 6 and
abilities at priority 9, so the registry is populated by the time
`get_config()` runs — and the enum correctly *omits* `woocommerce` when Woo
is inactive. Runtime confirmation: enum is `post, site, archive, user,
custom-fields, woocommerce`, and `{"group":"woocommerce"}` returns all 6
`designsetgo/woo-*` sources.

### W1 — fixed: four writers carried no annotations at all

`update-block`, `batch-update`, `configure-custom-css`, and
`configure-shape-divider` passed no `annotations` key. Core defaults every
flag to `false`, so nothing was actively misrepresented, but they were the
only four abilities with no declared contract and no `instructions` string.
All four now declare the full triple plus instructions.

### W2 — fixed: `idempotent` was declared inconsistently across pure reads

`get-settings` and `get-global-css` declared `readonly + idempotent`; the
six `info` abilities — equally pure reads — declared `readonly` only. Every
ability now declares all three flags explicitly, so `tools/list` has one
consistent shape.

### W3 — fixed: `list-extensions` metadata was stale in both directions

`EXTENSION_META` had drifted from `includes/extension-configs/`:

- **Shipped but undescribed** — `interactions`, `schema`, `style-binding`,
  and `visibility` had config files but no META entry, so they were
  returned with an auto-generated label and an **empty description**. Two
  of them, `visibility` (`dsgoVisibility`) and `style-binding`
  (`dsgoStyleBinding`), are among the most rule-heavy extensions in the
  plugin — an agent got the raw attribute schema and no guidance on the
  rule shape.
- **Described but never emitted** — `dynamic-tags` had a full META entry
  but no matching config file, so the loop never reached it.

All four are now documented (including the JSON-LD block list for `schema`
and the rule shapes for `visibility` / `style-binding`); the dead
`dynamic-tags` entry is removed. META and configs now match 19/19 exactly.

### W4 — fixed: `list-abilities` omitted annotations from its output

The ability tells agents "call this first", but its output carried no
`readonly` / `destructive` information, forcing a second round-trip to REST
introspection. It now emits an `annotations` object per ability.

### W5 — no change: the three `read` gates are intentional

`list-abilities`, `list-blocks`, and `list-extensions` gate on `read`,
which subscribers hold. Initially flagged as over-permissive, but
`abilities-security-test.php` contains explicit tests asserting subscribers
*can* call them — this is a tested design decision about a non-privileged
block catalog, not drift. Left as-is and now documented in
`docs/api/ABILITIES-API.md`.

### W6 — fixed: error codes were not namespaced

Codes were generic (`invalid_post`, `permission_denied`, `block_not_found`,
`missing_post_id`, …) and could collide with another plugin's codes in an
agent's error-matching logic. All ability-specific codes are now prefixed
`designsetgo_`, including the HTTP-status map in
`Abstract_Ability::get_default_status_for_error()` and the ~25 assertions
in the ability test files.

`rest_forbidden` deliberately keeps its unprefixed name — it is the code
WordPress core uses for the same condition and REST clients already match
on it.

### W7 — fixed: `update-settings` group properties were undescribed

The eight top-level settings groups (`performance`, `forms`, `animations`,
`security`, `integrations`, `sticky_header`, `draft_mode`, `llms_txt`) had
no `description`. All eight now do, including the note that
`animations.block_animations` is replaced wholesale while other list fields
merge positionally.

### W8 — fixed: documentation drift

- `docs/api/ABILITIES-API.md` documented **14 of 20** abilities — missing
  all four settings abilities, `configure-custom-css`, and
  `list-dynamic-tag-sources` — and labelled the info group "(5)" when there
  are 6. Now complete, with a category table, a Settings Abilities section,
  a runtime-verified permissions matrix, and a corrected error-code table.
- `docs/ARCHITECTURE.md` referenced
  `includes/abilities/class-abilities-provider.php` (no such file) and a
  `register_ability()` function (the real one is `wp_register_ability()`).
  Replaced with the real auto-discovery architecture and a real ability as
  the example.
- `docs/testing/TESTING-ABILITIES-API.md` used the dead
  `category=layout` filter and unprefixed error codes. Updated.

*(An earlier draft of this report listed `designsetgo/add-animation` as a
nonexistent ability referenced in ARCHITECTURE.md. That was wrong — it is a
JS filter namespace in an extension example, not an ability.)*

## Open item — not an abilities bug

`includes/admin/blocks-registry.json` lists **48 of 68** registered blocks.
The 20 missing ones now surface honestly as `group: "uncategorized"` in
`list-blocks` output:

> advanced-heading, chart, comparison-table, dynamic-image, fifty-fifty,
> flip-card-back, flip-card-front, heading-segment, product-categories-grid,
> product-showcase-hero, query, query-filter, query-group-header,
> query-no-results, query-pagination, query-results, section-divider,
> sticky-sections, timeline, timeline-item

This matters beyond the abilities surface: that file drives the Blocks &
Extensions admin screen, so those 20 blocks appear to have no enable/disable
control there. Worth a separate look — it is a product decision about which
groups they belong to, not something this audit should guess at.

## Re-verification

Rerun the runtime checks after any change to the discovery abilities:

```bash
npx wp-env run cli wp eval '
  $m = array_filter( wp_get_abilities(), fn($a) => strpos($a->get_name(),"designsetgo/")===0 );
  echo count($m) . PHP_EOL;'

npx wp-env run tests-cli --env-cwd=wp-content/plugins/designsetgo \
  vendor/bin/phpunit --filter Abilities
```
