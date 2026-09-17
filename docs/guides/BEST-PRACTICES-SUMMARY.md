# WordPress Block Development Best Practices - Quick Reference

**Quick access summary of [BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md](./BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md)**

> **Superseded pattern below:** the non-color `PanelBody` examples in this doc predate the Theme 3 Inspector IA. Current blocks wrap every custom control in `<DsgoInspectorPanel>` / `<DsgoInspectorPanel.Item>` instead of a bare `PanelBody` — see `../../.claude/CLAUDE.md` ("Inspector IA (Theme 3)"). The `PanelColorSettings` → `ColorGradientSettingsDropdown` guidance below is still current.

---

## Critical Rules (MUST Follow)

### 1. ALWAYS Use useInnerBlocksProps

```javascript
// ❌ WRONG
<div className="inner">
  <InnerBlocks />
</div>

// ✅ CORRECT
const innerBlocksProps = useInnerBlocksProps({
  className: 'inner',
  style: { display: 'grid' }
});
<div {...innerBlocksProps} />
```

**Why:** Plain `<InnerBlocks />` adds wrapper divs that break CSS Grid/Flexbox and cause editor/frontend mismatch.

### 2. NEVER Use useEffect for Styling

```javascript
// ❌ WRONG
useEffect(() => {
  document.querySelector('.block').style.display = 'grid';
}, []);

// ✅ CORRECT
const blockProps = useBlockProps({
  style: { display: 'grid' }
});
```

**Why:** DOM manipulation is unreliable, causes timing issues, and breaks WordPress's declarative architecture.

### 3. ALWAYS Match Edit and Save Markup

```javascript
// edit.js
const blockProps = useBlockProps({ className: 'my-block' });
return <div {...blockProps}>Content</div>;

// save.js
const blockProps = useBlockProps.save({ className: 'my-block' });
return <div {...blockProps}>Content</div>;
```

**Why:** Mismatched markup causes block validation errors.

### 4. ALWAYS Use ColorGradientSettingsDropdown (NEVER PanelColorSettings)

```javascript
// ❌ WRONG - Deprecated
import { PanelColorSettings } from '@wordpress/block-editor';

<PanelColorSettings
  title={__('Colors')}
  colorSettings={[
    {
      value: textColor,
      onChange: (value) => setAttributes({ textColor: value }),
      label: __('Text Color'),
    },
  ]}
/>

// ✅ CORRECT - Modern WordPress Standard
import {
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';

// Add clientId to function signature (REQUIRED)
export default function Edit({ attributes, setAttributes, clientId }) {
  const colorGradientSettings = useMultipleOriginColorsAndGradients();

  return (
    <InspectorControls group="color">
      <ColorGradientSettingsDropdown
        panelId={clientId}
        title={__('Colors', 'designsetgo')}
        settings={[
          {
            label: __('Text Color', 'designsetgo'),
            colorValue: textColor,
            onColorChange: (color) =>
              setAttributes({ textColor: color || '' }),
            clearable: true,
          },
        ]}
        {...colorGradientSettings}
      />
    </InspectorControls>
  );
}
```

**Why:**
- PanelColorSettings is deprecated (will be removed in future WordPress)
- ColorGradientSettingsDropdown places controls in Styles tab (better UX)
- Better theme integration and native clear/reset functionality
- All 13 designsetgo blocks use this pattern (migrated 2025-11-08)

---

## Quick Decision Trees

### Custom Block vs Extension?

```
Is functionality unique? (tabs, accordion, timeline)
├─ YES → Custom Block
└─ NO → Check complexity
    ├─ Needs video/complex state/DOM restructuring? → Custom Block
    ├─ Needs ≤3 controls + pure CSS? → Extension (maybe)
    └─ When in doubt → Custom Block
```

### Inline Styles vs CSS Classes?

```
Is value user-controlled?
├─ YES → Inline styles (backgroundColor, padding, fontSize)
└─ NO → Check purpose
    ├─ Responsive behavior? → CSS classes + media queries
    ├─ Theme variation? → CSS classes
    └─ State indicator? → CSS classes
```

