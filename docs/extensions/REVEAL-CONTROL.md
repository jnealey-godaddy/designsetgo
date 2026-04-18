# Reveal Control Extension - User Guide

**Compatible With**: Container blocks (Section, Row, Grid) and their children
**Since**: 1.0.0

## Overview

The **Reveal Control Extension** adds hover-triggered reveal effects to container blocks. Child blocks can be hidden until you hover over the parent container, perfect for interactive cards, portfolio items, and progressive disclosure.

**Key Features:**
- Container toggle: Enable reveal mode on parent
- Child toggle: Mark blocks to reveal on hover
- Two animation types: Fade or Collapse
- Uses CSS transitions (smooth, performant)
- Context-aware (child controls only appear inside reveal containers)

## 🚀 Quick Start

1. Create a **Section**, **Row**, or **Grid** block
2. Select the container, open **Reveal on Hover** panel
3. Toggle **Enable Reveal Mode**
4. Add child blocks (image, heading, button, etc.)
5. Select a child block, toggle **Reveal on Hover** in **Reveal Settings**
6. Hover over container on frontend to reveal hidden blocks

## ⚙️ Settings & Configuration

### Container Settings (Reveal on Hover Panel)
- **Enable Reveal Mode**: Activates reveal behavior for container
- **Collapse Animation**: Toggle between fade (opacity only) or collapse (height + fade)

### Child Settings (Reveal Settings Panel)
Only appears when block is inside a reveal-enabled container:
- **Reveal on Hover**: Hide this block until parent is hovered

### Animation Types
- **Fade**: Smooth opacity transition (default)
- **Collapse**: Collapses from height 0 + fade (more dramatic)

### Supported Containers
- **designsetgo/section**: Vertical stack
- **designsetgo/row**: Horizontal flex
- **designsetgo/grid**: Grid layout

## 💡 Common Use Cases

### 1. Portfolio Cards
Hide project details (description, tags, CTA) until hover.

### 2. Team Member Cards
Show name/photo by default, reveal bio/social links on hover.

### 3. Product Cards
Hide price and "Add to Cart" until user hovers (reduces visual clutter).

### 4. Image Overlays
Reveal text overlays on image hover for cleaner gallery views.

## ✅ Best Practices

**DO:**
- Use for supplementary content (not primary info)
- Provide visual hover cues (cursor pointer, scale, etc.)
- Test on touch devices (no hover state)
- Keep revealed content concise
- Use fade for subtle reveals, collapse for dramatic

**DON'T:**
- Hide critical information (mobile has no hover)
- Reveal navigation/CTAs only on hover
- Use on mobile-only sections (no hover on touch)
- Nest reveal containers (causes confusion)
- Hide accessibility-critical content

## 💡 Tips & Tricks

- **Mobile Fallback**: Revealed content stays visible on touch devices (no hover)
- **Combine with Clickable Group**: Reveal details on hover, click card to navigate
- **Stacking**: Reveal multiple elements at once (all marked for reveal)
- **Selective Reveal**: Mix revealed and always-visible children
- **CSS Transitions**: Smooth animations via CSS (no JavaScript)
- **Touch Devices**: First tap shows reveal, second tap triggers link (if clickable)

## Example Setup

### Product Card
**Container (Section)**:
- Enable Reveal Mode: ✓
- Animation: Fade

**Always Visible**:
- Product Image
- Product Title

**Reveal on Hover**:
- Price
- Description
- "Add to Cart" Button

Result: Clean card by default, full details on hover.

## Technical Notes

- Uses context API (`providesContext` and `usesContext`)
- CSS classes: `.dsgo-has-reveal` (container), `.dsgo-reveal-item` (children)
- Data attribute: `data-reveal-animation` (fade or collapse)
- Transitions controlled via CSS (performant, no JS)
- Children inherit reveal state from closest parent container
