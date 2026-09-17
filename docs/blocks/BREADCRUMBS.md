# Breadcrumbs Block - User Guide

**Version**: 1.0.0
**Category**: Design
**Keywords**: breadcrumb, navigation, hierarchy, seo

## Overview

The **Breadcrumbs Block** displays navigation breadcrumbs with Schema.org markup for improved UX and SEO. It automatically builds a trail based on the current page context--showing parent pages for hierarchical content and categories for posts--giving visitors a clear sense of where they are within your site.

Unlike manual breadcrumb implementations or heavyweight SEO plugins, this block integrates natively with the WordPress editor, provides a live preview, and outputs structured data for search engines out of the box.

## Quick Start: Adding Breadcrumbs

1. **Add the Block**: Insert the **Breadcrumbs** block where you want the trail to appear (typically at the top of your page or post template).
2. **Preview**: The editor shows a live preview of the breadcrumb trail based on the current page context (parent pages, categories, and post title).
3. **Customize**: Adjust the separator style, home link text, and visibility options in the sidebar settings.
4. **Publish**: The block renders semantic HTML with Schema.org markup on the front end.

**That's it!** The block automatically detects your page hierarchy and builds the breadcrumb trail.

## Settings & Configuration

### Display Settings

**Show Home Link** (`showHome`) - Default: `true`
- Prepends a "Home" link at the start of the breadcrumb trail
- Recommended for most sites to provide a clear root anchor

**Home Text** (`homeText`) - Default: `"Home"`
- Customize the label for the home link
- Examples: "Home", "Start", "Main", or your site name

**Separator** (`separator`) - Default: `"slash"`
- Choose the visual divider between breadcrumb items
- Options:
  - `slash` - `/`
  - `chevron` - `>`
  - `greater` - `>`
  - `bullet` - bullet character
  - `arrow-right` - right arrow

**Show Current Page** (`showCurrent`) - Default: `true`
- Displays the current page or post title as the last item in the trail
- Disable for a shorter trail that ends at the parent

**Link Current Page** (`linkCurrent`) - Default: `false`
- When enabled, the current page title is rendered as a clickable link
- When disabled (default), the current page is rendered as plain text

**Prefix Text** (`prefixText`) - Default: `""` (empty)
- Add a text label before the breadcrumb trail
- Examples: "You are here:", "Navigate:"

**Hide on Home Page** (`hideOnHome`) - Default: `true`
- Automatically hides the breadcrumb block when viewing the home/front page
- Prevents displaying a redundant single-item trail on the homepage

## Attributes Reference

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `showHome` | `boolean` | `true` | Show the home link at the start of the trail |
| `homeText` | `string` | `"Home"` | Label text for the home link |
| `separator` | `string` | `"slash"` | Separator style between items (`slash`, `chevron`, `greater`, `bullet`, `arrow-right`) |
| `showCurrent` | `boolean` | `true` | Show the current page title in the trail |
| `linkCurrent` | `boolean` | `false` | Make the current page title a clickable link |
| `prefixText` | `string` | `""` | Optional text displayed before the breadcrumb trail |
| `hideOnHome` | `boolean` | `true` | Hide the block entirely on the home/front page |

## Block Supports

The block supports the following WordPress core features:

**Anchor**
- Set a custom HTML anchor (ID) for the block
- Useful for linking directly to the breadcrumbs

**Alignment**
- Wide alignment
- Full-width alignment

**Spacing**
- Margin: Position relative to surrounding blocks
- Padding: Add internal spacing around the trail
- Block Gap: Control spacing between breadcrumb items

**Color**
- Text: Overall text color (separators, prefix text)
- Link: Breadcrumb link color (exposed by default in the sidebar)
- Link Hover: Breadcrumb link hover color (via color panel)
- Background: Background color behind the trail

**Typography**
- Font Size: Adjust breadcrumb text size (exposed by default in the sidebar)
- Font Weight: Control text weight
- Line Height: Adjust vertical spacing

**Border**
- Border Color
- Border Radius
- Border Style
- Border Width

**Note**: Direct HTML editing is disabled (`html: false`) to preserve structured data integrity.

## Common Use Cases

### 1. Site-Wide Template Breadcrumbs
Add the block to your theme's single post or page template to display breadcrumbs consistently across the site.

