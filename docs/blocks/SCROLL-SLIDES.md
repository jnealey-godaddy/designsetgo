# Scroll Slides Block - User Guide

**Version**: 1.0.0
**Category**: Design
**Keywords**: scroll, slides, fullscreen, pinned, showcase

## Overview

The Scroll Slides block creates a full-screen, scroll-pinned slideshow with crossfading content and side navigation. As users scroll, the block pins to the viewport and transitions between slides with smooth crossfade animations and background transitions. A vertical navigation panel allows direct jumping between slides.

**Key Features:**
- Full-screen scroll-pinned slideshow experience
- Crossfading content panels with background transitions
- Built-in side navigation with customizable headings
- Two starter templates (Blank and Showcase)
- Mobile-optimized tap navigation mode
- Reduced motion support and accessibility features
- Up to 10 slides per block

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [How It Works](#how-it-works)
3. [Settings & Configuration](#settings--configuration)
4. [Templates](#templates)
5. [Common Use Cases](#common-use-cases)
6. [Best Practices](#best-practices)
7. [Troubleshooting](#troubleshooting)
8. [Accessibility](#accessibility)

---

## Quick Start

### Basic Setup

1. **Add the Scroll Slides Block**
   - In the block editor, click the `+` button
   - Search for "Scroll Slides" or find it in the "Design" category
   - Choose a starter template: Blank (3 empty slides) or Showcase (3 pre-styled slides)

2. **Edit Navigation Headings**
   - The left column shows large heading inputs for each slide
   - Type a name for each slide (e.g., "Introduction", "Features", "Get Started")
   - These headings appear in the side navigation on the frontend

3. **Add Content to Slides**
   - Click on a slide panel in the right column to edit its content
   - Each slide accepts any WordPress blocks (headings, paragraphs, images, buttons, etc.)
   - Style individual slides with background colors, images, borders, and shadows

4. **Preview on Frontend**
   - Save and preview your page
   - Scroll down to see the pinned slideshow effect
   - Click navigation items to jump between slides

---

## How It Works

### Desktop: Scroll-Driven Mode

The block creates a spacer element whose height equals `slideCount * 100vh`. The slideshow container pins to the viewport as the user scrolls through the spacer.

**Scroll Behavior:**
1. The block pins to the viewport when it enters view
2. Scroll progress maps to the active slide index
3. Content panels crossfade between slides
4. Background layers transition with opacity
5. Navigation highlights the active slide
6. The block unpins when the spacer is fully scrolled past

**Scroll Engine Math:**
- `progress = clamp(0, -spacerRect.top / totalRange, 1)`
- `slideIndex = floor(progress * slideCount)`
- Nav clicks scroll to `(index + 0.5) / slideCount * totalRange` (midpoint targeting)

### Mobile: Tap Navigation Mode

On screens 781px and below, the block switches to tap navigation:
- No scroll pinning or spacer
- All slides are available via nav button taps
- `is-tap-mode` class replaces scroll behavior
- Same crossfade transitions, driven by clicks instead of scroll

### Reduced Motion

When `prefers-reduced-motion` is enabled, the entire scroll/tap engine is skipped. All slides display in their natural document flow without animation.

---

## Settings & Configuration

### Inspector Controls

#### Min Height
- **Control**: Unit Control
- **Default**: `100vh`
- Sets the minimum height of the slideshow container
- Common values: `100vh` (full viewport), `80vh`, `600px`

#### Max Height
- **Control**: Unit Control
- **Default**: `900px`
- Caps the container height on large screens
- Prevents slides from becoming excessively tall on ultrawide monitors

#### Constrain Width
- **Control**: Toggle
- **Default**: On
- When enabled, inner content respects the theme's content width
- When disabled, content fills the full container width

#### Content Width
- **Control**: Unit Control (visible when Constrain Width is on)
- **Default**: Theme content width
- Override the theme's default content width
- Falls back to `var(--wp--style--global--content-size, 1140px)` on the frontend

### Color Settings

#### Navigation Colors
- **Nav Color**: Default color of navigation text
- **Nav Active Color**: Color of the active navigation item

#### Overlay Color
- **Overlay Color**: Semi-transparent overlay on top of backgrounds (supports alpha)
- Applied via `::before` pseudo-element with CSS custom properties

### Block Supports

**Alignment:**
- Wide and Full width options

**Spacing:**
- Margin and Padding

**Color:**
- Background, Text, and Gradient

**Background:**
- Background Image, Size, Position, and Repeat

**Typography:**
- Font Size and Line Height

### Child Block: Scroll Slide

Each slide supports:
- **Nav Heading**: Text shown in the side navigation (Inspector panel)
- Background color, image, gradients
- Padding
- Border (color, radius, style, width)
- Shadow
- Typography

---

## Templates

When inserting a new Scroll Slides block, you can choose from two templates:

### Blank
- 3 empty slides ready for your content
- Clean starting point for custom designs

### Showcase
- 3 pre-styled slides with dark background colors
- Includes placeholder content (heading, paragraph, image)
- Great for seeing the effect in action before customizing

---

## Common Use Cases

### 1. Product Feature Showcase

**Goal**: Present product features in an immersive, full-screen experience

**Setup:**
1. Add Scroll Slides block (Full width)
2. Create 3-5 slides, one per feature
3. Add a compelling nav heading for each
4. Use background images or colors per slide
5. Keep content focused: heading + description + visual

**Content Structure:**
```
Scroll Slides
├─ Slide 1: "Design" (background image + overlay)
│  └─ Section > Heading + Paragraph + Image
├─ Slide 2: "Build" (dark background)
│  └─ Section > Heading + Paragraph + Button
└─ Slide 3: "Launch" (gradient background)
   └─ Section > Heading + Paragraph + Image
```

### 2. Portfolio / Case Studies

**Goal**: Walk visitors through projects with full-screen visuals

**Setup:**
1. Add Scroll Slides block (Full width)
2. One slide per project
3. Use background images with overlays for readability
4. Include project title, description, and CTA link
5. Customize nav headings with project names

### 3. Storytelling / Narrative

**Goal**: Guide users through a story or process

**Setup:**
1. Add Scroll Slides block
2. One slide per chapter or step
3. Use sequential nav headings ("Chapter 1", "Chapter 2", etc.)
4. Progressive color scheme across slides
5. Mix media types: text, images, videos

### 4. Landing Page Hero

**Goal**: Create an engaging above-the-fold experience

**Setup:**
1. Add Scroll Slides block as the first block on the page
2. Set to Full width alignment
3. Use high-quality background images
4. Keep content minimal and impactful
5. Add CTA buttons on key slides

---

## Best Practices

### Content Strategy

**DO:**
- Keep each slide focused on a single message
- Use 3-5 slides for optimal engagement
- Write concise, descriptive nav headings
- Use contrasting backgrounds between slides
- Test the full scroll experience end-to-end

**DON'T:**
- Overload slides with too much content
- Use more than 10 slides (hard limit)
- Skip nav headings (they serve as navigation labels)
- Forget to test on mobile (tap mode is different)
- Use very similar backgrounds on adjacent slides (the crossfade won't be noticeable)

### Visual Design

**Backgrounds:**
- Each slide's background is extracted to a separate layer for smooth crossfading
- Use distinct backgrounds to make transitions visible
- Background images with overlays work well for text readability
- Test background positioning on different screen sizes

**Navigation:**
- Nav headings should be short and descriptive (2-4 words)
- Customize nav colors to match your design
- Active nav color should clearly contrast with the default color

**Sizing:**
- `100vh` min-height creates a full-screen experience
- Use max-height to prevent excessive height on large monitors
- Content width constraint keeps text readable

### Performance

- Limit to 5-6 slides for best scroll performance
- Optimize background images before adding
- The scroll engine uses `requestAnimationFrame` for smooth 60fps
- `AbortController` handles cleanup automatically
- Frontend JS bails out entirely when reduced motion is preferred

---

## Troubleshooting

### Block Doesn't Pin to Viewport

**Symptom**: Slides scroll normally without pinning

**Possible Causes:**
1. Parent container has `overflow: hidden`
2. Reduced motion is enabled in OS settings
3. Viewport is below 781px (mobile tap mode, no pinning)

**Solutions:**
- Check parent containers for overflow restrictions
- Test with reduced motion disabled
- Resize browser above 781px to see desktop mode

### Navigation Not Appearing

**Symptom**: No side navigation on the frontend

**Possible Causes:**
1. Nav headings are empty on all slides
2. JavaScript error preventing DOM setup

**Solutions:**
- Add nav headings to slides via the Inspector panel or editor heading inputs
- Check browser console for JavaScript errors
- Verify `view.js` is loading (check Network tab)

### Slides Not Transitioning

**Symptom**: Only the first slide is visible

**Possible Causes:**
1. JavaScript error in scroll/tap engine
2. CSS specificity conflict hiding slides
3. View script not enqueued

**Solutions:**
- Check browser console for errors
- Inspect slide elements for unexpected `display: none` or `opacity: 0`
- Verify `build/blocks/scroll-slides/view.js` exists

### Mobile Tap Mode Not Working

**Symptom**: On mobile, slides don't change when tapping nav

**Possible Causes:**
1. JavaScript error in tap engine
2. Touch event conflicts with other scripts

**Solutions:**
- Check mobile browser console
- Test on a page without other interactive plugins
- Verify `is-tap-mode` class is added to the container

### Background Images Not Crossfading

**Symptom**: Backgrounds change abruptly instead of fading

**Possible Causes:**
1. Background styles not properly extracted from slides
2. CSS transitions overridden

**Solutions:**
- Ensure backgrounds are set on individual Scroll Slide blocks (not the parent)
- Check for theme CSS that might override transition properties

---

## Accessibility

### Keyboard Navigation

- All content is accessible via standard keyboard navigation
- Navigation buttons are focusable and operable via Enter/Space
- No keyboard traps within the slideshow
- Tab order follows logical content sequence

### Screen Readers

- An `aria-live` region announces slide changes
- Announcements follow the pattern: "Slide X of Y: [Nav Heading]"
- Navigation items use semantic `<button>` elements with `<nav>` wrapper
- Non-active slides use `inert` and `aria-hidden` to prevent confusion

### Reduced Motion

**Automatic Support:**
- Detects `prefers-reduced-motion: reduce` setting
- Completely disables the scroll/tap engine
- All slides render in normal document flow
- Full content remains accessible without any animation

### ARIA Attributes

- Active slides: `aria-hidden="false"`, no `inert`
- Inactive slides: `aria-hidden="true"`, `inert`
- Navigation: wrapped in `<nav>` element
- Live region: `aria-live="polite"` for non-intrusive announcements

### Mobile Accessibility

- Tap targets are appropriately sized
- Touch navigation is intuitive
- Content remains readable at all viewport sizes
- No horizontal scrolling required

---

## Advanced Tips

### Query-bound Mode

> **Added in 2.1.0**

When a Scroll Slides block is placed inside a `designsetgo/query` block it can act as the item host instead of `designsetgo/query-results`, iterating the query's items as scroll slides. The block receives the query's `queryId`, `querySource`, and `queryPostType` via block context and renders one slide per item with full editor-to-frontend parity — the first item's inner blocks are editable on the canvas, while subsequent items render as server-rendered HTML previews.

**When to use:** Choose this over the default Query Results block when you want an immersive scroll-pinned presentation of query items rather than a static grid.

See [QUERY.md](QUERY.md) for Dynamic Query configuration options.

### Re-initialization After AJAX

If loading Scroll Slides content via AJAX or dynamic page updates, dispatch a custom event to reinitialize:

```javascript
document.dispatchEvent(new CustomEvent('scroll-slides:reinit'));
```

### Combining with Other Blocks

**Stack Block Integration:**
```
Stack (Full Width, Dark Background)
└─ Scroll Slides (Full Width)
   ├─ Scroll Slide: "Overview"
   ├─ Scroll Slide: "Details"
   └─ Scroll Slide: "Action"
```

**Section Block Inside Slides:**
Each slide's default inner block template includes a Section block, which provides additional layout control for the slide's content.

### CSS Custom Properties

The block uses CSS custom properties you can reference or override:

```css
/* Overlay */
--dsgo-overlay-color: <color>;
--dsgo-overlay-opacity: <0-1>;

/* Navigation */
--dsgo-nav-color: <color>;
--dsgo-nav-active-color: <color>;
```

---

## Frequently Asked Questions

**Q: How many slides can I add?**
A: Up to 10 slides per Scroll Slides block.

**Q: Why don't I see the scroll effect in the editor?**
A: The scroll-pinned effect only works on the frontend. The editor shows a two-column layout with nav headings on the left and slide content on the right.

**Q: Can I change the crossfade speed?**
A: The transition speed is optimized for smooth performance and isn't configurable via the UI. You can override via CSS if needed.

**Q: Does it work on mobile?**
A: Yes. On screens 781px and below, it switches to a tap navigation mode instead of scroll-driven transitions.

**Q: What happens with reduced motion settings?**
A: The entire animation engine is disabled. Slides render in normal document flow without any scroll pinning or transitions.

**Q: Can I use this inside a Stack or Section block?**
A: Yes, it works well inside container blocks. Ensure parent containers don't have `overflow: hidden`.

**Q: How do I customize navigation colors?**
A: Use the Navigation color controls in the Inspector panel's Color group.

**Q: Can I add an overlay?**
A: Yes. Use the Overlay Color control in the Inspector panel to add a semi-transparent overlay (supports alpha).

**Q: Does it work with page caching?**
A: Yes. The effect is JavaScript-based and works with all caching plugins.

**Q: Can I nest Scroll Slides inside Scroll Slides?**
A: No. The child block (`scroll-slide`) only allows the parent `scroll-slides` block.

---

## Related Blocks

**Scroll Accordion**: Sticky stacking card effect for sequential content
**Image Accordion**: Expandable image panels with hover/click interactions
**Slider**: Standard slideshow with next/previous navigation
**Section**: Flexible content container (used inside slides)

---

**Need Help?**
- [GitHub Issues](https://github.com/jnealey-godaddy/designsetgo/issues) - Report bugs
- [GitHub Discussions](https://github.com/jnealey-godaddy/designsetgo/discussions) - Ask questions
- [Documentation](https://github.com/jnealey-godaddy/designsetgo/wiki) - Full wiki

---

*Last Updated: 2026-03-08*
*DesignSetGo v1.0.0*
*WordPress 6.4+*
