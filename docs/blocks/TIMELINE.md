# Timeline Block - User Guide

**Version**: 1.0.0
**Category**: Design
**Keywords**: timeline, history, events, chronology, roadmap, process, steps, milestones

## Overview

The **Timeline Block** displays chronological events, company history, or process steps in a visual timeline format with customizable layouts and scroll animations. It consists of a parent **Timeline** block and child **Timeline Item** blocks, using WordPress Block Context to pass settings from parent to child.

Commonly used for company histories, project roadmaps, event chronologies, and multi-step processes, the Timeline block supports both vertical and horizontal orientations with configurable connector lines, markers, and scroll-triggered animations.

## Quick Start: Building a Company Timeline

1.  **Add the Block**: Insert the **Timeline** block from the block inserter. It will appear with three default milestone items (2020, 2022, 2024).
2.  **Edit Content**:
    *   Click on a Timeline Item's date field and enter a date or label (e.g., "January 2024").
    *   Click on the title field and type a milestone name (e.g., "Company Founded").
    *   Add Paragraph blocks or any other content inside the item body.
3.  **Add More Items**:
    *   Select the parent Timeline block.
    *   Click the **+** (Appender) button to add a new Timeline Item.
4.  **Customize**:
    *   Select the parent Timeline block.
    *   In the Sidebar Settings, choose **Orientation** (vertical or horizontal).
    *   Set the **Content Layout** to "Alternating Sides" or "Right Side Only".
    *   Adjust marker shape, line style, and animation settings to match your design.

## Settings & Configuration

### Layout Settings

**Orientation**
- **`vertical`** (Default): Items stack top-to-bottom along a vertical line.
- **`horizontal`**: Items are arranged left-to-right along a horizontal line. Automatically switches to vertical on mobile devices.

**Content Layout** (Vertical orientation only)
- **`alternating`** (Default): Items alternate between left and right sides of the line.
- **`right`**: All items appear on the right side of the line.

**Item Spacing**
- Controls the gap between timeline items.
- **Default**: `2rem`
- Accepts `px`, `rem`, and `em` units.

### Line Settings

**Line Thickness**
- Width of the connector line in pixels.
- **Default**: `2`
- **Range**: 1 -- 8

**Connector Style**
- **`solid`** (Default): Continuous line.
- **`dashed`**: Evenly spaced dashes.
- **`dotted`**: Series of dots.

### Marker Settings

**Marker Shape**
- **`circle`** (Default): Round marker at each item.
- **`square`**: Square marker.
- **`diamond`**: Diamond (rotated square) marker.

**Marker Size**
- Size of the marker in pixels.
- **Default**: `16`
- **Range**: 8 -- 48

### Animation Settings

**Animate on Scroll**
- **Default**: On
- **When Enabled**: Items fade in as they scroll into the viewport.
- **When Disabled**: All items are visible immediately on page load.

**Animation Duration**
- Time in milliseconds for each item's fade-in animation.
- **Default**: `600`
- **Range**: 100 -- 2000 (step: 50)

**Stagger Delay**
- Delay in milliseconds between each consecutive item's animation start.
- **Default**: `100`
- **Range**: 0 -- 500 (step: 25)
- A value of `0` means all visible items animate simultaneously.

### Color Settings

Accessible under the **Color** inspector group:

- **Line Color**: Color of the connector line. Falls back to `--wp--preset--color--contrast` or `#e5e7eb`.
- **Marker Fill**: Fill color of the marker. Falls back to `--wp--preset--color--primary` or `#2563eb`.
- **Marker Border**: Border color of the marker. Falls back to the Marker Fill value if not set.

## Attributes Reference

### Timeline (`designsetgo/timeline`)

| Attribute | Type | Default | Description |
|---|---|---|---|
| `orientation` | `string` | `"vertical"` | Timeline direction. Enum: `vertical`, `horizontal`. |
| `layout` | `string` | `"alternating"` | Content placement in vertical mode. Enum: `alternating`, `right`. |
| `lineColor` | `string` | `""` | Color of the connector line. |
| `lineThickness` | `number` | `2` | Thickness of the connector line in pixels. |
| `connectorStyle` | `string` | `"solid"` | Line style. Enum: `solid`, `dashed`, `dotted`. |
| `markerStyle` | `string` | `"circle"` | Shape of the timeline markers. Enum: `circle`, `square`, `diamond`. |
| `markerSize` | `number` | `16` | Size of the markers in pixels. |
| `markerColor` | `string` | `""` | Fill color of the markers. |
| `markerBorderColor` | `string` | `""` | Border color of the markers. |
| `itemSpacing` | `string` | `"2rem"` | Spacing between timeline items. |
| `animateOnScroll` | `boolean` | `true` | Whether items animate into view on scroll. |
| `animationDuration` | `number` | `600` | Duration of the scroll animation in milliseconds. |
| `staggerDelay` | `number` | `100` | Delay between consecutive item animations in milliseconds. |

### Timeline Item (`designsetgo/timeline-item`)

| Attribute | Type | Default | Description |
|---|---|---|---|
| `date` | `string` | `""` | Date label displayed on the item. |
| `title` | `string` | `""` | Title text for the milestone or event. |
| `icon` | `string` | `""` | Optional icon identifier for the item. |
| `imageId` | `number` | `0` | Attachment ID of an optional image. |
| `imageUrl` | `string` | `""` | URL of the optional image. |
| `imageAlt` | `string` | `""` | Alt text for the optional image. |
| `isActive` | `boolean` | `false` | Whether the item is marked as active/current. |
| `linkUrl` | `string` | `""` | Optional link URL for the item. |
| `linkTarget` | `string` | `"_self"` | Link target. Use `_blank` for new tab. |
| `customMarkerColor` | `string` | `""` | Per-item override for the marker color. |
| `uniqueId` | `string` | `""` | Unique identifier for the item. |

