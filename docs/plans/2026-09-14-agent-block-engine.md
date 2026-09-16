# Agent Block Engine Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Agents build pages from DesignSetGo blocks through one JavaScript engine that serializes with the real `save()` in Node, in the editor, and — via a browser finishing step — for remote MCP agents, with lint rules and per-block guidance for design quality.

**Architecture:** `src/engine/` holds environment-agnostic modules (`tree`, `assemble`, `validate`, `lint`) that take a WordPress blocks API object as a parameter. The Node build (`webpack.engine.config.js` → `build/engine/node.cjs`) boots jsdom and registers blocks from their real `index.js` files; the browser build uses the editor's `wp.blocks`. Remote agents store a pending tree through a PHP ability; an editor plugin assembles it with the real registry and reports back over REST.

**Tech Stack:** `@wordpress/blocks` / `block-editor` / `block-library@9.8.18` (wp-6.7), jsdom, webpack 5, Jest (`wp-scripts test-unit-js`), `node:test` for the built bundle, PHP 7.4+ / Abilities API / WP REST, PHPUnit and Playwright on wp-env.

**Spec:** [`2026-09-14-agent-block-engine-design.md`](./2026-09-14-agent-block-engine-design.md). Read it first.

---

## Ground rules for every task

- Work only in `/Users/jnealey/github-local/designsetgo/.claude/worktrees/agent-block-engine` (branch `claude/agent-block-engine`). Never `cd` to the main checkout.
- Shell setup, every command: `export PATH="/Applications/Docker.app/Contents/Resources/bin:$HOME/.nvm/versions/node/v22.23.2/bin:$PATH"`.
- Tabs for JS/PHP, 2 spaces for JSON. Max 300 lines per file. No `console.log` in `src/` except the CLI's deliberate stdout/stderr writes (use `process.stdout.write`). `designsetgo_` PHP prefix, `dsgo` JS/CSS prefix. `defined( 'ABSPATH' ) || exit;` in PHP.
- TDD: write the failing test, watch it fail, implement, watch it pass.
- Run a single Jest file with `npx wp-scripts test-unit-js <path>`. `@wordpress/jest-console` fails a test on unexpected `console.error/warn/log`; engine code must mute WordPress's registration noise itself (see Task 3), never by editing Jest config.
- Commit with `git add <paths>` (never `-A`), message `type: description`, no attribution trailers.
- If an existing test starts failing, stop and report — do not weaken it.

---

## Phase 1 — Engine in Node

### Task 1: Commit the Node build scaffold

Already present uncommitted from the spike: `webpack.engine.config.js`, `src/engine/node/stubs/{style,editor,notices}.js`, `package.json`/`package-lock.json` with `@wordpress/block-library@9.8.18`, and throwaway `src/engine/node/cli.js` + `src/engine/node/spike-run.js`.

**Files:**
- Modify: `webpack.engine.config.js` (add single-chunk output)
- Modify: `package.json` (scripts, `files`)
- Modify: `.distignore`
- Delete: `src/engine/node/spike-run.js`
- Replace: `src/engine/node/cli.js` with a placeholder that prints usage

**Step 1:** In `webpack.engine.config.js` add to `plugins`: `new webpack.optimize.LimitChunkCountPlugin({ maxChunks: 1 })` (dynamic imports in extensions otherwise emit `ext-*.cjs` side files).

**Step 2:** `package.json` scripts: `"build:engine": "webpack --config webpack.engine.config.js"`, `"engine": "node build/engine/node.cjs"`, `"test:engine": "npm run build:engine && node --test tests/engine/"`. Add `"!build/engine/**"` to `files`. `.distignore`: add `webpack.engine.config.js` under Build Tools and `/build/engine` under a new "Agent engine (Node only)" heading.

**Step 3:** Replace `src/engine/node/cli.js` with:

```js
process.stdout.write('Usage: npm run engine -- <assemble|validate|lint> <file> [options]\n');
process.exitCode = 2;
```

**Step 4:** `npm run build:engine` → "compiled successfully", only `build/engine/node.cjs` emitted. `npm run engine; echo $?` → usage, `2`.

**Step 5:** Commit `webpack.engine.config.js src/engine/node package.json package-lock.json .distignore` — `chore: add the Node build for the agent block engine`.

---

### Task 2: Tree contract

**Files:**
- Create: `src/engine/tree.js`
- Test: `src/engine/test/tree.test.js`

`src/engine/tree.js` exports:

