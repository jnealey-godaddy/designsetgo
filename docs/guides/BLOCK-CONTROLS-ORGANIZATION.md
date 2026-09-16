# Block Controls Organization Best Practices

**Last Updated**: 2025-11-08
**WordPress Version**: 6.2+
**DesignSetGo Version**: 1.0.0

> **Superseded implementation pattern:** this guide's decision tree (Settings vs. Style) is still current, but its `PanelBody` / `PanelColorSettings` code samples predate the Theme 3 Inspector IA. Wrap custom controls in `<DsgoInspectorPanel>` / `<DsgoInspectorPanel.Item>` and use `ColorGradientSettingsDropdown` for color — see `../../.claude/CLAUDE.md` ("Inspector IA (Theme 3)").

## Overview

WordPress 6.2 introduced a major reorganization of block controls with separate **Settings** and **Styles** tabs. This guide covers best practices for organizing custom block controls to create an intuitive editing experience.

---

## Decision Tree: What Approach to Use?

```
Is this a standard WordPress feature (color, typography, spacing, etc.)?
├─ YES → Use Block Supports (in block.json)
└─ NO → Is this a visual variation?
    ├─ YES → Is it predefined (not adjustable)?
    │   ├─ YES → Use Block Styles (max 4 per block)
    │   └─ NO → Use InspectorControls
    └─ NO → Use InspectorControls
        └─ Behavioral setting → group="settings"
        └─ Visual setting → group="styles" or subsection
```

---

## 1. Block Supports (Preferred Approach)

**Use For**: Standard WordPress features that fit the core design system.

### Advantages
- ✅ Automatically registers attributes
- ✅ Generates consistent UI across blocks
- ✅ Applies values to block wrapper automatically
- ✅ Works with both static (JS) and dynamic (PHP) blocks
- ✅ Integrates with theme.json settings
- ✅ Reduces maintenance burden

### Available Block Supports

```json
{
  "supports": {
    // Color
    "color": {
      "text": true,
      "background": true,
      "gradients": true,
      "link": true
    },

    // Typography
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "fontFamily": true,
      "fontWeight": true,
      "letterSpacing": true,
      "textTransform": true
    },

    // Spacing
    "spacing": {
      "margin": true,
      "padding": true,
      "blockGap": true
    },

    // Layout
    "align": true,
    "alignWide": true,

    // Advanced
    "anchor": true,
    "customClassName": true,
    "html": false
  }
}
```

### Implementation Pattern

**Editor (edit.js)**:
```javascript
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps();
  // Automatically includes all support-generated attributes
  return <div {...blockProps}>Content</div>;
}
```

**Frontend (save.js)**:
```javascript
import { useBlockProps } from '@wordpress/block-editor';

export default function save({ attributes }) {
  const blockProps = useBlockProps.save();
  return <div {...blockProps}>Content</div>;
}
```

**Frontend (render.php for dynamic blocks)**:
```php
<?php
$wrapper_attributes = get_block_wrapper_attributes();
?>
<div <?php echo $wrapper_attributes; ?>>
  Content
</div>
```

---

## 2. Block Styles (Limited Use)

**Use For**: Predefined visual variations (e.g., "Rounded" vs "Square", "Outlined" vs "Filled").

### Critical Rules
- ⚠️ **Maximum 4 styles per block** (hard limit for good UX)
- ⚠️ **No adjustable properties** (use InspectorControls instead)
- ⚠️ **Distinct variations only** (not "slightly rounded" vs "more rounded")

### When NOT to Use Block Styles

❌ **BAD**: Multiple similar variations
```javascript
// DON'T DO THIS
registerBlockStyle('core/image', { name: 'slightly-rounded', label: 'Slightly Rounded' });
registerBlockStyle('core/image', { name: 'more-rounded', label: 'More Rounded' });
registerBlockStyle('core/image', { name: 'very-rounded', label: 'Very Rounded' });
```

