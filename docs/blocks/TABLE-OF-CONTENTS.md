# Table of Contents Block - User Guide

**Version**: 1.0.0
**Category**: Design
**Keywords**: toc, contents, navigation, headings, anchor, outline, scroll spy

## Overview

The **Table of Contents Block** automatically generates a navigable outline of your page content by scanning for heading blocks (H2-H6). It provides smooth scrolling navigation, scroll spy highlighting, and responsive mobile support—making it perfect for long-form content, documentation, and tutorials.

Unlike manual anchor links or heavy plugins, this block updates dynamically in the editor, requires no manual maintenance, and is fully accessible with keyboard navigation and screen reader support.

## 🚀 Quick Start: Adding a Table of Contents

1. **Add the Block**: Insert the **Table of Contents** block where you want your navigation to appear (typically near the top of your content).
2. **Add Headings**: Create heading blocks (H2, H3, etc.) throughout your post. The TOC will automatically detect them.
3. **Preview**: The editor shows a live preview of your table of contents as you add headings.
4. **Customize**: Adjust which heading levels to include, display style, and colors in the sidebar settings.

**That's it!** The block automatically generates IDs for your headings and builds the navigation links.

## ⚙️ Settings & Configuration

### Heading Levels

Select which heading levels to include in your table of contents:

**Include H2** (Default: On)
- Main sections of your content
- Recommended for most content

**Include H3** (Default: On)
- Subsections under H2
- Creates nested structure

**Include H4, H5, H6** (Default: Off)
- Deeper nesting levels
- Enable for highly structured content

**Tip**: Start with just H2 and H3. Add deeper levels only if your content requires detailed hierarchy.

### Display Settings

**Display Mode**
- **Hierarchical (Nested)**: Shows headings in a tree structure reflecting their hierarchy
  - H2 → H3 → H4 indentation
  - Best for well-structured content
- **Flat List**: Shows all headings at the same level
  - No indentation
  - Simpler appearance

**List Style**
- **Unordered (Bullets)**: Traditional bullet list (default)
- **Ordered (Numbers)**: Numbered list (1, 2, 3...)
  - Useful for step-by-step guides
  - Nested lists use letters (a, b, c...)

### Title Settings

**Show Title** (Default: On)
- Toggle the "Table of Contents" heading
- Disable for a more minimal look

**Title Text** (Default: "Table of Contents")
- Customize the heading text
- Examples: "Contents", "On this page", "Jump to..."

### Scroll Settings

**Smooth Scroll** (Default: On)
- Enables smooth animated scrolling when clicking links
- Disable for instant jump behavior

**Scroll Offset (px)** (Default: 0)
- Adds space from the top when scrolling to headings
- **Critical for sticky headers**: Set to your header height
  - Example: If your header is 80px tall, set offset to 80
- Range: 0-200px

**Tip**: If your headings are hidden behind a sticky header, increase the scroll offset until they're fully visible.

### Color Settings (Sidebar Group)

**Link Color**
- Color for table of contents links
- Defaults to theme text color
- Use theme colors for consistency

**Active Link Color**
- Color for the currently active section (scroll spy)
- Defaults to theme primary color
- Make it stand out for better navigation feedback

### Block Supports

The block also supports WordPress core features:

**Position** → **Sticky**
- Makes the TOC stay fixed while scrolling
- Perfect for long articles
- Access via block toolbar → Position → Sticky

**Spacing**
- Padding: Add internal spacing
- Margin: Position relative to other blocks
- Block Gap: Space between list items

**Typography**
- Font Size: Adjust link text size
- Line Height: Control vertical spacing
- Font Family: Match your theme

**Colors**
- Background: Add background color
- Text: Overall text color
- Links: Link color (also in dedicated panel)

**Border**
- Add borders, radius, and styling
- Great for card-style TOC

## 💡 Common Use Cases

### 1. Long Blog Posts
Add a TOC at the beginning to help readers navigate and understand the article structure at a glance.

**Settings**: H2 and H3, Hierarchical, Sticky position

### 2. Documentation Pages
Provide easy navigation through technical documentation with deep nesting.

**Settings**: H2-H5, Hierarchical, Ordered list, Sticky position

### 3. Tutorial Content
Help users jump to specific steps or sections in how-to guides.

**Settings**: H2 and H3, Ordered list, Smooth scroll enabled

### 4. Legal Pages (Privacy Policy, Terms)
Make lengthy legal documents more navigable and user-friendly.

**Settings**: H2 and H3, Hierarchical, Scroll offset for header

### 5. Resource Guides
Create comprehensive guides with clear section navigation.

**Settings**: H2-H4, Hierarchical, Sticky position with custom colors

## 🎨 Styling & Customization

### Card-Style TOC
1. Add **Background Color** (light gray or theme background)
2. Add **Padding** (2rem or 32px)
3. Add **Border Radius** (8px or 12px)
4. Optionally add subtle **Border**

### Sticky Sidebar TOC
1. Set **Width** to 25% or 300px (via Advanced → Dimensions)
2. Enable **Sticky Position** (via toolbar)
3. Float to the side using Column block
4. Works great in a 70/30 or 75/25 layout

### Minimal TOC
1. **Disable** "Show Title"
2. Use **Flat** display mode
3. **Unordered** list with no background
4. Smaller font size (14px or 0.875rem)