```js
export const TREE_VERSION = 1;

/** Path for the block at `index` beneath `parentPath` ('' for the root list). */
export function childPath(parentPath, index) {
	return parentPath ? `${parentPath}.innerBlocks[${index}]` : `blocks[${index}]`;
}

/**
 * Structural check of a block tree. Never throws.
 *
 * @param {unknown} tree Candidate tree.
 * @return {{ code: string, path: string, message: string }[]} Problems; empty when well formed.
 */
export function checkTreeShape(tree) { /* ... */ }

/** Depth-first visit: callback(node, path, parentNode|null, ancestors[]) */
export function walkTree(blocks, callback, parentPath = '', ancestors = []) { /* ... */ }
```

Problem codes (exact strings): `designsetgo_unsupported_tree_version` (path `version`), `designsetgo_invalid_tree` (root not an object or `blocks` not an array; path `blocks`), `designsetgo_invalid_block_definition` (node not an object, `name` not matching `/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/`, `attributes` present and not a plain object, `innerBlocks` present and not an array; path = node path).

**Step 1: failing tests** — one `it` per code plus: a valid nested tree returns `[]`; paths are `blocks[1].innerBlocks[0]`; `walkTree` visits in document order with correct `ancestors` names.

**Step 2:** run → FAIL (module not found). **Step 3:** implement. **Step 4:** PASS. **Step 5:** commit `feat(engine): add the block tree contract`.

---

### Task 3: Engine core — assemble and validate

**Files:**
- Create: `src/engine/quiet.js` — `withQuietConsole(fn)` mutes `console.{log,info,warn,error,groupCollapsed,groupEnd}` for the duration of `fn` and restores them in `finally` (also when `fn` throws). WordPress logs validation diffs and "already registered" noise through these.
- Create: `src/engine/assemble.js`
- Create: `src/engine/validate.js`
- Create: `src/engine/index.js` — `export function createEngine(blocksApi)` returning `{ assemble, validate }` bound to that API.
- Test: `src/engine/test/assemble.test.js`, `src/engine/test/validate.test.js`

`blocksApi` is an object with `createBlock, serialize, parse, getBlockType` (both `@wordpress/blocks` and `window.wp.blocks` satisfy it).

**`validate(blocksApi, markup)`** → `{ status: 'valid'|'invalid', invalid: [{ path, block, reason }] }`. Parse inside `withQuietConsole`, walk parsed blocks recursively (same path format as `childPath`), a block is invalid when `isValid === false` (reason: joined `validationIssues[].args`, max 500 chars) or `name === 'core/missing'` (reason `block type is not registered`).

