# Slider Block

A modern, performant slider/carousel block with multiple transition effects, auto-play, and full WordPress block support inside slides.

## Features

### Parent Block (Slider)
- **Multiple Transition Effects**: Slide, Fade, Zoom
- **Multi-slide View**: Show 1-6 slides at once (responsive)
- **Auto-play**: Configurable interval with pause on hover/interaction
- **Navigation**: Customizable arrows and dots with multiple styles
- **Style Variations**: Classic, Minimal, Card, Full-bleed
- **Loop Mode**: Infinite navigation
- **Touch Support**: Swipe gestures for mobile devices
- **Mouse Drag**: Draggable slides on desktop
- **Keyboard Navigation**: Arrow keys, Home, End support
- **Accessibility**: ARIA attributes, screen reader support, reduced motion respect
- **Responsive**: Separate settings for desktop/tablet/mobile

### Child Block (Slide)
- **Background Images**: Full media library integration
- **Background Controls**: Size, position, repeat, parallax effect
- **Overlay**: Customizable color, opacity, gradient options
- **Content Alignment**: Vertical and horizontal positioning
- **Full Block Support**: Add any WordPress blocks inside slides
- **Flexible Layout**: Like Flex/Grid containers

## Block Variations

Pre-configured slider variations for common use cases:

1. **Hero Slider**: Full-height with fade transitions, perfect for hero sections
2. **Gallery Carousel**: 3 slides per view, ideal for image galleries
3. **Testimonial Slider**: Centered, fade effect, auto-play for testimonials
4. **Product Showcase**: 3-column layout for product displays
5. **Logo Slider**: Multi-slide continuous scrolling for logos/partners

## Usage

### Basic Setup
1. Insert the Slider block (or choose a variation)
2. Add Slide blocks inside
3. Configure slider settings in Inspector
4. Add background images to slides
5. Add any content blocks inside each slide

### Inspector Controls

#### Slider Settings
- **Layout**: Slides per view, height/aspect ratio, gap
- **Navigation**: Arrow and dot styles/positions
- **Transitions**: Effect type, duration, easing
- **Auto-play**: Enable, interval, pause behaviors
- **Behavior**: Loop, draggable, swipeable, free mode
- **Style Variation**: Choose preset design
- **Advanced**: Breakpoints, ARIA labels

#### Slide Settings
- **Background Image**: Select, replace, remove images
  - Background size (cover/contain/auto)
  - Background position (9 options)
  - Background attachment (scroll/fixed for parallax)
- **Content Alignment**: Vertical and horizontal positioning
- **Overlay**: Color, opacity, gradient direction
- **Spacing**: Content padding, min height override

## Keyboard Shortcuts

- `←` / `→`: Previous/Next slide
- `Home`: Jump to first slide
- `End`: Jump to last slide
- `Tab`: Navigate through slides

## Performance

- **Bundle Size**: ~15KB total (both blocks)
- **IntersectionObserver**: Only animates when visible
- **Conditional Loading**: Features only load when enabled
- **Optimized Transitions**: GPU-accelerated CSS transforms
- **Lazy Loading**: Background images load as needed

## Accessibility

- ✅ WCAG 2.1 AA compliant
- ✅ ARIA attributes (`role`, `aria-label`, `aria-hidden`, `aria-current`)
- ✅ Keyboard navigation
- ✅ Screen reader announcements on user-driven navigation
- ✅ Focus management — off-screen slides are `inert`, so their links leave the
  tab order rather than sitting focusable inside an `aria-hidden` subtree
- ✅ `prefers-reduced-motion` support, re-evaluated when the setting changes
- ✅ Autoplay suspends off screen, in a background tab, and while the slider is
  hovered or focused
- ✅ Color contrast (customizable)
- ✅ Touch targets (44x44px minimum)

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari 14+, Chrome Android 90+)

## Code Architecture

### Files
```
slider/
├── block.json          # Block metadata and attributes
├── index.js            # Registration and variations
├── edit.js             # Editor component (420 lines)
├── save.js             # Frontend markup (96 lines)
├── style.scss          # Base styles
├── editor.scss         # Editor-specific styles
├── render.php          # Server render — authored passthrough + query mode
├── view.js             # Frontend runtime: navigation, ARIA, chrome state
└── view/
    ├── config.js       # data-* parsing, responsive slidesPerView, durations
    ├── chrome.js       # Arrow / dot / announcer construction
    ├── gestures.js     # Swipe and drag
    ├── autoplay.js     # Timer plus its suspend reasons
    └── scroll-driven.js # Pinned horizontal scroll mode

slide/
├── block.json          # Block metadata and attributes
├── index.js            # Registration
├── edit.js             # Editor component (300 lines)
├── save.js             # Frontend markup (92 lines)
├── style.scss          # Base styles (204 lines)
└── editor.scss         # Editor-specific styles (87 lines)
```

### Key Patterns
- **Declarative Styles**: CSS custom properties via inline styles
- **useInnerBlocksProps**: Proper inner blocks handling
- **Context Sharing**: Parent provides, child consumes
- **Data Attributes**: Configuration passed to JavaScript
- **Class-based JS**: DSGSlider owns navigation state; `view/` modules own the
  parts that do not need it
- **Loop Carousel**: inside a `designsetgo/query` the track doubles as the
  query's item container, so its children can change after init. `refresh()`
  rebuilds clones, dots and cached dimensions in response to
  `dsgo-query-items-appended`; see
  [the Query block guide](../../../.claude/docs/QUERY-BLOCK-GUIDE.md)

## Future Enhancements

- [ ] Thumbnail navigation
- [ ] Progress bar option
- [ ] Slide counter display
- [ ] Video slide support
- [ ] Ken Burns effect
- [ ] Custom animation easings
- [ ] Sync multiple sliders
- [ ] Export/import configurations

## Related Blocks

- **Flex Container**: For flexible slide layouts
- **Grid Container**: For grid-based slide content
- **Image Accordion**: Similar parent-child pattern
- **Tabs**: Alternative content organization

## Credits

Built following DesignSetGo plugin patterns and WordPress best practices.
