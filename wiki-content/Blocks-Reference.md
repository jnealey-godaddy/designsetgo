# Blocks Reference

Complete reference guide for all DesignSetGo blocks organized by category.

---

## Layout Containers

### Flex Container
**Name**: `designsetgo/flex`
**Category**: Design
**Description**: Flexible horizontal or vertical layouts with wrapping. Perfect for button groups, hero sections, and responsive card layouts.

**Key Features**:
- Horizontal or vertical orientation
- Wrapping control
- Justify content & align items controls
- Mobile stacking option
- Content width constraints
- Hover state colors for children

**Use Cases**: Button groups, hero sections, card layouts, navigation menus

---

### Grid Container
**Name**: `designsetgo/grid`
**Category**: Design
**Description**: Advanced CSS Grid layouts with responsive columns and full control over spacing and alignment.

**Key Features**:
- Responsive column count (desktop, tablet, mobile)
- Auto-fit and auto-fill options
- Customizable gap spacing
- Full WordPress spacing/color/typography support
- FSE compatible

**Use Cases**: Image galleries, product grids, feature sections, blog post layouts

---

### Stack Container
**Name**: `designsetgo/stack`
**Category**: Design
**Description**: Vertical stacking layouts with controlled spacing between items. Simplified vertical layout management.

**Key Features**:
- Vertical-only orientation
- Consistent spacing control
- Alignment options
- Full WordPress block support inside

**Use Cases**: Vertical content sections, form layouts, stacked cards

---

## Interactive Content

### Accordion
**Name**: `designsetgo/accordion`
**Category**: Design
**Parent Block**: `designsetgo/accordion-item`
**Description**: Collapsible content panels with smooth animations. Great for FAQs, content organization, and space-saving designs.

**Key Features**:
- Expand/collapse animation
- Multiple items support
- Allow multiple open or single open mode
- Icon customization
- Full WordPress block support inside panels

**Use Cases**: FAQs, product specifications, content organization

---

### Tabs
**Name**: `designsetgo/tabs`
**Category**: Design
**Parent Block**: `designsetgo/tab`
**Description**: Tabbed content interface with icons and deep linking support. Perfect for organizing related content.

**Key Features**:
- Horizontal tab navigation
- Icon support for each tab
- Deep linking with URL hash
- Active tab styling
- Responsive design

**Use Cases**: Product features, service descriptions, portfolio categories

---

### Slider
**Name**: `designsetgo/slider`
**Category**: Design
**Parent Block**: `designsetgo/slide`
**Description**: Modern, performant slider with multiple transition effects, auto-play, and full block support inside slides.

**Key Features**:
- Multiple transition effects: Slide, Fade, Zoom
- Auto-play with pause on hover/interaction
- Customizable arrows (color, background, size, padding, position)
- Customizable dots (color, style, position)
- Loop mode
- Drag/swipe support
- Responsive slides per view
- Free mode and centered slides options
- Custom aspect ratio or fixed height

**Advanced Options**:
- Arrow styles: Default, Circle, Square, Minimal
- Arrow positions: Sides, Inside, Outside, Bottom
- Dot styles: Default, Line, Square
- Dot positions: Bottom, Top, Left, Right
- Breakpoint customization

**Use Cases**: Hero sliders, image galleries, testimonials, product showcases

---

### Flip Card
**Name**: `designsetgo/flip-card`
**Category**: Design
**Parent Blocks**: `designsetgo/flip-card-front`, `designsetgo/flip-card-back`
**Description**: Interactive card that flips to reveal content on the back. Perfect for team profiles, product showcases, and feature highlights.

**Key Features**:
- Flip trigger: Hover or Click
- Flip effects: Flip, Fade, Slide, Zoom
- Flip direction: Horizontal or Vertical
- Customizable flip duration
- Full block support on front and back

**Use Cases**: Team member profiles, product cards, feature highlights, before/after showcases

---

### Scroll Accordion
**Name**: `designsetgo/scroll-accordion`
**Category**: Design
**Parent Block**: `designsetgo/scroll-accordion-item`
**Description**: Accordion that reveals items progressively as you scroll down the page with sticky stacking effect.

**Key Features**:
- Scroll-triggered reveal
- Sticky positioning
- Stacking effect
- Progressive disclosure
- Full block support inside items

**Use Cases**: Storytelling, progressive content reveal, timeline displays

---

### Image Accordion
**Name**: `designsetgo/image-accordion`
**Category**: Design
**Parent Block**: `designsetgo/image-accordion-item`
**Description**: Display a series of expandable image panels that reveal content on hover or click, perfect for showcasing portfolios, galleries, or featured content.

**Key Features**:
- Hover or click trigger
- Customizable height and gap
- Expand ratio control
- Overlay with opacity controls
- Default expanded panel
- Smooth transitions
- Full block support inside panels

**Use Cases**: Portfolio showcases, image galleries, featured content displays, category navigation

---

## Content Elements

### Icon
**Name**: `designsetgo/icon`
**Category**: Design
**Description**: Display icons from a library of 500+ icons with optional shapes, animations, and hover effects.

