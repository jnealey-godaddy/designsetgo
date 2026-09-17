# Abilities ↔ Blocks Drift Prevention — Spec

**Date:** 2026-09-17
**Status:** Spec. One implementation plan derives from this document, in four units.
**Depends on:** PR #565 (`fix/inserter-form-colors-mobile-order`). This branch is stacked on
its head, not on `main`. Rebase once #565 squash-merges.

## Context

PR #565 fixed eleven separate cases of the same bug. The Abilities API writes block markup
from PHP; every block's `save()` writes the same markup from JavaScript; nothing forced the
two to agree. Each divergence surfaced the same way — an author opened a generated page and
saw "This block contains unexpected or invalid content."

The fixes were correct. The shape of the problem is not addressed by them, because the
problem is structural: **three places in the abilities layer restate by hand something that
already has a machine-readable source of truth, and nothing asserts the restatement is
faithful.**

| # | Restatement | Source of truth | Guard today |
|---|---|---|---|
| 1 | `Block_Inserter`'s PHP mirror of every `save()` | `src/blocks/{slug}/save.js` | Partial — per block, not per attribute |
| 2 | `Block_Configurator`'s rich-text attribute allow-lists | `source: "html"\|"text"` in `block.json` | None |
| 3 | `Configure_Shape_Divider::VALID_SHAPES` | `src/blocks/section/utils/shape-dividers.js` | None |

This spec closes all three, then splits the 6,286-line file that surface 1 lives in.

### What already works, and must not be broken

The existing harness is sound and this spec extends rather than replaces it:

- `Abilities_Generated_Markup_Fixture_Test::default_payloads()` enumerates
  `WP_Block_Type_Registry` rather than a hand-written list, so **a newly registered block is
  covered the day it registers**.
- `Block_Inserter::get_serialization_gap()` **fails closed**: a block with no mirror is
  refused at insert time rather than silently serialized wrong.
- The PHP fixture test and `tests/unit/ability-generated-markup.test.js` form a two-sided
  loop. PHP cannot run `save()`; JavaScript cannot run the PHP serializer; the committed
  fixture is where they meet. The PHP half fails when *PHP output* drifts from the
  committed fixture; the JS half fails when *`save()`* drifts from it. **Both halves are
  load-bearing and neither substitutes for the other.**
- Extension attributes (`dsgoVisibility`, animations, grid-span, style bindings) are
  injected into the registry via `register_block_type_args`
  (`includes/features/class-extension-attributes.php:52`), so enumerating
  `WP_Block_Type_Registry` covers block.json attributes **and** extension attributes in one
  pass.

### The hole

Coverage is **per block, not per attribute**. The fixture holds 69 auto-enumerated payloads
at *default values* plus ~75 hand-authored scenarios. There are **777 block.json attributes
across 71 blocks** (407 string, 126 boolean, 123 number, 88 enum, 19 object, 14 array),
before extension attributes.

Add an attribute that changes `save()` and nothing goes red: the defaults payload does not
move, and no scenario exists until someone writes one. Every fix in #565 has that shape.

### Scope

In scope: the three surfaces above, plus the split of `class-block-inserter.php`.

Out of scope, named so the boundary is defensible later:

- Making any block dynamic to avoid having a `save()` to mirror.
- Moving serializers into `src/blocks/{slug}/`. Rejected: PHP under `src/blocks/` must be
  added to the webpack copy patterns or it is silently inert at runtime, a failure mode
  CLAUDE.md already documents for the query block. Agent-time code stays in `includes/`.
- Changing runtime behaviour when the inserter meets an attribute it cannot serialize. It
  continues to insert. **CI is the net, not a runtime refusal** — a generation must not
  start failing in production because a newly added attribute has not yet been mirrored.

---

## D0 — Decisions

