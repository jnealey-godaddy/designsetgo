# Image Accordion Block - User Guide

**Version**: 1.0.0
**Category**: Design
**Keywords**: image, accordion, gallery, portfolio, hover, expand

## Overview

The Image Accordion block creates an interactive expandable image gallery where panels expand on hover or click to reveal content. Perfect for portfolios, featured content showcases, before/after comparisons, or any visual presentation where you want to pack multiple images into a compact, engaging layout. Each panel can contain full WordPress block content including headings, text, buttons, and more.

**Key Features:**
- Expandable image panels with smooth transitions
- Hover or click/tap interaction modes
- Customizable expansion ratio and animation speed
- Optional color overlays with adjustable opacity
- Keyboard navigation support (Tab, Arrow keys, Enter, Space)
- Mobile-optimized with automatic touch detection
- Fully accessible with ARIA attributes
- Set default expanded panel

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [How It Works](#how-it-works)
3. [Settings & Configuration](#settings--configuration)
4. [Adding Content](#adding-content)
5. [Common Use Cases](#common-use-cases)
6. [Best Practices](#best-practices)
7. [Troubleshooting](#troubleshooting)
8. [Accessibility](#accessibility)

---

## Quick Start

### Basic Setup

1. **Add the Image Accordion Block**
   - Click the `+` button in the editor
   - Search for "Image Accordion" or find it in the "Design" category
   - Block inserts with 3 example items pre-configured

2. **Add Background Images to Panels**
   - Select each Image Accordion Item (child block)
   - In sidebar → Styles → Background → Background Image
   - Upload or select image from media library
   - Image fills the panel background

3. **Add Content to Each Panel**
   - Click inside each Image Accordion Item
   - Add headings, paragraphs, buttons, or any blocks
   - Content appears over the background image
   - Use the overlay feature for text readability

4. **Configure Interaction**
   - In Image Accordion settings (parent block)
   - Choose: Hover (desktop) or Click/Tap
   - Adjust expansion ratio and transition speed
   - Set default expanded panel (optional)

5. **Preview the Effect**
   - Save and preview on frontend
   - Hover or click panels to see expansion
   - Test on mobile for touch interaction

---

## How It Works

### Expansion Behavior

**Default State:**
- All panels are equal width
- Divided evenly across the accordion width
- Example: 3 panels = 33.33% each

**Expanded State:**
- One panel expands to take more space
- Other panels compress to accommodate
- Expansion ratio controls size difference
- Smooth CSS transition animates the change

**Example with 3 panels:**
- Default: 33% | 33% | 33%
- Panel 2 Expanded (3x ratio): 16% | 68% | 16%

### Interaction Modes

**Hover Mode (Desktop):**
- Mouse over panel to expand it
- Works automatically on non-touch devices
- Mouse leave resets to default expanded panel (or none)
- Smooth, fluid interaction

**Click/Tap Mode:**
- Click or tap to expand panel
- Stays expanded until another panel is clicked
- Better for touch devices
- More deliberate interaction

**Auto-Detection:**
- Touch devices automatically use click mode
- Even if hover mode is selected
- Ensures mobile usability

---

## Settings & Configuration

### Layout Panel

**Height**
- Default: 500px
- Units: px, vh, rem
- Fixed height for entire accordion
- All panels share this height

**Values:**
- Small: 300-400px (compact)
- Medium: 500-600px (balanced, default)
- Large: 700-900px (impactful)
- Full viewport: 100vh (hero section)

**Tips:**
- Use `vh` for viewport-relative sizing
- Test on mobile - reduce height if needed
- Ensure content fits within height

---

**Gap Between Items**
- Default: 4px
- Units: px, rem
- Space between panels
- Creates separation lines

**Values:**
- No gap: 0px (seamless)
- Tight: 2-4px (subtle, default)
- Medium: 8-12px (clear separation)
- Wide: 16px+ (bold lines)

**Visual Effect:**
- Tight gaps: Cohesive, single unit
- Wide gaps: Individual panels, more breathing room

---

### Expansion Behavior Panel

**Expanded Ratio**
- Default: 3
- Range: 2 to 5 (0.5 increments)
- How much larger the expanded panel becomes

**Ratio Explained:**
- 2x: Modest expansion (2x size of collapsed)
- 3x: Balanced expansion (default)
- 4x: Strong expansion
- 5x: Dramatic expansion

**Formula:**
- Expanded panel: Ratio × collapsed size
- Collapsed panels: Remaining space divided equally

**Example (3 panels, 3x ratio):**
- Collapsed width: 1 unit each
- Expanded width: 3 units
- Total: 1 + 3 + 1 = 5 units
- Percentages: 20% | 60% | 20%

---

**Transition Duration**
- Default: 0.5s (500ms)
- Units: s (seconds), ms (milliseconds)
- Speed of expansion/collapse animation

**Values:**
- Fast: 0.2-0.3s (snappy)
- Medium: 0.4-0.6s (balanced, default)
- Slow: 0.8-1.2s (dramatic)
- Very slow: 1.5s+ (overly slow)

**Help Text:** "Speed of expansion/collapse animation"

**Tips:**
- Faster feels responsive and modern
- Slower feels luxurious and deliberate
- Match to your brand personality
- Test with actual content

---

### Interaction Panel

**Trigger Type**
- Options: Hover (Desktop) | Click/Tap
- Default: Hover (Desktop)
- Controls how panels expand

**Hover (Desktop):**
- Mouse over to expand
- Auto-switches to click on mobile
- Fluid, immediate feedback
- Best for: Exploratory browsing, portfolios

**Click/Tap:**
- Click or tap to expand
- Stays expanded until another is clicked
- More deliberate interaction
- Best for: Mobile-first sites, intentional browsing

**Help Text:** "Hover is automatically replaced with click on mobile"

---

**Default Expanded Item**

> **Updated in 2.0.51** — The control is now a named-item picker that lists each item's heading text. Previously it was a numeric slider (0–10).

- Default: None (no panel expanded)
- Shows a dropdown of your actual item headings so you can identify panels by name
- Selecting a heading expands that panel on page load

**When to Use:**
- None: Neutral presentation, no favoritism
- Any item: Highlight a specific panel and guide attention to it on arrival

---

### Overlay Settings (Styles Tab)

**Enable Overlay**
- Toggle on/off
- Default: On (enabled)
- Adds color overlay to background images

**Why Use Overlays:**
- Improve text readability over images
- Create consistent visual style
- Reduce visual noise from backgrounds
- Darken/lighten images uniformly

---

**Overlay Color**
- Default: Black (#000000)
- Any color or hex code
- Applied over background images

**Common Colors:**
- Black (#000000): Classic, darkens images
- White (#ffffff): Lightens images (use with dark text)
- Navy (#1e3a5f): Sophisticated, professional
- Accent color: Brand-specific overlay

---

**Overlay Opacity (Default)**
- Default: 40%
- Range: 0-100%
- Opacity when panel is NOT expanded

**Values:**
- 0-20%: Very subtle
- 30-50%: Balanced (default 40%)
- 60-80%: Strong overlay
- 90-100%: Almost opaque

**Purpose:** Subdued panels when not expanded

---

**Overlay Opacity (Expanded)**
- Default: 20%
- Range: 0-100%
- Opacity when panel IS expanded

**Values:**
- 0%: No overlay when expanded (clear image)
- 10-30%: Subtle overlay (default 20%)
- 40%+: Still substantial overlay

**Purpose:** Lighter overlay reveals image when expanded

**Typical Pattern:**
- Default: 40-50% (collapsed panels subdued)
- Expanded: 10-20% (active panel image revealed)
- Creates focus on expanded panel

---

### Block Supports

**Width Alignment:**
- Default, Wide, Full width
- Recommended: Wide or Full for impact

**Margin:**
- Space around entire accordion
- Separate top/bottom controls

**Color:**
- **Text**: Default text color for all panels
- **Link**: Default link color
- Inherited by Image Accordion Items

**Typography:**
- **Font Size**: Default for all content
- **Line Height**: Default line height
- **Font Family**: Default font
- **Font Weight**: Default weight

---

## Adding Content

### Content Structure

Each Image Accordion Item is a container block:

**Recommended Pattern:**
```
Image Accordion Item
├─ Background Image (in Styles settings)
├─ Heading (H2) - Title
├─ Paragraph - Description
└─ Button - Optional CTA
```

**Alternative Pattern:**
```
Image Accordion Item
├─ Background Image
├─ Spacer (pushes content to bottom)
└─ Group (bottom-aligned)
   ├─ Heading
   └─ Paragraph
```

---

### Background Images

**Adding Images:**
1. Select Image Accordion Item
2. Sidebar → Styles → Background
3. Background Image → Upload or select
4. Image fills panel as background

**Image Optimization:**
- Use high-quality images
- Optimize file size (< 200KB recommended)
- Consistent dimensions across panels
- WebP format for smaller files

**Image Settings:**
- **Size**: Cover (fills panel), Contain (fits inside)
- **Position**: Center, Top, Bottom, Left, Right
- **Repeat**: No-repeat (recommended)

---

### Text Content

**Text Readability:**
- Use overlay color (black or dark) for light text
- Increase overlay opacity if text is hard to read
- Test on different screen sizes
- Ensure contrast ratio meets WCAG standards

**Content Guidelines:**
- Keep text concise (panel width is limited when collapsed)
- Use hierarchy: large heading, smaller description
- Add CTA buttons for action
- Test expanded vs. collapsed states

---

### Content Alignment

Each Image Accordion Item has alignment controls:

**Vertical Alignment:**
- Top, Center, Bottom
- Where content sits within panel height

**Horizontal Alignment:**
- Left, Center, Right
- Text alignment within panel

**Best Practices:**
- Bottom alignment: Content at bottom (common pattern)
- Center alignment: Balanced, symmetrical
- Left alignment: Traditional, readable

---

## Common Use Cases

### 1. Portfolio Showcase

**Goal**: Display creative work, projects, or case studies

**Setup:**
- Height: 600-800px
- Gap: 4px
- Expanded Ratio: 3-4x
- Trigger: Hover
- Default Expanded: 1 (first project)

**Content Per Panel:**
- Project screenshot as background
- Project title (H2)
- Brief description
- "View Project" button
- Overlay: 50% default, 15% expanded

**Tips:**
- Use 4-6 panels
- Maintain visual consistency
- Show your best work
- Link buttons to case studies

---

### 2. Travel Destinations

**Goal**: Showcase locations or experiences

**Setup:**
- Height: 500-700px
- Gap: 8px
- Expanded Ratio: 3x
- Trigger: Hover
- Default Expanded: 0 (none)

**Content Per Panel:**
- Destination photo as background
- Destination name (H2)
- Country/region
- Key highlights
- "Explore" button
- Overlay: 40% default, 20% expanded

**Visual Style:**
- Vibrant travel photos
- White text
- Dark overlay for contrast
- Consistent photo quality

---

### 3. Product Features

**Goal**: Highlight product benefits or features

**Setup:**
- Height: 400-500px
- Gap: 4px
- Expanded Ratio: 3x
- Trigger: Click/Tap
- Default Expanded: 1 (first feature)

**Content Per Panel:**
- Feature illustration or photo
- Feature name (H3)
- 1-2 sentence description
- Icon or badge (optional)
- Overlay: 30% default, 10% expanded

**Interaction:**
- Click to explore each feature
- Clear, scannable headlines
- Brief, benefit-focused copy

---

### 4. Team Directory

**Goal**: Introduce team members

**Setup:**
- Height: 600px
- Gap: 2px
- Expanded Ratio: 4x
- Trigger: Click/Tap
- Default Expanded: 0

**Content Per Panel:**
- Team member headshot
- Name (H3)
- Role/Title
- Short bio (paragraph)
- Social links (optional)
- Overlay: 50% default, 20% expanded

**Visual Style:**
- Professional headshots
- Consistent backgrounds
- Aligned bottom content
- Brand colors

---

### 5. Before/After Comparison

**Goal**: Show transformations or improvements

**Setup:**
- Height: 500px
- Gap: 0px (seamless)
- Expanded Ratio: 5x (dramatic)
- Trigger: Hover or Click
- Default Expanded: 1 (before)

**Content:**
- Panel 1: Before image + "Before" label
- Panel 2: After image + "After" label
- Minimal text, focus on images
- No overlay or very light (10-20%)

**Tips:**
- Only 2 panels
- Large expansion ratio
- Clear labels
- Matching image composition

---

## Best Practices

### Visual Design

**Image Selection:**
- Use high-quality, compelling images
- Maintain consistent aspect ratio
- Ensure images work both collapsed and expanded
- Test visibility of important details

**Color Overlays:**
- Use overlays for text readability
- Higher opacity on collapsed = focus on expanded
- Lower opacity on expanded = reveal image
- Test contrast ratios

**Content Layout:**
- Bottom-align content (common pattern)
- Use generous padding inside panels
- Keep collapsed state scannable
- Reveal more detail in expanded state

---

### Content Strategy

**Headlines:**
- Clear, descriptive titles
- Readable when collapsed (short)
- Intriguing enough to encourage expansion
- Consistent formatting across panels

**Descriptions:**
- Brief in collapsed state
- More detail when expanded (if desired)
- Front-load important information
- Use bullet points for scannability

**Calls to Action:**
- Add buttons for next steps
- Clear, action-oriented labels
- Ensure buttons are visible in both states
- Test click targets on mobile

---

### Interaction Design

**Hover vs. Click:**
- Hover: Fluid, exploratory, desktop-focused
- Click: Deliberate, touch-friendly, universal
- Consider your audience and device usage

**Default Expanded:**
- Use 0 for neutral presentation
- Use 1+ to highlight specific panel
- Don't use if all panels are equal priority

**Animation Speed:**
- Fast (0.3s): Modern, responsive
- Medium (0.5s): Balanced, default
- Slow (0.8s+): Luxurious, deliberate

---

### Performance

**Image Optimization:**
- Compress images before upload
- Use WebP format when possible
- Target < 200KB per image
- Test load times on mobile

**Panel Count:**
- Ideal: 3-6 panels
- Maximum: 8 panels
- More panels = narrower collapsed width

**Mobile Considerations:**
- Reduce height on mobile (use responsive controls)
- Test touch targets (min 44x44px)
- Ensure text is readable when collapsed
- Faster transitions on mobile (0.3-0.4s)

---

## Troubleshooting

### Panels Not Expanding

**Symptom**: Hover or click doesn't expand panels

**Possible Causes:**
1. Viewing in editor (effect only works on frontend)
2. JavaScript error
3. CSS conflict

**Solutions:**
- Preview on frontend
- Check browser console for errors
- Test with default WordPress theme
- Disable other plugins temporarily

---

### Text Hard to Read

**Symptom**: Content not visible over background images

**Solutions:**
1. Enable overlay color
2. Increase overlay opacity (60-80%)
3. Use darker overlay color
4. Add text shadow to content
5. Choose simpler background images

---

### Panels Too Narrow When Collapsed

**Symptom**: Content cut off or unreadable when collapsed

**Solutions:**
1. Reduce number of panels (4-5 max)
2. Decrease expansion ratio (2-3x)
3. Use shorter headlines
4. Remove content from collapsed state
5. Use icons instead of text

---

### Expanded Panel Not Obvious

**Symptom**: Can't tell which panel is expanded

**Solutions:**
1. Increase expansion ratio (4-5x)
2. Use stronger overlay opacity difference
3. Add border to expanded panel (custom CSS)
4. Increase transition duration for clarity

---

### Mobile Issues

**Common Problems:**

1. **Height too tall**
   - Reduce height to 300-400px on mobile
   - Use responsive settings

2. **Text too small**
   - Increase font size on mobile
   - Use larger headings

3. **Touch targets too small**
   - Entire panel is clickable
   - Ensure minimum 44px height

4. **Slow performance**
   - Optimize images
   - Reduce panel count
   - Test on real devices

---

## Accessibility

The Image Accordion is built with accessibility as a priority:

### Keyboard Navigation

**Tab Key:**
- Tab through panels in order
- Focus indicator visible
- Each panel is focusable

**Arrow Keys:**
- Right/Down: Next panel
- Left/Up: Previous panel
- Navigate between panels

**Action Keys:**
- Enter: Expand focused panel
- Space: Expand focused panel

**Home/End:**
- Home: Jump to first panel
- End: Jump to last panel

---

### Screen Reader Support

**ARIA Attributes:**
- `role="button"`: Panels are interactive
- `aria-label`: "Image panel 1", "Image panel 2", etc.
- `aria-expanded`: "true" or "false" based on state

**Announcements:**
- Panel focus announced
- Expanded state announced
- Content accessible in all states

---

### Touch Device Support

**Automatic Detection:**
- Detects touch capability
- Switches to click mode automatically
- Even if hover mode is selected

**Touch Optimization:**
- Entire panel is tap target
- No small buttons required
- Immediate visual feedback

---

### Color Contrast

**Best Practices:**
- Meet WCAG AA standards (4.5:1 for text)
- Use overlay for consistent contrast
- Test with contrast checking tools
- Don't rely on color alone for information

**Testing:**
- WebAIM Contrast Checker
- Browser DevTools accessibility panel
- Test with various overlay opacities

---

### Reduced Motion

**Not Currently Implemented:**
- Feature request for future versions
- Consider adding `prefers-reduced-motion` support
- Instant transitions for motion-sensitive users

---

## Advanced Tips

### Custom CSS Styling

Add visual enhancements with Custom CSS Extension:

```css
/* Add border to expanded panel */
.dsgo-image-accordion-item.is-expanded {
    outline: 3px solid #007cba;
    outline-offset: -3px;
}

/* Darken collapsed panels more */
.dsgo-image-accordion-item.is-collapsed::before {
    opacity: 0.7 !important;
}

/* Add scale effect on hover */
.dsgo-image-accordion-item:hover {
    transform: scale(1.02);
}
```

---

### Combining with Other Blocks

**Full-Width Section:**
```
Group (Full width, background color)
└─ Image Accordion (centered, max-width)
   ├─ Image Accordion Item 1
   ├─ Image Accordion Item 2
   └─ Image Accordion Item 3
```

**With Header:**
```
Stack
├─ Heading (Centered) - "Our Portfolio"
├─ Paragraph (Centered) - Description
└─ Image Accordion
   └─ Items...
```

---

## Frequently Asked Questions

**Q: How many panels should I use?**
A: 3-6 panels is ideal. More than 8 makes collapsed panels too narrow.

**Q: Can I use videos as backgrounds?**
A: Not directly. Use background images with video blocks inside panels instead.

**Q: Why doesn't hover work on my tablet?**
A: Touch devices automatically use click mode. Hover requires a mouse.

**Q: Can panels be different heights?**
A: No, all panels share the same height for the accordion effect.

**Q: How do I link panels to other pages?**
A: Add button blocks with links, or wrap content in link blocks.

**Q: Can I have panels expand to different sizes?**
A: No, expansion ratio is the same for all panels.

**Q: Does it work in sidebars or widgets?**
A: Yes, but wide/full width works best. Narrow containers limit effectiveness.

**Q: Can I nest accordions?**
A: Not recommended - creates confusing interactions.

**Q: Why is my text cut off when collapsed?**
A: Use shorter headlines or reduce number of panels for wider collapsed width.

**Q: Can I disable expansion on certain panels?**
A: Not built-in. All panels are interactive by design.

---

## Related Blocks

- **Scroll Accordion**: Stacking cards with scroll animation
- **Image Accordion Item**: Child block (panel)
- **Gallery**: Standard WordPress image gallery

---

**Need Help?**
- [GitHub Issues](https://github.com/jnealey-godaddy/designsetgo/issues) - Report bugs
- [GitHub Discussions](https://github.com/jnealey-godaddy/designsetgo/discussions) - Ask questions
- [Documentation](https://github.com/jnealey-godaddy/designsetgo/wiki) - Full wiki

---

*Last Updated: 2025-11-19*
*DesignSetGo v1.0.0*
*WordPress 6.4+*
