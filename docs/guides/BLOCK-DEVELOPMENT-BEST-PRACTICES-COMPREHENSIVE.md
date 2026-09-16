# WordPress Block Development Best Practices - Comprehensive Guide

**Version:** 1.0
**Date:** October 24, 2025
**Status:** Reference Documentation
**For:** DesignSetGo Blocks (Custom Block Library)
**Based on:** Official WordPress documentation, community best practices, and lessons learned from block refactoring

> **Superseded implementation pattern:** this guide's `PanelBody` inspector-control examples predate the Theme 3 Inspector IA. Current blocks wrap every custom control in `<DsgoInspectorPanel>` / `<DsgoInspectorPanel.Item>` (Settings → Style → Advanced) instead — see `../../.claude/CLAUDE.md` ("Inspector IA (Theme 3)"). The architectural guidance elsewhere in this doc (useInnerBlocksProps, attribute design, a11y, performance) remains current.

---

## Table of Contents

1. [Block Architecture & Structure](#1-block-architecture--structure)
2. [React Patterns in WordPress](#2-react-patterns-in-wordpress)
3. [Attribute Design](#3-attribute-design)
4. [Styling Best Practices](#4-styling-best-practices)
5. [Accessibility (a11y)](#5-accessibility-a11y)
6. [Performance](#6-performance)
7. [InnerBlocks Patterns](#7-innerblocks-patterns)
8. [Dynamic vs Static Blocks](#8-dynamic-vs-static-blocks)
9. [Block Variations & Transforms](#9-block-variations--transforms)
10. [Block Supports API](#10-block-supports-api)
11. [Testing](#11-testing)
12. [Common Pitfalls & Anti-Patterns](#12-common-pitfalls--anti-patterns)
13. [WordPress Coding Standards](#13-wordpress-coding-standards)
14. [Internationalization (i18n)](#14-internationalization-i18n)
15. [Security](#15-security)

---

## 1. Block Architecture & Structure

### When to Create Custom Blocks vs Extend Core Blocks

**Create Custom Blocks When:**
- Functionality is completely unique (tabs, accordion, timeline)
- You need full rendering control (video backgrounds, complex state)
- Core has no equivalent functionality
- You need complex parent-child relationships
- Feature requires custom save output

**Extend Core Blocks When:**
- Adding simple enhancements (≤3 controls)
- No DOM restructuring needed
- Pure CSS or simple data attributes
- Enhancing existing core block behavior
- Don't need state management

**Decision Matrix:**

| Criteria | Extension | Custom Block |
|----------|-----------|--------------|
| Complexity | ≤3 controls | Any complexity |
| DOM Changes | None | Any changes needed |
| State Management | None | Full React state |
| Video/Media | ❌ No | ✅ Yes |
| Interactions | Simple hover | Complex (tabs, accordion) |
| Animations | CSS only | JavaScript animations |
| Maintenance | Can break on WP updates | Stable, independent |

### block.json Best Practices

**Complete block.json Structure:**

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "designsetgo/container",
  "version": "1.0.0",
  "title": "Container",
  "category": "designsetgo",
  "description": "Advanced container block with layouts, backgrounds, and effects",
  "keywords": ["container", "layout", "section"],
  "textdomain": "designsetgo",

  "supports": {
    "anchor": true,
    "align": ["wide", "full"],
    "html": false,
    "inserter": true,
    "layout": {
      "allowSwitching": true,
      "allowInheriting": false,
      "allowEditing": true,
      "allowSizingOnChildren": true,
      "default": {
        "type": "flex",
        "orientation": "vertical"
      }
    },
    "spacing": {
      "margin": true,
      "padding": true,
      "blockGap": true,
      "__experimentalDefaultControls": {
        "padding": true,
        "blockGap": true
      }
    },
    "dimensions": {
      "minHeight": true
    },
    "color": {
      "background": true,
      "text": true,
      "gradients": true,
      "link": true,
      "__experimentalDefaultControls": {
        "background": true,
        "text": true
      }
    },
    "background": {
      "backgroundImage": true,
      "backgroundSize": true
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalDefaultControls": {
        "fontSize": true
      }
    },
    "shadow": true,
    "position": {
      "sticky": true
    },
    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true,
      "__experimentalDefaultControls": {
        "color": true,
        "radius": true,
        "style": true,
        "width": true
      }
    }
  },

  "attributes": {
    "layoutType": {
      "type": "string",
      "default": "stack"
    }
  },

  "providesContext": {
    "designsetgo/containerLayout": "layoutType"
  },

  "example": {
    "attributes": {
      "layout": {
        "type": "flex",
        "orientation": "vertical"
      }
    },
    "innerBlocks": [
      {
        "name": "core/heading",
        "attributes": {
          "level": 2,
          "content": "Container Example"
        }
      },
      {
        "name": "core/paragraph",
        "attributes": {
          "content": "This is an example of the container block."
        }
      }
    ]
  },

  "editorScript": "file:./index.js",
  "editorStyle": "file:./index.css",
  "style": "file:./style-index.css",
  "viewScript": "file:./view.js"
}
```

**Key Properties Explained:**

- `apiVersion: 3` - Latest block API (always use latest)
- `$schema` - Enables IDE autocomplete and validation
- `textdomain` - Required for translations
- `example` - Required for block preview in inserter and patterns
- `providesContext` - Share data with child blocks
- `supports` - Enable WordPress core features (spacing, colors, etc.)
- `__experimentalDefaultControls` - Set which panels are open by default

### File Structure Recommendations

**Standard Block Structure:**

```
src/blocks/container/
├── block.json              ← Block metadata (MUST include)
├── index.js                ← Block registration
├── edit.js                 ← Edit component (React)
├── save.js                 ← Save function (HTML output)
├── attributes.js           ← Attribute schema (optional, for complex blocks)
├── style.scss              ← Frontend styles
├── editor.scss             ← Editor-only styles
├── view.js                 ← Frontend JavaScript (optional)
├── components/             ← Block-specific components
│   ├── LayoutControl.js
│   └── BackgroundControl.js
├── hooks/                  ← Block-specific hooks
│   └── useLayoutStyles.js
└── README.md               ← Block documentation
```

### Naming Conventions

**Block Names:**
- Format: `namespace/block-name`
- Example: `designsetgo/container`, `designsetgo/advanced-heading`
- Use lowercase with hyphens
- Namespace prevents conflicts

**Attributes:**
- Use camelCase: `gridColumns`, `videoUrl`, `enableOverlay`
- Prefix custom attributes: `dsgGridColumns` (if extending core blocks)
- Boolean attributes: Start with `enable`, `is`, `has`, `show`

**CSS Classes:**
- BEM methodology: `.block-name__element--modifier`
- Prefix with namespace: `.dsgo-container__inner`
- Use semantic names: `.dsgo-container__video-background`

**PHP Functions/Classes:**
- PSR-4 autoloading
- Class names: `DesignSetGo_Container`
- Function names: `designsetgo_register_container`

---

## 2. React Patterns in WordPress

### Correct Use of WordPress Hooks

#### useBlockProps (CRITICAL)

**Always use `useBlockProps` for the outermost wrapper:**

```javascript
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit({ attributes }) {
  const blockProps = useBlockProps({
    className: classnames('dsgo-container', {
      'has-video-background': attributes.videoUrl,
    }),
    style: {
      backgroundColor: attributes.backgroundColor,
    }
  });

  return <div {...blockProps}>Content here</div>;
}
```

**Why:**
- Integrates with WordPress editor features (block toolbar, settings)
- Handles block selection, focus, and dragging
- Applies WordPress-managed classes and attributes
- Required for proper block functionality

**Save function variant:**

```javascript
export default function Save({ attributes }) {
  const blockProps = useBlockProps.save({
    className: 'dsgo-container'
  });

  return <div {...blockProps}>Content here</div>;
}
```

#### useInnerBlocksProps (CRITICAL for Nested Blocks)

**ALWAYS use `useInnerBlocksProps` instead of plain `<InnerBlocks />`:**

```javascript
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function Edit({ attributes }) {
  const { layoutType, gridColumns, gap } = attributes;

  // Calculate styles declaratively
  const innerStyles = {
    display: layoutType === 'grid' ? 'grid' : 'flex',
    gridTemplateColumns: layoutType === 'grid' ? `repeat(${gridColumns}, 1fr)` : undefined,
    gap: gap,
  };

  const blockProps = useBlockProps();

  // Apply styles to inner blocks container
  const innerBlocksProps = useInnerBlocksProps(
    {
      className: 'dsgo-container__inner',
      style: innerStyles,
    },
    {
      allowedBlocks: ['core/heading', 'core/paragraph'],
      template: [
        ['core/heading', { level: 2, placeholder: 'Enter heading...' }],
        ['core/paragraph', { placeholder: 'Enter content...' }]
      ],
      templateLock: false,
      orientation: layoutType === 'flex' ? 'horizontal' : undefined,
    }
  );

  return (
    <div {...blockProps}>
      {/* NO wrapper div around innerBlocksProps */}
      <div {...innerBlocksProps} />
    </div>
  );
}
```

**Save function:**

```javascript
export default function Save({ attributes }) {
  const innerStyles = {
    display: attributes.layoutType === 'grid' ? 'grid' : 'flex',
    gridTemplateColumns: attributes.layoutType === 'grid'
      ? `repeat(${attributes.gridColumns}, 1fr)`
      : undefined,
    gap: attributes.gap,
  };

  const blockProps = useBlockProps.save();
  const innerBlocksProps = useInnerBlocksProps.save({
    className: 'dsgo-container__inner',
    style: innerStyles,
  });

  return (
    <div {...blockProps}>
      <div {...innerBlocksProps} />
    </div>
  );
}
```

**Why this matters:**
- Plain `<InnerBlocks />` adds extra wrapper divs that break CSS Grid/Flexbox
- Causes editor/frontend markup mismatch
- Breaks block inserter and appender functionality
- WordPress core blocks ALL use `useInnerBlocksProps`

#### useSelect (Data Fetching)

**Pattern for efficient data fetching:**

```javascript
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

export default function Edit({ clientId }) {
  // Group store requests from same store
  const { hasInnerBlocks, blockCount, parentBlockId } = useSelect(
    (select) => {
      const { getBlockCount, getBlockParents } = select(blockEditorStore);
      return {
        hasInnerBlocks: getBlockCount(clientId) > 0,
        blockCount: getBlockCount(clientId),
        parentBlockId: getBlockParents(clientId)[0],
      };
    },
    [clientId] // Dependencies
  );

  // Use the data
  return <div>Block count: {blockCount}</div>;
}
```

**Best practices:**
- Group requests from the same store in one `useSelect`
- Include dependencies array (second parameter)
- Use granular selectors (don't fetch unnecessary data)
- Separate `useSelect` calls for different stores

#### useDispatch (State Updates)

```javascript
import { useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

export default function Edit({ clientId }) {
  const { insertBlocks, removeBlock } = useDispatch(blockEditorStore);

  const addNewBlock = () => {
    const newBlock = createBlock('core/paragraph');
    insertBlocks(newBlock, undefined, clientId);
  };

  return <button onClick={addNewBlock}>Add Paragraph</button>;
}
```

### When to Use useEffect vs When NOT To

**❌ NEVER use `useEffect` for:**
- Applying styles (use declarative props instead)
- DOM manipulation (use React props)
- Calculating derived state (calculate directly)
- Anything in the save function (save must be pure)

**✅ DO use `useEffect` for:**
- Side effects (API calls, subscriptions)
- Browser-only APIs (Intersection Observer, ResizeObserver)
- Cleanup operations (event listeners)
- External library initialization

**Anti-Pattern (WRONG):**

```javascript
// ❌ DON'T DO THIS
useEffect(() => {
  const element = document.querySelector(`[data-block="${clientId}"]`);
  element.style.display = 'grid';
}, [clientId, layoutType]);
```

**Correct Pattern:**

```javascript
// ✅ DO THIS
const blockProps = useBlockProps({
  style: {
    display: 'grid',
  }
});
```

**Valid useEffect example:**

```javascript
// ✅ This is OK - browser API that requires DOM
useEffect(() => {
  const container = containerRef.current;
  if (!container) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        // Trigger animation
      }
    });
  });

  observer.observe(container);

  return () => observer.disconnect(); // Cleanup
}, []);
```

### State Management Best Practices

**Local state with useState:**

```javascript
import { useState } from '@wordpress/element';

export default function Edit({ attributes, setAttributes }) {
  // Ephemeral UI state (doesn't save to database)
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Block state (saves to database)
  const { heading } = attributes;

  return (
    <>
      <input
        value={heading}
        onChange={(e) => setAttributes({ heading: e.target.value })}
      />
      <button onClick={() => setIsModalOpen(true)}>Open Modal</button>
      {isModalOpen && <Modal onClose={() => setIsModalOpen(false)} />}
    </>
  );
}
```

**When to use:**
- `useState`: Temporary UI state (modals, tabs, dropdowns)
- `attributes`: Data that must persist and save to post

### Component Composition Patterns

**Extract reusable components:**

```javascript
// components/ResponsiveControl.js
export function ResponsiveControl({ label, value, onChange }) {
  const [device, setDevice] = useState('desktop');

  return (
    <PanelBody title={label}>
      <ButtonGroup>
        <Button onClick={() => setDevice('desktop')}>Desktop</Button>
        <Button onClick={() => setDevice('tablet')}>Tablet</Button>
        <Button onClick={() => setDevice('mobile')}>Mobile</Button>
      </ButtonGroup>
      <RangeControl
        value={value[device]}
        onChange={(val) => onChange({ ...value, [device]: val })}
      />
    </PanelBody>
  );
}

// In edit.js
import { ResponsiveControl } from './components/ResponsiveControl';

<ResponsiveControl
  label="Padding"
  value={attributes.padding}
  onChange={(padding) => setAttributes({ padding })}
/>
```

---

## 3. Attribute Design

### Attribute Types and When to Use Each

```json
{
  "attributes": {
    "text": {
      "type": "string",
      "default": ""
    },
    "isEnabled": {
      "type": "boolean",
      "default": false
    },
    "count": {
      "type": "number",
      "default": 3
    },
    "items": {
      "type": "array",
      "default": []
    },
    "settings": {
      "type": "object",
      "default": {}
    },
    "content": {
      "type": "string",
      "source": "html",
      "selector": "p"
    },
    "imageUrl": {
      "type": "string",
      "source": "attribute",
      "selector": "img",
      "attribute": "src"
    }
  }
}
```

**Type Guidelines:**

| Type | Use For | Example |
|------|---------|---------|
| `string` | Text, URLs, IDs | `"videoUrl"`, `"anchorId"` |
| `boolean` | Toggles, flags | `"enableOverlay"`, `"isClickable"` |
| `number` | Counts, sizes, percentages | `"gridColumns"`, `"fontSize"` |
| `array` | Lists, collections | `"items"`, `"allowedBlocks"` |
| `object` | Complex nested data | `"responsivePadding": { desktop: 20, tablet: 15, mobile: 10 }` |

### Default Values Strategy

**Always provide sensible defaults:**

```json
{
  "attributes": {
    "gridColumns": {
      "type": "number",
      "default": 3
    },
    "gridColumnsTablet": {
      "type": "number",
      "default": 2
    },
    "gridColumnsMobile": {
      "type": "number",
      "default": 1
    },
    "gap": {
      "type": "string",
      "default": "20px"
    },
    "backgroundColor": {
      "type": "string",
      "default": ""
    },
    "videoUrl": {
      "type": "string",
      "default": ""
    },
    "enableOverlay": {
      "type": "boolean",
      "default": false
    }
  }
}
```

**Why defaults matter:**
- Blocks work immediately without configuration
- Prevents `undefined` values
- Cleaner conditional logic
- Better user experience

### Nested Attributes vs Flat Structure

**Flat (Recommended for simple cases):**

```json
{
  "paddingTop": { "type": "string", "default": "20px" },
  "paddingRight": { "type": "string", "default": "20px" },
  "paddingBottom": { "type": "string", "default": "20px" },
  "paddingLeft": { "type": "string", "default": "20px" }
}
```

**Nested (Better for complex/responsive):**

```json
{
  "padding": {
    "type": "object",
    "default": {
      "desktop": { "top": "20px", "right": "20px", "bottom": "20px", "left": "20px" },
      "tablet": { "top": "15px", "right": "15px", "bottom": "15px", "left": "15px" },
      "mobile": { "top": "10px", "right": "10px", "bottom": "10px", "left": "10px" }
    }
  }
}
```

**When to use nested:**
- Responsive values (desktop/tablet/mobile)
- Grouped settings (typography: font, size, weight, lineHeight)
- Complex configurations

### Migration Strategies for Attribute Changes

**Use the `deprecated` property for breaking changes:**

```javascript
// block.json remains the same with NEW attributes

// In index.js
import deprecated from './deprecated';

registerBlockType('designsetgo/container', {
  ...metadata,
  edit: Edit,
  save: Save,
  deprecated: deprecated, // Array of old versions
});

// deprecated.js
export default [
  // Most recent deprecated version first
  {
    attributes: {
      // OLD attribute structure
      columns: {
        type: 'number',
        default: 3
      }
    },
    migrate(attributes) {
      // Convert old attributes to new
      return {
        gridColumns: attributes.columns,
        gridColumnsTablet: 2,
        gridColumnsMobile: 1,
      };
    },
    save(props) {
      // OLD save function
      return <div className="old-structure">...</div>;
    }
  },
  // Older versions...
];
```

**Migration workflow:**
1. Add new attributes to block.json
2. Create deprecated entry with old save + migrate function
3. WordPress automatically migrates old blocks when loaded

---

## 4. Styling Best Practices

### Inline Styles vs CSS Classes

**Decision Matrix:**

| Use Case | Method | Example |
|----------|--------|---------|
| User-controlled values | Inline styles | `backgroundColor`, `fontSize`, `padding` |
| Responsive behavior | CSS classes + media queries | `.dsgo-container--tablet` |
| Theme variations | CSS classes | `.dsgo-button--primary` |
| State indicators | CSS classes | `.is-selected`, `.has-overlay` |
| Fixed design patterns | CSS classes | `.dsgo-grid`, `.dsgo-flex` |

**Inline styles (user-controlled):**

```javascript
const blockProps = useBlockProps({
  style: {
    backgroundColor: attributes.backgroundColor,
    padding: `${attributes.padding}px`,
    minHeight: attributes.minHeight,
  }
});
```

**CSS classes (design patterns):**

```scss
.dsgo-container {
  &--card {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }

  &--elevated {
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
  }

  &.has-overlay {
    position: relative;

    &::before {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(0,0,0,0.5);
    }
  }
}
```

### Editor Styles vs Frontend Styles

**Editor styles (editor.scss):**
```scss
// Editor-specific overrides
.dsgo-container {
  // Show grid outline in editor
  &.is-selected .dsgo-container__inner {
    outline: 1px dashed #ccc;
  }

  // Video placeholder in editor
  .dsgo-video-background-placeholder {
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 200px;
  }
}
```

**Frontend styles (style.scss):**
```scss
// Actual frontend styles
.dsgo-container {
  position: relative;

  &__inner {
    position: relative;
    z-index: 2;
  }

  &__video-background {
    position: absolute;
    inset: 0;
    overflow: hidden;
    z-index: 1;

    video {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }
}
```

### CSS Specificity and !important Usage

**When to use `!important`:**

✅ **Acceptable cases:**
1. User-chosen features must override theme (responsive grid columns)
2. Accessibility requirements (contrast, text color on overlays)
3. Overriding WordPress core styles (last resort)

```scss
// User explicitly chose to hide on mobile
.dsgo-hide-mobile {
  @media (max-width: 767px) {
    display: none !important; // Override any theme styles
  }
}

// Accessibility - ensure readable text on overlay
.has-dsgo-overlay {
  color: #ffffff !important; // Must override theme text color
}
```

❌ **Avoid:**
- Using `!important` for convenience
- Fighting your own CSS specificity
- Applying to reusable utilities

### theme.json Integration

**Define custom settings in theme.json:**

```json
{
  "version": 2,
  "settings": {
    "custom": {
      "designsetgoContainer": {
        "defaultGap": "20px",
        "maxWidth": "1200px"
      }
    },
    "spacing": {
      "units": ["px", "em", "rem", "vh", "vw"],
      "padding": true
    }
  },
  "styles": {
    "blocks": {
      "designsetgo/container": {
        "spacing": {
          "padding": {
            "top": "var(--wp--preset--spacing--50)",
            "bottom": "var(--wp--preset--spacing--50)"
          }
        },
        "color": {
          "background": "var(--wp--preset--color--base)"
        }
      }
    }
  }
}
```

**Access in JavaScript:**

```javascript
import { useSetting } from '@wordpress/block-editor';

export default function Edit() {
  const contentWidth = useSetting('custom.designsetgoContainer.maxWidth') || '1200px';
  const spacingUnits = useSetting('spacing.units') || ['px', 'em', 'rem'];

  return <div style={{ maxWidth: contentWidth }}>...</div>;
}
```

### CSS Custom Properties for Dynamic Values

**Best practice for user-controlled responsive values:**

```javascript
// JavaScript
const innerBlocksProps = useInnerBlocksProps({
  style: {
    '--dsgo-cols-desktop': attributes.gridColumns,
    '--dsgo-cols-tablet': attributes.gridColumnsTablet,
    '--dsgo-cols-mobile': attributes.gridColumnsMobile,
    '--dsgo-gap': attributes.gap,
    display: 'grid',
  }
});
```

```scss
// SCSS
.dsgo-container__inner {
  grid-template-columns: repeat(var(--dsgo-cols-desktop), 1fr);
  gap: var(--dsgo-gap);

  @media (max-width: 1023px) {
    grid-template-columns: repeat(var(--dsgo-cols-tablet), 1fr);
  }

  @media (max-width: 767px) {
    grid-template-columns: repeat(var(--dsgo-cols-mobile), 1fr);
  }
}
```

**Benefits:**
- CSS handles responsive logic (no JavaScript needed)
- Better performance
- Cleaner separation of concerns
- Works without JavaScript

---

## 5. Accessibility (a11y)

### ARIA Attributes

**Semantic HTML first, ARIA when needed:**

```javascript
// ✅ Good - Semantic HTML
<button onClick={handleClick}>Open Menu</button>

// ❌ Bad - Unnecessary ARIA
<div role="button" onClick={handleClick}>Open Menu</div>
```

**ARIA for complex widgets:**

```javascript
// Tabs component
<div role="tablist" aria-label="Content sections">
  <button
    role="tab"
    aria-selected={activeTab === 0}
    aria-controls="panel-0"
    id="tab-0"
  >
    Tab 1
  </button>
</div>
<div
  role="tabpanel"
  aria-labelledby="tab-0"
  id="panel-0"
  hidden={activeTab !== 0}
>
  Content
</div>
```

**Common ARIA patterns:**

| Pattern | Attributes | Example |
|---------|-----------|---------|
| Accordion | `aria-expanded`, `aria-controls` | Collapsible sections |
| Tabs | `role="tablist"`, `aria-selected` | Tab navigation |
| Modal | `role="dialog"`, `aria-modal`, `aria-labelledby` | Overlay dialogs |
| Menu | `role="menu"`, `aria-haspopup` | Dropdown menus |

### Keyboard Navigation

**Ensure all interactive elements are keyboard accessible:**

```javascript
export default function InteractiveBlock() {
  const handleKeyPress = (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      handleClick();
    }
  };

  return (
    <div
      role="button"
      tabIndex={0}
      onClick={handleClick}
      onKeyPress={handleKeyPress}
      aria-label="Clickable container"
    >
      Content
    </div>
  );
}
```

**Focus management:**

```javascript
import { useFocusReturn } from '@wordpress/compose';

export default function Modal({ onClose }) {
  const focusReturnRef = useFocusReturn();

  return (
    <div ref={focusReturnRef} role="dialog" aria-modal="true">
      <button onClick={onClose}>Close</button>
      {/* Focus returns to trigger when closed */}
    </div>
  );
}
```

### Screen Reader Support

**Provide descriptive labels:**

```javascript
<IconButton
  icon="close"
  label="Close modal" // Screen reader text
  onClick={onClose}
/>

<TextControl
  label="Container width"
  help="Maximum width for content area"
  value={width}
  onChange={setWidth}
/>
```

**Hide decorative elements:**

```html
<img src="decorative-divider.svg" alt="" role="presentation" />
<span aria-hidden="true">❤️</span>
```

### Color Contrast Requirements

**WCAG 2.1 AA minimum:**
- Normal text: 4.5:1 contrast ratio
- Large text (18pt+): 3:1 contrast ratio

**Implement contrast checking:**

```javascript
import { contrast } from '@wordpress/compose';

function isContrastSufficient(foreground, background) {
  const ratio = contrast(foreground, background);
  return ratio >= 4.5; // WCAG AA for normal text
}

// Warn user
{!isContrastSufficient(textColor, backgroundColor) && (
  <Notice status="warning">
    Text may not be readable. Consider adjusting colors.
  </Notice>
)}
```

### Focus Management

**Visible focus indicators:**

```scss
.dsgo-button {
  &:focus {
    outline: 2px solid var(--wp--preset--color--primary);
    outline-offset: 2px;
  }

  // Don't remove focus styles!
  &:focus:not(:focus-visible) {
    outline: none; // Only when using mouse
  }

  &:focus-visible {
    outline: 2px solid var(--wp--preset--color--primary);
  }
}
```

---

## 6. Performance

### Bundle Size Optimization

**Set performance budgets:**

```javascript
// webpack.config.js
module.exports = {
  ...defaultConfig,
  performance: {
    maxAssetSize: 100000, // 100KB per file
    maxEntrypointSize: 100000,
    hints: 'error',
  },
};
```

**Analyze bundle size:**

```bash
npm install --save-dev webpack-bundle-analyzer

# Add to webpack config
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;

plugins: [
  new BundleAnalyzerPlugin({
    analyzerMode: 'static',
    openAnalyzer: false,
  })
]

# Run build and view report
npm run build
open build/report.html
```

### Code Splitting Strategies

**Per-block code splitting:**

```javascript
// webpack.config.js
module.exports = {
  entry: {
    'blocks/container': './src/blocks/container/index.js',
    'blocks/tabs': './src/blocks/tabs/index.js',
    'blocks/accordion': './src/blocks/accordion/index.js',
  },
  output: {
    path: path.resolve(__dirname, 'build'),
    filename: '[name].js',
  },
};
```

**Conditional loading in PHP:**

```php
function designsetgo_enqueue_block_assets() {
  // Only load if block is used on page
  if (has_block('designsetgo/container')) {
    wp_enqueue_script(
      'dsgo-container',
      plugins_url('build/blocks/container.js', __FILE__),
      ['wp-blocks', 'wp-element'],
      filemtime(plugin_dir_path(__FILE__) . 'build/blocks/container.js')
    );
  }
}
add_action('enqueue_block_assets', 'designsetgo_enqueue_block_assets');
```

### Lazy Loading Blocks

**Dynamic imports for heavy components:**

```javascript
import { lazy, Suspense } from '@wordpress/element';

const IconPicker = lazy(() => import('./components/IconPicker'));

export default function Edit() {
  const [showIconPicker, setShowIconPicker] = useState(false);

  return (
    <>
      <Button onClick={() => setShowIconPicker(true)}>
        Choose Icon
      </Button>

      {showIconPicker && (
        <Suspense fallback={<Spinner />}>
          <IconPicker onSelect={handleIconSelect} />
        </Suspense>
      )}
    </>
  );
}
```

### Asset Loading Optimization

**Defer non-critical scripts:**

```php
wp_enqueue_script(
  'dsgo-animations',
  plugins_url('build/animations.js', __FILE__),
  ['wp-dom-ready'],
  DESIGNSETGO_VERSION,
  true // Load in footer
);

// Add defer attribute
add_filter('script_loader_tag', function($tag, $handle) {
  if ('dsgo-animations' === $handle) {
    return str_replace(' src', ' defer src', $tag);
  }
  return $tag;
}, 10, 2);
```

### JavaScript Best Practices for Performance

**Avoid memory leaks:**

```javascript
useEffect(() => {
  const handleResize = () => {
    // Handle resize
  };

  window.addEventListener('resize', handleResize);

  // CRITICAL: Clean up
  return () => {
    window.removeEventListener('resize', handleResize);
  };
}, []);
```

**Use passive event listeners:**

```javascript
useEffect(() => {
  const handleScroll = () => {
    // Handle scroll
  };

  window.addEventListener('scroll', handleScroll, { passive: true });

  return () => {
    window.removeEventListener('scroll', handleScroll);
  };
}, []);
```

**Debounce expensive operations:**

```javascript
import { debounce } from '@wordpress/compose';

const debouncedUpdate = useMemo(
  () => debounce((value) => {
    setAttributes({ searchQuery: value });
  }, 300),
  []
);

<TextControl
  onChange={debouncedUpdate}
/>
```

---

## 7. InnerBlocks Patterns

### Parent/Child Block Relationships

**Parent block provides context:**

```json
// parent-block/block.json
{
  "providesContext": {
    "designsetgo/parentId": "uniqueId",
    "designsetgo/layout": "layoutType"
  }
}
```

```javascript
// parent-block/edit.js
export default function Edit({ attributes, setAttributes }) {
  const innerBlocksProps = useInnerBlocksProps(
    {},
    {
      allowedBlocks: ['designsetgo/child-block'],
      template: [['designsetgo/child-block']],
    }
  );

  return <div {...innerBlocksProps} />;
}
```

**Child block uses context:**

```json
// child-block/block.json
{
  "usesContext": ["designsetgo/parentId", "designsetgo/layout"]
}
```

```javascript
// child-block/edit.js
export default function Edit({ context }) {
  const parentId = context['designsetgo/parentId'];
  const parentLayout = context['designsetgo/layout'];

  // Adapt child based on parent
  return <div>Child in {parentLayout} layout</div>;
}
```

### Allowed Blocks Configuration

```javascript
const innerBlocksProps = useInnerBlocksProps(
  {},
  {
    // Only allow specific blocks
    allowedBlocks: [
      'core/heading',
      'core/paragraph',
      'core/image',
      'designsetgo/custom-block'
    ],

    // OR define orientation
    orientation: 'horizontal', // or 'vertical'
  }
);
```

### Template and templateLock

**Pre-populate with template:**

```javascript
const TEMPLATE = [
  ['core/heading', { level: 2, placeholder: 'Enter heading...' }],
  ['core/paragraph', { placeholder: 'Enter description...' }],
  ['core/buttons', {}, [
    ['core/button', { text: 'Learn More' }]
  ]]
];

const innerBlocksProps = useInnerBlocksProps(
  {},
  {
    template: TEMPLATE,
    templateLock: false, // 'all', 'insert', 'contentOnly', false
  }
);
```

**Template lock options:**

| Value | Behavior |
|-------|----------|
| `'all'` | Can't add, remove, or move blocks |
| `'insert'` | Can't add or remove, but can reorder |
| `'contentOnly'` | Can only edit text content |
| `false` | Full editing freedom |

### Nested Blocks Best Practices

**Accessing children from parent:**

```javascript
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

export default function Edit({ clientId }) {
  const childBlocks = useSelect(
    (select) => select(blockEditorStore).getBlocks(clientId),
    [clientId]
  );

  const childCount = childBlocks.length;

  return (
    <div>
      <p>{childCount} child blocks</p>
      <div {...useInnerBlocksProps()} />
    </div>
  );
}
```

---

## 8. Dynamic vs Static Blocks

### When to Use Server-Side Rendering

**Use dynamic blocks (render_callback) when:**
- Data changes frequently (latest posts, user data)
- Need PHP processing (database queries, user permissions)
- Content from external APIs
- Performance-sensitive (pre-render HTML server-side)
- SEO-critical content that must be in initial HTML

**Use static blocks (save function) when:**
- Content is static/user-defined
- No server-side logic needed
- Better caching (HTML stored in post_content)
- Simpler architecture

### render_callback Pattern

**Dynamic block registration:**

```php
// In PHP
register_block_type(__DIR__ . '/build/blocks/latest-posts', [
  'render_callback' => 'designsetgo_render_latest_posts',
]);

function designsetgo_render_latest_posts($attributes) {
  $posts = get_posts([
    'posts_per_page' => $attributes['postsToShow'] ?? 5,
    'post_type' => 'post',
  ]);

  ob_start();
  ?>
  <div class="dsgo-latest-posts">
    <?php foreach ($posts as $post): ?>
      <article>
        <h3><?php echo esc_html($post->post_title); ?></h3>
        <div><?php echo wp_kses_post(get_the_excerpt($post)); ?></div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php
  return ob_get_clean();
}
```

**JavaScript (no save function):**

```javascript
// block.json
{
  "apiVersion": 3,
  "name": "designsetgo/latest-posts",
  // No "editorScript" field - PHP handles output
}

// edit.js
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit({ attributes }) {
  return (
    <>
      <InspectorControls>
        <RangeControl
          label="Posts to show"
          value={attributes.postsToShow}
          onChange={(postsToShow) => setAttributes({ postsToShow })}
        />
      </InspectorControls>

      <ServerSideRender
        block="designsetgo/latest-posts"
        attributes={attributes}
      />
    </>
  );
}

// NO save.js needed - PHP renders everything
```

### REST API Integration

**Fetch data in editor:**

```javascript
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

export default function Edit() {
  const [posts, setPosts] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    apiFetch({ path: '/wp/v2/posts?per_page=5' })
      .then((data) => {
        setPosts(data);
        setIsLoading(false);
      })
      .catch(() => setIsLoading(false));
  }, []);

  if (isLoading) return <Spinner />;

  return (
    <ul>
      {posts.map(post => (
        <li key={post.id}>{post.title.rendered}</li>
      ))}
    </ul>
  );
}
```

### Performance Implications

**Static blocks:**
- ✅ Fastest (HTML already in database)
- ✅ Best for caching
- ✅ No PHP execution on page load
- ❌ Content can become stale
- ❌ Can't access server data

**Dynamic blocks:**
- ❌ Slower (PHP executes on every page load)
- ✅ Always fresh data
- ✅ Can access database, user data, etc.
- ❌ More server load
- ✅ Can cache with object caching

---

## 9. Block Variations & Transforms

### Creating Block Variations

**Register variations:**

```javascript
import { registerBlockVariation } from '@wordpress/blocks';

registerBlockVariation('designsetgo/container', {
  name: 'hero-section',
  title: 'Hero Section',
  description: 'Full-width hero with centered content',
  icon: 'cover-image',
  attributes: {
    align: 'full',
    layoutType: 'stack',
    minHeight: '500px',
    constrainWidth: true,
    contentWidth: '800px',
  },
  innerBlocks: [
    ['core/heading', {
      level: 1,
      placeholder: 'Hero Heading',
      textAlign: 'center'
    }],
    ['core/paragraph', {
      placeholder: 'Hero description',
      textAlign: 'center'
    }],
    ['core/buttons', {
      layout: { type: 'flex', justifyContent: 'center' }
    }]
  ],
  scope: ['inserter'],
  isActive: (blockAttributes, variationAttributes) => {
    return blockAttributes.layoutType === variationAttributes.layoutType;
  },
});
```

### Block Transforms

**Transform from/to other blocks:**

```javascript
// In block registration
{
  transforms: {
    from: [
      {
        type: 'block',
        blocks: ['core/group'],
        transform: (attributes, innerBlocks) => {
          return createBlock(
            'designsetgo/container',
            {
              layoutType: 'stack',
              // Map attributes
            },
            innerBlocks
          );
        },
      },
      {
        type: 'shortcode',
        tag: 'container',
        attributes: {
          layoutType: {
            type: 'string',
            shortcode: (attrs) => attrs.named.layout || 'stack',
          },
        },
      }
    ],
    to: [
      {
        type: 'block',
        blocks: ['core/group'],
        transform: (attributes, innerBlocks) => {
          return createBlock('core/group', {}, innerBlocks);
        },
      }
    ]
  }
}
```

### isEligible and isActive

**isEligible - Control when variation appears:**

```javascript
registerBlockVariation('core/embed', {
  name: 'youtube',
  title: 'YouTube',
  isActive: ['providerNameSlug'],
  patterns: [
    /^https?:\/\/(www\.)?youtube\.com\/.+/i,
    /^https?:\/\/youtu\.be\/.+/i,
  ],
});
```

**isActive - Determine which variation is selected:**

```javascript
isActive: (blockAttributes, variationAttributes) => {
  // Simple - check one attribute
  return blockAttributes.style === variationAttributes.style;

  // Complex - check multiple conditions
  return (
    blockAttributes.layoutType === variationAttributes.layoutType &&
    blockAttributes.align === variationAttributes.align
  );

  // Array of attribute names (shorthand)
  // isActive: ['layoutType', 'align']
}
```

---

## 10. Block Supports API

### Core Supports Reference

```json
{
  "supports": {
    "anchor": true,
    "align": true,
    "alignWide": true,
    "ariaLabel": true,
    "className": true,
    "customClassName": true,
    "html": false,
    "inserter": true,
    "multiple": true,
    "reusable": true,
    "lock": true,

    "color": {
      "background": true,
      "text": true,
      "gradients": true,
      "link": true,
      "heading": true,
      "button": true,
      "enableContrastChecker": true,
      "__experimentalDuotone": true,
      "__experimentalDefaultControls": {
        "background": true,
        "text": true
      }
    },

    "spacing": {
      "margin": true,
      "padding": true,
      "blockGap": true,
      "__experimentalDefaultControls": {
        "padding": true,
        "blockGap": true
      }
    },

    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "fontFamily": true,
      "fontWeight": true,
      "fontStyle": true,
      "textTransform": true,
      "textDecoration": true,
      "letterSpacing": true,
      "__experimentalFontSize": true,
      "__experimentalTextDecoration": true,
      "__experimentalDefaultControls": {
        "fontSize": true
      }
    },

    "layout": {
      "allowSwitching": true,
      "allowInheriting": false,
      "allowEditing": true,
      "allowSizingOnChildren": true,
      "default": {
        "type": "flex",
        "orientation": "vertical"
      }
    },

    "dimensions": {
      "minHeight": true,
      "aspectRatio": true
    },

    "background": {
      "backgroundImage": true,
      "backgroundSize": true,
      "__experimentalDefaultControls": {
        "backgroundImage": true
      }
    },

    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true,
      "__experimentalDefaultControls": {
        "color": true,
        "radius": true,
        "style": true,
        "width": true
      }
    },

    "shadow": true,

    "position": {
      "sticky": true
    }
  }
}
```

### __experimentalDefaultControls

**Control which panels are open by default:**

```json
{
  "supports": {
    "color": {
      "background": true,
      "text": true,
      "gradients": true,
      "__experimentalDefaultControls": {
        "background": true,
        "text": false
      }
    }
  }
}
```

Users see background color panel open, text color collapsed.

### Custom Supports

**Add custom block-specific supports:**

```json
{
  "supports": {
    "__experimentalSelector": ".dsgo-container__inner",
    "interactivity": {
      "clientNavigation": true
    }
  }
}
```

---

## 11. Testing

### Unit Testing Blocks

**Test block registration:**

```javascript
// __tests__/container.test.js
import { registerBlockType } from '@wordpress/blocks';
import metadata from '../block.json';
import Edit from '../edit';
import Save from '../save';

describe('Container block', () => {
  it('registers successfully', () => {
    const block = registerBlockType(metadata.name, {
      ...metadata,
      edit: Edit,
      save: Save,
    });

    expect(block).toBeDefined();
    expect(block.name).toBe('designsetgo/container');
  });

  it('has required attributes', () => {
    const block = registerBlockType(metadata.name, metadata);

    expect(block.attributes.layoutType).toBeDefined();
    expect(block.attributes.gridColumns).toBeDefined();
  });
});
```

**Test components:**

```javascript
import { render, screen } from '@testing-library/react';
import Edit from '../edit';

describe('Container Edit', () => {
  it('renders without crashing', () => {
    const { container } = render(
      <Edit
        attributes={{}}
        setAttributes={jest.fn()}
      />
    );

    expect(container).toBeInTheDocument();
  });

  it('displays grid layout controls when grid is selected', () => {
    render(
      <Edit
        attributes={{ layoutType: 'grid' }}
        setAttributes={jest.fn()}
      />
    );

    expect(screen.getByText('Grid Columns')).toBeInTheDocument();
  });
});
```

### E2E Testing with Playwright

**Setup Playwright:**

```javascript
// playwright.config.js
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  use: {
    baseURL: 'http://localhost:8889',
  },
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
});
```

**E2E test example:**

```javascript
// tests/e2e/container-block.spec.js
import { test, expect } from '@playwright/test';
import { createNewPost, insertBlock } from '@wordpress/e2e-test-utils-playwright';

test.describe('Container Block', () => {
  test.beforeEach(async ({ page }) => {
    await createNewPost();
  });

  test('inserts container block', async ({ page }) => {
    await insertBlock('Container');

    const block = page.locator('[data-type="designsetgo/container"]');
    await expect(block).toBeVisible();
  });

  test('changes layout to grid', async ({ page }) => {
    await insertBlock('Container');

    // Open settings
    await page.click('[aria-label="Settings"]');

    // Change to grid
    await page.selectOption('select[aria-label="Layout Type"]', 'grid');

    // Verify grid is applied
    const inner = page.locator('.dsgo-container__inner');
    await expect(inner).toHaveCSS('display', 'grid');
  });

  test('adds inner blocks', async ({ page }) => {
    await insertBlock('Container');

    // Click add block button inside container
    await page.click('.block-list-appender button');
    await page.click('button:has-text("Paragraph")');

    // Type content
    await page.keyboard.type('Test content');

    // Verify content
    const paragraph = page.locator('.dsgo-container p');
    await expect(paragraph).toHaveText('Test content');
  });
});
```

### Block Validation Testing

**Test that edit and save produce identical output:**

```javascript
import { getSaveContent } from '@wordpress/blocks';
import { serialize } from '@wordpress/blocks';

test('save content matches edit', () => {
  const attributes = {
    layoutType: 'grid',
    gridColumns: 3,
  };

  const savedContent = getSaveContent('designsetgo/container', attributes);

  // Parse and re-serialize
  const blocks = parse(savedContent);
  const reserialized = serialize(blocks);

  expect(reserialized).toBe(savedContent);
});
```

### Accessibility Testing

**Automated accessibility tests:**

```javascript
import { test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('container block has no accessibility violations', async ({ page }) => {
  await createNewPost();
  await insertBlock('Container');

  const results = await new AxeBuilder({ page }).analyze();

  expect(results.violations).toEqual([]);
});
```

---

## 12. Common Pitfalls & Anti-Patterns

### Critical Anti-Pattern: useEffect for Styling

**❌ NEVER DO THIS:**

```javascript
useEffect(() => {
  const element = document.querySelector(`[data-block="${clientId}"]`);
  element.style.display = 'grid';
}, [clientId]);
```

**✅ DO THIS INSTEAD:**

```javascript
const blockProps = useBlockProps({
  style: { display: 'grid' }
});
```

**Why:** See [Section 2](#2-react-patterns-in-wordpress) for detailed explanation.

### Anti-Pattern: Plain InnerBlocks

**❌ NEVER DO THIS:**

```javascript
return (
  <div {...blockProps}>
    <div className="inner">
      <InnerBlocks />
    </div>
  </div>
);
```

**✅ DO THIS INSTEAD:**

```javascript
const innerBlocksProps = useInnerBlocksProps({
  className: 'inner'
});

return (
  <div {...blockProps}>
    <div {...innerBlocksProps} />
  </div>
);
```

### Migration and Deprecation Patterns

**Always provide migration path:**

```javascript
// deprecated.js
export default [
  {
    attributes: {
      // Old attribute structure
      oldAttr: { type: 'string' }
    },
    migrate(attributes) {
      // IMPORTANT: Return new attributes, don't mutate
      return {
        newAttr: attributes.oldAttr,
      };
    },
    save(props) {
      // Old save function
    },
    // isEligible can help target specific blocks
    isEligible(attributes) {
      return attributes.oldAttr !== undefined;
    }
  }
];
```

### Block Validation Errors

**Common causes:**
1. Edit/save markup mismatch
2. Attribute changes without deprecation
3. Missing default values
4. Conditional rendering differences

**Debugging:**

```javascript
// Enable validation error details
add_filter('block_editor_settings_all', function($settings) {
  $settings['__unstableIsBlockBasedTheme'] = true;
  return $settings;
});
```

### Editor Crashes to Avoid

**Don't:**
- Infinite loops in `useEffect`
- Accessing undefined properties without checks
- Mutating state directly
- Throwing errors without error boundaries

**Do:**

```javascript
// Error boundary
class BlockErrorBoundary extends Component {
  state = { hasError: false };

  static getDerivedStateFromError(error) {
    return { hasError: true };
  }

  render() {
    if (this.state.hasError) {
      return <div>Block failed to load</div>;
    }
    return this.props.children;
  }
}

// Wrap edit component
export default function Edit(props) {
  return (
    <BlockErrorBoundary>
      <EditComponent {...props} />
    </BlockErrorBoundary>
  );
}
```

---

## 13. WordPress Coding Standards

### JavaScript Coding Standards

**Follow WordPress JavaScript Coding Standards:**

```javascript
// ✅ Good
const { gridColumns, layoutType } = attributes;

if ('grid' === layoutType) {
  // Yoda conditions for comparison
}

// Object destructuring
const blockProps = useBlockProps({
  className: classnames('dsgo-container', {
    'has-grid': 'grid' === layoutType,
  }),
});

// Function naming: camelCase
function calculateGridStyles(columns) {
  return {
    gridTemplateColumns: `repeat(${columns}, 1fr)`,
  };
}
```

**Use ESLint:**

```json
// .eslintrc.json
{
  "extends": [
    "plugin:@wordpress/eslint-plugin/recommended"
  ],
  "rules": {
    "@wordpress/no-unsafe-wp-apis": "warn",
    "@wordpress/dependency-group": "error"
  }
}
```

### PHP Coding Standards

**Follow WordPress PHP Coding Standards:**

```php
<?php
/**
 * Register Container block.
 *
 * @package DesignSetGo
 */

function designsetgo_register_container_block() {
  register_block_type(
    __DIR__ . '/build/blocks/container',
    array(
      'render_callback' => 'designsetgo_render_container',
    )
  );
}
add_action( 'init', 'designsetgo_register_container_block' );

/**
 * Render Container block.
 *
 * @param array $attributes Block attributes.
 * @return string Block HTML.
 */
function designsetgo_render_container( $attributes ) {
  $layout_type = $attributes['layoutType'] ?? 'stack';

  $wrapper_attributes = get_block_wrapper_attributes(
    array(
      'class' => 'dsgo-container',
    )
  );

  return sprintf(
    '<div %1$s>%2$s</div>',
    $wrapper_attributes,
    $content
  );
}
```

**Use PHP_CodeSniffer:**

```bash
composer require --dev wp-coding-standards/wpcs

# Run
./vendor/bin/phpcs --standard=WordPress includes/
```

### CSS/SCSS Best Practices

```scss
// BEM methodology
.dsgo-container {
  // Block
  position: relative;

  // Element
  &__inner {
    position: relative;
    z-index: 2;
  }

  &__video-background {
    position: absolute;
    inset: 0;
  }

  // Modifier
  &--card {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }

  // State
  &.is-selected {
    outline: 1px dashed #ccc;
  }
}
```

### Documentation Standards

**JSDoc for JavaScript:**

```javascript
/**
 * Calculate grid styles based on layout type and columns.
 *
 * @param {string} layoutType - Layout type (grid, flex, stack).
 * @param {number} columns - Number of grid columns.
 * @param {string} gap - Gap between items.
 * @return {Object} Style object for inner blocks.
 */
function calculateLayoutStyles(layoutType, columns, gap) {
  // ...
}
```

**PHPDoc for PHP:**

```php
/**
 * Enqueue block assets conditionally.
 *
 * Only loads scripts/styles for blocks used on current page.
 *
 * @since 1.0.0
 *
 * @return void
 */
function designsetgo_enqueue_block_assets() {
  // ...
}
```

---

## 14. Internationalization (i18n)

### Text Domain Usage

**Set in block.json:**

```json
{
  "textdomain": "designsetgo"
}
```

**Use in JavaScript:**

```javascript
import { __ } from '@wordpress/i18n';

const title = __('Container', 'designsetgo');
const description = __('Advanced container with layouts and backgrounds', 'designsetgo');

// With placeholder
const label = sprintf(
  __('Showing %d blocks', 'designsetgo'),
  blockCount
);

// Singular/plural
const message = _n(
  '%d block',
  '%d blocks',
  blockCount,
  'designsetgo'
);

// With context
const label = _x('Container', 'block name', 'designsetgo');
```

### Translation Function Patterns

**PHP translations:**

```php
__( 'Container', 'designsetgo' ); // Return translated string
_e( 'Container', 'designsetgo' ); // Echo translated string
esc_html__( 'Container', 'designsetgo' ); // Return and escape
esc_html_e( 'Container', 'designsetgo' ); // Echo and escape
```

### Enabling Translations

**In PHP:**

```php
function designsetgo_init() {
  wp_set_script_translations(
    'dsgo-container-editor-script',
    'designsetgo',
    plugin_dir_path( __FILE__ ) . 'languages'
  );
}
add_action( 'init', 'designsetgo_init' );
```

### RTL Support

**SCSS with RTL:**

```scss
.dsgo-container {
  padding-left: 20px;

  [dir="rtl"] & {
    padding-left: 0;
    padding-right: 20px;
  }
}

// Or use CSS logical properties
.dsgo-container {
  padding-inline-start: 20px; // Works in both LTR and RTL
}
```

---

## 15. Security

### Input Sanitization

**JavaScript (attributes):**

```javascript
import { sanitizeURL } from '@wordpress/url';

setAttributes({
  linkUrl: sanitizeURL(input.value)
});
```

**PHP:**

```php
// Sanitize on input
$video_url = esc_url_raw( $attributes['videoUrl'] );
$grid_columns = absint( $attributes['gridColumns'] );
$text_content = sanitize_text_field( $attributes['text'] );
```

### Output Escaping

**Always escape output:**

```php
// HTML output
echo esc_html( $text );

// Attribute output
echo '<div class="' . esc_attr( $class_name ) . '">';

// URL output
echo '<a href="' . esc_url( $link ) . '">';

// Allow safe HTML
echo wp_kses_post( $content );

// JavaScript output
echo '<script>var data = ' . wp_json_encode( $data ) . ';</script>';
```

### Nonce Usage

**Verify nonces in REST API callbacks:**

```php
function designsetgo_save_block_settings( $request ) {
  // Check nonce
  if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
    return new WP_Error( 'invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
  }

  // Sanitize
  $settings = $request->get_param( 'settings' );
  $sanitized = array_map( 'sanitize_text_field', $settings );

  // Save
  update_option( 'designsetgo_settings', $sanitized );

  return rest_ensure_response( $sanitized );
}
```

### Capability Checks

**Check user permissions:**

```php
function designsetgo_render_admin_page() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have sufficient permissions to access this page.', 'designsetgo' ) );
  }

  // Render admin page
}
```

---

## Appendix: Quick Reference

### WordPress Hooks Cheat Sheet

```javascript
// Block wrapper
useBlockProps()
useBlockProps.save()

// Inner blocks
useInnerBlocksProps(props, config)
useInnerBlocksProps.save(props)

// Data
useSelect(callback, deps)
useDispatch(store)

// Settings
useSetting('path.to.setting')

// Utilities
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { InspectorControls, BlockControls } from '@wordpress/block-editor';
```

### Performance Checklist

- [ ] Bundle size <100KB total
- [ ] Each block <10KB
- [ ] No jQuery dependency
- [ ] Conditional loading (only used blocks)
- [ ] Lazy load heavy components
- [ ] Code splitting per block
- [ ] Tree-shaking enabled
- [ ] Images lazy loaded
- [ ] CSS minified
- [ ] JS minified

### Accessibility Checklist

- [ ] WCAG 2.1 AA compliant
- [ ] 4.5:1 color contrast (normal text)
- [ ] Keyboard navigation works
- [ ] Focus indicators visible
- [ ] ARIA labels on interactive elements
- [ ] Semantic HTML
- [ ] Screen reader tested
- [ ] Alt text on images

### Testing Checklist

- [ ] Unit tests pass
- [ ] E2E tests pass
- [ ] Works in latest 2 WP versions
- [ ] Editor/frontend parity
- [ ] No console errors
- [ ] Block validation succeeds
- [ ] Mobile responsive
- [ ] Cross-browser tested
- [ ] Accessibility audit passed

---

**Document Version:** 1.0
**Last Updated:** October 24, 2025
**Maintained By:** DesignSetGo Team
**Next Review:** Quarterly

**Contributing:**
This is a living document. Found a best practice we missed? Submit a PR to improve this guide.
