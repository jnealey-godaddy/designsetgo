# Color Controls Pattern

**Last Updated**: 2025-11-08
**WordPress Version**: 6.4+

## Overview

This document defines the standardized approach for implementing color controls across all DesignSetGo blocks, using WordPress's modern `ColorGradientSettingsDropdown` component.

**Shared hook available**: `useBlockColors({ attributes, setAttributes, entries })` (`src/hooks/useBlockColors.js`) wraps steps 4–6 below (color extraction, `settings` array construction, encode/decode of preset vs. custom values) and is the preferred entry point for new blocks. It is currently used by Section, Card, Text Path, Hotspot, Slider, Section Divider, and Star Rating. The manual pattern documented here (used by Icon Button and other existing blocks) remains valid — prefer the hook for anything new.

## Why This Pattern?

### Problems with Old Approach (PanelColorSettings)
- ❌ Colors appear in Settings tab instead of Styles tab
- ❌ Inconsistent with WordPress core blocks
- ❌ Users expect colors in the Styles tab
- ❌ Requires custom clear button implementation

### Benefits of New Approach (ColorGradientSettingsDropdown)
- ✅ Colors appear in Styles tab where users expect them
- ✅ Matches WordPress core block patterns
- ✅ Native clear/reset functionality built-in
- ✅ Better theme integration
- ✅ Automatic theme color palette support

## Implementation Pattern

### 1. Block Attributes

Color attributes should be stored as simple strings with empty string defaults:

```json
{
  "attributes": {
    "hoverBackgroundColor": {
      "type": "string",
      "default": ""
    },
    "hoverTextColor": {
      "type": "string",
      "default": ""
    }
  }
}
```

### 2. Edit Component Setup

#### Required Imports

```javascript
import {
  useBlockProps,
  InspectorControls,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
```

#### Component Signature

Add `clientId` prop to edit component:

```javascript
export default function MyBlockEdit({
  attributes,
  setAttributes,
  clientId, // ← Required for ColorGradientSettingsDropdown
}) {
  // ...
}
```

#### Color Settings Hook

```javascript
// Get theme color palette and gradient settings
const colorGradientSettings = useMultipleOriginColorsAndGradients();
```

### 3. InspectorControls Organization

Split color controls from other settings using `group="color"`:

```javascript
return (
  <>
    {/* Color controls - appear in STYLES tab */}
    <InspectorControls group="color">
      <ColorGradientSettingsDropdown
        panelId={clientId}
        title={__('Hover Colors', 'designsetgo')}
        settings={[
          {
            label: __('Hover Background', 'designsetgo'),
            colorValue: hoverBackgroundColor,
            onColorChange: (color) =>
              setAttributes({
                hoverBackgroundColor: color || '',
              }),
            clearable: true, // ← Enables native clear button
          },
          {
            label: __('Hover Text', 'designsetgo'),
            colorValue: hoverTextColor,
            onColorChange: (color) =>
              setAttributes({ hoverTextColor: color || '' }),
            clearable: true,
          },
        ]}
        {...colorGradientSettings}
      />
    </InspectorControls>

    {/* Other controls - appear in SETTINGS tab */}
    <InspectorControls>
      {/* Non-color settings here */}
    </InspectorControls>
  </>
);
```

### 4. Color Value Extraction

WordPress stores colors in two ways:
- **Custom colors**: `attributes.style.color.background` (hex/rgb values)
- **Preset colors**: `attributes.backgroundColor` (theme color slugs)

Extract both formats:

```javascript
const {
  style,
  backgroundColor,
  textColor,
  hoverBackgroundColor,
  hoverTextColor,
} = attributes;

// Extract WordPress color values
// Custom colors come from style.color.background (hex/rgb)
// Preset colors come from backgroundColor/textColor (slugs that need conversion)
const bgColor =
  style?.color?.background ||
  (backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
const txtColor =
  style?.color?.text ||
  (textColor && `var(--wp--preset--color--${textColor})`);
```

### 5. Applying Colors

#### Inline Styles (Recommended for User-Controlled Values)

Apply colors directly to elements using inline styles:

```javascript
const buttonStyles = {
  // Apply extracted colors
  ...(bgColor && { backgroundColor: bgColor }),
  ...(txtColor && { color: txtColor }),

  // Apply custom hover colors as CSS variables
  ...(hoverBackgroundColor && {
    '--dsgo-button-hover-bg': hoverBackgroundColor,
  }),
  ...(hoverTextColor && {
    '--dsgo-button-hover-color': hoverTextColor,
  }),
};

return (
  <div className="my-block__wrapper" style={buttonStyles}>
    {/* Content */}
  </div>
);
```

