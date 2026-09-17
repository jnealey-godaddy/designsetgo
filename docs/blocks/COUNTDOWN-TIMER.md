# Countdown Timer Block - User Guide

**Version**: 1.0.0
**Category**: Widgets
**Keywords**: countdown, timer, clock, deadline, event

## Overview

The **Countdown Timer Block** displays a live countdown to a specific date and time with customizable styling. Perfect for product launches, event announcements, sales deadlines, and time-sensitive promotions.

**Key Features:**
- Live countdown (updates every second)
- Timezone support for accurate global timing
- 3 layout options (boxed, inline, compact)
- Toggle individual units (days, hours, minutes, seconds)
- Customizable completion actions and messages
- Full styling controls (colors, borders, spacing)

## 🚀 Quick Start

1. Insert the **Countdown Timer** block.
2. Set your target date and time in the Date & Time panel.
3. Choose timezone (defaults to site timezone).
4. Select a layout style (Boxed recommended for visual impact).
5. Customize colors and appearance as needed.

## ⚙️ Settings & Configuration

### Date & Time
- **Target Date & Time**: Pick your deadline using date/time picker
- **Timezone**: Select timezone for accurate countdown (uses WordPress site timezone by default)

### Display
Toggle which units to show:
- **Show Days**: Display days remaining (recommended for long countdowns)
- **Show Hours**: Display hours (0-23 or total hours)
- **Show Minutes**: Display minutes (0-59)
- **Show Seconds**: Display seconds (0-59) - creates sense of urgency

**Layout Options:**
- **Boxed**: Units in individual boxes (most visual)
- **Inline**: Units in horizontal row (compact)
- **Compact**: Minimal spacing (space-saving)

### Styling
- **Number Color**: Color for countdown numbers
- **Label Color**: Color for unit labels (Days, Hours, etc.)
- **Unit Background**: Background color for each unit box
- **Unit Gap**: Spacing between units
- **Unit Padding**: Inner padding for each unit box

### Unit Border
- **Border Color**: Custom border color for unit boxes
- **Border Style**: Solid, dashed, dotted, double
- **Border Width**: Border thickness (px)
- **Border Radius**: Rounded corners (px)

### Completion
**Action on completion:**
- **Show Message**: Display custom message when countdown ends
- **Hide Timer**: Hide entire block when countdown ends

**Completion Message**: Customize text shown when timer reaches zero

## 💡 Common Use Cases

### 1. Product Launch
Set timer to launch date. Use **Boxed** layout with large numbers. Show all units. "Launching Soon!" completion message.

### 2. Flash Sale
Short-term sale countdown. Use **Inline** layout. Show hours, minutes, seconds only. "Sale Ended" completion message.

### 3. Event Registration
Countdown to event start. Use **Boxed** layout. Show days and hours. "Event Started!" completion message.

### 4. Limited Offer
Create urgency for special offers. Use **Compact** layout. Show minutes and seconds. Hide timer on completion.

## ✅ Best Practices

**DO:**
- Set timezone correctly for global audiences
- Test completion behavior before publishing
- Use appropriate units (days for long waits, seconds for urgency)
- Match colors to your brand/theme
- Place prominently near related CTA

**DON'T:**
- Use for dates in the past (shows zero/message immediately)
- Show seconds for countdowns over 7 days (creates false urgency)
- Forget to set a completion message
- Use tiny font sizes (readability matters)

## ♿ Accessibility

- **Semantic HTML**: Uses `<time>` elements for screen readers
- **ARIA Labels**: Unit labels announce properly
- **Color Independence**: Don't rely solely on color to convey urgency
- **Text Readability**: Ensure sufficient contrast for numbers and labels

## 💡 Tips & Tricks

- **Recurring Events**: Update target date after each event for reusable countdowns
- **Testing**: Set timer to 1 minute ahead to test completion behavior
- **Urgency Colors**: Use red/orange for final hours, blue/green for longer timeframes
- **Multiple Timers**: Add several for different events/offers on same page
- **Timezone Display**: Add text above timer to clarify timezone (e.g., "Launches Dec 25, 2025 at 12:00 PM EST")