**`assemble(blocksApi, tree)`** → `{ status, markup, invalid, treeHash }`:
1. `checkTreeShape(tree)`; any problem → `status: 'invalid'`, `markup: ''`, `invalid` = problems mapped to `{ path, block: node name or '', reason: message, code }`.
2. Walk the tree; unregistered names (`!getBlockType(name)`) → `designsetgo_unknown_block` problems, same early return.
3. Build blocks bottom-up with `createBlock(name, attributes ?? {}, children)`, serialize, then `validate()` the serialized markup and return its `invalid` list (status `valid` when empty).
4. `treeHash`: sha256 hex of `JSON.stringify(tree)`. Use a small pure-JS sha256 in `src/engine/hash.js` (Node's `crypto` is not available in the browser build; keep it dependency-free, ~60 lines, test against the known digest of `"abc"`: `ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad`).

**Tests** register blocks through the Jest registry from Task 4 — so write Task 3's tests against a minimal registration: in `beforeAll`, import `registerForJest` is not yet available, so register two throwaway block types directly with `registerBlockType` from `@wordpress/block-editor/node_modules/@wordpress/blocks` (`test/static` with a `save` returning `<p>{attributes.text}</p>` and `attributes.text: { type: 'string', source: 'html', selector: 'p' }`; `test/wrap` with `save` → `<div><InnerBlocks.Content/></div>`). Cases: valid nested tree → `status: valid`, markup contains both comments; unknown block → `designsetgo_unknown_block` at `blocks[0].innerBlocks[1]`; bad version → `designsetgo_unsupported_tree_version`; `validate()` of hand-edited markup (`<p>changed</p>` inside a `test/static` whose comment says other text is fine — instead alter the tag to `<span>`) → invalid with path `blocks[0]`; `validate()` of an unregistered comment → `core/missing` reason; `withQuietConsole` restores console after a throw; identical trees yield identical `treeHash`.

Commit: `feat(engine): assemble and validate block trees`.

---

### Task 4: Registry — register blocks the way the editor does

**Files:**
- Create: `src/engine/registry/register-all.js`
- Create: `src/engine/registry/sources-fs.js` (Jest / tests)
- Create: `src/engine/registry/sources-webpack.js` (Node build)
- Test: `tests/unit/engine/registry.test.js`

`register-all.js`:

```js
/**
 * Register every block the way the block editor does.
 *
 * Order matters and mirrors a real editor page load:
 * 1. block.json definitions, as PHP bootstraps them — 14 blocks pass only
 *    metadata.name to registerBlockType and are refused without this.
 * 2. the designsetgo category.
 * 3. core blocks.
 * 4. extensions (attribute + save-prop filters must exist before blocks register).
 * 5. each block's real index.js.
 *
 * @param {Object}   blocksApi  @wordpress/blocks (the copy block-editor uses).
 * @param {Object}   sources    { blockJsons: Object[], extensionModules: [name, () => void][], blockModules: [name, () => void][], registerCoreBlocks: () => void }
 * @return {{ failures: { file: string, message: string }[] }} Registration files that threw.
 */
export function registerAll(blocksApi, sources) { /* each step inside withQuietConsole; a throwing module is recorded, not rethrown */ }
```

Guard against double registration: if `blocksApi.getBlockType('designsetgo/section')` already exists, return `{ failures: [] }` without re-running.

`sources-fs.js` (CommonJS `require` + `fs`, only used under Jest): reads `src/blocks/*/block.json`, returns thunks requiring `src/extensions/*/index.js` and `src/blocks/*/index.js` (skip dirs without the file, e.g. `src/blocks/shared`), and `registerCoreBlocks` from `@wordpress/block-library`. Export `registerForJest()` which imports the nested blocks API and calls `registerAll`, returning failures.

`sources-webpack.js`: same shape via `require.context('../../blocks', true, /^\.\/[^/]+\/block\.json$/)`, `require.context('../../extensions', true, /^\.\/[^/]+\/index\.js$/)`, `require.context('../../blocks', true, /^\.\/[^/]+\/index\.js$/)`.

**Tests (`tests/unit/engine/registry.test.js`):**
- `registerForJest()` returns no failures.
- Completeness: every `name` in `src/blocks/*/block.json` is registered (`getBlockType`), and there are at least 90 `core/` types.
- Every `src/extensions/*/index.js` was loaded: assert one known attribute per attribute-registering extension exists on `designsetgo/section` or `core/group` — read the extension source to pick the attribute (e.g. `dsgoVisibility` from `visibility/filters.js`, `dsgoAnimationEnabled` from block-animations). List at least 6.
- Mixed tree from the spike (section > heading, paragraph, image, buttons>button, row>icon-button) assembles `valid` through `createEngine`.

The jest-console noise: the registration runs inside `withQuietConsole`, so tests stay clean. If WordPress logs *asynchronously* after registration (timers), call `jest.runOnlyPendingTimers` is NOT allowed to be added globally — instead report it.

Commit: `feat(engine): register blocks from their real registration files`.

---

### Task 5: Node runtime and CLI

**Files:**
- Create: `src/engine/node/dom.js` — installs jsdom globals (`window, document, navigator, Node, Element, HTMLElement, DOMParser, MutationObserver, Event, CustomEvent, getComputedStyle, requestAnimationFrame, cancelAnimationFrame`, `window.matchMedia` stub) only when absent. Must be required before any `@wordpress/*` module.
- Create: `src/engine/node/boot.js` — `require('./dom')` first, then `registerAll(blocksApi, sources-webpack)`; exports `bootEngine()` → `{ engine, failures }`. Plugin source imports `@wordpress/blocks`, which the webpack externals already point at the nested copy.
- Create: `src/engine/node/args.js` — parse `argv` into `{ command, file, flags }`; flags `--json`, `--lint`, `--context <file>`, `--max-warnings <n>`, `--out <file>`.
- Replace: `src/engine/node/cli.js`
- Test: `tests/engine/cli.test.mjs` (`node:test`, runs the built bundle via `child_process.spawnSync(process.execPath, ['build/engine/node.cjs', ...])`)
- Fixtures: `tests/engine/fixtures/valid-tree.json`, `unknown-block.json`, `invalid-markup.html`, `valid-markup.html`

Commands:
- `assemble <tree.json>` — stdout: markup (or full report with `--json`). Exit `0` valid, `1` invalid.
- `validate <file...>` — each file is markup; report per file; exit `1` if any invalid.
- `lint <tree.json>` — added in Task 12; until then exit `2` with "lint is not available yet".
- Unknown command, missing file, unreadable JSON → stderr message, exit `2`.
- Any registration failure from `bootEngine()` → stderr lists files, exit `2` (a partially registered engine must not report "valid").

All non-output WordPress noise is muted; stdout carries only the markup or JSON.

**Step 1:** write `tests/engine/cli.test.mjs` covering every exit code above, `--json` parses and has `status`, `--out` writes the file. **Step 2:** `npm run test:engine` → FAIL. **Steps 3–4:** implement, PASS. **Step 5:** commit `feat(engine): add the assemble and validate CLI`.

---

### Task 6: Round trip for every block

**Files:**
- Create: `tests/unit/helpers/non-default-value.js` — move `nonDefaultValue()` out of `tests/unit/deprecations-isEligible.test.js` verbatim, export it; update that test to import it.
- Test: `tests/unit/engine/round-trip.test.js`

For every registered `designsetgo/*` type: (a) `createBlock(name)` assembles valid; (b) for each attribute with a `nonDefaultValue()` (skip `source` attributes other than `html`/`text`, skip `role: 'local'`/`__experimental*`), a single-attribute tree assembles valid **and** re-parsing the markup returns that attribute's value (deep-equal). Use `describe.each` so failures name block and attribute.

If a case fails, do not skip it: record the block, attribute and diff in the task report. The controller decides whether it is a real `save()` bug (fix in a separate commit with a deprecation if markup changes) or an attribute the round trip legitimately cannot preserve (add to an explicit, commented `KNOWN_LOSSY` map in the test with the reason).

Commit: `test(engine): round-trip every block attribute through the engine`.

---

### Task 7: Move existing Jest registration onto the registry

**Files:**
- Modify: `tests/unit/deprecations-isEligible.test.js`
- Modify: `tests/unit/ability-generated-markup.test.js`
- Modify: `tools/regenerate-patterns.js` (keep `registerDesignSetGoBlock` exported as a thin wrapper that calls `registerForJest()` so any disposable runner keeps working; update the header comment about extension imports — the registry loads all extensions)

Replace per-block `registerDesignSetGoBlock` calls with `registerForJest()` in `beforeAll`. Run both tests and `tests/unit/blocks` deprecation tests.

Expected risk: full registration adds extension-appended deprecations, so `deprecations-isEligible` covers more entries than before. If it now fails, stop and report the failing block/deprecation — that is a real finding, not a test to relax.

Commit: `refactor(tests): register blocks through the engine registry`.

---

### Task 8: Generated cases for the frozen PHP writer

**Files:**
- Create: `src/engine/node/fixture-cases.js` + CLI command `fixture-cases --out tests/unit/__fixtures__/ability-generated-cases.json`
- Modify: `tests/phpunit/abilities-generated-markup-fixture-test.php` — add `generated_payloads()` reading the cases file, merged into `payloads()`, keyed `generated::<block>::<attribute>`
- Modify: `tests/unit/ability-generated-markup.test.js` — invalid `generated::` keys are compared against `tests/unit/__fixtures__/ability-generated-known-drift.json` (an array of keys); new invalid keys fail, keys in the allowlist that became valid also fail ("remove from known drift")
- Regenerate: `tests/unit/__fixtures__/ability-generated-markup.json`

Cases: for each `designsetgo/*` block that `Block_Inserter` can serialize (not `parent`-restricted children — skip blocks whose `block.json` has `parent` or `ancestor`), one payload per attribute from `nonDefaultValue()`, `{ block_name, attributes: { [attr]: value }, inner_blocks: [] }`.

Regenerate the PHP fixture inside wp-env (start it from this worktree: `npx wp-env start`; see memory note — if 9451 is taken, set `WP_ENV_PORT`/`WP_ENV_TESTS_PORT`):
`npx wp-env run tests-cli --env-cwd=wp-content/plugins/agent-block-engine bash -c 'DSGO_UPDATE_FIXTURES=1 vendor/bin/phpunit --filter Abilities_Generated_Markup_Fixture'` (the plugin directory name is the worktree basename; confirm with `wp plugin list`). Then run the Jest test, put every currently invalid generated key into the known-drift file, and state the count in the commit body.

Commit: `test(abilities): guard the frozen PHP writer with generated cases`.

---

## Phase 2 — Design quality

### Task 9: Lint framework and design context helpers

**Files:**
- Create: `src/engine/lint/index.js` — `lint(tree, designContext = {}, { rules } = {})` → `findings[]` sorted by path; each rule module exports `{ id, severity, check(node, ctx) }` where `ctx = { path, parent, ancestors, tree, design, report(message, suggestion?) }`; rules are also given `checkTree(tree, ctx)` optionally for page-level rules.
- Create: `src/engine/lint/design.js` — from a `get-design-context` payload: `paletteColors()` → `Map slug→hex` (merge `settings.color.palette.{default,theme,custom}`; later wins), `spacingSlugs()`, `fontSizeSlugs()`, `presetColor(value)` resolving `slug`, `var:preset|color|slug`, `var(--wp--preset--color--slug)`.
- Create: `src/engine/lint/color.js` — `parseColor(hex|rgb())` → `{r,g,b}` or `null`, `contrastRatio(a, b)` per WCAG 2.x.
- Create: `src/engine/lint/rules/index.js` exporting the rule list.
- Tests: `src/engine/lint/test/{lint,design,color}.test.js`

Tests: contrast of `#000`/`#fff` is 21, `#777`/`#fff` ≈ 4.48; palette merge precedence; a fake rule receives correct `path`/`ancestors`; unknown design context yields no crash and no findings from context-dependent rules.

Commit: `feat(engine): add the lint framework`.

### Task 10: Rules — content and accessibility

**Files:** `src/engine/lint/rules/{no-custom-html,image-alt,heading-order}.js`, tests in `src/engine/lint/rules/test/`.

- `no-custom-html` (error): `core/html`; any block whose string attributes contain `<svg`. Suggest a real block (icon / image).
- `image-alt` (error): `core/image`, `designsetgo/dynamic-image` with empty/missing `alt` — unless `className` contains `is-decorative` or attribute `dsgoDecorative` is true (check block.json for the real decorative attribute names first; use what exists).
- `heading-order` (error, page-level): collect `core/heading` levels and `designsetgo/advanced-heading` levels in document order; more than one level-1 → finding on the second; a jump of more than one level downward (h2 → h4) → finding on the later heading.

Each rule: one passing and one failing fixture tree per condition. Commit: `feat(engine): lint custom HTML, image alt text and heading order`.

### Task 11: Rules — tokens and layout

**Files:** `src/engine/lint/rules/{contrast,preset-values,prefer-dsgo-layout,top-level-sections,mobile-layout,empty-container}.js` + tests.

- `contrast` (error): resolve text (`textColor` slug or `style.color.text`) and background (`backgroundColor` slug or `style.color.background`), inheriting the nearest ancestor's values when a block has none; skip when either is unresolvable or a gradient/background image is set (`gradient`, `style.color.gradient`, `style.background.backgroundImage`). Ratio < 4.5 → finding with both colors and the ratio to two decimals.
- `preset-values` (warning): `style.color.{text,background}` raw colors when the palette is non-empty (suggest nearest palette slug by RGB distance); `style.spacing.{padding,margin,blockGap}` raw `px`/`rem`/`em` values when spacing presets exist; `style.typography.fontSize` raw when font-size presets exist.
- `prefer-dsgo-layout` (warning): `core/columns`; `core/group` whose `layout.type` is `flex` (→ `designsetgo/row`) or `grid` (→ `designsetgo/grid`); top-level `core/group` (→ `designsetgo/section`).
- `top-level-sections` (warning, page-level): exactly one top-level block that is a container (`core/group`, `designsetgo/section`) holding ≥ 2 container children.
- `mobile-layout` (warning): read `src/blocks/grid/block.json` and `src/blocks/row/block.json` to find the real desktop-column and mobile/stack attributes (e.g. grid `desktopColumns`/`mobileColumns`, row stacking/wrap); flag ≥ 3 desktop columns with no mobile override. Document the attribute names chosen in the rule's header comment.
- `empty-container` (warning): `designsetgo/section|row|grid|card`, `core/group|columns|column` with no `innerBlocks`.

Commit: `feat(engine): lint contrast, preset use and layout`.

### Task 12: CLI lint integration

**Files:** Modify `src/engine/node/cli.js`, `src/engine/index.js` (add `lint` to `createEngine` return), `tests/engine/cli.test.mjs`.

- `lint <tree.json> [--context file]` → findings as text (`severity path rule: message`) or JSON; exit `1` on any error or when warnings exceed `--max-warnings` (default unlimited).
- `assemble --lint` → report gains `findings`; lint errors make exit `1` even when markup is valid.

Commit: `feat(engine): run lint from the CLI`.

### Task 13: Per-block agent guidance

**Files:**
- Create: `src/blocks/{section,row,grid,card,icon-button,accordion,tabs}/agent.json`
- Create: `src/engine/guidance-schema.json` and `tests/unit/engine/guidance.test.js`
- Modify: `webpack.config.js` copy patterns (`src/blocks/*/agent.json` → `build/blocks/<name>/agent.json`, mirroring the `block.json` pattern)
- Modify: `includes/abilities/info/class-list-blocks.php` — add `guidance` (decoded `agent.json` from `DESIGNSETGO_PLUGIN_DIR . 'build/blocks/<dir>/agent.json'`, or `null`) when `detail` is `full`; map block name → directory by reading each `build/blocks/*/block.json` name once per request
- Test: `tests/phpunit/abilities-list-blocks-guidance-test.php` (assert `assertFileExists` on the build copy first, then `guidance.whenToUse` for `designsetgo/section`)

Guidance content must describe real attributes: read each block's `block.json` and `edit.js` before writing examples. Each example tree must be realistic (headings, copy, CTA), use theme preset slugs (`base`, `contrast`, spacing `50`) rather than raw values, and follow the lint rules.

Jest test: every `agent.json` validates against the schema (write a minimal hand-rolled validator — no new dependency); every example assembles `valid` and has zero lint errors and zero warnings with the Twenty Twenty-Five palette fixture `tests/unit/__fixtures__/design-context-tt5.json` (create it from `wp_get_global_settings()` output inside wp-env, trimmed to `color.palette`, `spacing.spacingSizes`, `typography.fontSizes`).

Run `npm run build` then `grep -l whenToUse build/blocks/*/agent.json | wc -l` → 7. Commit: `feat(abilities): give agents per-block guidance`.

---

## Phase 3 — Browser

### Task 14: Browser engine global

**Files:**
- Create: `src/engine/browser/index.js` — `window.designsetgoEngine = { version: 1, assemble: (tree) => …, validate: (markup) => …, lint: (tree, design) => … }` built with `createEngine(window.wp.blocks)` lazily on first call (blocks register after this script runs).
- Modify: `src/index.js` — `import './engine/browser';`
- Test: `src/engine/browser/test/index.test.js` (mock `window.wp.blocks` with the nested API after `registerForJest()`)

Run `npm run build`; confirm the editor entry stays under the 250 KB budget (webpack prints a warning otherwise). Commit: `feat(engine): expose the engine in the block editor`.

### Task 15: Editor "Agent build" panel

**Files:**
- Create: `src/engine/browser/panel/{index,AgentBuildPanel,ReportList}.js`
- Modify: `src/engine/browser/index.js` (register the plugin)
- Test: `src/engine/browser/panel/test/AgentBuildPanel.test.js`

`registerPlugin('designsetgo-agent-build', { render })` with `PluginSidebar` + `PluginSidebarMoreMenuItem` (import from `@wordpress/editor`, falling back to `@wordpress/edit-post` exactly like the existing draft-mode panel does — read `src/extensions/draft-mode/` first and follow its pattern). Content: `TextareaControl` for tree JSON (`__nextHasNoMarginBottom`), buttons **Check** (assemble + lint, show report) and **Insert** (only when valid: `insertBlocks(parsedBlocks)` from `core/block-editor`). `ReportList` renders invalid entries and findings grouped by severity with paths. All strings translated.

Tests: invalid JSON shows an error notice, invalid tree disables Insert, valid tree calls `insertBlocks` with blocks named as the tree.

Commit: `feat(engine): add an Agent build panel to the editor`.

### Task 16: Node and browser parity (Playwright)

**Files:** `tests/e2e/agent-engine-parity.spec.js`, reuse `tests/engine/fixtures/*.json` plus 3 richer trees in `tests/e2e/fixtures/agent-trees/`.

Before the test: `npm run build && npm run build:engine`; for each tree, generate Node markup with `node build/engine/node.cjs assemble <tree> --json` in the spec's `beforeAll`. In the editor page, `page.evaluate` → `window.designsetgoEngine.assemble(tree)`. Compare only DesignSetGo block regions: extract each `<!-- wp:designsetgo/… -->…<!-- /wp:designsetgo/… -->` outermost region from both outputs and assert equality after replacing core block comments+markup inside them with a placeholder (core may differ between 6.7 and the site's WordPress).

