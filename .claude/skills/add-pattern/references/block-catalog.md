# Block Catalog

All blocks available for use in DesignSetGo patterns. Use `designsetgo/{name}` for custom blocks, `core/{name}` for WordPress core blocks.

## Layout Containers

These are the primary structural blocks. Every pattern starts with one.

### designsetgo/section

Full-width outer container with constrained inner content. **Use as the outermost block in every pattern.**

```text
Key attributes:
  constrainWidth (boolean, default true)
  contentWidth (string)
  tagName (string: "div"|"section"|"article"|"aside"|"header"|"footer"|"main"|"nav")

Supports: backgroundColor, textColor, gradient (style.color.gradient), spacing (padding, margin), border

HTML structure:
  <div class="wp-block-designsetgo-section alignfull dsgo-stack {bg-classes}" style="{padding}">
    <div class="dsgo-stack__inner" style="max-width:var(--wp--style--global--content-size, 1140px);margin-left:auto;margin-right:auto">
      {inner blocks}
    </div>
  </div>
```

### designsetgo/grid

Responsive multi-column grid. Default: 3 desktop, 2 tablet, 1 mobile.

```text
Key attributes:
  desktopColumns (number, default 3)
  tabletColumns (number, default 2)
  mobileColumns (number, default 1)
  alignItems (string: "stretch"|"center"|"start"|"end")

Supports: spacing (blockGap, padding), border

HTML structure:
  <div class="wp-block-designsetgo-grid alignfull dsgo-grid dsgo-grid-cols-{n} ...">
    <div class="dsgo-grid__inner" style="display:grid;grid-template-columns:repeat({n}, 1fr);...">
      {inner blocks}
    </div>
  </div>
```

### designsetgo/row

Horizontal flex container for inline layouts (button groups, icon+text).

```text
Key attributes:
  constrainWidth (boolean)
  mobileStack (boolean)
  layout.type: "flex"
  layout.flexWrap: "wrap"|"nowrap"
  layout.justifyContent: "left"|"center"|"right"|"space-between"

HTML structure:
  <div class="wp-block-designsetgo-row alignfull dsgo-flex dsgo-no-width-constraint">
    <div class="dsgo-flex__inner" style="display:flex;justify-content:{align};flex-wrap:{wrap};gap:{gap}">
      {inner blocks}
    </div>
  </div>
```

### designsetgo/fifty-fifty

Full-width 50/50 split layout with edge-to-edge media on one side and constrained content on the other. Perfect for hero sections, feature highlights, and about sections.

```text
Key attributes:
  mediaPosition ("left"|"right", default "left")
  mediaId (number), mediaUrl (string), mediaAlt (string)
  focalPoint (object: { x: 0-1, y: 0-1 })
  minHeight (string, default "500px")
  verticalAlignment ("top"|"center"|"bottom", default "center")
  contentPadding (string, default "var(--wp--preset--spacing--50)")

Supports: backgroundColor, textColor, gradient, typography (fontSize, lineHeight), spacing (margin, blockGap)

InnerBlocks: Yes — content side accepts any blocks (headings, paragraphs, buttons, etc.)

HTML structure:
  <div class="wp-block-designsetgo-fifty-fifty alignfull dsgo-fifty-fifty dsgo-fifty-fifty--media-{position}" style="--dsgo-fifty-fifty-min-height:{h};--dsgo-fifty-fifty-content-justify:{align};--dsgo-fifty-fifty-content-padding:{pad}">
    <div class="dsgo-fifty-fifty__media">
      <img src="{url}" alt="{alt}" style="object-position:{focal}" loading="lazy" />
    </div>
    <div class="dsgo-fifty-fifty__content">
      <div class="dsgo-fifty-fifty__content-inner">
        {inner blocks}
      </div>
    </div>
  </div>
```

## Content Blocks

### designsetgo/card

Self-contained card with image, badge, title, subtitle, body, and CTA.

