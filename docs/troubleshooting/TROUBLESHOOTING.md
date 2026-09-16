# DesignSetGo - Troubleshooting Guide

Common issues and their solutions for DesignSetGo WordPress plugin development. This is the canonical troubleshooting doc for the project (see also [HANDLING-LINT-ERRORS.md](./HANDLING-LINT-ERRORS.md) for pre-commit lint specifically).

---

## Blocks Not Appearing in Editor

If you don't see DesignSetGo blocks in the block inserter, follow these steps:

### 1. Verify Plugin is Activated

```bash
npx wp-env run cli wp plugin list
# You should see:
# designsetgo | active
```

If not active:

```bash
npx wp-env run cli wp plugin activate designsetgo
```

### 2. Check for PHP Errors

```bash
npx wp-env logs wordpress | grep -i error
```

### 3. Verify Build Files Exist

```bash
# Check that a block's build output exists (any block, e.g. accordion)
ls -la build/blocks/accordion/
# You should see: block.json, index.js, index.css, style-index.css
```

### 4. Check Block Registration

```bash
npx wp-env run cli wp block list | grep designsetgo
# You should see entries like: designsetgo/accordion, designsetgo/section, ...
```

### 5. Clear WordPress / Restart the Environment

```bash
npx wp-env stop
npx wp-env start
```

### 6. Check Browser Console

Open the editor, open DevTools (F12) → Console, and look for JavaScript errors mentioning `designsetgo`.

### 7. Confirm You're Running wp-env From the Project Root

`wp-env` maps the current directory as a plugin, so it must be started from the repo root:

```bash
pwd            # should be the designsetgo repo root
npx wp-env start
```

### 8. Manual Check in wp-admin

1. Go to `http://localhost:9451/wp-admin` (the port is set in `.wp-env.json`; `npx wp-env start` prints the actual URL).
2. Navigate to Plugins → look for "DesignSetGo" → Activate if needed.
3. Posts → Add New → click **+** → search for any DesignSetGo block (e.g. "Accordion") → it should appear under the DesignSetGo category.

## Common Runtime Issues

### "The plugin does not have a valid header"

Make sure `designsetgo.php` is in the root of the plugin directory with proper plugin headers.

### "Parse error" / "Fatal error"

Check the PHP version — this repo's `.wp-env.json` pins `phpVersion` (currently 8.3):

```bash
npx wp-env run cli php -v
```

### "Block validation error" / "Attempt Recovery"

Usually means block attributes or markup changed without a deprecation. See the **Deprecations** section in `.claude/CLAUDE.md` — a deprecation's `save()` must reproduce the stored HTML byte-for-byte; `isEligible` does not rescue an invalid block.

### Styles not loading

```bash
npm run build
npx wp-env stop
npx wp-env start
```

Then verify the CSS actually compiled: `grep -i "class-name" build/style-index.css`.

### Enable verbose WordPress debugging

Add to `.wp-env.override.json`:

```json
{
  "config": {
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true,
    "WP_DEBUG_DISPLAY": true,
    "SCRIPT_DEBUG": true
  }
}
```

Then `npx wp-env stop && npx wp-env start`.

---

## Build Issues

### npm run build hangs indefinitely

**Symptoms:** `npm run build` starts but never completes, webpack appears stuck, high CPU.

**Root cause:** Corrupted webpack cache in `node_modules/.cache`, or a stale build directory.

```bash
# Quick fix
npm run build:clean

# Manual
npm run clean:cache
npm run build

# Nuclear option
npm run clean:all
npm install
npm run build
```

`npm run clean:all` runs `clean:cache` + `clean:cache:wp-env` + `clean:build` (see `package.json`).

### Build is unusually slow

```bash
# Increase Node memory if you have RAM to spare
NODE_OPTIONS="--max-old-space-size=8192" npm run build

# Find bloat
npm run build:analyze
```

### PHPStan hanging or using too much memory

```bash
vendor/bin/phpstan analyse --level=3
php -d memory_limit=4G vendor/bin/phpstan analyse
# Or add problem paths to phpstan.neon's excludePaths
```

