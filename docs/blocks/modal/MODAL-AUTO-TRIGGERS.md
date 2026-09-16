# Modal Auto-Triggers & URL Hash Guide

**Version**: 1.1.0
**Last Updated**: 2025-11-19

## Overview

The Modal block now includes advanced triggering capabilities that allow modals to open automatically based on user behavior or URL parameters, providing enhanced user engagement and conversion opportunities.

## Features Implemented

### 1. URL Hash Triggering

Open modals directly from URL links by matching the hash to the modal ID.

**Usage:**
```
https://yoursite.com/page#dsgo-modal-123
```

**Settings:**
- **Allow Hash Trigger**: Enable/disable hash-based triggering (default: enabled)
- **Update URL on Open**: Optionally update browser URL when modal opens (default: disabled)

**Benefits:**
- Direct linking to modal content
- Shareable modal URLs
- Browser back button support
- SEO-friendly implementation

**Example Use Cases:**
- Link from emails to specific modal content
- Share signup forms directly
- Deep linking to promotional offers

---

### 2. Auto-Trigger System

Automatically open modals based on user behavior without manual interaction.

#### Trigger Types

##### **Page Load**
Opens modal after page loads with configurable delay.

**Settings:**
- **Delay (ms)**: 0-30,000ms (default: 0)
- **Frequency**: Every visit, Once per session, Once per user

**Best For:**
- Welcome messages
- Important announcements
- Cookie notices
- Promotional offers

**Example Configuration:**
```javascript
{
  autoTriggerType: 'pageLoad',
  autoTriggerDelay: 2000,        // 2 seconds after page load
  autoTriggerFrequency: 'session' // Once per browser session
}
```

---

##### **Exit Intent**
Detects when user is about to leave the page (mouse moves toward browser top).

**Settings:**
- **Sensitivity**: Low (100px), Medium (50px), High (20px)
- **Minimum Time (seconds)**: 0-300s (default: 5s)
- **Exclude Mobile**: Don't trigger on mobile devices (default: enabled)

**Best For:**
- Newsletter signups
- Abandoned cart recovery
- Special offers to retain visitors
- Feedback collection

**How It Works:**
- Monitors mouse movement near the top of the viewport
- Only triggers after minimum time on page
- Respects mobile device exclusion

**Example Configuration:**
```javascript
{
  autoTriggerType: 'exitIntent',
  exitIntentSensitivity: 'medium',
  exitIntentMinTime: 10,          // Must be on page 10+ seconds
  exitIntentExcludeMobile: true
}
```

---

##### **Scroll Depth**
Triggers when user scrolls to a specific percentage of the page.

**Settings:**
- **Scroll Depth (%)**: 0-100% (default: 50%)
- **Scroll Direction**: Down only, Up or Down

**Best For:**
- Content engagement prompts
- Related content suggestions
- Survey requests
- Subscription offers

**How It Works:**
- Calculates scroll percentage: `(scrollTop / totalScrollHeight) * 100`
- Triggers once when threshold is reached
- Can be restricted to downward scrolling only

**Example Configuration:**
```javascript
{
  autoTriggerType: 'scroll',
  scrollDepth: 75,               // 75% down the page
  scrollDirection: 'down'         // Only when scrolling down
}
```

---

##### **Time on Page**
Opens modal after user has been on page for specified duration.

**Settings:**
- **Time on Page (seconds)**: 0-300s (default: 30s)

**Best For:**
- Engaged user targeting
- Content-relevant offers
- Support chat prompts
- Feedback requests

**Example Configuration:**
```javascript
{
  autoTriggerType: 'time',
  timeOnPage: 45                 // After 45 seconds on page
}
```

---

### 3. Frequency Control

Manage how often auto-triggered modals appear to users.

**Options:**

1. **Every Visit** (`always`)
   - Shows modal on every page load
   - No tracking required
   - Best for critical notices

2. **Once per Session** (`session`)
   - Shows once per browser session
   - Uses sessionStorage
   - Resets when browser closes
   - Best for announcements

3. **Once per User** (`once`)
   - Shows once and remembers choice
   - Uses localStorage + cookie fallback
   - Configurable duration (1-365 days)
   - Best for newsletters, promotions

**Cookie Duration:**
- Default: 7 days
- Range: 1-365 days
- Only applies to "Once per User" frequency

**Storage Keys:**
```javascript
// Session storage
sessionStorage.setItem(`dsgo_modal_${modalId}_shown`, 'true');

// Local storage + cookie
localStorage.setItem(`dsgo_modal_${modalId}_shown`, 'true');
document.cookie = `dsgo_modal_${modalId}_shown=true;expires=${date};path=/`;
```

---

## Modal Block Variations

Pre-configured modal patterns for common use cases.

### 1. Newsletter Signup
- **Dimensions**: 500px width, auto height
- **Trigger**: Exit intent (medium sensitivity, 10s min time)
- **Frequency**: Once per user (30 days)
- **Animation**: Slide up
- **Content**: Heading, description, email input, CTA button

### 2. Video Player
- **Dimensions**: 800px width (16:9 optimized)
- **Overlay**: Dark (95% opacity)
- **Animation**: Zoom
- **Close Button**: Outside top-right
- **Content**: Video embed placeholder

### 3. Image Lightbox
- **Dimensions**: Auto-fit to image
- **Overlay**: Dark with blur
- **Animation**: Fade
- **Close Button**: Inside top-right, styled
- **Content**: Image + caption