### Highlighted Active Section
1. Set **Link Color** to subtle gray (#666)
2. Set **Active Link Color** to vibrant theme color
3. Enables visual feedback as users scroll

## ✅ Best Practices

### DO:
- **Place near the top** of your content (after intro paragraph)
- **Use sticky positioning** for very long articles (5+ screens)
- **Set scroll offset** if you have a sticky header
- **Start with H2** - Don't skip to H3 without H2
- **Test on mobile** - TOC automatically becomes scrollable
- **Use descriptive headings** - TOC is only as good as your headings

### DON'T:
- **Skip heading levels** (H2 → H4) - breaks hierarchy
- **Include all levels** (H2-H6) unless truly necessary
- **Hide important content** in sections users might miss
- **Use decorative headings** that don't represent structure
- **Add multiple TOCs** to the same page (causes confusion)

## 📱 Mobile Behavior

The Table of Contents automatically adapts to mobile screens:

**Automatic Changes (< 782px)**:
- Maximum height: 400px (becomes scrollable)
- Reduced spacing and indentation
- Smaller title font size
- Custom scrollbar styling

**No configuration needed!** These are responsive design defaults.

**Tip**: If your mobile TOC is too long, consider reducing heading levels (disable H4-H6).

## ♿ Accessibility

The block is fully accessible and follows WCAG 2.1 guidelines:

**Keyboard Navigation**
- ✅ Tab through all links
- ✅ Enter/Space to activate
- ✅ Skip link for screen readers
- ✅ Focus indicators on all interactive elements

**Screen Reader Support**
- ✅ Semantic HTML (`<nav>`, `<a>`, `<ul>`)
- ✅ ARIA labels on links ("Jump to section: Heading text")
- ✅ Heading hierarchy preserved
- ✅ List semantics conveyed properly

**Other Features**
- ✅ High contrast mode support
- ✅ Reduced motion support (disables smooth scroll)
- ✅ Clear focus indicators
- ✅ Color is not the only indicator (underlines)

## 🔧 Advanced Features

### Scroll Spy
As users scroll the page, the TOC automatically highlights which section they're currently viewing using Intersection Observer API.

**How it works**:
1. Monitors heading visibility
2. Highlights corresponding link
3. Updates in real-time
4. No performance impact (uses browser API)

**To disable**: Turn off "Smooth Scroll" (also disables scroll spy)

### Auto-Generated IDs
The block automatically generates IDs for headings that don't have them:
- Format: `heading-text-0`, `heading-text-1`, etc.
- URL-safe (lowercase, hyphens, alphanumeric)
- Unique within the page

**Manual IDs**: You can still set custom IDs via heading block's Advanced → HTML Anchor setting.

### Dynamic Re-initialization
If you load content dynamically (AJAX, lazy load), trigger a re-scan:

```javascript
window.dispatchEvent(new Event('dsgo-reinit-toc'));
```

## 🐛 Troubleshooting

### TOC is Empty in Editor
**Problem**: "No headings found" message
**Solution**: Add heading blocks to your post. The TOC only shows headings that exist.

### Headings Hidden Behind Sticky Header
**Problem**: Clicking links scrolls too far
**Solution**: Increase "Scroll Offset" to match your header height (e.g., 80px)

### Wrong Headings Showing
**Problem**: TOC includes headings from sidebar/footer
**Solution**: The block excludes headings inside other TOC blocks, but includes all page headings. This is expected behavior.

### Scroll Spy Not Working
**Problem**: Active link doesn't update on scroll
**Solution**:
1. Enable "Smooth Scroll"
2. Check browser compatibility (requires IntersectionObserver API)
3. Older browsers may not support this feature

### TOC Not Sticky
**Problem**: TOC scrolls away
**Solution**: Use block toolbar → Position → Sticky (requires WordPress 6.7+)

## 🎯 Pro Tips

1. **Combine with Progress Bar**: Show reading progress alongside TOC navigation
2. **Use in Templates**: Add to page templates for consistent navigation
3. **Style for Print**: Add print-specific styles to show full outline when printing
4. **Deep Link Sharing**: Share URLs with `#heading-id` to link directly to sections
5. **SEO Benefit**: Well-structured headings (H2-H6) improve content organization signals

## 📊 Performance

**Lightweight & Fast**:
- Total size: ~20KB JavaScript (unminified)
- Uses efficient IntersectionObserver (not scroll events)
- Single initialization per block
- No external dependencies
- Minimal CSS footprint (~3KB)

**Browser Compatibility**:
- ✅ Chrome, Firefox, Safari, Edge (modern versions)
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)
- ⚠️ IE11 not supported (lacks IntersectionObserver)

## 🔄 Version History

### 1.0.0 (November 2025)
**Initial Release**
- Auto-scanning of H2-H6 headings
- Hierarchical and flat display modes
- Ordered and unordered list styles
- Smooth scroll with customizable offset
- Scroll spy with active link highlighting
- Mobile responsive design
- Full accessibility support (WCAG 2.1)
- WordPress block supports (spacing, colors, typography)
- Sticky positioning support

## 📚 Related Blocks

- **Heading Block** (Core): Create the headings that populate the TOC
- **Section Block**: Organize content into sections with headings
- **Anchor Block**: Manual anchor points (TOC auto-generates these)

## 🆘 Support

Need help? Check these resources:

1. **Plugin Documentation**: `/docs/` directory in plugin
2. **WordPress Support Forums**: Search for "DesignSetGo"
3. **GitHub Issues**: Report bugs or request features
4. **Community Slack**: Get help from other users

---

**Built with ❤️ by the DesignSetGo team**
