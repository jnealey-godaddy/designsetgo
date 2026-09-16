# Row Block

**Category**: Design | **Keywords**: row, flex, flexbox, horizontal, layout

## Overview

Create flexible horizontal layouts with wrapping using CSS Flexbox. Perfect for button groups, side-by-side content, and responsive layouts that adapt across screen sizes.

**Key Features:**
- Flexbox horizontal layouts with wrapping
- Mobile stack option (auto-stack below 768px)
- WordPress native layout controls (justification, alignment, wrapping)
- Width constraint system for centered layouts
- Overlay color for image backgrounds
- Auto-converts to Section when vertical orientation selected

---

## Quick Start

1. **Add Block** → Search "Row" in Design category
2. **Add Content** → Insert blocks (buttons, images, headings, paragraphs)
3. **Configure Layout** → Justification (left/center/right/space-between)
4. **Enable Mobile Stack** → Turn ON for responsive stacking below 768px

### Button Group Example
- Add Row + 2-3 Buttons
- Justification: Left or Center
- Mobile Stack: ON
- Result: Side-by-side on desktop, stacked on mobile

---

## Settings

### Settings
- **Constrain Inner Width**: OFF (default) - Full width
  - ON: Centers content with max-width (1140px)
- **Max Content Width**: Custom width when constrained (960px, 1200px, etc.)
- **Stack on Mobile**: OFF (default)
  - ON: Items stack vertically below 768px (recommended for buttons, hero sections)

**Mobile Stack Visual**:
```
Desktop: [Item 1] [Item 2] [Item 3]
Mobile (OFF): [Item 1] [Item 2]... (scrolls)
Mobile (ON):  [Item 1]
              [Item 2]
              [Item 3]
```

---

### Layout Panel (WordPress Native)
**Orientation**: Horizontal (changing to Vertical auto-converts to Section block)

**Justification** (horizontal alignment):
- Left: Items left, space right
- Center: Items centered
- Right: Items right, space left
- Space Between: Items spread, space distributed
- Space Evenly: Equal space around all

**Vertical Alignment**:
- Top: Align to top
- Middle: Centered (common for image + text)
- Bottom: Align to bottom
- Stretch: Equal height items

**Wrap**:
- Wrap (default): Items wrap to new line when full
- Nowrap: Items stay on one line (may overflow)

---

### Color Panel
- **Background**: Color, gradient, or image
- **Text Color**: Cascades to child blocks
- **Overlay Color**: 80% opacity over background images

**Hover Settings**:
- Hover Background/Text Color
- Hover Icon/Button Background (propagates to DesignSetGo blocks)

---

### Advanced Settings
**HTML Element**: div, section, article, aside, header, footer, nav
- `<div>`: Default, general container
- `<nav>`: Navigation menus
- `<section>`: Distinct content area
- `<header>`: Header area

---

### WordPress Block Supports
- **Alignment**: Full, Wide, None
- **Background Image**: Cover, center, no-repeat
- **Spacing**: Padding, margin, block gap (space between items)
- **Dimensions**: Min Height (for full-height rows)
- **Typography**: Font size, line height
- **Border**: Color, style, width, radius

---

## Common Use Cases

### 1. Button Group
- 2-3 Buttons
- Justification: Left or Center
- Mobile Stack: ON
- Block Gap: Medium

### 2. Hero Section (Image + Text)
- Left: Image (50%)
- Right: H1 + Paragraph + Buttons (50%)
- Vertical Alignment: Middle
- Mobile Stack: ON

### 3. Feature Cards
- 3-4 items with icons/images + text
- Justification: Space Between or Space Evenly
- Mobile Stack: ON
- Wrap: ON

### 4. CTA Bar
- Text left, Button right
- Justification: Space Between
- Vertical Alignment: Middle
- Mobile Stack: ON

### 5. Stats/Counters
- 3-4 stat items
- Justification: Space Evenly
- Mobile Stack: ON
- Text Alignment: Center

---

## Best Practices

### DO:
- Enable Mobile Stack for multi-item rows
- Use Space Between for distributing items evenly
- Set vertical alignment to Middle for mixed content heights
- Use block gap for spacing (not margins)
- Combine with Section for full-width backgrounds

### DON'T:
- Forget Mobile Stack (causes horizontal scroll on mobile)
- Use for vertical stacking (use Section instead)
- Overcrowd rows (3-4 items max typically)
- Mix drastically different item heights without alignment
- Nest rows too deeply (keep structure simple)

---

## Layout Patterns

### Row + Buttons
```
Row (Mobile Stack ON)
  ├─ Button: Primary
  └─ Button: Secondary
```

### Row + Image/Text
```
Row (Mobile Stack ON, Vertical: Middle)
  ├─ Image (50%)
  └─ Heading + Paragraph (50%)
```

### Row + Cards (Wrapping)
```
Row (Wrap ON, Space Evenly)
  ├─ Card 1
  ├─ Card 2
  ├─ Card 3
  └─ Card 4 (wraps to new line if needed)
```

---

## Accessibility

**Semantic HTML**:
- Use `<nav>` for navigation rows
- Use `<section>` for content areas
- Use `<div>` for general containers

**Keyboard**: Logical tab order (left to right)

**Color Contrast**: 4.5:1 minimum

**Focus States**: Visible focus indicators

---

## Troubleshooting

**Items not centering horizontally?**
- Set Justification: Center
- Check item widths (may need to set manually)

**Items not aligning vertically?**
- Set Vertical Alignment: Middle
- Verify all items have content

**Mobile Stack not working?**
- Verify "Stack on Mobile" is ON
- Check viewport width <768px
- Look for CSS conflicts

**Items overflowing?**
- Enable Wrap in Layout panel
- Reduce item widths
- Consider Mobile Stack

---

## Tips & Tricks

**Equal-Width Items**: Set child blocks to equal basis (50%, 33.33%, 25%)

**Full-Height Rows**: Set Min Height: 100vh + Vertical Alignment: Middle

**Alternating Layouts**: Reverse item order between rows for visual interest

**Combining with Section**:
```
Section (full-width, background color)
  └─ Row (constrained, centered content)
       ├─ Item 1
       └─ Item 2
```

**Responsive Gaps**: Use WordPress spacing presets (sm/md/lg) for consistent gaps

---

*Version 1.0.0 | WordPress 6.4+ | [Report Issues](https://github.com/jnealey-godaddy/designsetgo/issues)*