**Key Features**:
- 500+ icon library
- Multiple icon shapes (circle, square, rounded)
- Size customization
- Color controls (icon, background, border)
- Hover effects
- Rotation and flip options
- Link support

**Use Cases**: Feature highlights, service icons, social media links, decorative elements

---

### Icon Button
**Name**: `designsetgo/icon-button`
**Category**: Design
**Description**: Icon-based buttons with multiple styles and hover effects.

**Key Features**:
- Combined icon + text button
- Multiple button styles
- Hover effects
- Link support
- Alignment controls

**Use Cases**: Call-to-action buttons, navigation elements, social sharing buttons

---

### Icon List
**Name**: `designsetgo/icon-list`
**Category**: Text
**Parent Block**: `designsetgo/icon-list-item`
**Description**: Lists with custom icons for each item instead of traditional bullets.

**Key Features**:
- Custom icon for each list item
- Icon size and color controls
- Spacing controls
- Full block support inside items

**Use Cases**: Feature lists, benefits, steps, checklists

---

### Pill
**Name**: `designsetgo/pill`
**Category**: Design
**Description**: Badge/tag components with customizable styles. Perfect for labels, categories, and status indicators.

**Key Features**:
- Customizable colors
- Border radius control
- Size options
- Text alignment
- Link support

**Use Cases**: Category tags, status badges, labels, pricing tags

---

### Counter
**Name**: `designsetgo/counter`
**Category**: Widgets
**Parent Block**: `designsetgo/counter-group` (optional)
**Description**: Animated counting numbers that count up when scrolled into view.

**Key Features**:
- Animated count-up effect
- Start and end values
- Duration control
- Prefix and suffix support
- Decimal places
- Separator customization
- Can be grouped with Counter Group

**Use Cases**: Statistics, metrics, achievements, milestones

---

### Progress Bar
**Name**: `designsetgo/progress-bar`
**Category**: Widgets
**Description**: Animated progress indicators with multiple styles.

**Key Features**:
- Horizontal bar style
- Animated on scroll into view
- Percentage display
- Customizable colors
- Label support
- Height control

**Use Cases**: Skills display, loading indicators, progress tracking, survey results

---

### Blobs
**Name**: `designsetgo/blobs`
**Category**: Design
**Description**: Create random, unique, and organic-looking blob shapes. Customize with gradients, overlays, and images. Animate with smooth morphing effects.

**Key Features**:
- 6 preset blob shapes
- 5 animation types: Morph 1, Morph 2, Float, Pulse, Spin
- Customizable size
- Gradient support
- Background image support
- Overlay with opacity control
- Animation duration and easing
- Full block support inside (text/content overlay)

**Use Cases**: Background decorations, hero sections, feature highlights, abstract designs

---

## Media Layouts

### Fifty Fifty
**Name**: `designsetgo/fifty-fifty`
**Category**: Design
**Description**: Full-width 50/50 split layout with edge-to-edge media on one side and constrained content on the other. Perfect for hero sections, feature highlights, and about sections.

**Key Features**:
- Edge-to-edge media with object-fit cover
- Media position toggle (left or right)
- Focal point picker for image cropping control
- Configurable minimum height (px, vh, vw, em, rem)
- Content vertical alignment (top, center, bottom)
- Content side auto-constrains to site content width
- InnerBlocks support for any content on the content side
- Toolbar flip layout button and media replace flow
- Mobile-responsive stacking (media always first)
- Lazy loading images with proper alt text support

**Block Supports**:
- Full-width alignment (always full)
- Anchor (custom HTML ID)
- Color (background, text, gradients, link)
- Spacing (margin, blockGap)
- Typography (fontSize, lineHeight)

**Attributes**:
- `mediaPosition` - Which side the media appears on (`left` or `right`, default: `left`)
- `mediaId` - WordPress media library attachment ID
- `mediaUrl` - URL of the media image
- `mediaAlt` - Alt text for accessibility
- `focalPoint` - Focal point coordinates (x, y from 0 to 1)
- `minHeight` - Minimum height of the block (CSS value, default: `500px`)
- `verticalAlignment` - Content vertical alignment (`top`, `center`, `bottom`)
- `contentPadding` - Internal padding for the content side

**Use Cases**: Hero sections, feature highlights, about sections, team introductions, product showcases, landing page sections

---

## Advanced Interactions

### Scroll Marquee
**Name**: `designsetgo/scroll-marquee`
**Category**: Design
**Description**: Display rows of images that scroll horizontally in alternating directions based on page scroll. For best performance, use optimized images (WebP format recommended) and limit to 20 images or less.

**Key Features**:
- Multiple rows with independent images
- Alternating scroll directions (left/right)
- Scroll speed control
- Customizable image dimensions
- Gap spacing between images
- Row gap spacing
- Border radius control
- Parallax scroll effect

**Performance Notes**:
- Recommended: WebP format images
- Limit: 20 images or less per row
- Optimize image sizes before use

**Use Cases**: Logo showcases, portfolio galleries, partner displays, image walls

---

## Form Builder

### Form Builder
**Name**: `designsetgo/form-builder`
**Category**: Widgets
**Description**: Create custom forms with multiple field types, AJAX submission, and spam protection.