Run against this worktree's wp-env. Commit: `test(engine): assert Node and editor serialize identically`.

---

## Phase 4 — Remote agents

### Task 17: Tree validation in PHP

**Files:**
- Create: `includes/abilities/agent-build/class-tree-validator.php` — `Tree_Validator::validate( $tree ): array` returning a list of `{ code, path, message }`
- Modify: `includes/abilities/class-block-inserter.php` — add `public static function find_tree_placement_problems( array $tree ): array` that runs the existing child-placement and attribute-value checks **without** the serialization-gap check (the engine serializes, so PHP coverage gaps are irrelevant). Reuse, don't copy, the private finders.
- Modify: `includes/abilities/class-abilities-registry.php` — require the new directory's classes (it loads `class-*.php` per directory; check whether subdirectories need adding to its list)
- Test: `tests/phpunit/agent-build-tree-validator-test.php`

Checks in order: version (`designsetgo_unsupported_tree_version`), shape (same codes as `src/engine/tree.js`), size (`wp_json_encode` > 1 MB → `designsetgo_tree_too_large`), unknown block (`WP_Block_Type_Registry`), attribute schema (`rest_validate_value_from_schema( $value, $attr_schema_without_source_keys, $name )` for each provided attribute that the block type declares; unknown attribute names are allowed — extensions add attributes only JS knows about), placement (`find_tree_placement_problems`, mapped to `designsetgo_invalid_child_placement` with its paths converted to `blocks[i].innerBlocks[j]` form).

