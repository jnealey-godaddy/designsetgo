# Section Block

**Category**: Design | **Keywords**: section, stack, vertical, layout, container

> **Updated in 2.1.0** — Section now automatically clears default padding when nested inside another Section.

## Overview

Create vertical stacking containers for organizing content areas. Perfect for page layouts, hero sections, and content sections. Maintains centered, constrained layouts with full-width backgrounds.

**Key Features:**
- Vertical flex layout (auto-converts to Row when horizontal)
- Width constraint system (enabled by default, typically 1140px)
- Overlay color support for image backgrounds
- Hover effects with context propagation to child blocks
- Semantic HTML elements (section, article, header, footer, main)

---

## Quick Start

1. **Add Block** → Search "Section" in Design category
2. **Add Content** → Insert blocks (headings, paragraphs, images, buttons)
3. **Configure Width** → Constrain Inner Width: ON (default, 1140px)
4. **Style** → Background, padding, overlay color

### Hero Section Example
- Full width alignment + background image
- Overlay Color: Dark semi-transparent
- Min Height: 100vh | Justification: Center
- Content: H1 + Paragraph + Buttons

---

## Settings

### Section Settings Panel
- **Constrain Inner Width**: ON (default) - Centers content with max-width
  - ON: Full-width background, centered content (1140px)
  - OFF: Content spans full container width
- **Max Content Width**: Custom width (960px, 1140px, 1200px, 1400px)

**When to Disable**:
- Full-width galleries/images
- Nested sections (parent already constrains)
- Intentional edge-to-edge designs

### Nested Sections and Padding

When a Section block is placed inside another Section block, DesignSetGo automatically clears the inner Section's default padding so spacing doesn't compound. Only the explicitly-set (non-default) padding values are preserved; if you want padding on the inner Section you can still set it manually and it will be respected. This prevents accidental double-spacing in layouts that use Sections for structural grouping.

---

### Layout Panel (WordPress Native)
**Orientation**: Vertical (changing to Horizontal auto-converts to Row block)

**Justification** (vertical distribution):
- Top: Items at top, space below
- Center: Centered, equal space top/bottom (hero sections)
- Bottom: Items at bottom, space above
- Space Between: Items spread, space distributed

---

### Color Panel
- **Background**: Color, gradient, or image
- **Text Color**: Cascades to child blocks
- **Overlay Color**: 80% opacity over background images (improves text readability)

**Hover Settings** (interactive sections):
- Hover Background/Text Color
- Hover Icon/Button Background (propagates via context to DesignSetGo blocks)

---

### Advanced Settings
**HTML Element**: div, section, article, aside, header, footer, main
- `<section>`: Page sections (most common)
- `<article>`: Blog posts, independent content
- `<header>`: Page header (once per page)
- `<footer>`: Page footer (once per page)
- `<main>`: Main content (once per page)

---

### WordPress Block Supports
- **Alignment**: Full (typical for sections)
- **Background Image**: Add with cover, center, no-repeat
- **Spacing**: Padding (XL top/bottom typical), margin, block gap
- **Dimensions**: Min Height (100vh for full-height, 500px for heroes)
- **Typography**: Font size, line height (cascades to children)
- **Border**: Color, style, width, radius
- **Shadow**: Box shadow for elevated sections

---

## Common Use Cases

### 1. Hero Section (Full-Height)
- Min Height: 100vh | Justification: Center
- Background: Image + overlay color (dark, 80%)
- Content: H1 + Paragraph + Buttons
- Constrain: ON (1140px)

### 2. Feature Section
- Background: Light color
- Padding: XXL top/bottom
- Content: H2 + Paragraph + Grid (3 columns)
- Constrain: ON (1140px)

### 3. Content Section (Blog/Article)
- HTML Element: `<article>` or `<section>`
- Constrain: ON (960px for readability)
- Padding: XL top/bottom
- Content: Headings, paragraphs, images

### 4. CTA Section
- Background: Brand color/gradient
- Text: White (high contrast)
- Justification: Center
- Content: H2 + Paragraph + Button
- Constrain: ON (800px)

### 5. Footer Section
- HTML Element: `<footer>`
- Background: Dark color
- Text/Link Color: Light
- Content: Grid (3-4 columns) + links
- Constrain: ON (1140px)

---

## Best Practices

### DO:
- Use full-width with constrained inner content
- Alternate background colors between sections
- Use adequate padding (XL or XXL)
- Set semantic HTML elements
- Use overlay colors for text over images
- Combine Section + Grid/Row for complex layouts

### DON'T:
- Use same background for all sections
- Nest sections unnecessarily
- Skip section headings
- Forget to test mobile
- Use images without overlay

---

## Layout Patterns

### Full-Page Structure
```
<header> → Header Section (logo, nav)
<main>
  <section> → Hero (background image, overlay)
  <section> → Features (light bg, grid)
  <section> → Content (white bg)
  <section> → CTA (brand color)
</main>
<footer> → Footer Section (dark bg)
```

### Section + Grid Pattern
```
Section (full-width, constrained)
  └─ Grid (3 columns)
       ├─ Feature 1
       ├─ Feature 2
       └─ Feature 3
```

### Width Constraint Visual
```
Full-width with constraint ON:
┌───────────────────────────┐
│ /// Full-width bg /////   │
│     ┌─────────────┐       │
│     │ Constrained │       │
│     │ 1140px max  │       │
│     └─────────────┘       │
└───────────────────────────┘
```

---

## Accessibility

**Semantic HTML**:
- Use `<section>` for distinct content areas
- Use `<header>`, `<footer>`, `<main>` once per page
- Use `<article>` for independent content

**Heading Hierarchy**: H1 → H2 → H3 (never skip levels)

**Color Contrast**: 4.5:1 minimum for text, use overlay for images

**Keyboard**: Logical tab order, visible focus states

---

## Troubleshooting

**Content not centering?**
- Verify Section alignment: Full Width
- Enable "Constrain Inner Width"
- Check theme overrides

**Background image not showing?**
- Set Background Size: Cover
- Add Min Height (e.g., 500px)

**Overlay not visible?**
- Set background image first
- Then set overlay color in Color panel

**Vertical justification not working?**
- Set Min Height for section (justification needs extra space)

---

## Tips & Tricks

**Full-Height Sections**: Min Height: 100vh + Justification: Center

**Alternating Backgrounds**: White → Light Gray → Brand Color → White

**Width Guidelines**:
- 960px: Text-heavy (blog posts)
- 1140px: General content
- 1200-1400px: Wide feature sections

**Combining Blocks**: Section → Grid (features) | Section → Row → Image + Text

---

*Version 1.0.0 | WordPress 6.4+ | [Report Issues](https://github.com/jnealey-godaddy/designsetgo/issues)*
