# Manual QA Testing Guide — Pre-Release Validation

**Purpose:** Validate every shipped change in the current `[Unreleased]` window before publishing **v2.1.0**.

**Scope:** Covers everything merged since the last public release (**v2.0.49**, 2026-04-12) — PRs #342 through #382. Work through section by section; each has a focused setup, the steps to run, and the pass criteria.

**Last updated:** 2026-04-24 · **Target release:** 2.1.0 · **Last released:** 2.0.49

---

## How to Use This Guide

1. Start from a clean `wp-env` instance: `npm run build && npx wp-env start --update`.
2. Activate the DesignSetGo plugin and a core block theme (Twenty Twenty-Five is the baseline). Also run one pass on a classic theme if you test public-facing output.
3. For every section:
   - Run the **Setup** once.
   - Walk through **Steps** in order.
   - Tick each **Pass criteria** box. Record failures with the PR number below.
4. Run the **Smoke tests** (bottom of the doc) against staging after deploy, before flipping the release live.

**Browsers:** Chrome + Safari minimum. Firefox for keyboard / a11y passes.
**Viewports:** 1440 (desktop), 768 (tablet), 375 (mobile).

---

## 1. Dynamic Query Block Family (highest-risk area)

The Query block family saw five releases (v1 → v2.5) plus a restructure and an onboarding redesign. Test in this order — later features assume earlier ones still work.

### 1.1 Query v1 — baseline (PR #364)

**Setup:** Create a page. Insert **Dynamic Query** block (not core `core/query`).

