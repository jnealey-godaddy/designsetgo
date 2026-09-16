# Scroll Parallax Extension - User Guide

**Compatible With**: Container and visual blocks (Group, Cover, Image, Section, etc.)
**Since**: 1.3.0
**Status**: Production Ready

## Overview

The **Scroll Parallax Extension** adds Elementor-style vertical and horizontal scroll parallax effects to blocks. Elements move at different speeds relative to page scroll, creating depth and visual interest.

**Features:**
- Four directions: Up, Down, Left, Right
- Speed control (0-10 scale, max 200px movement)
- Viewport range for precise timing (start/end percentages)
- Effects relative to viewport or entire page
- Per-device toggles (Desktop, Tablet, Mobile)
- Performance-optimized with requestAnimationFrame
- Respects `prefers-reduced-motion` accessibility setting

## How to Use

1. Select a supported block (Group, Cover, Image, Section, etc.)
2. Open the block settings sidebar
3. Find the **Scroll Parallax** panel
4. Toggle **Enable Scroll Parallax Effect** to ON
5. Choose your direction and speed
6. Fine-tune viewport range and device settings as needed

## Settings

### Enable Scroll Parallax Effect
Toggle the parallax movement on or off.

### Direction
The direction the element moves while scrolling DOWN the page:
- **Up**: Element moves upward (creates depth, element appears further away)
- **Down**: Element moves downward (element appears closer)
- **Left**: Element moves left (horizontal parallax)
- **Right**: Element moves right (horizontal parallax)

### Speed
Controls how much the element moves relative to scroll (0-10 scale):
- **0**: No movement (disabled)
- **5**: Moderate parallax effect (default)
- **10**: Maximum movement (200px)

Higher speeds create more dramatic effects but may feel disorienting.

### Viewport Start (%)
Effect starts when the element reaches this viewport position:
- **0%**: Top of viewport (effect starts immediately when element enters)
- **50%**: Middle of viewport
- **100%**: Bottom of viewport

### Viewport End (%)
Effect ends when the element reaches this viewport position:
- **0%**: Top of viewport
- **50%**: Middle of viewport
- **100%**: Bottom of viewport (effect continues until element exits)

### Effects Relative To
Calculate scroll position relative to:
- **Viewport**: Effect based on element's position within the visible screen
- **Entire Page**: Effect based on total page scroll position

Use "Viewport" for localized effects on individual elements. Use "Entire Page" for background elements that should move consistently throughout scroll.

### Apply Effects On
Toggle parallax for specific device sizes:
- **Desktop**: Screens wider than 1024px (default: ON)
- **Tablet**: Screens 768px - 1024px (default: ON)
- **Mobile**: Screens narrower than 768px (default: OFF)

Mobile is disabled by default for better performance on resource-constrained devices.

## Supported Blocks

### Core WordPress Blocks
- Group
- Cover
- Image
- Media & Text
- Columns / Column

### DesignSetGo Blocks
- Section
- Row
- Grid
- Flip Card (and Front/Back)
- Icon
- Icon Button
- Image Accordion (and Items)
- Scroll Accordion (and Items)

## Use Cases

### 1. Hero Background Depth
Apply "Up" direction to a background image with speed 3-4 for subtle depth:
```
Background image moves slower than foreground content
```

### 2. Floating Elements
Apply "Down" direction to decorative elements so they float as user scrolls:
```
Icons or shapes that drift down the page
```

### 3. Horizontal Reveals
Apply "Left" or "Right" to elements that slide in from the sides:
```
Testimonial cards that slide into view
```

### 4. Layered Compositions
Combine multiple elements with different speeds for parallax layers:
```
Background: Speed 2 (slow)
Midground: Speed 5 (medium)
Foreground: Speed 0 (normal scroll)
```

## Best Practices

**DO:**
- Start with lower speeds (2-4) and increase gradually
- Use on visual elements, not text-heavy content
- Test on actual scroll to feel the effect
- Disable on mobile if performance suffers
- Use "Viewport" relative mode for most use cases
- Combine with other DesignSetGo extensions (animations, reveal)

**DON'T:**
- Apply to text blocks (reduces readability)
- Use high speeds (7+) on many elements simultaneously
- Ignore mobile performance - always test on real devices
- Mix too many directions on adjacent elements (disorienting)
- Forget to check accessibility settings

## Accessibility

The Scroll Parallax extension respects the `prefers-reduced-motion` media query:
- When enabled, parallax effects are completely disabled
- Elements remain in their normal position without movement
- All content remains visible and accessible

## Technical Notes

- Uses `requestAnimationFrame` for smooth 60fps animations
- `IntersectionObserver` for efficient visibility detection
- Passive scroll listeners to avoid blocking scroll
- CSS transforms (`translateX`/`translateY`) for GPU-accelerated movement
- Automatically cleans up observers when elements are removed
- Maximum movement capped at 200px for usability
- Initialization guards prevent duplicate processing

## Troubleshooting

### Effect not appearing
1. Ensure the block type is supported (see list above)
2. Verify "Enable Scroll Parallax Effect" is toggled ON
3. Check that Desktop (or your current device) toggle is ON
4. Scroll the page - effect only visible during scroll
5. Verify speed is greater than 0

### Movement feels wrong
1. Try different directions until it feels natural
2. Reduce speed if movement is too dramatic
3. Adjust viewport start/end for precise timing
4. Use "Viewport" relative mode for most cases

### Performance issues
1. Disable Mobile toggle if scrolling stutters on phones
2. Reduce number of parallax elements on the page
3. Lower speed values for smoother performance
4. Check for conflicting animations/effects

### Effect jumps or stutters
1. Ensure viewport start is less than viewport end
2. Check for CSS that might conflict (position: fixed, transform)
3. Test with other extensions disabled to isolate the issue