Tests: one per code, a valid DesignSetGo + core tree passes, path format matches the JS contract.

Commit: `feat(abilities): validate agent block trees in PHP`.

### Task 18: Pending build store and REST route

**Files:**
- Create: `includes/abilities/agent-build/class-build-store.php` — meta keys `_dsgo_pending_tree` (JSON string: `{ tree, mode, base }`), `_dsgo_build_report` (JSON string). Methods: `store( int $post_id, array $tree, string $mode ): void` (writes report `{ status: 'pending', treeHash }`), `pending( int $post_id ): ?array`, `report( int $post_id ): array`, `write_report( int $post_id, array $report ): void`, `clear( int $post_id ): void`, `is_conflict( int $post_id ): bool` (`get_post_field( 'post_modified_gmt' )` ≠ stored `base`). Register both meta keys with `register_post_meta( '', $key, array( 'single' => true, 'type' => 'string', 'show_in_rest' => false, 'auth_callback' => fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id ) ) )`.
- Create: `includes/abilities/agent-build/class-build-rest.php` — `designsetgo/v1/agent-build/(?P<id>\d+)`:
  - `GET` → `{ pending: bool, tree, mode, conflict, postStatus, designContext }` (`designContext` from `( new Get_Design_Context() )->execute( array() )`)
  - `POST` body `{ status, invalid, findings }`; `status` enum `finished|finished_with_findings|failed|conflict|awaiting_review|discarded`; terminal statuses (`finished`, `finished_with_findings`, `discarded`) clear the pending tree; `failed`/`conflict`/`awaiting_review` keep it
  - `permission_callback`: `current_user_can( 'edit_post', $id )`; validate `id` exists
