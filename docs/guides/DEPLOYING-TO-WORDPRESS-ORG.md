# Deploying a New Plugin Version to WordPress.org

This guide covers the end-to-end release process for shipping a new version of DesignSetGo to the [WordPress.org plugin directory](https://wordpress.org/plugins/designsetgo/).

The repo already wires deployment to GitHub Actions — pushing a git tag is what triggers the public release. Everything below is the surrounding work that must be correct **before** the tag is pushed, plus the verification steps to run after.

## TL;DR

1. Decide the new version (SemVer): `MAJOR.MINOR.PATCH`.
2. Bump the version in **three** places: [designsetgo.php](../../designsetgo.php) header, [readme.txt](../../readme.txt) `Stable tag`, [package.json](../../package.json).
3. Update [CHANGELOG.md](../../CHANGELOG.md) and the `== Changelog ==` section of `readme.txt`.
4. Run the pre-flight checks (`npm run build`, lint, tests).
5. Commit + merge to `main`.
6. Tag the commit (`git tag x.y.z && git push origin x.y.z`).
7. Watch [`Deploy to WordPress.org`](../../.github/workflows/deploy.yml) run; verify on .org.

---

## How releases are wired

Two workflows in [.github/workflows/](../../.github/workflows/) handle WP.org delivery:

| Workflow | Trigger | What it does |
|----------|---------|--------------|
| [deploy.yml](../../.github/workflows/deploy.yml) | Push of any git tag | Builds the plugin and runs `10up/action-wordpress-plugin-deploy` — copies the built plugin to SVN `trunk/`, copies it to `tags/<tag>/`, and updates the WP.org `Stable tag`. |
| [deploy-assets.yml](../../.github/workflows/deploy-assets.yml) | Manual (`workflow_dispatch`) | Deploys *only* `assets/` (banners, screenshots, icons), or `readme.txt`, or pushes code to `trunk` without a version bump. Has a `dry_run` toggle. |

Both rely on two repository secrets:

- `SVN_USERNAME` — your WordPress.org account
- `SVN_PASSWORD` — the matching password (or app-password if 2FA is on)

The set of files actually shipped to SVN is filtered by [.distignore](../../.distignore). If you add new top-level files or directories that should NOT ship (e.g. dev tooling), update `.distignore` first.

---

## 1. Pick the version number

DesignSetGo follows [SemVer](https://semver.org/):

- **PATCH** (`2.1.0` → `2.1.1`) — bug fixes, no behaviour changes for users.
- **MINOR** (`2.1.0` → `2.2.0`) — new blocks/extensions/features, backwards-compatible.
- **MAJOR** (`2.1.0` → `3.0.0`) — breaking changes (removed blocks, attribute schema changes without deprecations, PHP/WP minimum bumps).

Bumping the WP "Tested up to" or PHP minimum is by itself a MINOR change unless it actually breaks installations.

---

## 2. Update version metadata (three places)

WordPress.org reads the `Stable tag` from `readme.txt` and the `Version:` header from the main plugin file. They MUST match the git tag.

### a. Plugin header — [designsetgo.php](../../designsetgo.php)

```php
 * Version:           2.2.0
 * Requires at least: 6.7
 * Requires PHP:      8.0
```

If you raised the minimum WP or PHP version, bump `Requires at least` / `Requires PHP` here too.

### b. readme.txt

```
Requires at least: 6.7
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 2.2.0
```

Always re-check `Tested up to` against the latest WP release before tagging.

### c. package.json

```json
"version": "2.2.0"
```

This keeps the npm/`plugin-zip` artifact in sync with the released version.

> **Sanity check:** the three numbers must be byte-identical. A mismatched `Stable tag` is the #1 cause of the .org listing showing the wrong version after deploy.

---

## 3. Update changelogs

Two files. They serve different audiences but should agree.

### CHANGELOG.md (developer-facing, Keep a Changelog format)

Add a new section above the previous release:

```markdown
## [2.2.0] - 2026-05-15

### New Blocks
- ...

### New Features
- ...

### Bug Fixes
- ...

### Breaking Changes
- ...
```

### readme.txt — `== Changelog ==` section (user-facing)

WP.org renders this on the plugin page. Keep entries shorter and audience-appropriate:

```
== Changelog ==

= 2.2.0 =
* New: <user-visible feature>
* Fix: <user-visible bug fix>
* Improvement: <perf/UX tweaks>
```

Also update `== Upgrade Notice ==` at the bottom of `readme.txt` if the release has anything users need to know before updating (database migrations, removed blocks, deprecated APIs).

---

## 4. Pre-flight checks

Run from the repo root:

```bash
# Clean build
npm ci
npm run build

# Static checks
npm run lint:js
npm run lint:css
npm run lint:php

# Smoke tests
npm run test:e2e:chromium
```

Manual checks:

- [ ] Editor loads with no console errors in a fresh `wp-env`.
- [ ] Insert a representative sample of new blocks, save, reload — no "Attempt Recovery" warnings.
- [ ] Frontend renders the saved post correctly.
- [ ] Spot-check at least one block on the latest stable WP and one on the prior major (currently 6.7).

If you're sensitive to package-size regressions, run `npm run build:analyze` and compare to the prior release.

---

## 5. Validate `readme.txt`

WP.org has a strict parser. Use the official validator before tagging:

- <https://wordpress.org/plugins/developers/readme-validator/>

Paste the contents; it must return "Your readme.txt rocks." Any warning will silently degrade the listing (missing screenshots section, untested headers, etc.).

Common gotchas:

- Headers must use `==` (h1) and `===` (h2) exactly.
- `Tags:` is capped at 5.
- `Stable tag` must be a real, existing tag — never `trunk`.

---

## 6. Commit and tag

The deploy workflow keys off the **tag**, not the branch. Standard flow:

```bash
# On a release branch — see CONTRIBUTING.md for branch naming
git checkout -b claude/release-2.2.0
git add designsetgo.php readme.txt package.json CHANGELOG.md
git commit -m "release: 2.2.0"
git push -u origin claude/release-2.2.0
# Open PR, merge to main after review
```

After the release commit lands on `main`:

```bash
git checkout main
git pull
git tag 2.2.0           # NO leading "v"
git push origin 2.2.0
```

> **The tag name IS the WP.org SVN tag.** Use the bare version (`2.2.0`), not `v2.2.0`. Mismatched tags must be re-cut — SVN tags cannot be silently re-pointed.

---

## 7. Watch the deploy

Open the Actions tab and follow the [`Deploy to WordPress.org`](../../.github/workflows/deploy.yml) run for the new tag. It does, in order:

1. `actions/checkout@v4`
2. `npm ci` + `npm run build`
3. `10up/action-wordpress-plugin-deploy@stable` — handles SVN checkout, copies the built tree (filtered by `.distignore`) to `trunk/` and `tags/<tag>/`, commits both.
4. Generates a release ZIP and attaches it to the GitHub release (because `generate-zip: true`).

Typical run is 4–7 minutes. The .org listing usually reflects the new version within ~15 minutes of a successful run; CDN caches can lag up to an hour.

If the deploy step fails:

- **`E170001` / auth failure** → `SVN_USERNAME` / `SVN_PASSWORD` are wrong or the .org account isn't a committer on the plugin.
- **`Stable tag` mismatch warning** → step 2 wasn't done correctly. Fix `readme.txt`, commit, delete the tag, re-tag.
- **No assets/readme updated** → expected. The main deploy doesn't touch `assets/`. Use `deploy-assets.yml` for that.

---

## 8. Post-release verification

- [ ] <https://wordpress.org/plugins/designsetgo/> shows the new version + changelog.
- [ ] Download the ZIP from the .org page and diff against the GitHub release ZIP (they should be identical).
- [ ] Install the .org ZIP on a clean WP site, activate, smoke-test one new feature.
- [ ] Existing site upgrade path: install the previous version, then upgrade to the new one — confirm no deprecation warnings or "Attempt Recovery" prompts on existing content.
- [ ] If the release introduced new screenshots or banners, run [deploy-assets.yml](../../.github/workflows/deploy-assets.yml) manually with `deploy_type: assets` (do a `dry_run: true` pass first).

---

## Special cases

### Asset-only updates (banners, screenshots, icon)

You don't need a new version for these. Run [deploy-assets.yml](../../.github/workflows/deploy-assets.yml) manually:

1. Actions → "Deploy Assets to WordPress.org" → Run workflow.
2. `deploy_type: assets`, `dry_run: true` first to preview.
3. Re-run with `dry_run: false` once the diff looks right.

Files come from [assets/](../../assets/) in the repo root. Naming follows WP.org conventions: `banner-1544x500.png`, `icon-256x256.png`, `screenshot-1.png`, etc.

### readme.txt-only updates

Same workflow, `deploy_type: readme`. Useful for fixing typos, adding tested-up-to bumps, or expanding FAQ entries without cutting a release.

### Hotfix to trunk without a version bump

`deploy_type: trunk`. Pushes the current `main` build to SVN `trunk/` only — does not create a tag and does not change `Stable tag`. Use sparingly; users on autoupdate get whatever `Stable tag` points at, not `trunk`.

### Reverting a bad release

WordPress.org does not allow deleting tags. To roll back:

1. Cut a NEW patch version (e.g. `2.2.1`) that reverts the breaking commits.
2. Update `Stable tag` in `readme.txt` to the new patch version.
3. Tag and deploy as normal.

If the bad version is causing active harm, you can also temporarily set `Stable tag` to the previous good version (e.g. `2.1.0`) and run `deploy_type: readme` — autoupdate will pull users back to that version. Do this only as a stopgap.

---

## Checklist (printable)

```
[ ] Version bumped in designsetgo.php
[ ] Stable tag bumped in readme.txt
[ ] package.json version bumped
[ ] CHANGELOG.md entry added
[ ] readme.txt Changelog + Upgrade Notice updated
[ ] readme.txt validates on wordpress.org/plugins/developers/readme-validator/
[ ] npm run build clean
[ ] Lint suite green
[ ] E2E smoke green
[ ] Manual editor + frontend smoke on latest WP
[ ] Release commit merged to main
[ ] Tag pushed (bare version, no "v" prefix)
[ ] Deploy workflow green
[ ] .org listing reflects new version
[ ] Upgrade-from-prior-version smoke-tested
```

---

## References

- [10up/action-wordpress-plugin-deploy](https://github.com/10up/action-wordpress-plugin-deploy)
- [10up/action-wordpress-plugin-asset-update](https://github.com/10up/action-wordpress-plugin-asset-update)
- [WordPress.org readme.txt validator](https://wordpress.org/plugins/developers/readme-validator/)
- [Plugin Developer FAQ — How do I tag a release?](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [SemVer 2.0.0](https://semver.org/spec/v2.0.0.html)
