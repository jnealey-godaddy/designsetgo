# Modal Block - FSE & Block API Compatibility

**Version**: 1.2.0
**Last Updated**: 2025-11-19
**Status**: Production Ready

## Overview

The DesignSetGo Modal block is fully compatible with WordPress Full Site Editing (FSE) and leverages the complete Block API for maximum flexibility and theme integration.

## Block API Supports

The Modal block implements comprehensive `supports` in block.json for seamless integration with WordPress themes and the block editor.

### Spacing Controls

```json
"spacing": {
  "margin": false,
  "padding": true,
  "blockGap": true,
  "__experimentalDefaultControls": {
    "padding": true,
    "blockGap": true
  }
}
```

**Available Settings**:
- ✅ **Padding**: Control inner spacing of modal content
- ✅ **Block Gap**: Space between inner blocks
- ❌ **Margin**: Disabled (modals are portaled to body)

**Access**: Settings Sidebar → Dimensions panel

### Color Controls

```json
"color": {
  "background": true,
  "text": true,
  "link": true,
  "gradients": true,
  "__experimentalDefaultControls": {
    "background": true,
    "text": true
  }
}
```

**Available Settings**:
- ✅ **Background Color/Gradient**: Modal content background
- ✅ **Text Color**: Default text color for modal content
- ✅ **Link Color**: Link styling within modal
- ✅ **Custom Colors**: Full theme palette support
- ✅ **Gradients**: Background gradient support

**Access**: Settings Sidebar → Styles → Color panel

### Typography Controls

```json
"typography": {
  "fontSize": true,
  "lineHeight": true,
  "__experimentalFontFamily": true,
  "__experimentalFontWeight": true,
  "__experimentalFontStyle": true,
  "__experimentalTextTransform": true,
  "__experimentalTextDecoration": true,
  "__experimentalLetterSpacing": true,
  "__experimentalDefaultControls": {
    "fontSize": true
  }
}
```

**Available Settings**:
- ✅ **Font Size**: Theme font sizes + custom
- ✅ **Line Height**: Custom line spacing
- ✅ **Font Family**: Theme fonts + system fonts
- ✅ **Font Weight**: 100-900 weight options
- ✅ **Font Style**: Normal/Italic
- ✅ **Text Transform**: Uppercase/Lowercase/Capitalize
- ✅ **Text Decoration**: Underline/Strikethrough
- ✅ **Letter Spacing**: Custom character spacing

**Access**: Settings Sidebar → Styles → Typography panel

### Border Controls

```json
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
```

**Available Settings**:
- ✅ **Border Color**: Custom colors + theme palette
- ✅ **Border Radius**: Rounded corners (px/em/rem/%)
- ✅ **Border Style**: Solid/Dashed/Dotted
- ✅ **Border Width**: Custom thickness per side

**Access**: Settings Sidebar → Styles → Border panel

### Dimensions

```json
"dimensions": {
  "minHeight": true,
  "__experimentalDefaultControls": {
    "minHeight": false
  }
}
```

**Available Settings**:
- ✅ **Min Height**: Ensure minimum modal height
- ✅ **Width**: Custom modal width (also in Settings panel)
- ✅ **Max Width**: Responsive width constraints
- ✅ **Height**: Custom modal height
- ✅ **Max Height**: Prevent overly tall modals

**Access**: Settings Sidebar → Dimensions panel

### Shadow Support

```json
"shadow": true
```

**Available Settings**:
- ✅ **Box Shadow**: Add depth with shadows
- ✅ **Custom Shadows**: Full shadow customization
- ✅ **Theme Shadows**: Use theme-defined shadow presets

**Access**: Settings Sidebar → Styles → Shadow panel

### Additional Supports

```json
"anchor": true,           // HTML anchor for direct linking
"customClassName": true,  // Custom CSS classes
"className": true,        // Default class name support
"interactivity": {
  "clientNavigation": true  // Client-side navigation support
}
```

## Theme Integration

### Using Theme Colors

The Modal block fully supports theme.json color palettes:

