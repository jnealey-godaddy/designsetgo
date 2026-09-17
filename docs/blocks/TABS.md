# Tabs Block - User Guide

**Version**: 1.0.0
**Category**: Design
**Keywords**: tabs, tabbed, accordion, toggle

## Overview

The Tabs block creates accessible tabbed content interfaces that organize information into clickable panels. Unlike accordions which stack vertically, tabs display navigation horizontally (or vertically) with content panels that switch when users click different tabs. Perfect for organizing related content, product information, pricing tiers, and feature comparisons.

**Key Features:**
- Horizontal and vertical tab orientations
- 4 visual tab styles (Default, Pills, Underline, Minimal)
- Optional icons on tab labels (with 3 position options)
- Responsive mobile modes (Accordion, Dropdown, or Scrollable Tabs)
- Deep linking support (URL hash navigation)
- Comprehensive color customization
- Keyboard navigation (arrow keys, Home/End)
- Fully accessible (WCAG 2.1 AA compliant)
- Mobile-optimized with configurable breakpoint

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Tab Orientations](#tab-orientations)
3. [Tab Styles](#tab-styles)
4. [Settings & Configuration](#settings--configuration)
5. [Mobile Responsiveness](#mobile-responsiveness)
6. [Deep Linking](#deep-linking)
7. [Common Use Cases](#common-use-cases)
8. [Styling Options](#styling-options)
9. [Best Practices](#best-practices)
10. [Accessibility](#accessibility)
11. [FAQ](#faq)

---

## Quick Start

### Creating Your First Tabs

1. **Add the Tabs Block**
   - In the block editor, click the `+` button
   - Search for "Tabs" or find it in the "Design" category
   - The block starts with 3 tabs by default

2. **Edit Tab Titles**
   - Click on a tab in the navigation to select it
   - In the sidebar, edit the "Tab Title" field
   - The title updates in the tab navigation

3. **Add Content to Tabs**
   - Click a tab in the navigation to view its content panel
   - Click inside the content area to add blocks
   - Add any WordPress blocks (paragraphs, images, buttons, etc.)

4. **Customize Appearance** (optional)
   - Change tab style (Default, Pills, Underline, Minimal)
   - Adjust colors in the Color panel
   - Configure mobile behavior for smaller screens

---

## Tab Orientations

Choose how tabs are arranged on the page:

### Horizontal (Default)

**Layout:**
- Tab navigation appears across the top
- Content panels appear below
- Tabs flow left-to-right

**Alignment Options:**
- **Left**: Tabs aligned to left edge
- **Center**: Tabs centered
- **Right**: Tabs aligned to right edge
- **Justified**: Tabs stretch to fill full width

**Best For:**
- Most common use case
- Desktop-first designs
- 3-6 tabs (more can wrap on mobile)
- Standard website layouts

---

### Vertical

**Layout:**
- Tab navigation appears on the left side
- Content panels appear to the right
- Tabs stack top-to-bottom

**Best For:**
- Sidebar-style navigation
- Many tabs (8+ tabs)
- Longer tab titles
- Documentation and help centers
- Wide content areas

**Note**: Vertical tabs automatically adjust on mobile based on Mobile Mode setting.

---

## Tab Styles

Choose from 4 visual styles for tab navigation:

### 1. Default

**Appearance:**
- Bordered tabs with background colors
- Clear separation between tabs
- Active tab stands out with different background

**Best For:**
- Professional, business sites
- E-commerce product pages
- Dashboard interfaces
- Traditional web applications

**Visual Characteristics:**
- Border around each tab
- Background color fills
- Distinct active state

---

### 2. Pills

**Appearance:**
- Rounded, button-like tabs
- Compact, modern design
- Active tab has filled background

**Best For:**
- Modern, minimalist designs
- Mobile-first interfaces
- Small tab counts (3-5 tabs)
- Call-to-action focused designs

**Visual Characteristics:**
- Rounded corners (pill-shaped)
- Hover effects
- Clear active state with background fill

---

### 3. Underline

**Appearance:**
- Minimal tabs with bottom border
- Clean, text-focused design
- Active tab has underline indicator

**Best For:**
- Minimal, content-focused sites
- Blog layouts
- News and media sites
- Clean, modern aesthetics

**Visual Characteristics:**
- No background colors by default
- Bottom border on active tab
- Lightweight, unobtrusive

---

### 4. Minimal

**Appearance:**
- Text-only tabs
- No borders or backgrounds
- Active state shown with color only

**Best For:**
- Ultra-minimal designs
- Text-heavy interfaces
- Documentation sites
- When content should take center stage

**Visual Characteristics:**
- No visual decoration
- Relies on color for active state
- Most lightweight option

---

## Settings & Configuration

### Settings

**Orientation**
- **Horizontal**: Tabs across top (default)
- **Vertical**: Tabs along left side

**Tab Style**
- Default, Pills, Underline, or Minimal
- See [Tab Styles](#tab-styles) section for details

**Alignment** (Horizontal only)
- Left, Center, Right, or Justified
- Controls how tabs are positioned in navigation bar

**Gap Between Tabs**
- Range: 0-40px
- Default: 8px
- Controls spacing between individual tabs
- Smaller gaps: More compact
- Larger gaps: More breathing room

**Show Border Below Tabs**
- Toggle on/off
- Adds horizontal divider line between navigation and content
- Useful for visual separation
- Works with all tab styles

---

### Mobile Settings

Configure how tabs behave on smaller screens:

**Mobile Breakpoint (px)**
- Range: 320-1024px
- Default: 768px
- Screen width below which mobile mode activates
- Common values:
  - 480px: Small phones only
  - 768px: Tablets and phones (standard)
  - 1024px: Tablets in portrait mode

**Mobile Mode**
Choose how tabs transform on mobile:

1. **Accordion** (Default)
   - Tabs convert to vertical accordion
   - Each tab becomes a collapsible section
   - Space-efficient for small screens
   - Best for: Most use cases

2. **Dropdown**
   - Tabs become a select dropdown menu
   - User selects tab from dropdown
   - Very compact
   - Best for: Many tabs, limited space

3. **Tabs (Scrollable)**
   - Tabs remain as tabs but scroll horizontally
   - Swipe to see more tabs
   - Maintains desktop appearance
   - Best for: Visual consistency, fewer tabs

---

### Advanced Settings

**Enable Deep Linking**
- Toggle on/off
- Allows tabs to be accessed via URL hash
- Example: `yoursite.com/page#tab-features`
- Benefits:
  - Shareable tab links
  - Browser back button support
  - Direct navigation to specific tab
  - Improved SEO

**How Deep Linking Works:**
1. Enable "Deep Linking" in Advanced panel
2. Each tab gets an anchor based on its title
3. Share URLs with hash: `#tab-title`
4. When page loads with hash, corresponding tab opens
5. Browser back/forward buttons work with tabs

**Use Cases:**
- Product comparison pages (link to "Pricing" tab)
- Documentation (link to specific sections)
- Marketing pages (link to "Features" tab from email)
- Multi-step forms (bookmark progress)

---

### Color Settings

Comprehensive color controls in the "Color" panel:

**Tab Text**
- Text color for inactive tabs
- Default: Inherits from theme

**Tab Background**
- Background color for inactive tabs
- Applies to Default and Pills styles
- Minimal/Underline styles ignore this

**Active Tab Text**
- Text color for the selected tab
- Should contrast with Active Tab Background
- Provides clear visual feedback

**Active Tab Background**
- Background color for the selected tab
- Most important color for visual hierarchy
- Should stand out from inactive tabs

**Tab Border**
- Color for tab borders
- Applies to Default style primarily
- Subtle colors work best

**Tab Content Background**
- Background color for content panel area
- Independent from tab navigation colors
- Useful for visual distinction

**Color Best Practices:**
- Ensure Active Tab visually dominates
- Maintain 4.5:1 contrast ratio for text
- Test in both light and dark modes
- Keep inactive tabs subtle
- Use theme colors for consistency

---

## Mobile Responsiveness

### Mobile Breakpoint Strategy

**Default Breakpoint: 768px**
- Tablets in portrait mode and smaller screens

**Consider Changing If:**
- Your tabs are narrow: Use 480px (phones only)
- Your tabs are wide: Use 1024px (include tablets)
- You have many tabs: Lower breakpoint for earlier conversion

---

### Mobile Mode Comparison

| Mode | Pros | Cons | Best For |
|------|------|------|----------|
| **Accordion** | Space-efficient, familiar pattern | Multiple clicks to compare | Most use cases, FAQs |
| **Dropdown** | Very compact, works with many tabs | Less discoverable | Many tabs, advanced users |
| **Tabs (Scrollable)** | Maintains desktop look, swipeable | Can feel cramped | Few tabs, visual consistency |

---

### Mobile Testing Checklist

- [ ] Test at your chosen breakpoint
- [ ] Verify tap targets are large enough (min 44px)
- [ ] Check text readability on small screens
- [ ] Test swipe gestures (if using scrollable tabs)
- [ ] Verify dropdown works (if using dropdown mode)
- [ ] Test in both portrait and landscape
- [ ] Ensure content doesn't overflow horizontally

---

## Deep Linking

### Setting Up Deep Linking

1. **Enable in Tabs Block**
   - Select Tabs block
   - Go to "Advanced" panel
   - Toggle "Enable Deep Linking" on

2. **Automatic Anchor Generation**
   - Each Tab automatically gets an anchor from its title
   - "Features" tab → `#features`
   - "Pricing Plans" tab → `#pricing-plans`

3. **Custom Anchors** (optional)
   - Select individual Tab block
   - Set custom "Anchor (URL Hash)" in Tab Settings
   - Use URL-friendly names (lowercase, hyphens, no spaces)

---

### Using Deep Links

**Share Links:**
```
https://yoursite.com/products#features
https://yoursite.com/pricing#enterprise-plan
https://yoursite.com/docs#installation
```

**In Navigation Menus:**
- Add custom links pointing to tab anchors
- Users click menu item, page scrolls and tab opens

**In Content:**
- Link from other pages or sections
- Example: "Learn more in our Features tab" (link to `#features`)

**In Email Campaigns:**
- Direct users to specific tab content
- Improves conversion by reducing clicks

**URL Parameters + Tabs:**
- Combine with query parameters: `?product=pro#pricing`
- Great for analytics tracking

---

### Deep Linking Best Practices

**DO:**
- Use clear, descriptive anchors (`#pricing` not `#tab3`)
- Test links before sharing
- Keep anchors short and memorable
- Use consistent naming across your site

**DON'T:**
- Use special characters in anchors
- Change anchors after sharing (breaks links)
- Use duplicate anchors on the same page
- Rely solely on deep links (provide navigation too)

---

## Common Use Cases

### 1. Product Information

**Goal**: Organize product details into scannable sections

**Setup:**
1. Horizontal orientation
2. Pills or Default tab style
3. 3-5 tabs: "Overview", "Features", "Specifications", "Reviews"
4. Enable deep linking for sharing specific sections

**Tab Content Ideas:**
- **Overview**: Hero image, key benefits, CTA button
- **Features**: List with icons, feature highlights
- **Specifications**: Table or list of technical specs
- **Reviews**: Testimonials, ratings, customer quotes

**Tips:**
- Keep "Overview" tab first (default visible)
- Use icons on tabs for visual interest
- Add "Buy Now" buttons in relevant tabs
- Link to "Specifications" tab from product features

---

### 2. Pricing Tiers

**Goal**: Compare pricing plans side-by-side

**Setup:**
1. Horizontal orientation, justified alignment
2. Pills style for button-like appearance
3. 3 tabs: "Starter", "Professional", "Enterprise"
4. Accordion mode on mobile for easy comparison

**Tab Content:**
- Columns block with features list
- Pricing table
- "Choose Plan" button
- Feature comparison checklist

**Tips:**
- Highlight recommended plan visually
- Use active tab colors to emphasize selection
- Include annual vs monthly toggle inside content
- Add FAQ accordion below tabs

---

### 3. Documentation Sections

**Goal**: Organize help content into logical sections

**Setup:**
1. Vertical orientation (many sections)
2. Underline or Minimal style (clean, content-focused)
3. Tabs: "Getting Started", "Configuration", "API Reference", "Troubleshooting"
4. Enable deep linking for bookmarking

**Tab Content:**
- Headings for subsections
- Code blocks for technical content
- Images/screenshots for visual guidance
- "Next Steps" links at bottom

**Tips:**
- Use clear, action-oriented tab titles
- Keep navigation visible (vertical orientation)
- Add table of contents in first tab
- Cross-link between tabs when relevant

---

### 4. Event Information

**Goal**: Display event details in organized sections

**Setup:**
1. Horizontal orientation, center aligned
2. Default or Pills style
3. Tabs: "Schedule", "Speakers", "Venue", "Register"
4. Tabs mode on mobile (maintain visual consistency)

**Tab Content:**
- **Schedule**: Timeline, session list, breaks
- **Speakers**: Speaker bios with photos
- **Venue**: Map, directions, parking info
- **Register**: Registration form, pricing

**Tips:**
- Use icons (calendar, person, location, ticket)
- Keep most important tab first ("Register" or "Schedule")
- Add countdown timer in Register tab
- Include social sharing buttons

---

### 5. Team/Department Pages

**Goal**: Showcase team members or departments

**Setup:**
1. Horizontal orientation
2. Pills or Default style
3. Tabs by department: "Leadership", "Engineering", "Design", "Sales"
4. Accordion on mobile for vertical scrolling

**Tab Content:**
- Team member cards (image, name, role)
- Department description
- Contact information
- Department achievements

**Tips:**
- Use consistent layout for each department
- Include photos for visual interest
- Add "Join Our Team" CTA in relevant sections
- Link to individual bio pages if available

---

## Styling Options

### Using WordPress Block Supports

**Spacing**
- **Margin**: Space around entire tabs block
- **Padding**: Space inside tabs container
- **Block Gap**: Control spacing between elements

**Colors**
- Use the dedicated Tab Colors panel (see [Color Settings](#color-settings))
- Overall background and text colors available in standard Color panel

**Typography**
- **Font Size**: Affects tab labels and content
- **Line Height**: Spacing between text lines
- **Font Family**: Custom fonts for tabs
- **Font Weight**: Bold, normal, light

**Border**
- **Border Radius**: Round corners of entire tabs block
- **Border Color/Width**: Outer border around tabs
- **Border Style**: Solid, dashed, dotted

**Alignment**
- Wide width: Extends to wider content area
- Full width: Extends to full page width

---

### Individual Tab Styling

Each Tab block (panel) can be styled independently:

- Padding inside content area
- Background color and image
- Text and link colors
- Typography settings
- Alignment (wide, full)


---

## Best Practices

### User Experience

**DO:**
- Keep tab count reasonable (3-7 tabs ideal)
- Use clear, concise tab labels (1-2 words)
- Put most important content in first tab
- Provide visual feedback for active tab
- Test on mobile devices
- Use icons sparingly (don't clutter)

**DON'T:**
- Hide critical content in later tabs
- Use overly technical labels
- Create too many tabs (consider navigation menu instead)
- Rely on color alone to indicate active state
- Forget to test keyboard navigation
- Use tabs for sequential content (use steps/wizard instead)

---

### Content Organization

**When to Use Tabs:**
- Related content that can be viewed independently
- Comparison scenarios (pricing, features)
- Space-constrained layouts
- Dashboard-style interfaces
- Product information organization

**When NOT to Use Tabs:**
- Sequential processes (use steps/wizard)
- Content that should be visible simultaneously
- Very short content (unnecessary hiding)
- Critical information users must see (keep visible)
- SEO-critical content (tabs may impact indexing)

---

### Performance

**Optimization Tips:**
- Tabs are lightweight with minimal JavaScript
- All tab content loads immediately (not lazy-loaded)
- Consider image optimization in tab content
- Avoid embedding multiple videos in tabs (bandwidth)
- Test page load time with content-heavy tabs

**SEO Considerations:**
- Search engines can index tab content
- All content is in page HTML (not AJAX loaded)
- Deep linking improves discoverability
- Use semantic headings inside tabs
- Don't hide critical keywords in tabs

---

## Accessibility

The Tabs block is built to WCAG 2.1 AA standards:

### Keyboard Navigation

**Tab Key:**
- Moves focus to tab navigation
- Cycles through tabs

**Arrow Keys:**
- **Left/Right** (horizontal): Navigate between tabs
- **Up/Down** (vertical): Navigate between tabs
- Automatically activates focused tab

**Home/End Keys:**
- **Home**: Jump to first tab
- **End**: Jump to last tab

**Enter/Space:**
- Activate focused tab (when not auto-activating)

---

### Screen Reader Support

**ARIA Attributes:**
- `role="tablist"` on navigation
- `role="tab"` on each tab button
- `role="tabpanel"` on each content panel
- `aria-selected` indicates active tab
- `aria-controls` links tab to panel
- `aria-labelledby` links panel to tab

**Announcements:**
- Screen readers announce tab count ("Tab 2 of 5")
- Active state announced
- Panel content is accessible
- Navigation structure is clear

---

### Visual Accessibility

**Contrast Requirements:**
- Text on tab buttons: 4.5:1 ratio minimum
- Active tab must be visually distinct
- Focus indicators clearly visible
- Hover states sufficient contrast

**Motion:**
- Respects `prefers-reduced-motion`
- No autoplay/auto-rotation
- User controls all interactions

---

### Accessibility Best Practices

**DO:**
- Test with keyboard only
- Verify screen reader announcements
- Ensure sufficient color contrast
- Provide clear focus indicators
- Use descriptive tab labels

**DON'T:**
- Rely on color alone for active state
- Use low contrast colors
- Hide focus indicators
- Use tiny text (minimum 16px recommended)
- Forget to test with assistive technology

---

## FAQ

**Q: How many tabs can I add?**
A: No technical limit, but 3-7 tabs is ideal for UX. More than 10 tabs should use vertical orientation or consider an alternative UI pattern.

**Q: Can I use icons on tabs?**
A: Yes! Each Tab block has an Icon Picker. Choose from a library of icons and position them left, right, or top of the tab title.

**Q: Do tabs work with page caching?**
A: Yes! Tabs work with all caching plugins. They use client-side JavaScript that runs after page load.

**Q: Can I link to a specific tab from another page?**
A: Yes! Enable "Deep Linking" in the Tabs block settings. Then link to `yoursite.com/page#tab-anchor`.

**Q: How do I change which tab is active by default?**
A: In the editor, simply click the tab you want to be active. The editor remembers and saves the active tab state.

**Q: Can I use tabs inside tabs (nested tabs)?**
A: Technically yes, but not recommended. Nested tabs create poor UX and confuse users.

**Q: Why do my tabs wrap to multiple rows on desktop?**
A: Too many tabs or long tab titles. Solutions:
  - Reduce tab count
  - Shorten tab titles
  - Use vertical orientation
  - Increase breakpoint for earlier mobile conversion

**Q: Can I style individual tabs differently?**
A: Tab navigation uses consistent styling (from parent Tabs block). However, each Tab's content panel can have unique colors, padding, and backgrounds.

**Q: Do tabs affect SEO?**
A: Tab content is in the HTML and indexable by search engines. However, Google may prioritize visible content. Use deep linking and ensure critical content is in first tab.

**Q: How do I remove a tab?**
A: Select the Tab block (click in its content area), then click the three dots in the toolbar and select "Remove Tab". You must have at least 1 tab.

**Q: Can I reorder tabs?**
A: Yes! Use the up/down arrows in the block toolbar, or drag and drop in the List View.

**Q: Why don't my tab colors show?**
A: Check that you're setting colors in the right place:
  - Tab navigation colors: Set on parent Tabs block
  - Content panel colors: Set on individual Tab blocks

---

## Additional Resources

**Related Blocks:**
- [Accordion Block](ACCORDION.md) - Alternative collapsible content

**General Documentation:**
- [Block Development Best Practices](../guides/BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md)
- [FSE Compatibility Guide](../../.claude/docs/FSE-COMPATIBILITY-GUIDE.md)
- [Accessibility Guide](../compliance/ACCESSIBILITY-COLOR-CONTRAST-GUIDE.md)

---

**Need Help?**
- [GitHub Issues](https://github.com/jnealey-godaddy/designsetgo/issues) - Report bugs
- [GitHub Discussions](https://github.com/jnealey-godaddy/designsetgo/discussions) - Ask questions

---

*Last Updated: 2025-11-19*
*DesignSetGo v1.0.0*
*WordPress 6.4+*