✅ **GOOD**: Use adjustable control instead
```javascript
// DO THIS
<InspectorControls group="dimensions">
  <PanelBody title={__('Border', 'designsetgo')}>
    <RangeControl
      label={__('Border Radius', 'designsetgo')}
      value={borderRadius}
      onChange={(value) => setAttributes({ borderRadius: value })}
      min={0}
      max={50}
      __next40pxDefaultSize
      __nextHasNoMarginBottom
    />
  </PanelBody>
</InspectorControls>
```

### Implementation Pattern

**File Structure**:
```
includes/block-styles/
├── index.js              # Main import file
├── image.js              # Image block styles
├── group.js              # Group block styles
└── container.js          # Custom block styles
```

**Example (includes/block-styles/image.js)**:
```javascript
import { __ } from '@wordpress/i18n';
import { registerBlockStyle, unregisterBlockStyle } from '@wordpress/blocks';
import domReady from '@wordpress/dom-ready';

domReady(() => {
  // Unregister core styles if needed
  unregisterBlockStyle('core/image', 'rounded');

  // Register custom styles (max 4)
  registerBlockStyle('core/image', {
    name: 'modern-shadow',
    label: __('Modern Shadow', 'designsetgo'),
  });

  registerBlockStyle('core/image', {
    name: 'bordered',
    label: __('Bordered', 'designsetgo'),
  });
});
```

**CSS (src/styles/style.scss)**:
```scss
.wp-block-image {
  &.is-style-modern-shadow {
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
  }

  &.is-style-bordered {
    border: 2px solid currentColor;
    padding: 1rem;
  }
}
```

---

## 3. InspectorControls (Custom Settings)

**Use For**: Block-specific functionality not covered by core supports.

### Available Groups (WordPress 6.2+)

| Group | Location | Use Case |
|-------|----------|----------|
| `settings` | Settings Tab | Behavioral controls, block functionality |
| `styles` | Styles Tab | Visual controls not in subsections |
| `color` | Styles Tab → Color | Custom color controls |
| `typography` | Styles Tab → Typography | Custom text controls |
| `dimensions` | Styles Tab → Dimensions | Size, spacing, border radius |
| `border` | Styles Tab → Border | Border style, width, color |
| `advanced` | Advanced Section | HTML attributes, CSS classes |

### Organization Principles

**1. Settings vs. Styles**

**Settings Tab** (`group="settings"` or default):
- Link behavior (URL, target)
- Animation triggers
- Display conditions
- Content source
- Block-specific functionality

**Styles Tab** (`group="styles"`):
- Custom visual properties
- Layout variations
- Decorative elements

**2. Use Subsections When Possible**

Instead of:
```javascript
<InspectorControls group="styles">
  <PanelBody title={__('Colors', 'designsetgo')}>
    {/* color controls */}
  </PanelBody>
</InspectorControls>
```

Do this:
```javascript
<InspectorControls group="color">
  <PanelBody title={__('Custom Colors', 'designsetgo')}>
    {/* color controls */}
  </PanelBody>
</InspectorControls>
```

**Why**: Better organization, matches WordPress core patterns, easier for users to find.

### Implementation Patterns

#### Example 1: Settings Tab (Behavioral)

```javascript
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

<InspectorControls group="settings">
  <PanelBody title={__('Animation Settings', 'designsetgo')}>
    <ToggleControl
      label={__('Enable Animation', 'designsetgo')}
      checked={enableAnimation}
      onChange={(value) => setAttributes({ enableAnimation: value })}
      __nextHasNoMarginBottom
    />

    {enableAnimation && (
      <SelectControl
        label={__('Animation Type', 'designsetgo')}
        value={animationType}
        options={[
          { label: __('Fade In', 'designsetgo'), value: 'fade' },
          { label: __('Slide Up', 'designsetgo'), value: 'slide' },
          { label: __('Scale', 'designsetgo'), value: 'scale' },
        ]}
        onChange={(value) => setAttributes({ animationType: value })}
        __next40pxDefaultSize
        __nextHasNoMarginBottom
      />
    )}
  </PanelBody>
</InspectorControls>
```

#### Example 2: Color Subsection

