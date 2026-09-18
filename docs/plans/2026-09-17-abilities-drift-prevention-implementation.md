# Abilities Drift Prevention Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make it impossible to merge a change that drifts the abilities layer's PHP
restatements away from the block definitions they mirror.

**Architecture:** Three hand-maintained restatements get a CI guard that diffs them against
their machine-readable source of truth. The `save()` mirror is proved by probing every
registry attribute and round-tripping it through the existing PHP → fixture → JS loop; the
rich-text allow-lists get a fidelity assertion (validity cannot see this class of bug); the
shape enum becomes generated. Only then is the 6,286-line mirror split into per-block
serializers, with byte-identical fixtures as the proof the move changed nothing.

**Tech Stack:** PHP 7.4+, PHPUnit via wp-env, Jest/jsdom, `@wordpress/blocks`,
`WP_Block_Type_Registry`, GitHub Actions.

**Spec:** [2026-09-17-abilities-drift-prevention.md](2026-09-17-abilities-drift-prevention.md)

---

## Environment notes (read first)

The PHP toolchain is not on the default PATH in this worktree. Every PHP command needs:

```bash
export PATH="/opt/homebrew/bin:/usr/local/bin:/Applications/Docker.app/Contents/Resources/bin:$PATH"
```

Composer is not installed globally; it lives at `/tmp/composer` and is invoked as
`php /tmp/composer`. `vendor/` is already installed in this worktree.

Run PHPUnit through this worktree's own wp-env instance. Two worktree-specific gotchas:

- wp-env mounts the plugin under the WORKTREE's directory name, not `designsetgo`.
- Ports 9451/9461/9471/9481 are taken by other worktrees' instances, so this one pins
  9491/9492 in `.wp-env.override.json` (gitignored).
- Blocks register from `build/`, so `npm run build` must have run or the registry is empty
  and every registry-driven test passes vacuously.

```bash
npx wp-env run tests-cli \
  --env-cwd=wp-content/plugins/claude+abilities-drift-prevention vendor/bin/phpunit
```

`wp-env run` has no `--env` flag, so fixture regeneration goes through docker directly:

```bash
docker exec -e DSGO_UPDATE_FIXTURES=1 \
  -w /var/www/html/wp-content/plugins/claude+abilities-drift-prevention \
  <tests-cli container> vendor/bin/phpunit --filter Abilities_Attribute_Matrix_Fixture
```

Regenerate fixtures with `DSGO_UPDATE_FIXTURES=1` prefixed to the phpunit command
(Task 5 adds `npm run fixtures:update` as a wrapper).

---

## Unit 1 — Attribute coverage matrix

### Task 1: Probe table and its two-way key diff

**Files:**
- Create: `tests/fixtures/attribute-probes.json`
- Create: `tests/phpunit/abilities-attribute-probe-table-test.php`

**Step 1: Write the failing test.** It enumerates every `designsetgo/*` attribute in
`WP_Block_Type_Registry` (this covers extension attributes too — they are injected via
`register_block_type_args`), asks the probe generator for a value, and requires that
every attribute is either auto-derivable or present in the table.

```php
public function test_every_registry_attribute_is_probeable_or_declared() {
    $table   = json_decode( file_get_contents( $this->table_path() ), true );
    $missing = array();

    foreach ( $this->registry_attributes() as $block => $attrs ) {
        foreach ( $attrs as $attr => $definition ) {
            if ( null !== Attribute_Probe_Generator::derive( $definition ) ) {
                continue;
            }
            if ( isset( $table[ $block ][ $attr ] ) ) {
                continue;
            }
            $missing[] = $block . '::' . $attr;
        }
    }

    $this->assertSame( array(), $missing, 'Undeclared attributes: no probe and no skip.' );
}
```

**Step 2: The reverse diff, in the same file.** A table entry naming an attribute the
registry no longer has must fail. This is what stops the declaration rotting.

```php
public function test_no_stale_probe_table_entries() { /* symmetric assertion */ }
```

**Step 3: Run both.** Expected: FATAL, `Attribute_Probe_Generator` not found. That is the
correct first failure.

```bash
npx wp-env run tests-cli --env-cwd=wp-content/plugins/designsetgo \
  vendor/bin/phpunit --filter Abilities_Attribute_Probe_Table
```

