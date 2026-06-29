# `includes/` Restructure — Concern-Based Layout (Moves Only)

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Reorganize the 20 loose files in `includes/` root and the flat-but-polluted `includes/blocks/` folder into concern-based subdirectories so a newcomer can navigate the plugin by folder name.

**Architecture:** Pure file relocation via `git mv` + updating the three places that reference moved paths (`class-plugin.php` loader list, `designsetgo.php` early requires, one PHPUnit test). **No code changes inside any moved file. No namespace changes. No PSR-4.** The hand-maintained `require_once` list in `class-plugin.php` stays a manual list, regrouped with comment headers matching the new folders (safe, predictable load order).

**Design decision (chosen):** Keep a **`blocks/` parent that mirrors `src/blocks/`** — it holds only genuinely per-block server logic, each block family in its own subfolder (`blocks/query/`, `blocks/forms/`, `blocks/modal/`), plus the block registrar `class-loader.php` at its root. **Cross-cutting infrastructure that works on *any* block is evicted to top-level folders**: `bindings/` (Block Bindings sources), `dynamic-tags/` (dynamic-data subsystem), `features/` (render-time extensions). So `includes/blocks/query/` is the server-side mirror of `src/blocks/query/`.

**Tech Stack:** PHP, WordPress, `git mv`, Composer (PHPCS/PHPStan/PHPUnit).

**Scope guard:** Moves only. Explicitly OUT of scope (separate future passes): slimming the 1,244-line `class-plugin.php`, PSR-4 autoloading, renaming `extension-configs/` → `config/`, aligning the `DesignSetGo\Blocks\Query` namespace on the evicted `bindings/` files to their new folder.

**Sequencing:** Execute only after in-flight branches (PCP work, release 2.2) have landed — a 25-file move conflicts badly with open PRs. Do this on a fresh `claude/` branch off `main`.

---

## Target layout

```
includes/
  class-plugin.php          (unchanged location — bootstrap)
  helpers.php               (unchanged location — global helpers)
  core/
    class-assets.php
  blocks/                   ← server logic for SPECIFIC blocks (mirrors src/blocks/)
    class-loader.php        (registers all blocks — stays at blocks/ root, unchanged)
    query/
      class-query.php
      class-query-template-controller.php
      class-query-filter-index.php
      class-query-filter-index-hooks.php
      class-query-filter-index-rebuilder.php
      class-query-filter-index-cli.php
      class-query-filter-registry.php
    forms/
      class-form-handler.php
      class-form-security.php
      class-form-submissions.php
    modal/
      class-modal-hooks.php
  bindings/                 ← cross-cutting (Block Bindings work on ANY block)
    class-block-bindings-support.php
    class-query-bindings-helpers.php
    class-query-bindings.php
    class-query-bindings-metabox.php
    class-query-bindings-pods.php
    class-query-bindings-jetengine.php
  dynamic-tags/             ← cross-cutting (promoted out of blocks/dynamic-tags/)
    class-dynamic-tags-registry.php
    class-dynamic-tags-sources-post.php
    class-dynamic-tags-sources-site.php
    class-dynamic-tags-sources-archive.php
    class-dynamic-tags-sources-user.php
    class-dynamic-tags-field-discovery.php
    class-dynamic-tags-image-resolver.php
    class-dynamic-tags-rest.php
    class-dynamic-tags-bootstrap.php
  features/                 ← cross-cutting render-time extension runtime
    class-sticky-header.php
    class-overlay-header.php
    class-section-styles.php
    class-button-global-styles.php
    class-icon-injector.php
    class-custom-css-renderer.php
    class-style-binding.php
    class-svg-pattern-renderer.php
    class-block-visibility.php
    class-extension-attributes.php
    breadcrumbs-functions.php
  data/                     (already exists, currently empty)
    icon-svg-library.php
    svg-pattern-data.php
    block-animation-attributes.php
  integrations/
    query-monitor/
      class-query-qm-collector.php
      class-query-qm-output.php
  abilities/  admin/  llms-txt/  markdown/  patterns/  extension-configs/   (UNCHANGED)
```

