# Audit Remediation Implementation Plan

> **For Codex:** REQUIRED SUB-SKILL: Use `executing-plans` to implement this plan task-by-task.

**Goal:** Close every concrete security, correctness, performance, and quality-gate defect identified in `PLUGIN-REVIEW.md` without changing existing serialized block content.

**Architecture:** The public Dynamic Query endpoint will resolve a named query from a published post instead of rendering caller-supplied block data. A separate authenticated preview contract will continue to accept editor-only attributes. Form submission will resolve one server-side form schema before any spam, rate-limit, or field processing. Dynamic client changes will use one query state and one lifecycle signal, while long-running index work will retain resumable state.

**Tech Stack:** WordPress REST API, PHP 7.4, PHPUnit/wp-env, Gutenberg view modules, Jest/jsdom, GitHub Actions.

---

### Task 1: Establish focused regression harnesses

**Files:**
- Modify: `tests/phpunit/form-handler-test.php`
- Modify: `tests/phpunit/blocks/query/query-rest-render-test.php`
- Create: `src/blocks/query/test/view.test.js`
- Modify: `src/blocks/tabs/test/view.test.js`
- Modify: `tests/unit/blocks/scroll-accordion.test.js`

1. Add one failing test for each audit reproduction: unknown form ID, omitted required field, public query request, untrusted attributes, protected grouping, post-refresh Load more state, URL filter propagation, appended lifecycle notification, nested Tabs, and removed accordion listeners.
2. Run each focused PHPUnit/Jest file and confirm it fails because the reported behavior is still present.
3. Do not change production code in this task.

### Task 2: Enforce a server-owned form submission contract

**Files:**
- Modify: `includes/blocks/forms/class-form-handler.php`
- Modify: `includes/blocks/forms/class-form-security.php`
- Modify: `tests/phpunit/form-handler-test.php`

1. Write failing tests that require a published form match, a global IP budget, bounded field/payload sizes, and required-field enforcement.
2. Resolve one form definition once per request, reject unknown forms before spam checks, and derive type/constraint/required schema from that definition.
3. Add an IP-only rate-limit counter alongside the existing per-form counter; preserve filters and configured block limits.
4. Verify valid required fields still submit and the focused form test suite passes.

### Task 3: Split trusted public query rendering from authenticated preview

**Files:**
- Modify: `includes/blocks/query/class-query.php`
- Modify: `src/blocks/query/render-helpers.php`
- Modify: `src/blocks/query/render-posts.php`
- Modify: `src/blocks/query/render.php` if needed for context output
- Modify: `tests/phpunit/blocks/query/query-rest-render-test.php`

1. Write failing tests for anonymous rendering of a query embedded in a published post, rejection of unknown/unpublished posts, and rejection of caller-supplied attributes/inner blocks.
2. Add route arguments for a public source post ID and query ID. Parse the published post’s blocks recursively, select the matching Dynamic Query, and render only its stored attributes and inner blocks.
3. Retain a nonce-plus-capability protected preview route for arbitrary editor inputs; do not expose arbitrary templates through the public route.
4. Refuse protected metadata in group/filter output and exclude password-protected posts from public rendering.
5. Confirm route responses for guests, subscribers, and editors match their intended contracts.

### Task 4: Repair Dynamic Query client state and lifecycle

**Files:**
- Modify: `src/blocks/query/view.js`
- Modify: `src/blocks/query/view-helpers.js`
- Modify: `src/blocks/query/render-helpers.php`
- Modify: `src/blocks/query/render.php` if context is assembled there
- Modify: `src/blocks/query/test/view.test.js`

1. Write failing tests for two delegated Load more clicks after a refresh, filter/sort propagation, and reinitialization after append.
2. Store the current page on the query wrapper or a query-scoped state object, reset it when a region is replaced, and send URL parameters through the same request builder as refresh.
3. Send only `postId`, `queryId`, page, and allowed URL params to the public endpoint; stop sending serialized attributes and inner blocks.
4. Emit the shared content-loaded lifecycle event for appended nodes as well as the host-specific append event.
5. Run focused query view tests, then the full JS suite.

### Task 5: Fix interactive ownership and teardown

**Files:**
- Modify: `src/blocks/tabs/view.js`
- Modify: `src/blocks/tabs/test/view.test.js`
- Modify: `src/blocks/scroll-accordion/view.js`
- Modify: `tests/unit/blocks/scroll-accordion.test.js`

1. Write failing nested-tab and removed-accordion tests.
2. Restrict a Tabs instance to panels whose nearest `.dsgo-tabs` owner is that instance.
3. Give each Scroll Accordion instance a cleanup callback; discard disconnected instances before scroll/resize work and clear timers/frames/listeners when query content is removed.
4. Verify keyboard navigation and existing reduced-motion behavior remain covered.

### Task 6: Make filter indexing cache-safe and resumable

**Files:**
- Modify: `includes/blocks/query/class-query-filter-index.php`
- Modify: `includes/blocks/query/class-query-filter-index-rebuilder.php`
- Modify: `includes/blocks/query/class-query-filter-index-hooks.php`
- Modify: `includes/blocks/query/class-query.php`
- Modify: `tests/phpunit/blocks/query/filter-index-test.php`
- Modify: `tests/phpunit/blocks/query/filter-index-rebuilder-test.php`

1. Write failing tests for deleting an object’s last indexed value and for pausing/resuming a multi-batch rebuild.
2. Bump cache epoch after every successful delete path, including no replacement rows.
3. Persist rebuild cursor/status, process one bounded batch per REST/cron invocation, schedule continuation, and retain the live table until a completed replacement generation is ready. Renew the lock while work remains.
4. Test interruption/resume and completed-table swap with the existing query/index suites.

### Task 7: Repair quality gates and targeted maintainability issues

**Files:**
- Modify: `.github/workflows/ci.yml`
- Modify: `jest.config.js`
- Modify: `includes/core/class-assets.php`
- Modify: `src/blocks/slider/edit.js`
- Modify: `src/blocks/slider/test/*` only if behavior changes

1. Add a failing config-level test or deterministic check proving lint failures are not masked and nested worktrees cannot be discovered.
2. Remove CI’s success-on-lint-failure fallbacks and anchor Jest roots/test matching to this checkout.
3. Stop embedding unrelated Grid/Row/Icon/Pill critical CSS when another DesignSetGo block is the only feature on the page; retain correct styles for pages that render those blocks.
4. Convert the affected Slider inspector groups to the repository’s Settings/Style/Advanced shared panel primitive without changing block attributes or serialized output.
5. Run lint, build, scoped unit suites, PHP standards, and browser-facing focused tests.

### Task 8: Verify, review, and document

**Files:**
- Modify: `PLUGIN-REVIEW.md` only if the audit artifact is intentionally added to this branch
- Modify: `docs/reviews/2026-09-09-audit-evidence/README.md` only if the audit artifact is intentionally added to this branch

1. Run focused regression suites, the full current-checkout Jest suite, PHP tests in wp-env, PHP syntax, PHPCS, PHPStan, lint, and production build.
2. Run a logged-out browser test for public query filtering/pagination and an authenticated editor-preview test.
3. Re-read the audit, mark each concrete finding as fixed only with recorded evidence, and document unavailable checks plainly.
4. Commit the scoped implementation with `fix: remediate plugin audit findings` after review.
