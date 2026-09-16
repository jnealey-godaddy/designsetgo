# Color Contrast Validation Guide
**DesignSetGo WordPress Plugin**

## Overview

This guide shows how to add WCAG 2.1 AA color contrast validation to blocks using the included contrast checking utilities.

> **Note:** The inspector examples below use a bare `PanelBody`. Current blocks wrap custom controls in `<DsgoInspectorPanel>` / `<DsgoInspectorPanel.Item>` instead (Settings → Style → Advanced) — see `../../.claude/CLAUDE.md` ("Inspector IA (Theme 3)"). The contrast-checking utilities and functions below are current.

## Available Utilities

### Contrast Checker (`src/utils/contrast-checker.js`)

```javascript
import {
    validateContrast,
    getContrastRatio,
    meetsWCAG,
} from '../utils/contrast-checker';
```

**Functions:**
- `validateContrast(foreground, background, isLargeText)` - Complete validation with details
- `getContrastRatio(color1, color2)` - Calculate contrast ratio (1-21)
- `meetsWCAG(ratio, level, isLargeText)` - Check if ratio meets standard
- `parseColor(color)` - Parse any CSS color to RGB
- `suggestAccessibleColors(foreground, background)` - Get color suggestions

### React Components (`src/components/ContrastNotice.js`)

```javascript
import ContrastNotice, { ContrastIndicator } from '../components/ContrastNotice';
```

**Components:**
- `<ContrastNotice />` - Full notice banner with warning
- `<ContrastIndicator />` - Compact inline indicator with ratio

## Quick Start

### 1. Add to Blocks with Custom Color Controls

For blocks using `ColorGradientSettingsDropdown`:

```javascript
import { InspectorControls } from '@wordpress/block-editor';
import {
    // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
    __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
} from '@wordpress/block-editor';
import ContrastNotice from '../../components/ContrastNotice';

export default function MyBlockEdit({ attributes, setAttributes }) {
    const { textColor, backgroundColor } = attributes;

    return (
        <>
            <InspectorControls group="color">
                {/* Show contrast warning before color controls */}
                <ContrastNotice
                    textColor={textColor}
                    backgroundColor={backgroundColor}
                />

                <ColorGradientSettingsDropdown
                    title={__('Colors', 'designsetgo')}
                    settings={[
                        {
                            label: __('Text Color', 'designsetgo'),
                            colorValue: textColor,
                            onColorChange: (color) =>
                                setAttributes({ textColor: color || '' }),
                        },
                        {
                            label: __('Background Color', 'designsetgo'),
                            colorValue: backgroundColor,
                            onColorChange: (color) =>
                                setAttributes({ backgroundColor: color || '' }),
                        },
                    ]}
                    {...colorGradientSettings}
                />
            </InspectorControls>

            {/* Your block content */}
        </>
    );
}
```

### 2. Add to Blocks Using Block Supports

For blocks that use WordPress Block Supports for colors:

```javascript
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { ContrastIndicator } from '../../components/ContrastNotice';

export default function MyBlockEdit({ attributes }) {
    const blockProps = useBlockProps();

    // Extract colors from block supports
    const textColor = blockProps.style?.color;
    const backgroundColor = blockProps.style?.backgroundColor;

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Accessibility', 'designsetgo')}>
                    <ContrastIndicator
                        textColor={textColor}
                        backgroundColor={backgroundColor}
                    />
                </PanelBody>
            </InspectorControls>

            <div {...blockProps}>
                {/* Your content */}
            </div>
        </>
    );
}
```

## Usage Examples

### Full Notice (Recommended for Custom Color Blocks)

```javascript
<ContrastNotice
    textColor="#333333"
    backgroundColor="#ffffff"
    isLargeText={false}
    isDismissible={false}
/>
```

**When to use:**
- Custom color controls in InspectorControls
- Before/after ColorGradientSettingsDropdown
- When both text and background colors are customizable

### Compact Indicator (For Block Supports)

```javascript
<ContrastIndicator
    textColor={textColor}
    backgroundColor={backgroundColor}
    isLargeText={fontSize >= 18}
/>
```

**When to use:**
- Blocks using WordPress Block Supports for colors
- Space-constrained UIs
- Accessibility panel summaries

## WCAG Standards Reference

### Contrast Requirements