**Steps:**
1. Pick the default template via the onboarding picker (PR #378 / #380 flow).
2. Add a **Query Pagination** sibling (numbered + load-more variations).
3. Add a **Query Filter** sibling — test the `checkbox`, `select`, `search`, `sort`, `active`, and `reset` variations.
4. Add a **Query No Results** sibling, change the query to match nothing, confirm it renders.
5. Bind a field in an inner block via **DSGo Post Meta** and **DSGo ACF** sources (ACF only visible if the ACF plugin is active).
6. View the page on the frontend. Trigger load-more, run a filter, sort, reset.

**Pass criteria:**
- [ ] Sibling blocks bind correctly (same `queryId` context, no "wrong query" warnings).
- [ ] First paint HTML matches the REST-rendered HTML byte-for-byte (view-source before and after a filter apply — inner markup identical).
- [ ] `[data-dsgo-query-id]` wrapper + `[data-dsgo-blobs-for]` sibling present on the frontend.
- [ ] URL updates with `q`, `sort`, `filter_<taxonomy>` params; back/forward buttons restore state.

### 1.2 Query v2.2 — facets, infinite scroll, live preview (PR #365)

**Setup:** Create a Query with at least 15 matching posts and 2 taxonomies.

**Steps:**
1. Toggle **Show counts** on each filter variation — confirm counts match actual match totals.
2. Switch Query Pagination to **Infinite scroll** variation. Scroll and verify it auto-pauses after `autoPauseAfter` (default 3) and reveals a button.
3. Enable "Prefers reduced motion" in the OS — confirm infinite scroll falls back to the button only.
4. In the editor, change the query source to **Users** and **Terms** — confirm the first item remains editable (`InnerBlocks`) and items 2..N render via `BlockPreview`.
5. Open **Settings → DesignSetGo → Dynamic Query**. Run **Rebuild filter index**.
6. In a terminal: `wp dsgo query index status`, `wp dsgo query index rebuild-filter post_tag`, `wp dsgo query index drop` (then rebuild).

**Pass criteria:**
- [ ] Filter counts update as other filters apply (facet behavior, not static totals).
- [ ] Infinite scroll respects `autoPauseAfter` and reduced-motion.
- [ ] Admin rebuild UI and WP-CLI both populate `{$wpdb->prefix}dsgo_query_filter_index` (check via `wp db query "SELECT COUNT(*) FROM wp_dsgo_query_filter_index"`).
- [ ] Editor live preview works for posts, users, and terms.

### 1.3 Query v2.3 — nested, relationships, visibility, group-by (PR #372)

**Setup:** Requires an ACF relationship field (or a meta field holding an array of post IDs) on a post type.

**Steps:**
1. Add an inner Query inside a Query item. Confirm the inner query can read `designsetgo/parentItem` context.
2. On a binding inside the inner query, set `scope: 'parent'`. Confirm it reads the outer item's meta.
3. Add a Query with `source: 'relationship'` + `relationshipField: <your field>`. Test each `relationshipFallback`: `empty`, `all`, `parent`.
4. On any inner block, open **Inspector → Advanced → Visibility**. Add rules for each type: `meta`, `taxonomy`, `index`, `auth`. Combine with AND and OR. Confirm the editor preview mirrors frontend output exactly.
5. Set `groupBy` on the Query (taxonomy / meta / date with year, year-month, year-month-day precision). Insert a **Query Group Header** and bind it to `designsetgo/groupLabel`.
6. View the frontend — confirm `<section class="dsgo-query-group">` wrappers appear once per group with the correct header.

**Pass criteria:**
- [ ] Nested loops resolve without infinite-recursion or "query not found" errors.
- [ ] `scope: 'parent'` / `'root'` read from the intended parent-stack entry.
- [ ] Visibility rules apply server-side AND preview identically in the editor.
- [ ] Group-by partitions render correctly at each date precision.

### 1.4 Query v2.4 — third-party bindings + template I/O (PR #373)

**Setup:** Optional — install Meta Box, Pods, and/or JetEngine on a test site. Without them, only confirm the bindings don't register.

**Steps:**
1. With each plugin active, verify `designsetgo/metabox`, `designsetgo/pods`, `designsetgo/jetengine` sources appear in the binding picker.
2. Bind a formatted field (date / file / relation) through each — confirm the plugin's native formatting runs (not raw meta).
3. Deactivate each plugin and confirm the binding source disappears AND pre-existing bindings fall back gracefully (no fatal, empty string output).
4. In a Query's Inspector → Settings → Template I/O → **Export** — download JSON, confirm `schemaVersion: 1`.
5. On a different page, **Import** that JSON. Confirm a fresh `queryId` is generated (sibling bindings on the original page don't break, new page bindings resolve to the new Query).

**Pass criteria:**
- [ ] Bindings register only when host plugin is active.
- [ ] Exported JSON has the attribute allowlist applied (no extraneous attrs).
- [ ] Imported Query has a new `queryId` and works immediately.

### 1.5 Query v2.5 — advanced filters, QM panel, style bindings (PR #375)

**Setup:** Install Query Monitor plugin.

**Steps:**
1. **Date Query Builder:** add `after`, `before`, and `between` clauses. Use both ISO dates (`2025-01-01`) and relative expressions (`-30 days`, `today`).
2. **Multi-level tax/meta groups:** create `{ relation: AND, clauses: [{...}, { relation: OR, clauses: [...] }] }`. Confirm nested groups resolve correctly on the frontend.
3. **`include_children` toggle:** on a hierarchical taxonomy clause, toggle off — confirm children are excluded.
4. **Query Monitor panel:** open QM toolbar, click **DSGo (N)**. Each Query render should show args, found-posts count, duration, SQL.
5. **Dynamic style bindings (`dsgoStyleBinding`):** bind a color or spacing CSS property on a block to a meta/ACF field. Confirm the inline style injects via `WP_HTML_Tag_Processor` on the root element.
6. **Security probe:** try to bind a style to a malicious value containing `url(`, `expression(`, or `javascript:`. Confirm it's rejected (no style applied, no JS executes).

**Pass criteria:**
- [ ] Relative date expressions parse correctly in every timezone.
- [ ] Nested AND/OR clauses generate valid WP_Query args (inspect via QM panel).
- [ ] QM panel only loads when QM is active (check that requests without QM don't load QM assets).
- [ ] Style binding security gate rejects dangerous values — no XSS possible.

### 1.6 Query v1 restructure + onboarding (PR #380)

**Setup:** A fresh page.

**Steps:**
1. Insert **Dynamic Query** — confirm the onboarding template picker appears (not the old inserter variations list).
2. Pick the **Minimal** template. Confirm it creates a `designsetgo/query` container with a `designsetgo/query-results` child inside.
3. Check that sibling filters / pagination scaffold correctly (PR #380 scaffolds siblings via variations).
4. Create an empty-template Query. Confirm **total-items preview** returns 0 (not a stale count) — PR #356 fix.
5. Confirm old saved content with the legacy single-block Query still renders and transforms correctly if migrated.

**Pass criteria:**
- [ ] Container + `query-results` split renders identically to v1 output (parity test — grab old HTML, compare).
- [ ] Template picker covers: Minimal, Grid, List, Cards (whatever ships).
- [ ] Legacy content auto-migrates without "Attempt Recovery" warnings.

### 1.7 Query — CI/Copilot follow-ups (PR #377 sidecar commits)

- [ ] `ebbbf514` — NestedPreview tests pass.
- [ ] `56d347b7` — total-items preview = 0 on empty template.
- [ ] `77cbf661` — Visible unchecked boxes + horizontal orientation variation render correctly.
- [ ] `07fd0f9e` — No duplicate column + groupBy controls in inspector (was a regression).
- [ ] `45576f66` — Editor preview items 1..N render via server HTML for exact visual parity with frontend.

---

## 2. Form Builder

### 2.1 Redirect URL normalization + DOM XSS fix (PR #382, commit `f05fc119`)

**Setup:** Form block with a **Redirect URL** confirmation action.

**Steps:**
1. Set redirect to a same-origin path (`/thank-you`).
2. Set redirect to an absolute URL on the same origin (`https://yoursite.test/thank-you`).
3. Set redirect to a malformed URL containing `javascript:alert(1)` or `data:text/html,...` — confirm **navigation is blocked or normalized to a safe URL**. This was the DOM XSS fix.
4. Set redirect to an off-site URL — confirm it's rejected or only allowed if explicitly configured.

**Pass criteria:**
- [ ] Only safe `http(s):` URLs navigate. `javascript:` / `data:` / `vbscript:` are rejected.
- [ ] No console errors when the URL is rejected; a user-friendly fallback occurs.

### 2.2 Confirmation message persists across reload (PR #379)

**Steps:**
1. Submit a form with **Display message** confirmation.
2. Reload the page — confirm the message still renders (previously disappeared).
3. Navigate away and back — confirm stale confirmations clear (PR review commit `e5e2ceb2`).
4. Submit two different forms on the same page — confirm each has a unique confirmation key fallback (no cross-talk).

**Pass criteria:**
- [ ] Confirmation persists through one reload.
- [ ] Confirmation clears when navigating to a different page and back.
- [ ] Multiple forms on one page don't show each other's confirmations.

### 2.3 Form block pattern parity (PR #378)

**Steps:**
1. Insert every form-related pattern from the pattern library.
2. Confirm each renders without "unexpected content" warnings in the editor.
3. Compare the pattern's saved HTML to the current form `save.js` output — should match.

**Pass criteria:**
- [ ] All form patterns load cleanly.
- [ ] No `id="contact-professional"` or other stale root IDs (PR review commit `dda9fc27`).

### 2.4 admin-ajax.php fallback + rate limiting (PR #342, already released in 2.0.48 but regression-check)

**Steps:**
1. In DevTools, block `/wp-json/designsetgo/v1/forms/submit` at the network layer.
2. Submit a form — confirm fallback to `admin-ajax.php`.
3. Block that too — confirm native POST fallback renders server-side success.
4. Submit rapidly to trigger rate limiting — confirm user-friendly error message.

**Pass criteria:**
- [ ] Three-tier submission works: REST → admin-ajax → native POST.
- [ ] Rate-limit error is readable, not raw JSON.

---

## 3. Security Hardening

### 3.1 Draft Mode REST nonce verification (PR #377)

**Steps:**
1. As admin, toggle Draft Mode on — confirm banner appears.
2. In DevTools, capture the toggle request; replay it with a stale/missing nonce — confirm 403.
3. Confirm read-permission nonce was reverted (commit `79ff766f`) — reading draft state still works without a write nonce.

**Pass criteria:**
- [ ] Write endpoints reject missing/invalid nonces.
- [ ] Read endpoints continue to work without the write nonce.

### 3.2 Color / innerHTML / LLMS gate / CSS sanitizer hardening (PR #349)

**Steps:**
1. Paste HTML into a rich-text area containing `<script>`, `<iframe>`, `on*=`. Confirm it's stripped on save.
2. Attempt to set an inline color via the color picker to a value containing `javascript:` / `expression(`. Confirm rejection.
3. Verify `llms.txt` is only served when the feature is enabled AND the URL is allowed. Disabled state returns 404, not 200-empty.
4. Verify CSS sanitizer rejects `url(javascript:...)`, `expression(...)`, and `@import` with remote URLs.

**Pass criteria:**
- [ ] KSES allowlist holds under hostile paste input.
- [ ] Color + CSS sanitizers reject all tested vectors.
- [ ] LLMS gate is strict (feature flag + URL allowlist both checked).

### 3.3 Per-URL Markdown negotiation (PR #376)

**Steps:**
1. `curl -H "Accept: text/markdown" https://yoursite.test/sample-page/` — expect `Content-Type: text/markdown; charset=utf-8`, `Vary: Accept`.
2. `curl -H "Accept: text/html" ...` — expect HTML.
3. `curl -H "Accept: application/json" ...` — expect `406 Not Acceptable`.
4. Respect the llms.txt flag, post-type allowlist, exclusion meta, password-protected posts.
5. Run the site against [acceptmarkdown.com](https://acceptmarkdown.com) readiness check.

**Pass criteria:**
- [ ] Q-value negotiation works (e.g., `text/html;q=0.5,text/markdown;q=0.9` returns MD).
- [ ] 406 fires for unsupported accept types.
- [ ] Password-protected posts never leak Markdown.

### 3.4 `llms.txt` inline file delivery (PR #366)

- [ ] `/llms.txt` and per-post `.md` files render **inline** (no auto-download `Content-Disposition: attachment`).

---

## 4. New Blocks

### 4.1 Fifty Fifty

**Steps:**
1. Insert Fifty Fifty. Toggle media position left / right.
2. Set focal point via picker — confirm `object-position` updates.
3. Set min-height (px, vh, none).
4. Resize to 375 — confirm media + content stack.
5. Toggle content vertical alignment (top / middle / bottom).
6. Confirm edge-to-edge media even with theme content width.

**Pass criteria:**
- [ ] Full-width media edge-to-edge on desktop.
- [ ] Mobile stacks correctly.
- [ ] Editor parity with frontend.

### 4.2 Query Group Header (see 1.3 above).

---

## 5. New Extensions

### 5.1 Hover Effects extension (PR #371)

**Setup:** Extension should appear on blocks it's enabled for — verify the allowlist.

**Steps:**
1. Enable on a supported block, open **Inspector → Hover Effects**.
2. Test each effect: scale, translate, rotate, color shift, overlay fade.
3. Confirm effects respect `prefers-reduced-motion`.
4. Confirm no CSS specificity collisions with other extensions.

**Pass criteria:**
- [ ] Effects apply cleanly, revert cleanly on mouse-leave.
- [ ] `prefers-reduced-motion` disables non-essential motion.

### 5.2 Grid row span (PR #367)

**Steps:**
1. Insert Grid with 3+ children.
2. On a child, open grid controls → set row span to 2.
3. Confirm the child spans two rows on desktop, behaves sensibly on mobile.

**Pass criteria:**
- [ ] Row span renders in editor + frontend.
- [ ] Responsive grid doesn't break when row span is active.

---

## 6. Layout Fixes

### 6.1 Row fresh-block stacking (PR #368)

- [ ] Insert a new Row — items display **inline** by default, not stacked.

### 6.2 Section nested padding (PR #369)

- [ ] Insert a Section inside another Section — nested one has **no default padding** (previously doubled-up).

### 6.3 Advanced Heading segment gap (PR #370)

- [ ] Insert Advanced Heading with multiple segments — default gap is **0**, not a fallback spacing value.

### 6.4 Sticky header logo shrink (PRs #344, #374)

**Steps:**
1. Enable sticky header with shrink-logo option.
2. Scroll down — logo shrinks smoothly.
3. Scroll back up — logo restores with the **same smooth transition** (PR #374 fix).

**Pass criteria:**
- [ ] Both scroll directions animate (not snap).
- [ ] No layout shift when the header state changes.

---

## 7. Theme 1–6 — Editor UX & Inspector IA

This is cross-cutting: **every block** was touched. Test a representative sample (below) on the assumption that a passing sample implies all pass.

### 7.1 Theme 3 — Inspector IA (PR #363)

**Representative blocks:** Grid, Section, Stack, Tabs, Slider, Icon List, Form Builder root, Query.

**For each block:**
- [ ] Exactly three panels in this order: **Settings**, **Style**, **Advanced** (plus **Color** group).
- [ ] Panel titles are generic ("Settings" / "Style"), not prefixed with block name.
- [ ] Every control is visible by default (no hunting through `ToolsPanel` kebab).
- [ ] Reset-per-control (⋮) works and restores `block.json` default.
- [ ] Color controls appear in the Color group, not inside Settings/Style.
- [ ] HTML element / anchor / class in the Advanced group.

### 7.2 Theme 5 — Shared tablist keyboard + child toolbar (PR #359)

**Blocks:** Tabs, Slider, Scroll Slides, Accordion, Image Accordion.

- [ ] Arrow keys, Home, End move between children (tablist keyboard).
- [ ] Child toolbar exposes Add / Duplicate / Move / Remove for each compound block.
- [ ] Inline `+` on canvas only appears when block is selected / has focus / hover.

### 7.3 Theme 2 — Flip card consolidation (PR #357)

**Steps:**
1. Load a page with legacy `flip-card-front` / `flip-card-back` children.
2. Confirm they render identically to before.
3. Insert a new Flip Card — it uses `flip-card-face` with a `side` attribute.
4. Legacy blocks are `inserter: false` but still transformable.

**Pass criteria:**
- [ ] Existing content renders unchanged.
- [ ] New inserts use the consolidated block.

### 7.4 Theme 1, 4, 6 — placeholders, polish, shared primitives (PRs #354, #356, #358, #362)

- [ ] Placeholders on every block show onboarding or a clear empty state.
- [ ] Block icons + categories are consistent (Theme 4 polish).
- [ ] Shared primitives exist and are used where documented: `useUniqueBlockId`, `useBlockColors`, `useTablistKeyboard`, `cssVars`, `<DsgoInspectorPanel>`, `<DsgoBlockPlaceholder>`, `<DsgoChildToolbar>`.

### 7.5 Editor UX pass (PR #348)

- [ ] Appenders (`+` buttons), navigators, and wizards present on compound blocks.
- [ ] No console warnings about `PanelBody` — only `DsgoInspectorPanel` (ToolsPanel) wrappers in custom inspectors.

### 7.6 Image accordion inline upload (PR #347)

- [ ] Click a pane → uploads inline, expands on click.
- [ ] Works in iframed editor (site editor + post editor).

---

## 8. Abilities API

### 8.1 Settings exposed (PR #351)

**Steps:**
1. `curl /wp-json/wp-abilities/v1/` — confirm plugin's ability categories appear.
2. Each ability resolves, permissions callback gates correctly.
3. Try as a non-admin — confirm permissions block access.

**Pass criteria:**
- [ ] Abilities register, respond, and enforce capabilities.

### 8.2 Add-block round-trip through save() (PR #355)

- [ ] Invoke the add-block ability — the output passes through `save.js` so HTML matches what the editor would emit.
- [ ] No "unexpected content" warnings when inserting ability-generated blocks.

### 8.3 Inline `required: true` migration (PR #352)

- [ ] Existing abilities with inline `required: true` migrate silently to JSON Schema-compliant form on load.

---

## 9. Patterns & Templates

### 9.1 Pattern library (baseline from 2.0.0)

Spot-check 5 patterns:
- [ ] Hero pattern
- [ ] CTA pattern
- [ ] Form pattern (already in 2.3)
- [ ] Features grid pattern
- [ ] Full homepage template

Each should:
- [ ] Insert cleanly (no "Attempt Recovery").
- [ ] Render identically in editor + frontend.
- [ ] Use only block-native markup (no raw HTML blocks — per CLAUDE.md convention).

---

## 10. Accessibility & Keyboard

Run on a built page with at least one Query, one Form, one Tabs, one Accordion, one Modal.

- [ ] Tab order is logical and visible focus ring always shows.
- [ ] All interactive controls reachable by keyboard.
- [ ] Screen reader (VoiceOver on macOS) announces labels, not raw IDs.
- [ ] Filter controls announce their state (`aria-pressed` / `aria-checked`).
- [ ] Color contrast meets WCAG AA (use Axe or Lighthouse).
- [ ] `prefers-reduced-motion` disables all motion (sticky header, infinite scroll, hover effects).

---

## 11. Performance & Regression

- [ ] Lighthouse score on a Query-heavy page ≥ previous release baseline (note the baseline before starting).
- [ ] No duplicate asset loads (inspect Network tab — each enqueue once).
- [ ] `build/style-index.css` contains every new block class (`grep -i <class>` per new block).
- [ ] No PHP notices/warnings in `npx wp-env logs` during a full-page render + filter + load-more cycle.
- [ ] Editor console is clean (no React key warnings, no deprecation warnings).

---

## 12. Upgrade Path

**Steps:**
1. Install the previous released version (**2.0.49**) on a test site.
2. Create content using legacy flip-card-front/back (Theme 2 consolidation target) and forms with block attrs relying on server-side defaults (PR #345). Note: Dynamic Query itself is brand-new in 2.1.0, so there is no "legacy Query" content to migrate.
3. Take a screenshot of the rendered frontend.
4. Upgrade to the new build.
5. Visit the same pages — confirm no visual regression.
6. Open each page in the editor — confirm no "Attempt Recovery" or validation warnings.

**Pass criteria:**
- [ ] Visual parity between pre- and post-upgrade.
- [ ] Silent auto-migration via `isEligible` on every deprecation.
- [ ] No fatals in server logs.

---

## Smoke Tests (post-deploy, production)

Run within 10 minutes of deploy:

- [ ] Home page loads, no 500s.
- [ ] One Query-powered page loads, filter + pagination work.
- [ ] One Form submits end-to-end, confirmation renders.
- [ ] One `.md` URL returns Markdown with correct headers.
- [ ] `wp dsgo query index status` reports a healthy index.
- [ ] No spike in PHP errors in hosting logs.

---

## Failure Log

Record any failures here with PR #, test step, and reproduction notes. Triage before publishing.

| PR # | Section | Failure | Owner | Status |
|------|---------|---------|-------|--------|
|      |         |         |       |        |
