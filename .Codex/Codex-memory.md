# Codex issue notes

## Codex-2026-09-25-extract-zip

- Dependabot alerts #128 and #145 both concern the development-only `extract-zip` package. As of 2026-09-25, npm has no patched release.
- `@wordpress/env` 10.35.0 brings in `extract-zip` 1.7.0. `@wordpress/scripts` 31.6.0 brings in two copies of 2.0.1 through Puppeteer and Lighthouse.
- Updating to `@wordpress/env` 11.16.0 and `@wordpress/scripts` 35.0.0 removes every `extract-zip` entry from the npm lockfile. The Lighthouse toolchain now requires Node 22.19 or newer, so CI and deployment use Node 24.
- `@wordpress/scripts` 35 uses ESLint 10 and ignores this repo's `.eslintrc.js`. Keep ESLint 8 and the WordPress ESLint plugin 24 as explicit lint dependencies until the project migrates its rules to flat config.
- Keep `@wordpress/stylelint-config` 23 as a direct dependency for `.stylelintrc.json`. A local parent checkout can otherwise mask its absence, while clean CI fails to resolve it. Version 23 supports the repository's Stylelint 16 peer tree.

## Codex-01a0ef09-2026-09-29-release-fixes

- Isolated branch Codex/release-preflight-fixes repairs public Markdown visibility/excerpts and invalidates legacy owned exports independently of the unchanged 2.8.2 version.
- Public export guards reject every nonempty post password even when a browser cookie unlocks HTML; failing-cookie tests precede the fix.
- Query v2 carries only a signed opaque reference; full templates stay in viewer-bound 30-day sliding transients. Purge page/CDN caches on upgrade; immediate old-template revocation also requires clearing source transients.
- Both ZIP and SVN filters preserve all seven assets/images/patterns files; SVN excludes dev tooling and duplicate marketing images.
- Verified 4,262 JS tests, 1,925 PHP tests/7,477 assertions, build/lint/PHPStan/licenses and packaged WordPress browser checks. Packaged Plugin Check has zero errors and the existing old upgrade-notice warning.
- Packaged local fixture is on localhost:9452; production, release tags and the version are unchanged. See docs/reviews/2026-09-29-release-fixes.md for operational limits.

## Codex-01a0ef09-2026-09-29-existing-user-regressions

- Branch Codex/existing-user-regressions fixes REG01–04 after PR617: conservative HTML-v/PCRE pattern admission, serial 100-name icon batches, legacy Modal white surface/black close icon, and matching editor/frontend background token.
- Unsupported custom JS patterns remain browser-only; all independent native/required field checks stay in place. No saved markup or version change.
- Verified 4,274 JS tests, 1,962 PHP tests/7,515 assertions, build/lint/PHPStan and packaged Chrome checks: 162 AJAX icons, actual old saved content, custom pattern parity, theme token parity and 390px Modal viewport.
- Broader intentional upgrade behavior remains in the release audit. See docs/reviews/2026-09-29-regression-fixes.md for boundaries; no production deployment.

## Codex-01a0ef09-2026-09-29-release-2.9.0

- Prepare 2.9.0 on Codex/release-2-9-0 from merged main 4546b1c9; align all version fields and document intentional upgrade/cache/export behavior.
- Fresh build, JS/CSS/PHP checks, 4,274 JS tests and 1,962 PHP tests/7,515 assertions passed; main's 20 WordPress/PHP combinations, CodeQL and Plugin Check passed.
- Versioned ZIP includes seven pattern images and excludes development files. Full six-profile browser run and live Turnstile/storage/inbox checks remain pending; no tag or customer publication.
- Live Turnstile has both keys configured. Do not move production secrets to local/browser storage. Current evidence and remaining gates: docs/reviews/2026-09-29-release-2.9.0.md.

## Codex-2026-10-09-dependabot-critical-high

- Worktree `.worktrees/dependabot-critical-high`, branch `Codex/dependabot-critical-high`, base `a4e8a1eb`.
- Live open critical/high alerts: simple-git #159/#160/#161 and http-cache-semantics #154. Override simple-git to ^4.0.2 and http-cache-semantics to ^4.3.0; lockfile has one copy of each, outside every reported critical/high affected range.
- simple-git 4 removed its callable CommonJS default. Postinstall patches both @wordpress/env Git imports to named simpleGit; fails explicitly if upstream source changes. Remove patch when wp-env supports the named export.
- Added real local wp-env clone/fetch/checkout/reset regression test; confirmed it fails with the old import and passes with the patch. Existing 4,274 unit tests, the new integration test, build and JS/CSS lint passed. PHP lint unavailable because Composer is not installed in the host shell; no PHP changed.
- http-cache-semantics maintainer disputes CVE-2026-93748 in https://github.com/kornelski/http-cache-semantics/issues/56. Version 4.3.0 is outside the published <=4.2.0 range; do not claim a proven fix for the disputed behavior. Shared private-response storable() guard verified separately.
- npm audit upload was rejected by automatic approval review; user approval requested. No audit result or GitHub alert closure claimed. Alerts close after default-branch merge and GitHub rescanning; no merge or publication performed.