### Static vs Dynamic Block?

```
Does content change frequently? (latest posts, user data)
├─ YES → Dynamic (render_callback)
└─ NO → Check needs
    ├─ Need PHP processing? → Dynamic
    ├─ SEO-critical initial HTML? → Dynamic
    └─ User-defined static content? → Static (save function)
```

---

## Essential Patterns

### Block Structure

```
src/blocks/my-block/
├── block.json          ← Metadata, supports, attributes
├── index.js            ← Register block
├── edit.js             ← Edit component
├── save.js             ← Save function
├── style.scss          ← Frontend styles
├── editor.scss         ← Editor-only styles
└── view.js             ← Frontend JS (optional)
```

### Complete Edit Pattern

```javascript
import { useBlockProps, useInnerBlocksProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
  const { columns, gap } = attributes;

  // 1. Calculate styles declaratively
  const innerStyles = {
    display: 'grid',
    gridTemplateColumns: `repeat(${columns}, 1fr)`,
    gap,
  };

  // 2. Block wrapper
  const blockProps = useBlockProps({
    className: 'my-block'
  });

  // 3. Inner blocks
  const innerBlocksProps = useInnerBlocksProps(
    {
      className: 'my-block__inner',
      style: innerStyles,
    },
    {
      allowedBlocks: ['core/heading', 'core/paragraph'],
      template: [['core/heading', { level: 2 }]],
    }
  );

  return (
    <>
      <InspectorControls>
        <PanelBody title="Layout Settings">
          <RangeControl
            label="Columns"
            value={columns}
            onChange={(columns) => setAttributes({ columns })}
            min={1}
            max={6}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div {...innerBlocksProps} />
      </div>
    </>
  );
}
```

### Complete Save Pattern

```javascript
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function Save({ attributes }) {
  const { columns, gap } = attributes;

  // Same calculation as edit.js
  const innerStyles = {
    display: 'grid',
    gridTemplateColumns: `repeat(${columns}, 1fr)`,
    gap,
  };

  const blockProps = useBlockProps.save({
    className: 'my-block'
  });

  const innerBlocksProps = useInnerBlocksProps.save({
    className: 'my-block__inner',
    style: innerStyles,
  });

  return (
    <div {...blockProps}>
      <div {...innerBlocksProps} />
    </div>
  );
}
```

### Responsive with CSS Custom Properties

```javascript
// JavaScript
const innerBlocksProps = useInnerBlocksProps({
  style: {
    '--cols-desktop': attributes.columnsDesktop,
    '--cols-tablet': attributes.columnsTablet,
    '--cols-mobile': attributes.columnsMobile,
    '--gap': attributes.gap,
    display: 'grid',
  }
});
```

```scss
// SCSS
.my-block__inner {
  grid-template-columns: repeat(var(--cols-desktop), 1fr);
  gap: var(--gap);

  @media (max-width: 1023px) {
    grid-template-columns: repeat(var(--cols-tablet), 1fr);
  }

  @media (max-width: 767px) {
    grid-template-columns: repeat(var(--cols-mobile), 1fr);
  }
}
```

---

## block.json Essentials