Note: `npm run lint:php` / `composer run-script lint` is PHPCS only — PHPStan is a **separate** script, `composer run-script analyse` (see `.claude/skills/wp-phpstan`). CI runs both; don't assume `lint:php` passing means static analysis passed too.

### Jest tests hang or fail

```bash
npm run test:unit -- --no-cache
npm run test:unit -- path/to/test.test.js
npx jest --clearCache
```

---

## WordPress Environment (wp-env) Issues

### wp-env won't start

```bash
npm run wp-env:clean
npm run wp-env:start

# Check Docker
docker ps
docker system prune

# Check the configured port (see .wp-env.json → "port"; this repo pins 9451)
lsof -i :9451
```

### Plugin not showing in WordPress admin

```bash
npm run build
npm run wp-env:stop
npm run wp-env:start

npx wp-env run cli wp plugin activate designsetgo --debug
npx wp-env logs
```

---

## Dependency Issues

### npm install fails

```bash
npm cache clean --force
rm -rf node_modules package-lock.json
npm install

# If peer dependency conflicts:
npm install --legacy-peer-deps
```

### composer install fails

```bash
composer clear-cache
composer self-update
composer install -vvv
```

---

## Git / Pre-commit Issues

### Pre-commit hook fails

Pre-commit hooks show warnings but are non-blocking by design.

```bash
# Fix linting issues rather than skipping hooks
npm run lint:js -- --fix
npm run lint:css -- --fix
composer run-script lint:fix

# Skip only as a last resort (not recommended)
git commit --no-verify -m "message"
```

See [HANDLING-LINT-ERRORS.md](./HANDLING-LINT-ERRORS.md) for the "unused import" false-positive case specifically.

---

## Performance Issues

### Editor is slow in the browser

```bash
npm run build:analyze          # bundle visualization
ls -lh build/ | sort -k5 -h     # find large assets
npx lighthouse http://localhost:9451 --view
```

---

## CI/CD Issues

### CI builds fail but local works

1. **PHP version matrix** — CI tests multiple PHP versions; `.wp-env.json` here pins PHP 8.3 locally. Test another version with Docker if needed.
2. **Stale local cache** — CI always builds clean:
   ```bash
   npm run clean:all
   npm install
   npm run build
   ```
3. **WordPress version** — `.wp-env.json` pins `"core": "WordPress/WordPress#6.9"`; edit that value locally to reproduce an older-WP CI failure, then `npm run wp-env:clean && npm run wp-env:start`.

### PHPStan fails in CI but passes locally

Usually a PHP version mismatch — check `php --version` locally against CI's matrix, or run PHPStan inside a matching PHP Docker image.

---

## Quick Diagnostic Commands

```bash
# Versions
node --version
npm --version
php --version
composer --version

# Disk usage (large caches can fill disk)
du -sh node_modules/.cache
du -sh .wp-env 2>/dev/null

# Hanging processes
ps aux | grep -i "webpack\|wp-scripts\|phpstan"
pkill -9 -f "webpack|wp-scripts|phpstan"

# General state
git status
git log --oneline -5
```

## Quick Reset

If all else fails, do a complete reset:

```bash
npx wp-env stop
npx wp-env clean all
npm run clean:all
npm install
composer install
npm run build
npx wp-env start
npx wp-env run cli wp plugin activate designsetgo
npx wp-env run cli wp block list | grep designsetgo
```

## Getting Help

1. **Search Issues:** https://github.com/jnealey-godaddy/designsetgo/issues
2. **Check Logs:** `npm run build` output, `npx wp-env logs`, browser DevTools console
3. **Open a New Issue** with: WordPress version, PHP version, browser + version, console errors, and steps to reproduce.

---

## Prevention Checklist

**Daily:** pull latest changes, run `npm run build` to verify setup.

**Weekly:** `npm run clean:cache`, check `npm outdated` / `composer outdated`.

**Before a PR:** `npm run build:clean`; fresh `wp-env` (`npx wp-env clean all && npx wp-env start`); `npm run lint:js && npm run lint:css && npm run lint:php` (PHPCS) **and** `composer run-script analyse` (PHPStan — separate from `lint:php`, see above).