Net result: `includes/` root holds only `class-plugin.php` + `helpers.php`; `includes/blocks/` is purified to per-block server code; cross-cutting infra is top-level.

---

## Pre-flight (do once, before Task 1)

**Step 0.1 — Branch off clean main**

```bash
git checkout main && git pull
git checkout -b claude/includes-restructure
```

**Step 0.2 — Confirm baseline is green** so any later breakage is attributable to the moves:

```bash
composer lint:php   # or: npm run lint:php
composer phpstan    # or per phpstan.neon
vendor/bin/phpunit  # or: npm run test:php
```
Expected: all pass (record any pre-existing failures to ignore later).

**Step 0.3 — Verify `uninstall.php` does not `require` a moved file** (the basename `class-form-handler` appears there — confirm it is only a comment, not a functional require):

```bash
grep -n "class-form-handler" uninstall.php
```
Expected: a comment line only. If it is a `require_once`, add it to Task 5's edit list.

---

## Task 1: `core/` — assets

**Files:**
- Move: `includes/class-assets.php` → `includes/core/class-assets.php`
- Modify: `includes/class-plugin.php` (assets path)

> `includes/blocks/class-loader.php` does NOT move — it stays at `blocks/` root as the block registrar.

**Step 1.1 — Create dir + move**

```bash
mkdir -p includes/core
git mv includes/class-assets.php includes/core/class-assets.php
```

**Step 1.2 — Update `class-plugin.php`**

In `load_dependencies()`:
- `'includes/class-assets.php'` → `'includes/core/class-assets.php'`

Add a `// --- Core ---` comment header above it.

**Step 1.3 — Lint**

```bash
php -l includes/core/class-assets.php && php -l includes/class-plugin.php
```
Expected: `No syntax errors detected`.

**Step 1.4 — Commit**

```bash
git add -A
git commit -m "refactor: move asset loader into includes/core/"
```

---

## Task 2: `data/` — static data files

**Files:**
- Move: `includes/icon-svg-library.php` → `includes/data/icon-svg-library.php`
- Move: `includes/svg-pattern-data.php` → `includes/data/svg-pattern-data.php`
- Move: `includes/block-animation-attributes.php` → `includes/data/block-animation-attributes.php`
- Modify: `includes/class-plugin.php` (path for `svg-pattern-data.php`)
- Modify: `designsetgo.php` (early requires for `icon-svg-library.php`, `block-animation-attributes.php`)

**Step 2.1 — Move** (`includes/data/` already exists)

```bash
git mv includes/icon-svg-library.php includes/data/icon-svg-library.php
git mv includes/svg-pattern-data.php includes/data/svg-pattern-data.php
git mv includes/block-animation-attributes.php includes/data/block-animation-attributes.php
```

**Step 2.2 — Update `designsetgo.php`** (lines ~39, ~44):
- `'includes/block-animation-attributes.php'` → `'includes/data/block-animation-attributes.php'`
- `'includes/icon-svg-library.php'` → `'includes/data/icon-svg-library.php'`

**Step 2.3 — Update `class-plugin.php`**:
- `'includes/svg-pattern-data.php'` → `'includes/data/svg-pattern-data.php'`
Add `// --- Static data ---` header.

**Step 2.4 — Lint**

```bash
php -l designsetgo.php && php -l includes/class-plugin.php
```

**Step 2.5 — Commit**

```bash
git add -A
git commit -m "refactor: move static data files into includes/data/"
```

---

## Task 3: `features/` — render-time feature runtime

**Files (move from `includes/` root):**
- `class-sticky-header.php`, `class-overlay-header.php`, `class-section-styles.php`,
  `class-button-global-styles.php`, `class-icon-injector.php`, `class-custom-css-renderer.php`,
  `class-style-binding.php`, `class-svg-pattern-renderer.php`, `class-block-visibility.php`,
  `class-extension-attributes.php`, `breadcrumbs-functions.php` → `includes/features/`
- Modify: `includes/class-plugin.php`, `designsetgo.php` (breadcrumbs early require)

