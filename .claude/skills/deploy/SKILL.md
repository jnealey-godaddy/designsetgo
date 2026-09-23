---
name: deploy
description: Prepare plugin for WordPress.org deployment
disable-model-invocation: true
allowed-tools: Bash(npm run *), Bash(npm ci *), Bash(npx wp-env *), Bash(git *), Bash(gh *), Bash(curl *), Read, Edit, Glob
---


Release a new version to WordPress.org. Pushing a `v*` tag triggers `.github/workflows/deploy.yml`, which builds the plugin and publishes it to WordPress.org SVN. The tag push is the point of no return: the release is public within minutes.

1. **Start from a green main**
   - `git switch main && git pull --ff-only origin main`
   - Check CI on the latest main commit: `gh run list --branch main --limit 5`. A Dependabot PR can merge with failing checks and break main for every open PR. Fix main first, don't release over it.
   - `npm ci`, so `node_modules` matches the lockfile.
   - List what's shipping with `git log --oneline --first-parent v<previous>..main`. Read each PR (`gh pr view <n>`) to learn what changed for users.

2. **Run production build**
   - `npm run build` must complete without errors. The webpack asset-size warning for large block bundles (Slider, Section) is expected.

3. **Update version numbers**
   - Use the version the user gave. Ask only if they didn't give one.
   - `package.json` `"version"`.
   - `package-lock.json`: the top-level `"version"` and `packages[""].version`, both near the top. Edit them by hand. `npm install` can rewrite the whole lockfile.
   - `designsetgo.php`: the `Version:` header and the `DESIGNSETGO_VERSION` constant.
   - `readme.txt`: `Stable tag:`.
   - `readme.txt` `== Changelog ==`: a new `= X.X.X - YYYY-MM-DD =` entry at the top (see step 4).
   - `readme.txt` `== Upgrade Notice ==`: a new `= X.X.X =` entry of one or two sentences.
   - Check nothing else still says the old version: `grep -n "<old version>" designsetgo.php package.json readme.txt`. Only the old changelog and upgrade notice headings should match.

4. **Write the changelog for customers**
   `readme.txt` is what site owners read on the WordPress.org plugin page and on the wp-admin update screen. Write for them, not for developers.
   - Include only changes a site owner or editor would notice: bug fixes, new features, visible improvements and security fixes.
   - Leave out dependency bumps, test and tooling changes, CI fixes, refactors and anything internal.
   - Say what people experienced before and what's different now, in plain words. Leave out class names, hook names, option names and file paths unless someone has to use them, such as a filter a developer can set.
   - Start each line with the labels earlier entries use: `* **Fix:**`, `* **New:**`, `* **Improved:**`.
   - `CHANGELOG.md` is the developer changelog. Don't copy its detail into `readme.txt`.
   - Keep the `== Changelog ==` section under 5,000 words. WordPress.org truncates it past that, and only committers see the import warning. Check with `awk '/^== Changelog ==/{f=1;next} /^== /{f=0} f' readme.txt | wc -w`. If it's over, replace the oldest version entries with one-line highlights under `= Earlier releases =`. `tests/unit/readme-changelog-length.test.js` fails CI when the section is over.
   - The entries go public with the tag. Show them to the user before step 8 if they haven't seen them.

5. **Run security audit**
   - Run `/security-audit`, scoped to what's shipping: `git diff v<previous>..main`.
   - Fix critical and high severity findings before releasing.
   - Medium and low findings don't block the release. Report them to the user as follow-ups.

6. **Run tests and lint**
   - `npm run test:unit`, `npm run lint:js` and `npm run lint:css`.
   - PHP runs inside wp-env, because the host has no PHP:
     - `npx wp-env run tests-cli --env-cwd=wp-content/plugins/designsetgo php vendor/bin/phpunit`
     - `npx wp-env run cli --env-cwd=wp-content/plugins/designsetgo php vendor/bin/phpcs --standard=phpcs.xml --warning-severity=0`
     - `composer run-script lint` (`phpcs`) is separate from `composer run-script analyse` (PHPStan) — CI runs both, and `npm run lint:php` only covers the former. Run the phpstan pass too: `npx wp-env run cli --env-cwd=wp-content/plugins/designsetgo php vendor/bin/phpstan analyse --memory-limit=2G`.
   - `lint:js` also lints untracked files in the working tree. If it fails, check whether the file is tracked with `git ls-files <path>`. Errors in untracked local files don't reach the release, and CI lints a clean checkout.
   - Fix anything failing in tracked files.
   - Full E2E tests (`npm run test:e2e`) need a running WordPress site and are run separately. The pre-commit hook runs a few E2E tests against the wp-env dev site and doesn't block the commit. That site can be serving a different checkout or worktree, so check which one before trusting a failure there.

7. **Pre-deployment checklist**
   - Do we need to update any documentation?
   - All tests passing?
   - No console errors in browser (editor + frontend)?
   - Works with latest WordPress version?
   - Works with latest Gutenberg plugin?
   - Tested with common themes (esp. Twenty Twenty-Five)?
   - Security audit clean?
   - Changelog written for customers and under 5,000 words (step 4)?
   - Screenshots current in `.wordpress-org/`?
   - readme.txt short description under 150 characters?

8. **Commit and tag for deployment**
   - Stage only the release files. Never run `git add .`: the working tree often holds unrelated local work.
     `git add designsetgo.php package.json package-lock.json readme.txt`, plus any fixes made in steps 5 and 6.
   - Commit: `git commit -m "chore: Release version X.X.X"`
   - Create tag: `git tag vX.X.X`
   - Push commits: `git push origin main`
   - Push tag: `git push origin vX.X.X`

9. **Verify deployment**
   - Find the run with `gh run list --workflow deploy.yml --limit 1`. Wait for it to finish, then confirm every step passed with `gh run view <id>`.
   - SVN has the tag when this returns 200:
     `curl -s -o /dev/null -w "%{http_code}" https://plugins.svn.wordpress.org/designsetgo/tags/X.X.X/readme.txt`
   - The public listing has the new version when `"version"` reads X.X.X. This takes one to ten minutes:
     `curl -s "https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=designsetgo&request%5Bfields%5D%5Bsections%5D=0"`
   - Check that https://wordpress.org/plugins/designsetgo/ shows the new changelog.
   - Report the deploy result, what shipped, and the audit follow-ups from step 5.

**Note**: The `.distignore` file controls which files are excluded from WordPress.org deployment. Only production files from `/build/` are included; development files (`/src/`, `/tests/`, `/docs/`, `.github/`, etc.) are automatically excluded.