**D0.1 The PHP mirror is permanent.** site-designer-api calls the abilities on a customer's
site; there is no Node runtime there to run the real `save()`. The design assumes ~71
hand-written mirrors forever and makes drift impossible to merge rather than impossible to
write.

**D0.2 CI blocks the merge; runtime inserts anyway.** An undeclared attribute is a red
build, never a refused insert.

**D0.3 Probes prove; declarations only scope.** Where a value can be generated, correctness
is proved by round-trip. A declaration exists only to supply values that cannot be guessed,
or to record why an attribute is exempt — and every declaration is key-diffed against the
live registry in both directions so it cannot rot.

**D0.4 A rejected probe is a failure, not a skip.** If a generated probe value is refused by
the inserter's own validators (`find_invalid_attribute_values()`, `columnTemplate`'s
`;{}<>` refusal), the answer is a declared probe. Silently dropping the payload would leave
an attribute uncovered while the suite reported green.

**D0.5 The matrix proves one attribute at a time, not combinations.** Attributes that only
affect `save()` in concert with another (hover colours while hover is off) will round-trip
without exercising anything. That is an uncovered case, not a false pass. Combinations
remain the job of the hand-authored scenarios, which are kept.

---

## Unit 1 — Attribute coverage matrix

**Goal:** every attribute in the registry, flipped alone, provably round-trips.

### Generation

Base payload is the block's defaults. Flip exactly one attribute, so a failure names one
attribute. Probe values derive from the attribute's declared type:

| Kind | Probe | Approx. count |
|---|---|---|
| `boolean` | flipped default | 126 |
| `enum` | **every member** — this is exactly where restatement drift lives | 88 attrs → ~250 payloads |
| `number` / `integer` | default ±1, clamped to declared `minimum`/`maximum` | 123 |
| `string`, name matches `/color/i` | `#ff0000`, plus a second payload with `var:preset\|color\|contrast` | — |
| `string`, name matches `/url\|href\|src/i` | `https://example.com/probe` | — |
| `string`, name matches `/width\|height\|size\|gap\|radius\|spacing/i` | `2rem` | — |
| `string`, name matches `/text\|label\|title\|content\|message\|caption/i` | `Probe` | — |
| anything else — `object`, `array`, unguessable `string` | **no guess; red until declared** | 33 object/array, plus strings the heuristics miss |

A probe value need not be realistic. The assertion is *parity*, not sensibility: an
unrealistic value that both sides treat identically still proves the mirror. It must only be
a value the inserter accepts (D0.4).

### The probe table

`tests/fixtures/attribute-probes.json`, one entry per attribute the generator cannot derive:

```json
{
  "designsetgo/grid": {
    "columnTemplate": { "probe": "minmax(0, .7fr) minmax(0, 1.3fr)" },
    "someServerOnlyAttr": { "skip": "no save() effect — read only by render.php" }
  }
}
```

A test diffs the table's keys against the live registry **in both directions**: an attribute
with no probe and no skip is red; a stale entry for a deleted attribute is red. That is what
stops the declaration rotting, and it is why the table is allowed to exist at all.

### Assertion

The generated payloads go through the existing PHP → fixture → JS loop, asserting
`isValid` with the real block registrations.

### Files

- Create: `tests/phpunit/abilities-attribute-matrix-fixture-test.php`
- Create: `tests/unit/ability-attribute-matrix.test.js`
- Create: `tests/fixtures/attribute-probes.json`
- Create: `tests/unit/__fixtures__/ability-attribute-matrix.json` (generated, ~1,000 entries)
- Modify: `package.json` — add `fixtures:update`

The matrix fixture is kept **separate** from `ability-generated-markup.json` so the curated,
human-reviewable 144 entries stay human-reviewable.

---

## Unit 2 — Rich-text fidelity

**Goal:** inline markup an agent supplies survives into stored content, per a declared
policy, for every rich-text attribute.

### Why `isValid` cannot catch this