```javascript
import { InspectorControls, PanelColorSettings } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

<InspectorControls group="color">
  <PanelColorSettings
    title={__('Icon Colors', 'designsetgo')}
    colorSettings={[
      {
        value: iconColor,
        onChange: (value) => setAttributes({ iconColor: value }),
        label: __('Icon Color', 'designsetgo'),
      },
      {
        value: iconHoverColor,
        onChange: (value) => setAttributes({ iconHoverColor: value }),
        label: __('Icon Hover Color', 'designsetgo'),
      },
    ]}
  />
</InspectorControls>
```

**Important CSS for Subsections**:
```scss
// Required for controls in color, typography, dimensions, border groups
.your-custom-control-wrapper {
  grid-column: 1 / -1; // Spans full width of grid layout
}
```

#### Example 3: Dimensions Subsection

```javascript
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, __experimentalUnitControl as UnitControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

<InspectorControls group="dimensions">
  <PanelBody title={__('Icon Size', 'designsetgo')}>
    <UnitControl
      label={__('Icon Size', 'designsetgo')}
      value={iconSize}
      onChange={(value) => setAttributes({ iconSize: value })}
      units={[
        { value: 'px', label: 'px', default: 24 },
        { value: 'em', label: 'em', default: 1.5 },
        { value: 'rem', label: 'rem', default: 1.5 },
      ]}
      __next40pxDefaultSize
    />
  </PanelBody>

  <PanelBody title={__('Spacing', 'designsetgo')}>
    <RangeControl
      label={__('Gap Between Items', 'designsetgo')}
      value={gap}
      onChange={(value) => setAttributes({ gap: value })}
      min={0}
      max={100}
      __next40pxDefaultSize
      __nextHasNoMarginBottom
    />
  </PanelBody>
</InspectorControls>
```

#### Example 4: Advanced Controls

```javascript
import { InspectorAdvancedControls } from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

<InspectorAdvancedControls>
  <TextControl
    label={__('HTML Anchor', 'designsetgo')}
    value={anchor}
    onChange={(value) => setAttributes({ anchor: value })}
    help={__(
      'Enter a word or two — without spaces — to make a unique web address just for this block, called an "anchor." Then, you\'ll be able to link directly to this section of your page.',
      'designsetgo'
    )}
    __next40pxDefaultSize
    __nextHasNoMarginBottom
  />
</InspectorAdvancedControls>
```

---

## 4. Organization Best Practices

### Grouping Related Controls

Use `PanelBody` components to group related settings:

```javascript
<InspectorControls group="settings">
  {/* Link Settings */}
  <PanelBody title={__('Link Settings', 'designsetgo')} initialOpen={true}>
    <TextControl label={__('URL', 'designsetgo')} />
    <ToggleControl label={__('Open in new tab', 'designsetgo')} />
  </PanelBody>

  {/* Display Settings */}
  <PanelBody title={__('Display Settings', 'designsetgo')} initialOpen={false}>
    <ToggleControl label={__('Show Icon', 'designsetgo')} />
    <SelectControl label={__('Icon Position', 'designsetgo')} />
  </PanelBody>
</InspectorControls>
```

### Multiple Groups in One Block

```javascript
export default function Edit({ attributes, setAttributes }) {
  return (
    <>
      {/* Behavioral settings */}
      <InspectorControls group="settings">
        <PanelBody title={__('Animation', 'designsetgo')}>
          {/* animation controls */}
        </PanelBody>
      </InspectorControls>

      {/* Color settings */}
      <InspectorControls group="color">
        <PanelColorSettings
          title={__('Colors', 'designsetgo')}
          colorSettings={[/* ... */]}
        />
      </InspectorControls>

      {/* Dimension settings */}
      <InspectorControls group="dimensions">
        <PanelBody title={__('Size', 'designsetgo')}>
          {/* size controls */}
        </PanelBody>
      </InspectorControls>

      {/* Advanced settings */}
      <InspectorAdvancedControls>
        <TextControl label={__('Custom CSS Class', 'designsetgo')} />
      </InspectorAdvancedControls>

      {/* Block content */}
      <div {...useBlockProps()}>
        {/* ... */}
      </div>
    </>
  );
}
```

