# Existing-user regression fixes implementation plan

> **For Codex:** Use test-driven development and verification-before-completion for each task.

**Goal:** Resolve REG-01 through REG-04 before the 2.9 release, preserving saved content and explicit author choices.

**Architecture:** Keep form constraints at their existing server boundary, but enforce custom patterns only when their browser and PHP meanings agree. Batch lazy icon requests within the existing endpoint limit and retry omitted responses without treating them as unknown. Restore the historical modal surface fallback and use one explicit background token in editor and frontend; no saved markup changes.

**Tech stack:** WordPress PHP, Gutenberg, JavaScript, SCSS, Jest, PHPUnit, local wp-env and Chrome.

## Tasks

1. Form pattern compatibility: add failing browser/server contract cases to PHPUnit, retain enforcement of supported patterns, skip unsupported ECMAScript-only/invalid-browser syntax, then run form rules/submission/Turnstile coverage. Document the supported boundary rather than claiming complete JavaScript regex equivalence.
2. Icon transport: add failing 101+ name and partial/malformed response cases; chunk requests to the route's 100-name limit, preserve explicit unknown-name caching and delayed failure retry, then run lazy-injector and asset coverage.
3. Modal compatibility: add a failing compiled-style test for dark base with saved black text and token parity; use the same explicit modal background token and historical white fallback in both stylesheets. Preserve per-instance background/color overrides and save/deprecation output.
4. Build and inspect the actual ZIP; run full JS/PHP suites, required lint and static analysis. Reproduce fixes in the packaged local installation, including browser input validity, real form handler, large dynamic icon update, and modal surface/author-color/token parity.
5. Review the effective diff, repair any review findings, update the changelog and focused verification report, commit only scoped files, push a new branch, open and attach a ready-for-review PR. No version bump, merge, tag, or deployment.

## Decisions

- Avoid executing JavaScript or adding a regex engine dependency on the server. A conservative shared syntax boundary is preferable to applying PCRE's different interpretation to browser-only patterns.
- Restore compatibility for legacy modal content via stylesheet fallback, without a serialization change or migration. Explicit theme modal background tokens remain opt-in.
- Keep the icon route's work limit. Omitted entries remain retryable; only explicit string responses become cached answers.