```text
Key attributes:
  layoutPreset ("minimal"|"overlay"|"horizontal"|"featured")
  title, subtitle, bodyText (string)
  badgeText (string), showBadge (boolean)
  visualStyle ("shadow"|"outlined"|"filled"|"flat")
  showImage (boolean), showCta (boolean)
  image: { id, url, alt }

Supports: backgroundColor, textColor, spacing, border
```

### designsetgo/icon

Inline SVG icon display.

```text
Key attributes:
  icon (string — icon name, e.g., "arrow-right", "check", "star", "envelope", "phone", "location")
  iconStyle ("filled"|"outlined")
  iconSize (number, default 48)
  strokeWidth (number)
  ariaLabel (string)

HTML structure:
  <div class="wp-block-designsetgo-icon dsgo-icon">
    <div class="dsgo-icon__wrapper dsgo-lazy-icon" data-icon-name="{icon}" data-icon-style="{style}" ...></div>
  </div>
```

### designsetgo/icon-button

Button with optional icon. Primary CTA element in patterns.

```text
Key attributes:
  text (string)
  url (string), linkTarget ("_self"|"_blank")
  icon (string — icon name), iconPosition ("start"|"end"|"none")
  iconSize (number), iconGap (string)
  hoverAnimation (string)

Supports: backgroundColor, textColor, spacing (padding), border

HTML structure (with icon, iconPosition "start"):
  <a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button" style="...;gap:8px;...;flex-direction:row;..." href="{url}" target="_self">
    <span class="dsgo-icon-button__icon dsgo-lazy-icon" style="display:flex;align-items:center;justify-content:center;width:20px;height:20px;flex-shrink:0" data-icon-name="{icon}" data-icon-size="20"></span>
    <span class="dsgo-icon-button__text">{text}</span>
  </a>

HTML structure (with icon, iconPosition "end"):
  Same as above but style includes flex-direction:row-reverse (icon span still first in markup)

HTML structure (iconPosition "none"):
  <a class="wp-block-designsetgo-icon-button dsgo-icon-button wp-block-button wp-block-button__link wp-element-button" style="...;gap:0;...;flex-direction:row;..." href="{url}" target="_self">
    <span class="dsgo-icon-button__text">{text}</span>
  </a>
  Note: No icon span. gap:0 instead of gap:8px.

Border classes:
  When border width/color is set, adds "has-border-color" to the class list.
  Style order: border-color, border-width, border-radius (border-color comes BEFORE border-width).

Common inline styles (always present):
  display:inline-flex;align-items:center;justify-content:center;width:auto
  background-color:var(--wp--preset--color--{bg});color:var(--wp--preset--color--{text})
  padding-top/right/bottom/left from spacing attributes
```

### designsetgo/icon-list

Vertical or horizontal list with icons per item.

```text
Key attributes:
  layout ("vertical"|"horizontal")
  iconSize (number), iconColor (string)
  gap (string), columns (number)

Children: designsetgo/icon-list-item
  Key attributes: icon (string), linkUrl (string)
  InnerBlocks: Yes (paragraph, heading, etc.)
```

### designsetgo/pill

Rounded inline text badge/tag.

```text
Key attributes:
  content (string)
  align (string)
  fontSize (string)

Supports: backgroundColor, textColor, spacing, border
```

### designsetgo/divider

Visual separator with multiple styles.

```text
Key attributes:
  dividerStyle ("solid"|"dashed"|"dotted"|"double"|"gradient"|"dots"|"wave"|"icon")
  width (number), thickness (number)
  iconName (string — for icon style)

Supports: color
```

### designsetgo/blobs

Animated organic blob shapes. Good for hero backgrounds.

```text
Key attributes:
  blobShape ("shape-1"|"shape-2"|...)
  blobAnimation ("morph-1"|"morph-2"|...)
  size (string, e.g., "400px")
  animationDuration (string), animationEasing (string)

InnerBlocks: Yes — content placed inside the blob
```

### designsetgo/advanced-heading

Multi-style heading with segments. Each segment can have different font/weight/color.

```text
Key attributes:
  level (number, 1-6)
  textAlign (string)

Children: designsetgo/heading-segment
  Key attributes: content (string, rich text HTML)
```