For a `source: "html"` attribute the value lives **in the markup, not the block comment**.
If PHP strips `<em>` before writing, the parser reads the attribute back as `probe`,
`save()` of `probe` is `probe`, and the block is **perfectly valid**.

This is not a gap in Unit 1's matrix that better probes would close — it is structural.
`Care <em>begins</em>` shipped as `Care begins` and no validity check was ever going to see
it. This surface needs a **fidelity** assertion, not a validity one.

### Current state

13 attributes across 10 blocks carry `source: "html"|"text"`:

| Block | Attributes | Today |
|---|---|---|
| icon-button, modal-trigger | `text` | ✅ narrow label allow-list (#565) |
| heading-segment | `content` | ✅ generic inline path |
| accordion-item | `title` | ❌ `esc_html` by the inserter |
| card | `title`, `subtitle`, `bodyText`, `badgeText` | ❌ plain text |
| timeline-item | `date`, `title` | ❌ plain text |
| counter | `label` | ❌ plain text |
| countdown-timer | `completionMessage` | ❌ plain text |
| form-builder | `submitButtonText` | ❌ plain text |
| table-of-contents | `titleText` | ❌ plain text |

`Block_Configurator::is_inline_markup_attribute()` hardcodes `content|caption|citation`;
`BUTTON_LABEL_ATTRIBUTES` covers two blocks. Everything else falls to
`sanitize_text_field()`.

### Design

A policy table, `Block_Configurator::RICH_TEXT_ATTRIBUTES`, mapping block → attribute → one
of:

- `inline` — the full inline allow-list (links, spans, `em`/`strong`/`mark`/`code`)
- `label` — the narrow allow-list for text inside `<a>`/`<button>`: `strong`, `em`, `b`,
  `i`, `br`, no attributes
- `plain` — `sanitize_text_field()`, because `save()` escapes it. **Correct for
  accordion-item `title` as things stand**: the inserter writes it with `esc_html`, so
  moving it to an inline policy without changing the inserter would break parity.

Two tests:

1. **Key diff** — the table covers exactly the registry's `source: html|text` set, both
   directions.
2. **Fidelity** — insert with a markup-bearing value, re-parse the stored blocks in PHP,
   assert the inline markup survived as the declared policy says. A `plain` policy asserts
   the tags are gone; `inline` and `label` assert their allow-list survived and everything
   outside it did not.

The eight attributes currently wrong become eight red tests. Fixing them means moving the
policy **and** the inserter's emission together — the fidelity test fails if only one moves.

### Files

- Modify: `includes/abilities/class-block-configurator.php`
- Modify: `includes/abilities/class-block-inserter.php` (emission for reclassified attrs)
- Create: `tests/phpunit/abilities-rich-text-fidelity-test.php`

---

## Unit 3 — Shape divider enum

**Goal:** one list, not two.

`VALID_SHAPES` holds 29 shapes; `shape-dividers.js` holds 29. They agree today. Nothing
makes them agree tomorrow, and a shape added to the JS is **valid input the ability
rejects** before the callback runs.

A sweep of every multi-member list in `includes/abilities/` and `includes/extension-configs/`
found this is the **only** remaining restatement of a JS list. `list-dynamic-tag-sources`
and `list-blocks` already read the live registry — the lesson has been learned once and is
documented in the former's own comments. The other enums are genuinely closed sets
(`top|bottom|both`, `_self|_blank`, `summary|full`).

### Design

A build step reads `src/blocks/section/utils/shape-dividers.js` and writes a **committed**
`includes/abilities/generated/shape-dividers.php` returning the array. `VALID_SHAPES`
becomes a read of that file.

Generated-and-committed, not read from `build/` at runtime: a missing or stale build
artifact would silently empty the schema's enum, which is the same silent-inertness failure
CLAUDE.md documents for query PHP. CI asserts freshness by regenerating and running
`git diff --exit-code`.

### Files

- Create: `tools/generate-shape-divider-enum.js`
- Create: `includes/abilities/generated/shape-dividers.php` (generated, committed)
- Modify: `includes/abilities/configurators/class-configure-shape-divider.php`
- Modify: `package.json`, `.github/workflows/ci.yml`

---

## Unit 4 — Split `class-block-inserter.php`

**Goal:** make the mirror maintainable, now that the net from Unit 1 exists.

**Order matters.** The split lands last. Today's 144-entry fixture would prove a
2,800-line mechanical move preserved 144 behaviours; the ~1,000-entry matrix proves it
preserved all of them. Cutting the file open before the net exists gets the sequencing
backwards.

### Structure

```
includes/abilities/serializers/
  class-serializer-registry.php     block name → serializer class; replaces the switch
  class-serializer-support.php      the ~40 shared helpers, promoted from private
  class-section-serializer.php      one file per block slug, 1:1 with src/blocks/{slug}/
  class-icon-button-serializer.php
  …
```

Each serializer exposes one method, `wrapper( array $attributes ): ?array`, returning the
`['opening', 'closing']` pair every `switch` case already returns. The seam is clean because
the existing cases already share that contract.

Three consequences worth stating:

- **The registry replaces `null` as the gap signal.** `generate_designsetgo_wrapper_html()`
  currently returns `null` to mean "no mirror", which `get_serialization_gap()` reads. A
  missing registry entry carries the same meaning, but is enumerable — so a test can assert
  every registered non-dynamic `designsetgo/*` block has a serializer or a documented gap,
  and that no serializer exists without a block.
- **One file per block, not per family.** ~55 files, most tiny (heading-segment is 25 lines).
  Family grouping reintroduces "where does this one live?" and defeats the mapping test.
  Fall-through cases (`flip-card-front`/`back`/`face`, `query`/`query-results`) map several
  names to one class.
- **Behaviour preservation is proved, not argued.** Both fixtures must regenerate
  **byte-identical** across the split. That is the acceptance criterion for this unit.

The ~40 currently-private shared helpers (`convert_color_value_to_css_var()`,
`has_overlay()`, `render_shape_divider()`, `numeric_attribute()`, …) move to
`Serializer_Support` as public statics. `Block_Inserter` keeps insert orchestration,
definition validation, block-support plumbing and the diagnostics API.

### Files

- Create: `includes/abilities/serializers/*.php` (~57 files)
- Modify: `includes/abilities/class-block-inserter.php` (6,286 → ~2,400 lines)
- Create: `tests/phpunit/abilities-serializer-registry-test.php`

---

## CI

Everything slots into the two jobs `.github/workflows/ci.yml` already runs — jest at line
129, phpunit at line 326. No new jobs. One addition: the shape-divider freshness check in
the lint job.

`npm run fixtures:update` wraps `DSGO_UPDATE_FIXTURES=1 … phpunit` so regenerating is one
command rather than a remembered environment variable.

## Acceptance

1. Adding an attribute to any `block.json` without a probe or a documented skip fails CI.
2. Adding an attribute whose `save()` effect the PHP mirror does not reproduce fails CI.
3. Adding a `source: "html"` attribute without a rich-text policy fails CI.
4. Adding a shape to `shape-dividers.js` without regenerating fails CI.
5. Unit 4 leaves both fixtures byte-identical.
6. Runtime insert behaviour is unchanged throughout (D0.2).

## Known limits

- One attribute at a time, not combinations (D0.5). Hand-authored scenarios remain the only
  coverage for interacting attributes, and remain hand-authored.
- The probe table's `skip` entries are a place a wrong judgement can hide. The key diff
  forces a `skip` to be *written*; it cannot force it to be *right*. Each one carries a
  reason for exactly that reason.
- Unit 4 is a large mechanical diff. The byte-identical fixture requirement makes it safe to
  merge but does not make it pleasant to review; it should land as its own PR.
