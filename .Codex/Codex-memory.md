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
