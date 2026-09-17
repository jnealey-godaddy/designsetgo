# Counter Group Block - User Guide

**Version**: 1.0.0
**Category**: Widgets
**Keywords**: counter, stats, statistics, numbers, animated

## Overview

The **Counter Group** block displays animated statistics that count up when they scroll into view. Perfect for showcasing metrics like customer counts, revenue figures, or completion rates in a responsive grid layout.

**Key Features:**
- Animated counting triggered on scroll
- Responsive multi-column grid (desktop, tablet, mobile)
- Global animation and number formatting controls
- Customizable hover effects
- Individual counter customization support

## 🚀 Quick Start

### Creating Your First Counter Group

1. **Add the Block**
   - Insert "Counter Group" from Widgets category
   - Block includes 3 default counters

2. **Configure Layout**
   - Set columns for desktop (1-6), tablet, and mobile
   - Adjust gap between counters
   - Choose content alignment (left, center, right)

3. **Customize Individual Counters**
   - Click each counter to set its end value
   - Add prefix (`$`) or suffix (`+`, `%`)
   - Add descriptive labels
   - Optional: Add icons

4. **Style & Animate**
   - Typography panel: Font size, line height
   - Color panel: Text, background, hover color
   - Animation panel: Duration, delay, easing

## ⚙️ Settings & Configuration

### Layout Settings

- **Columns (Desktop/Tablet/Mobile)**: Responsive column control
  - Desktop: 1-6 columns (default: 3) - applies >1024px
  - Tablet: 1-6 columns (default: 2) - applies 768-1023px
  - Mobile: 1-6 columns (default: 1) - applies <768px
- **Gap**: Spacing between counters (default: 32px)
- **Content Alignment**: Left, Center, or Right alignment

### Animation Settings

- **Duration**: 0.5-5 seconds (default: 2s) - how long counting takes
- **Delay**: 0-2 seconds (default: 0s) - pause before animation starts
- **Easing Function**:
  - Ease Out Quad (default) - natural, slows at end
  - Ease Out Cubic - dramatic slow-down
  - Ease In Out - smooth throughout
  - Linear - constant speed

**How it works**: Counters animate when 50% visible, plays once per page load.

### Number Formatting

- **Use Thousands Separator**: Toggle on/off (default: on)
  - Format: 1,000,000 vs 1000000
- **Thousands Separator**: Character for grouping (`,`, `.`, ` `, `'`)
  - US/UK: `,` → 1,000,000
  - European: `.` → 1.000.000
- **Decimal Point**: Character for decimals (`.`, `,`)
  - US/UK: `.` → 99.9
  - European: `,` → 99,9

### Color Settings

- **Number Hover Color**: Color when hovering over numbers
- **Text Color**: Default text color for all counters
- **Background Color**: Background for entire group

## 💡 Common Use Cases

### 1. Business Statistics
3-4 columns showing company metrics with icons and clear labels.
```
500+ Happy Customers | $1M+ Revenue | 99.9% Uptime
```

### 2. E-commerce Social Proof
Display trust indicators with impressive numbers.
```
10,000+ Products Sold | 5,000+ Reviews | 4.8/5 Rating
```

### 3. Service Reliability
Technical metrics that demonstrate platform quality.
```
99.99% Uptime | <50ms Response Time | 24/7 Support
```

### 4. Event Impact
Show event success and scale.
```
500+ Attendees | 50 Speakers | 20 Countries
```

## ✅ Best Practices

**DO:**
- Use 3-4 counters maximum (avoid overwhelming users)
- Keep numbers relevant and impressive
- Add descriptive labels for context
- Test responsive layouts on all devices
- Use consistent formatting across counters

**DON'T:**
- Display more than 6 counters (creates clutter)
- Use arbitrary or unimpressive numbers
- Mix formatting styles within a group
- Forget to test mobile experience
- Use misleading statistics

## ♿ Accessibility

- **Screen Readers**: Semantic HTML with descriptive labels
- **Motion Sensitivity**: Respects `prefers-reduced-motion` (shows final values instantly)
- **Readability**: Ensure sufficient color contrast for numbers and labels

## 📝 Tips

**Large Numbers**: Use K/M suffixes for readability
- Instead of 1,000,000+ → use 1M+

**Staggered Animations**: Override individual counter delays
- Counter 1: 0s, Counter 2: 0.3s, Counter 3: 0.6s

**Combining Blocks**:
- Add Heading above for section title
- Add Button below for call-to-action

## 🔗 Related Blocks

- [Progress Bar](./PROGRESS-BAR.md) - Alternative for single progress metrics

---

**Need Help?**
- [GitHub Issues](https://github.com/jnealey-godaddy/designsetgo/issues)
- [GitHub Discussions](https://github.com/jnealey-godaddy/designsetgo/discussions)

*Last Updated: 2025-11-19 | DesignSetGo v1.0.0 | WordPress 6.4+*