### 4. Announcement / Promo
- **Dimensions**: 600px width
- **Trigger**: Page load (2s delay)
- **Frequency**: Once per session
- **Animation**: Zoom
- **Content**: Heading, promo text, CTA button

### 5. Cookie Notice
- **Dimensions**: 500px width
- **Trigger**: Page load (1s delay)
- **Frequency**: Once per user (365 days)
- **Behavior**: No backdrop/ESC close, no close button
- **Content**: Notice text, Learn More + Accept buttons

---

## Block Settings Location

All settings are organized in the Inspector Controls (right sidebar):

### **Behavior**
- Close on Backdrop Click
- Close on ESC Key
- Disable Body Scroll
- **Allow Hash Trigger** ⭐ NEW
- **Update URL on Open** ⭐ NEW

### **Auto Trigger** ⭐ NEW
- Trigger Type (None, Page Load, Exit Intent, Scroll, Time)
- Frequency (Every visit, Session, Once)
- Cookie Duration (if Once selected)
- **Type-specific settings:**
  - **Page Load**: Delay
  - **Exit Intent**: Sensitivity, Min Time, Exclude Mobile
  - **Scroll**: Depth %, Direction
  - **Time**: Seconds on page

---

## Implementation Details

### Data Attributes

All settings are saved as data attributes on the modal element:

```html
<div
  data-dsgo-modal="true"
  data-modal-id="dsgo-modal-123"
  data-allow-hash-trigger="true"
  data-update-url-on-open="false"
  data-auto-trigger-type="exitIntent"
  data-auto-trigger-frequency="once"
  data-cookie-duration="30"
  data-exit-intent-sensitivity="medium"
  data-exit-intent-min-time="10"
  data-exit-intent-exclude-mobile="true"
  ...
>
```

### JavaScript Architecture

**File**: `src/blocks/modal/view.js`

**Key Methods:**
- `setupAutoTrigger()` - Main auto-trigger coordinator
- `checkTriggerFrequency()` - Validates if modal should show
- `markAsShown()` - Records modal display
- `setupPageLoadTrigger()`
- `setupExitIntentTrigger()`
- `setupScrollTrigger()`
- `setupTimeOnPageTrigger()`
- `setupHashTrigger()`

**Event Cleanup:**
All event listeners and timeouts are properly cleaned up in `destroy()` method to prevent memory leaks.

---

## Browser Support

- ✅ Modern browsers (Chrome, Firefox, Safari, Edge)
- ✅ localStorage/sessionStorage with cookie fallback
- ✅ Mobile device detection
- ✅ Reduced motion support
- ✅ High contrast mode support

---

## Best Practices

### Exit Intent
- Set minimum time (10-30s) to avoid annoying new visitors
- Use medium sensitivity for best results
- Exclude mobile devices (exit intent doesn't work well on mobile)
- Frequency: "Once per user" to avoid repetition

### Page Load
- Add slight delay (1-3s) for better UX
- Use "Once per session" for announcements
- Keep content brief and valuable

### Scroll Depth
- 50-75% is optimal for engagement
- Use "down only" to avoid triggers while reading
- Great for related content suggestions

### Time on Page
- 30-60s shows genuine interest
- Longer times (2-3 min) for support offers
- Shorter times (10-15s) for simple CTAs

### Frequency
- **Critical notices**: Every visit
- **Announcements**: Once per session
- **Signups/Promotions**: Once per user
- Always provide easy close option
- Respect user choice

---

## Testing

**Editor Preview:**
Auto-triggers are **disabled in the editor** to prevent interference. A notice appears when auto-trigger is configured:

> "Auto-triggers are disabled in the editor. They will work on the frontend."

**Frontend Testing:**
1. Clear localStorage/sessionStorage
2. Clear cookies for site
3. Use incognito/private browsing
4. Test each trigger type individually
5. Verify frequency settings
6. Test on mobile devices
7. Check console for errors

**Dev Tools Testing:**
```javascript
// Check frequency tracking
localStorage.getItem('dsgo_modal_123_shown');
sessionStorage.getItem('dsgo_modal_123_shown');

// Clear tracking for testing
localStorage.removeItem('dsgo_modal_123_shown');
sessionStorage.removeItem('dsgo_modal_123_shown');
```

---

## Troubleshooting

### Modal not auto-triggering

1. **Check frequency tracking**: Clear localStorage/sessionStorage
2. **Verify data attributes**: Inspect modal element in browser DevTools
3. **Check console**: Look for JavaScript errors
4. **Build project**: Run `npm run build` after changes
5. **Incognito mode**: Test in private browsing to bypass storage

### Exit intent not working

- Only works on desktop (not mobile)
- Requires minimum time on page
- Mouse must move to top edge of viewport
- Check "Exclude Mobile" setting

### Hash trigger not working

- Ensure "Allow Hash Trigger" is enabled
- Hash must match modal ID exactly (case-sensitive)
- Check for JavaScript errors
- Verify modal ID is unique

---

## Future Enhancements (v1.2.0+)

- [ ] JavaScript API for programmatic control
- [ ] PHP filters for server-side customization
- [ ] Modal gallery/navigation
- [ ] Custom animation builder
- [ ] Analytics integration hooks
- [ ] A/B testing capabilities

---

## Related Documentation

- [Modal Block Overview](MODAL.md)
- [WordPress Block Development](https://developer.wordpress.org/block-editor/)
- [ARIA Dialog Pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/)

---

**Questions or Issues?**
File an issue on GitHub or consult the main plugin documentation.
