# Icon List Block - User Guide

**Version**: 1.0.0
**Category**: Text
**Keywords**: icon, list, features, benefits, services

## Overview

The Icon List block creates organized lists of items with icons, perfect for displaying features, benefits, services, or any structured content. Each list item can include an icon, title, description, and any WordPress blocks. The parent block manages consistent styling across all items.

**Key Features:**
- ✅ **Flexible Layouts** - Vertical, horizontal, or grid arrangements
- ✅ **Consistent Styling** - Centralized icon size, color, and spacing controls
- ✅ **129+ Built-in Icons** - Each item can have its own icon
- ✅ **Nested Blocks** - Add any WordPress blocks inside list items
- ✅ **Responsive Grid** - 1-4 column grid layout with automatic wrapping
- ✅ **Icon Positioning** - Left, right, or top of content
- ✅ **Customizable Gap** - Control spacing between items and within items
- ✅ **Context System** - Parent settings cascade to all child items

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [List Settings](#list-settings)
3. [Icon Colors](#icon-colors)
4. [Styling & Appearance](#styling--appearance)
5. [Layout Options](#layout-options)
6. [Common Use Cases](#common-use-cases)
7. [Best Practices](#best-practices)
8. [Tips & Tricks](#tips--tricks)
9. [FAQ](#faq)

---

## Quick Start

### Basic Icon List Setup

1. **Add the Icon List Block**
   - Click the `+` button in the editor
   - Search for "Icon List"
   - Or find it in the "Text" category
   - Block inserts with 3 default items

2. **Customize List Items**
   - Click on any list item to edit
   - Change the icon using the Icon Picker
   - Edit the title text
   - Add description or additional blocks
   - Add/remove items using `+` button or block toolbar

3. **Configure List Appearance**
   - Select the parent Icon List block (click block border)
   - Open "List Settings" panel
   - Choose layout (Vertical, Horizontal, or Grid)
   - Adjust icon size, gap, and position
   - Set icon colors

4. **Style the List**
   - Use color controls for list background, text, and borders
   - Add padding/margins for spacing
   - Apply typography settings for text styling

---

## List Settings

Parent-level controls that apply to all list items:

### List Settings

**Layout**
Choose how list items are arranged:

- **Vertical** (default)
  - Items stack top-to-bottom
  - Most common layout
  - Best for reading flow
  - Works for any number of items
  - Supports left, center, right alignment

- **Horizontal**
  - Items flow left-to-right
  - Best for short lists (2-5 items)
  - Creates horizontal navigation feel
  - May wrap on mobile
  - Supports left, center, right distribution

- **Grid**
  - Items in multi-column grid
  - Choose 1-4 columns
  - Automatically responsive
  - Best for 4+ items
  - Even distribution across columns

---

**Alignment** (Vertical and Horizontal layouts only)
Control item alignment:

**Vertical Layout:**
- **Left**: Items align to left edge
- **Center**: Items centered horizontally
- **Right**: Items align to right edge

**Horizontal Layout:**
- **Left**: Items packed to left
- **Center**: Items centered with space between
- **Right**: Items packed to right

**Note**: Grid layout doesn't use alignment (items fill columns).

---

**Columns** (Grid layout only)
- Range: 1-4 columns
- Default: 1
- Automatically responsive:
  - Desktop: Set number of columns
  - Tablet: May reduce columns
  - Mobile: Typically 1 column

**Choosing Column Count:**
- **2 columns**: Feature comparisons, balanced layouts
- **3 columns**: Services, features, team members
- **4 columns**: Large lists, icon showcases

---

**Icon Position**
Choose where icons appear relative to content:

- **Left** (default)
  - Icon to left of text
  - Natural reading flow (LTR languages)
  - Most common choice

- **Right**
  - Icon to right of text
  - Alternative layout
  - Good for RTL languages or design variety

- **Top**
  - Icon above text
  - Centers text below icon
  - Best for grid layouts
  - More vertical space usage

**Visual Impact:**
- Left/Right: Compact, text-focused
- Top: Icon-focused, more visual

---

**Icon Size**
- Range: 16-128px
- Default: 32px
- Applies to ALL list items
- Common sizes:
  - **16-24px**: Small, subtle icons
  - **32-48px**: Standard, balanced
  - **64-96px**: Large, feature-focused
  - **96-128px**: Hero sections, landing pages

**Tip**: Larger icons = more visual weight on icons vs text.

---

**Gap**
Space between list items:
- Default: 24px
- Units: px, em, rem
- Recommended:
  - **Compact**: 12-16px
  - **Standard**: 20-32px
  - **Spacious**: 40-60px

**Use `em` or `rem`** for responsive gap that scales with font size.

---

## Icon Colors

Centralized color controls for all list item icons:

### Icon Colors (Color Settings Group)

**Icon Color**
- Controls the icon fill/stroke color
- Applies to all list items
- Supports theme and custom colors
- Default: Inherits from text color
- Individual items can't override (maintains consistency)

**Icon Background Color**
- Adds background behind icons
- Creates badge/circle effect
- Applies padding automatically (8px)
- Border radius: 4px (subtle rounding)
- Makes icons stand out more
- Increases icon container size (icon size + 16px)

**When to Use Icon Backgrounds:**
- Feature lists (professional look)
- Services (differentiation)
- Steps or processes (numbered badges work well)
- Brand colors (background = brand color, icon = white)

**Example Combination:**
- Icon Color: White
- Icon Background Color: Theme primary color
- Result: Colored badge with white icon

---

## Styling & Appearance

### List-Level Styles

These styles apply to the entire list container:

**Background Color**
- Background behind entire list
- Use for list sections
- Creates visual grouping
- Pair with padding for spacing

**Text Color**
- Default text color for list items
- Individual items inherit this
- Useful for dark backgrounds

**Border**
- Adds border around entire list
- Separates from other content
- Combine with padding and background

**Padding**
- Internal space within list container
- Creates breathing room
- Recommended: 24-40px when using background

**Margin**
- External space around list
- Separates from adjacent blocks

---

### Typography

**Font Size**
- Controls default text size for items
- Items inherit this setting
- Headings within items can override

**Line Height**
- Affects readability
- Default: 1.5-1.6
- Taller line height = more readable

---

## Layout Options

### Vertical Layout

**Best For:**
- Feature lists
- Step-by-step processes
- Benefits and advantages
- FAQ items
- Service descriptions
- Long content per item

**Characteristics:**
- Natural reading flow
- Unlimited items
- Best for text-heavy content
- Mobile-friendly

**Design Tips:**
- Use consistent icon sizes
- Adequate gap (24-32px)
- Left icon position most common
- Center alignment for landing pages

---

### Horizontal Layout

**Best For:**
- Short feature highlights (3-5 items)
- Icon navigation
- Step indicators
- Compact benefit lists
- Key statistics

**Characteristics:**
- Side-by-side display
- Limited space per item
- Best for brief content
- May wrap on smaller screens

**Design Tips:**
- Keep items concise
- Equal content length per item
- Works well with icon top position
- Test mobile wrapping behavior

---

### Grid Layout

**Best For:**
- Large lists (6+ items)
- Feature showcases
- Service portfolios
- Product highlights
- Team members
- Icon collections

**Characteristics:**
- Multi-column display
- Even distribution
- Scalable for many items
- Automatically responsive

**Design Tips:**
- 2-3 columns most common
- Icon top position works well
- Keep item heights similar
- Test with varying content lengths

**Responsive Behavior:**
- 4 columns → 2 columns (tablet)
- All → 1 column (mobile)

---

## Common Use Cases

### 1. Feature List

**Goal**: Showcase product/service features

**Setup:**
1. Add Icon List block
2. Layout: Vertical or Grid (3 columns)
3. Icon Position: Left or Top
4. Icon Size: 32-48px
5. Icon Color: Theme accent color
6. Gap: 32px
7. For each item:
   - Choose relevant icon (check, star, rocket, etc.)
   - Heading: Feature name
   - Paragraph: Feature description

**Example Icons:**
- Check mark: Included features
- Star: Premium features
- Rocket: Performance features
- Shield: Security features
- Lightning: Speed features

---

### 2. Benefits Section

**Goal**: Highlight advantages or benefits

**Setup:**
1. Add Icon List block
2. Layout: Grid (2-3 columns)
3. Icon Position: Top (centers content)
4. Icon Size: 64px (larger for impact)
5. Icon Background Color: Light theme color
6. Icon Color: Theme primary
7. For each item:
   - Relevant benefit icon
   - Heading: Benefit title
   - Paragraph: Explanation

**Design Tip**: Use icon backgrounds for badge effect.

---

### 3. Service Offerings

**Goal**: List services or offerings

**Setup:**
1. Add Icon List block
2. Layout: Grid (3 columns)
3. Icon Position: Top
4. Icon Size: 48px
5. Icon Color: Consistent brand color
6. For each item:
   - Service-specific icon
   - Heading: Service name
   - Paragraph: Service description
   - Optional: Button or link

**Pro Tip**: Add Button block after description for "Learn More" CTAs.

---

### 4. Step-by-Step Process

**Goal**: Guide users through steps

**Setup:**
1. Add Icon List block
2. Layout: Vertical
3. Icon Position: Left
4. Icon Size: 40px
5. Use numbered icons (1, 2, 3, etc.) or sequence icons
6. For each item:
   - Number or step icon
   - Heading: Step title
   - Paragraph: Step instructions

**Alternative**: Use circle-check icons with different colors for each step.

---

### 5. Contact Information

**Goal**: Display contact methods

**Setup:**
1. Add Icon List block
2. Layout: Vertical
3. Icon Position: Left
4. Icon Size: 24px
5. For each item:
   - Icon: Phone, Mail, Location, Clock
   - Heading: Contact type
   - Paragraph or link: Contact details

**Example:**
- Phone icon → "(555) 123-4567"
- Mail icon → "contact@example.com"
- Location icon → "123 Main St, City, State"

**Tip**: Add links to phone/email using Link controls on Icon List Item.

---

### 6. Social Proof / Testimonials

**Goal**: Display key statistics or trust signals

**Setup:**
1. Add Icon List block
2. Layout: Horizontal or Grid (4 columns)
3. Icon Position: Top
4. Icon Size: 48px
5. For each item:
   - Relevant icon (star, users, trophy, check)
   - Large number or stat
   - Label below

**Example:**
- Star icon → "4.9/5" → "Rating"
- Users icon → "10,000+" → "Customers"
- Trophy icon → "50+" → "Awards"

---

### 7. Comparison Lists

**Goal**: Compare two options side-by-side

**Setup:**
1. Use 2 Icon List blocks (one per option)
2. Place in Columns block (2 columns)
3. Layout: Vertical (for each list)
4. Icon Position: Left
5. Different icon colors per list:
   - Option 1: Green check icons
   - Option 2: Blue star icons
6. Align content for easy comparison

---

### 8. FAQ or Accordion Preview

**Goal**: List frequently asked questions

**Setup:**
1. Add Icon List block
2. Layout: Vertical
3. Icon Position: Left
4. Icon Size: 24px
5. Icon: Question mark or help icons
6. For each item:
   - Heading: Question
   - Paragraph: Answer

**Alternative**: Use Accordion block for expandable answers.

---

## Best Practices

### Content Guidelines

✅ **DO:**
- Keep item lengths similar (especially in grid)
- Use consistent icon style across list
- Write concise headings (2-6 words)
- Provide enough detail in descriptions
- Use parallel structure in text
- Test with longest content (avoid overflow)

❌ **DON'T:**
- Mix icon styles (filled and outlined)
- Use too many different icons (confusing)
- Write long headings (wrapping issues)
- Vary content length drastically in grids
- Overcrowd with too many items
- Use icons that don't match content

---

### Icon Selection

**Consistency:**
- Use icons from same family (all outlined or all filled)
- Maintain visual weight across icons
- Use recognizable, universal symbols

**Relevance:**
- Icons should clearly represent content
- Avoid abstract icons for concrete concepts
- Test icon recognition with others

**Common Pairings:**
- Check → Features, benefits, completed items
- Star → Ratings, favorites, premium features
- Rocket → Launch, speed, growth
- Shield → Security, protection, trust
- Heart → Favorites, health, care
- Lightbulb → Ideas, tips, innovation
- Gear → Settings, customization, technical

---

### Layout Selection

**Choose Based on:**
- **Content length**: Long = Vertical, Short = Horizontal/Grid
- **Number of items**: Few = Vertical/Horizontal, Many = Grid
- **Visual priority**: Icons = Top position, Text = Left position
- **Screen size**: Mobile-first? Vertical often best

**Responsive Considerations:**
- Horizontal wraps to vertical on mobile
- Grid reduces columns on smaller screens
- Icon top position uses more vertical space

---

### Spacing Strategy

**Compact Lists** (12-16px gap):
- Dense information
- Secondary content
- Sidebar lists

**Standard Lists** (24-32px gap):
- Main content
- Balanced readability
- Most use cases

**Spacious Lists** (40-60px gap):
- Hero sections
- Landing pages
- Premium feel
- Large icons

---

### Accessibility

**Ensure Lists Are Accessible:**
1. Use semantic heading levels inside items
2. Maintain sufficient color contrast (icons and text)
3. Don't rely on color alone to convey meaning
4. Icons supplement text (text is primary)
5. Keyboard navigation works through items

**Icon Considerations:**
- Icons are decorative (text conveys meaning)
- No ARIA labels needed if text is descriptive
- Ensure icon color contrasts with background

---

## Tips & Tricks

### Numbered List with Icons

**Create Step Numbers:**
1. Use Icon List block
2. For each item, use number icons (1, 2, 3...)
3. Or use circle-1, circle-2, circle-3 icons
4. Icon background color adds badge effect

**Alternative**: Use circle icons with different colors per step.

---

### Icon List as Navigation

**Create Icon Menu:**
1. Icon List block, horizontal layout
2. For each item:
   - Add icon
   - Brief heading
   - Add Link URL in Icon List Item settings
3. Style with hover effects using custom CSS

**Use Cases:**
- Service menu
- Quick links
- Category navigation

---

### Alternating Icon Colors

**Create Visual Variety:**
1. Set default Icon Color in parent block
2. Individual items inherit this color
3. For variety, use different icon styles (filled vs outlined)
4. Or manually adjust using custom CSS per item

**Note**: Individual items can't override parent icon color (by design for consistency).

---

### Icon List in Columns

**Side-by-Side Lists:**
1. Add Columns block (2 columns)
2. Add Icon List block in each column
3. Different themes per list
4. Example: Features vs Benefits

**Tip**: Keep column lists similar lengths.

---

### Icon List with Background

**Create Card-Style List:**
1. Select Icon List block (parent)
2. Add Background Color
3. Add Padding (32-40px)
4. Add Border Radius (8px)
5. Optional: Add border or shadow

**Result**: List appears as distinct card section.

---

### Mixed Content in Items

**Beyond Title & Description:**
Each Icon List Item accepts any blocks:
- Headings (h2, h3, h4)
- Paragraphs
- Buttons
- Images
- Lists
- Quotes
- Separator lines

**Example Use:**
Feature item with icon, heading, description, and "Learn More" button.

---

### Icon List in Sidebar

**Compact Sidebar List:**
1. Smaller icon size (20-24px)
2. Reduced gap (12-16px)
3. Vertical layout
4. Concise text
5. Optional: Light background

**Use Cases:**
- Related links
- Quick facts
- Categories
- Recent items

---

## FAQ

**Q: Can I use different icon sizes for different items?**
A: No, icon size is controlled at the parent level for consistency. All items share the same icon size.

**Q: How do I add more items?**
A: Click the `+` button in the list, or use the block inserter after selecting the Icon List block.

**Q: Can individual items have different icon colors?**
A: No, icon color is set at parent level for visual consistency. All items share the same icon color.

**Q: How do I remove an item?**
A: Click on the item, then use the block toolbar or options menu to remove it.

**Q: What's the difference between gap and content gap?**
A: "Gap" (parent setting) is space BETWEEN list items. "Content Gap" (item setting) is space between icon and content, or between heading and description.

**Q: Can I reorder list items?**
A: Yes! Use the up/down arrows in the block toolbar, or drag-and-drop using the list view.

**Q: Do icons need to be the same across all items?**
A: No! Each item can have its own icon. Choose what makes sense for each item's content.

**Q: Can I link entire list items?**
A: Yes! Each Icon List Item has Link Settings. When linked, the entire item becomes clickable.

**Q: Why is my grid showing 1 column?**
A: On mobile, grids typically collapse to 1 column. Check on desktop or adjust responsive breakpoints.

**Q: Can I nest Icon Lists?**
A: Not recommended. Icon Lists expect Icon List Items as children. For nested content, add blocks WITHIN list items.

**Q: How do I create a horizontal list on desktop but vertical on mobile?**
A: Use Horizontal layout - it automatically wraps to vertical on small screens. Or use responsive CSS.

**Q: Can I use Icon List for navigation menus?**
A: Yes, but WordPress Navigation block is better suited for that. Icon List works for static icon-based menus.

**Q: How many items can I add?**
A: No hard limit, but for usability, keep lists focused:
- Vertical: 3-10 items
- Horizontal: 3-5 items
- Grid: 6-12 items

**Q: Can I export/import list configurations?**
A: Use WordPress block patterns to save and reuse Icon List configurations.

**Q: Why can't I change the icon position for just one item?**
A: Icon position is parent-level setting for layout consistency. All items follow the same icon position.

**Q: Can I add images instead of icons?**
A: No, this block uses SVG icons only. For images, use core List block with Image blocks inside.

**Q: Do Icon Lists support dark mode?**
A: If your theme supports dark mode and you use theme colors, Icon Lists will adapt automatically.

---

## Related Blocks

**Icon Block**: Standalone icons without list structure

**Icon Button Block**: Buttons with icons

**Icon List Item Block**: Individual items (used inside Icon List)

**WordPress List Block**: Core list block (bullets/numbers, no icons)

**WordPress Columns Block**: For side-by-side Icon Lists

---

## Browser Support

Icon List blocks work in all modern browsers:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Android)

Grid layout uses CSS Grid, supported in all modern browsers.

---

**Need Help?**
- [GitHub Issues](https://github.com/jnealey-godaddy/designsetgo/issues) - Report bugs
- [GitHub Discussions](https://github.com/jnealey-godaddy/designsetgo/discussions) - Ask questions
- [Documentation](https://github.com/jnealey-godaddy/designsetgo/wiki) - Full wiki

---

*Last Updated: 2025-11-19*
*DesignSetGo v1.0.0*
*WordPress 6.4+*
