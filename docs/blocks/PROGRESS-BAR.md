# Progress Bar Block - User Guide

**Version**: 1.0.0
**Category**: Widgets
**Keywords**: progress, bar, stat, percentage, meter

## Overview

The **Progress Bar** block displays progress or statistics with an animated bar that fills when scrolling into view. Perfect for showing completion rates, skill levels, project progress, or any percentage-based metric.

**Key Features:**
- Animated bar fill on scroll
- Customizable bar and background colors
- Label and percentage display options
- Three bar styles (solid, striped, animated stripes)
- Adjustable height and border radius
- Responsive design

## 🚀 Quick Start

### Creating a Progress Bar

1. **Add the Block**
   - Insert "Progress Bar" from Widgets category

2. **Set Progress**
   - Adjust percentage slider (0-100%)

3. **Configure Appearance**
   - Set bar color and background color
   - Adjust height and border radius
   - Choose bar style (solid, striped, animated)

4. **Add Labels**
   - Toggle label text on/off
   - Toggle percentage display on/off
   - Choose label position (above, inside, below)

5. **Customize Animation**
   - Enable/disable animate on scroll
   - Set animation duration
   - Enable stripe animation (if using striped style)

## ⚙️ Settings & Configuration

### Settings

- **Percentage**: 0-100% (default: 75%)
  - Sets the progress/completion level
  - Bar fills to this percentage

### Appearance

- **Bar Height**: Height of the progress bar
  - Units: px, em, rem
  - Default: 20px
  - Typical range: 10-40px

- **Border Radius**: Rounded corners
  - Units: px, em, %
  - Default: 4px
  - Use 50% for fully rounded ends

- **Bar Style**: Visual style of the fill bar
  - **Solid**: Plain color fill (default)
  - **Striped**: Diagonal stripe pattern
  - **Animated**: Moving diagonal stripes

### Label Settings

- **Show Label**: Toggle label text on/off
- **Label Text**: Custom text (e.g., "Project Progress")
- **Show Percentage**: Toggle percentage display on/off
- **Label Position**: Where to display label/percentage
  - **Above Bar**: Label displays above progress bar
  - **Inside Bar**: Label displays inside the filled area
  - **Below Bar**: Label displays below progress bar

**Display format**: When both enabled, shows "Label Text - 75%"

### Animation

- **Animate on Scroll**: Toggle scroll-triggered animation
  - When enabled: Bar animates when it enters viewport
  - When disabled: Bar shows final state immediately

- **Animation Duration**: 0.5-5 seconds (default: 1.5s)
  - How long the fill animation takes

- **Animate Stripes**: Toggle stripe movement (striped/animated styles only)
  - Creates moving stripe effect

### Color Settings

- **Bar Color**: Color of the filled progress bar
  - Default: Blue (#2563eb)
  - Use theme colors for consistency

- **Background Color**: Color of the unfilled area
  - Default: Light gray (#e5e7eb)
  - Should contrast with bar color

## 💡 Common Use Cases

### 1. Project Completion
Display overall project progress.
```
Label: "Project Completion" | Percentage: 85% | Style: Solid
```

### 2. Skill Levels
Show proficiency in different skills.
```
Label: "JavaScript" | Percentage: 90% | Style: Solid | Position: Inside
Label: "CSS" | Percentage: 85% | Style: Solid | Position: Inside
```

### 3. Goal Progress
Track progress toward a goal or target.
```
Label: "Fundraising Goal" | Percentage: 67% | Style: Striped | Position: Above
```

### 4. Survey Results
Display poll or survey data.
```
Label: "Approval Rating" | Percentage: 78% | Style: Solid | Position: Below
```

### 5. Loading/Processing Indicator
Visual indicator of system status (though typically use for static display).
```
Label: "Upload Progress" | Percentage: 45% | Style: Animated | Position: Above
```

## ✅ Best Practices

**DO:**
- Use descriptive labels that explain what the percentage represents
- Choose colors with good contrast (bar vs background)
- Keep height proportional to font size (taller for larger text)
- Use solid style for professional/corporate contexts
- Test label readability in "inside" position

**DON'T:**
- Use progress bars for non-percentage data
- Make bars too thin (hard to see) or too thick (overwhelming)
- Use "inside" position with small percentages (<30%)
- Overuse animated stripes (can be distracting)
- Forget to test on mobile devices

## ♿ Accessibility

- **Screen Readers**: Uses semantic HTML with accessible labels
- **Motion**: Respects `prefers-reduced-motion` (shows final state instantly)
- **Contrast**: Ensure sufficient color contrast for visibility
- **Text Size**: Labels scale with WordPress typography settings

## 📝 Tips

**Inside Labels - Visibility**:
- Works best with percentages >40%
- Use contrasting text color for readability
- Consider "above" or "below" for small percentages

**Multiple Progress Bars**:
- Stack multiple bars to compare metrics
- Use consistent styling across all bars
- Add spacing between bars with Spacer block

**Styling for Impact**:
- Rounded ends (50% border radius) for modern look
- Subtle animation (1-2s duration) for professional feel
- Use theme accent colors for brand consistency

**Combining with Counter Blocks**:
```
Counter: 850/1000 Projects
Progress Bar: 85% completion
```

## 🔗 Related Blocks

- [Counter Group](./COUNTER-GROUP.md) - For animated statistics

---

**Need Help?**
- [GitHub Issues](https://github.com/jnealey-godaddy/designsetgo/issues)
- [GitHub Discussions](https://github.com/jnealey-godaddy/designsetgo/discussions)

*Last Updated: 2025-11-19 | DesignSetGo v1.0.0 | WordPress 6.4+*