| Text Size | WCAG AA | WCAG AAA |
|-----------|---------|----------|
| Normal text (< 18pt) | 4.5:1 | 7:1 |
| Large text (≥ 18pt or ≥ 14pt bold) | 3:1 | 4.5:1 |

### Text Size Detection

```javascript
// Determine if text is "large" for WCAG purposes
const fontSize = attributes.fontSize || 16; // Default to 16px
const fontWeight = attributes.fontWeight || 400;

const isLargeText =
    fontSize >= 18 || // 18pt+ is always large
    (fontSize >= 14 && fontWeight >= 700); // 14pt+ bold is large

<ContrastNotice
    textColor={textColor}
    backgroundColor={backgroundColor}
    isLargeText={isLargeText}
/>
```

## Real-World Examples

### Example 1: Container Block with Hover Colors

```javascript
// src/blocks/flex/edit.js
import ContrastNotice from '../../components/ContrastNotice';

export default function FlexEdit({ attributes, setAttributes }) {
    const {
        textColor,
        backgroundColor,
        hoverTextColor,
        hoverBackgroundColor,
    } = attributes;

    return (
        <>
            <InspectorControls group="color">
                {/* Regular state contrast */}
                <ContrastNotice
                    textColor={textColor}
                    backgroundColor={backgroundColor}
                />

                <ColorGradientSettingsDropdown
                    title={__('Default Colors', 'designsetgo')}
                    settings={[
                        {
                            label: __('Text Color', 'designsetgo'),
                            colorValue: textColor,
                            onColorChange: (color) =>
                                setAttributes({ textColor: color || '' }),
                        },
                        {
                            label: __('Background Color', 'designsetgo'),
                            colorValue: backgroundColor,
                            onColorChange: (color) =>
                                setAttributes({ backgroundColor: color || '' }),
                        },
                    ]}
                    {...colorGradientSettings}
                />

                {/* Hover state contrast */}
                {hoverTextColor && hoverBackgroundColor && (
                    <ContrastNotice
                        textColor={hoverTextColor}
                        backgroundColor={hoverBackgroundColor}
                    />
                )}

                <ColorGradientSettingsDropdown
                    title={__('Hover Colors', 'designsetgo')}
                    settings={[
                        {
                            label: __('Hover Text Color', 'designsetgo'),
                            colorValue: hoverTextColor,
                            onColorChange: (color) =>
                                setAttributes({ hoverTextColor: color || '' }),
                        },
                        {
                            label: __('Hover Background Color', 'designsetgo'),
                            colorValue: hoverBackgroundColor,
                            onColorChange: (color) =>
                                setAttributes({ hoverBackgroundColor: color || '' }),
                        },
                    ]}
                    {...colorGradientSettings}
                />
            </InspectorControls>
        </>
    );
}
```

### Example 2: Button Block with Multiple States

```javascript
import { useState } from '@wordpress/element';
import { ToggleGroupControl, ToggleGroupControlOption } from '@wordpress/components';
import ContrastNotice from '../../components/ContrastNotice';

export default function ButtonEdit({ attributes }) {
    const [previewState, setPreviewState] = useState('default');

    const colors = {
        default: {
            text: attributes.textColor,
            background: attributes.backgroundColor,
        },
        hover: {
            text: attributes.hoverTextColor,
            background: attributes.hoverBackgroundColor,
        },
        active: {
            text: attributes.activeTextColor,
            background: attributes.activeBackgroundColor,
        },
    };

    return (
        <InspectorControls>
            <PanelBody title={__('Accessibility', 'designsetgo')}>
                {/* State selector */}
                <ToggleGroupControl
                    label={__('Preview State', 'designsetgo')}
                    value={previewState}
                    onChange={setPreviewState}
                    isBlock
                >
                    <ToggleGroupControlOption value="default" label="Default" />
                    <ToggleGroupControlOption value="hover" label="Hover" />
                    <ToggleGroupControlOption value="active" label="Active" />
                </ToggleGroupControl>

                {/* Contrast check for selected state */}
                <ContrastIndicator
                    textColor={colors[previewState].text}
                    backgroundColor={colors[previewState].background}
                />
            </PanelBody>
        </InspectorControls>
    );
}
```

## Programmatic Validation

### Check Contrast in JavaScript