### File Organization for Complex Blocks

For blocks with many controls, split into separate files:

```
src/blocks/complex-block/
├── index.js                          # Block registration
├── edit.js                           # Main edit component
├── save.js                           # Save function
├── components/
│   ├── inspector/
│   │   ├── SettingsPanel.js         # Settings tab controls
│   │   ├── ColorPanel.js            # Color subsection
│   │   ├── TypographyPanel.js       # Typography subsection
│   │   ├── DimensionsPanel.js       # Dimensions subsection
│   │   └── AdvancedPanel.js         # Advanced controls
│   └── BlockContent.js               # Main content component
└── utils/
    └── helpers.js                    # Pure functions
```

**edit.js Example**:
```javascript
import { useBlockProps } from '@wordpress/block-editor';
import SettingsPanel from './components/inspector/SettingsPanel';
import ColorPanel from './components/inspector/ColorPanel';
import DimensionsPanel from './components/inspector/DimensionsPanel';
import BlockContent from './components/BlockContent';

export default function Edit(props) {
  return (
    <>
      <SettingsPanel {...props} />
      <ColorPanel {...props} />
      <DimensionsPanel {...props} />
      <div {...useBlockProps()}>
        <BlockContent {...props} />
      </div>
    </>
  );
}
```

---

## 5. DesignSetGo-Specific Guidelines

### Color Controls Refactoring Checklist

When refactoring color controls:

- [ ] **Check if Block Supports is sufficient**
  ```json
  {
    "supports": {
      "color": {
        "text": true,
        "background": true
      }
    }
  }
  ```

- [ ] **If custom colors needed, use `group="color"`**
  ```javascript
  <InspectorControls group="color">
    <PanelColorSettings title={__('Custom Colors', 'designsetgo')} />
  </InspectorControls>
  ```

- [ ] **Use PanelColorSettings, not individual ColorPalette**
  ```javascript
  // ✅ GOOD - Consistent with WordPress
  <PanelColorSettings
    colorSettings={[
      { value: color1, onChange: setColor1, label: 'Color 1' }
    ]}
  />

  // ❌ AVOID - Custom implementation
  <ColorPalette value={color1} onChange={setColor1} />
  ```

- [ ] **Leverage theme colors automatically**
  ```javascript
  // PanelColorSettings automatically includes theme colors
  // No need to fetch manually unless required
  ```

### Common Patterns in DesignSetGo

**Pattern 1: Icon Blocks**
- Use `dimensions` group for icon size
- Use `color` group for icon colors
- Use `settings` group for icon library/selection

**Pattern 2: Container Blocks**
- Use Block Supports for spacing, background
- Use `dimensions` group for gap, width constraints
- Use `settings` group for layout type

**Pattern 3: Interactive Blocks**
- Use `settings` group for animation, hover behavior
- Use `color` group for state colors (default, hover, active)
- Use `dimensions` group for transition timing

---

## 6. Migration Checklist

Migrating existing blocks to new organization:

### Step 1: Audit Current Controls
```bash
# Find all InspectorControls in your blocks
grep -r "InspectorControls" src/blocks/
```

### Step 2: Categorize Each Control

| Control Type | Destination |
|--------------|-------------|
| Color picker | Block Supports or `group="color"` |
| Font size | Block Supports `typography.fontSize` |
| Spacing | Block Supports `spacing` |
| Border radius | Block Supports `spacing.borderRadius` or `group="dimensions"` |
| Link URL | `group="settings"` |
| Animation toggle | `group="settings"` |
| Custom size | `group="dimensions"` |

### Step 3: Replace Default InspectorControls

```javascript
// Before (WordPress 6.1 and earlier)
<InspectorControls>
  <PanelBody title="Settings">
    {/* all controls */}
  </PanelBody>
</InspectorControls>

// After (WordPress 6.2+)
<InspectorControls group="settings">
  <PanelBody title="Behavior">
    {/* behavioral controls */}
  </PanelBody>
</InspectorControls>

<InspectorControls group="color">
  <PanelColorSettings
    title="Colors"
    colorSettings={[/* ... */]}
  />
</InspectorControls>
```

