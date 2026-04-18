# Flip Card Block - User Guide

**Version**: 1.0.0
**Category**: Design
**Keywords**: flip, card, interactive, hover, 3d, rotate

## Overview

The **Flip Card Block** adds a layer of interactivity and surprise to your website. By hiding content on the back of a card, you encourage users to engage with the element to reveal more information. It's a fun, space-saving way to present "Before & After" content, quizzes, or detailed profiles.

## 🚀 Quick Start: Building a Team Member Card

1.  **Insert Block**: Add the **Flip Card** block.
2.  **Edit Front**:
    *   Select the first **Flip Card Face** (Side = Front). Add an **Image** block (Team Photo).
    *   Add a **Heading** (Name) and **Paragraph** (Role).
    *   *Style*: Set a white background and add a subtle shadow.
3.  **Edit Back**:
    *   Select the second **Flip Card Face** (Side = back — already set by the template). Add a **Paragraph** (Bio).
    *   Add an **Icon Group** or **Buttons** for social links (LinkedIn, Twitter).
    *   *Style*: Set a colored background (e.g., Brand Blue) and white text to make it distinct from the front.
4.  **Settings**:
    *   Set **Trigger** to `Hover`.
    *   Set **Effect** to `Flip`.

## ⚙️ Settings & Configuration

### Flip Trigger
- **Hover** (Default): Best for desktop.
- **Click**: Essential for mobile/touch devices.

### Flip Effect
- **Flip (3D)**: Realistic rotation.
- **Fade**: Smooth opacity transition.
- **Slide**: Content slides in.
- **Zoom**: Scale animation.

### Flip Direction
- **Horizontal**: Like a book page.
- **Vertical**: Like a calendar.

### Flip Duration
- Range: 0.1s - 3s.
- Recommendation: 0.6s for natural feel.

## 💡 Common Use Cases

### 1. Team Profiles
Front shows photo/name; Back reveals bio/socials.

### 2. Product Details
Front shows image; Back lists specs and "Buy Now".

### 3. Q&A / Flashcards
Front asks question; Back shows answer.

### 4. Before/After Showcases
Front shows "Before" state; Back shows "After".

## 🎨 Styling & Customization

*   **Match Styles**: Ensure padding and border radius match on both front and back.
*   **Visual Hierarchy**: Use larger headings on front, detailed text on back.
*   **Mobile Optimization**: Test font sizes on small screens.

## ✅ Best Practices

**DO:**
- Keep content concise on both sides.
- Provide visual cues for interaction (shadow, cursor).
- Use "Click" trigger for mobile-heavy sites.

**DON'T:**
- Hide critical info only on the back.
- Mix different triggers on the same page.

## ♿ Accessibility

*   **Keyboard Navigation**: Click-triggered cards are keyboard accessible (Enter/Space).
*   **Screen Readers**: Both front and back content are announced.
*   **Motion Sensitivity**: Respects `prefers-reduced-motion`.

## 👨‍💻 Developer Notes

*   **DOM Structure**:
    ```html
    <div class="wp-block-designsetgo-flip-card dsgo-flip-card">
      <div class="dsgo-flip-card__container">
        <div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__front">...</div>
        <div class="wp-block-designsetgo-flip-card-face dsgo-flip-card__face dsgo-flip-card__back">...</div>
      </div>
    </div>
    ```
*   **CSS Transforms**: Uses `transform: rotateY(180deg)`.
*   **Backface Visibility**: `backface-visibility: hidden` ensures clean rotation.

## ❓ FAQ

**Q: Can I add videos?**
A: Yes, but be careful with autoplay.

**Q: How do I make cards equal height?**
A: Set a minimum height on both Front and Back blocks.
