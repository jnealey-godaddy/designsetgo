---
name: add-extension
description: Create a block extension to enhance core WordPress blocks
argument-hint: [block-name]
allowed-tools: Read, Write, Edit, Glob, Grep, Bash(mkdir *), Bash(npm run *)
---


Create a new block extension to enhance a WordPress core block.

## Ask the User For

- **Which core block to extend?** (e.g., "core/group", "core/heading", "core/button", "core/image")
- **What enhancements to add?** (e.g., "gradient text", "icon support", "hover effects")
- **Needs frontend JavaScript?** (Yes/No for interactivity)

## What Gets Created

1. Extension directory: `src/extensions/[block-name]-enhancements/`
2. `index.js` with WordPress filters
3. `styles.scss` for frontend styles
4. `editor.scss` for editor-only styles
5. `frontend.js` (if interactive features needed)

## Extension Pattern

```javascript
// 1. Add attributes
addFilter('blocks.registerBlockType', 'designsetgo/add-attributes', (settings, name) => {
    if (name !== 'core/group') return settings;

    return {
        ...settings,
        attributes: {
            ...settings.attributes,
            customAttribute: { type: 'string', default: '' }
        }
    };
});

// 2. Add controls (conditionally!)
addFilter('editor.BlockEdit', 'designsetgo/add-controls', createHigherOrderComponent((BlockEdit) => {
    return (props) => {
        if (props.name !== 'core/group') return <BlockEdit {...props} />;

        // Show controls conditionally based on WordPress state
        // e.g., only show grid controls when layout.type === 'grid'

        return (
            <>
                <BlockEdit {...props} />
                <InspectorControls>
                    {/* Your controls */}
                </InspectorControls>
            </>
        );
    };
}));

// 3. Add classes on save
addFilter('blocks.getSaveContent.extraProps', 'designsetgo/add-classes', (props, block, attributes) => {
    if (block.name !== 'core/group') return props;

    return {
        ...props,
        className: classnames(props.className, 'custom-class')
    };
});
```

## CRITICAL: Extension vs Custom Block Decision

| Use Extension When | Use Custom Block When |
|-------------------|----------------------|
| ≤3 simple controls | Complex functionality |
| No DOM restructuring | Need video/media backgrounds |
| CSS-only changes | Interactive components (tabs, accordion) |
| Enhancing existing blocks | Unique UI patterns |

**For DesignSetGo:** Default to custom blocks for unique features. Extensions only for simple enhancements.

## After Creation

Import and initialize in `src/index.js`:

```javascript
import './extensions/group-enhancements';
```

## Patterns to Follow

**From [CLAUDE.md](../../claude.md):**
- Work WITH WordPress attributes, don't replace them
- Show controls conditionally based on WordPress state
- Don't duplicate WordPress toolbar functionality
- Use WordPress layout system when extending Group block

## Reference

Run `/review-pr` before opening a PR.
