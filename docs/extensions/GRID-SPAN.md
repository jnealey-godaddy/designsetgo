# Grid Span Extension - User Guide

**Compatible With**: Blocks inside Grid containers only
**Since**: 1.0.0

## Overview

The **Grid Span Extension** allows blocks inside a Grid container to span multiple columns. Perfect for creating magazine layouts, featured content areas, and asymmetric grid designs.

**Key Features:**
- Span 1 to 12 columns (or parent grid max)
- Also supports Row Span (`grid-row: span N`) for spanning multiple rows
- Only appears when block is inside Grid
- Responsive (adjusts to parent grid's column settings)
- Visual preview in editor
- Works with all blocks

## 🚀 Quick Start

1. Create a **Grid** block with 3+ columns
2. Add blocks inside Grid (images, groups, headings, etc.)
3. Select a block you want to span multiple columns
4. Open **Grid Settings** panel in sidebar (only appears inside Grid)
5. Adjust **Column Span** slider (e.g., span 2 columns)
6. Block expands to fill multiple columns

## ⚙️ Settings & Configuration

### Grid Settings Panel
- **Column Span**: Number of columns block spans (1 to parent grid max)
- **Row Span**: Number of rows block spans (`grid-row: span N`)
- Slider range adjusts based on parent grid's column count
- Default: 1 (single column/row) for both controls
- If the parent Grid's **Align Rows** option is on, it overrides both Column Span and Row Span (each item fills one column in the shared row layout instead) — turn Align Rows off to use these controls

### Responsive Behavior
- **Desktop**: Uses full column span value
- **Tablet**: Constrained to parent's tablet column count
- **Mobile**: Constrained to parent's mobile column count

Example: If block spans 4 columns, but parent grid is 2 columns on tablet:
- Desktop: Spans 4 columns
- Tablet: Spans 2 columns (max available)
- Mobile: Spans 1 column (if parent grid is 1 column)

## 💡 Common Use Cases

### 1. Featured Content
Span 2 columns for a featured post/product in a 3-column grid.

### 2. Magazine Layouts
Mix 1-column and 2-column items for editorial designs.

### 3. Image Galleries
Span landscape images across 2 columns, portrait images stay 1 column.

### 4. Asymmetric Grids
Create visual interest by varying column spans.

## ✅ Best Practices

**DO:**
- Plan grid column count first (e.g., 12-column grid for flexibility)
- Use column spans that divide evenly (e.g., 2, 3, 4, 6 in 12-column grid)
- Test on tablet/mobile (column spans adjust)
- Mix spanning and non-spanning for visual variety
- Use in Magazine layouts, dashboards, portfolios

**DON'T:**
- Span more columns than parent grid has (no effect)
- Forget responsive behavior (test tablet/mobile)
- Use in non-Grid containers (control won't appear)
- Create unbalanced rows (e.g., 5 columns in 4-column grid wraps)
- Over-span (entire row = boring layout)

## 💡 Tips & Tricks

- **12-Column Grid**: Most flexible (divisible by 1, 2, 3, 4, 6, 12)
- **Visual Balance**: Span every 2nd or 3rd item for rhythm
- **Full Width**: Span all columns for full-width headers/footers
- **Debugging**: If control missing, check parent is `designsetgo/grid`
- **Responsive Testing**: Use browser dev tools to test breakpoints
- **CSS Grid**: Uses `grid-column: span N` (native CSS)

## Example Layouts

### Magazine Grid (4 columns)
- Item 1: Span 2 columns (featured)
- Item 2: Span 2 columns (featured)
- Items 3-6: Span 1 column each (fills row)

### Dashboard (3 columns)
- Header: Span 3 columns (full width)
- Sidebar: Span 1 column
- Main: Span 2 columns

### Portfolio (6 columns)
- Hero: Span 6 columns
- Projects: Mix of 2-column and 3-column spans

## Technical Notes

- Uses CSS `grid-column: span N`
- Only adds control when `useSelect` detects parent Grid block
- Responsive spans calculated via parent grid's column attributes
- Editor uses dynamic CSS injection for live preview
- Frontend uses inline styles for column span
