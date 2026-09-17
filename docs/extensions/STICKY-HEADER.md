# Sticky Header Extension - User Guide

**Compatible With**: Header template parts (Site Editor)
**Since**: 1.0.0

## Overview

The **Sticky Header Extension** makes header template parts stick to the top of the viewport when scrolling. Adds advanced behaviors like shrinking, auto-hiding, shadows, and background changes for polished, modern headers.

**Key Features:**
- Sticky positioning on scroll
- Optional shrink effect (reduces height when scrolled)
- Auto-hide on scroll down, reveal on scroll up
- Shadow depth control
- Background color on scroll
- Global settings in plugin options
- Works exclusively with header template parts

## 🚀 Quick Start

1. Open **Site Editor** (Appearance > Editor)
2. Edit your **Header** template part
3. Select the template part wrapper
4. Open **Sticky Header** panel in sidebar
5. Toggle **Enable Sticky Header**
6. Customize shadow, shrink, and hide behaviors

## ⚙️ Settings & Configuration

### Sticky Header Panel (Template Part)
- **Enable Sticky Header**: Makes header stick to top when scrolling
- **Shadow Size**: None, Small, Medium, Large (depth when scrolled)
- **Shrink on Scroll**: Reduces header height by percentage when scrolled
- **Shrink Amount**: 5% to 50% size reduction (default: 50%)
- **Hide on Scroll Down**: Auto-hide when scrolling down, reveal when scrolling up
- **Background on Scroll**: Apply global background color when scrolled (set in DesignSetGo Settings)

### Global Settings (DesignSetGo Settings)
- **Enable/Disable Globally**: Master switch for sticky headers
- **Z-Index**: Stacking order (default: 1000)
- **Transition Speed**: Animation duration for shrink/hide effects
- **Background Color**: Applied when "Background on Scroll" enabled

## 💡 Common Use Cases

### 1. E-commerce Sites
Sticky header keeps cart and search accessible while browsing products.

### 2. Long-Form Content
Auto-hide on scroll down maximizes reading space, reveals on scroll up for navigation.

### 3. Transparent Headers
Use "Background on Scroll" to add solid background when scrolling away from hero.

### 4. Compact Navigation
Shrink on scroll reduces header height, preserving space while maintaining access.

## ✅ Best Practices

**DO:**
- Use medium shadow for depth without overwhelming
- Test shrink amount (15-20% works well)
- Enable "Hide on Scroll Down" for long pages
- Add background on scroll if header is initially transparent
- Test on mobile (ensure touch targets stay 44px+)

**DON'T:**
- Use sticky on tall headers (>150px) without shrinking
- Combine auto-hide with very fast scrolling content (can feel glitchy)
- Set shrink amount > 30% (nav items become too small)
- Forget to test with mobile menus
- Use heavy shadows (distracting)

## 💡 Tips & Tricks

- **Transparent to Solid**: Start with transparent header, enable "Background on Scroll"
- **Performance**: Uses CSS `position: sticky` (GPU accelerated, no JS scroll listeners)
- **Footer Conflict**: If footer sticks, ensure it doesn't have sticky classes
- **Debugging**: Check DesignSetGo Settings if controls disabled (global toggle may be off)
- **Shadow Only**: Use shadow without shrink for subtle depth
- **Full Auto-Hide**: Combine hide on scroll with shrink for maximum space

## Technical Notes

- Only applies to template parts with `area="header"` or slug containing "header"
- Requires global setting enabled in DesignSetGo Settings
- Z-index controlled globally (avoid conflicts with modals, dropdowns)
- Works with FSE and classic themes using template parts
