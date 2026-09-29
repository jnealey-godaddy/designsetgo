# Release preflight fixes implementation plan

> **For Codex:** Execute the independent fixes with the debugging, test-driven-development and parallel-agent skills; review the integrated diff before creating the PR.

**Goal:** Resolve the two public visibility disclosures and missing pattern media identified in the September 29 preflight, without tagging or deploying.

**Architecture:** Apply the shared visibility evaluator at every Markdown dispatch with an explicit post context and anonymous public audience. Replace client-visible Query definitions with signed opaque references to bounded server-side WordPress transients. Preserve existing pattern media URLs and include their files in both packaging paths.

**Tech stack:** WordPress PHP, PHPUnit, Jest, wp-scripts ZIP packaging and rsync deployment filtering.

## 1. Public Markdown

- Add failing regressions in `tests/phpunit/markdown-visibility-test.php`: nested parents/children, explicit post context, administrative generation, exception and reentrant restoration.
- Cover public index excerpts and upgrade cleanup of per-post/full caches and static exports.
- Gate `includes/markdown/class-converter.php` and handler child paths with `BlockVisibility::matches()`; restore caller state in `finally`.
- Add an independent repair marker in the LLMS controller so an unchanged plugin version cannot leave old exported content public.
- Run the new tests red, then green, then the existing LLMS suites.

## 2. Query refresh sources

- Add failing regressions in `tests/phpunit/blocks/query/query-refresh-confidentiality-test.php` for decoded page source, cross-audience replay, later item conditions, tampering, missing/expired records and legacy sources.
- Update `includes/blocks/query/class-query-refresh-source.php` to retain complete templates server-side and emit a minimal signed reference.
- Preserve site/query/source-post authorization and nested/editor behavior; reject old plaintext sources.
- Bound storage lifetime and document full-page cache invalidation and the need to reload after storage expiry.
- Run the new tests red, then green, then all Query regressions.

## 3. Release packaging

- Add tests in `tests/unit/release-packaging.test.js` against the real ZIP builder and rsync rules.
- Include `assets/images/patterns/**` in `package.json`; replace the blanket assets exclusion in `.distignore`.
- Correct development-config exclusions and remove duplicated marketing assets, after checking runtime references.
- Rebuild the actual ZIP and assert all seven mapped files exist, with production URLs unchanged.

## 4. Integration and review

- Run the complete PHP and JavaScript suites, build, JS/CSS/PHP lint, PHPStan and license checks.
- Install the packaged plugin in a separate local WordPress environment; verify anonymous Markdown, Query refresh/load-more and pattern media in the browser, including the editor.
- Record migration/cache requirements in the changelog and PR description.
- Review the complete effective diff, commit only the isolated task files, push the branch and open a review-ready PR.
- Leave production, tags and the release version unchanged.
