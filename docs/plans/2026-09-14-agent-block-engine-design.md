# Agent Block Engine — Design

**Status:** Implemented (Phases 1–4)
**Date:** 2026-09-14
**Branch:** `claude/agent-block-engine`

## Goal

Let agents build pages from DesignSetGo blocks and have every page open in the editor with no "Attempt Recovery" warning, while giving those agents enough guidance and feedback to produce pages that are well designed.

This covers three places an agent can run: a local coding agent (Node), an assistant inside the block editor (browser), and a remote agent calling a live site over MCP / the Abilities API.

## Problem

Block markup is written twice today: by each block's `save()` in JavaScript, and by `Block_Inserter`'s PHP mirror of it (`includes/abilities/class-block-inserter.php`, ~5,700 lines), which is what the Abilities API stores when an agent inserts a block. The two drift. Most recent abilities commits are parity fixes ("match save.js for form colours", "keep custom class names and anchors", "serialize shape dividers"), and each divergence is found only after an agent has written a page the editor rejects. `tests/unit/ability-generated-markup.test.js` catches drift for the cases someone thought to add to its fixture, and nothing else.

Separately, valid markup is not enough: agents pick the wrong blocks, ignore theme presets, and produce layouts that break on mobile.

`humanmade/block-runner` solves a similar problem for core blocks only. Its headless Gutenberg registers `@wordpress/block-library` and exposes no way to register third-party blocks, so it is a reference, not a dependency.

## Decisions

| # | Decision | Why |
|---|---|---|
| D1 | Agents submit a **raw block tree**, not section recipes. | Maximum flexibility; design quality comes from guidance and lint instead of a fixed vocabulary. |
| D2 | The JavaScript `save()` is the **only** source of markup. | A single implementation removes drift by construction. A browser in the loop is acceptable for remote writes, which is what makes this possible. |
| D3 | Remote writes are stored as **pending trees** and finished in the real editor. | PHP cannot run `save()`. The editor already has every block type registered for the site's actual WordPress version, including Woo and third-party blocks. |
| D4 | Published posts are **never finished automatically**. | A person reviews and publishes agent changes to live content. |
| D5 | Lint is **JavaScript-only** and never blocks a valid remote save. | Porting rules to PHP would recreate the duplication D2 removes. |
| D6 | The PHP writer (`add-block` and siblings) is **frozen**, then retired. | No new blocks get PHP mirrors. It stays guarded by a generated comparison until Site Designer finishes drafts in a browser. |
| D7 | Core blocks in the Node engine are pinned to **WordPress 6.7**, the plugin's minimum. | Core keeps deprecations for older markup, so 6.7-format markup stays valid on newer WordPress; the reverse is not true. |

## Contract: block tree v1

```json
{
	"version": 1,
	"blocks": [
		{
			"name": "designsetgo/section",
			"attributes": { "backgroundColor": "base" },
			"innerBlocks": [
				{ "name": "core/heading", "attributes": { "content": "Hello", "level": 2 } }
			]
		}
	]
}
```

- Same shape as WordPress block objects and `add-block`'s `inner_blocks` (`name`, `attributes`, `innerBlocks`). Core and DesignSetGo blocks mix freely.
- `attributes` and `innerBlocks` are optional.
- Any other `version` is rejected with `designsetgo_unsupported_tree_version`.
- Locations are reported as paths: `blocks[0].innerBlocks[2]`.

## Architecture

```
            block tree v1
                 │
     ┌───────────┼─────────────────────┐
     ▼           ▼                     ▼
  Node CLI    Editor assistant     build-page ability (PHP)
     │           │                     │  validate structure, store pending tree
     │           │                     ▼
     │           │                 editor opened at finish_url
     ▼           ▼                     ▼
  ┌─────────────────────────────────────────┐
  │ engine: registry · assemble · validate · lint │
  └─────────────────────────────────────────┘
```

### Engine (`src/engine/`)

One codebase, built twice. Each module stays under 300 lines.