```javascript
import { validateContrast } from '../utils/contrast-checker';

// In your component
const checkColors = () => {
    const result = validateContrast('#333', '#fff');

    console.log(result);
    // {
    //     isValid: true,
    //     ratio: 12.63,
    //     level: 'AAA',
    //     message: 'Excellent contrast (12.63:1)',
    //     meetsAA: true,
    //     meetsAAA: true
    // }

    if (!result.isValid) {
        // Show warning or prevent save
        alert('Color contrast is too low!');
    }
};
```

### Prevent Invalid Combinations

```javascript
const onBackgroundChange = (color) => {
    const result = validateContrast(textColor, color);

    if (!result.isValid) {
        // Show warning but still allow
        alert(__('Warning: This color combination may be hard to read', 'designsetgo'));
    }

    setAttributes({ backgroundColor: color });
};
```

## Testing

### Manual Testing

1. **Use Browser DevTools**
   - Inspect element
   - Check computed contrast ratio in Accessibility panel (Chrome/Edge)

2. **Online Tools**
   - [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/)
   - [Coolors Contrast Checker](https://coolors.co/contrast-checker)

3. **Browser Extensions**
   - [axe DevTools](https://www.deque.com/axe/devtools/)
   - [WAVE](https://wave.webaim.org/extension/)

### Automated Testing

```javascript
// Example Jest test
import { validateContrast } from '../utils/contrast-checker';

describe('Color Contrast', () => {
    it('should pass AA for black text on white', () => {
        const result = validateContrast('#000', '#fff');
        expect(result.isValid).toBe(true);
        expect(result.meetsAA).toBe(true);
    });

    it('should fail AA for light gray on white', () => {
        const result = validateContrast('#ccc', '#fff');
        expect(result.isValid).toBe(false);
    });

    it('should consider large text requirements', () => {
        const result = validateContrast('#777', '#fff', true);
        expect(result.meetsAA).toBe(true); // 3:1 for large text
    });
});
```

## Common Patterns

### Pattern 1: Overlay Text on Images

```javascript
// When text appears over background images
<ContrastNotice
    textColor={overlayTextColor}
    backgroundColor={overlayBackgroundColor || 'rgba(0, 0, 0, 0.5)'}
/>
```

### Pattern 2: Gradient Backgrounds

```javascript
// For gradient backgrounds, test against darkest/lightest color
import { parseColor } from '../utils/contrast-checker';

const gradientColors = ['#1e3a8a', '#3b82f6', '#93c5fd'];
const darkest = '#1e3a8a'; // Manually determine or calculate

<ContrastNotice
    textColor={textColor}
    backgroundColor={darkest}
/>
```

### Pattern 3: Themeable Blocks

```javascript
// Check against theme default colors
import { useSettings } from '@wordpress/block-editor';

export default function ThemedBlockEdit() {
    const [themePalette] = useSettings('color.palette');
    const baseColor = themePalette?.find(c => c.slug === 'base')?.color || '#fff';
    const contrastColor = themePalette?.find(c => c.slug === 'contrast')?.color || '#000';

    return (
        <ContrastIndicator
            textColor={contrastColor}
            backgroundColor={baseColor}
        />
    );
}
```

## Best Practices

### ✅ DO:
- Always validate custom text/background color combinations
- Show warnings prominently but allow users to proceed
- Test hover, focus, and active states
- Consider large text (≥18pt) vs normal text
- Provide helpful guidance on fixing issues

### ❌ DON'T:
- Block users from saving if contrast is low (warn instead)
- Validate decorative elements (backgrounds without text)
- Check contrast for elements hidden from screen readers
- Assume default theme colors meet WCAG (always verify)

## Migration Checklist

To add contrast validation to existing blocks:

- [ ] Import `ContrastNotice` or `ContrastIndicator`
- [ ] Identify all color attributes (text, background, borders)
- [ ] Add notice/indicator to `InspectorControls`
- [ ] Position before or after color controls
- [ ] Test with various color combinations
- [ ] Update block documentation
- [ ] Add to accessibility testing checklist

## Resources

- [WCAG 2.1 Contrast Guidelines](https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum.html)
- [WebAIM Contrast and Color](https://webaim.org/articles/contrast/)
- [Accessible Color Palette Builder](https://toolness.github.io/accessible-color-matrix/)

---

**Last Updated:** 2025-11-08
**Plugin Version:** 1.0.0
