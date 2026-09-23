---
name: i18n-update
description: Update translation files and check for untranslated strings
disable-model-invocation: true
allowed-tools: Bash(npm run *), Bash(npx wp-env *), Bash(git *), Read, Glob
---


Manage internationalization for the DesignSetGo plugin.

## Quick Commands

**Find untranslated strings:**

```bash
# JavaScript strings missing translation
grep -r "'[A-Z][a-z]" src/ --include="*.js" | grep -v "__(" | grep -v "//"

# Strings missing text domain
grep -r "__(" src/ includes/ | grep -v "'designsetgo'"
```

**Generate POT file** (the host has no local `wp` CLI — this runs inside wp-env, matching the `i18n:make-pot` package.json script):

```bash
npm run i18n:make-pot
# = wp-env run cli wp i18n make-pot /var/www/html/wp-content/plugins/designsetgo /var/www/html/wp-content/plugins/designsetgo/languages/designsetgo.pot --domain=designsetgo
```

**Update PO files:**

```bash
# For each language
msgmerge --update languages/designsetgo-nl_NL.po languages/designsetgo.pot
msgmerge --update languages/designsetgo-es_ES.po languages/designsetgo.pot
```

**Compile MO files:**

```bash
msgfmt languages/designsetgo-nl_NL.po -o languages/designsetgo-nl_NL.mo
msgfmt languages/designsetgo-es_ES.po -o languages/designsetgo-es_ES.mo
```

**Regenerate the per-file JS JSON catalogs** (`languages/designsetgo-{locale}-{md5}.json`, the `i18n:make-json` script):

```bash
npm run build          # REQUIRED FIRST — see warning below
npm run i18n:make-json
```

**Critical — build before make-json:** each JSON catalog's filename hash is `md5()` of the JS file path WordPress loads translations for. Core hashes the `build/` path at runtime, but `wp i18n make-json` hashes whatever `src="..."` it finds in the current `script-handles.json`/source map. Running `make-json` against a stale or missing `build/` produces catalogs keyed to the wrong hash, so the translation silently never loads for that file (no error — the string just renders in English). Always `npm run build` immediately before regenerating JSON catalogs, and verify with `grep -i "your-string" languages/designsetgo-<locale>-*.json`.

## Full Workflow

### 1. Find Untranslated Strings

Scan codebase for hardcoded strings or missing text domains.

### 2. Fix Translation Issues

**Common issues:**

```javascript
// ❌ BAD - Hardcoded string
console.log('Block loaded');

// ✅ GOOD - Translated
console.log(__('Block loaded', 'designsetgo'));

// ❌ BAD - String concatenation
const msg = 'You have ' + count + ' items';

// ✅ GOOD - Use sprintf
const msg = sprintf(__('You have %d items', 'designsetgo'), count);

// ❌ BAD - Missing translator comment
__('Save', 'designsetgo')

// ✅ GOOD - With context
/* translators: Button label to save block settings */
__('Save', 'designsetgo')
```

### 3. Generate POT File

Create or update the template file with all translatable strings.

### 4. Update Language Files

Merge new strings into existing translations. Translators will fill in missing translations.

### 5. Compile MO Files

Convert human-readable PO files to machine-readable MO files.

### 6. Test Translations

**In WordPress:**

1. Go to Settings → General
2. Change Site Language
3. Check all block labels, descriptions, and controls
4. Verify strings appear in target language

**Test RTL Languages:**

For Arabic, Hebrew:

1. Switch language in WordPress
2. Check layout doesn't break
3. Verify `style-rtl.css` is loaded

## Best Practices

**Always:**
- Use `__('String', 'designsetgo')` for translatable strings
- Add translator comments for ambiguous strings
- Use `sprintf()` for variable interpolation
- Test with RTL languages

**Never:**
- Translate URLs, code, or HTML tags
- Concatenate translated strings
- Use variables in translation strings (use placeholders)

## Common Translation Functions

```javascript
// Simple translation
__('Text', 'designsetgo')

// Translation with echo
_e('Text', 'designsetgo')

// Singular/plural
_n('%d item', '%d items', count, 'designsetgo')

// Translation with context
_x('Post', 'noun', 'designsetgo')
_x('Post', 'verb', 'designsetgo')
```

## Before Committing

Always check for untranslated strings using the Quick Commands greps above — there is no dedicated `lint:i18n` script in `package.json`.

## Reference

- [WordPress I18n Documentation](https://developer.wordpress.org/plugins/internationalization/)
- [Plugin Handbook: I18n](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/)