- **`registry/`** — registers blocks the same way a real editor page does: bootstraps every `src/blocks/*/block.json` as a server-side definition (what PHP sends the editor), sets the `designsetgo` category, then loads every `src/extensions/*/index.js` **before** registering core blocks, then every `src/blocks/*/index.js` — the real registration files. (Implementation note: extensions load before core blocks, not after as an earlier draft of this doc had it — in a real editor page, plugin scripts enqueued via `enqueue_block_editor_assets` run before `edit-post`'s `initializeEditor()` calls `registerCoreBlocks()`, so extension filters such as animations apply to core blocks too. Registering core blocks first would silently drop those extension attributes from core blocks in agent trees.) The file lists come from the filesystem (`require.context` in the Node build, `fs` in Jest), so a new block or extension is picked up without editing a list.
- **`assemble(tree)`** → `{ markup, blocks, report }`. Builds via `createBlock` → `serialize`, re-parses the output, and requires every block to validate. A block type that is not registered is reported, not thrown.
- **`validate(markup)`** → `report`. Parse plus `validateBlock`, recursively, with the path of each invalid block.
- **`lint(tree, designContext)`** → `findings`. See [Design quality](#design-quality).

Report shape, shared by every surface:

```json
{
	"status": "valid | invalid",
	"treeHash": "sha256…",
	"invalid": [ { "path": "blocks[1]", "block": "designsetgo/grid", "reason": "…" } ],
	"findings": [ { "rule": "image-alt", "severity": "error", "path": "blocks[0].innerBlocks[3]", "message": "…", "suggestion": "…" } ]
}
```

### Registration spike (2026-09-14)

An earlier draft planned to split the save-affecting half of 12 extensions out of their `index.js`. A spike showed that is unnecessary: in jsdom, every real `src/extensions/*/index.js` and `src/blocks/*/index.js` loads without error (editor-only filters such as `editor.BlockEdit` are inert without an editor), and after bootstrapping `block.json` definitions all **72** DesignSetGo blocks and **90** core blocks register, round-trip valid at defaults, and serialize a mixed core + DesignSetGo tree with every block valid. This held both under Jest and in a plain `node` process running the webpack Node build. Fourteen blocks (the form fields, blobs, scroll-accordion) pass only `metadata.name` to `registerBlockType` and depend on the `block.json` bootstrap; without it they are refused with "must have a title".

Loading the real registration files is also more faithful than a separate manifest: anything a block's `index.js` adds beyond `block.json` is included automatically.

### Builds

- **Node:** `build/engine/node.cjs` (`webpack.engine.config.js`) running on jsdom. Only plugin source is bundled and transpiled; `node_modules` packages are required at runtime from their CommonJS builds, the same modules Jest runs. Plugin source's `@wordpress/blocks` import is pointed at the copy nested under `@wordpress/block-editor`, so `useBlockProps.save()` and the parser share an instance (the problem `tests/unit/deprecations-isEligible.test.js` works around). `@wordpress/editor` and `@wordpress/notices` are stubbed exactly as the Jest config stubs them. Core blocks come from `@wordpress/block-library@9.8.18`, the package's `wp-6.7` dist-tag (a dev dependency; adding it left all 160 Jest suites passing). Exposed as `npm run engine -- <command>`; publishing to npm is out of scope. **Excluded from the plugin ZIP** via `.distignore` and the `files` list in `package.json`.
- **Browser:** `build/engine/browser.js`, a thin layer over the site's already-loaded `wp.blocks` registry. It ships in the plugin.

### What it replaces

`registerDesignSetGoBlock()` in `tools/regenerate-patterns.js` and the Jest-only registration in `deprecations-isEligible.test.js` and `ability-generated-markup.test.js` move onto the shared registry. There is one registration path.

## Surfaces

### Local agent (Node)

1. The agent writes `tree.json`.
2. `npm run engine -- assemble tree.json --lint --context design-context.json` writes markup to stdout, or `--json` for the full report. `design-context.json` is the output of the existing `designsetgo/get-design-context` ability.
3. Exit codes: `0` valid and no lint errors, `1` invalid markup or lint errors (or warnings beyond `--max-warnings`), `2` usage or I/O error.
4. The agent pushes the markup with WP-CLI or REST. Nothing server-side changes.

Also: `validate <markup-files…>` and `lint tree.json [--context <file>] [--json] [--max-warnings <n>] [--out <file>]` — the same flags `assemble --lint` accepts, run standalone against a tree with no markup produced. `fixture-cases` (no file argument) generates attribute-probe cases straight from the registry for the frozen-PHP-writer comparison (Test 8) rather than taking one.

### Editor assistant (browser)

`engine.assemble(tree)` returns real blocks; the assistant inserts them with `insertBlocks`. The report and lint findings appear in a sidebar panel. Nothing saves until the person saves.

### Remote agent (MCP)

**`designsetgo/build-page`** (requires `edit_post` on the target, or `edit_posts` plus the post type's create capability for a new post)

Input: `{ post_id?: int, new?: { title, post_type }, tree, mode: "replace" | "append" }` — exactly one of `post_id` or `new`. `new.post_type` is restricted to a registered, REST-visible (`show_in_rest`) post type that supports `editor` and is not `wp_`-prefixed (rules out site-structure types such as templates, template parts, navigation, and global styles) — see `Build_Page::is_buildable_post_type()`.

PHP validates structure only, and returns every problem as data (not `WP_Error`, which the MCP bridge flattens to "Ability execution failed."), each with a `path`:

| Code | Cause |
|---|---|
| `designsetgo_unsupported_tree_version` | `version` is not `1` |
| `designsetgo_unknown_block` | Name not in `WP_Block_Type_Registry` |
| `designsetgo_invalid_attribute` | Fails the registered attribute schema (`rest_validate_value_from_schema`) |
| `designsetgo_invalid_child_placement` | Rejected by `Block_Inserter::check_child_placement()` |
| `designsetgo_tree_too_large` | Serialized tree over 1 MB |

On success it stores, in protected post meta registered with an `auth_callback`:

- `_dsgo_pending_tree` — one JSON blob: `{ tree, mode, base }`, where `base` is the post's `post_modified_gmt` when stored. (Implementation note: `base` lives inside this same meta value rather than a separate `_dsgo_pending_base` key as an earlier draft of this doc specified — one read gives tree + mode + base atomically. See `Build_Store::META_PENDING_TREE`.)
- `_dsgo_build_report` — `{ status: "pending" }`

`post_content` is not touched. A live page keeps serving its current content.

Response: `{ status: "pending", post_id, finish_url }`. `finish_url` is the post's editor URL plus `&dsgo-finish=1`.

**Finishing (editor plugin)**

When the editor loads a post with `_dsgo_pending_tree`:

1. If `post_modified_gmt` differs from the `base` stored inside `_dsgo_pending_tree`, stop: report `conflict`, leave content alone.
2. Run `assemble` and `lint` with the site's design context.
3. If any block is invalid: report `failed` with the invalid paths. Do not change content.
4. If valid:
   - **Draft or new post:** apply (`replace` or `append`), save through REST, clear `_dsgo_pending_tree`, report `finished` or `finished_with_findings`.
   - **Published post:** apply to the editor **unsaved**, report `awaiting_review`, and show a "Review agent changes" notice with a **Discard** action. Saving clears the pending meta and reports `finished` or `finished_with_findings`; Discard clears it and reports `discarded`. Leaving without either keeps the tree pending, so it is applied again on the next editor load.
5. Set `data-dsgo-finish="done"` or `"failed"` on the document element so a headless browser knows when to stop waiting.

The finish runs on any editor load of that post, with or without `dsgo-finish=1`; the parameter only causes the status attribute to be set promptly for automated sessions.

Content is saved through the REST API, so `unfiltered_html` rules and KSES apply as they do for any editor save.

**`designsetgo/get-build-status`** — input `{ post_id }`, returns `_dsgo_build_report`: `pending | awaiting_review | finished | finished_with_findings | failed | conflict | discarded`, plus `invalid` and `findings`.

**Implementation note — GET response reshaping:** the REST GET on the pending-build route (consumed by the editor's finishing plugin, not by `get-build-status`) runs the stored tree through `Tree_Shape::to_response_shape()` before returning it. A block with no attributes is stored as PHP's empty array (`[]`, indistinguishable from an empty object once JSON-encoded), which the browser's strict tree-shape check rejects; `to_response_shape()` restores `{}` for any node's `attributes` value that is empty, so the contract the CLI/editor consume stays unambiguous. This was a product bug found and fixed during Task 21 (see the ledger).

**Authentication dependency:** a headless finish needs a logged-in session with `edit_post`. Site Designer supplies that session. DesignSetGo adds no new authentication path.

### Existing abilities

Every ability that writes markup through `Block_Inserter` (`add-block`, `add-child-block`, `add-accordion-item`, `add-tab`, `add-timeline-item`, and any configurator that re-serializes through it) keeps working. New blocks and attributes get no PHP mirror code. `list-abilities` and each of their descriptions point agents to `build-page`. Retirement is a separate decision once Site Designer finishes drafts in a browser.

## Design quality

### Guidance before building

Each block may have `src/blocks/{block}/agent.json`:

```json
{
	"whenToUse": "A full-width band of content with its own background.",
	"avoid": [ "Wrapping every section of a page in one outer section." ],
	"examples": [
		{
			"title": "Hero",
			"tree": {
				"version": 1,
				"blocks": [
					{
						"name": "designsetgo/section",
						"attributes": { "backgroundColor": "contrast", "textColor": "base" },
						"innerBlocks": [
							{ "name": "core/heading", "attributes": { "level": 1, "content": "Build faster" } },
							{ "name": "core/paragraph", "attributes": { "content": "A short supporting line." } }
						]
					}
				]
			}
		}
	]
}
```

`designsetgo/list-blocks` returns it alongside each attribute schema. The text is guidance for agents and is not translated. Every example must assemble valid and lint clean in CI.

### Lint rules

One rule per file in `src/engine/lint/rules/`, each with passing and failing fixtures. A rule receives the tree and the design context and returns findings. When a rule cannot resolve a value (for example a gradient or image background for `contrast`), it skips rather than guesses.

| Rule | Severity | Finding |
|---|---|---|
| `no-custom-html` | error | `core/html`, or inline SVG used for visuals |
| `image-alt` | error | Image with no alt text that isn't marked decorative |
| `contrast` | error | Resolved text/background preset pair below 4.5:1 |
| `heading-order` | error | Skipped heading level, or more than one h1 |
| `preset-values` | warning | Raw hex, px or rem where a theme preset exists; suggests the nearest preset slug |
| `prefer-dsgo-layout` | warning | `core/columns`, or `core/group` acting as a row, grid or section |
| `top-level-sections` | warning | One wrapper block around every section of the page |
| `mobile-layout` | warning | Grid or row with 3+ columns and no mobile column or stacking setting |
| `empty-container` | warning | Container block with no children |

### What findings block

| Surface | Invalid markup | Lint error | Lint warning |
|---|---|---|---|
| CLI | exit 1 | exit 1 | exit 1 only past `--max-warnings` |
| Editor assistant | not inserted | shown | shown |
| Remote | not saved (`failed`) | saved, reported | saved, reported |

Screenshot-based visual review stays in Site Designer.

## Testing

1. **Registry completeness** — every `src/blocks/*/block.json` and every extension that registers attributes or save props is registered by the manifest. Fails when a new one is missed.
2. **Round trip for every block** — defaults and non-default values (reusing `nonDefaultValue()` from `deprecations-isEligible.test.js`) assemble valid and survive `serialize → parse` without losing an attribute.
3. **Existing deprecation tests** run on the shared registry, unchanged in what they assert.
4. **Guidance examples** — every `agent.json` example assembles valid and lints clean.
5. **Lint rules** — each rule's passing and failing fixtures.
6. **Node and browser agree** — Playwright on wp-env assembles the same fixture trees in the editor; DesignSetGo blocks' markup must match the Node output byte for byte. Core blocks are excluded because the site may run a newer WordPress than 6.7.
7. **Remote flow end to end** — `build-page` over REST, open `finish_url`, then assert each outcome: `finished`, `finished_with_findings`, `failed`, `conflict`, and for a published post `awaiting_review` (left unsaved with the review notice), then `discarded` after Discard. PHPUnit covers each `build-page` rejection code.
8. **Frozen PHP writer** — a generated comparison in three steps: the Node engine writes attribute sets for each block from its schema to a fixture; PHPUnit (`abilities-generated-markup-fixture-test.php`) serializes each through `Block_Inserter`; `ability-generated-markup.test.js` asserts the engine finds every result valid.

## Phases

Each phase is independently shippable.

1. **Engine in Node** — registry, `assemble`, `validate`, CLI; move Jest helpers onto it; tests 1–3 and 8.
2. **Design quality** — lint rules, `agent.json` for the most-used blocks (section, row, grid, card, icon-button, accordion, tabs), `list-blocks` exposure; tests 4–5.
3. **Browser** — browser build, editor assistant panel; test 6.
4. **Remote** — `build-page`, `get-build-status`, finishing editor plugin, freeze notices on existing abilities; test 7.
5. **Retire the PHP writer** — separate decision, gated on Site Designer finishing drafts.

## Risks

| Risk | Mitigation |
|---|---|
| A `save()` behaves differently on jsdom than in a browser | Test 6 fails on any byte difference |
| A future block or extension's `index.js` touches an editor-only API at load time, so Node can't load it | Test 1 fails if any registration file throws on load; the registry reports the file rather than silently skipping it |
| Adding `@wordpress/block-library` bumps shared `@wordpress/*` packages and breaks Jest (see the package-skew history) | Verified at adoption: all 160 suites pass; the full suite runs on every task |
| Core block markup from Node differs from a newer site's core | Pinned to 6.7 (D7); remote writes finish on the site's own registry |
| Headless finish can't authenticate | Site Designer owns the session; without it, drafts wait for a person to open them |
| Pending tree goes stale while a person edits | `base` (inside `_dsgo_pending_tree`) conflict check |
| `contrast` false positives on complex backgrounds | Rules skip values they can't resolve |
| `designsetgo/section` with an explicit `style: {}` attribute assembles invalid (serialize vs. reparse differ) | Parked, out of scope for this plan (Task 21 ruling) — the engine reports `failed` rather than writing bad content; tracked as a follow-up in `src/blocks/section` |

## Out of scope

- HTML-to-block conversion
- Section recipes
- Screenshot or visual review (owned by Site Designer)
- Publishing the engine to npm
- Retiring the PHP writer (Phase 5 is its own decision)