**Settings**: Show Home on, Chevron separator, Hide on Home Page on

### 2. Documentation Sites
Help users navigate through nested documentation pages with clear hierarchy indicators.

**Settings**: Show Home on, Slash separator, Show Current on, Prefix Text "You are here:"

### 3. E-Commerce Category Navigation
Guide shoppers through product category hierarchies back to the shop root.

**Settings**: Show Home on (Home Text: "Shop"), Arrow-right separator, Show Current on

### 4. Blog Posts with Categories
Show the category path for blog posts so readers can discover related content.

**Settings**: Show Home on, Chevron separator, Link Current off

## Styling & Customization

### Subtle Bar Style
1. Add a light **Background Color** (e.g., light gray)
2. Add **Padding** (0.5rem top/bottom, 1rem left/right)
3. Set a small **Font Size** (13px or 0.8125rem)
4. Use a muted **Text Color** for separators

### Minimal Style
1. No background color
2. Set **Link Color** to a subtle gray
3. Use `slash` or `chevron` separator
4. Small font size to stay unobtrusive

### Branded Breadcrumbs
1. Set **Link Color** to your brand primary color
2. Set **Link Hover Color** to a darker shade
3. Add a bottom **Border** for visual separation
4. Use **Font Weight** to make links bolder

## Accessibility

The block follows WCAG 2.1 guidelines and outputs accessible, semantic HTML:

**Semantic Structure**
- Rendered inside a `<nav>` element for landmark navigation
- Uses an ordered list (`<ol>`) to convey sequence
- Each breadcrumb item is a list item (`<li>`)
- Separators are marked with `aria-hidden="true"` so screen readers skip them

**Keyboard Navigation**
- All breadcrumb links are focusable via Tab
- Enter/Space activates links
- Clear focus indicators on interactive elements

**Screen Reader Support**
- The `<nav>` landmark allows users to jump directly to breadcrumbs
- List semantics convey the number of items and their order
- Current page item is rendered as plain text (not a link) by default, clearly distinguishing it from navigable items

**Schema.org Structured Data**
- Outputs BreadcrumbList schema markup for search engine rich results
- Improves how your pages appear in search results (breadcrumb trails in SERPs)

## Troubleshooting

### Breadcrumbs Not Showing on the Front Page
**Problem**: The block is invisible on the home page
**Solution**: This is expected when "Hide on Home Page" is enabled (the default). Disable `hideOnHome` if you want breadcrumbs on the front page.

### Only "Home" Appears in the Trail
**Problem**: The trail shows only the home link
**Solution**: Ensure "Show Current Page" is enabled. If the page has no parent pages or categories, the trail will naturally be short.

### Editor Preview Says "Loading breadcrumbs preview..."
**Problem**: The preview does not render
**Solution**: Wait for the editor to finish loading post data. If it persists, save the post and reload the editor.

### Separator Not Changing
**Problem**: The separator looks the same after changing the setting
**Solution**: Some separators look similar at small font sizes. Try increasing the font size or switching to a more distinct option like `arrow-right`.

## Context

The block uses the following WordPress block context:

- `postId` - The current post ID
- `postType` - The current post type (e.g., `page`, `post`)

This context is used to determine the breadcrumb trail hierarchy (parent pages for pages, categories for posts).

## Version History

### 1.0.0
**Initial Release**
- Automatic breadcrumb trail generation from page hierarchy
- Five separator styles (slash, chevron, greater, bullet, arrow-right)
- Configurable home link with custom text
- Current page display with optional linking
- Optional prefix text
- Hide on home page option
- Schema.org BreadcrumbList structured data
- Full WordPress block supports (spacing, colors, typography, border)
- Live editor preview with context-aware trail
- WCAG 2.1 accessible markup

## Related Blocks

- **Navigation Block** (Core): Full site navigation menu
- **Table of Contents Block** (DesignSetGo): In-page heading navigation
- **Section Block**: Organize page content into distinct areas

## Support

Need help? Check these resources:

1. **Plugin Documentation**: `/docs/` directory in plugin
2. **WordPress Support Forums**: Search for "DesignSetGo"
3. **GitHub Issues**: Report bugs or request features
4. **Community Slack**: Get help from other users

---

**Built by the DesignSetGo team**
