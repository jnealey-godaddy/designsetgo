---
name: quick-fix
description: Quick fixes for common issues (build errors, missing styles, validation)
allowed-tools: Read, Glob, Grep, Bash(ls *), Bash(cat *), Bash(npm *)
---


Quickly diagnose and fix common DesignSetGo plugin issues.

## What's the Problem?

1. **Build errors** - `npm run build` fails
2. **Styles not applying** - Editor or frontend styles missing
3. **Block validation error** - "This block contains unexpected or invalid content"
4. **Console errors** - JavaScript errors in browser console
5. **Layout not working** - Grid/flex/stack layout broken
6. **Colors not showing** - Theme colors missing from picker
7. **Translation missing** - Strings not translating
8. **wp-env not starting** - Local development environment issues


## Fix 1: Build Errors

**Symptoms:** `npm run build` fails with errors

**Quick fixes:**

```bash
# Clear everything and rebuild
rm -rf node_modules build
npm install
npm run build

# Check for syntax errors
npm run lint:js
npm run lint:css
npm run lint:php

# If specific file error, check that file for:
# - Missing imports
# - Syntax errors (missing brackets, semicolons)
# - Typos in function names
```


## Fix 2: Styles Not Applying

### Editor Styles Missing

```bash
# 1. Check if styles are imported in editor.scss
grep "@import" src/styles/editor.scss

# 2. Verify build output
cat build/index.css | grep "your-class-name"

# 3. If missing, add import to src/styles/editor.scss
# @import '../blocks/your-block/editor';

# 4. Rebuild
npm run build
```

### Frontend Styles Missing

```bash
# 1. Check if styles are imported in the REAL frontend entry (src/style.scss,
#    not src/styles/style.scss — that file is dead code, nothing imports it)
grep "@import" src/style.scss

# 2. Verify build output
cat build/style-index.css | grep "your-class-name"

# 3. If missing, add import to src/style.scss
# @import 'blocks/your-block/style';

# 4. Rebuild
npm run build
```

**CRITICAL:** Both `src/style.scss` (frontend) and `src/styles/editor.scss` (editor) must import block styles. `src/styles/style.scss` looks like the frontend entry but is dead code.


## Fix 3: Block Validation Error

**Symptoms:** "This block contains unexpected or invalid content" or block shows recovery prompt

**Common causes:**

1. **Edit and save markup don't match**

```javascript
// ❌ WRONG - Different structure in edit vs save
// edit.js
<div {...blockProps}>
    <div className="inner">
        <InnerBlocks />
    </div>
</div>

// save.js
<div {...blockProps}>
    <InnerBlocks.Content />
</div>

// ✅ CORRECT - Identical structure
// Both edit.js and save.js:
const innerBlocksProps = useInnerBlocksProps(...);
<div {...innerBlocksProps} />
```

2. **Attributes changed without deprecation**

```javascript
// If you changed attributes, add deprecation:
// block.json
{
  "deprecated": [
    {
      "attributes": { /* old attributes */ },
      "save": OldSaveComponent
    }
  ]
}
```

3. **Using plain `<InnerBlocks />` instead of `useInnerBlocksProps`**

```javascript
// ❌ WRONG
<InnerBlocks />

// ✅ CORRECT
const innerBlocksProps = useInnerBlocksProps();
<div {...innerBlocksProps} />
```


## Fix 4: Console Errors

**Check browser console for specific errors:**

### "Cannot find module '@wordpress/...'"

```javascript
// Add proper import
import { __ } from '@wordpress/i18n';
```

### "useSelect is not a function"

```javascript
// Import from correct package
import { useSelect } from '@wordpress/data';
```

### "Attribute is undefined"

```json
// Add default in block.json
{
  "attributes": {
    "yourAttribute": {
      "type": "string",
      "default": ""
    }
  }
}
```

### "Invalid hook call"

```javascript
// Move hooks outside conditionals
// ❌ WRONG
if (condition) {
    const value = useSelect(...);
}

// ✅ CORRECT
const value = useSelect(...);
if (condition && value) {
    // use value
}
```