> `class-modal-hooks.php` is NOT here — it is block-specific and goes to `blocks/modal/` in Task 5.

**Step 3.1 — Move**

```bash
mkdir -p includes/features
git mv includes/class-sticky-header.php          includes/features/class-sticky-header.php
git mv includes/class-overlay-header.php          includes/features/class-overlay-header.php
git mv includes/class-section-styles.php          includes/features/class-section-styles.php
git mv includes/class-button-global-styles.php    includes/features/class-button-global-styles.php
git mv includes/class-icon-injector.php           includes/features/class-icon-injector.php
git mv includes/class-custom-css-renderer.php     includes/features/class-custom-css-renderer.php
git mv includes/class-style-binding.php           includes/features/class-style-binding.php
git mv includes/class-svg-pattern-renderer.php    includes/features/class-svg-pattern-renderer.php
git mv includes/class-block-visibility.php        includes/features/class-block-visibility.php
git mv includes/class-extension-attributes.php    includes/features/class-extension-attributes.php
git mv includes/breadcrumbs-functions.php         includes/features/breadcrumbs-functions.php
```

**Step 3.2 — Update `designsetgo.php`** (line ~49):
- `'includes/breadcrumbs-functions.php'` → `'includes/features/breadcrumbs-functions.php'`

**Step 3.3 — Update `class-plugin.php`** loader paths for all 11 files (prefix `includes/features/`). Add `// --- Render-time features ---` header.

**Step 3.4 — Lint**

```bash
for f in includes/features/*.php; do php -l "$f"; done
php -l designsetgo.php && php -l includes/class-plugin.php
```

**Step 3.5 — Commit**

```bash
git add -A
git commit -m "refactor: group render-time feature classes into includes/features/"
```

---

## Task 4: `blocks/query/` — query engine (nest, don't evict)

**Files (move from `includes/blocks/` down into `includes/blocks/query/`):**
- `class-query.php`, `class-query-template-controller.php`, `class-query-filter-index.php`,
  `class-query-filter-index-hooks.php`, `class-query-filter-index-rebuilder.php`,
  `class-query-filter-index-cli.php`, `class-query-filter-registry.php`
- Modify: `includes/class-plugin.php`, `tests/phpunit/blocks/query/filter-index-cli-test.php:85`

**Step 4.1 — Move**

```bash
mkdir -p includes/blocks/query
git mv includes/blocks/class-query.php                        includes/blocks/query/class-query.php
git mv includes/blocks/class-query-template-controller.php    includes/blocks/query/class-query-template-controller.php
git mv includes/blocks/class-query-filter-index.php           includes/blocks/query/class-query-filter-index.php
git mv includes/blocks/class-query-filter-index-hooks.php     includes/blocks/query/class-query-filter-index-hooks.php
git mv includes/blocks/class-query-filter-index-rebuilder.php includes/blocks/query/class-query-filter-index-rebuilder.php
git mv includes/blocks/class-query-filter-index-cli.php       includes/blocks/query/class-query-filter-index-cli.php
git mv includes/blocks/class-query-filter-registry.php        includes/blocks/query/class-query-filter-registry.php
```

**Step 4.2 — Update `class-plugin.php`** loader paths: `includes/blocks/class-query*` → `includes/blocks/query/class-query*`. Add `// --- Blocks: Query engine ---` header.

**Step 4.3 — Update the explicit test require** in `tests/phpunit/blocks/query/filter-index-cli-test.php:85`:
- `'includes/blocks/class-query-filter-index-cli.php'` → `'includes/blocks/query/class-query-filter-index-cli.php'`

**Step 4.4 — Lint**

```bash
for f in includes/blocks/query/*.php; do php -l "$f"; done
php -l includes/class-plugin.php
php -l tests/phpunit/blocks/query/filter-index-cli-test.php
```

**Step 4.5 — Commit**

```bash
git add -A
git commit -m "refactor: nest query engine under includes/blocks/query/"
```

---

## Task 5: `blocks/forms/`, `blocks/modal/`, `bindings/`, `dynamic-tags/`