### Step 4: Add Block Supports

```json
// block.json
{
  "supports": {
    "color": {
      "text": true,
      "background": true
    },
    "spacing": {
      "padding": true,
      "margin": true
    }
  }
}
```

### Step 5: Remove Duplicate Attributes

```json
// Before
{
  "attributes": {
    "textColor": { "type": "string" },
    "backgroundColor": { "type": "string" },
    "fontSize": { "type": "string" }
  }
}

// After (Block Supports handles these)
{
  "attributes": {
    // Only custom attributes remain
  }
}
```

### Step 6: Update Edit Component

```javascript
// Before
const { textColor, backgroundColor } = attributes;

// After
// Block Supports handles these via useBlockProps()
// Only reference custom attributes
```

---

## 7. Testing Checklist

After reorganizing controls:

- [ ] **Settings appear in correct tab/section**
- [ ] **Controls function correctly in editor**
- [ ] **Styles apply correctly on frontend**
- [ ] **No duplicate controls** (Block Supports vs custom)
- [ ] **No validation errors** in block editor
- [ ] **Mobile responsive** (editor sidebar)
- [ ] **Accessibility** (keyboard navigation, labels)
- [ ] **Theme.json integration** (colors, spacing presets)

---

## 8. Common Mistakes to Avoid

### ❌ Mistake 1: Too Many Custom Controls
```javascript
// DON'T: Reinventing WordPress features
<InspectorControls>
  <PanelBody>
    <ColorPalette label="Text Color" />
    <ColorPalette label="Background" />
    <RangeControl label="Font Size" />
    <RangeControl label="Padding" />
  </PanelBody>
</InspectorControls>
```

```json
// DO: Use Block Supports
{
  "supports": {
    "color": { "text": true, "background": true },
    "typography": { "fontSize": true },
    "spacing": { "padding": true }
  }
}
```

### ❌ Mistake 2: Wrong Group
```javascript
// DON'T: Visual controls in Settings
<InspectorControls group="settings">
  <PanelColorSettings title="Colors" />
</InspectorControls>
```

```javascript
// DO: Visual controls in appropriate subsection
<InspectorControls group="color">
  <PanelColorSettings title="Colors" />
</InspectorControls>
```

### ❌ Mistake 3: Missing Grid CSS for Subsections
```javascript
// Controls in color/typography/dimensions/border groups need:
<div style={{ gridColumn: '1 / -1' }}>
  <YourCustomControl />
</div>
```

### ❌ Mistake 4: Too Many Block Styles
```javascript
// DON'T: More than 4 styles
registerBlockStyle('designsetgo/icon', { name: 'style1' });
registerBlockStyle('designsetgo/icon', { name: 'style2' });
registerBlockStyle('designsetgo/icon', { name: 'style3' });
registerBlockStyle('designsetgo/icon', { name: 'style4' });
registerBlockStyle('designsetgo/icon', { name: 'style5' }); // ❌
```

---

## 9. Resources

### Official Documentation
- [Block Supports](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/)
- [InspectorControls](https://github.com/WordPress/gutenberg/blob/trunk/packages/block-editor/src/components/inspector-controls/README.md)
- [Block Styles](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-styles/)
- [Using Block Inspector Sidebar Groups](https://developer.wordpress.org/news/2023/06/using-block-inspector-sidebar-groups/)

### Community Resources
- [10up Block Editor Best Practices](https://gutenberg.10up.com/)
- [WordPress StackExchange](https://wordpress.stackexchange.com/questions/tagged/gutenberg)

---

## Summary

**Priority Order for Implementation:**

1. **Block Supports** - Always check first
2. **Existing WordPress Patterns** - Use core components
3. **InspectorControls with Groups** - Organize by purpose
4. **Block Styles** - Only for predefined variations (max 4)

**Key Takeaway**: Use WordPress's built-in features first, organize custom controls by function (Settings vs Styles), and keep the UI clean and intuitive.