#### SCSS Defaults (Only When No Inline Styles)

Use `:not()` selector to apply defaults only when user hasn't set colors:

```scss
.my-block__wrapper {
  // Only apply default if no inline background style
  &:not([style*="background"]) {
    background-color: var(--wp--preset--color--primary, #2563eb);
  }

  // Only apply default if no inline color style
  &:not([style*="color"]) {
    color: var(--wp--preset--color--base, #fff);
  }
}
```

#### Hover Colors via CSS Variables

Target elements with CSS variables and apply on hover:

```scss
.my-block__wrapper[style*="--dsgo-button-hover-bg"]:hover {
  background-color: var(--dsgo-button-hover-bg) !important;
  opacity: 1; // Override default opacity change
}

.my-block__wrapper[style*="--dsgo-button-hover-color"]:hover {
  color: var(--dsgo-button-hover-color) !important;
}
```

### 6. Save Component Consistency

**CRITICAL**: Color extraction logic MUST match between edit.js and save.js:

```javascript
export default function MyBlockSave({ attributes }) {
  const {
    style,
    backgroundColor,
    textColor,
    hoverBackgroundColor,
    hoverTextColor,
  } = attributes;

  // Extract WordPress color values (must match edit.js)
  const bgColor =
    style?.color?.background ||
    (backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
  const txtColor =
    style?.color?.text ||
    (textColor && `var(--wp--preset--color--${textColor})`);

  // Calculate styles (must match edit.js)
  const buttonStyles = {
    ...(bgColor && { backgroundColor: bgColor }),
    ...(txtColor && { color: txtColor }),
    ...(hoverBackgroundColor && {
      '--dsgo-button-hover-bg': hoverBackgroundColor,
    }),
    ...(hoverTextColor && {
      '--dsgo-button-hover-color': hoverTextColor,
    }),
  };

  return (
    <div className="my-block__wrapper" style={buttonStyles}>
      {/* Content */}
    </div>
  );
}
```

## Complete Example: Icon Button Block

### block.json

```json
{
  "attributes": {
    "hoverBackgroundColor": {
      "type": "string",
      "default": ""
    },
    "hoverTextColor": {
      "type": "string",
      "default": ""
    }
  },
  "supports": {
    "color": {
      "background": true,
      "text": true
    }
  }
}
```

### edit.js

```javascript
import { __ } from '@wordpress/i18n';
import {
  useBlockProps,
  InspectorControls,
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';

export default function IconButtonEdit({
  attributes,
  setAttributes,
  clientId,
}) {
  const {
    hoverBackgroundColor,
    hoverTextColor,
    style,
    backgroundColor,
    textColor,
  } = attributes;

  // Get theme color palette and gradient settings
  const colorGradientSettings = useMultipleOriginColorsAndGradients();

  // Extract WordPress color values
  const bgColor =
    style?.color?.background ||
    (backgroundColor && `var(--wp--preset--color--${backgroundColor})`);
  const txtColor =
    style?.color?.text ||
    (textColor && `var(--wp--preset--color--${textColor})`);

  const buttonStyles = {
    ...(bgColor && { backgroundColor: bgColor }),
    ...(txtColor && { color: txtColor }),
    ...(hoverBackgroundColor && {
      '--dsgo-button-hover-bg': hoverBackgroundColor,
    }),
    ...(hoverTextColor && {
      '--dsgo-button-hover-color': hoverTextColor,
    }),
  };

  return (
    <>
      <InspectorControls group="color">
        <ColorGradientSettingsDropdown
          panelId={clientId}
          title={__('Hover Colors', 'designsetgo')}
          settings={[
            {
              label: __('Hover Background', 'designsetgo'),
              colorValue: hoverBackgroundColor,
              onColorChange: (color) =>
                setAttributes({
                  hoverBackgroundColor: color || '',
                }),
              clearable: true,
            },
            {
              label: __('Hover Text', 'designsetgo'),
              colorValue: hoverTextColor,
              onColorChange: (color) =>
                setAttributes({ hoverTextColor: color || '' }),
              clearable: true,
            },
          ]}
          {...colorGradientSettings}
        />
      </InspectorControls>

      <InspectorControls>
        {/* Other settings panels */}
      </InspectorControls>

      <div {...useBlockProps()}>
        <div className="dsgo-icon-button__wrapper" style={buttonStyles}>
          {/* Button content */}
        </div>
      </div>
    </>
  );
}
```

### style.scss