**Files:**
- Forms (nest in blocks/): `class-form-handler.php`, `class-form-security.php`, `class-form-submissions.php` → `includes/blocks/forms/`
- Modal (nest in blocks/): `includes/blocks/class-modal-hooks.php` → `includes/blocks/modal/class-modal-hooks.php`
- Bindings (evict to top level): `includes/class-block-bindings-support.php` (root) + `includes/blocks/class-query-bindings*.php` → `includes/bindings/`
- Dynamic tags (promote to top level): `includes/blocks/dynamic-tags/*.php` → `includes/dynamic-tags/`
- Modify: `includes/class-plugin.php`

**Step 5.1 — Move forms**

```bash
mkdir -p includes/blocks/forms
git mv includes/blocks/class-form-handler.php     includes/blocks/forms/class-form-handler.php
git mv includes/blocks/class-form-security.php    includes/blocks/forms/class-form-security.php
git mv includes/blocks/class-form-submissions.php includes/blocks/forms/class-form-submissions.php
```

**Step 5.2 — Move modal**

```bash
mkdir -p includes/blocks/modal
git mv includes/blocks/class-modal-hooks.php includes/blocks/modal/class-modal-hooks.php
```

**Step 5.3 — Move bindings (top level)**

```bash
mkdir -p includes/bindings
git mv includes/class-block-bindings-support.php          includes/bindings/class-block-bindings-support.php
git mv includes/blocks/class-query-bindings-helpers.php   includes/bindings/class-query-bindings-helpers.php
git mv includes/blocks/class-query-bindings.php           includes/bindings/class-query-bindings.php
git mv includes/blocks/class-query-bindings-metabox.php   includes/bindings/class-query-bindings-metabox.php
git mv includes/blocks/class-query-bindings-pods.php      includes/bindings/class-query-bindings-pods.php
git mv includes/blocks/class-query-bindings-jetengine.php includes/bindings/class-query-bindings-jetengine.php
```

**Step 5.4 — Promote dynamic-tags (top level)**

```bash
git mv includes/blocks/dynamic-tags includes/dynamic-tags
```

**Step 5.5 — Update `class-plugin.php`** loader paths:
- forms `class-form-*`: `includes/blocks/` → `includes/blocks/forms/`
- modal: `includes/blocks/class-modal-hooks.php` → `includes/blocks/modal/class-modal-hooks.php`
- `class-block-bindings-support.php`: `includes/` → `includes/bindings/`
- the five `class-query-bindings*`: `includes/blocks/` → `includes/bindings/`
- the nine `dynamic-tags/...`: `includes/blocks/dynamic-tags/` → `includes/dynamic-tags/`
Add `// --- Blocks: Forms ---`, `// --- Blocks: Modal ---`, `// --- Block Bindings (cross-cutting) ---`, `// --- Dynamic tags (cross-cutting) ---` headers.

> Note: the evicted bindings files keep their `DesignSetGo\Blocks\Query` namespace and `class-query-bindings-*` names for this pass — namespace/name alignment is a deliberate follow-up.

**Step 5.6 — Lint**

```bash
for f in includes/blocks/forms/*.php includes/blocks/modal/*.php includes/bindings/*.php includes/dynamic-tags/*.php; do php -l "$f"; done
php -l includes/class-plugin.php
```

**Step 5.7 — Commit**

```bash
git add -A
git commit -m "refactor: nest forms/modal under blocks/; evict bindings + dynamic-tags to top level"
```

---

## Task 6: `integrations/query-monitor/`

**Files:**
- Move: `includes/class-query-qm-collector.php`, `includes/class-query-qm-output.php` → `includes/integrations/query-monitor/`
- Modify: `includes/class-plugin.php`

**Step 6.1 — Move**

```bash
mkdir -p includes/integrations/query-monitor
git mv includes/class-query-qm-collector.php includes/integrations/query-monitor/class-query-qm-collector.php
git mv includes/class-query-qm-output.php    includes/integrations/query-monitor/class-query-qm-output.php
```

**Step 6.2 — Update `class-plugin.php`** (both paths, under the `defined('QM_VERSION')` guard) → `includes/integrations/query-monitor/`. Add `// --- Integrations: Query Monitor ---` header.

