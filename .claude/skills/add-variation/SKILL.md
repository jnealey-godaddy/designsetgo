---
name: add-variation
description: Create a block variation with preset configurations
argument-hint: [block-name]
allowed-tools: Read, Write, Edit, Glob, Grep, Bash(mkdir *), Bash(npm run *)
---


Create a new block variation, for either a WordPress core block or an existing DesignSetGo block.

## Pre-flight: is a variation the right call?

Per `.claude/claude.md`'s "Variations vs. new blocks": if the idea would differ from an existing block only by 1–3 attributes **and share the same `save()` output structure**, a variation is correct. If markup, inner-block structure, or block-level behaviour actually differs, scaffold a new block instead (`/add-block`) — variations cannot carry differing markup.

## Ask the User For

- **Which block?** (e.g., "core/group", "core/columns", "core/cover", or an existing `designsetgo/*` block)
- **Variation name and description**
- **Default layout and attributes**
- **Icon** (from WordPress Dashicons, or a Dashicon slug for a DSGo block)

## What Gets Created

For an existing **DesignSetGo** block, DesignSetGo's own convention is a `variations.js` file colocated with the block, not a separate directory. Two registration styles both exist in the codebase — either is fine, pick whichever the target block's `index.js` already uses:

1. `src/blocks/[block-name]/variations.js` — exports the variations array (create if it doesn't exist yet)
2. `src/blocks/[block-name]/index.js` — either:
   - imports it (`import variations from './variations';`) and passes `variations` into the settings object given to `registerBlockType(metadata.name, { ...metadata, variations, ... })` — see `src/blocks/modal/index.js` + `src/blocks/modal/variations.js`; or
   - imports it and loops `variations.forEach((variation) => registerBlockVariation(metadata.name, variation))` after `registerBlockType()` — see `src/blocks/query-filter/index.js` + `src/blocks/query-filter/variations.js`, or `src/blocks/query-pagination/`, `src/blocks/slider/index.js` (inline, no separate file)

For a genuine **WordPress core block** (`core/group`, `core/columns`, etc.), call `registerBlockVariation('core/group', {...})` directly — there is currently no example of this in the codebase (every `registerBlockVariation` call here targets the DSGo block's own `metadata.name`), so treat the pattern below as standard WordPress usage, not a verified in-repo example, and place the call in the extension file that already targets that core block (see `/add-extension`).

## Variation Pattern

```javascript
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

registerBlockVariation('core/group', {
    name: 'hero-section',
    title: __('Hero Section', 'designsetgo'),
    description: __('Full-width hero section with centered content', 'designsetgo'),
    icon: 'cover-image',
    scope: ['inserter'],
    attributes: {
        layout: {
            type: 'constrained',
            contentSize: '800px',
        },
        style: {
            spacing: {
                padding: {
                    top: 'var(--wp--preset--spacing--70)',
                    bottom: 'var(--wp--preset--spacing--70)',
                },
            },
        },
    },
    innerBlocks: [
        ['core/heading', { level: 1, content: __('Hero Title', 'designsetgo') }],
        ['core/paragraph', { content: __('Hero description', 'designsetgo') }],
    ],
});
```

## Use WordPress Native Layout Attributes

**For Grid:**
```javascript
layout: { type: 'grid', columnCount: 3 }
```

**For Flex:**
```javascript
layout: { type: 'flex', orientation: 'horizontal' }
```

**For Constrained (default):**
```javascript
layout: { type: 'constrained', contentSize: '800px' }
```

## Best Practices

**Always:**
- Use WordPress spacing presets: `var(--wp--preset--spacing--50)`
- Never hardcode colors or spacing values
- Provide meaningful `innerBlocks` examples
- Keep variations focused (< 5 per block type)

**Variation Scope:**
- `['inserter']` - Appears in block inserter
- `['block']` - Appears in block transforms
- `['inserter', 'block']` - Appears in both

## After Creation

Build the plugin:

```bash
npm run build
```

Variations will appear in the block inserter under the parent block.