```scss
.dsgo-icon-button__wrapper {
  // Default colors only when no inline styles
  &:not([style*="background"]) {
    background-color: var(--wp--preset--color--primary, #2563eb);
  }
  &:not([style*="color"]) {
    color: var(--wp--preset--color--base, #fff);
  }

  // Default hover effect
  &:hover {
    opacity: 0.9;
  }
}

// Hover colors via CSS variables (override default hover)
.dsgo-icon-button__wrapper[style*="--dsgo-button-hover-bg"]:hover {
  background-color: var(--dsgo-button-hover-bg) !important;
  opacity: 1; // Override default opacity
}

.dsgo-icon-button__wrapper[style*="--dsgo-button-hover-color"]:hover {
  color: var(--dsgo-button-hover-color) !important;
}
```

## Migration Checklist

When converting a block from PanelColorSettings to ColorGradientSettingsDropdown:

- [ ] Add `clientId` prop to edit component signature
- [ ] Import `ColorGradientSettingsDropdown` and `useMultipleOriginColorsAndGradients`
- [ ] Add `colorGradientSettings` hook call
- [ ] Split `InspectorControls` - add `group="color"` for color controls
- [ ] Replace `PanelColorSettings` with `ColorGradientSettingsDropdown`
- [ ] Add `clearable: true` to each color setting
- [ ] Add color extraction logic for both custom and preset colors
- [ ] Update inline styles to use extracted colors
- [ ] Update SCSS to use `:not()` selectors for defaults
- [ ] Ensure save.js matches edit.js color extraction exactly
- [ ] Test with both custom colors and theme preset colors
- [ ] Test clear functionality in color picker
- [ ] Build and verify in both editor and frontend

## Common Patterns

### Single Color Control

```javascript
<ColorGradientSettingsDropdown
  panelId={clientId}
  title={__('Icon Color', 'designsetgo')}
  settings={[
    {
      label: __('Icon Color', 'designsetgo'),
      colorValue: iconColor,
      onColorChange: (color) =>
        setAttributes({ iconColor: color || '' }),
      clearable: true,
    },
  ]}
  {...colorGradientSettings}
/>
```

### Multiple Color Groups

```javascript
<InspectorControls group="color">
  <ColorGradientSettingsDropdown
    panelId={clientId}
    title={__('Normal Colors', 'designsetgo')}
    settings={[
      {
        label: __('Background', 'designsetgo'),
        colorValue: backgroundColor,
        onColorChange: (color) =>
          setAttributes({ backgroundColor: color || '' }),
        clearable: true,
      },
      {
        label: __('Text', 'designsetgo'),
        colorValue: textColor,
        onColorChange: (color) =>
          setAttributes({ textColor: color || '' }),
        clearable: true,
      },
    ]}
    {...colorGradientSettings}
  />

  <ColorGradientSettingsDropdown
    panelId={clientId}
    title={__('Hover Colors', 'designsetgo')}
    settings={[
      {
        label: __('Hover Background', 'designsetgo'),
        colorValue: hoverBackgroundColor,
        onColorChange: (color) =>
          setAttributes({ hoverBackgroundColor: color || '' }),
        clearable: true,
      },
      {
        label: __('Hover Text', 'designsetgo'),
        colorValue: hoverTextColor,
        onColorChange: (color) =>
          setAttributes({ hoverTextColor: color || '' }),
        clearable: true,
      },
    ]}
    {...colorGradientSettings}
  />
</InspectorControls>
```

## Troubleshooting

### Issue: Colors not appearing in Styles tab
**Solution**: Ensure `InspectorControls` has `group="color"` prop

### Issue: Clear button not showing
**Solution**: Add `clearable: true` to each setting in the settings array

### Issue: Preset colors not working
**Solution**: Check color extraction logic converts slugs to CSS variables:
```javascript
(backgroundColor && `var(--wp--preset--color--${backgroundColor})`)
```

### Issue: Editor and frontend don't match
**Solution**: Ensure save.js has identical color extraction logic as edit.js

### Issue: Default colors always showing
**Solution**: Use `:not([style*="background"])` selector to only apply when no inline style

## Reference Blocks

Blocks using the manual `ColorGradientSettingsDropdown` pattern documented above include Icon Button, Row, Grid, Tabs, Accordion, Image Accordion, Counter, Modal, Timeline, Breadcrumbs, and others (`grep -rl "ColorGradientSettingsDropdown" src/blocks --include="edit.js"`). Blocks using the newer `useBlockColors` hook instead: Section, Card, Text Path, Hotspot, Slider, Section Divider, and Star Rating — see the hook note above.

## Related Documentation

- [WordPress Block Editor Handbook - InspectorControls](https://developer.wordpress.org/block-editor/reference-guides/components/inspector-controls/)
- [WordPress Block Color Support](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/#color)
- [Theme.json Color Palette](https://developer.wordpress.org/themes/global-settings-and-styles/settings/)