**Step 6.3 — Confirm `includes/blocks/` now contains only the registrar + the three subfolders**

```bash
ls -A includes/blocks
```
Expected: `class-loader.php  forms  modal  query` (and nothing else loose). If a loose `class-*.php` remains, it was missed — move it before proceeding.

**Step 6.4 — Lint + commit**

```bash
php -l includes/integrations/query-monitor/class-query-qm-collector.php
php -l includes/integrations/query-monitor/class-query-qm-output.php
php -l includes/class-plugin.php
git add -A
git commit -m "refactor: move Query Monitor integration into includes/integrations/"
```

---

## Task 7: Verify nothing references old paths + full test run

**Step 7.1 — Grep for surviving old path strings** (should return ONLY comments, if anything):

```bash
# Files that LEFT blocks/ entirely (bindings, dynamic-tags) — no path should still say includes/blocks/<that-file>
grep -rn "includes/blocks/class-query-bindings\|includes/blocks/dynamic-tags\|includes/blocks/class-form\|includes/blocks/class-modal\|includes/blocks/class-query\b" includes designsetgo.php uninstall.php tests --include="*.php"
# Files that left the includes/ root
grep -rn "includes/class-assets\|includes/class-sticky\|includes/class-overlay\|includes/class-section-styles\|includes/class-button-global\|includes/class-icon-injector\|includes/class-custom-css\|includes/class-style-binding\|includes/class-svg-pattern\|includes/class-block-visibility\|includes/class-extension-attributes\|includes/breadcrumbs\|includes/icon-svg-library\|includes/svg-pattern-data\|includes/block-animation\|includes/class-block-bindings-support\|includes/class-query-qm" includes designsetgo.php uninstall.php tests --include="*.php"
```
Expected: no `require_once`/`include` hits. Any match must be a doc comment. Update stale doc comments in:
- `tests/phpunit/block-animation-attributes-test.php:6`
- `tests/phpunit/icon-svg-library-test.php:6`
- `tests/phpunit/wp-trigger-error-test.php` (lines ~231, ~299)
(cosmetic — fix for accuracy, not correctness)

**Step 7.2 — Confirm tooling still scans the tree.** Open `phpstan.neon` and `phpcs.xml`; verify they scan `includes/` (or `.`) broadly, not an enumerated subdir list. If either enumerates specific subdirs (e.g. `includes/blocks`), add the new top-level dirs (`bindings`, `dynamic-tags`, `features`, `core`, `integrations`).

**Step 7.3 — Full quality gate**

```bash
composer lint:php   # PHPCS
composer phpstan
vendor/bin/phpunit
npm run build       # sanity: JS build untouched but confirm no breakage
```
Expected: same pass/fail baseline as Step 0.2 (no NEW failures).

**Step 7.4 — Smoke test the plugin actually loads** (no fatal from a dangling require). In wp-env:

```bash
npx wp-env run cli wp eval 'echo defined("DESIGNSETGO_VERSION") ? "DSGo loaded OK\n" : "NOT LOADED\n";'
npx wp-env logs   # confirm no PHP fatals/warnings on load
```
Expected: `DSGo loaded OK`, clean logs.

**Step 7.5 — Final commit (doc-comment cleanup if any)**

```bash
git add -A
git commit -m "refactor: update stale path references in test doc comments"
```

---

## Rollback

Each task is one commit. To undo a single step: `git revert <sha>`. To abandon entirely: `git checkout main && git branch -D claude/includes-restructure`. Because every move used `git mv`, history follows the files (`git log --follow <newpath>`).

## Done when

- `includes/` root holds only `class-plugin.php` + `helpers.php` + concern folders.
- `includes/blocks/` holds only `class-loader.php` + `query/` + `forms/` + `modal/`.
- Cross-cutting infra (`bindings/`, `dynamic-tags/`, `features/`) is top-level.
- Step 7.1 greps return no functional old-path references.
- Step 7.3 gate matches the Step 0.2 baseline; Step 7.4 smoke test passes with clean logs.
