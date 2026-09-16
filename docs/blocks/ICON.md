# Icon Block - User Guide

**Version**: 1.0.0
**Category**: Design
**Keywords**: icon, svg, symbol, graphic

## Overview

The **Icon Block** gives you access to a library of crisp, scalable vector icons. Unlike image icons, SVGs can be colored, sized, and styled with CSS, ensuring they look perfect on any screen resolution.

## 🚀 Quick Start: Creating an Icon List

1.  **Layout**: Insert a **Row** or **Stack** block.
2.  **Insert Icon**: Add the **Icon** block.
    *   Select a "Checkmark" icon.
    *   Set **Color** to Green.
    *   Set **Size** to `20px`.
3.  **Add Text**: Add a **Paragraph** block next to it.
4.  **Duplicate**: Select the Row and duplicate it to create a list.

## ⚙️ Settings & Configuration

### Icon Settings
- **Library**: 129+ built-in icons.
- **Style**: Filled (solid) or Outlined (stroke).
- **Stroke Width**: Adjustable for outlined icons.
- **Rotation**: 0°, 90°, 180°, 270°.

### Styling
- **Size**: 16px - 200px.
- **Color**: Text color controls fill/stroke.
- **Background**: Add background color for badge effect.
- **Border**: Add border radius for circular icons.

### Link Settings
- **URL**: Make icon clickable.
- **Open in New Tab**: Optional.

## 💡 Common Use Cases

### 1. Feature Lists
Checkmarks or bullets next to text.

### 2. Social Links
Facebook, Twitter, Instagram icons linking to profiles.

### 3. Navigation
Home or Search icons in menus.

### 4. Call-to-Action
Large arrow or download icons.

## ✅ Best Practices

**DO:**
- Use consistent icon styles (all filled or all outlined).
- Ensure sufficient contrast.
- Use "Decorative" toggle for purely visual icons.

**DON'T:**
- Use obscure icons without labels.
- Mix styles randomly.

## ♿ Accessibility

*   **Decorative Icons**: Toggle ON to hide from screen readers.
*   **Accessible Label**: Required for standalone/linked icons (e.g., "Search", "Facebook").

## 👨‍💻 Developer Notes

*   **Inline SVG**: Renders SVG code directly (not `<img>`), allowing CSS styling.
*   **Performance**: Extremely lightweight.
*   **Custom Icons**: Extendable via `designsetgo_icons` filter.
