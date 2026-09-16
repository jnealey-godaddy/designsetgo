# Modal Trigger Block - User Guide

**Version**: 1.0.0
**Category**: Widgets
**Keywords**: modal, button, trigger, popup, open

## Overview

The **Modal Trigger Block** creates customizable buttons that open Modal blocks. Works seamlessly with the Modal block to provide accessible, user-friendly popup interactions.

**Key Features:**
- Automatically detects Modal blocks on page
- Three button styles: Fill, Outline, Link
- Optional icons with customizable position and size
- Full width or auto width options
- Inherits theme colors and typography

## Quick Start

1. Add a **Modal** block to your page first.
2. Set a unique **Modal ID** in the Modal block settings (e.g., `newsletter`).
3. Insert the **Modal Trigger** block where you want the button.
4. Select your target modal from the **Target Modal** dropdown.
5. Edit button text and style as needed.

## Settings & Configuration

### Trigger Settings
- **Target Modal**: Select which modal this button opens (dropdown auto-populates).
- **Button Style**: Fill (solid background), Outline (border only), or Link (text only).
- **Justification**: Left, Center, or Right — positions the button within its container.
- **Width**: Auto (fits content) or Full Width (stretches to container).

### Icon Settings
- **Icon**: Choose from 100+ built-in icons.
- **Icon Position**: Start (before text), End (after text), or None.
- **Icon Size**: 12-48px (default: 20px).
- **Icon Gap**: Spacing between icon and text (default: 8px).

### Styling (Sidebar Panels)
- **Colors**: Background and text color (Block > Settings panel).
- **Typography**: Font size, weight, line height, letter spacing.
- **Spacing**: Padding and margin.
- **Border**: Color, radius, style, width.

## Common Use Cases

### 1. Newsletter Signup
Use **Fill** style with an envelope icon at start. Set to Full Width for emphasis.

### 2. Video Gallery
Use **Outline** style with play icon. Set modal to open video content.

### 3. Call-to-Action
Use **Fill** style with arrow icon at end. Customize colors to match brand.

### 4. Terms & Conditions
Use **Link** style for inline text triggers like "Read our Privacy Policy".

## Best Practices

**DO:**
- Add Modal block before Modal Trigger block.
- Use descriptive button text ("Open Newsletter" not "Click Here").
- Use icons to reinforce action (envelope for signup, play for video).
- Test button visibility against page background.

**DON'T:**
- Forget to select a target modal (button won't work).
- Use too many trigger buttons for the same modal (one primary trigger is clearer).
- Make link-style buttons hard to see (ensure proper contrast).

## Accessibility

- **Semantic HTML**: Uses `<button>` element with `type="button"`.
- **Keyboard**: Fully accessible via Tab and Enter/Space keys.
- **Data Attribute**: Uses `data-dsgo-modal-trigger` for JavaScript binding.

## Troubleshooting

**Button doesn't open modal:**
- Verify Modal block has a Modal ID set.
- Ensure Target Modal matches the Modal ID exactly.
- Check that Modal block is on the same page.

**No modals in dropdown:**
- Add a Modal block to your page first.
- Set a Modal ID in the Modal block settings.
- Refresh the editor if recently added.
