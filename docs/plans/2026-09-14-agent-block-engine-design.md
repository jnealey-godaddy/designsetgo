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
- `attributes` and `innerBlocks` are optional. A node accepts **no other keys**: `inner_blocks`, `block_name`, `attrs` and the like are rejected with `designsetgo_invalid_block_definition` (in both `checkTreeShape()` and PHP's `Tree_Shape`), never silently ignored.
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
- **`assemble(tree)`** → `{ markup, blocks, report }`. Builds via `createBlock` → `serialize`, re-parses the output, and requires every block to validate. A block type that is not registered is reported, not thrown. It also compares the parsed tree's structure (names and child counts) with the input and reports children a `save()` never rendered as `designsetgo_dropped_inner_blocks` at that node's path (e.g. `core/paragraph` given `innerBlocks`). A `save()` that throws is reported as `designsetgo_assemble_error` (`reason: "assemble failed: …"`, at the failing block's path when it can be located) — `assemble()` never throws. Top-level attributes whose value is an empty object (`style: {}`) are dropped before `createBlock`, so the block's default applies.
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
| `designsetgo_invalid_tree` | The tree is not an object with a `blocks` array |
| `designsetgo_unsupported_tree_version` | `version` is not `1` |
| `designsetgo_invalid_block_definition` | A node is not an object, has a malformed `name`, non-object `attributes`, non-array `innerBlocks`, or any key other than `name`/`attributes`/`innerBlocks` |
| `designsetgo_tree_too_large` | Serialized tree over 1 MB |
| `designsetgo_unknown_block` | Name not in `WP_Block_Type_Registry` |
| `designsetgo_invalid_attribute` | Fails the registered attribute schema (`rest_validate_value_from_schema`) |
| `designsetgo_invalid_child_placement` | Violates the block types' own `block.json` metadata in `WP_Block_Type_Registry`: a block with `parent` that is not a direct child of one of those; a block with `ancestor` with none of those above it; or a child not listed in its parent's `allowedBlocks`. Nothing else — any block may be given children here (see `designsetgo_dropped_inner_blocks`), and no PHP wrapper mirror is consulted. |
| `designsetgo_invalid_input` | `post_id`/`new` both or neither given, `mode` not `replace`/`append`, `new` not an object, or `new.post_type` not buildable |
| `designsetgo_invalid_post` | `post_id` does not exist or is not a buildable post type (same rule as `new.post_type`) |
| `designsetgo_post_create_failed` | Creating the `new` draft failed |

Codes that only the engine (browser or Node) or the REST handshake produces:

| Code | Where | Cause |
|---|---|---|
| `designsetgo_dropped_inner_blocks` | report `invalid` entry | Serialize → parse lost or changed submitted children (the block's `save()` cannot hold them) |
| `designsetgo_assemble_error` | report `invalid` entry | A block's `save()` (or `createBlock`/`parse`) threw |
| `designsetgo_build_mismatch` | REST `POST /agent-build/{id}`, HTTP 409 | The report's `buildId` does not match the pending build, or nothing is pending |

On success it stores, in protected post meta registered with an `auth_callback`:

- `_dsgo_pending_tree` — one JSON blob: `{ tree, mode, base, submitter, buildId }`, where `base` is the post's `post_modified_gmt` when stored, `submitter` is the id of the user who called `build-page`, and `buildId` is a fresh `wp_generate_uuid4()`. (Implementation note: `base` lives inside this same meta value rather than a separate `_dsgo_pending_base` key as an earlier draft of this doc specified — one read gives everything atomically. See `Build_Store::META_PENDING_TREE`.) Both meta values are written `wp_slash()`ed, because `update_post_meta()` unslashes and would otherwise strip the escapes `wp_json_encode()` adds for quotes, newlines, backslashes and non-ASCII.
- `_dsgo_build_report` — `{ status: "pending", buildId, treeHash }`

**KSES and the submitter.** The tree is saved later by whoever opens the editor, who may hold `unfiltered_html` when the submitter does not. So when the submitter lacks `unfiltered_html`, `build-page` runs `wp_kses_post()` over every string inside every node's `attributes` (nested arrays included) before storing — mirroring the attribute filtering core applies when such a user saves content themselves (`Tree_Kses`). A submitter with `unfiltered_html` has the tree stored unchanged.

`post_content` is not touched. A live page keeps serving its current content.

Response: `{ status: "pending", post_id, finish_url }`. `finish_url` is the post's editor URL plus `&dsgo-finish=1`.

**Finishing (editor plugin)**

When the editor loads a post with `_dsgo_pending_tree`:

The REST GET returns `{ pending, tree, mode, buildId, submitter, isSubmitter, conflict, postStatus, designContext }`; `isSubmitter` is whether the current user submitted the build. Every report POST must echo that `buildId` (a required arg): a POST whose `buildId` differs from the pending build's, or that arrives with nothing pending, is rejected with HTTP 409 `designsetgo_build_mismatch` and changes nothing. The JS `treeHash` is informational only; it is not expected to equal PHP's.

1. If `post_modified_gmt` differs from the `base` stored inside `_dsgo_pending_tree`, stop: report `conflict`, leave content alone. `conflict` is terminal — the tree is cleared.
2. Run `assemble` and `lint` with the site's design context.
3. If any block is invalid: report `failed` with the invalid paths. Do not change content. `failed` is terminal — the tree is cleared and the agent resubmits.
4. If valid:
   - **Auto-save** — only when `isSubmitter` is true **and** the post is not `publish`/`future`/`private`: apply (`replace` or `append`), save through REST, report `finished` or `finished_with_findings` (which clears `_dsgo_pending_tree`).
   - **Review** — every other case: a published/scheduled/private post, or a build submitted by another user. Apply to the editor **unsaved**, report `awaiting_review`, and show a non-dismissible review notice with a **Discard** action ("Review agent changes before updating." for the submitter; for someone else's build, a notice that an agent submitted the changes on behalf of another user and they must be reviewed before saving). Saving reports `finished` or `finished_with_findings`; Discard restores the previous blocks, reports `discarded`, and cancels the pending save report, so a later save reports nothing. Both clear the pending meta. Leaving without either keeps the tree pending (`awaiting_review` is the only non-terminal report), so it is applied again on the next editor load.
5. Set `data-dsgo-finish="done"` or `"failed"` on the document element so a headless browser knows when to stop waiting.

The finish runs on any editor load of that post, with or without `dsgo-finish=1`; the parameter only causes the status attribute to be set promptly for automated sessions. It runs only in the top-level window of an editor with a real post — a positive integer post id and a post type not prefixed `wp_` — so it never runs inside the canvas iframe, the Site Editor, or the widgets editor, and shows no notices there. If block registration never settles, it reports `failed` only when a build is actually pending. The Agent build sidebar likewise registers only in the top window.

An automatic save happens only in the submitter's own editor session, so it runs under the same capabilities that submitted the tree, and core's KSES applies to that save as usual. A build submitted by someone else is never saved automatically: its attribute strings were already KSES-filtered at `build-page` if the submitter lacked `unfiltered_html`, and the reviewer decides whether to save it.

**`designsetgo/get-build-status`** — input `{ post_id }`, returns `_dsgo_build_report`: `pending | awaiting_review | finished | finished_with_findings | failed | conflict | discarded`, plus `invalid`, `findings` and `buildId`. `finished`, `finished_with_findings`, `discarded`, `failed` and `conflict` are terminal (the pending tree is cleared, the report kept); `pending` and `awaiting_review` mean a tree is still pending.

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
7. **Remote flow end to end** — `build-page` over REST, open `finish_url`, then assert each outcome: `finished`, `finished_with_findings`, `failed`, `conflict`, and for a published post `awaiting_review` (left unsaved with the review notice), then `discarded` after Discard. Also: a paragraph with a link and non-ASCII text finishes with its content intact; a `core/list > core/list-item` tree is accepted and finished; a contributor's build opened by an administrator lands in `awaiting_review`, unsaved. PHPUnit covers each `build-page` rejection code, KSES filtering, the `buildId` 409 cases, and terminal statuses.
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
| An explicit empty-object attribute (`style: {}`) serializes markup that doesn't re-parse identically | `assemble()` drops top-level empty-object attributes before `createBlock`, so the block default applies |

## Out of scope

- HTML-to-block conversion
- Section recipes
- Screenshot or visual review (owned by Site Designer)
- Publishing the engine to npm
- Retiring the PHP writer (Phase 5 is its own decision)