## Fix 5: Layout Not Working

**Symptom:** Grid/flex/stack not applying to blocks

**Fix: Use `useInnerBlocksProps` instead of plain `<InnerBlocks />`**

```javascript
// ❌ WRONG - Plain InnerBlocks
<div style={{ display: 'grid' }}>
    <InnerBlocks />
</div>

// ✅ CORRECT - useInnerBlocksProps
const innerBlocksProps = useInnerBlocksProps({
    style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(3, 1fr)',
        gap: '20px'
    }
});

return <div {...blockProps}><div {...innerBlocksProps} /></div>;
```

**Save function must match:**

```javascript
// save.js
const innerBlocksProps = useInnerBlocksProps.save({
    style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(3, 1fr)',
        gap: '20px'
    }
});

return <div {...blockProps}><div {...innerBlocksProps} /></div>;
```


## Fix 6: Colors Not Showing

**Symptom:** Theme colors missing from color picker

**Fix: Use `ColorGradientSettingsDropdown` (never `PanelColorSettings`)**

The plugin-wide migration off `PanelColorSettings` is finished (no remaining usages), so this should never happen in existing code — treat any hit below as a regression to fix directly, following the pattern in `.claude/claude.md`.

**Quick check:**

```bash
# Are you using deprecated PanelColorSettings?
grep -r "PanelColorSettings" src/blocks/your-block/
```

Consider `useBlockColors()` (`src/hooks/useBlockColors.js`) to wrap the `ColorGradientSettingsDropdown` boilerplate instead of hand-rolling it.


## Fix 7: Translation Missing

**Symptom:** Strings appear in English instead of target language

**Quick fixes:**

```bash
# 1. Regenerate POT file (runs inside wp-env — the host has no local wp CLI)
npm run i18n:make-pot

# 2. Update PO files
msgmerge --update languages/designsetgo-nl_NL.po languages/designsetgo.pot

# 3. Compile MO files
msgfmt languages/designsetgo-nl_NL.po -o languages/designsetgo-nl_NL.mo

# 4. If a JS string, rebuild THEN regenerate the JSON catalogs (order matters
#    — see /i18n-update for why):
npm run build
npm run i18n:make-json

# 5. Clear WordPress cache
# In wp-admin: Go to Settings → General → Site Language → Save Changes
```

**Check for untranslated strings:**

```bash
# Find hardcoded strings
grep -r "'[A-Z][a-z]" src/ --include="*.js" | grep -v "__("
```

See `/i18n-update` for complete workflow.


## Fix 8: wp-env Not Starting

**Symptoms:** `npx wp-env start` fails

**Quick fixes:**

```bash
# Stop all containers
npx wp-env stop

# Remove and restart
npx wp-env destroy
npx wp-env start

# Check Docker is running
docker ps

# If Docker not running, start Docker Desktop

# Check ports aren't in use
lsof -i :8888
lsof -i :8889

# Kill processes using ports if needed
kill -9 PID
```


## General Troubleshooting

**Clear everything and start fresh:**

```bash
# Stop wp-env
npx wp-env stop

# Clear build and dependencies
rm -rf node_modules build

# Reinstall and rebuild
npm install
npm run build

# Restart wp-env
npx wp-env start
```

**Check plugin in browser:**

1. Open browser console (F12)
2. Check for errors in Console tab
3. Check Network tab for failed requests
4. Look for specific error messages


## Before Asking for Help

Run this diagnostic:

```bash
# Check versions
node --version  # Should be 18+ or 20+
npm --version

# Check build status
npm run build

# Check linting
npm run lint:js
npm run lint:css

# Check tests (there is no plain "test" script — use test:unit / test:php / test:e2e)
npm run test:unit

# Check wp-env
npx wp-env start
```


## Reference Commands

For more detailed fixes:

- `/refactor` - Refactor anti-patterns
- `/i18n-update` - Fix translations
- `/check-compat` - Check WordPress compatibility
- `npm run build` - Build troubleshooting (no dedicated skill; see Fix 1 above)