### Minimal block.json

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "designsetgo/my-block",
  "title": "My Block",
  "category": "designsetgo",
  "textdomain": "designsetgo",
  "supports": {
    "anchor": true,
    "align": ["wide", "full"],
    "html": false,
    "spacing": {
      "margin": true,
      "padding": true
    },
    "color": {
      "background": true,
      "text": true
    }
  },
  "attributes": {
    "myAttr": {
      "type": "string",
      "default": ""
    }
  },
  "example": {
    "attributes": {},
    "innerBlocks": []
  },
  "editorScript": "file:./index.js",
  "editorStyle": "file:./index.css",
  "style": "file:./style-index.css"
}
```

### Full Supports (Copy-Paste Ready)

```json
{
  "supports": {
    "anchor": true,
    "align": ["wide", "full"],
    "html": false,
    "inserter": true,
    "layout": {
      "allowSwitching": true,
      "allowEditing": true
    },
    "spacing": {
      "margin": true,
      "padding": true,
      "blockGap": true,
      "__experimentalDefaultControls": {
        "padding": true
      }
    },
    "dimensions": {
      "minHeight": true
    },
    "color": {
      "background": true,
      "text": true,
      "gradients": true,
      "__experimentalDefaultControls": {
        "background": true,
        "text": true
      }
    },
    "background": {
      "backgroundImage": true
    },
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "__experimentalDefaultControls": {
        "fontSize": true
      }
    },
    "shadow": true,
    "__experimentalBorder": {
      "color": true,
      "radius": true,
      "style": true,
      "width": true,
      "__experimentalDefaultControls": {
        "radius": true
      }
    }
  }
}
```

---

## Data Management

### useSelect Pattern

```javascript
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