## Block Supports

### Timeline

| Feature | Value |
|---|---|
| Anchor | Yes |
| Alignment | `wide`, `full` |
| HTML Editing | No |
| Spacing (margin) | Yes |
| Spacing (padding) | Yes (default control) |
| Spacing (blockGap) | Yes (default control) |
| Color (background) | Yes (default control) |
| Color (text) | Yes (default control) |
| Color (link) | Yes |
| Typography (fontSize) | Yes (default control) |
| Typography (lineHeight) | Yes (default control) |
| Typography (fontFamily) | Yes (experimental) |
| Typography (fontWeight) | Yes (experimental) |
| Border (color, radius, style, width) | Yes (all default controls) |

### Timeline Item

| Feature | Value |
|---|---|
| HTML Editing | No |
| Reusable | No |
| Spacing (padding) | Yes (default control) |
| Spacing (margin) | No |
| Color (background) | Yes (default control) |
| Color (text) | Yes (default control) |
| Color (link) | Yes |
| Typography (fontSize) | Yes (default control) |
| Typography (lineHeight) | Yes |
| Typography (fontFamily) | Yes (experimental) |
| Typography (fontWeight) | Yes (experimental) |
| Border (color, radius, style, width) | Yes (radius is default control) |

## Animation and Scroll Behavior

The Timeline block uses the Intersection Observer API (via `view.js`) to detect when items enter the viewport and trigger animations.

**How it works**:
1.  When `animateOnScroll` is enabled, the CSS class `dsgo-timeline--animate` is added to the container.
2.  Each Timeline Item starts hidden and transitions into view as the user scrolls.
3.  The `animationDuration` attribute controls how long each item's transition takes (exposed as the CSS custom property `--dsgo-timeline-animation-duration`).
4.  The `staggerDelay` attribute offsets each item's animation start so they appear sequentially rather than all at once.

**Responsive behavior**: Horizontal timelines automatically revert to vertical layout on mobile devices, ensuring readability on smaller screens.

**Editor behavior**: Animations are not played in the block editor. All items are fully visible during editing regardless of the `animateOnScroll` setting.

## Context Passing

The parent Timeline block provides the following values to child Timeline Item blocks via WordPress Block Context:

- `designsetgo/timeline/orientation`
- `designsetgo/timeline/layout`
- `designsetgo/timeline/lineColor`
- `designsetgo/timeline/markerStyle`
- `designsetgo/timeline/markerSize`
- `designsetgo/timeline/markerColor`
- `designsetgo/timeline/markerBorderColor`
- `designsetgo/timeline/animateOnScroll`

This means child items automatically inherit their parent's visual configuration without requiring duplicate settings.

## CSS Custom Properties

The block exposes the following CSS custom properties for advanced theme integration:

| Property | Maps To | Fallback |
|---|---|---|
| `--dsgo-timeline-line-color` | `lineColor` | `--wp--preset--color--contrast` or `#e5e7eb` |
| `--dsgo-timeline-line-thickness` | `lineThickness` | `2px` |
| `--dsgo-timeline-connector-style` | `connectorStyle` | `solid` |
| `--dsgo-timeline-marker-size` | `markerSize` | `16px` |
| `--dsgo-timeline-marker-color` | `markerColor` | `--wp--preset--color--primary` or `#2563eb` |
| `--dsgo-timeline-marker-border-color` | `markerBorderColor` | Marker color value |
| `--dsgo-timeline-item-spacing` | `itemSpacing` | `2rem` |
| `--dsgo-timeline-animation-duration` | `animationDuration` | `600ms` |

## Common Use Cases

### 1. Company History
Display founding milestones, growth events, and key achievements. Use the vertical alternating layout for visual balance.

### 2. Project Roadmap
Outline planned phases with dates. Mark the current phase using the `isActive` attribute on the relevant Timeline Item.

### 3. Event Schedule
Present a chronological list of events. The horizontal orientation works well for shorter timelines displayed in a wide container.

### 4. Process Steps
Break down a workflow into numbered steps. Use the "Right Side Only" layout for a clean, linear presentation.

## Best Practices

**DO:**
- Use clear, concise date labels and descriptive titles.
- Set `isActive` on the current milestone for visual emphasis.
- Test horizontal timelines on mobile to verify the vertical fallback.
- Use stagger delay to create a polished sequential reveal effect.

**DON'T:**
- Overload individual timeline items with too much content.
- Use very long animation durations that delay content visibility.
- Set stagger delay too high with many items (total delay compounds).
- Rely solely on color to distinguish active items (consider text labels for accessibility).

## FAQ

**Q: Can I use the horizontal layout on mobile?**
A: Horizontal timelines automatically switch to vertical on mobile devices for better readability.

**Q: Can I override marker colors on individual items?**
A: Yes. Each Timeline Item has a `customMarkerColor` attribute that overrides the parent's marker color.

**Q: Can I add images to timeline items?**
A: Yes. Each Timeline Item supports an optional image via the `imageId`, `imageUrl`, and `imageAlt` attributes.

**Q: Can I link a timeline item to another page?**
A: Yes. Use the `linkUrl` attribute on the Timeline Item. Set `linkTarget` to `_blank` to open in a new tab.

**Q: Do scroll animations work with page caching?**
A: Yes. Animations are driven entirely by client-side JavaScript and CSS, so they are fully compatible with caching.