```json
{
  "settings": {
    "color": {
      "palette": [
        {
          "name": "Primary",
          "slug": "primary",
          "color": "#007cba"
        }
      ]
    }
  }
}
```

Users can select theme colors for:
- Modal background
- Text color
- Link color
- Overlay color (custom control)
- Close button colors (custom control)

### Using Theme Typography

The Modal block inherits theme typography settings:

```json
{
  "settings": {
    "typography": {
      "fontFamilies": [
        {
          "fontFamily": "System",
          "name": "System Font",
          "slug": "system"
        }
      ],
      "fontSizes": [
        {
          "name": "Small",
          "size": "0.875rem",
          "slug": "small"
        }
      ]
    }
  }
}
```

### Using Theme Spacing

Block gap and padding respect theme spacing scale:

```json
{
  "settings": {
    "spacing": {
      "spacingSizes": [
        {
          "name": "Small",
          "size": "1rem",
          "slug": "50"
        }
      ]
    }
  }
}
```

## Block Patterns

The Modal block includes 4 pre-built patterns for common use cases:

### 1. Newsletter Signup Modal

**Pattern Slug**: `designsetgo/modal/newsletter-signup`
**Category**: DesignSetGo: Modals

**Includes**:
- Icon button trigger ("Subscribe to Newsletter")
- Modal with exit intent trigger
- Form builder with email field
- Optimized dimensions (500px wide)

**Features**:
- Exit intent auto-trigger
- Shows once per user (cookie tracking)
- Mobile-responsive
- AJAX form submission

### 2. Announcement & Promo Modal

**Pattern Slug**: `designsetgo/modal/announcement-promo`
**Category**: DesignSetGo: Modals

**Includes**:
- Auto-opening modal (no trigger button needed)
- Promotional heading with emoji
- Call-to-action button

**Features**:
- Page load trigger (2-second delay)
- Shows once per session
- Zoom animation
- Perfect for limited-time offers

### 3. Contact Form Modal

**Pattern Slug**: `designsetgo/modal/contact-form`
**Category**: DesignSetGo: Modals

**Includes**:
- Icon button trigger ("Contact Us")
- Modal with contact form
- Form builder with name, email, message fields

**Features**:
- Click trigger
- AJAX form submission
- Success message
- Accessible form validation

### 4. Image Gallery with Navigation

**Pattern Slug**: `designsetgo/modal/image-gallery`
**Category**: DesignSetGo: Modals

**Includes**:
- Three trigger buttons for gallery items
- Three linked modals (gallery group)
- Image + caption layout per modal

**Features**:
- Gallery navigation (prev/next buttons)
- Keyboard navigation (arrow keys)
- Swipe gestures on mobile
- Lightbox-optimized styling

**Gallery Navigation**:
- All modals share `galleryGroupId: "image-gallery"`
- Sequential `galleryIndex`: 0, 1, 2
- Arrow navigation on sides
- Dark overlay (95% opacity)

## FSE Theme Compatibility

### Tested Themes

- ✅ **Twenty Twenty-Five** (Block theme)
- ✅ **Twenty Twenty-Four** (Block theme)
- ✅ **Twenty Twenty-Three** (Block theme)

### Template Editor Support

The Modal block works seamlessly in:
- **Template Editor**: Add modals to headers, footers, 404 pages
- **Template Parts**: Include modals in reusable template parts
- **Page Templates**: Full support in custom page templates
- **Post Templates**: Add modals to single post templates

**Example Use Cases**:
- Site-wide newsletter signup in footer template part
- Cookie notice in site header template
- 404 page modal with search functionality
- Product modals in WooCommerce templates

### Global Styles Support

The Modal block fully respects theme.json global styles:

```json
{
  "styles": {
    "blocks": {
      "designsetgo/modal": {
        "color": {
          "background": "var(--wp--preset--color--base)",
          "text": "var(--wp--preset--color--contrast)"
        },
        "typography": {
          "fontSize": "var(--wp--preset--font-size--medium)"
        },
        "spacing": {
          "padding": {
            "top": "var(--wp--preset--spacing--50)",
            "bottom": "var(--wp--preset--spacing--50)",
            "left": "var(--wp--preset--spacing--60)",
            "right": "var(--wp--preset--spacing--60)"
          }
        }
      }
    }
  }
}
```