### designsetgo/breadcrumbs

Navigation breadcrumbs with Schema.org markup.

```text
Key attributes:
  showHome (boolean), homeText (string)
  separator ("chevron"|"slash"|"arrow"|"dot")
  showCurrent (boolean), hideOnHome (boolean)
```

### designsetgo/table-of-contents

Auto-generated TOC from page headings.

```text
Key attributes:
  includeH2-H6 (boolean per level)
  displayMode ("hierarchical"|"flat")
  listStyle ("ordered"|"unordered")
  showTitle (boolean), titleText (string)
  scrollSmooth (boolean)
```

## Interactive Blocks

### designsetgo/accordion

Collapsible FAQ/content sections.

```text
Key attributes:
  allowMultipleOpen (boolean)
  iconStyle ("chevron"|"plus-minus"|"caret"|"arrow"|"none")
  iconPosition ("left"|"right")
  borderBetween (boolean)

Children: designsetgo/accordion-item
  Key attributes: title (string), isOpen (boolean), uniqueId (string)
  InnerBlocks: Yes
```

### designsetgo/tabs

Tabbed content panels.

```text
Key attributes:
  uniqueId (string)
  orientation ("horizontal"|"vertical")
  activeTab (number)
  alignment ("start"|"center"|"end"|"stretch")
  tabStyle ("underline"|"boxed"|"pills"|"minimal")
  enableDeepLinking (boolean)

Children: designsetgo/tab
  Key attributes: title (string), icon (string), anchor (string)
  InnerBlocks: Yes
```

### designsetgo/slider

Multi-slide carousel with arrows and dots.

```text
Key attributes:
  slidesPerView (number), height (string)
  showArrows (boolean), showDots (boolean)
  effect ("slide"|"fade"|"coverflow")
  autoplay (boolean), autoplayInterval (number)
  loop (boolean), draggable (boolean)
  styleVariation (string)

Children: designsetgo/slide
  Key attributes: backgroundImage (object), overlayColor/Opacity
  InnerBlocks: Yes
```

### designsetgo/flip-card

Card that flips to reveal back content.

```text
Key attributes:
  flipTrigger ("hover"|"click")
  flipEffect ("horizontal"|"vertical"|"diagonal")
  flipDuration (string)

Children: designsetgo/flip-card-front, designsetgo/flip-card-back
  Both accept InnerBlocks
```

### designsetgo/image-accordion

Expandable image panels for portfolios.

```text
Key attributes:
  height (string), gap (string)
  expandedRatio (number)
  triggerType ("hover"|"click")
  enableOverlay (boolean), overlayColor/Opacity

Children: designsetgo/image-accordion-item
  InnerBlocks: Yes
```

### designsetgo/modal

Accessible modal dialog.

```text
Key attributes:
  modalId (string — unique identifier)
  width (string), maxWidth (string)
  animationType (string)
  overlayOpacity (number), overlayColor/Blur
  disableBodyScroll (boolean)

InnerBlocks: Yes

Trigger: designsetgo/modal-trigger
  Key attributes: targetModalId (string), text (string), icon (string)
```

### designsetgo/scroll-accordion

Scroll-triggered stacking accordion — items reveal progressively as the user scrolls down the page.

```text
Key attributes:
  alignItems (string, default "flex-start")

Supports: backgroundColor, textColor, gradient, typography (fontSize, lineHeight), spacing (margin, padding, blockGap), align (wide, full)

Children: designsetgo/scroll-accordion-item
  Key attributes: overlayColor (string)
  Supports: backgroundColor, textColor, gradient, backgroundImage, border, shadow, spacing (padding)
  InnerBlocks: Yes

HTML structure:
  <div class="wp-block-designsetgo-scroll-accordion dsgo-scroll-accordion">
    <div class="dsgo-scroll-accordion__items" style="display:flex;flex-direction:column;align-items:{align}">
      <div class="dsgo-scroll-accordion-item" style="--dsgo-overlay-color:{color};--dsgo-overlay-opacity:0.8">
        {inner blocks}
      </div>
    </div>
  </div>
```