// Group requests from same store
const { hasInnerBlocks, blockCount } = useSelect(
  (select) => {
    const { getBlockCount } = select(blockEditorStore);
    return {
      hasInnerBlocks: getBlockCount(clientId) > 0,
      blockCount: getBlockCount(clientId),
    };
  },
  [clientId] // Dependencies!
);
```

### useDispatch Pattern

```javascript
import { useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

const { insertBlocks } = useDispatch(blockEditorStore);

const addBlock = () => {
  const newBlock = createBlock('core/paragraph');
  insertBlocks(newBlock, undefined, clientId);
};
```

---

## Parent-Child Communication

### Parent Provides Context

```json
// parent/block.json
{
  "providesContext": {
    "designsetgo/parentId": "uniqueId",
    "designsetgo/layout": "layoutType"
  }
}
```

### Child Uses Context

```json
// child/block.json
{
  "usesContext": ["designsetgo/parentId", "designsetgo/layout"]
}
```

```javascript
// child/edit.js
export default function Edit({ context }) {
  const parentLayout = context['designsetgo/layout'];

  // Adapt child based on parent
  return <div>Child in {parentLayout} layout</div>;
}
```

---

## Block Extensions

### When to Use Extensions vs Custom Blocks

Extensions enhance existing blocks with additional functionality. Use extensions when:
- Functionality applies to multiple block types (not block-specific)
- Feature can be implemented with ≤3 controls + pure CSS
- No DOM restructuring needed
- No complex state management required

**Examples:** Custom CSS, Max Width, Responsive Visibility, Scroll Parallax

### Extension Exclusion System

Some third-party blocks (especially server-side rendered blocks) may conflict with DSG extensions. Use the exclusion system to prevent extensions from being applied:

```javascript
import { shouldExtendBlock } from '../../utils/should-extend-block';

// In attribute registration
export const extendBlockAttributes = (settings, name) => {
  if (!shouldExtendBlock(name)) {
    return settings;
  }
  // Add attributes...
};

// In edit controls, editor styles, and save props
if (!shouldExtendBlock(blockName)) {
  return null; // or return original component
}
```

**Admin UI:** Users can exclude blocks via Settings > Extensions > Exclusions tab
**Format:** Exact match (`gravityforms/form`) or namespace wildcard (`gravityforms/*`)
**Performance:** Results are cached automatically via memoization

### Extension Development Checklist

- [ ] Check `shouldExtendBlock()` before adding attributes
- [ ] Check `shouldExtendBlock()` in edit controls
- [ ] Check `shouldExtendBlock()` in editor styles
- [ ] Check `shouldExtendBlock()` in save props
- [ ] Use explicit allowlist (never `!== 'core/block'`)
- [ ] Test with excluded blocks to ensure no errors
- [ ] Document which blocks the extension works best with

---

## Security Checklist

### Always Sanitize & Escape

```php
// Sanitize input
$url = esc_url_raw( $attributes['url'] );
$text = sanitize_text_field( $attributes['text'] );
$number = absint( $attributes['count'] );

// Escape output
echo esc_html( $text );
echo esc_attr( $class );
echo esc_url( $url );
echo wp_kses_post( $content );
```

---

## Performance Checklist

- [ ] Bundle size <100KB total
- [ ] Each block <10KB average
- [ ] No jQuery dependency
- [ ] Conditional loading (PHP checks if block used)
- [ ] Code splitting per block
- [ ] CSS custom properties for responsive (not JS)
- [ ] Lazy load heavy components
- [ ] Images lazy loaded
- [ ] Passive event listeners
- [ ] Cleanup in useEffect returns

---

## Accessibility Checklist

- [ ] WCAG 2.1 AA compliant
- [ ] Color contrast 4.5:1 minimum
- [ ] Keyboard navigation works
- [ ] Focus indicators visible
- [ ] ARIA labels on controls
- [ ] Semantic HTML (`<button>` not `<div role="button">`)
- [ ] Alt text on images
- [ ] Screen reader tested
- [ ] `prefers-reduced-motion` respected

---

## Testing Checklist

- [ ] Works in latest 2 WordPress versions
- [ ] Editor matches frontend (markup parity)
- [ ] Block validation succeeds
- [ ] No console errors
- [ ] Mobile responsive
- [ ] Cross-browser (Chrome, Firefox, Safari, Edge)
- [ ] Unit tests pass
- [ ] E2E tests pass
- [ ] Accessibility audit passed (axe)

---

## Common Mistakes

### 1. Not Using useBlockProps

```javascript
// ❌ Missing WordPress integration
return <div className="my-block">...</div>;

// ✅ Proper WordPress integration
const blockProps = useBlockProps();
return <div {...blockProps}>...</div>;
```

### 2. Mutating State Directly

```javascript
// ❌ Mutates array
const items = attributes.items;
items.push(newItem);
setAttributes({ items });

// ✅ Creates new array
setAttributes({
  items: [...attributes.items, newItem]
});
```

### 3. Missing Dependencies in useEffect

```javascript
// ❌ Missing dependency
useEffect(() => {
  doSomething(propValue);
}, []);

// ✅ Includes all dependencies
useEffect(() => {
  doSomething(propValue);
}, [propValue]);
```

### 4. Using Hooks in Save

```javascript
// ❌ Hooks in save
export default function Save() {
  const [state, setState] = useState(false); // ERROR!
  return <div>...</div>;
}

// ✅ Save is pure function
export default function Save({ attributes }) {
  return <div>...</div>;
}
```

### 5. Forgetting Text Domain

```javascript
// ❌ Missing text domain
__('Hello')

// ✅ Include text domain
__('Hello', 'designsetgo')
```

---

## When Something Breaks

### Block Validation Error

**Cause:** Edit/save markup mismatch

**Fix:**
1. Ensure edit.js and save.js generate identical HTML
2. Check attribute defaults match
3. Add deprecation if intentionally changing

### Styles Not Applying in Editor

**Cause:** Using useEffect or wrong hooks

**Fix:**
1. Remove useEffect for styling
2. Use `useBlockProps` and `useInnerBlocksProps`
3. Calculate styles declaratively

### Editor Crash

**Cause:** Uncaught errors, infinite loops

**Fix:**
1. Add error boundary
2. Check useEffect dependencies
3. Ensure no state mutations
4. Check for undefined before accessing properties

---

## Resources

**Official:**
- [Block Editor Handbook](https://developer.wordpress.org/block-editor/)
- [Block API Reference](https://developer.wordpress.org/block-editor/reference-guides/block-api/)
- [@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)

**Community:**
- [10up Block Editor Best Practices](https://gutenberg.10up.com/)
- [WordPress StackExchange](https://wordpress.stackexchange.com/)

**Our Documentation:**
- [Complete Best Practices Guide](./BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md)
- [Declarative Styling / useInnerBlocksProps Lessons](../../.claude/docs/EDITOR-STYLING-GUIDE.md)

---

**Last Updated:** October 24, 2025
**For:** DesignSetGo Blocks Development Team
