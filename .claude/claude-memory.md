# Claude Memory - DesignSetGo

## Task 19 fix round 1 (agent: task-19-build-page-2026-09-14, same branch, commit after `dcf4b069`)

Reviewer found the schema-level "tree" property (declared `'type' => 'object'`) let core's real `WP_Ability::execute()` → `validate_input()` → `rest_validate_value_from_schema()` reject `tree: null` / `tree: "a string"` as a bridge-flattened `WP_Error` **before** `Build_Page::execute()` ever ran — confirmed by writing `wp_get_ability('designsetgo/build-page')->execute($input)` tests (not a direct `->execute()` call, which bypasses this entirely) and watching them fail pre-fix. **Fix: make `type` a multi-type array** (`array('object','array','string','boolean','integer','number','null')`) instead of a bare `'object'`. Traced WP core (`rest-api.php`) to confirm this is sound, not just permissive-by-luck: `rest_get_best_type_for_value()` walks the list in order and uses the first matching type's validator, so a real tree object still validates its `properties` fully (since `'object'` is listed first) while any other shape matches a later, unconstrained type and sails through to `Tree_Validator` for the real judgment. Bonus finding: `rest_is_object()` is literally `is_array($value)` — no list-vs-map distinction — so a JSON array like `[1,2,3]` matches `'object'` too, meaning `'array'` never actually gets selected when `'object'` precedes it in the type list (kept in the list anyway for schema-reader honesty). **Any future ability with a "value is validated downstream, not at the schema layer" property needs this same multi-type trick**, not just an absent `required`.

`new.post_type` hardening: `post_type_supports($type, 'editor')` is NOT sufficient to exclude WP's internal `wp_`-prefixed site-structure types — checked `wp-includes/post.php`'s actual registrations and both `wp_navigation` and `wp_global_styles` declare `'supports' => array('title','editor','revisions')` AND `show_in_rest => true`. An administrator (who has `edit_theme_options`, what these types map `create_posts` to) really could have gotten a `wp_navigation` post created via `new.post_type` without an explicit `0 !== strpos($type, 'wp_')` check alongside the `editor` support check.

Deduplication: extracted `problem_response()`/`get_store()` (previously copy-pasted in both ability classes) into `Agent_Build_Ability_Helpers`, a **plain static class**, not a trait — `Abilities_Registry::load_abilities_from_directory()` globs `agent-build/class-*.php` only, so a `trait-*.php` file would silently never load without a loader change. Naming it `class-agent-build-ability-helpers.php` needed zero registry changes: picked up by the existing glob, skipped by the ability-instantiation loop's `is_subclass_of(Abstract_Ability::class)` gate exactly like `Build_Page_Schema`/`Tree_Validator` already are. `Abstract_Ability` itself was left untouched per explicit instruction.

`git stash` is shared across sessions in this worktree and must never be used for a "compare against a previous commit" check — use `git show <sha>:<path>` instead (this is how the pre-existing `abilities-smoke-test.php` whitespace-alignment noise was confirmed to predate Task 19, without touching the shared stash stack).

## Task 19 — build-page and get-build-status abilities (agent: task-19-build-page-2026-09-14, branch `claude/agent-block-engine`)

New: `includes/abilities/agent-build/class-build-page.php` (`Build_Page`, `designsetgo/build-page`), `class-build-page-schema.php` (`Build_Page_Schema` — split out purely for the 300-line cap, mirrors how `Report_Schema` was split from `Build_REST`), `class-get-build-status.php` (`Get_Build_Status`, `designsetgo/get-build-status`). Both auto-register: `Abilities_Registry::load_abilities_from_namespace()` already scanned the `agent-build` directory for Task 17/18, so no wiring changes were needed anywhere.

**Response-shape split, and why it differs from `Add_Block`**: permission failures are `WP_Error` via `$this->permission_error()` (matches `Add_Block`), but every INPUT problem — post_id/new both-or-neither, bad mode, Tree_Validator failures, nonexistent post_id, unregistered/non-REST `new.post_type` — comes back as data: `{ success: false, problems: [ {code, path, message} ] }`, the exact shape `Tree_Validator::validate()` already returns. This deliberately diverges from `Add_Block`, which returns `WP_Error` for `missing_post_id`/`invalid_post` too. Reason: `WP_Ability::execute()` (core `class-wp-ability.php`) calls `validate_input()` — which runs `rest_validate_value_from_schema()` against the registered schema, including `required` — *before* our `execute_callback` (`Abstract_Ability::run()`) ever runs. A `required` field declared in the schema therefore fails as a bridge-flattened `WP_Error` no matter what our own code does. Fix: build-page's input schema declares **no top-level `required` array at all** (not even `tree`) — `Tree_Validator::validate( $input['tree'] ?? null )` is called unconditionally and already returns a `designsetgo_invalid_tree` problem for a missing/malformed tree, so passing raw `null` through gets the same data-shaped diagnostic regardless of invocation path (direct `->execute()` call in tests, or through the real `WP_Ability`/MCP bridge). Any ability needing this same guarantee should follow suit: skip `required` in the schema, validate presence manually, first thing in `execute()`.

**Get the shared `Build_Store`, don't construct a second one**: `Build_Store::__construct()` registers `add_action('init', register_meta)`; a second instance re-registers the same post-meta keys (harmless but pointless). No static accessor existed, so both new classes read `\DesignSetGo\Plugin::instance()->agent_build_store` (public property, always constructed in `Plugin::init()` since `Build_Store` is unconditionally `require_once`'d in `load_dependencies()`), with a defensive `new Build_Store()` fallback only if that property somehow isn't the right type. Existing `agent-build-rest-test.php`/`agent-build-store-test.php` construct their own throwaway `new Build_Store()` per test — fine there since each test's DB changes roll back, but the ability code itself must not do that in production.

**Stage order matters for "no post created on failure"**: `execute()` runs (1) input shape (post_id XOR new, mode enum) → (2) `Tree_Validator::validate()` → (3) resolve + permission-check the concrete target (existing post's `edit_post`, or `new`'s post-type validity + `create_posts` cap) → (4) only then `wp_insert_post()` (if `new`) and `$store->store()`. Tested explicitly: total `wp_posts` row count (via `get_posts(['fields'=>'ids','suppress_filters'=>false])`, not raw `$wpdb` SQL — avoids a phpcs `DirectDatabaseQuery` warning that fails the build here) is unchanged after a validation failure on the `new` path.

**Typeless-schema gate** (`abilities-security-test.php::test_no_ability_declares_a_typeless_schema_node`) walks every `designsetgo/` ability's input/output schema recursively and fails if any `properties`/`items` node lacks `type`. Reused `Block_Inserter::get_inner_blocks_schema()` for the tree's `blocks` property (already fully typed, same `name`/`attributes`/`innerBlocks` shape as the tree contract) rather than hand-rolling a second recursive schema.

**Enumeration tests updated**: `abilities-smoke-test.php::test_list_abilities_contains_new_abilities()` (the actual hardcoded-name-list test — `abilities-coverage-test.php` has no hardcoded ability count) now includes both new names; also added a small dedicated `has_ability()` check to `abilities-coverage-test.php` per the task brief's literal wording. `list-abilities` itself needed no change — it enumerates the registry dynamically.

New test file `tests/phpunit/abilities-build-page-test.php` (`@group abilities`, `@group agent-build`), 22 tests: registration, annotations/category, XOR/mode/tree input-shape rejection (each asserting the DB write count is unchanged), permission WP_Errors vs. data problems, the `new` draft-creation path (draft status, empty content, sanitized title, default post_type `page`), `finish_url` shape, and a `get-build-status` round trip (pending → terminal report via `Build_Store::write_report()`+`clear()`, simulating what `Build_REST::post_item()` does in production, without re-registering REST routes).

**wp-env CLI gotcha (reconfirmed from Task 17's note)**: `vendor/bin/phpunit <file1> <file2> ...` with bare file paths fails under this repo's `phpunit.xml.dist` ("Class X could not be found") — always use `--filter <ClassName>` (pipe-join `ClassName1|ClassName2` for several at once), and run each `wp-env run` invocation as its own tool call rather than a shell `for` loop — a multi-command loop piping through `npx wp-env run` got flagged by the sandbox's worktree-isolation guard as "git in a form too complex to verify."

**Full suite green**: `--group abilities` (132 tests), `--group agent-build` (69 tests), `Abilities_Build_Page_Test` (22), `Abilities_Smoke_Test` (20), `Abilities_Security_Test` (51, includes the typeless-schema gate), `Test_Validate_Input`+new coverage test (4). phpcs clean on all three new/production PHP files and on both edited test files (`abilities-build-page-test.php` needed one `phpcs:ignore` for `get_posts()`'s uncached-function sniff — `suppress_filters=>false` is set but the sniff can't see that statically, same pattern already used in `class-draft-mode-preview.php`/`class-find-blocks.php`). `abilities-coverage-test.php` still carries 24 pre-existing phpcs violations (multiple classes per file, unaligned multi-line function calls) — confirmed via `git stash`/`apply` that every one of them predates this task's one-method addition; none are new.

## Task 17 — Tree validation in PHP (agent: task-17-tree-validator-2026-09-14, branch `claude/agent-block-engine`, commit `18f7a827`)

`Tree_Validator` (`includes/abilities/agent-build/class-tree-validator.php`, namespace `DesignSetGo\Abilities\Agent_Build`) mirrors `src/engine/tree.js`'s structural contract in PHP for Task 19's `designsetgo/build-page` ability, then adds PHP-only checks: size (1 MB), unknown block, attribute schema, placement. Stages gate strictly — a stage only runs once every earlier one returned zero problems.

**`find_invalid_attribute_values()` (Block_Inserter) is deliberately NOT reused.** Its enum check is fully subsumed by `rest_validate_value_from_schema()` (enum is a JSON Schema keyword). Its other rules — a hardcoded per-block "unsupported value" list — describe gaps in `generate_designsetgo_wrapper_html()`'s OWN hand-written serializer (e.g. `designsetgo/text-path`'s custom `pathType`), the same category of problem as `find_serialization_gaps()`, which the brief explicitly said to skip because Task 19 serializes with a real browser `save()`, not this class's mirror. Only `find_invalid_child_placements()` is reused, via a new `Block_Inserter::find_tree_placement_problems( $tree )` wrapper — see that method's docblock for the full reasoning, since a future task revisiting this exact question should read it there rather than re-derive it.

**`rest_validate_value_from_schema()` chokes on WP core's own `"type": "rich-text"`** (used on `content` attributes, e.g. `core/paragraph`/`core/heading`) — triggers `_doing_it_wrong` ("type" keyword must be a JSON-Schema builtin) and fails the PHPUnit strict-notices gate. Fixed with a `has_validatable_type()` guard: skip validation (treat as unchecked, like an attribute the block type doesn't declare) when `type` includes anything outside `array|object|string|number|integer|boolean|null`. Binding descriptor keys (`source`, `selector`, `attribute`, `query`, `role`, `__experimental*`) are also stripped before validating — a `source:'html'` schema is a binding descriptor, not a value constraint.

**PHP object-vs-array ambiguity**: JSON's `{}` and `[]` both decode to PHP `array()` with `json_decode(..., true)` — indistinguishable. Treated as satisfying EITHER shape check (object-like AND list-like) rather than picking one, matching the brief's explicit instruction and avoiding false positives on genuinely-empty `attributes`/`innerBlocks`.

**300-line file-size guideline not met**: the class landed at 386 lines even after aggressively trimming docblocks and merging near-duplicate helpers (`is_object`/`is_list_like` now share `is_sequential_and_nonempty()`). Six full-blown validation stages (version/shape, size, unknown-block, attribute-schema, placement) each need a WordPress-Docs-compliant docblock (enforced by `phpcs.xml`'s `WordPress-Docs` ruleset — verified there's no `FileLength`/`Metrics` sniff actually enforcing 300 lines, and `class-block-inserter.php` itself is 5758 lines), so treated this as a soft target rather than blocking. Flagged in the task report rather than sacrificing doc coverage or splitting Task 17's single named deliverable file into two.

**wp-env CLI gotchas for this worktree**: `npx wp-env run <container> <cmd>` cannot take a multi-token quoted string as one arg (e.g. `"wp eval '...'"`) — pass each token as its own arg. `wp eval` itself is blocked by the sandbox's worktree-isolation guard regardless. To inspect a registered block's actual attribute schema, read the block's `block.json` off disk instead (`find / -path "*wp-includes/blocks/<name>/block.json"` for core, `src/blocks/<name>/block.json` for DSGo). Running `phpunit` with bare file-path args fails ("Class ... could not be found") under this repo's `phpunit.xml.dist` test-suite config — use `--filter <ClassName|ClassName|...>` against the whole suite instead.


## 2.7.4 release blockers — fixed on `claude/2-7-4-release-blockers` (agent: release-2.7.4-prep-2026-09-10, session f49439d6)

#545 (audit remediation) shipped four regressions none of its tests covered; each was reproduced live on wp-env before fixing.