**Step 4: Commit the red test.** Do not implement yet.

---

### Task 2: Probe value derivation

**Files:**
- Create: `tests/phpunit/helpers/class-attribute-probe-generator.php`
- Test: `tests/phpunit/abilities-attribute-probe-generator-test.php`

Test-support code, not shipped code — it lives under `tests/`, so it is excluded from the
plugin build and from Plugin Check.

**Step 1: Write unit tests for `derive()`** covering each row of the spec's table: a boolean
flips its default; an enum yields every member; a number respects declared
`minimum`/`maximum`; a string matching `/color/i` yields `#ff0000` *and* a preset probe; an
`object` yields `null` (meaning "must be declared").

**Step 2: Run.** Expected: FAIL, class not found.

**Step 3: Implement `derive( array $definition ): ?array`** returning a list of probe values
or `null`. Name heuristics run only for `type: string` with no `enum`:

| Pattern | Value |
|---|---|
| `/color/i` | `#ff0000`, then `var:preset\|color\|contrast` |
| `/url\|href\|src/i` | `https://example.com/probe` |
| `/width\|height\|size\|gap\|radius\|spacing/i` | `2rem` |
| `/text\|label\|title\|content\|message\|caption/i` | `Probe` |
| anything else | `null` |

**Step 4: Run.** Expected: PASS.

**Step 5: Commit.**

---

### Task 3: Generate the matrix fixture

**Files:**
- Create: `tests/phpunit/abilities-attribute-matrix-fixture-test.php`
- Create: `tests/unit/__fixtures__/ability-attribute-matrix.json` (generated)

Model this file on `abilities-generated-markup-fixture-test.php` — reuse its
`stabilise_ids()` approach verbatim (blocks seed UUIDs on insert, so without it the fixture
can never match twice) and its `DSGO_UPDATE_FIXTURES=1` regeneration contract.

**Step 1:** Build payloads: start from block defaults, flip exactly one attribute to one
probe value, label as `<block>::<attribute>::<index>`. Skip blocks where
`Block_Inserter::get_serialization_gap()` is non-null, and skip the two WooCommerce-gated
blocks, exactly as `default_payloads()` does — otherwise the fixture depends on which
plugins the environment has.

**Step 2: D0.4 — a rejected probe must fail, not skip.** Before serializing, run the payload
through `Block_Inserter::find_invalid_attribute_values()`. If it is refused, collect it and
fail the test naming the attribute. Do **not** drop it silently.

**Step 3:** Run with `DSGO_UPDATE_FIXTURES=1` to write the fixture. Inspect the entry count
(expect roughly 1,000) and spot-check three entries by hand.

**Step 4: Commit** the test and the generated fixture together.

---

### Task 4: Validate the matrix against real `save()`

**Files:**
- Create: `tests/unit/ability-attribute-matrix.test.js`

**Step 1:** Copy the structure of `tests/unit/ability-generated-markup.test.js`. Import
`parse` from `@wordpress/block-editor/node_modules/@wordpress/blocks` — **not** the
top-level `@wordpress/blocks` copy. That is not a style preference: it must be the same
instance `useBlockProps.save()` talks to, and the existing tests document why.

**Step 2:** Register every referenced block via `registerDesignSetGoBlock` from
`tools/regenerate-patterns`, then assert `collectInvalid(parse(markup))` is empty for every
payload, reporting `label` + attribute in the failure message.

**Step 3: Run.** `npx jest tests/unit/ability-attribute-matrix.test.js`

Expected: **failures.** This is the point of the exercise — each failure is real drift the
old per-block coverage could not see. Record the list before fixing anything.

**Step 4: Commit** the test plus the recorded failure list in the commit message.

---

### Task 5: Triage the matrix failures — DONE

**Baseline 375 failures across 46 blocks; now 0, at 4,068 payloads.**

Two harness faults had to be fixed before the list meant anything:

1. `tools/regenerate-patterns` imports ONLY `block-animations`, deliberately and with a
   comment saying so. Without the other 16 save-affecting extensions, `save()` in the JS
   test could not emit their props while the PHP mirror did — 138 phantom failures.
   `ability-attribute-matrix.test.js` now imports all 17.
2. `textColor` / `backgroundColor` / `borderColor` / `fontSize` are core's preset-SLUG
   attributes; the `var:preset|…` shorthand belongs in `style.color.*`. 102 phantom
   failures from a bad probe.