### designsetgo/scroll-marquee

Horizontally scrolling image rows.

```text
Key attributes:
  rows (array — images and directions)
  scrollSpeed (number)
  imageHeight/Width (string)
  gap (string), borderRadius (string)
```

## Data/Stats Blocks

### designsetgo/counter-group

Animated number counters that count up on scroll.

```text
Key attributes:
  columns (number), animationDuration (number)
  animationDelay (number), animationEasing (string)

Children: designsetgo/counter
  Key attributes:
    uniqueId (string), endValue (number)
    prefix (string), suffix (string)
    label (string), decimals (number)
```

### designsetgo/progress-bar

Animated progress/skill bar.

```text
Key attributes:
  percentage (number)
  barColor (string), backgroundColor (string)
  height (string), borderRadius (string)
  showLabel (boolean), labelText (string)
  barStyle (string), animateOnScroll (boolean)
```

### designsetgo/countdown-timer

Countdown to a target date/time.

```text
Key attributes:
  targetDateTime (string), timezone (string)
  showDays/Hours/Minutes/Seconds (boolean)
  layout ("boxed"|"inline"|"compact")
  completionAction/Message (string)
```

### designsetgo/timeline

Chronological event display with scroll animations.

```text
Key attributes:
  orientation ("vertical"|"horizontal")
  layout ("alternating"|"right")
  markerStyle, connectorStyle (string)
  animateOnScroll (boolean)

Children: designsetgo/timeline-item
  Key attributes: date (string), title (string), icon (string)
  InnerBlocks: Yes
```

### designsetgo/comparison-table

Feature comparison grid for pricing pages.

```text
Key attributes:
  columns (array: name, link, featured)
  rows (array: label, tooltip, cells)
  alternatingRows (boolean)
  responsiveMode ("scroll"|"stack")
  showCtaButtons (boolean)
```

### designsetgo/map

Interactive map (OpenStreetMap or Google Maps).

```text
Key attributes:
  dsgoProvider ("openstreetmap"|"googlemaps")
  dsgoLatitude/Longitude (number)
  dsgoZoom (number), dsgoAddress (string)
  dsgoHeight (string)
```

## Form Blocks

### designsetgo/form-builder

Container for building forms with AJAX submission and spam protection.

```text
Key attributes:
  formId (string), submitButtonText (string)
  ajaxSubmit (boolean)
  successMessage/errorMessage (string)

Children (all require parent: designsetgo/form-builder):
  designsetgo/form-text-field      — fieldName, label, placeholder, required, validation
  designsetgo/form-email-field     — fieldName, label, placeholder, required
  designsetgo/form-phone-field     — fieldName, label, phoneFormat, required
  designsetgo/form-textarea-field  — fieldName, label, rows, maxLength, required
  designsetgo/form-number-field    — fieldName, label, min, max, step, required
  designsetgo/form-date-field      — fieldName, label, minDate, maxDate, required
  designsetgo/form-time-field      — fieldName, label, minTime, maxTime, required
  designsetgo/form-url-field       — fieldName, label, required
  designsetgo/form-select-field    — fieldName, label, options (array), required
  designsetgo/form-checkbox-field  — fieldName, label, checkedByDefault, required
  designsetgo/form-hidden-field    — fieldName, value
```

## Core WordPress Blocks

Frequently used in patterns alongside custom blocks:

| Block              | Key Attributes                                                 |
| ------------------ | -------------------------------------------------------------- |
| `core/heading`     | level (1-6), textAlign, content, fontSize, style.typography    |
| `core/paragraph`   | align, fontSize, style.typography, style.spacing               |
| `core/image`       | url, alt, sizeSlug, width, height, style.border                |
| `core/group`       | layout.type, style.spacing, style.border, backgroundColor      |
| `core/columns`     | Not commonly used — prefer `designsetgo/grid`                  |
| `core/spacer`      | height                                                         |
| `core/separator`   | Not commonly used — prefer `designsetgo/divider`               |
