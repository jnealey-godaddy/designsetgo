# Slider Block - User Guide

**Version**: 1.0.0
**Category**: Design
**Keywords**: slider, carousel, slideshow, gallery, hero

## Overview

The Slider block creates modern, performant sliders with multiple transition effects, auto-play functionality, and full WordPress block support inside each slide. Perfect for hero sections, image galleries, testimonials, product showcases, and any content that benefits from a slideshow format.

**Key Features:**
- Multiple Slides Per View - Display 1-6 slides at once on desktop with responsive breakpoints
- 3 Transition Effects - Slide, fade, and zoom animations with customizable timing
- Flexible Sizing - Fixed height or aspect ratio modes with responsive options
- Full Navigation Controls - Customizable arrows and dots with multiple styles and positions
- Auto-play Support - Smart auto-play with pause on hover/interaction and viewport detection
- Touch & Drag Support - Swipe on mobile, drag with mouse on desktop
- Infinite Loop - Seamless infinite scrolling with intelligent clone management
- Keyboard Accessible - Full keyboard navigation with screen reader support
- Any Block Inside - Use headings, paragraphs, images, buttons, forms, or any WordPress block
- Performance Optimized - Efficient rendering with reduced motion support

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [Settings & Configuration](#settings--configuration)
3. [Common Use Cases](#common-use-cases)
4. [Best Practices](#best-practices)
5. [Accessibility](#accessibility)
6. [Troubleshooting](#troubleshooting)
7. [Frequently Asked Questions](#frequently-asked-questions)

---

## Quick Start

### Basic Slider Setup

1. **Add the Slider Block**
   - In the block editor, click the `+` button
   - Search for "Slider" or find it in the "Design" category
   - The block will insert with 3 pre-configured example slides

2. **Customize Default Slides**
   - Click on any slide to select it
   - Edit the heading and paragraph content
   - Add background images, change colors, or modify layout
   - Each slide is a container that accepts any WordPress blocks

3. **Add More Slides**
   - Click the blue `+` button between slides to add a new slide
   - Or select the Slider block and use the toolbar to insert slides
   - Duplicate existing slides to maintain consistent styling

4. **Configure Slider Settings**
   - With the Slider block selected (not individual slides), open the Settings sidebar
   - Adjust layout, navigation, transitions, and behavior options
   - Changes apply immediately in the editor preview

5. **Test Your Slider**
   - Use the arrow buttons in the editor to preview navigation
   - Click dots to jump to specific slides
   - Save and view on the frontend to test all features

---

## Settings & Configuration

### Layout Settings

**Slides Per View (Desktop)**
- Range: 1-6 slides
- Default: 1 slide
- How many slides are visible at once on desktop screens
- Use 1 for full-width hero sliders
- Use 2-3 for content carousels
- Use 4-6 for product/image galleries

**Slides Per View (Tablet)**
- Range: 1-4 slides
- Default: 1 slide
- Number of visible slides on tablet devices (between mobile and desktop breakpoints)
- Typically set to half of desktop value

**Slides Per View (Mobile)**
- Range: 1-2 slides
- Default: 1 slide
- Number of visible slides on mobile devices
- Usually set to 1 for better mobile experience

**Important Note:** When using Fade or Zoom effects, slides per view is automatically set to 1 (these effects only work with single slides).

---

**Use Aspect Ratio**
- Toggle: On/Off
- Default: Off (uses fixed height)
- When enabled, slider height is determined by aspect ratio instead of fixed pixel height
- Better for responsive designs that need to maintain proportions

**Aspect Ratio** (when enabled)
- Options: 16:9, 4:3, 21:9, 1:1, 3:2
- Default: 16:9
- Common ratios:
  - **16:9** - Widescreen (videos, hero sections)
  - **4:3** - Classic (presentations, standard content)
  - **21:9** - Ultra-wide (cinematic hero sections)
  - **1:1** - Square (Instagram-style, product images)
  - **3:2** - Photography (DSLR standard)

**Height** (when aspect ratio disabled)
- Default: 500px
- Accepts: px, vh, rem units
- Fixed pixel height for the slider
- Use `vh` (viewport height) for full-screen sections
  - Example: `100vh` = full screen height
  - Example: `50vh` = half screen height

**Gap Between Slides**
- Default: 20px
- Accepts: px, rem units
- Space between slides when multiple slides are visible
- Set to 0 for seamless edge-to-edge slides
- Increase for card-style layouts

---

### Navigation Settings

**Show Arrows**
- Toggle: On/Off
- Default: On
- Displays previous/next navigation arrows
- Essential for desktop users without touch screens

**Arrow Style** (when arrows enabled)
- **Default** - Simple chevron arrows
- **Circle** - Arrows inside circular backgrounds
- **Square** - Arrows inside square backgrounds
- **Minimal** - Text-only arrows (‹ and ›)

**Arrow Position (Horizontal)**
- **Sides** - Positioned at left and right edges of slider
- **Inside** - Positioned inside the slider content area
- **Outside** - Positioned outside the slider viewport (requires extra margin)

**Arrow Position (Vertical)**
- **Top** - Arrows positioned at top of slider
- **Center** - Arrows vertically centered (most common)
- **Bottom** - Arrows positioned at bottom of slider

**Arrow Size**
- Default: 48px
- Adjusts the size of arrow buttons
- Larger sizes are easier to click/tap on mobile
- Smaller sizes are more subtle for minimal designs

**Arrow Padding**
- Default: Auto
- Inner spacing inside arrow buttons
- Increase to make arrow hit area larger
- Useful for touch-friendly designs

**Arrow Colors** (in Styles tab > Color section)
- **Arrow Icon Color** - Color of the arrow symbol
- **Arrow Background** - Background color of arrow button
- Supports WordPress color palette
- Use contrast for better visibility

---

**Show Dots**
- Toggle: On/Off
- Default: On
- Displays pagination dots below (or above) the slider
- One dot per slide for direct navigation

**Dot Style** (when dots enabled)
- **Default** - Circular dots
- **Lines** - Horizontal line indicators
- **Squares** - Square indicators

**Dot Position**
- **Bottom** - Dots below the slider (most common)
- **Top** - Dots above the slider

**Dot Color** (in Styles tab > Color section)
- Color of the pagination dots
- Active dot uses higher opacity
- Inactive dots use lower opacity

---

### Transition Settings

**Transition Effect**
- **Slide** - Slides move horizontally (supports multiple slides per view)
- **Fade** - Slides fade in/out (forces 1 slide per view)
- **Zoom** - Slides zoom in/out (forces 1 slide per view)

**Choosing Effects:**
- **Slide**: Best for galleries, product carousels, multi-slide layouts
- **Fade**: Best for hero sections, image backgrounds, smooth transitions
- **Zoom**: Best for dramatic hero sections, featured content

**Transition Duration**
- Default: 0.5s (500ms)
- Range: 0.1s - 2s
- How long the transition animation takes
- Faster (0.2-0.3s): Snappy, responsive
- Standard (0.5s): Balanced, professional
- Slower (0.8-1s): Dramatic, emphasis

**Transition Easing**
- **Ease** - Gradual start and end (most natural)
- **Ease In Out** - Slow start and end, faster middle
- **Ease In** - Slow start, fast end
- **Ease Out** - Fast start, slow end
- **Linear** - Constant speed (mechanical feel)

---

### Auto-play Settings

**Enable Auto-play**
- Toggle: On/Off
- Default: Off
- Automatically advances slides after a set interval
- Only activates when slider is visible in viewport (performance optimization)

**Auto-play Interval**
- Default: 3000ms (3 seconds)
- Range: 1000ms - 10000ms
- Time between automatic slide transitions
- Recommended: 3-5 seconds for readable content
- Longer intervals for content-heavy slides
- Shorter intervals for simple images

**Pause on Hover**
- Toggle: On/Off
- Default: On
- Pauses auto-play when user hovers over slider
- Improves user experience by preventing unwanted transitions
- Recommended to keep enabled

**Pause on Interaction**
- Toggle: On/Off
- Default: On
- Stops auto-play permanently after user clicks, swipes, or drags
- Prevents annoying users who manually navigate
- Recommended to keep enabled

---

### Behavior Settings

**Loop**
- Toggle: On/Off
- Default: On
- Enables infinite looping (from last slide back to first)
- When off, navigation stops at first/last slide
- Uses intelligent clone management for seamless transitions

**Swipeable (Touch)**
- Toggle: On/Off
- Default: On
- Enables touch swipe navigation on mobile/tablet
- Essential for mobile user experience

**Draggable (Mouse)**
- Toggle: On/Off
- Default: On
- Enables mouse drag navigation on desktop
- Provides intuitive interaction for desktop users

**Free Mode**
- Toggle: On/Off
- Default: Off
- Smooth scrolling without snap points
- Slider doesn't snap to slide positions
- Creates a "carousel" feel
- Best with multiple slides per view

**Centered Slides**
- Toggle: On/Off
- Default: Off
- Active slide is centered in the viewport
- Useful for highlighting the current slide
- Works well with 3+ slides per view

---

### Scroll-Driven Horizontal (Scroll Carousel)

The Slider block includes a **Scroll Carousel** variation that converts the slider into a scroll-driven horizontal experience. As the user scrolls down the page, slides advance horizontally — no arrows, dots, or manual interaction needed.

**Insert the Scroll Carousel:**
- In the block editor, click `+` and search for "Scroll Carousel"
- Or insert a standard Slider and enable "Scroll-Driven Horizontal" in Behavior Settings

**How It Works:**
1. The slider wraps itself in a "pin spacer" element
2. The slider becomes `position: sticky` and pins to the viewport center
3. Vertical scroll progress maps to horizontal `translateX` on the track
4. A progress bar indicator shows how far through the slides you've scrolled
5. When the spacer is fully scrolled past, the slider unpins

**Scroll Carousel Default Attributes:**
- Slides per view: 2.5 (desktop), 1.5 (tablet), 1 (mobile)
- Gap: 24px
- Style variation: Card
- Arrows: Hidden
- Dots: Hidden
- Autoplay: Off
- Loop: Off

**Scroll Speed**
- Range: 0.5 - 3
- Default: 1
- Controls how much vertical scrolling is needed to traverse all slides
- Higher values = more scroll distance required (slower horizontal movement)
- Lower values = less scrolling needed (faster horizontal movement)

**Automatically Disabled When Scroll-Driven:**
- Autoplay
- Loop
- Arrows
- Dots
- Swipe/Drag gestures

**Progress Bar:**
- A thin progress bar appears at the bottom of the slider
- Shows scroll completion percentage (0% to 100%)
- 120px wide on desktop, 80px on mobile
- Styled with subtle dark/light contrast

**Reduced Motion:**
- When `prefers-reduced-motion` is enabled, the scroll-driven position updates are skipped
- Slides display in their natural layout without horizontal scrolling

**Best Use Cases:**
- Feature showcases with card-style slides
- Portfolio pieces that reveal as users scroll
- Product feature tours
- Any content that benefits from a "scroll to explore" interaction

---

### Advanced Settings

**Mobile Breakpoint**
- Default: 768px
- Range: 320px - 900px
- Below this screen width, uses mobile slides per view setting
- Standard mobile breakpoint is 768px (iPad portrait and smaller)

**Tablet Breakpoint**
- Default: 1024px
- Range: 768px - 1280px
- Below this width (and above mobile breakpoint), uses tablet slides per view
- Standard tablet breakpoint is 1024px (iPad landscape and smaller)

**ARIA Label**
- Default: "Image slider"
- Accessible label for screen readers
- Customize to describe your slider's content
- Examples: "Product showcase", "Team member carousel", "Testimonial slider"

---

### Query-bound Mode

> **Added in 2.1.0**

When a Slider block is placed inside a `designsetgo/query` block it can act as the item host instead of `designsetgo/query-results`, iterating the query's items as slides. The Slider receives the query's `queryId`, `querySource`, and `queryPostType` via block context and renders one slide per item with full editor-to-frontend parity — the first item's inner blocks are editable on the canvas, while subsequent items render as server-rendered HTML previews.

**When to use:** Choose this over the default Query Results block when you want a paginated or carousel presentation of query items rather than a static grid.

See [QUERY.md](QUERY.md) for Dynamic Query configuration options.

---

## Common Use Cases

### 1. Hero Section Slider

**Goal**: Full-width hero section with rotating content

**Setup:**
1. Set height to `80vh` or use 16:9 aspect ratio
2. 1 slide per view on all devices
3. Use **Fade** or **Zoom** effect for smooth transitions
4. Enable auto-play (4-5 second interval)
5. Enable pause on hover and interaction
6. Add background images to each slide
7. Center content vertically and horizontally

**Tips:**
- Use high-quality, optimized background images
- Keep text concise and readable
- Add overlay to slides for better text contrast
- Consider 3-5 slides maximum to avoid overwhelming users

---

### 2. Product Carousel

**Goal**: Showcase multiple products with navigation

**Setup:**
1. Desktop: 4 slides per view
2. Tablet: 2 slides per view
3. Mobile: 1 slide per view
4. Use **Slide** effect
5. Gap: 20-30px for card spacing
6. Show arrows and dots
7. Enable swipe and drag
8. Loop enabled

**Tips:**
- Use consistent image sizes for all products
- Add pricing and "Add to Cart" buttons in each slide
- Consider auto-play with 5-6 second interval
- Use square or 3:2 aspect ratio for product images

---

### 3. Testimonial Slider

**Goal**: Rotating customer testimonials

**Setup:**
1. 1 slide per view (all devices)
2. Use **Fade** effect for subtle transitions
3. Height: Auto or 400-500px
4. Auto-play enabled (6-8 second interval)
5. Pause on interaction enabled
6. Centered content
7. Minimal arrow style or hide arrows

**Tips:**
- Include customer photo, quote, and attribution
- Use centered text alignment
- Add quotation marks or icons
- Limit to 5-8 testimonials for best impact

---

### 4. Image Gallery

**Goal**: Browsable image collection

**Setup:**
1. Desktop: 3-4 slides per view
2. Tablet: 2 slides per view
3. Mobile: 1 slide per view
4. Use 1:1 or 4:3 aspect ratio
5. Gap: 15-20px
6. Circle arrow style
7. Free mode enabled (optional for smooth scrolling)
8. Centered slides enabled

**Tips:**
- Optimize images for web
- Use consistent aspect ratios
- Consider linking slides to full-size images or modals
- Dots may not be needed with many images

---

### 5. Content Carousel

**Goal**: Featured blog posts or articles

**Setup:**
1. Desktop: 3 slides per view
2. Tablet: 2 slides per view
3. Mobile: 1 slide per view
4. Use **Slide** effect
5. Height: 400-500px
6. Show arrows and dots
7. No auto-play (let users control)
8. Loop enabled

**Tips:**
- Include featured image, title, excerpt, and CTA button
- Use card-style layout with background or border
- Consistent content length across slides
- Limit to 6-10 featured items

---

### 6. Scroll Carousel (Scroll-Driven)

**Goal**: Horizontal card carousel that advances as users scroll down

**Setup:**
1. Insert the "Scroll Carousel" variation (or enable scroll-driven on an existing slider)
2. Add 4-6 slides with card-style content
3. Set slides per view to 2.5 (desktop), 1.5 (tablet), 1 (mobile)
4. Use Card style variation
5. Gap: 20-30px
6. Scroll speed: 1 (default) or adjust to taste

**Content Per Slide:**
- Featured image with rounded corners
- Heading (H3)
- Short description paragraph
- Optional CTA button

**Tips:**
- Works best at full or wide width alignment
- Use consistent card heights for visual harmony
- Keep slides to 4-8 for comfortable scroll distance
- Test scroll speed on different screen sizes
- Consider the total page length — scroll carousel adds vertical height

---

### 7. Full-Screen Background Slider

**Goal**: Full viewport background slider

**Setup:**
1. 1 slide per view
2. Height: `100vh`
3. Use **Fade** effect
4. Auto-play enabled (5-6 seconds)
5. Hide dots, show minimal arrows
6. Large overlay opacity for text readability
7. Centered content alignment

**Tips:**
- Use high-resolution images (but optimize file size)
- Add dark overlay (50-80% opacity) for text contrast
- Keep slides to 3-5 maximum
- Ensure text is readable on all slides

---

## Best Practices

### Performance

**Optimize Images:**
- Compress images before uploading (use tools like TinyPNG, ShortPixel)
- Use appropriate image sizes (don't use 4K images for 500px slider)
- Consider lazy loading for sliders below the fold
- Use WebP format for better compression

**Reduce Motion:**
- The slider automatically respects `prefers-reduced-motion` preference
- Users who disable animations see instant transitions
- Auto-play is automatically disabled for reduced motion users

**Limit Slides:**
- Keep total slides under 20 for best performance
- More slides = larger DOM = slower performance
- Consider pagination or filters for large collections

---

### User Experience

**DO:**
- Make navigation obvious (visible arrows/dots)
- Use auto-play sparingly (can be annoying)
- Provide pause controls for auto-play
- Ensure content is readable on all devices
- Test swipe/drag interactions on touch devices
- Use consistent slide heights
- Provide alternative navigation (arrows AND dots)

**DON'T:**
- Use very fast auto-play intervals (< 3 seconds)
- Hide all navigation controls
- Use too many slides (overwhelming)
- Mix wildly different content types
- Forget to test on mobile devices
- Use very long transition durations (> 1 second)
- Auto-play sliders with critical information

---

### Content Guidelines

**Slide Content:**
- Keep text concise and scannable
- Use high-contrast text on backgrounds
- Include clear calls-to-action
- Maintain consistent visual hierarchy
- Test readability at all screen sizes

**Accessibility:**
- Add meaningful ARIA labels
- Ensure keyboard navigation works
- Test with screen readers
- Provide text alternatives for image-only slides
- Use sufficient color contrast

---

## Accessibility

The Slider block is built with accessibility as a priority:

### WCAG 2.1 AA Compliance

**Keyboard Navigation:**
- `Left Arrow` - Previous slide
- `Right Arrow` - Next slide
- `Home` - Jump to first slide
- `End` - Jump to last slide
- `Tab` - Navigate between controls (arrows, dots)

**Screen Reader Support:**
- `role="region"` with descriptive ARIA label
- Each slide has `role="group"` and `aria-roledescription="slide"`
- Live region announces slide changes ("Slide 2 of 5")
- Inactive slides have `aria-hidden="true"`
- Navigation buttons have descriptive labels

**Focus Management:**
- Slider is keyboard focusable
- Current slide is in tab order
- Inactive slides are removed from tab order
- Focus indicators are visible

**Reduced Motion:**
- Respects `prefers-reduced-motion` system preference
- Disables transitions for motion-sensitive users
- Disables auto-play when reduced motion is preferred

---

### Accessibility Best Practices

**When Creating Sliders:**

1. **Provide Descriptive ARIA Labels**
   - Customize ARIA label to describe slider purpose
   - Examples: "Customer testimonials", "Featured products", "Photo gallery"

2. **Ensure Keyboard Access**
   - Test all navigation with keyboard only
   - Verify arrow keys work
   - Ensure Tab key reaches all controls

3. **Test with Screen Reader**
   - Verify slide changes are announced
   - Check that slide count is communicated
   - Ensure navigation labels are clear

4. **Color Contrast**
   - Text must have 4.5:1 contrast ratio minimum
   - Navigation controls must be visible
   - Test with various color blindness simulations

5. **Auto-play Considerations**
   - Provide pause button for auto-play
   - Keep intervals long enough to read content (5+ seconds)
   - Consider disabling auto-play for content-heavy sliders

---

## Troubleshooting

### Slider Not Displaying Correctly

**Check:**
1. **Slides exist** - Slider needs at least one slide block inside
2. **JavaScript loaded** - Check browser console for errors
3. **Theme compatibility** - Some themes may have CSS conflicts
4. **Height setting** - Ensure height or aspect ratio is set appropriately
5. **Content overflow** - Check if slide content is too large for container

**Common Fixes:**
- Clear browser cache and reload
- Check for JavaScript console errors (F12)
- Temporarily disable other plugins to check for conflicts
- Increase slider height if content is cut off

---

### Navigation Not Working

**Check:**
1. **Arrows/Dots enabled** - Verify in Slider Settings
2. **Multiple slides** - Need at least 2 slides for navigation
3. **JavaScript errors** - Check browser console
4. **Theme conflicts** - Some themes override styles

**Common Fixes:**
- Ensure show arrows/dots is toggled on
- Add more slides (need minimum of 2)
- Check browser console for JavaScript errors
- Increase z-index if navigation is behind content

---

### Auto-play Issues

**Remember:**
- Auto-play only works when slider is visible in viewport
- Auto-play is disabled in the editor (test on frontend)
- Respects reduced motion preferences
- Stops after user interaction (if pause on interaction enabled)

**Debug Steps:**
1. Save and preview page (auto-play disabled in editor)
2. Scroll slider into view
3. Wait for interval to elapse
4. Check browser console for errors
5. Verify auto-play is enabled in settings

---

### Slides Jumping or Flickering

**Possible Causes:**
1. **Images loading** - Slider initializes before images load
2. **Height calculation** - Fixed height not set
3. **CSS conflicts** - Theme or plugin CSS interfering

**Solutions:**
- Set fixed height instead of auto
- Ensure images have width/height attributes
- Check for CSS conflicts in browser inspector
- Increase transition duration for smoother animations

---

### Mobile/Touch Issues

**Common Problems:**
1. **Swipe not working** - Ensure swipeable is enabled
2. **Arrows too small** - Increase arrow size for mobile
3. **Content cut off** - Adjust mobile slides per view
4. **Overlapping elements** - Check z-index and positioning

**Solutions:**
- Enable swipeable in behavior settings
- Increase arrow size to 40px+ for better touch targets
- Set mobile slides per view to 1
- Test on actual devices, not just browser resize

---

### Performance Problems

**If slider is slow:**
1. **Too many slides** - Reduce to 10-15 maximum
2. **Large images** - Compress and optimize
3. **Complex content** - Simplify slide content
4. **Multiple sliders** - Limit sliders per page

**Optimization:**
- Compress images (use TinyPNG, ShortPixel)
- Use appropriate image dimensions
- Limit slides to 15-20 maximum
- Disable auto-play if not needed
- Remove unnecessary block gap/padding

---

## Frequently Asked Questions

**Q: How many slides can I add?**
A: Technically unlimited, but keep under 20 for best performance. More slides means larger page size and slower rendering.

**Q: Can I use videos in slides?**
A: Yes! Add a Video block or Embed block (YouTube, Vimeo) inside any slide. Be aware that videos increase page load time.

**Q: Can I nest sliders (slider inside slider)?**
A: Not recommended. It creates poor UX and can cause conflicts. Use multiple separate sliders instead.

**Q: Why does fade/zoom only show 1 slide?**
A: Fade and zoom effects only work with single slides. These effects fade/zoom the entire slide, so multiple slides per view isn't possible. Switch to slide effect for multi-slide view.

**Q: How do I make slides the same height?**
A: Set a fixed height in Layout Settings (e.g., 500px) or use aspect ratio mode. This ensures all slides have consistent height.

**Q: Can I customize arrow/dot appearance?**
A: Yes! Use the Color settings to change colors. For advanced customization, use the Custom CSS Extension or Additional CSS in Customizer.

**Q: Does the slider work with page caching?**
A: Yes! The slider uses client-side JavaScript, so it works with all caching plugins.

**Q: Can I link entire slides?**
A: While slides themselves aren't clickable, you can add a full-width/height link or button inside the slide content to achieve this effect.

**Q: How do I disable navigation on mobile?**
A: You can hide arrows/dots with custom CSS for mobile devices. The slider will still work with swipe gestures.

**Q: What is the Scroll Carousel variation?**
A: It's a scroll-driven mode where vertical page scrolling drives horizontal slide navigation. The slider pins to the viewport and slides advance as you scroll. Insert it directly from the block inserter by searching "Scroll Carousel".

**Q: Can I adjust how fast the Scroll Carousel moves?**
A: Yes. Use the "Scroll Speed" range control (0.5-3) in Behavior Settings. Higher values require more scrolling to traverse all slides.

**Q: Why does the Scroll Carousel add extra page height?**
A: The scroll-driven mode creates a pin spacer whose height determines the scroll distance. This extra height is what maps to horizontal movement. Adjust scroll speed to control how much height is added.

**Q: Can I control slider with custom JavaScript?**
A: Yes! The slider dispatches custom events (`dsgo-slider-change`) that you can listen to. Access the slider instance via WeakMap if needed.

**Q: Why is my auto-play not working?**
A: Auto-play is disabled in the editor (by design). Save and preview on the frontend to test. Also check that the slider is visible in the viewport and auto-play is enabled in settings.

**Q: Can I use different transition effects for different slides?**
A: No, the transition effect applies to all slides in a slider. Create separate slider blocks if you need different effects.

**Q: How do I create a full-width slider?**
A: Select the Slider block and in the toolbar, choose "Full Width" alignment. Or use the Align option in the block settings.

---

## Additional Resources

**General Documentation:**
- [Block Development Best Practices](../BLOCK-DEVELOPMENT-BEST-PRACTICES-COMPREHENSIVE.md)
- [Accessibility Guide](../ACCESSIBILITY-COLOR-CONTRAST-GUIDE.md)
- [FSE Compatibility Guide](../FSE-COMPATIBILITY-GUIDE.md)

**Related Blocks:**
- [Slide Block](SLIDE.md) - Individual slide configuration

---

## Changelog

**Version 1.0.0** (2025-11-19)
- Initial release
- 3 transition effects (slide, fade, zoom)
- Responsive slides per view (desktop, tablet, mobile)
- Customizable navigation (arrows and dots)
- Auto-play with smart viewport detection
- Touch/drag support
- Infinite loop with clone management
- Keyboard navigation
- Full accessibility compliance (WCAG 2.1 AA)
- Reduced motion support
- Performance optimizations

---

**Need Help?**
- [GitHub Issues](https://github.com/jnealey-godaddy/designsetgo/issues) - Report bugs
- [GitHub Discussions](https://github.com/jnealey-godaddy/designsetgo/discussions) - Ask questions
- [Documentation](https://github.com/jnealey-godaddy/designsetgo/wiki) - Full wiki

---

*Last Updated: 2026-03-08*
*DesignSetGo v1.0.0*
*WordPress 6.4+*