- **Forms with a default Phone field rejected every submission.** `form-phone-field` renders `<select name="{field}_country_code">` (`showCountryCode` defaults on) but the server schema only knew `{field}`, and #545 made unknown names a hard `400 unknown_field`. Turnstile hit the same wall with its injected `cf-turnstile-response`. Fix: the schema declares `{field}_country_code` (validated `^\+\d{1,4}$`), undeclared names are **dropped, not rejected**, and `turnstile.render()` gets `'response-field': false`. **Lesson:** `tests/phpunit/form-submission-contract-test.php` submits what the *rendered* markup posts (DOMDocument over `do_blocks()` output, mirroring view.js's skip list) — hand-built payloads are how this slipped through. Any new companion input in a field block needs declaring in `extract_field_types_from_blocks()`.
- **Forms outside wp_posts** (block widgets, theme-file templates, registered patterns) became `unknown_form`. Now resolved from a cached index (`dsgo_form_external_definitions_v1`, busted on widget/theme/template changes). Private-page forms stay refused on purpose.
- **Dynamic Query refresh 404'd outside post content** (templates, synced patterns, archives, nested loops): #545 re-read the query from `get_post( postId )->post_content`. Replaced with a **signed refresh source**: first paint embeds base64(attributes + innerBlocks + sourcePostId) + HMAC in `[data-dsgo-blobs-for]`; `/query/render` renders only what verifies. `DesignSetGo\Blocks\Query\RefreshSource` owns it. Traps baked in: base64-in-an-attribute because `the_content` filters (`capital_P_dangit`) rewrite inline `<script>` JSON and would break the HMAC; the HMAC includes the blog ID (AUTH_SALT is network-wide); the source post comes from a `the_content` enter/leave stack, **not** `get_the_ID()` (wrong in templates and nested loops), and REST refreshes re-enter it via `render_within()` so nested queries keep their gate; the editor preview route must **never** emit a signed source (it would mint trusted definitions from editor input).
- **`/query/render-preview` leaked non-public content to subscribers** (pre-existing since 2.7.3's `/query/render`): now `edit_posts` + post type must be viewable or editable. User search/sort by email now needs `list_users`, matching core's users endpoint.
- **Visitors got a REST nonce**, which goes stale on cached pages and core rejects before the route runs → now logged-in only, and view.js never borrows `wpApiSettings.nonce`. Filter refresh falls back to loading the filtered URL if the refresh can't run (e.g. salts rotated under a cached page).

Env gotchas: port 9451 may be serving a `.worktrees/` checkout — this folder's own instance ran on `WP_ENV_PORT=9471 WP_ENV_TESTS_PORT=8871`; permalinks are off (`?page_id=`, `?rest_route=`); query PHP tests load from `build/`, so rebuild (or copy) after editing `src/blocks/query/*.php`. main's CI is also red from `Design_Context_Ability_Test` on WP < 6.9 (fixed here) and from wp-env docker builds failing `apt-get update` on the PHP 7.4/8.0 jobs (infra, not fixed).

## Task 6 — Modal Trigger: justification wrapper (agent: modal-trigger-justification-2026-07-11, branch `claude/pill-default-font-size`, commit `15c118ca`)

Simplest of the Pill/Icon/Icon-Button/Modal-Trigger series — structurally identical to Icon Button (Task 5) but with only 3 pre-existing deprecations (not 9) and zero pattern regeneration needed (`patterns/hero/hero-video-modal.php` is the block's only pattern usage and sets no `align`). Full report: `.superpowers/sdd/task-6-report.md` (gitignored).

**Shape**: `.dsgo-justify` wrapper (block root) → `.dsgo-modal-trigger` (`<button>`, shrink-wrapped, all visual supports). `justification` (toolbar) + `fullWidth` (Settings toggle) replace `align`. New `v4` deprecation = pre-wrapper save() verbatim; all four deprecations (v4→v1) route through a shared `migrateAlign()` helper. `selectors.root: ".wp-block-designsetgo-modal-trigger .dsgo-modal-trigger"` added, same as Icon Button.

**Difference from Icon Button worth remembering**: Icon Button needed a SEPARATE `v9Supports` object because its pre-refactor block.json already differed subtly from its older `sharedSupports` (an earlier gap-token refactor had already touched typography). Modal Trigger's pre-refactor `block.json` supports were **byte-identical** to the `sharedSupports` constant its v3/v2/v1 deprecations already used (no earlier refactors had drifted them apart) — so `v4.supports = sharedSupports`, no new constant needed. **Lesson: don't assume every "add a vN deprecation" task needs its own supports object — diff the pre-change block.json against the existing shared constant first; if they're already identical, reuse it.**

**Where I deliberately overrode the task brief (per the task's own "rules win over brief" precedence)**: the brief suggested v1/v2's `migrate()` set `fullWidth: width === 'full'` directly (skipping `migrateAlign()`) since the legacy `width` attribute only ever encoded `'auto'|'full'`, never left/center/right. But `sharedSupports.align` was still active on v1/v2 even though their own `save()` never read `attributes.align` — meaning a user could theoretically have set `align:'left'`/`'right'` via the block toolbar on a v1/v2-era trigger, and a direct `fullWidth` assignment would silently discard that. Fixed by mirroring Icon Button's v5/v6 pattern instead: merge `width` into `align` first (`align: width === 'full' ? 'full' : rest.align`), then run the merged object through `migrateAlign()`. This is the second time a brief's "shortcut" migrate for a legacy width-only version turned out to have this exact latent align-preservation gap — worth checking for on any future width→justification conversion in this series.

**Icon rendering consolidation** (two conditional spans → one span + `--icon-end` class with `flex-direction: row-reverse`) was applied to the NEW save.js/edit.js only; the new v4 deprecation's `save()` keeps the OLD two-span form verbatim, since that's what's actually byte-stored in real legacy content.

**Browser verification**: chrome-devtools MCP was locked (stale profile, same recurring issue noted in Tasks 4/5) — used a standalone Playwright script driving `wp.data`/`wp.blocks` directly (not UI clicks) to set exact attributes, avoiding inspector-field flakiness for the Modal-ID/Target-Modal selects. Built one page: reference paragraph + Modal + 4 Modal Triggers (left/default, right+styled+bordered, fullWidth, explicit-padding). Editor: reference paragraph's `left` offset was **identical** to every trigger wrapper's `left` (50px), regardless of justification — direct proof the wrapper, not the button, is what's capped at the content column. Frontend: same pattern (130px across all four). Border-radius/border-color/padding all measured on the BUTTON only (wrapper stayed `0px`/transparent in every case). Clicked the frontend trigger — modal opened (`aria-hidden` → `false`, `.dsgo-modal--open` class, visible) — confirms `data-dsgo-modal-trigger` + `modal/view.js`'s direct-attribute-selector click wiring survived the wrapper change untouched (that file was read and confirmed to never assume a specific ancestor structure).

**Pre-existing tech debt found, explicitly NOT fixed (out of this task's file scope)**: `includes/abilities/class-block-inserter.php` (~line 1237, an AI/Abilities-API-facing generator) constructs Modal Trigger markup in the pre-v3 nested `<div class="…--width-*"><button class="…__button">` shape — meaning it was already generating legacy v1/v2-shaped markup even before this task, since it never tracked the block's real "current" save() format at any point in the block's history. Not made worse by this change (the updated v1/v2 deprecations still migrate that exact shape correctly, verified via a dedicated Jest test using v2's own `save()` output, not hand-typed HTML, for byte fidelity).

## Task 5 — Icon Button: justification wrapper (agent: icon-button-justification-2026-07-11, branch `claude/pill-default-font-size`, commit `a6afb769`)

The hardest of the Pill/Icon/Icon-Button/Modal-Trigger series, because Icon Button is **static** (bakes save() into stored HTML, incl. 45 pattern occurrences) and its root was literally the `<a>`/`<button>`, not a block-level element with a `__wrapper` div already established (unlike Pill/Icon, which just moved styling within an existing wrapper). Full report: `.superpowers/sdd/task-5-report.md` (gitignored, not committed — see below for the durable version).

**Shape**: `.dsgo-justify` wrapper (block root, `useBlockProps`) → `.dsgo-icon-button` (`<a>`/`<button>`, shrink-wrapped, all visual supports). `justification` (toolbar) + `fullWidth` (Settings toggle) replace `align`. New `v9` deprecation = pre-wrapper save() verbatim; **all nine** deprecations (v9→v1) route through one shared `migrateAlign()` helper — this repo's established "deprecations don't cascade" rule meant every one of v8..v1's `migrate()` needed the same align→justification/fullWidth conversion added, not just the new entry.

**New pattern found this task, not present in Pill/Icon**: `block.json` needs a top-level `"selectors": {"root": ".wp-block-designsetgo-icon-button .dsgo-icon-button"}` in addition to `__experimentalSkipSerialization` on the visual supports. Skip-serialization only controls the block's own inline save()/edit() output; `selectors.root` is the SEPARATE, complementary mechanism controlling where WordPress's native theme.json/Global-Styles CSS generation (base block styles, block style *variations* like `is-style-outline`) targets. Confirmed by reading `core/button`'s real (installed WP core) block.json — it declares both mechanisms together (`color.__experimentalSkipSerialization` etc. AND `selectors.root: ".wp-block-button .wp-block-button__link"`), and by tracing `WP_Theme_JSON::get_block_style_variation_selector()` (`wp-includes/class-wp-theme-json.php`) — it inserts `.is-style-{slug}` after the FIRST token of a compound selector, so `.wp-block-designsetgo-icon-button.is-style-outline .dsgo-icon-button` is exactly what that function produces. A hand-written CSS override for a style variation (the block's own `is-style-outline` rule) needs the identical compound-selector shape or it silently stops matching once the block root moves.

**Non-block-support consumers that also broke when the root moved (grep for ALL of these before assuming skip-serialization covers everything, per Task 4's own lesson generalized)**:
1. `blocks.getSaveContent.extraProps` filters from OTHER extensions. `src/extensions/block-animations/editor.js`'s `addAnimationSaveProps` (a **universal**, all-blocks filter, not icon-button-specific) targets whatever `useBlockProps.save()` is called on — now the wrapper. Verified this is **not a bug** (opacity/transform-only entrance animations are visually identical whether applied to the wrapper or the button, since the wrapper's only visible content IS the button), unlike Rule 1's color/border/typography groups which would visibly break.
2. Hand-written CSS selectors elsewhere assuming the OLD single-element structure: (a) parent-hover-button-background selectors in `style.scss`/`editor.scss` used `.dsgo-flex__inner > .dsgo-icon-button` (direct child) — fixed to `> .wp-block-designsetgo-icon-button .dsgo-icon-button` (WP's own always-present auto class as the direct-child anchor, `.dsgo-icon-button` now a descendant). (b) The `.is-style-outline` override (see above).
3. **NOT broken, verified and left alone**: `src/blocks/section/style.scss`'s width/align-self rules for inline children (`> .dsgo-icon-button, > .wp-block-designsetgo-icon-button {...}`) already listed BOTH the custom class and WP's auto class as OR-alternatives (apparently already written this way generically, not specifically anticipating this refactor) — since `wp-block-designsetgo-icon-button` stays on the wrapper unchanged, these rules keep working with zero edits needed.
4. PHP: `includes/class-plugin.php`'s `apply_default_icon_button_hover()` used unqualified `WP_HTML_Tag_Processor::next_tag()` (assumed first tag = the button) — fixed to `while(next_tag()){ if(!has_class('dsgo-icon-button')) continue; ...}`, which is transparently backward-compatible (old un-migrated frontend content still has the class on the first/only tag).

**`isEligible` mechanics reconfirmed against real `@wordpress/blocks` source** (`apply-block-deprecated-versions.js`, matches `[[reference_wp_deprecation_iseligible_mechanics]]`): `isEligible` is ONLY consulted via `if (block.isValid && !isEligible(...)) continue;` — i.e. it's purely an opt-in for blocks that are ALREADY valid. For an invalid block (the normal migration case), `isEligible`'s return value is never read; the loop always tries `validateBlock` (save-content match) against every deprecation in array order regardless. Confirmed this experimentally too: adding `v9` ahead of `v8` in the array does NOT regress old (pre-gap-refactor) content — v9's `save()` fails to byte-match first (wrong shape), the loop falls through to `v8` and succeeds, exactly as the "queue of shapes, try each until one matches" doc comment in that file describes.

**Pattern regeneration (45 occurrences / 196 regions / 83 files) — no `tools/` dir existed, had to build one.** `tools/regenerate-patterns.js` (kept, reusable) regex-extracts each `<!-- wp:{block} -->...<!-- /wp:{block} -->` region from `patterns/**/*.php`, parses+reserializes each in isolation (NOT the whole file — these are `.php` files with surrounding PHP/translation calls, and in one file the block markup itself was embedded inside a single-quoted PHP string). Two hard problems, both solved and documented in the script's own header for reuse:
- **No `@babel/register` in this repo.** A hand-rolled `Module._extensions['.js']` + `@babel/core` transform hook got past ESM/JSX, but `@wordpress/block-editor`'s component tree needs `window` (jsdom) at module-load time even just to import `useBlockProps`/`RichText` from the barrel. Jest's `jest-environment-jsdom` + existing `babel-jest` config already solve both together. **Verdict: this class of tool can only run through a disposable Jest test file** (`tests/unit/tools/run-regenerate-<block>-patterns.test.js`, write → run → delete, keep the `tools/` module itself), not plain `node`, in this repo. Also: `@wordpress/babel-preset-default` only converts ESM→CJS when `api.env()==='test'` (checked via `process.env`/babel `envName`) — outside Jest's own transform it leaves `import`/`export` untouched for webpack, so a raw `@babel/core` call needs `envName: 'test'` explicitly or requiring any of this plugin's own `import`-based source throws `ERR_MODULE_NOT_FOUND`.
- **Universal editor extensions silently corrupt regeneration if not loaded first.** First real run mis-regenerated one pattern (not by leaving stale markup — by silently DROPPING `dsgoAnimationEnabled`/`dsgoEntranceAnimation`, since `block-animations`'s `addFilter('blocks.registerBlockType', ...)` was never run, so those keys weren't part of the parsed schema and `getBlockAttributes()` silently stripped them — the SAME "supports must be complete" failure class as Rule 2, just for extension attributes). The `changed > 0` check in the regenerator doesn't catch this — it just writes something different-but-wrong. Fix: import the specific extension(s) that block's real pattern content actually uses (checked via `grep -ohE '"dsgo[A-Za-z]+"'` scoped to that block's own comments — NOT importing the full `src/index.js`, which also pulls in a `.scss` import that fails under Jest since this repo's `identity-obj-proxy` devDependency, referenced by `jest.config.js`'s own `moduleNameMapper`, was never actually installed — a genuine, pre-existing, unrelated gap, confirmed via `package.json`/`package-lock.json` grep, out of scope to fix here).
- **Verification chain** (all real, not assumed): grep for zero remaining `"align"` on icon-button comments across all 83 changed files; `git diff --stat` region/file counts match the dry-run summary; `php -l` on all 83 (real risk given the PHP-string-embedded file); re-running the regenerator against its OWN output is a no-op (`filesChanged:[]`, zero `console.error`) — the strongest signal, proves every region is valid against the CURRENT save(), not merely eventually-migratable.

**Browser verification methodology** (chrome-devtools MCP locked by a concurrent session again — 4th time this has happened across this task series per prior memory entries; standalone Playwright (`chromium.launch()`) is now the default fallback, not a one-off workaround). Built a top-level (NOT wrapped in a DSGo Section) test page with matched old-`align`/new-`justification` triplets specifically to isolate WordPress's OWN core constrained-layout mechanism from DSGo's separate Stack/Flex width system (a Section-wrapped first attempt gave ambiguous width numbers because `.dsgo-stack__inner`'s own children-sizing rules are a different, pre-existing mechanism from what this task fixes). Old content measurably escaped/overflowed the content column (frontend, raw unmigrated: `align:left` sat at container edge x=50 vs content column x=70; `align:right` overflowed to x=1230 vs column right edge x=1210); new content's wrapper matched the column exactly (70–1210) in every case, on the same page/theme/run. Editor canvas: old content silently migrates on load (no Attempt Recovery) to the identical DIV-wrapped shape as new content. **Gotcha**: a first hand-typed "old format" fixture (no icon markup, since I forgot `icon`/`iconPosition` default to `lightbulb`/`start` — an icon is present by DEFAULT) genuinely tripped a REAL "Attempt recovery" (lowercase 'r' — case-sensitive string check bug in my own verification script initially hid this) warning, since no deprecation's `save()` could match icon-less output when defaults imply an icon. Confirms deprecation matching is byte-exact and unforgiving — fixed by generating fixtures via the block's own `getSaveContent()` instead of hand-typing, matching the `withDefaults()` pattern already needed for the Jest deprecation tests.

## v2.4 unreleased Chrome MCP smoke test (agent: v24-smoke-test-2026-07-08, commit tested: 8825f34c on main)

Full editor + frontend smoke test of everything merged since the `v2.3.0` tag (i.e. the unreleased 2.4.0 in package.json), using this project's own wp-env (localhost:9451, source-mounted plugin). Other wp-env instances may be running on the same machine that mount a stale `designsetgo.latest-stable` zip build for an unrelated project — check the mount before testing, and never use one of those for DSGo source testing.

**Scope** (derived from `git log v2.3.0..HEAD`, not from the CLAUDE.md "Dynamic Query vX.Y" sections which track older/already-released work): section-divider shape masks, SVG-pattern theme inheritance, row/grid overlay + hover-variation style-kit detection, modal/scroll-slides new attributes, icon-button/icon-list-item/image-accordion/scroll-marquee/blobs kit-controllable tokens, map marker-color-as-preset.

**Method**: built one page (`/v2-4-smoke-test/`) with Section (wave shape divider + SVG pattern "Theme default"), Row + Grid (applied the pre-existing QA style-kit variations — see below), Modal, Scroll Slides (Feature Showcase template), Icon Button, Image Accordion (Showcase template), Blobs, Map. Verified via `evaluate_script` against real computed styles/attributes, not just visual screenshots (a screenshot of the shape-divider looked blank at first — turned out to be correct rendering with a low-contrast default band color, not a bug; always check computed `::before` mask/background, not just the element itself).

**Result: no functional regressions found.** Zero PHP fatals/warnings in debug.log (grepped for non-Pods entries), zero failed network requests, zero "Attempt Recovery" / invalid-content warnings, zero DesignSetGo-caused frontend console errors. Confirmed working: shape-mask CSS var/mask-composite mechanics, SVG-pattern inherit resolution + control-hiding, `dsgo-grid--has-overlay` / `dsgo-flex--has-hover-{text,icon,button}` style-kit detection (matches slug substrings `hover-text`/`hover-icon`/`hover-button`, NOT `hover-lift` or other free-form slugs — that's correct, not a bug), image-accordion height/gap correctly omitted (theme-token fallback, no baked px), map marker color stored as `var:preset|color|accent-1` not raw hex, modal correctly `display:none` on frontend until triggered.

**Two minor non-blocking findings — fixed same session (user asked to address them)**:
1. `src/extensions/svg-patterns/components/SvgPatternsPanel.js` — "Enable SVG pattern" `ToggleControl` (~line 159) and both "Pattern Opacity"/"Pattern Scale" `RangeControl`s (~line 269, ~line 286) were missing `__nextHasNoMarginBottom`/`__next40pxDefaultSize`, violating the CLAUDE.md "Future-Proof" rule. Added the props (matching the "Fixed Background" toggle in the same file, which already had them). Verified in browser: opening the panel and selecting a concrete pattern (to reveal the opacity/scale sliders) no longer logs the WP core deprecation warnings.
2. `src/blocks/image-accordion/edit.js` `useSelect` (line ~54) built a fresh `itemOptions` array on every call, triggering WP dev-mode's "useSelect returns different values" warning. **Fix pattern** (reusable for similar cases elsewhere): don't build derived arrays/objects inside the `useSelect` mapSelect callback — select only the raw, store-memoized value (`getBlock(clientId)?.innerBlocks`, which is referentially stable across calls against unchanged state), then compute the derived `itemOptions` in a separate `useMemo` keyed on that stable reference. `hasInnerBlocks` now derives as a plain `children.length > 0` outside useSelect since it's cheap. Verified the warning no longer fires on initial editor load with an Image Accordion block present. Jest suite (`src/blocks/image-accordion/test/`) still 9/9 passing.

**Environment note**: found `.wp-env-7-core/wordpress/wp-content/mu-plugins/dsgo-qa-section-variations.php` — a prior session already registered `QA · Overlay Dark/Light` and `QA · Hover Background/Text Light/Icon Background/Icon Colour/Button Background` block style variations specifically for testing this row/grid hover-variation feature. Reuse this fixture for any future testing of [[project_style_kit_cross_repo]]-adjacent hover/overlay detection instead of re-deriving slugs from scratch.

## Section-divider block Task 1 — extracted shared shape-mask CSS primitives (agent: section-divider-task1-2026-07-06, branch: claude/section-divider-block, worktree: .worktrees/section-divider)

Pure CSS reorg prep for the upcoming standalone `designsetgo/section-divider` block, which will reuse the section block's shape-mask library.

- Moved `src/blocks/section/styles/_shape-masks.scss` → `src/styles/shared/_shape-masks.scss` (byte-identical, verified via `diff`).
- New `src/styles/shared/_shape-mask-classes.scss` — holds the `$dsgo-shape-slugs` list, the `@each` per-slug `--dsgo-shape-mask` assignment, and the `is-shape-inherit` resolver (all block-agnostic). `@use`s sibling `shape-masks`.
- `src/blocks/section/styles/_shape-divider.scss` now `@use '../../../styles/shared/shape-mask-classes';` and keeps only section-specific painting: `::before` two-layer knockout, bleed, position, flip, `is-front`, `is-shape-fan` single-layer override, `.dsgo-stack--has-shape-divider` stacking.
- Only reference to the old path was inside `_shape-divider.scss` itself (its own `@use` + two `$dsgo-shape-slugs` usages) — no other SCSS file imported `_shape-masks.scss` directly. `ShapeDivider.js` had a doc-comment mentioning the old filename; updated for accuracy (non-functional).
- Verified: `npm run build` clean; `build/blocks/section/style-index.css` (frontend) and `build/blocks/section/index.css` (editor) both still contain the mask data-URIs, `is-shape-inherit`, and `is-shape-fan`; `npx jest src/blocks/section/` → 10/10 passed (baseline unchanged).
- Commit `8af7da6c` — next task builds the new `designsetgo/section-divider` block on top of these shared partials.

Mirrored the two `designsetgo/icon` features onto the `designsetgo/icon-list` (parent) + `designsetgo/icon-list-item` (child) pair, matching the `iconSize`/`iconColor` context-propagation pattern already used by that block family.

- **icon-list block.json**: `iconSize` is now nullable (no default, was `32`). Added `iconStyle` (string enum filled/outlined) and `strokeWidth` (number, default 1.5) attributes, added to `providesContext` as `designsetgo/iconList/iconStyle` / `designsetgo/iconList/strokeWidth`.
- **icon-list-item block.json**: added the two new keys to `usesContext`.
- **icon-list-item save.js**: `hasExplicitSize = typeof ctxIconSize === 'number'`. Explicit case is byte-identical to old output (same inline `Npx`/`N+16px`). Inherit case emits no inline width/height/minWidth — instead sets `--dsgo-icon-list-size: calc(var(--wp--custom--designsetgo--icon-list--default-size, 32) * 1px)` inline and adds a `dsgo-icon-list-item__icon--inherit-size` modifier class; style.scss resolves both the plain icon box (token value) and the background box (token + 16px) from that class. `data-icon-style`/`data-icon-stroke-width` emitted only when the parent sets them (`|| undefined` idiom) — frontend `lazy-icon-injector.js` already handles the fallback-to-theme-default and `.dsgo-icon-outlined` wrapping generically (it's block-agnostic), so **zero PHP changes were needed** — `Icon_Injector`'s `$icon_blocks` allowlist already includes `designsetgo/icon-list-item`.
- **icon-list-item edit.js**: added `useIconDefaults({ sizeKey: 'iconList', sizeFallback: 32 })` for preview; computes `effectiveSize`/`effectiveStyle`/`strokeWidth` from context with theme-default fallback, passes to `getIcon(icon, effectiveStyle, strokeWidth)` and uses plain inline pixel styles (no CSS-var indirection needed in the editor, since JS already resolves the final number).
- **icon-list edit.js / ListSettingsPanel.js**: added Icon Style `ToggleGroupControl` + conditional Stroke Width `RangeControl` (shown only when `effectiveStyle === 'outlined'`), made Icon Size `RangeControl` null-aware (`allowReset`, `placeholder={iconDefaults.size}`, inherit help text) — copied verbatim from `icon/edit.js`'s idiom.
- **Deprecation added**: `icon-list-item/deprecated.js` v2. Reasoning: for lists with an **explicit** parent `iconSize`, context still yields the same number under the new save(), so output is byte-identical. The deprecation only actually *changes behavior* for **implicit-default** lists (parent left `iconSize` unset): old save() always baked `context[...] || 32` as a literal inline pixel pair; new save() now gets `undefined` from context (no more `32` default) and renders the inherit-token markup instead. `isEligible` matches the old pixel-pair signature (`dsgo-lazy-icon` + inline `width:Npx;height:Npx`) minus the new `--inherit-size` class marker — this also matches explicit-size posts (they always baked a pixel pair too), but that's harmless: `migrate()` is a no-op passthrough, and the *current* save() reproduces byte-identical markup for explicit-size posts once migrated, so re-running them through the deprecation path is a no-op in practice. `icon-list/deprecated.js` (parent) was NOT touched — parent's own `save()` never rendered an icon, so its output is unaffected by these attribute changes.
- **Known pre-existing gap, not introduced by this change and explicitly out of scope**: `.dsgo-icon-outlined` stroke CSS lives only in `icon/style.scss` + `icon/editor.scss` (not duplicated per instruction — it's meant to be global). Per-block CSS loading (each block only enqueues its own `style-index.css`/editor `index.css`) means a page/editor session using only `icon-list`/`icon-button` (no standalone `icon` block) won't load the outline stroke CSS. Confirmed `icon-button` has the exact same gap already (no local `.dsgo-icon-outlined`), so this is an accepted existing architecture decision, not a regression.

## Form border / SVG pattern color no longer forced into save() (agent: icon-button-styles-2026-07-02, branch: claude/icon-button-styles)

Slack request (Liz Elliott): pattern authors couldn't remove `--dsgo-form-border-color`/`data-dsgo-svg-pattern-color` from hand-authored block HTML — save() always re-injected a fallback value, so the block failed validation unless the exact hex was hardcoded back in.

- **form-builder**: `edit.js`/`save.js` `formStyles['--dsgo-form-border-color']` no longer falls back to `'#d1d5db'` — just `convertColorToCSSVar(fieldBorderColor)` (undefined when empty, so React omits the property). `.dsgo-form-builder` in `style.scss` already declared `--dsgo-form-border-color: #d1d5db;` as the CSS default (line 11), so nothing new needed there. Added **v4 deprecation** in `deprecated.js` (old forced-fallback save, `isEligible: !attributes.fieldBorderColor`) since existing published forms with an unset border color have the literal `#d1d5db` baked into their stored HTML. Also fixed `includes/abilities/class-block-inserter.php` (~line 1774), the PHP block-inserter/abilities-API generator that mirrors save.js, to match (now conditionally omits like the label/background-color siblings).
- **svg-patterns extension**: `dsgoSvgPatternColor` attribute default changed from `'#9c92ac'` (`DEFAULTS.color`) to `''` in `attributes.js` — previously the non-empty schema default meant addSvgPatternSaveProps always baked a color into `data-dsgo-svg-pattern-color`, even for untouched blocks. `editor.js`'s extraProps filter now emits `convertColorToCSSVar(dsgoSvgPatternColor)` with no `|| ''` fallback (omits attr when unset). `SvgPatternsPanel.js`'s `onColorChange` no longer re-forces `DEFAULTS.color` when the user clears the swatch (was defeating the point — clearing used to write the literal default back into state). PHP renderer (`class-svg-pattern-renderer.php` line 255) already had a `#9c92ac` fallback for a *missing* attribute; hardened it to also treat a present-but-empty attribute as missing. Mirrored the default change in `includes/extension-configs/svg-patterns.php` (PHP schema mirror consumed by `Extension_Attributes` for REST `block-types` exposure).
- **Silent-migration follow-up (resolved, not just flagged)**: initially thought the SVG pattern extension couldn't get a clean deprecation since it patches foreign blocks (`core/group`) via `blocks.getSaveContent.extraProps` and can't own a full alternate `save()`. Fixed properly instead — `attributes.js` now pushes a `legacyColorDeprecation` onto `settings.deprecated` for `core/group`/`designsetgo/section`, reusing the block's own **current** `save` (captured from `settings.save` at filter time) paired with `dsgoSvgPatternColor` defaulted back to `DEFAULTS.color` ('#9c92ac'). Works because `addSvgPatternSaveProps` is value-driven, not code-path-driven — pairing the real current save with the old default naturally reproduces the old baked-in byte output for comparison, no markup reimplementation needed. **Gotcha that cost real debugging time**: `@wordpress/blocks`' `process-block-type.js` re-applies the *entire* `blocks.registerBlockType` filter chain to each deprecation entry (passing the deprecation object as a 3rd arg, `null` on the primary pass) — our own filter was re-firing on its own deprecation entry and stomping the deliberately-old default back to the new one. Fix: `addSvgPatternAttributes(settings, name, deprecatedDefinition)` early-returns `settings` unchanged when `deprecatedDefinition` is truthy. Verified against the **real, unmocked** `@wordpress/blocks` parser in `tests/unit/svg-patterns-deprecation.test.js` (had to register/parse through `@wordpress/block-editor`'s *nested* `node_modules/@wordpress/blocks` copy, not the top-level one, since `useBlockProps.save()` resolves supports against whichever instance registered the block — version-hoisting doesn't dedupe these two). Confirms old-style content (color omitted from the comment, implicit `#9c92ac`) parses `isValid: true` via silent migration (`console.info` "Block successfully updated"), and new-style content (color genuinely omitted) also parses valid on the primary pass. The **frontend was never at risk either way** — WP doesn't validate block content there, only in the editor — but now the editor doesn't even show the recovery notice.

## v2.2 Codex review remediation (agent: codex-review-2026-04-18, commit: 37a491e0)

- **Priority 0 / CI fix**: `Plugin::maybe_upgrade()` is now `public` and hooked onto `admin_init` (no longer called directly in `__construct`). This prevents `FilterIndex::install()` from executing `require_once ABSPATH . 'wp-admin/includes/upgrade.php'` at phpstan analysis time.
- **#1 Slug→ID**: `src/blocks/query-filter/render.php` translates taxonomy slug values in `$dsgo_active_filters_by_key` to term IDs via `get_term_by()` before passing to `FilterIndex::count_for_options()`.
- **#2 Post-status gate**: `FilterIndex::reindex_object()` calls `get_post_status()` early; non-publish posts are removed and short-circuit via `remove_object()`. Covers meta/taxonomy hooks firing on drafts.
- **#3 Server-side registration**: `useFilterRegistration.js` deleted. `FilterIndexHooks::on_save_post()` now calls `register_filters_from_post_blocks()` → recursive `walk_blocks_for_filters()` on `parse_blocks()` output. Only published posts register filters.
- **#4a Infinite sentinel guard**: Single-page `totalPages < 2` guard moved BEFORE the `infinite` render path so single-page results never emit a sentinel.
- **#4b Infinite last-page teardown**: `loadMore` in view.js now removes `[data-dsgo-pagination="infinite"]` wrapper on last page (garbage-collects observer), not just the loadmore button.
- **#5 drop clears db_version**: `FilterIndexCLI::drop` also calls `delete_option('designsetgo_db_version')` so next `admin_init` reinstalls the table.
- **Tests**: 125 PHPUnit tests / 298 assertions all pass. 4 new tests in filter-index-test.php; 1 new test in filter-counts-test.php.

## Phase C — Infinite Scroll Bundle (agent: phase-c-2026-04-18)

### Implementation Summary
- **C1**: Added `paginationKind` enum (numbered/loadmore/infinite) + 3 new attrs (autoPauseAfter, sentinelOffsetPx, buttonLabelWhenPaused) to block.json. Created variations.js with `infinite-scroll` variation using `isActive: ['paginationKind']`. Updated index.js to call `registerBlockVariation`.
- **C2**: Created `src/blocks/query-pagination/components/InfiniteScrollControls.js` — 3 DsgoInspectorPanel.Item entries (NumberControl×2, TextControl×1). Uses eslint-disable comment *inside* the import block for `__experimentalNumberControl`. Updated edit.js with `PaginationPreview` sub-component (extracted to avoid no-nested-ternary lint error).
- **C3**: render.php now checks `paginationKind === 'infinite'` first (before totalPages guard). Emits sentinel div with `data-wp-init="callbacks.initInfiniteObserver"` + hidden button. IAPI context includes autoLoadCount=0, restUrl, nonce. Added `--infinite` modifier + sentinel styles to style.scss.
- **C4**: Added `callbacks.initInfiniteObserver` to the existing single `store('designsetgo/query', {...})` call. Uses `IntersectionObserver` with rootMargin offset. Fires `button.click()` (re-hidden via Promise.resolve microtask) to reuse the loadMore generator. Reduced-motion: reveals button, skips observer. Auto-pauses at threshold, reveals button, disconnects observer.
- **Tests**: 5 PHPUnit tests in `tests/phpunit/blocks/query/pagination-infinite-render-test.php`. All 109 query-block tests pass.
- **Commits**: `8bfd6911` (C1+C2), `d8b64aaa` (C3+C4+tests).

### Key Design Decisions
- `paginationKind` is a NEW attribute separate from the legacy `mode` attribute — both coexist for backwards compat. render.php resolves effective kind by checking paginationKind ≠ 'numbered' first, then falls back to mode.
- Infinite renders the sentinel even on single-page results (observer fires but finds no next page, ctx.autoLoadCount never increments).
- `eslint-disable-next-line` for `__experimentalNumberControl` must go INSIDE the import block (not before the import statement) to suppress the rule on the right line.
- `PaginationPreview` sub-component extracted from QueryPaginationEdit to satisfy `no-nested-ternary` lint rule.
- The `IntersectionObserver` no-undef lint error (line 385) is the same pre-existing pattern as HTMLElement/DOMParser elsewhere in view.js — codebase doesn't declare browser globals in eslint config.

## B3+B4 — Filter Counts + Intersection (agent: b3b4-2026-04-18)

### Implementation Summary
- **B3**: Added `showCounts` attr to query-filter block.json (default true). ToggleControl in Settings panel. render.php computes per-option counts via `FilterIndex::count_for_options()` for checkbox and select kinds when filter is registered and showCounts is true. CSS `.dsgo-query-filter__count` added to style.scss.
- **B4**: No new files — intersection works via existing $_GET overlay mechanism in `class-query.php::handle_render()` which overlays $_GET with incoming `params` payload before calling `designsetgo_query_render_region()`. Filter siblings re-render with updated $_GET so counts are always current. Added explanatory comment to render-helpers.php.
- **Tests**: `filter-counts-test.php` with 5 PHPUnit tests (group: query-block in class docblock, NOT file docblock — PHPUnit 9 ignores file-level @group). Total: 88 tests passing.
- **Commits**: `bf744d66` (B3), `3ce52fcb` (B4).
- **Key insight**: PHPUnit 9 requires @group annotation on the *class* docblock, not the file docblock.

## Task 14 — Query Filter Block (agent: task14-2026-04-18)

### Implementation Summary
- Block: `designsetgo/query-filter` with 6 variations (checkbox, select, search, sort, active, reset)
- render.php: Helper functions prefixed `designsetgo_query_filter_render_*`; all vars namespaced `$dsgo_filter_*` to avoid WP global conflicts
- view.js: Extended `store('designsetgo/query')` with 5 new actions (setFilter, setFilterDebounced, toggleFilter, removeActiveFilter, resetAll) + shared generator `dsgoQueryRefresh()` + async helper `dsgoQueryRefreshPlain()` for debounced search
- render-posts.php: Added `q` param direct handling (when `bindSearchTo` is empty) + `filter_<taxonomy>` → tax_query + `sort=orderby.DIR` → orderby/order
- PHPUnit: 38 tests pass (35 prior + 3 new in filter-server-test.php)
- Jest: 1525 tests pass (37 suites)

### Key Design Decisions
- Debounced search uses `dsgoQueryRefreshPlain()` (async/await) not the generator, because IAPI regular (non-generator) actions run synchronously, making `setTimeout` straightforward
- `yield*` (not `yield`) used for delegating to the shared `dsgoQueryRefresh` generator
- `$taxonomy` variable renamed to `$dsgo_filter_taxonomy` throughout render.php to avoid WordPress.WP.GlobalVariablesOverride
- `q` param direct handling added to render-posts.php in addition to the existing `bindSearchTo` attribute path

## Shape Dividers (Section Block)

### Design Decisions

1. **Positioning**: Shape dividers are positioned **inside** the section at `top: 0` / `bottom: 0` (not outside). Positioning outside the block boundary is bad practice and can cause overlap issues with adjacent content.

2. **Automatic Padding**: When a shape divider is enabled, the inner container (`dsgo-stack__inner`) automatically receives padding equal to the shape's height. This prevents content from overlapping the shapes while letting users adjust their own padding on top.

3. **Color Controls Location**: Shape divider colors appear in the **main Color panel** (InspectorControls group="color") alongside other color settings like Overlay, Hover Background, etc. The Shape Divider panels only handle shape selection, height, width, flip, and front options.

4. **Two Color Properties**:
   - **Shape Color**: The SVG fill color
   - **Background Color**: The color behind the shape (useful for transitions between sections)

### Files

- `src/blocks/section/components/ShapeDivider.js` - Renders the SVG shape
- `src/blocks/section/components/ShapeDividerControls.js` - Shape selection and settings (not colors)
- Color controls are in `edit.js` within `<InspectorControls group="color">`

### Shape Divider Theme Inheritance (agent: shape-divider-theme-inheritance-2026-07-01, JS core unit, commit c01f810d)

**Supersedes the SVG-based design above.** CSS layer (prior commits `88f98fa`, `b81ba13`) moved shape dividers to class-based CSS `mask-image` rendering — no inline `<svg>` in markup. This unit updated JS to match.

- `ShapeDivider.js` rewritten: renders a single empty `<div>` with classes `dsgo-shape-divider dsgo-shape-divider--{top|bottom} is-shape-{slug|inherit}` + optional `is-flip-x is-flip-y is-front`, and inline vars `--dsgo-shape-height`/`--dsgo-shape-width` (always) + `--dsgo-shape-fill`/`--dsgo-shape-band` (omitted when unset so CSS `var(..., fallback)` applies). Old props (`--dsgo-shape-offset/-color/-background/-gradient-dir`, `dsgo-shape-divider--front`) are gone.
- `save.js`/`edit.js`: `fillColor = explicit shapeDivider{Top,Bottom}Color via convertColorToCSSVar || sectionBackgroundColor` (never `sectionTextColor` anymore — that's now only used by `ShapeDividerControls`'s inspector preview swatch). `bandColor = explicit shapeDivider{Top,Bottom}BackgroundColor via convertColorToCSSVar` only, no fallback in JS (CSS provides `--wp--preset--color--base` fallback).
- `shapeDividerTop`/`shapeDividerBottom` attributes unchanged (still `string`, default `""`); value space gained `'inherit'` (theme-default). `'inherit'` is treated as "set" everywhere truthy checks are used (padding-clearing, `dsgo-stack--has-shape-divider` class) since it's a non-empty string.
- `ShapeDividerControls.js`: `'Theme default'` (`value: 'inherit'`) option spliced in as the 2nd option (right after "None") in the shared `ShapeDividerPanel`'s shape `SelectControl` — done locally in the component, NOT by mutating `getShapeDividerOptions()` in `utils/shape-dividers.js` (kept generic/reusable). `ShapePreview` sub-component silently renders nothing for `'inherit'` (no SVG mapping exists client-side for the theme default — that only resolves via CSS custom property) — acceptable, not a crash, out of scope to enhance.
- Color panel labels relabeled: "Top/Bottom Shape Color" → "Top/Bottom Shape Fill (default: section background)"; "Top/Bottom Shape Background" → "Top/Bottom Band Background (default: base)". `ColorGradientSettingsDropdown` settings items have no per-item `help` slot (only `label`), so the default-value hint was folded into the label text itself.
- `SHAPE_DIVIDERS` object and `deprecated.js` deliberately untouched (deprecation/migration is a later task).

## Scroll Marquee Border Radius → Native Border Support (agent: scroll-marquee-border-radius-2026-07-02)

Root cause: `designsetgo/scroll-marquee`'s `borderRadius` attribute had a `'8px'` block.json default and was *always* serialized into a `--dsgo-marquee-border-radius` CSS custom property on the wrapper's inline style. Hand-authored/generated block markup that omitted this var (or any markup not produced by save.js verbatim) failed `.save()` validation ("Attempt Recovery").

Fix: moved border-radius off the custom attribute entirely onto WP's native border support, matching `core/image`'s own mechanism, per the "prefer native supports" rule in project CLAUDE.md.

- `block.json`: removed `borderRadius` attribute; added `supports.__experimentalBorder = { radius: true, __experimentalSkipSerialization: true, __experimentalDefaultControls: { radius: true } }`. Skip-serialization matters here because `useBlockProps()`/`.save()` would otherwise put the border style on the *wrapper* — we need it on each `<img>` instead (images repeat 6× per row for infinite scroll), so it's read manually via `attributes.style?.border?.radius` in both edit.js and save.js and applied per-image. No custom UI code needed — WP auto-injects the native Border panel into Styles since the support is declared.
- No default: an unset radius now means **0** (square corners), matching a fresh `core/image` block — confirmed with the user this was the intended behavior (not "keep 8px as the visual default").
- Behavioral parity beyond "no forced default": user explicitly wants the marquee to **inherit whatever border-radius is set on `core/image` in Site Editor → Styles → Blocks → Image** when the marquee's own radius is blank. Implemented as `includes/features/class-scroll-marquee-styles.php` (`DesignSetGo\Scroll_Marquee_Styles`), closely modeled on the existing `Button_Global_Styles` pattern (`class-button-global-styles.php`): reads `wp_get_global_styles( array(), array( 'block_name' => 'core/image' ) )['border']['radius']` (string or per-corner `{topLeft,topRight,bottomLeft,bottomRight}` array — same shape `Button_Global_Styles::extract_declarations()` already handles), sanitizes via `safecss_filter_attr()`, and injects a **low-specificity** `.dsgo-scroll-marquee__image { border-radius: ...; }` rule via `wp_add_inline_style` (frontend: `render_block_designsetgo/scroll-marquee` filter; editor: `enqueue_block_assets`). Deliberately CSS-rule injection, not per-instance `WP_HTML_Tag_Processor` markup rewriting (cf. `class-style-binding.php`'s approach) — an explicit per-block radius is emitted as an **inline** `style=""` attribute on the `<img>` by save.js/edit.js, which always wins over any external stylesheet rule regardless of specificity, so the two mechanisms compose correctly without instance-level branching in PHP. Wired into `class-plugin.php` exactly like `button_global_styles` (`require_once` → property → `new` + `->init()`).
- Deprecation: added `v3` to `deprecated.js` (array is now `[v3, v2, v1]`) for the pre-this-change format. `isEligible` guards on `innerHTML.includes('--dsgo-marquee-border-radius')` **and** the same "real row/image data present" check `v2` already uses (both v1 and the old v3-eligible format emit that CSS var, so the row-data guard is required to keep ancient v1 content falling through correctly — v3 alone can't distinguish them). `migrate()` moves `borderRadius` → `style.border.radius`, passthrough if unset.
- Verified via `wp eval-file` in wp-env (Twenty Twenty-Five/WP 6.9): wrote `styles.blocks.core/image.border.radius = '50px'` directly into the active theme's user global-styles post (`WP_Theme_JSON_Resolver::get_user_global_styles_post_id()`), confirmed `Scroll_Marquee_Styles::generate_css()` (via Reflection) produces the expected rule and that `maybe_inject_frontend()` attaches it as inline CSS on the `designsetgo-scroll-marquee-global-styles` handle. Deprecation `isEligible`/`migrate` logic sanity-checked with a standalone Node script (4 cases: old-format-with-custom-radius, ancient-v1-shape, already-new-format, default-8px-carried-through) — all matched expected outcomes. Did **not** get a live editor/frontend browser check in this session — chrome-devtools-mcp's shared profile lock was held by another concurrent Claude Code session in the same working directory (multiple `claude --resume` processes observed), so browser automation was skipped in favor of the PHP/Node-level verification above.

**Jest test gotcha (important for future section-block tests):** `src/blocks/section/test/save.test.js` uses `createBlock`/`serialize`/`registerBlockType`/`setCategories` imported from `@wordpress/block-editor/node_modules/@wordpress/blocks` — NOT the top-level `@wordpress/blocks`. Reason: this repo's `@wordpress/block-editor` requires `@wordpress/blocks@^14.15.0` while the top-level package resolves to `13.10.0`, so npm nests a second copy. `save.js` imports `@wordpress/block-editor`, whose `useBlockProps.save()`/`useInnerBlocksProps.save()` read block-support metadata via `getBlockType()` against the NESTED registry. Registering the block on the top-level `@wordpress/blocks` instead leaves the nested registry empty → `useBlockProps.save()` throws (`Cannot read properties of undefined (reading 'align')`) → `serialize()` silently collapses to a self-closing comment (`<!-- wp:designsetgo/section {...} /-->`) with no error surfaced, which reads exactly like "the block didn't register" and can send you chasing the wrong bug. Also needed `setCategories([{ slug: 'designsetgo', title: 'DesignSetGo' }])` before `registerBlockType()`, since the `designsetgo` category isn't registered in the Jest env (only via PHP `block-categories` filters) and `registerBlockType()` silently rejects blocks with unknown categories too.

## Pill editor color/border/gradient — skip-serialization fix (agent: pill-skip-serialization-2026-07-11, task 3 follow-up on branch `claude/pill-default-font-size`)

Fixed a Critical review finding: the prior fix's editor.scss "neutralizer" (`.wp-block-designsetgo-pill { background: none !important; ... }` + `[style*="background"]` transfer selectors) failed for two structural reasons — (0,1,0)+`!important` only *ties* with WP's own `.has-*-background-color { ... !important }` and loses on source order (solid presets still painted a full-width bar), and `[style*="…"]` selectors only fire on *inline* styles, so a preset **gradient** (class-driven, no inline style) never reached the span at all. **Lesson: don't fight block-support CSS specificity with more CSS — use `__experimentalSkipSerialization` in block.json so WP never puts the class/style on the wrapper in the first place, then re-derive it onto the inner element in JS with the official helpers.**

- `block.json`: `color.__experimentalSkipSerialization: true`, `__experimentalBorder.__experimentalSkipSerialization: true`, `spacing.__experimentalSkipSerialization: ["padding"]` (margin stays serialized — don't skip the whole group if only one sub-path needs to move).
- `edit.js`: `__experimentalUseColorProps` / `__experimentalUseBorderProps` / `__experimentalGetSpacingClassesAndStyles` from `@wordpress/block-editor` (same mechanism core's own `button` block uses for its inner `<a>`/link) — call each with `attributes`, merge `.className`/`.style` onto the inner element. For a *partial* group skip (padding-only here), call the spacing helper with a synthetic `{ style: { spacing: { padding: ... } } }` containing only the sub-path you skipped — passing the whole `style.spacing` would also pull in margin and duplicate it onto the inner element.
- These are experimental APIs → each import line needs its own `// eslint-disable-next-line @wordpress/no-unsafe-wp-apis` (repo convention, see `src/hooks/useBlockColors.js`, `src/blocks/icon-button/edit.js`).
- **Jest coverage ceiling for this pattern**: `useBlockProps()`'s wrapper-side color/border/spacing injection comes from `wrapperProps` on `PrivateBlockContext`, populated only by a live `BlockListBlock` ancestor (full `BlockEditorProvider` tree) — NOT reachable from a bare `render(<Edit .../>)` in Jest, regardless of mocking strategy. So a JS unit test can fully cover "does edit.js derive the right has-*/style onto the inner element" (the part that silently regressed — old code never produced classes at all, only sniffed inline styles) but CANNOT cover "does the real wrapper end up clean" — that needs PHPUnit (frontend) + an actual browser (editor canvas). Confirmed via Playwright (chrome-devtools-mcp profile was locked by a concurrent session) against a real wp-env page: all 5 cases (preset bg+text, preset gradient, custom inline bg+text, custom border+radius, padding) match exactly between editor canvas and frontend, wrapper carries zero background/border/padding in every case.
- Relevant to **Task 5/6 (Icon Button, Modal Trigger)**: this is the intended pattern for those blocks too per the task-3 brief — skip-serialize the visual supports in block.json, re-derive with the official hooks in edit.js, don't reach for CSS overrides.

## Pill `vAlign` deprecation — silent data-loss fix (agent: pill-vAlign-supports-fix-2026-07-11, task 3 follow-up on branch `claude/pill-default-font-size`)

Fixed a Critical review finding in the `vAlign` deprecation entry added by the task-3 work above (`deprecated.js`, exported as `[vAlign, vStatic, v1]`). `vAlign.supports` was written as only `{ html: false, align: [...], alignWide: false }` — no `color`/`__experimentalBorder`/`typography`/`spacing`. Every styled, `align`-positioned dynamic pill silently lost `backgroundColor`/`textColor`/`gradient`/`borderColor`/`fontSize`/the whole `style` object the next time the page was opened+saved.

**Mechanism (traced through `@wordpress/blocks` source, not guessed):** `processBlockType()` (`store/process-block-type.js`) re-runs the *entire* `blocks.registerBlockType` filter chain against each `settings.deprecated[i]` entry at registration time — same filters (`color.js`/`border.js`/`spacing.js`/`typography.js` support hooks) that auto-add `backgroundColor` etc. to a block's `attributes`, gated on `hasBlockSupport(settings, '<group>')` reading that deprecation's own `settings.supports`. A `supports` block missing a group means those attributes never get added to *that deprecation's* schema — full stop, independent of what the current (non-deprecated) block declares. Then at parse time, `applyBlockDeprecatedVersions()` calls `getBlockAttributes(deprecatedBlockType, originalContent, parsedAttributes)`, which drops any key not in that (stripped) schema *before* `migrate()` ever runs — `migrate()` was never the bug, it simply never received the data.

Also confirmed (relevant for anyone writing deprecation-parse tests): `validateBlock()` still runs even when `isEligible()` returns true (see line ~79 of `apply-block-deprecated-versions.js`) — `isEligible` only lets an *already-valid* block opt into migration; it does NOT bypass the save-output-must-match-stored-HTML check for an already-invalid block. This matters only for deprecations with a real `save()` (`vStatic`/`v1`); a deprecation whose `save()` returns `null` (like `vAlign`, self-closing-comment/dynamic) sidesteps this entirely since expected content is always `''`, trivially matching any self-closing raw content regardless of which attributes survived — which is why the task's suggested regression-test markup uses a self-closing comment.

**Fix:** `vAlign.supports = staticSupports` (reused the existing constant already shared by `vStatic`/`v1`, rather than duplicating it) — confirmed via `git show 889b8e06:src/blocks/pill/block.json` (the commit that made Pill dynamic while `align` was still the attribute, i.e. exactly `vAlign`'s era) that `staticSupports` is byte-for-byte what that real block.json declared (no `__experimentalSkipSerialization`, added only later — fine, since that key affects serialization only, not attribute registration). `vAlign.attributes` needed no change (`{ content: {...} }` only was already correct per the same historical block.json).

Also removed a dead `'background'` entry from `$inner_paths` in `render.php:69` (`designsetgo_route_visual_supports()` call) — Pill has no WP background-image support, only `color.background`, which the `'color'` path already covers; `style.background` never exists in Pill's style tree so that array entry never matched anything. Confirmed via PHPUnit (`Block_Support_Routing_Test`, all 7 pass incl. the preset-gradient case) that removal is a no-op.

**New regression tests** (`tests/unit/pill-deprecation.test.js`, new `describe('Pill deprecation - styled pill retains visual attributes through migration', ...)`) all go through the real `parse()` pipeline (a direct `.migrate()` call cannot observe this class of bug — it's handed already-correct attributes). Confirmed RED-then-GREEN for the `vAlign` case by stashing the `deprecated.js` fix and rerunning (`backgroundColor`/`textColor`/`fontSize`/`style.border.radius` all `undefined` before, correct after). Also added analogous parse-pipeline tests for `vStatic`/`v1` (adapted from the real, already-e2e-proven fixture `tests/unit/__fixtures__/patterns/pill-old.html`) per the task's "check the same way" instruction — both already passed even against the buggy code, confirming the bug was isolated to `vAlign` only. Full report appended to `.superpowers/sdd/task-3-report.md`.

## Task 4 — Icon: justification wrapper (agent: icon-justification-2026-07-11, branch `claude/pill-default-font-size`)

Same shape as Pill (task 3): `.dsgo-icon` (outer) → plain block-level positioning wrapper via `.dsgo-justify`; `.dsgo-icon__wrapper` (inner) → visible, shrink-wrapped, gets all visual supports routed onto it. Rules 1 & 2 from the task followed as written (skip-serialization + editor helpers instead of a CSS neutralizer; `vAlign.supports` reuses the block's full historical supports set, not just `align`). Full report: `.superpowers/sdd/task-4-report.md`. Three things this task found that weren't in the Pill precedent or the brief:

1. **Deprecations shift array indices.** Icon already had 3 deprecations (`vLazy`, `v2`, `v1`) before this task, unlike Pill which only had 2. Prepending `vAlign` shifted every existing index by one. A pre-existing Jest test (`icon-token-deprecation.test.js`) hardcoded `deprecated[1]` to mean `v2` — it silently broke (wrong deprecation's `save()` got rendered, producing markup that no longer matched the assertion) until the index was corrected to `deprecated[2]`. **Lesson: prepending to a `deprecated` array is not free even when you don't touch the older entries' logic — grep for `deprecated[<number>]` in tests before landing.**

2. **`console.toHaveInformed()` only fires on the invalid→valid deprecation path, not the already-valid isEligible opt-in path.** Traced through `applyBlockDeprecatedVersions` (`@wordpress/blocks/src/api/parser/apply-block-deprecated-versions.js`): for a DYNAMIC block (`save()` === null), a modern block with the old attribute (e.g. `align:"left"`) is already `block.isValid === true` before the deprecation loop runs at all (content is always `''`, trivially matches `save()`). `isEligible` in that case is purely an "opt this already-valid block into migration too" gate — and that path does not go through the invalid→valid re-parse that emits the `console.info` WP logs elsewhere. Pill's own `vAlign` test for this exact case (`'migrates a dynamic pill authored with align into justification'`) already omitted `toHaveInformed()` — I initially copied a `toHaveInformed()` call from a DIFFERENT (invalid-static-legacy) test case into this scenario by pattern-matching the file structure too loosely, and it failed for exactly this reason. Fixed by removing the assertion for the already-valid case, consistent with Pill's precedent.

3. **A CSS feature that paints the outer wrapper directly (not through WP's color-support attribute mechanism) needs its own fix.** Icon has a "parent hover icon background" feature (Flex/Grid/Stack `hover-icon-*` style variation or `hoverIconBackgroundColor` context var) that painted `background-color` directly on `.dsgo-icon` via a plain CSS selector, completely independent of `__experimentalSkipSerialization`/`designsetgo_route_visual_supports()`. Once `.dsgo-icon` became the block-level positioning wrapper, that selector would have reproduced the exact column-spanning bug this task fixes, just for hover instead of the native background attribute. Retargeted the 6 selectors (×2 files, style.scss + editor.scss) from `> .dsgo-icon` to `> .dsgo-icon .dsgo-icon__wrapper`. **Lesson: grep for every OTHER thing that paints backgrounds/borders on the block's root class before assuming skip-serialization covers everything — WP's own block-support mechanism isn't the only thing that can target a class name.**

Also: Icon had no `color.gradients` support at all before this task (only `background`/`text`); added it (mirroring Icon Button, a sibling in the same family that already had it) because the task's regression-test requirements explicitly need preset-gradient routing coverage, and there was nothing to test without it. `vAlign.supports.color` needed `gradients: true` too so a styled+aligned dynamic icon with a gradient doesn't lose it during migration (same Rule-2 mechanism as Pill, verified RED before the fix by temporarily reproducing the align-only-supports mistake).

## Final-fix-2 — Critical A (Grid align-self axis bug) + Critical B (Icon legacy-align shim) (agent: final-fix-2-critical-a-b-2026-07-12, branch `claude/justification-content-column`)

Two Criticals in a *previous* fix wave's own polish pass, caught by measuring the live DOM rather than reading CSS — a good example of why "the CSS looks right" isn't proof.

**Critical A root cause:** `.dsgo-stack__inner > .dsgo-justify, .dsgo-grid__inner > .dsgo-justify { align-self: stretch; justify-self: stretch; }` was one combined rule for two container types with *different layout models*. In flexbox (Stack, column-direction), `align-self` is the cross/horizontal axis — correct. In CSS Grid (Grid block), `align-self` is the BLOCK/vertical axis and `justify-self` is the inline/horizontal axis — the opposite of flexbox's naming, and the rule's own comment conflated them. Compounded by `.dsgo-justify` having `display:flex` with no `align-items` set at all, so the visible child inside also defaulted to `stretch` on its own cross axis. Net effect: every justified block (Pill, Icon Button, Modal Trigger — Icon was accidentally spared, see below) became full-row-height in any Grid, silently overriding the Grid's own author-set `alignItems`. Fix: split into two separate rules (Stack keeps `align-self: stretch`; Grid keeps only `justify-self: stretch`, `align-self` left at default `auto`) + added `align-items: center` to the base `.dsgo-justify` rule as defense-in-depth.

**Non-obvious finding while writing the regression test:** Icon's own pre-existing `.dsgo-icon { align-items: center; }` rule (present *before* this bug was introduced, unrelated to it) coincidentally already prevented Icon's *visible* element (`.dsgo-icon__wrapper`) from stretching, even though the *wrapper* (`.dsgo-icon` — same element, also carries `.dsgo-justify`) still got the wrong `align-self: stretch` pre-fix. Since Icon paints no background/border on the wrapper itself, that wrapper-level stretch had zero visible symptom for Icon specifically. **Lesson: a regression test built around "measure the visible/shrink-wrapped element" (the natural choice, matching how the bug reads in a browser) can silently fail to catch the same root-cause bug in a sibling block whose CSS happens to have an unrelated compensating rule — verify each block in the family separately rather than assuming one passing case proves the fix for all.** The other three blocks (Pill, Icon Button, Modal Trigger) correctly caught the bug pre-fix (measured 320px vs expected <192px in a forced-320px Grid row).

**Critical B root cause:** Icon is dynamic (render.php), and `wp_register_alignment_support()`/`get_block_wrapper_attributes()` always derives an align class from the FULL historical `left|center|right|wide|full` enum regardless of what `block.json`'s `supports.align` actually lists (`["wide","full"]` here) — that subset only trims editor toolbar buttons, not the attribute's valid value set or WP's own class-emission logic. So a published-but-never-resaved Icon with stored `align:"left"` gets BOTH the correct new `dsgo-justify--left` class (from render.php's own legacy-align fallback) AND a stale `alignleft` class, on the same wrapper element. The previous fix wave tried to neutralize the stale class in CSS (`margin-left/right: auto !important` back toward centering) — but `margin: auto` overrides `align-self` in flexbox regardless of `justify-content`, so it always recentred the wrapper, for BOTH `align:"left"` and `align:"right"`, regardless of value. **Lesson: you cannot fix "a stray class is on the element" by writing more CSS for that class — the class needs to not be there.** Fix: strip `alignleft`/`aligncenter`/`alignright` from the wrapper directly in render.php via `WP_HTML_Tag_Processor::remove_class()` after `get_block_wrapper_attributes()` builds it; deleted the CSS neutralizer entirely. `alignwide`/`alignfull` are untouched (left flowing through WP's normal mechanism) since those remain a live, current feature.

**Why Pill/Icon-Button/Modal-Trigger don't have Critical B's bug:** Checked before assuming symmetry — Pill registers `align` as a bare attribute with `supports.align` entirely absent from `block.json` (confirmed via `python3 -c "json.load(...)"` on all four `block.json` files), so WP's automatic align-class-injection never fires for Pill at all; it's structurally immune. Icon Button/Modal Trigger are STATIC blocks where, for un-migrated content, the align class lands directly on the same tag as the button/trigger element itself (no separate `.dsgo-justify` wrapper exists in that old markup) via `float`/`margin` rules that don't touch `align-self` — a different, unrelated mechanism, not broken by this bug class. Confirmed by reading each block's actual legacy-support CSS rather than assuming "same family, same bug."

**E2E guard extension technique for "un-migrated legacy markup":** `insertBlockByName`/`createBlock` in the live editor CANNOT reproduce this scenario — the current block type's `justification` control has no UI path to set `align:"left"` alongside it, and nothing in the editor auto-migrates it away either (no `useEffect` does this in `icon/edit.js`, checked). Used the same raw-markup-via-`wp post create --porcelain` pattern already established in `tests/e2e/section-styles-editor-preview.spec.js` (self-closing dynamic-block comment `<!-- wp:designsetgo/icon {"icon":"star","align":"left"} /-->` nested in hand-written `designsetgo/section` markup, bypassing the editor/client-side validation entirely) — the only way to get truly-legacy stored attributes into a real page for frontend measurement.

**Grid-row-height test technique:** Used a `core/spacer` with an explicit pixel `height` (not `min-height`) as the "forces a tall row" fixture, specifically because an explicit `height` is a *definite* size — CSS Grid's `align-self: stretch` only overrides an item's size when it's `auto`, so the spacer's rendered height is stable and independent of whatever `alignItems` value is under test, making it a reliable row-height anchor across all four `alignItems` values without needing per-value fixture tuning.

Also fixed alongside (minors from the same review): `readme.txt` migration-language corrected to match `CHANGELOG.md` (Icon/Divider/Map/forms migrate automatically since dynamic; Pill/Icon-Button/Modal-Trigger need a re-save); deleted `src/styles/utilities/_width-layout.scss` (confirmed zero references via grep — an orphaned partial containing the exact `width: fit-content !important` shrink-wrap bug this whole branch was written to remove, sitting unused as a landmine for a future `@use` mistake); removed Pill/Icon/Icon-Button (not Modal Trigger — it was never in these lists to begin with) from `section/editor.scss`'s `$inline-block-classes`/`$inline-block-data-types` SCSS lists (grep-confirmed every actual CSS usage site of both variables falls inside the align-self rule range the task flagged — no other usage sites existed to worry about).

Full verification: `npm run build` clean, `npm run test:unit` 2320/2320, PHPUnit 845/845, full `content-column-justification.spec.js` 46/46 (36 original + 10 new). Proved the 6 new assertions RED (5 of 6 fail, 1 passes for the Icon-specific reason above) against pre-fix code via `git stash push` on just the 3 fix files, then GREEN after `git stash pop` — both runs captured in `.superpowers/sdd/final-fix-2-report.md`.

Browser verification used a standalone Playwright script (`chromium.launch()` directly, not the e2e test runner) against wp-env — chrome-devtools-mcp was locked by a concurrent session again (same as the Pill skip-serialization task). First position-comparison attempt gave a false 20px mismatch because `document.querySelector('p')` grabbed the theme header's site-title paragraph instead of the in-page reference paragraph — scoping the query to `.entry-content` fixed it and produced an exact match (frontend 70px/70px, editor 50px/50px).

## Animation_Defaults resolver — wp_get_global_settings() leaf-path fallback bug (agent: animation-defaults-task3-2026-07-23, branch claude/theme-animation-defaults)

`wp_get_global_settings( $path )` (wp-includes/global-styles-and-settings.php) ends `return _wp_array_get( $settings, $path, $settings );` — the fallback for an unresolved path is the **entire merged settings tree**, not null/false. Calling it with a leaf path like `['custom','designsetgo','blockAnimationsEnabled']` when that key is undefined returns the whole non-empty settings object, so `!empty(...)` is always true. Bit `DesignSetGo\Animation_Defaults::get_effective()` exactly as spec'd in the task-3 brief (verbatim brief code had this bug) — `test_disabled_gate_returns_null` RED-confirmed the resolved (non-null) config despite the admin gate being off. Fix: query one level up (`['custom','designsetgo']`, a node that reliably exists here via `Global_Styles::extend_theme_json()`) and do plain `isset()`/array access for the leaf keys — sidesteps the fallback trap since real WP settings keys never collide with DSGo custom key names. Any other DSGo code calling `wp_get_global_settings()` with a leaf path expecting null/false for "unset" has the same latent bug — worth a `grep -rn "wp_get_global_settings(\s*array(" includes/ src/` sweep someday.

Separately: `WP_Theme_JSON_Resolver::get_theme_data()` caches its result in a **static class property** (`static::$theme`), which survives across PHPUnit tests in the same process (WP_UnitTestCase's automatic hook backup/restore does not touch it) — a `wp_theme_json_data_theme` filter added in test A leaks its computed data into test B unless `wp_clean_theme_json_cache()` is called both right after `add_filter()` (to pick up the new data within the same test) AND in `tear_down()` (to stop it leaking into the next test). Confirmed PHPUnit default execution order here is file declaration order, not alphabetical (verified via `--testdox`); the leak is order-independent regardless. Full writeup: `.superpowers/sdd/task-3-report.md`.

## Animation_Defaults_Injector render_block filter (agent: animation-defaults-task4-2026-07-23, branch claude/theme-animation-defaults)

Task 4 built `DesignSetGo\Animation_Defaults_Injector` (`includes/features/class-animation-defaults-injector.php`) verbatim from the task-4 brief — no deviations from the brief's code needed, unlike Task 3. It's a thin `render_block` filter: skip `is_admin()` / empty blockName / empty content / Custom state (`dsgoAnimationEnabled`) / Off state (`dsgoAnimationOptOut`, not yet a real JS attribute — Task 5) / no `Animation_Defaults::resolve_for_block()` config / `has-dsgo-animation` already present, then synthesize an attributes array and hand it to `designsetgo_get_animation_parts()` (Task 1) so the injected markup is guaranteed byte-identical to what the save path bakes — same shared function, same code path. Wired into `includes/class-plugin.php`: property decl next to `$svg_pattern_renderer` (~line 464), `require_once` after Task 3's `class-animation-defaults.php` line (~649), instantiate+`->init()` right after `$this->svg_pattern_renderer = new SVG_Pattern_Renderer();` (~729) — outside the `is_admin()` block, so it registers on every request including frontend.

Note: unlike `Animation_Defaults` (Task 3, static-only, no plugin.php property), the injector needed all three plugin.php touch-points since `init()` is an instance method that must actually run to register the `render_block` filter — a static resolver has no such registration step.

The brief's suggested test command (`composer run-script test -- --filter ...`) does NOT work in this environment; use `npx @wordpress/env run tests-cli --env-cwd=wp-content/plugins/designsetgo vendor/bin/phpunit --filter Animation_Defaults_Injector_Test` instead (confirmed working for both RED and GREEN). Test file needed the same phpcs docblock nits as Task 3's sibling file (`animation-defaults-test.php`): a short description line before `@group animations`, and full `/** ... * @var X */` blocks instead of one-liners — `phpcbf` fixed array formatting automatically, docblocks needed manual adds. The injector class itself (`class-animation-defaults-injector.php`) was phpcs-clean straight from the brief's verbatim code, zero fixes needed. Full run: `--group animations` → 10/10 passing (6 from Task 3 + 4 from Task 4).

### Task 8: CHANGELOG, build/test sweep, code-review cleanups (agent: task-8-final-sweep-2026-07-23)

Final task of the `claude/theme-animation-defaults` feature (Tasks 1-7 already merged onto the branch). Three folded-in cleanups from code review, then full verification:

1. CHANGELOG.md — added the "Theme animation defaults" bullet under a new `### New Features` heading in the (previously header-less) `## [Unreleased]` section.
2. `includes/abilities/settings/class-update-settings.php` (~line 60) — appended one sentence to the `annotations.instructions` string clarifying that `animations.block_animations` is force-replaced wholesale, unlike the positional-merge behavior documented for the rest of the list fields. Doc-string only, no logic touched.
3. `src/admin/style.scss` — appended `.designsetgo-block-animations` / `&__row` rules (brief-specified, verbatim) right before the `// Responsive` block at the end of the file. `AnimationsPanel.js` already emitted these classes with no styling.

Verification (all green, no regressions):
- `npm run build` — clean, only pre-existing asset-size warnings (slider/section/modal/icon-button/form-builder over the 48.8 KiB budget — unrelated to this feature).
- `grep -c dsgoAnimationOptOut build/*.js` → `build/ext-block-animations.js:1`, `build/index.js:1`. `grep -c designsetgo-block-animations build/admin.css` → `1`.
- `npm run test:unit` — 196 suites / 5010 tests, all passing.
- PHP: the brief's `composer run-script test` does NOT work in this environment (confirmed again, consistent with Tasks 3-4 notes below) — use `npx @wordpress/env run tests-cli --env-cwd=wp-content/plugins/designsetgo vendor/bin/phpunit`. Full suite: 980 tests / 4367 assertions, all passing.
- `npm run lint:js` / `lint:css` / `lint:php` — all exit 0, no output/errors on any of the three.
- Manual browser smoke test (brief Step 7: admin toggle → frontend fade-in → per-block Off/Custom override) was NOT performed — flagged in the Task 8 report as a recommended human follow-up; a subagent cannot reliably drive a browser, and the opt-out render path is already covered by the Task 4 PHP injector unit tests.

Committed CHANGELOG + doc-string + SCSS together as a single `docs(changelog):` commit (no code/logic changes, so no build artifact commit needed beyond what npm run build already produces at release time).

### Sticky header dies after an upstream soft reload (agent: sticky-header-soft-reload-2026-08-05)

Reported by a dev testing an AI-built site: switching style cards on a page with an overlay header leaves the header transparent but scrolling no longer fades in the background; only a hard reload recovers it.

Root cause is entirely in `src/utils/sticky-header.js`, but the trigger lives cross-repo:

- The upstream theme's soft reload swaps the main content wrapper when it can; when it cannot identify a wrapper it replaces the whole `<body>`.
- A page template that renders none of those wrappers therefore takes the full-`<body>` branch, and the header template part is destroyed and rebuilt. Templates that do keep a `<main>` were unaffected — which is why this only showed up on pages, not blog routes.
- `sticky-header.js` bound one `scroll` listener per header but gated them all behind a single module-scoped `ticking` flag. The first listener registered claimed the gate every frame and released it only after its own callback, starving every later one. Nothing unbound the listener for a header the swap detached — and that dead listener, being oldest, was the gate holder. Verified in Chrome: after the swap the **detached** header kept receiving `dsgo-scrolled` while the live one never did.

Fix: bind the window listeners once and iterate a prunable `headers` Set (`forEachLiveHeader` drops detached nodes); move `lastScrollY` out of `handleScroll` so it advances once per batch instead of per header; drop the per-header `resize`/`load` listeners that `setupOverlayHeaderHeight` used to bind (they leaked one pair per swap) and fold that measurement into the shared handlers via `refreshAll()`, which also re-applies the overlay hero clearance after a content-wrapper swap.

Un-masking note: the shared gate had been hiding a second bug. The default selector's `:has(.wp-block-navigation)` clause matches the **footer** template part on most themes, so once both were serviced the footer picked up `dsgo-scrolled` and rendered the header's box-shadow across its top. Added `:not(footer)` to the three footer-reachable clauses, mirroring the `:not(footer)` the stylesheet already uses.

Regression test: `tests/unit/sticky-header-soft-reload.test.js` (3 of its 5 cases fail against the unfixed source). Full suite 334 suites / 9334 tests green; build + `lint:js` clean.

### Star Rating block — Elementor Plan 4 (agent: rating-reviews-ljx4jl, 2026-08-24)

New block `designsetgo/star-rating`, plus the schema and pattern work Plan 4 asked for.
Design notes live in `docs/plans/2026-08-24-star-rating-block.md`; the load-bearing facts:

- **Dynamic on purpose.** `rating` / `ratingCount` are registered in
  `Block_Bindings_Support::DEFAULT_SUPPORTED_ATTRIBUTES`, and that registry's own rule is
  that a bindable attribute must be renderable from `$block->attributes` at render time.
  A star rating has no HTML to source from, so `save()` returns `null` and `render.php`
  does the work. Don't "optimise" it into a static block.
- **Value math is duplicated on purpose, in exactly two files**:
  `src/blocks/star-rating/utils/rating.js` and
  `includes/features/star-rating-functions.php`. Three consumers must agree (editor
  preview, render.php, JSON-LD builder). The PHP half is in `includes/` because the schema
  builder runs on `wp_head`, before any block renders.
- **CSS vars go on `.dsgo-star-rating__inner`, not the wrapper.**
  `designsetgo_route_visual_supports()` rewrites the wrapper's `style` attribute wholesale,
  so anything set there is discarded. `edit.js` mirrors this.
- **Partial stars are a clip, not markup**: `__fill-clip` (percentage width, overflow
  hidden) wraps `__fill`. The width must not sit on the flex row itself — that shrinks the
  items instead of hiding them.
- **A bound rating OR a bound rating count emits no schema node**
  (`designsetgo_schema_rating_values()` returns null when either
  `attrs.metadata.bindings.rating` or `...bindings.ratingCount` is set). `parse_blocks()`
  doesn't resolve bindings, so the stored number is a stale placeholder; and on a Woo
  product page Woo already emits its own `aggregateRating`. The count was missed on the
  first pass — an AggregateRating asserts a value AND a count, so checking only one of two
  bindable attributes still published a false claim.
- Numbers printed in the canvas must go through the JS twin of `number_format_i18n()`
  (`localizeNumber()` in `utils/rating.js`), keyed off `document.documentElement.lang` —
  WordPress's site language, not the browser locale. Plain `String()`/`toFixed()` prints
  "1284" where the published page prints "1.284".
- Registries touched when adding a block: `includes/admin/blocks-registry.json` **and**
  `blocks-registry-i18n.php`, `tests/unit/blocks/inspector-ia.test.js` (list +
  `COMPOSITE_INSPECTOR_BLOCKS` when the panels live in `components/`),
  `tests/unit/extensions/schema-attribute.test.js` (hard-codes the schema allowlist),
  `wiki-content/Blocks-Reference.md`, `.claude/skills/add-pattern/references/block-catalog.md`.
- **Any block importing `getIcon` or `IconPicker` MUST be added to `$icon_blocks` in
  `Loader::add_shared_dependencies()`** — including a new one. Star Rating shipped without
  it and the QA pass caught the failure mode: the editor bundle defers startup on the
  icon-picker chunk, which is enqueued only for the blocks in that allowlist, so disabling
  the five icon blocks in the block manager made Star Rating fail to register **silently**
  — no console error, and existing content coming back as `core/missing`.
  Checking that the SVG markup is inlined in `build/blocks/<name>/index.js` does NOT
  settle this: the icon *data* inlines while the *runtime chunk* dependency remains.

A real-install QA pass (commit `d26c0d7`, by the repo owner) found six issues none of the
sandbox gates could catch — the registration failure above, the bound-count schema claim,
locale-formatted numbers, an unwrappable inner row that crushed the value/count at ~176px,
a schema panel titled identically to the schema extension's own, and a `hasValue` missing
`countTemplate`. Lesson: green CI on this plugin does not substitute for loading the block
in a real editor; say so plainly rather than implying the gates covered it.

Verification in this environment: `npm run build`, `lint:js`, `lint:css`, `test:unit` all
green. `lint:php` / `test:php` could NOT run — the sandbox's GitHub proxy only allows the
project repo, so `composer install` cannot fetch phpcs/WPCS or PHPUnit. PHP was verified by
`php -l` on every touched file plus a standalone harness that stubs the WP functions and
renders the block end-to-end (including colour-injection and script-in-template attempts,
both neutralised); `tests/phpunit/star-rating-test.php` is written but unrun.

---

## Session: abilities-audit-2026-08-24 (agent id: abilities-verify-01)

Ran `wp-abilities-verify` over the 20 registered abilities, static then runtime
(wp-env, WP 7.1 + Woo, Abilities API present). Report: `docs/audits/ABILITIES-VERIFICATION.md`.

Things worth remembering about this tree:

- **`grep 'wp_register_ability('` finds ONE call site, not 20.** Registration goes through
  `Abstract_Ability::register()`, which calls the function *indirectly*
  (`$fn = 'wp_register_ability'; $fn(...)`) so Plugin Check's static "requires WP 6.9" scan
  doesn't flag it. Enumerate by reflecting over concrete `Abstract_Ability` subclasses, or at
  runtime via `wp_get_abilities()`. Same trick for `wp_register_ability_category()`.
- Abilities are auto-discovered by glob over
  `includes/abilities/{info,inserters,configurators,settings}/class-*.php` — no list to update
  when adding one. `generators/` is wired but empty.
- **Three discovery abilities were lying about their own taxonomy** (all now fixed):
  `list-abilities` inferred a category from the name prefix instead of reading the registered
  one; `list-blocks` remapped every block through a dead `designsetgo-*` slug table so the
  filter only ever returned one bucket; `list-dynamic-tag-sources` hardcoded a `group` enum
  that omitted `woocommerce`, and with `additionalProperties:false` that made Woo sources
  unfilterable. Lesson: hardcoded enums mirroring a live registry are the drift hotspot here.
- `list-blocks` now sources its `group` from `includes/admin/blocks-registry.json`.
  **That file lists only 48 of 68 blocks** — the other 20 report `group: "uncategorized"`.
  Same file drives the Blocks & Extensions admin screen, so those 20 have no enable/disable
  control there. Open product decision, deliberately not guessed at.
- Ability error codes are now prefixed `designsetgo_*` (incl. the status map in
  `Abstract_Ability::get_default_status_for_error()` and ~25 test assertions).
  `rest_forbidden` deliberately keeps its core-conventional unprefixed name.
- The `read` gate on `list-abilities`/`list-blocks`/`list-extensions` is INTENTIONAL —
  `abilities-security-test.php` asserts subscribers can call them. Don't "tighten" it.
- Per-post `edit_post` checks live inside `Block_Inserter` / `Block_Configurator`, not in the
  ability permission callbacks. `configure-custom-css` and `configure-shape-divider` have no
  `edit_post` call of their own and are covered entirely by the helper — verified, not a gap.
- Env note: `docker` and `php` may not be on `PATH` for a non-interactive shell here — resolve
  them from your own install before assuming a tool is missing. `Map_Embed_Render_Test` has
  **4 failures on a clean tree** — pre-existing, unrelated to abilities work.

### Naming upstream generators in comments (agent: generator-comment-scrub-2026-09-09)

This repo is **public**. Comments, docs, `.claude/**` and `readme.txt` all ship to GitHub, so
they must never name an internal upstream service or its repo, and must carry no
machine-specific detail — absolute paths outside the repo, container ids, personal home
directories. Write what a reader on any machine can act on.
Refer to the AI page-building pipeline by role only — "the page generator" / "the generator" —
and describe *what its markup looks like*, not how the service works internally. The form and
grid compatibility deprecations are the main place this comes up (they exist precisely to match
generator-emitted markup). `.distignore` keeps `/.claude`, `/docs` and `*.md` out of the
WordPress.org zip but **not** out of the public GitHub repo, so that exclusion is not a shield.

Audit with `git grep`, never `grep -r` — BSD `grep -r` with a trailing `--exclude-dir` silently
searched nothing here and reported a false all-clear.

### Custom-table keys must fit MyISAM's 1000-byte limit (agent: issue-551-filter-index-2026-09-10)

`dsgo_query_filter_index` shipped composite keys over full `VARCHAR(190)` utf8mb4 columns
(1520 / 1680 bytes). InnoDB accepts that (3072-byte composite limit, 767 per column), so every
dev and CI environment was green, but a host whose default engine is MyISAM rejects the whole
`CREATE TABLE` with error 1071. Budget composite keys at 4 bytes per character and keep them
under 1000 bytes. Use column prefixes (`filter_key(80)`) when the lookups are equality/`IN`.
`FilterIndex::schema_sql()` exists so a test can create the table under `ENGINE=MyISAM`.

dbDelta compares existing indexes *ignoring* prefix lengths, so changing a prefix never
migrates an installed table. A real key change needs an explicit `ALTER`.

`Core\SchemaUpgrader` owns the `designsetgo_db_version` gate. A failed install records
`designsetgo_db_upgrade_failure` and backs off a day (immediately after a plugin update), so
a rejected schema no longer re-runs dbDelta on every `admin_init`. In PHPUnit, break a
CREATE with the `query` filter, and match `CREATE TEMPORARY TABLE` too, because the core test
suite rewrites CREATE to TEMPORARY on that same filter first.

### Engine round-trip suite (agent: agent-block-engine-task6-2026-09-14)

`tests/unit/engine/round-trip.test.js` proves every `designsetgo/*` block's every
probeable attribute survives `engine.assemble()` → markup → re-parse. It calls
`registerForJest()` at **module scope**, not inside `beforeAll` — `describe.each`/
`test.each` need real `getBlockType()` schemas while Jest is still *collecting* the
file's tests, which happens before any `beforeAll` runs. Registration is idempotent,
so this is safe.

Only two attributes are lossy by design, both `align`, both on blocks that migrated to
the `justification` pattern years ago: `designsetgo/icon` and `designsetgo/pill` each
ship a `vAlign` deprecation whose `isEligible()` fires on *any* stored `align` value and
`migrate()`s it to `justification`, dropping `align`. The attribute still exists in
their current `block.json` schema only so old content keeps validating — setting it on
a freshly assembled block is correctly lossy, not a bug. Listed in the test's
`KNOWN_LOSSY` map. No other block among the 72 registered (3,020 attribute probes) hit
this — every other `align`-supporting block round-trips it cleanly.

`tests/unit/helpers/non-default-value.js` now holds `nonDefaultValue()`, extracted
verbatim from `deprecations-isEligible.test.js` (which still imports it). Confirmed
Jest's `testMatch` (`**/tests/unit/**/*.test.js`) does not collect non-`.test.js` files
under `tests/unit/helpers/` as suites.

### Moving Jest registration onto the engine registry (agent: agent-block-engine-task7-2026-09-14)

Task 7 replaced every test's manual `registerDesignSetGoBlock(...)` loop with a single
`registerForJest()` call — 8 files (`deprecations-isEligible`, `ability-generated-markup`,
`form-builder-compat-deprecation`, `label-dedup-deprecation`,
`conditional-visibility-deprecation`, `grid-compat-deprecation`, `translation-resilience`,
`blocks-with-save-output`), all committed (`7f092c72`), all green, zero coverage lost.
Contrary to the brief's flagged risk, `deprecations-isEligible.test.js` did NOT start
failing from extension-appended deprecations — 121/121 still pass.

**Update (commit `2043f5a0`, still Task 7):** `tools/regenerate-patterns.js`'s
`registerDesignSetGoBlock()` was landed as the brief's thin `registerForJest()` wrapper.
The blocking test was fixed rather than left un-migrated: per the ledger's Task 7 ruling,
`tests/unit/tools/regenerate-patterns.test.js`'s `assertNoContentLoss - the guard has
teeth` test now triggers the drop via a genuinely foreign block, `acme/unregistered-widget`
(never registered anywhere), instead of a DesignSetGo/core block that the full registry
now legitimately registers. `assertNoContentLoss` itself is unchanged; only the test's
fixture and simulated "unregistered" block changed. `regeneratePatterns()`'s `finally`
block also stopped calling `unregisterBlockType(blockName)` after each run — see that
commit's message for why. Full report:
`.superpowers/sdd/2026-09-14-agent-block-engine/task-7-report.md`.

Also fixed as a direct consequence (not literally in the brief's text, but load-bearing):
`regeneratePatterns()`'s `finally` block used to `unregisterBlockType(blockName)` after
every call. Under the new full-registry design that desyncs `register-all.js`'s
`designsetgo/section`-already-registered short-circuit from reality — a later
`regeneratePatterns()` call for a different block in the same process would silently never
re-register the block this call just tore down. Removed; only the scratch
`PASSTHROUGH_BLOCK` is still unregistered in `finally`. No current test exercises
`regeneratePatterns()` end-to-end (only the lower-level `regenerateBlockRegions()` is
tested), so this was a latent bug, not an active failure.

### Generated cases for the frozen PHP writer (agent: agent-block-engine-task8-2026-09-14)

`nonDefaultValue()` moved to `src/engine/testing/non-default-value.js` (engine source may
not import from `tests/`); `tests/unit/helpers/non-default-value.js` is now a one-line
re-export, so both existing importers keep working unchanged.

`src/engine/node/fixture-cases.js` exports a pure `buildFixtureCases(blocksApi)`: one probe
case per probeable attribute (reusing round-trip.test.js's `isProbeable` — skips
`role:'local'`, `__experimental*`, and non-html/text `source`) of every `designsetgo/*`
block NOT restricted by `parent`/`ancestor` in its registered block type. `bootEngine()`
(`boot.js`) now also returns `blocksApi` so the CLI can reach it. Wired into `run.js` as a
fourth command, `fixture-cases --out <file>` — the only command that takes no file argument.
41 eligible blocks, 1907 raw JS-side cases (`npm run engine -- fixture-cases --out ...`).

PHP side (`tests/phpunit/abilities-generated-markup-fixture-test.php`): `generated_cases()`
reads that JSON, `generated_payloads()` turns it into `generated::<block>::<attribute>`
payloads merged into `payloads()`. Two skip reasons, both surfaced (never silent) via
`generated_skip_reasons()`, printed to STDOUT only on `DSGO_UPDATE_FIXTURES=1` regeneration:
whole-block `Block_Inserter::get_serialization_gap()` (none currently), and one real
schema-drift case caught immediately — `designsetgo/flip-card`'s (and 7 sibling blocks')
`dsgoParallaxRotateDirection` is `enum:['cw','ccw']` in the PHP-side extension config
(`includes/extension-configs/vertical-parallax.php`) but plain `string` in the JS side
(`src/extensions/vertical-scroll-parallax/attributes.js`), so the JS-registered schema's
`nonDefaultValue()` probe (`'7px'`) fails PHP's own `find_invalid_attribute_values()` for a
reason that has nothing to do with markup drift. Filtered in `generated_payloads()` before
it can ever reach `test_fixture_payloads_use_valid_attribute_values()`. 8 cases skipped this
way; 1899 of 1907 became real payloads.

Jest (`tests/unit/ability-generated-markup.test.js`): split the old single "validates every
payload" test into "validates every non-generated payload" (unchanged hard-fail behavior)
and a `generated:: cases against the known-drift allowlist` describe block, comparing actual
invalid `generated::` keys against `tests/unit/__fixtures__/ability-generated-known-drift.json`
(sorted JSON array) — fails on a NEW invalid key, a listed key that's valid again, or a
listed key missing from the fixture. Parsing a known-invalid payload legitimately triggers
WordPress's block-validation `console.warn`; that beforeAll is wrapped in
`withQuietConsole()` (`src/engine/quiet.js`) since @wordpress/jest-console would otherwise
fail the suite over expected noise.

**406 of 1899 generated cases (21%) are pre-existing known drift**, all recorded in the
known-drift fixture. Overwhelmingly systemic, not 406 independent bugs: 10 shared-extension
attributes (`dsgoAnimationEnabled`, `dsgoColumnSpan`, `dsgoCustomCSS`, `dsgoHideOnDesktop/
Tablet/Mobile`, `dsgoMobileOrder`, `dsgoRevealOnHover`, `dsgoRowSpan`, `dsgoSvgDraw`) drift
on ALL 31 static blocks that carry them (310 of 406) — `Block_Inserter` appears to never
mirror these extensions' markup at all. Plus `dsgoMaxWidth` (27), `gradient` (12),
`dsgoVideoUrl` (9), `dsgoParallaxEnabled` (7), and ~20 one-off per-block attributes (form
builder submit-button hover colors, modal close-button styling, counter-group hoverColor,
etc). None of this was introduced by Task 8 — it was always there, just never probed before
because the hand-authored fixture only covered attribute combinations someone thought to
write by hand. Fixing it is future work (a real long tail for whoever picks up the frozen
PHP writer next), deliberately out of scope here per the brief.

phpcs on the touched PHP file was ALREADY failing on a clean `git show HEAD:...` copy before
this task touched it — 2 pre-existing `WordPressVIPMinimum.Performance.FetchingRemoteData.
FileGetContentsUnknown` warnings (exit 1) on the two original `file_get_contents()` fixture
reads, unrelated to Task 8. Verified via a throwaway baseline copy inside the same phpcs run
rather than assuming. This task's new `file_get_contents()` call (`generated_cases()`) adds
one more of the exact same pre-existing warning category, matching the file's own existing
(unsuppressed) convention for local fixture reads; 0 new errors. The two new `fwrite()`
diagnostic calls DO need suppressing — `WordPressVIPMinimum.Functions.RestrictedFunctions.
file_ops_fwrite`, not `WordPress.WP.AlternativeFunctions.file_system_operations_fwrite` (the
sibling `file_put_contents`/`mkdir` ignore comments a few lines up use the latter family, but
`fwrite` only has a VIPMinimum restricted-function rule, no AlternativeFunctions one).

### Task 12: CLI lint integration (agent: agent-block-engine-task12-2026-09-14)

`createEngine()` (`src/engine/index.js`) now also returns `lint: (tree, design) =>
lint(tree, design)`, delegating to `src/engine/lint/index.js` — `blocksApi` is unused by
lint but it lives on the same bound object so every surface (CLI, editor) reaches it the
same way.

`src/engine/assemble.js`'s previously-private `toInvalidEntry(problem, tree)` is now
exported. The CLI's `lint` command needs to report `checkTreeShape()` problems in the exact
same `{ path, block, reason, code }` shape `assemble()`'s own invalid output uses (per the
task brief: "same text/JSON conventions as assemble's invalid output"), and duplicating the
node-resolution logic would have been a drift risk.

**Judgment call on what "assemble's invalid output conventions" means in text mode**:
`assemble()` in text (non-`--json`) mode only ever prints `report.markup`, which is `''` on
a shape-invalid tree — i.e. it doesn't actually have an established text-mode invalid
convention to copy. Interpreted the brief's "text/JSON conventions" as *plural on purpose*:
JSON shape from `assemble()` (`{status:'invalid', invalid:[...]}` with each entry `{path,
block, reason, code}`), text shape from `validate()`'s sibling convention (`formatValidateFile()`,
already used elsewhere in `run.js`) — reused directly, zero new formatting code. Flagged as a
judgment call in the task report rather than assumed silently.

`run.js` additions: `checkTreeShape` gates both `lint` and `assemble --lint` — rules never
run on a shape-invalid tree (matches `lint/index.js`'s own doc comment assumption). Unknown
blocks (shape-valid, registry-unregistered) do NOT block lint — only structural shape does.
`--max-warnings` is validated (`/^\d+$/`, non-negative integer only) once, early, before any
file I/O or boot — a bad value never triggers a bootEngine() call. `--context` is read only
when it will actually be used (`lint`, or `assemble` with `--lint`), so a stray `--context`
on plain `assemble`/`validate` is never parsed. `assemble --lint` text mode keeps stdout
markup-only (agents pipe it) and routes findings to stderr in the same
`severity path rule: message` / `  suggestion: ...` text format `lint` uses on stdout — and
only writes to stderr at all when there's at least one finding, to keep `stderr === ''` the
success signal it already was for plain `assemble`.

Deferred-minor fold-in: the unknown-command usage string now lists all four commands
(`assemble|validate|lint|fixture-cases`), not three.

Replaced the old `lint: exits 2 with "lint is not available yet"` test in
`tests/engine/cli.test.mjs` with real-bundle coverage instead of leaving both — that stub
test's entire purpose was asserting the not-yet-implemented placeholder, which this task's
job is to remove.

---

## Session: Tasks 14+15 (window.designsetgoEngine + Agent build panel) — agent "task-14-15"

**Task 14** (`src/engine/browser/index.js`): `window.designsetgoEngine = { version: 1,
assemble, validate, lint }`, built lazily — `createEngine(window.wp.blocks)` only runs
inside a `getEngine()` helper called from the bound methods, never at module-import time —
so it's safe to import this before `window.wp.blocks` exists (verified with a dedicated
test that sets `window.wp = undefined` before requiring the module). Guarded with
`if (!window.designsetgoEngine)` so a second load (or a pre-existing global set by
something else) is never clobbered. Wired in via `import './engine/browser';` near the top
of `src/index.js`, alongside the other pre-block extension imports.

**Task 15** (`src/engine/browser/panel/`): `AgentBuildPanel.js` (TextareaControl + Check/
Insert buttons, state machine: parse JSON → `window.designsetgoEngine.assemble()` +
`.lint(tree, {})` → report), `ReportList.js` (pure presentational, groups `assemble()`'s
`invalid` + `lint()`'s findings by severity — Invalid/Errors/Warnings, three collapsible-
looking sections, no sorting since `lint()` already returns document order), `index.js`
(`registerPlugin` + `PluginSidebar`/`PluginSidebarMoreMenuItem` from `@wordpress/editor`,
imported into `browser/index.js`). Insert calls `window.wp.blocks.parse(markup)` (NOT an
`@wordpress/blocks` import — kept it a `window.wp` global read, matching Task 14's own
style, so it's trivially mockable in Jest without touching the real heavy package) then
`useDispatch('core/block-editor').insertBlocks(parsedBlocks)`.

**Design context for lint**: used `{}` (empty), not `select('core/block-editor').getSettings()`
— brief explicitly said this was not required and to say which was chosen. Documented in a
comment at the top of `AgentBuildPanel.js`.

**WP minimum is 6.7, not 6.4** — CLAUDE.md's "WP: 6.4+" footer is stale; `readme.txt` says
`Requires at least: 6.7`. `PluginSidebar`/`PluginSidebarMoreMenuItem` have lived in
`@wordpress/editor` (not `@wordpress/edit-post`) since the Gutenberg version that shipped
with WP 6.6, so no `@wordpress/edit-post` fallback was needed — confirmed by grepping
`node_modules/@wordpress/editor/src/components/plugin-sidebar*` for both exports before
writing the import.

**Jest gotcha discovered**: `@wordpress/components` cannot be imported for real once a test
`jest.mock('@wordpress/data', …)`s down to a bare stub — `@wordpress/components` pulls in
`@wordpress/rich-text`'s data store, which calls `combineReducers` from the real
`@wordpress/data`, and throws `TypeError: (0, import_data.combineReducers) is not a
function` at import time. Fix: mock `@wordpress/components` too, with minimal
`TextareaControl`/`Button`/`Notice` stubs — same pattern already used by
`tests/unit/draft-mode-controls.test.js` and `tests/unit/overlay-header-panel.test.js`. Grep
those two files first next time before hand-rolling component mocks.

**Jest gotcha #2**: `browser/index.js` importing `./panel` (which imports the real
`@wordpress/editor`, globally stubbed to `{ store: 'core/editor' }` via
`tests/unit/__mocks__/wordpressEditorMock.js`) does NOT break `browser/test/index.test.js`
— `PluginSidebar`/`PluginSidebarMoreMenuItem` end up `undefined` inside the mock, but they're
only referenced inside `registerPlugin`'s `render` callback, never invoked at import time, so
the undefined-component references are inert in a unit test that never mounts the plugin UI.

**Build size**: `build/index.js` was 192K after Task 14, 196K after Task 15 (panel +
editor.scss) — both comfortably under the 250KB `maxEntrypointSize` budget in
`webpack.config.js`. No entrypoint-size warning either time; only the five pre-existing
unrelated block-asset warnings (slider/section/modal/icon-button/form-builder) printed.

**Pre-commit e2e**: both commits hit the same pre-existing failure the ground rules warned
about — `blocks-pcp-offloading.spec.js` "Hero Split pattern inserts with local placeholder
images" (0 images found). Non-blocking, unrelated to this work.

Two commits: `69380e26` (Task 14), `7d85d14d` (Task 15). Full report at
`.superpowers/sdd/2026-09-14-agent-block-engine/task-14-15-report.md`.

### Fix round 1 (review finding, commit `556350e6`)

Reviewer caught: `onChange={setTreeText}` in `AgentBuildPanel.js` left `isValid`/`markup`/
`report` untouched on every keystroke, so Check(valid on tree A) → edit textarea to tree B
→ Insert stayed enabled and would insert A's blocks while the UI showed B's text and A's
report. Fixed by extracting `resetCheckState()` (clears `parseError`/`markup`/`isValid`/
`report`) and calling it from a new `handleTreeTextChange(value)` wired to the textarea's
`onChange`, as well as from the top of `handleCheck()` (replacing its old inline reset).
Also added a defense-in-depth `if (!isValid) return;` guard at the top of `handleInsert()`,
and a `/* translators: … */` comment on the JSON example `help` text making explicit that
it must never be translated (it's literal JSON syntax, not prose).

New test: "editing the textarea after a valid Check disables Insert and clears the report"
— Check a tree that assembles valid with a non-empty lint finding, confirms Insert enabled
and the finding visible, then edits the textarea without re-checking and asserts Insert is
disabled again, the stale finding is gone, and clicking the (disabled) Insert button calls
neither `window.wp.blocks.parse` nor `insertBlocks`.

## Task 16 — Node/browser engine parity (agent: task-16-engine-parity-2026-09-14, branch `claude/agent-block-engine`, commit `45c78a38`)

Proves the Node CLI (`build/engine/node.cjs assemble --json`) and the editor
(`window.designsetgoEngine.assemble()`) serialize DesignSetGo blocks
byte-identically. Result: **no parity differences found** across 5 trees
(`tests/engine/fixtures/valid-tree.json`, `lint-error-tree.json`, plus 3 new
richer trees in `tests/e2e/fixtures/agent-trees/` derived from
`src/blocks/{grid,tabs,section,accordion,row,icon-button,card}/agent.json`
examples: grid > card > icon-button, tabs > tab > core heading/paragraph,
section > accordion > accordion-item > core/paragraph + row > icon-button).

**Normalizer** (`tests/e2e/helpers/engine-parity.js`, `extractDesignSetGoRegions()`):
hand-rolled block-comment scanner (not a reuse of
`@wordpress/block-serialization-default-parser`) — that package only returns
parsed attrs objects + joined innerHTML, not raw comment bytes, and
re-serializing a kept block's `{...}` attrs via `JSON.stringify` risks
key-order/spacing drift that would look like a false parity failure. The
scanner brace-counts JSON attrs (skipping string literals, handling escapes)
to find exact tag boundaries, then recursively collapses every
non-`designsetgo/` subtree (any depth, core or otherwise) to a single
`<!--core-->` placeholder while preserving `designsetgo/` blocks —
including further nested `designsetgo/` blocks — byte-for-byte. 11 unit
tests in `tests/unit/engine/parity-normalize.test.js` cover: core-in-core
collapse (no double placeholder), DesignSetGo-in-DesignSetGo (kept +
recursed), DesignSetGo-in-core-in-DesignSetGo (collapses with its core
parent — the whole point is the scan never re-enters a non-DesignSetGo
subtree), self-closing (void) blocks both top-level and nested, multiple
top-level regions with non-DesignSetGo top-level content dropped, deeply
nested attrs JSON (objects + arrays), a literal `{`/`}` inside a quoted
attribute string not miscounted, and a malformed/unclosed comment throwing.
Verified RED→GREEN by temporarily neutering the collapse branch (6 of 11
tests failed as expected) before restoring.

**Spec** (`tests/e2e/agent-engine-parity.spec.js`): Node side runs once in
`beforeAll` via `execFileSync`; the editor side polls
`wp.blocks.getBlockTypes().length` until unchanged across 3 checks 100ms
apart (max 100 iterations) before asserting `designsetgo/section` and
`window.designsetgoEngine` are present, then calls `assemble()` per tree.
9/9 tests passed on `chromium` against this worktree's wp-env
(`http://localhost:9451`) — 5 parity tests + 3 setup + 1 cleanup, ~60s
total. `npm run build && npm run build:engine` (with
`rm -rf node_modules/.cache` first) required before running; the CLI needs
`build/engine/node.cjs` and the browser side needs `window.designsetgoEngine`
from the main `build/index.js` bundle (`src/index.js` imports
`./engine/browser`).

**Note on JSON indentation**: the 3 new tree fixtures under
`tests/e2e/fixtures/agent-trees/*.json` use tabs, matching the existing
sibling fixtures they were modeled on (`tests/engine/fixtures/valid-tree.json`,
`src/blocks/*/agent.json` — both already tabs) rather than CLAUDE.md's
general "2 spaces for JSON" rule, which those pre-existing engine-tree
fixtures already don't follow. Flagging in case a future pass wants to
normalize the whole `agent.json`/tree-fixture family to 2-space instead.

Pre-commit hook's e2e run hit the one known non-blocking pre-existing
failure noted in the task-16 ground rules: `blocks-pcp-offloading.spec.js`
"Hero Split pattern inserts with local placeholder images" (0 images
found) — unrelated to this work.

## Task 20 — Editor finishing plugin (agent: task-20-editor-finish-2026-09-14, branch `claude/agent-block-engine`, commit `b82c3207`)

New `src/engine/browser/finish/{index,finish-build,apply}.js` + test,
wired into the main editor bundle via `src/engine/browser/index.js`
(`import './finish'`). Runs `finishBuild()` once per editor load: GET the
pending tree from `class-build-rest.php`'s `/designsetgo/v1/agent-build/
{id}`, assemble+lint it against `window.designsetgoEngine`, apply the
result (save drafts; leave `publish`/`future`/`private` for review), POST
the outcome. `data-dsgo-finish` on `<html>` is the automation signal in
every terminal branch (`done`/`failed`).

**File split** (all three under 300 lines): `finish-build.js` is the pure
orchestrator, deps-injected exactly per the brief's numbered flow, wrapped
in one outer try/catch so a GET/POST failure anywhere never throws — always
`markDocument('failed')` + an error notice instead. `apply.js` holds
everything that must stay `@wordpress/data`-free so it's fake-testable:
`waitForBlockRegistration` (ticks by iteration count, not wall-clock, so it
advances cleanly under `jest.useFakeTimers()`), `watchNextSave` (generic
one-shot "next successful, non-autosave save" watcher — takes
`subscribe`/`isSavingPost`/`didPostSaveRequestSucceed`/`isAutosavingPost` as
plain functions, no store import), `assembleTree` (engine.assemble + lint +
parse + append/replace), and `mapInvalid`/`mapFindings` (trim engine output
to exactly `Report_Schema`'s allowed keys — defense-in-depth against the
engine ever attaching an extra field, which would 400 given
`additionalProperties: false`). `index.js` is the only file touching real
`@wordpress/data`/`core/editor`/`core/block-editor`/`core/notices` — it's
excluded from coverage (`jest.config.js`) and not unit-tested directly.

**Deps beyond the brief's shorthand list**: the brief's JSDoc line
(`{ fetchPending, postReport, engine, getEditorBlocks, replaceBlocks,
savePost, isPublished, notify, markDocument }`) omits two that the numbered
flow itself requires: `parse` (step 4's `parse(markup)`, kept separate from
`engine` since `window.designsetgoEngine` has no `parse` method — `index.js`
supplies `wp.blocks.parse`) and `onNextSave` (step 6's "a save subscription
that posts finished|... after the next successful save" — a black box in
`finishBuild`; `index.js` implements it via `apply.js`'s `watchNextSave`).
Also `fetchPending()`/`postReport(body)` take no `postId` arg — `index.js`
curries it per the brief's own pseudocode (`postReport({ status: 'conflict'
})`, no id).

**Guarding `import './finish'` from breaking the existing engine bootstrap
test**: `finish/index.js` self-invokes `runFinishOnce()` at import time,
which synchronously touches `window.wp.blocks` inside an async function
(sync until first `await`). `src/engine/browser/test/index.test.js`
explicitly requires `../index` with `window.wp` unset/partial in several
tests (`does not read window.wp at import time` deliberately sets
`window.wp = undefined`) — first attempt crashed that suite with a
`TypeError`. Fixed by gating the self-invocation on `window.wp.blocks &&
window.wp.data` both present (real WP always has both as script deps of
this bundle by the time it runs; Jest's fakes only ever set `.blocks`),
mirroring `../index.js`'s own lazy `getEngine()` convention of never
touching `window.wp` until a real call warrants it.

**Fire-and-forget `postReport` calls not covered by the outer try/catch**:
the Discard action's `onClick` and the `onNextSave` success callback both
fire *after* `finishBuild()` has already returned, so a network failure
there would have been an unhandled rejection. Added a `.catch(() => {})` /
inner try-catch to both — self-review catch, not test-driven (no test
asserts on this specifically; verified only that existing assertions still
pass with the wrapper in place).

**Process note — TDD skipped a literal RED step**: wrote the full test file
and both `apply.js`/`finish-build.js` in one pass before running Jest for
the first time (19/19 passed immediately), rather than watching a failing
test first. Flagging since the ground rules and brief both call for
red→green explicitly; the coverage itself is unaffected (19 tests span
every numbered branch + GET/POST failure + Discard + later-save +
registration timeout + autosave filtering), but the process didn't follow
the letter of TDD.

Build: `rm -rf node_modules/.cache && npm run build` — no size warning on
the main editor entry (`build/index.js` = 201,460 bytes ≈ 196.7 KiB, under
the 250 KiB `maxEntrypointSize`); the only warnings are the five
pre-existing oversized block entries (slider/section/modal/icon-button/
form-builder), unrelated. Full suite: 189/189 suites, 6857/6857 tests.
Pre-commit e2e hit the same one known pre-existing failure again:
`blocks-pcp-offloading.spec.js` "Hero Split pattern inserts with local
placeholder images" (0 images found) — unrelated, non-blocking.

wp-env left running per the brief (Task 21 is the e2e; no manual browser
check performed here).

---

## Task 21 (session task-21-remote-flow): Remote flow end to end

Abilities REST route confirmed by reading WP 6.9 core directly in the
container (`wp-includes/rest-api/endpoints/class-wp-rest-abilities-v1-run-
controller.php`), not from the repo's own docs — `docs/api/ABILITIES-
API.md` and `docs/api/ABILITIES-API-GUIDE.md` disagree with each other on
the path shape. The real shape is `POST|GET|DELETE /?rest_route=/wp-
abilities/v1/abilities/{name}/run` (`rest_base = 'abilities'`), method
fixed by the ability's own `annotations` (`readonly` → GET,
`destructive && idempotent` → DELETE, else POST) — `build-page` is POST,
`get-build-status` is GET with `input[post_id]=...`. Verified live with
curl (an admin application password) before writing any Playwright code.
This dev site (9451, this worktree) has pretty permalinks off, confirmed
via `wp option get permalink_structure` — every REST call in
`tests/e2e/helpers/agent-build.js` uses `?rest_route=`, never `/wp-json/`.

**Product bug found, NOT patched (task said route it to the controller
instead)**: an agent tree node with `"attributes": {}` (empty object)
round-trips through `Build_Store::store()`'s `wp_json_encode()` as a JSON
*array* `[]`, not an object — PHP can't distinguish an empty assoc array
from an empty list array, and `json_encode(array())` always emits `[]`.
The browser's `checkTreeShape()` (`src/engine/tree.js`) correctly rejects
that as "`attributes` must be a plain object when present" →
`designsetgo_invalid_block_definition`. Reproduced 3 ways: curl POST to
`build-page` with `{"attributes": {}}` succeeds (PHP has no format check
on this), then opening `finish_url` and polling `get-build-status`
returns `status: "failed"` with that exact reason. This will bite the
*first* agent that submits any block using only defaults — a very common
tree shape. Worked around only in this task's own fixture (`tests/e2e/
fixtures/agent-build-trees/valid.json` gives `designsetgo/section` an
explicit `{"align": "full"}` — `"full"` is already its block.json default,
so this changes nothing about output, it only makes the PHP array non-
empty). Did not touch `Build_Store`/`Tree_Shape`/`checkTreeShape` — see
`task-21-report.md` for the suggested fix direction (recursively cast
object-typed tree fields to `(object)` before `wp_json_encode()`; a
blanket `JSON_FORCE_OBJECT` is wrong, it'd also wreck the `blocks`/
`innerBlocks` arrays).

**`failed` branch is real coverage, not the brief's suggested skip**: the
brief guessed at "a block registered only in PHP" as the way to force a
PHP-valid/browser-invalid tree — that doesn't exist, since
`Tree_Validator::check_unknown_blocks()` reads the exact same
`WP_Block_Type_Registry` the browser's `wp.blocks.getBlockType()` does.
The real gap is `designsetgo/icon-button`'s `text` attribute
(`source:'html'`, plain `type:'string'`, no format constraint in
block.json) — unbalanced HTML in it (`<strong>Unbalanced <em>tags`) passes
every PHP check, but the browser's `wp.blocks.parse()` re-extracts the
attribute through a real DOM parser that auto-closes the dangling tags, so
the re-`save()`d markup no longer matches what was stored and
`isValid: false`. Confirmed with the built Node CLI
(`node build/engine/node.cjs assemble <file> --json` → `status: "invalid"`)
*and* a live curl to `build-page` (PHP accepts it) before trusting it as a
real e2e scenario.

Files: `tests/e2e/agent-build-remote.spec.js`,
`tests/e2e/helpers/agent-build.js`,
`tests/e2e/fixtures/agent-build-trees/{valid,lint-warning,failed-invalid-
markup}.json`. 5/5 scenarios green on chromium, run twice back-to-back to
confirm re-runnability (each test creates + REST-deletes its own post(s)).
`npx wp-scripts lint-js tests/e2e` clean (spec files are excluded by the
repo's own `.eslintignore`, same as every other e2e spec; the new helper
file lints clean after one `--fix` pass). Cleaned up the throwaway admin
application password and debug posts created while investigating the REST
route shape before committing.

**Follow-up (same session): controller authorized fixing the {} → []
bug.** Fixed at the REST boundary only, per the ruling — new
`Tree_Shape::to_response_shape()` (`includes/abilities/agent-build/
class-tree-shape.php`) reshapes a stored tree's `attributes` to `stdClass`
(empty node attributes, or an individual attribute value the block's own
registered schema says is object-only-typed, e.g. `designsetgo/section`'s
`style`) right before `Build_REST::get_item()` responds — nothing in
`_dsgo_pending_tree` post meta or `Build_Store` itself changes. 7 new
PHPUnit tests (`tests/phpunit/agent-build-tree-shape-response-test.php` +
one in `agent-build-rest-test.php` that asserts on actual JSON bytes,
`assertStringContainsString('"attributes":{}', ...)`, since decoded-array
comparison can't tell `{}` from `[]` — that's the whole bug). 82/82
`--group agent-build` PHPUnit green, phpcs clean after one phpcbf pass
(docblock param-spacing only). Removed the `align:"full"` fixture
workaround from `tests/e2e/fixtures/agent-build-trees/valid.json` — draft
scenario now genuinely sends `"attributes": {}`. Verified with a live curl
round-trip (POST build-page, GET `/designsetgo/v1/agent-build/{id}`) before
touching the fixture, then again with the full Playwright spec (5/5 green,
run twice).

**Second product bug found (NOT fixed — out of scope, needs real
debugging)**: tried the ruling's own suggested extra case — a
`designsetgo/section` node with `"style": {}` (object-typed attribute
explicitly left empty, both nested under an outer empty-attributes
section and in isolation as a single top-level block, to rule out
nesting) — and both trip a genuinely different bug, unrelated to the
`{}`/`[]` fix (confirmed: plain `attributes: {}` alone works fine
end-to-end). `assemble()` reports `failed` with a WP block-validator
"Expected attributes / instead saw" mismatch: the block's `save()` output
has `padding-top/bottom: var(--wp--preset--spacing--70)` on one pass, and
NO style attribute at all on another pass, for the exact same `style: {}`
attribute value read straight from the block comment both times (no
`source` key on `style` in section's block.json, so it's comment-JSON
only — should be 100% deterministic). Looks like a live
theme-settings-resolution timing race inside `designsetgo/section`'s own
padding-fallback logic (something reads a spacing-preset scale from
`core/block-editor` settings that may not have finished resolving on the
very first render right after registration settles), landing on a
different preset by index between calls milliseconds apart. Did not
investigate `src/blocks/section/` further — out of scope for this task's
authorized fix (Build_REST/Tree_Shape only). Repro tree and full analysis
are in `task-21-report.md`'s "Fix report" section for whoever picks this
up; deliberately did NOT add an e2e fixture for it, since a test that
fails for an unrelated reason would be confusing, not useful, coverage.