## Best Practices for FSE

### 1. Use Theme Presets

**Recommended**:
```
Background: Theme Primary Color
Text: Theme Contrast Color
Font Size: Theme Medium
```

**Avoid**:
```
Background: #007cba (hardcoded)
Text: #333333 (hardcoded)
Font Size: 16px (fixed)
```

### 2. Responsive Design

- Use `vw` units for `maxWidth` (e.g., `90vw`)
- Use `vh` units for `maxHeight` (e.g., `90vh`)
- Test on mobile, tablet, desktop
- Utilize theme spacing scale for consistent gaps

### 3. Accessibility

- ✅ Always provide meaningful trigger text
- ✅ Use semantic heading levels
- ✅ Ensure sufficient color contrast
- ✅ Test keyboard navigation (Tab, Esc, Arrow keys)
- ✅ Verify screen reader compatibility

### 4. Performance

- ✅ Limit modals to 3-5 per page
- ✅ Use auto-triggers sparingly (avoid multiple on same page)
- ✅ Optimize images in gallery modals
- ✅ Set appropriate `cookieDuration` for auto-triggers

## Example: Custom Theme Styling

Create a custom theme.json to style all modals site-wide:

```json
{
  "$schema": "https://schemas.wp.org/trunk/theme.json",
  "version": 2,
  "styles": {
    "blocks": {
      "designsetgo/modal": {
        "color": {
          "background": "var(--wp--preset--color--base-2)",
          "text": "var(--wp--preset--color--contrast)"
        },
        "typography": {
          "fontSize": "var(--wp--preset--font-size--medium)",
          "fontFamily": "var(--wp--preset--font-family--primary)"
        },
        "spacing": {
          "padding": {
            "top": "var(--wp--preset--spacing--60)",
            "bottom": "var(--wp--preset--spacing--60)",
            "left": "var(--wp--preset--spacing--70)",
            "right": "var(--wp--preset--spacing--70)"
          },
          "blockGap": "var(--wp--preset--spacing--50)"
        },
        "border": {
          "radius": "8px"
        },
        "shadow": "var(--wp--preset--shadow--natural)"
      }
    }
  }
}
```

This will apply consistent styling to all Modal blocks across your site while still allowing per-instance customization.

## Migration from Classic Themes

If migrating from a classic theme to a block theme:

1. **Check Modal IDs**: Ensure `modalId` values don't conflict across templates
2. **Update Colors**: Replace hardcoded hex colors with theme palette
3. **Update Typography**: Use theme font sizes instead of px values
4. **Test Triggers**: Verify trigger links/buttons work in new template structure
5. **Review Auto-Triggers**: Ensure auto-trigger modals don't duplicate across templates

## Troubleshooting

### Modal doesn't match theme colors

**Solution**: Clear customizations and select colors from theme palette instead of custom colors.

**Steps**:
1. Select modal block
2. Sidebar → Styles → Color
3. Click "Reset" on each color
4. Select from "Theme" tab instead of "Custom"

### Typography not inheriting

**Solution**: Ensure font family support is enabled in theme.json:

```json
{
  "settings": {
    "typography": {
      "fontFamilies": true
    }
  }
}
```

### Patterns not appearing

**Solution**: Verify pattern category is registered:

```php
register_block_pattern_category(
  'dsgo-modal',
  array( 'label' => __( 'DesignSetGo: Modals', 'designsetgo' ) )
);
```

## Related Documentation

- [Modal Auto-Triggers](MODAL-AUTO-TRIGGERS.md)
- [Modal Gallery Navigation](MODAL-GALLERY-NAVIGATION.md)

---

**Last Updated**: November 2024
**Version**: 1.2.0
**WordPress Compatibility**: 6.4+