- Modify: plugin bootstrap where other REST controllers are hooked (find how `class-draft-mode-rest.php` is loaded and follow it)
- Tests: `tests/phpunit/agent-build-store-test.php`, `tests/phpunit/agent-build-rest-test.php` (permissions for subscriber/contributor-on-others'-post/editor, status enum, clearing rules, conflict after `wp_update_post`)

Commit: `feat(abilities): store pending agent builds`.

### Task 19: `build-page` and `get-build-status` abilities

**Files:**
- Create: `includes/abilities/agent-build/class-build-page.php` (`designsetgo/build-page`)
- Create: `includes/abilities/agent-build/class-get-build-status.php` (`designsetgo/get-build-status`)
- Tests: `tests/phpunit/abilities-build-page-test.php`

Follow `class-add-block.php` for structure, `check_permission`, and returning input problems as data. `build-page` input: `post_id` (int) XOR `new` (`{ title: string, post_type: string default 'page' }`), `tree` (object), `mode` (`replace|append`, default `replace`). New post: `wp_insert_post` as `draft` with empty content, requires `current_user_can( get_post_type_object( $type )->cap->create_posts )`. Validation problems → `{ success: false, problems: [...] }` (no post created). Success → `{ success: true, status: 'pending', post_id, finish_url }` with `finish_url = add_query_arg( 'dsgo-finish', '1', get_edit_post_link( $id, 'raw' ) )`. Description text tells agents: markup is produced when the post is opened in the editor at `finish_url`; poll `get-build-status`.

`get-build-status`: input `post_id`; returns the stored report plus `pending` bool; `edit_post` required.

Both declare `annotations`: build-page `readonly: false, destructive: false, idempotent: false`; status `readonly: true`. Register via the directory the registry scans.

Tests: permission denials, `new` + `post_id` together rejected, invalid tree returns problems and writes nothing, valid tree leaves `post_content` untouched on an existing published post and stores meta, `finish_url` shape, status round trip. Also update `tests/phpunit/abilities-coverage-test.php` (or whatever enumerates abilities) for the two new names.

Commit: `feat(abilities): let remote agents build pages through the engine`.

### Task 20: Editor finishing plugin

**Files:**
- Create: `src/engine/browser/finish/{index,finish-build,apply}.js`
- Test: `src/engine/browser/finish/test/finish-build.test.js`

`finish-build.js` exports a pure orchestrator taking injected dependencies so it is unit-testable:

```js
/**
 * @param {Object} deps { fetchPending, postReport, engine, getEditorBlocks, replaceBlocks, savePost, isPublished, notify, markDocument }
 */
export async function finishBuild(postId, deps) { … }
```

Flow (exactly the spec's):
1. `fetchPending()` (GET route). Not pending → `markDocument('done')`, return.
2. `conflict` → `postReport({ status: 'conflict' })`, notify warning, `markDocument('failed')`.
3. `engine.assemble(tree)` + `engine.lint(tree, designContext)`. Invalid → `postReport({ status: 'failed', invalid, findings })`, notify error, `markDocument('failed')`.
4. Blocks: `parse(markup)`; `mode === 'append'` → current blocks + new; else new.
5. Not published → `replaceBlocks(next)`, `await savePost()`; on success `postReport({ status: findings.length ? 'finished_with_findings' : 'finished', findings })`, `markDocument('done')`; on save failure `postReport({ status: 'failed', invalid: [{ path: '', block: '', reason: 'save failed' }] })`, `markDocument('failed')`.
6. Published → keep `const original = getEditorBlocks()`, `replaceBlocks(next)`, `postReport({ status: 'awaiting_review', findings })`, `notify` with actions **Discard** (`replaceBlocks(original)`, `postReport({ status: 'discarded' })`) and a save subscription that posts `finished|finished_with_findings` after the next successful save; `markDocument('done')`.

`markDocument(state)` sets `document.documentElement.dataset.dsgoFinish`. `index.js` wires real deps (`@wordpress/api-fetch` for the route, `core/editor` for post id/status/save, `core/block-editor` `resetBlocks`, `core/notices`), waits until block registration is stable (poll `wp.blocks.getBlockTypes().length` until unchanged across 3 ticks 100 ms apart — see memory note on registration races), and runs once per editor load. Import from `src/engine/browser/index.js`.

Unit tests cover each numbered branch with fakes. Commit: `feat(engine): finish pending agent builds in the editor`.

### Task 21: Remote flow end to end

**Files:** `tests/e2e/agent-build-remote.spec.js`

Using REST with the admin session (`requestUtils.rest` from the e2e helpers — follow existing specs' auth setup): call the abilities run endpoint for `designsetgo/build-page` (find the route shape in `abilities-smoke-test.php` or WP core `wp-abilities/v1`), then:
- new draft, valid tree → open `finish_url`, wait for `html[data-dsgo-finish]`, status `finished`, reload editor, no block shows "Attempt Recovery" (`.block-editor-warning` count 0).
- tree whose assemble is invalid (use a block whose attribute value passes PHP schema but produces invalid markup is hard — instead stub with a tree containing a block registered only in PHP: skip; use `designsetgo/icon-button` with a `text` containing unbalanced HTML) — if no genuinely invalid-but-PHP-valid tree exists, assert the `failed` branch through the unit tests and document that here.
- published page → `awaiting_review`, notice visible, content on frontend unchanged; click Discard → `discarded`.
- conflict → store tree, `wp_update_post` via REST, open `finish_url` → `conflict`.
- lint warning tree → `finished_with_findings`.

Commit: `test(engine): cover the remote agent build flow end to end`.

### Task 22: Freeze the PHP writer and document

**Files:**
- Modify: descriptions in `includes/abilities/inserters/class-{add-block,add-child-block,add-accordion-item,add-tab,add-timeline-item}.php` — append: "For building or rebuilding page sections, prefer designsetgo/build-page, which serializes with the editor's own block code."
- Modify: `includes/abilities/info/class-list-abilities.php` output/guidance to name `build-page` as the recommended way to build pages
- Modify: `.claude/CLAUDE.md` — new "Agent block engine" section: tree contract, `npm run engine`, where lint rules live, `agent.json`, the rule that new blocks get no PHP `Block_Inserter` mirror, the Build_Store meta keys and finish flow
- Modify: `readme.txt` changelog — one customer-facing line (see memory: plain language, user-visible only), e.g. "AI agents can now build pages with DesignSetGo blocks that open cleanly in the editor, with checks for accessibility and design consistency."
- Modify: spec status → "Implemented (Phases 1–4)"

Run the full gates: `npm run build`, `npm run build:engine`, `npm run test:engine`, `npx wp-scripts test-unit-js`, `npm run lint:js`, `npm run lint:css`, PHPUnit in wp-env, `npm run lint:php` in wp-env, the two new Playwright specs.

Commit: `docs: document the agent block engine`.
