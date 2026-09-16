# Accordion Block - User Guide

**Version**: 1.0.0
**Category**: Design
**Keywords**: accordion, faq, collapsible, toggle, expand

## Overview

The **Accordion Block** is a versatile layout tool that allows you to organize content into vertically stacked, collapsible sections. It is an essential component for reducing page clutter and improving user experience by allowing visitors to toggle the visibility of content.

Commonly used for FAQs, product features, and complex data presentation, the Accordion block is fully accessible and highly customizable.

## 🚀 Quick Start: Building a Simple FAQ

1.  **Add the Block**: Insert the **Accordion** block from the block inserter. It will appear with two default items.
2.  **Edit Content**:
    *   Click on the first "Accordion Title" and type your question (e.g., "What is your return policy?").
    *   Click inside the body area and add a Paragraph block with the answer.
3.  **Add More Items**:
    *   Select the main Accordion block.
    *   Click the **+** (Appender) button at the bottom to add a new Accordion Item.
4.  **Customize**:
    *   Select the parent Accordion block.
    *   In the Sidebar Settings, enable **Allow Multiple Open** if you want users to read multiple answers at once.
    *   Set the **Icon Style** to "Plus/Minus" for a modern look.

## ⚙️ Settings & Configuration

### Settings

**Allow Multiple Open**
- **Default**: Off (only one panel open at a time)
- **When Enabled**: Users can open multiple panels simultaneously
- **When Disabled**: Opening a new panel automatically closes the previous one

**Tip**: Use the "Open by Default" toggle on individual accordion items to control which panels are open when the page loads.

### Icon Settings

**Icon Style**
Choose from 4 visual styles:
1. **Chevron** (Default): Down-pointing arrow.
2. **Plus/Minus**: Plus (+) when closed, minus (-) when open.
3. **Caret**: Small triangular arrow.
4. **None**: No icon.

**Icon Position**
- **Left**: Icon appears before the title.
- **Right**: Icon appears after the title.

### Style Settings

**Border Between Items**
- **Enabled** (Default): Items have dividing borders with no gap.
- **Disabled**: Items are separated with spacing (gap).

**Gap Between Items**
- Only available when "Border Between Items" is disabled.
- Controls spacing between accordion items.

### Color Settings
Customize the interactive states to match your brand:
- **Border Color**: Color of the separator lines.
- **Open State**: Background and Text color for the active item's header.
- **Hover State**: Background and Text color on hover.

## 💡 Common Use Cases

### 1. FAQ Section
Group common questions and answers. Keep "Allow Multiple Open" disabled to help users focus on one answer at a time.

### 2. Product Features
Showcase features in a compact list. Enable "Allow Multiple Open" so users can compare features side-by-side.

### 3. Course Curriculums
Outline course modules. Users can expand each module to see the lessons inside.

### 4. Process Steps
Break down complex processes into manageable steps.

## 🎨 Styling & Customization

*   **High Contrast**: Use the **Open Background Color** to make the active section pop.
*   **Minimalist Look**: Disable **Border Between Items** and set **Item Gap** to `0` for a seamless list.
*   **Nested Content**: You can place *any* block inside an accordion body—images, columns, or even other accordions (though not recommended for UX).

## ✅ Best Practices

**DO:**
- Use clear, descriptive titles that preview content.
- Set important items to "Open by Default".
- Test on mobile devices (accordions save vertical space).

**DON'T:**
- Hide critical information in closed accordions.
- Create too many nested accordions (confusing).
- Use low contrast colors.

## ♿ Accessibility

The Accordion block is built with accessibility as a core principle (WCAG 2.1 AA).

*   **Keyboard Navigation**:
    *   `Tab`: Navigate between accordion item headers.
    *   `Enter` or `Space`: Open/close focused item.
*   **Screen Reader Support**:
    *   Proper ARIA attributes (`aria-expanded`, `aria-controls`).
    *   Unique IDs for each item ensure proper relationships.
*   **Focus Management**: Focus is properly managed when items are expanded or collapsed.

## 👨‍💻 Developer Notes

*   **Context Passing**: The parent `designsetgo/accordion` block passes settings (like `iconStyle` and `allowMultipleOpen`) to child `designsetgo/accordion-item` blocks via WordPress Context.
*   **DOM Structure**:
    ```html
    <div class="wp-block-designsetgo-accordion">
      <div class="wp-block-designsetgo-accordion-item">
        <button class="designsetgo-accordion-trigger">Title</button>
        <div class="designsetgo-accordion-panel">Content</div>
      </div>
    </div>
    ```
*   **CSS Classes**:
    *   `.is-open`: Applied to the active accordion item.
    *   `.has-icon-right` / `.has-icon-left`: Modifiers for icon position.

## ❓ FAQ

**Q: Can I nest accordions inside accordion items?**
A: Technically yes, but it's not recommended for UX reasons.

**Q: Can I link directly to an open accordion item?**
A: Not built-in, but possible with custom JavaScript.

**Q: Do accordions work with page caching?**
A: Yes! Accordions work entirely with HTML/CSS/JS and are compatible with caching.