**Key Features**:
- 12 different field types
- AJAX form submission
- Success/error message customization
- Spam protection (honeypot, rate limiting)
- Email notification support
- Field styling controls
- Submit button customization
- Custom field spacing and sizing

**Field Types Available**:
1. **Text Field** - Single-line text input
2. **Email Field** - Email validation
3. **Phone Field** - Phone number input
4. **URL Field** - URL validation
5. **Date Field** - Date picker
6. **Time Field** - Time picker
7. **Number Field** - Numeric input with min/max
8. **Checkbox Field** - Single checkbox
9. **Select Field** - Dropdown selection
10. **Textarea** - Multi-line text input
11. **File Upload** - File upload with type/size limits
12. **Hidden Field** - Hidden data storage

**Security Features**:
- Honeypot spam protection
- Rate limiting (configurable)
- Input sanitization
- Nonce verification

**Email Integration**:
- Custom recipient email
- Custom subject line
- From name and email
- Reply-to support
- Custom email body

**Use Cases**: Contact forms, lead generation, surveys, registration forms, feedback forms

---

## Block Relationships

### Parent-Child Blocks

Many DesignSetGo blocks use parent-child relationships for better organization:

1. **Accordion** → Accordion Item
2. **Tabs** → Tab
3. **Slider** → Slide
4. **Flip Card** → Flip Card Front + Flip Card Back
5. **Icon List** → Icon List Item
6. **Counter Group** → Counter
7. **Scroll Accordion** → Scroll Accordion Item
8. **Image Accordion** → Image Accordion Item
9. **Form Builder** → 12 Form Field Types

### Context Sharing

Parent blocks provide context to child blocks for:
- Consistent styling
- Shared settings
- Animation coordination
- Color inheritance

---

## Block Categories in WordPress

DesignSetGo blocks appear in both WordPress core categories AND a custom DesignSetGo collection:

**WordPress Core Categories**:
- **Design**: Most layout and interactive blocks
- **Text**: Icon List
- **Widgets**: Forms, Counters, Progress Bars

**Custom Collection**:
All blocks are also available in the "DesignSetGo" collection in the block inserter for easy discovery.

---

## Performance Characteristics

All blocks are optimized for performance:

- **Bundle Size**: < 10 KB per block (editor), < 5 KB (frontend)
- **CSS-Only Animations**: No JavaScript libraries for animations
- **Progressive Enhancement**: Core features work without JavaScript
- **Tree-Shaking**: Only loads what you use
- **No jQuery**: Pure vanilla JavaScript

---

## Accessibility Features

All blocks meet WCAG 2.1 AA standards:

- **Keyboard Navigation**: Full keyboard support
- **Screen Reader Friendly**: Proper ARIA labels
- **Focus Management**: Visible focus indicators
- **Reduced Motion**: Respects `prefers-reduced-motion`
- **Color Contrast**: Enforced minimum contrast ratios

---

## WordPress Integration

### Full Site Editing (FSE) Compatible

All blocks support:
- Theme.json integration
- WordPress color palettes
- WordPress spacing presets
- Typography presets
- Layout settings

### Block Supports

Most blocks include:
- Anchor (ID) support
- Alignment (wide, full)
- Spacing (margin, padding, blockGap)
- Color (background, text, gradients)
- Typography (font size, line height, etc.)
- Border (color, radius, style, width)

---

## Quick Reference Table

| Block | Category | Parent/Child | Animation | Link Support | FSE Ready |
|-------|----------|--------------|-----------|--------------|-----------|
| Flex Container | Design | - | ❌ | ❌ | ✅ |
| Grid | Design | - | ❌ | ❌ | ✅ |
| Stack | Design | - | ❌ | ❌ | ✅ |
| Accordion | Design | Parent | ✅ | ❌ | ✅ |
| Tabs | Design | Parent | ✅ | ✅ | ✅ |
| Slider | Design | Parent | ✅ | ❌ | ✅ |
| Flip Card | Design | Parent | ✅ | ❌ | ✅ |
| Reveal | Design | - | ✅ | ❌ | ✅ |
| Scroll Accordion | Design | Parent | ✅ | ❌ | ✅ |
| Image Accordion | Design | Parent | ✅ | ❌ | ✅ |
| Icon | Design | - | ✅ | ✅ | ✅ |
| Icon Button | Design | - | ✅ | ✅ | ✅ |
| Icon List | Text | Parent | ❌ | ❌ | ✅ |
| Pill | Design | - | ❌ | ✅ | ✅ |
| Counter | Widgets | Child* | ✅ | ❌ | ✅ |
| Progress Bar | Widgets | - | ✅ | ❌ | ✅ |
| Blobs | Design | - | ✅ | ❌ | ✅ |
| Scroll Marquee | Design | - | ✅ | ❌ | ✅ |
| Fifty Fifty | Design | - | ❌ | ❌ | ✅ |
| Form Builder | Widgets | Parent | ❌ | ❌ | ✅ |

*Counter can be used standalone or as a child of Counter Group

---

**Last Updated**: 2026-02-15
**Total Blocks**: 41
**WordPress Compatibility**: 6.7+