A third fault was in the probe table's own format: one key, `probe`, meant either a single
value or a list of alternatives depending on the value's *shape*, which is unresolvable for
an array-valued attribute. It guessed wrong for every one of them — comparison-table's
`columns` was set to an object where the block expects an array. Now `probe` is one value
and `probes` is a list.

**What the matrix actually found.** Every item below was live drift in shipped code:

| Area | Fault |
|---|---|
| 5 universal extensions | `get_extension_save_props()` reproduced none of responsive hide, custom CSS, reveal-on-hover, background video, clickable-group; max-width only on 3 of ~70 blocks |
| Alignment | 12 blocks declare `supports.align` and never called `align_class()` |
| extraProps vs block props | Modal's custom class, extension classes and alignment went to its content div instead of the root |
| Preset shorthand | Several serializers wrote `var:preset\|…` into markup — not just invalid blocks, but **unparseable CSS**, so those colours never rendered |
| counter-group `gap` | `intval() . 'px'` reproduced the `"32px"` default exactly and turned `2rem` into `2px` |
| counter-group `alignContent` | Read an `alignment` attribute the block has never declared |
| table-of-contents | Wrote no style attribute at all |
| icon-button / modal-trigger | `align: full` wrongly implied `fullWidth`; `hoverAnimation` interpolated instead of mapped |
| progress-bar | No striped background, no `--animated` modifier, label text dropped |
| form-builder | No border radius, no redirect URL, no submit hover colours; `hasFields: false` emitted markup where `save()` returns null |
| modal | Close-button colours and label lost; content height never written; border hardcoded |
| card | No image element at all |
| icon-list | Missing `data-dsgo-icon-style`, so markup matched an **older deprecation** and migrated silently on open |

That last one is the subtlest: the block was *valid*, so no validity check would ever have
caught it. It surfaced only because the matrix suite fails on an unexpected `console.info`,
which is what WordPress logs when a deprecation migrates a block.

**Two faults are recorded but deliberately not fixed here**, because both change stored
markup and need deprecations:

- `counter`'s `save()` writes a preset shorthand into a CSS custom property, so that colour
  never applies on the front end. The mirror reproduces it faithfully, bug included.
- `counter`'s `showIcon` and `advanced-heading`'s `animatedHeadline` inline SVG from a
  JavaScript icon library with no PHP equivalent. Both are refused at insert time rather
  than approximated.

---

## Unit 2 — Rich-text fidelity

### Task 6: Policy table and key diff

**Files:**
- Modify: `includes/abilities/class-block-configurator.php`
- Create: `tests/phpunit/abilities-rich-text-policy-test.php`

**Step 1:** Add `RICH_TEXT_ATTRIBUTES`, block → attribute → `'inline'|'label'|'plain'`. Seed
it with **today's actual behaviour**, not the desired behaviour: icon-button/modal-trigger
`text` → `label`, heading-segment `content` → `inline`, everything else → `plain`.

**Step 2:** Write the two-way key diff against every registry attribute carrying
`source: "html"` or `source: "text"`. Run it; it should pass, because the table was seeded
from reality.

**Step 3: Commit.**

---

### Task 7: The fidelity assertion

**Files:**
- Create: `tests/phpunit/abilities-rich-text-fidelity-test.php`

**Why this test and not `isValid`:** for a `source: "html"` attribute the value lives in the
markup, not the block comment. If PHP strips `<em>` before writing, the parser reads the
attribute back stripped, `save()` of the stripped value reproduces it exactly, and the block
is **valid**. Validity is structurally blind here. Assert fidelity instead.

**Step 1:** For every attribute in `RICH_TEXT_ATTRIBUTES`, insert a block with
`Care <em>begins</em> <a href="https://example.com">here</a>`, re-parse the stored post
content with `parse_blocks()`, and assert per policy:

- `inline` — `<em>` and `<a href>` both survive
- `label` — `<em>` survives, `<a>` does not
- `plain` — no tags survive

**Step 2: Run.** Expected: PASS for all thirteen, because the table was seeded from actual
behaviour. The test documents the status quo before changing it.

**Step 3: Commit.**

---

### Task 8: Reclassify the eight wrong attributes

