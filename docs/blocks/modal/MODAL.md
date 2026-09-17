# Modal Block - User Guide

**Version**: 1.0.0
**Category**: Widgets
**Keywords**: modal, popup, dialog, overlay, lightbox

## Overview

The **Modal Block** allows you to create accessible, highly customizable modal dialogs (popups) directly within the WordPress editor. It supports various trigger types, including click, time delay, scroll depth, and exit intent, making it perfect for lead generation, announcements, and galleries.

## 🚀 Quick Start

### 1. Creating a Modal
1. Insert the **Modal** block into your page or post.
2. Add content inside the modal (Headings, Paragraphs, Forms, Images, etc.).
3. Set a unique **Modal ID** in the block settings (e.g., `my-modal`).

### 2. Triggering the Modal
**Method A: Using a Link**
1. Create a standard link or button.
2. Set the URL to `#` + your Modal ID (e.g., `#my-modal`).

**Method B: Auto-Trigger**
1. Select the Modal block.
2. In the settings sidebar, go to **Auto-Triggers**.
3. Select a **Trigger Type** (e.g., Time Delay) and configure the options.

## ⚙️ Settings & Configuration

### Settings
- **Modal ID**: Unique identifier (Required).
- **Allow Hash Trigger**: Enable opening via URL hash (e.g., `site.com/#modal-id`).
- **Update URL on Open**: Updates browser URL when opened.

### Auto-Triggers
- **Time Delay**: Open after X seconds.
- **Exit Intent**: Open when mouse leaves window (Desktop only).
- **Scroll Depth**: Open after scrolling X%.
- **Frequency**: Control how often it shows (Every Visit, Once per Session, Once per User).

### Appearance
- **Dimensions**: Width, Max Width, Height.
- **Animation**: Fade, Slide Up, Zoom, Flip, etc.
- **Overlay**: Color, Opacity, Blur.

### Close Options
- **Show Close Button**: Toggle X button.
- **Close on Backdrop Click**: Click outside to close.
- **Close on ESC Key**: Press Escape to close.

## 💡 Common Use Cases

### 1. Newsletter Signup
Use **Exit Intent** trigger to capture leads before they leave. Set frequency to "Once per User".

### 2. Video Showcase
Use **Zoom** animation and a dark overlay for a cinematic feel.

### 3. Image Lightbox
Use **Fade** animation and enable **Gallery Navigation** for multiple images.

### 4. Cookie Notice
Auto-trigger on page load. Disable "Close on Backdrop" to force interaction.

## ✅ Best Practices

**DO:**
- Use descriptive IDs (`newsletter-modal` not `modal-1`).
- Ensure close buttons are visible and accessible.
- Use frequency controls to avoid annoying users.

**DON'T:**
- Overuse auto-triggers (can frustrate users).
- Make modals difficult to close.

## ♿ Accessibility

*   **Keyboard Navigation**: `ESC` to close. Focus is trapped within modal while open.
*   **Screen Reader Support**: `aria-modal="true"`, `role="dialog"`.
*   **Focus Management**: Focus returns to trigger element on close.

## 👨‍💻 Developer Notes

For advanced usage, including JavaScript events and PHP filters, please refer to the [Modal API Reference](MODAL-API-REFERENCE.md).

### Quick JS Example
```javascript
// Open a modal programmatically
if (window.dsgoModal) {
    window.dsgoModal.open('my-modal');
}
```
