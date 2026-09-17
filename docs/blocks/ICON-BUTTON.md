# Icon Button Block - User Guide

**Version**: 1.0.0
**Category**: Design
**Keywords**: button, icon, link, cta, animated

## Overview

The **Icon Button Block** is an enhanced version of the standard button. It combines text with an icon to create clear, actionable, and visually appealing calls to action (CTAs). It also supports advanced hover animations that standard buttons lack.

## 🚀 Quick Start: Creating a "Download" Button

1.  **Insert Block**: Add the **Icon Button** block.
2.  **Content**:
    *   Text: "Download Guide"
    *   Icon: "Download" (Arrow pointing down)
    *   Position: "Start"
3.  **Style**:
    *   Background: Brand Blue.
    *   Text: White.
    *   Border Radius: `50px` (Pill shape).
4.  **Animation**:
    *   Set **Hover Animation** to `Lift`.
    *   Set **Hover Background** to a slightly darker Blue.

## ⚙️ Settings & Configuration

### Content & Link
- **Text**: Button label.
- **URL**: Destination link.
- **Target**: Open in New Tab.

### Icon Configuration
- **Position**: Start (left) or End (right).
- **Size**: Icon pixel size.
- **Gap**: Space between icon and text.

### Styling & Animation
- **Justification**: Left, Center, or Right — positions the button within its container.
- **Width**: Auto or Full width.
- **Hover Animation**:
    - **None**: Color change only.
    - **Slide Icon**: Icon moves on hover.
    - **Lift**: Button moves up.
    - **Fill Diagonal**: Background fill effect.

## 💡 Common Use Cases

### 1. Primary CTA
"Get Started" with arrow icon.

### 2. Download Button
"Download PDF" with download icon.

### 3. Contact Link
"Email Us" with envelope icon.

### 4. Social Share
"Share" with social logo.

## ✅ Best Practices

**DO:**
- Use action verbs.
- Ensure high contrast.
- Use "Lift" or "Zoom" for primary actions.

**DON'T:**
- Use vague text ("Click Here").
- Overuse animations.

## ♿ Accessibility

*   **Contrast**: 4.5:1 ratio.
*   **Touch Target**: Minimum 44x44px.
*   **Focus States**: Visible outlines for keyboard users.

## 👨‍💻 Developer Notes

*   **Flexbox**: Uses `display: flex` for alignment.
*   **CSS Transitions**: Animations handled via `transition` on `transform`.