**Files:**
- Modify: `includes/abilities/class-block-configurator.php`
- Modify: `includes/abilities/class-block-inserter.php`

Now flip the policy to what it *should* be and watch the test go red, one attribute at a
time. Per the spec's table: card `title`/`subtitle`/`bodyText`, timeline-item `date`/`title`
move to `inline`; accordion-item `title` moves only if the inserter stops writing it with
`esc_html`.

**Order per attribute — do not batch:**

1. Change the policy entry. Run the fidelity test. Expect FAIL.
2. Change the inserter's emission for that attribute (`wp_kses_post` with the matching
   allow-list rather than `esc_html`/`sanitize_text_field`).
3. Run the fidelity test. Expect PASS.
4. Run the Unit 1 matrix and the existing generated-markup suite. **Both must stay green** —
   a policy change that alters stored markup is a `save()` parity change too.
5. Regenerate fixtures if markup legitimately changed, inspect the diff, commit.

`badgeText`, `label`, `completionMessage`, `submitButtonText` and `titleText` are
`source: "text"`, not `"html"`. Check each block's `save()` before moving it: if `save()`
renders it as plain text, `plain` is correct and it should stay.

---

## Unit 3 — Shape divider enum

### Task 9: Generate the list

**Files:**
- Create: `tools/generate-shape-divider-enum.js`
- Create: `includes/abilities/generated/shape-dividers.php`
- Modify: `package.json`, `.github/workflows/ci.yml`

**Step 1:** Script reads `src/blocks/section/utils/shape-dividers.js`, extracts the 29
`value:` entries, writes a PHP file returning the array with a generated-file header.

**Step 2:** Add `npm run generate:shape-enum`; wire a CI freshness step that regenerates and
runs `git diff --exit-code`.

**Step 3: Commit.**

### Task 10: Point the ability at it

**Files:**
- Modify: `includes/abilities/configurators/class-configure-shape-divider.php`
- Create: `tests/phpunit/abilities-shape-enum-test.php`

`VALID_SHAPES` becomes a read of the generated file. Test asserts the PHP list equals the JS
list (parse the JS in the test) so the two can never diverge even if the generator breaks.

Run the full PHP suite. Commit.

---

## Unit 4 — Split `class-block-inserter.php` — DONE

**6,902 → 2,688 lines.** The largest remaining serializer is modal at 263.

Done in two moves rather than the planned four tasks, because dependency analysis showed
both sets were cleanly separable:

**Serializer_Support** — 29 pure helpers (JavaScript-compatible number formatting, colour
conversion, the shape-divider renderer, overlay and hover maths, alignment and padding
routing). The set called nothing that stayed behind and used no shared constants, so it
moved as one piece. `Block_Inserter` keeps a delegator for `overlay_opacity_for_color()`:
that name is a cross-runtime contract, cited from `src/utils/overlay-opacity.js`, from
`tests/fixtures/overlay-opacity-cases.json` and from its own test.

**50 serializers + Serializer_Registry** — the 3,111-line switch. Across all fifty cases
the bodies called exactly **two** Block_Inserter methods, used no class constants and had
no case-level `break`, which is why this could be done in one pass instead of batches of
five. `get_routed_visual_attributes()` joined Serializer_Support;
`generate_form_builder_html()` moved into the form-builder serializer, its only caller.

The registry's real value is that it is **enumerable**. `get_serialization_gap()` used to
answer "can this block be inserted?" by running the switch and checking for null, so the
set of serializable blocks could only be discovered one block at a time.
`Abilities_Serializer_Registry_Test` now asserts things about the set as a whole.

### Acceptance — met

Both fixtures regenerate **byte-identical**, pinning 4,068 attribute-matrix payloads plus
the 144 curated ones through a 3,111-line move. This is the reason the matrix was built
first: against the old 144-entry fixture the same move would have demonstrated a fraction
as much.

PHPUnit 1,638 tests / 6,533 assertions · Jest 163 suites / 3,571 tests · `composer lint`
clean · PHPStan clean.

Two things the split surfaced that were invisible before, both now fixed: a redundant `??`
on a guaranteed array offset (PHPStan only sees it once the code is in a file it analyses
under the new shape), and `block-inserter-form-variation-test` reflecting into
`generate_form_builder_html()` — still private, now on `FormBuilder_Serializer`.
