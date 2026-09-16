# Modal Block - Developer API Reference

**Version**: 1.3.0
**Last Updated**: 2025-11-19
**Status**: Production Ready

## Overview

The Modal block provides both JavaScript and PHP APIs for developers to programmatically control modals and customize their behavior. This enables advanced integrations, custom triggers, analytics tracking, and theme-specific customizations.

## Table of Contents

- [JavaScript API](#javascript-api)
  - [Core Methods](#core-methods)
  - [Event System](#event-system)
  - [Advanced Usage](#advanced-usage)
- [PHP Filters & Actions](#php-filters--actions)
  - [Filters](#filters)
  - [Actions](#actions)
  - [PHP Examples](#php-examples)
- [Best Practices](#best-practices)
- [TypeScript Definitions](#typescript-definitions)

---

## JavaScript API

### Accessing the API

The modal API is globally available via `window.dsgoModal`:

```javascript
// Check if API is loaded
if (window.dsgoModal) {
  // API is ready
}

// Or use DOM ready
document.addEventListener('DOMContentLoaded', () => {
  window.dsgoModal.open('dsgo-modal-newsletter');
});
```

### Core Methods

#### `open(modalId, options)`

Opens a modal by ID.

**Parameters:**
- `modalId` (string, required): The ID of the modal to open
- `options` (object, optional):
  - `trigger` (HTMLElement): Element that triggered the modal (for focus restoration)

**Returns:** `boolean` - Success status

**Example:**
```javascript
// Simple usage
window.dsgoModal.open('dsgo-modal-contact');

// With trigger element
const button = document.querySelector('#contact-btn');
window.dsgoModal.open('dsgo-modal-contact', { trigger: button });
```

---

#### `close(modalId)`

Closes a specific modal or all modals.

**Parameters:**
- `modalId` (string, optional): The ID of the modal to close. If omitted, closes all open modals.

**Returns:** `boolean | number` - Success status or count of modals closed

**Example:**
```javascript
// Close specific modal
window.dsgoModal.close('dsgo-modal-contact');

// Close all open modals
window.dsgoModal.close();
```

---

#### `closeAll()`

Closes all currently open modals.

**Returns:** `number` - Number of modals that were closed

**Example:**
```javascript
const closedCount = window.dsgoModal.closeAll();
console.log(`Closed ${closedCount} modals`);
```

---

#### `isOpen(modalId)`

Checks if a modal is currently open.

**Parameters:**
- `modalId` (string, required): The ID of the modal to check

**Returns:** `boolean` - Whether the modal is open

**Example:**
```javascript
if (window.dsgoModal.isOpen('dsgo-modal-cart')) {
  console.log('Shopping cart modal is open');
}
```

---

#### `getInstance(modalId)`

Gets the modal instance object for direct access.

**Parameters:**
- `modalId` (string, required): The ID of the modal

**Returns:** `DSGModal | null` - Modal instance or null if not found

**Example:**
```javascript
const modal = window.dsgoModal.getInstance('dsgo-modal-promo');
if (modal) {
  console.log('Animation type:', modal.settings.animationType);
  console.log('Gallery group:', modal.settings.galleryGroupId);
}
```

---

#### `getAllModals()`

Gets all modal instances on the page.

**Returns:** `Array<Object>` - Array of modal info objects

**Example:**
```javascript
const modals = window.dsgoModal.getAllModals();
modals.forEach(modal => {
  console.log(`Modal ID: ${modal.id}, Open: ${modal.isOpen}`);
});
```

---

### Event System

#### `on(event, callback)`

Registers an event listener.

**Parameters:**
- `event` (string, required): Event name
  - `'modalOpen'`: Fired when modal opens
  - `'modalClose'`: Fired when modal closes
  - `'modalBeforeOpen'`: Fired before modal opens
  - `'modalBeforeClose'`: Fired before modal closes
- `callback` (function, required): Event handler function

**Returns:** `function` - Unsubscribe function

**Example:**
```javascript
// Register event listener
const unsubscribe = window.dsgoModal.on('modalOpen', (data) => {
  console.log('Modal opened:', data.modalId);
  console.log('Trigger element:', data.trigger);
});

// Later, unsubscribe
unsubscribe();
```

---

#### `off(event, callback)`

Removes an event listener.

**Parameters:**
- `event` (string, required): Event name
- `callback` (function, required): The exact function reference to remove

**Returns:** `boolean` - Success status

**Example:**
```javascript
function handleModalOpen(data) {
  console.log('Opened:', data.modalId);
}

// Register
window.dsgoModal.on('modalOpen', handleModalOpen);

// Unregister
window.dsgoModal.off('modalOpen', handleModalOpen);
```

---

### Event Data

All events receive a data object with:

**`modalBeforeOpen`:**
```javascript
{
  modalId: string,      // Modal ID
  element: HTMLElement, // Modal element
  trigger: HTMLElement  // Triggering element (may be null)
}
```

**`modalOpen`:**
```javascript
{
  modalId: string,      // Modal ID
  element: HTMLElement, // Modal element
  trigger: HTMLElement  // Triggering element (may be null)
}
```

**`modalBeforeClose`:**
```javascript
{
  modalId: string,      // Modal ID
  element: HTMLElement  // Modal element
}
```

**`modalClose`:**
```javascript
{
  modalId: string,      // Modal ID
  element: HTMLElement  // Modal element
}
```

---

### Advanced Usage

#### Form Validation Before Close

```javascript
let hasUnsavedChanges = false;

// Track form changes
document.querySelector('#contact-form').addEventListener('input', () => {
  hasUnsavedChanges = true;
});

// Prevent accidental close
window.dsgoModal.on('modalBeforeClose', (data) => {
  if (data.modalId === 'dsgo-modal-contact' && hasUnsavedChanges) {
    if (!confirm('You have unsaved changes. Close anyway?')) {
      // Prevent close by reopening
      setTimeout(() => {
        window.dsgoModal.open(data.modalId);
      }, 100);
    }
  }
});
```

#### Analytics Tracking

```javascript
window.dsgoModal.on('modalOpen', (data) => {
  // Google Analytics 4
  gtag('event', 'modal_open', {
    modal_id: data.modalId,
    modal_element: data.element.className
  });
});

window.dsgoModal.on('modalClose', (data) => {
  gtag('event', 'modal_close', {
    modal_id: data.modalId
  });
});
```

#### Custom Triggers

```javascript
// Open modal after AJAX success
fetch('/api/cart/add', { method: 'POST', body: data })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      window.dsgoModal.open('dsgo-modal-cart-success');
    }
  });

// Open modal based on scroll depth
let scrollTriggered = false;
window.addEventListener('scroll', () => {
  const scrollPercent = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;

  if (scrollPercent > 75 && !scrollTriggered) {
    window.dsgoModal.open('dsgo-modal-newsletter');
    scrollTriggered = true;
  }
});
```

#### Chaining Modals

```javascript
// Open second modal after first closes
window.dsgoModal.on('modalClose', (data) => {
  if (data.modalId === 'dsgo-modal-step1') {
    setTimeout(() => {
      window.dsgoModal.open('dsgo-modal-step2');
    }, 300);
  }
});
```

---

## PHP Filters & Actions

### Filters

#### `designsetgo/modal/attributes`

Modify modal attributes before rendering.

**Parameters:**
- `$attributes` (array): Modal block attributes
- `$block` (array): Complete block data

**Returns:** `array` - Modified attributes

**Example:**
```php
add_filter('designsetgo/modal/attributes', function($attributes, $block) {
    // Force all modals to have slow animation
    $attributes['animationDuration'] = 600;

    // Disable body scroll for specific modal
    if ($attributes['modalId'] === 'special-modal') {
        $attributes['disableBodyScroll'] = false;
    }

    return $attributes;
}, 10, 2);
```

---

#### `designsetgo/modal/classes`

Modify CSS classes applied to modal wrapper.

**Parameters:**
- `$classes` (array): Array of CSS class names
- `$attributes` (array): Modal block attributes
- `$block` (array): Complete block data

**Returns:** `array` - Modified classes

**Example:**
```php
add_filter('designsetgo/modal/classes', function($classes, $attributes, $block) {
    // Add custom class for analytics
    $classes[] = 'tracked-modal';

    // Add class based on modal type
    if (!empty($attributes['autoTriggerType']) && $attributes['autoTriggerType'] !== 'none') {
        $classes[] = 'modal-auto-triggered';
    }

    // Add class for gallery modals
    if (!empty($attributes['galleryGroupId'])) {
        $classes[] = 'modal-gallery-item';
    }

    return $classes;
}, 10, 3);
```

---

#### `designsetgo/modal/data_attributes`

Modify data attributes passed to JavaScript.

**Parameters:**
- `$data_attrs` (array): Associative array of data attributes
- `$attributes` (array): Modal block attributes
- `$block` (array): Complete block data

**Returns:** `array` - Modified data attributes

**Example:**
```php
add_filter('designsetgo/modal/data_attributes', function($data_attrs, $attributes, $block) {
    // Add custom tracking ID
    $data_attrs['tracking-id'] = get_option('site_tracking_id');

    // Add page type for analytics
    $data_attrs['page-type'] = is_singular('product') ? 'product' : 'page';

    // Override delay for logged-in users
    if (is_user_logged_in()) {
        $data_attrs['auto-trigger-delay'] = 0;
    }

    return $data_attrs;
}, 10, 3);
```

---

#### `designsetgo/modal/content`

Modify complete modal HTML output.

**Parameters:**
- `$block_content` (string): Current block HTML
- `$attributes` (array): Modal block attributes
- `$block` (array): Complete block data
- `$classes` (array): Filtered CSS classes
- `$data_attrs` (array): Filtered data attributes

**Returns:** `string` - Modified block content

**Example:**
```php
add_filter('designsetgo/modal/content', function($content, $attributes, $block, $classes, $data_attrs) {
    // Add custom wrapper for specific modal
    if ($attributes['modalId'] === 'premium-modal') {
        $content = '<div class="premium-modal-wrapper">' . $content . '</div>';
    }

    return $content;
}, 10, 5);
```

---

### Actions

#### `designsetgo/modal/before_render`

Fired before modal block renders.

**Parameters:**
- `$attributes` (array): Modal block attributes
- `$block` (array): Complete block data

**Example:**
```php
add_action('designsetgo/modal/before_render', function($attributes, $block) {
    // Log modal renders for debugging
    if (WP_DEBUG) {
        error_log('Rendering modal: ' . $attributes['modalId']);
    }

    // Enqueue additional scripts for specific modal
    if ($attributes['modalId'] === 'video-modal') {
        wp_enqueue_script('video-player');
    }
}, 10, 2);
```

---

#### `designsetgo/modal/after_render`

Fired after modal block renders.

**Parameters:**
- `$block_content` (string): Rendered block HTML
- `$attributes` (array): Modal block attributes
- `$block` (array): Complete block data

**Example:**
```php
add_action('designsetgo/modal/after_render', function($content, $attributes, $block) {
    // Track modal usage
    $modal_views = get_option('modal_views', array());
    $modal_id = $attributes['modalId'];
    $modal_views[$modal_id] = ($modal_views[$modal_id] ?? 0) + 1;
    update_option('modal_views', $modal_views);
}, 10, 3);
```

---

#### `designsetgo/modal/footer_scripts`

Fired in wp_footer for custom modal JavaScript.

**Example:**
```php
add_action('designsetgo/modal/footer_scripts', function() {
    ?>
    <script>
    // Custom modal behavior
    if (window.dsgoModal) {
        // Auto-open modal after 30 seconds of inactivity
        let inactivityTimer;
        document.addEventListener('mousemove', resetTimer);
        document.addEventListener('keypress', resetTimer);

        function resetTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                window.dsgoModal.open('dsgo-modal-inactive');
            }, 30000);
        }

        resetTimer();
    }
    </script>
    <?php
});
```

---

### PHP Examples

#### Disable Auto-Triggers for Logged-In Users

```php
add_filter('designsetgo/modal/attributes', function($attributes) {
    if (is_user_logged_in() && !empty($attributes['autoTriggerType'])) {
        $attributes['autoTriggerType'] = 'none';
    }
    return $attributes;
});
```

#### Add Custom Analytics Classes

```php
add_filter('designsetgo/modal/classes', function($classes, $attributes) {
    // Track modal category
    $trigger_type = $attributes['autoTriggerType'] ?? 'manual';
    $classes[] = 'trigger-' . sanitize_html_class($trigger_type);

    // Track animation type
    $animation = $attributes['animationType'] ?? 'fade';
    $classes[] = 'animation-' . sanitize_html_class($animation);

    return $classes;
}, 10, 2);
```

#### Conditional Modal Loading

```php
add_filter('designsetgo/modal/content', function($content, $attributes) {
    // Only show newsletter modal on blog pages
    if ($attributes['modalId'] === 'dsgo-modal-newsletter' && !is_singular('post')) {
        return '';  // Don't render
    }

    return $content;
}, 10, 2);
```

---

## Best Practices

### JavaScript

1. **Check API Availability**: Always check if `window.dsgoModal` exists before use
2. **Use Unsubscribe**: Store unsubscribe functions and clean up event listeners
3. **Avoid Memory Leaks**: Remove listeners when components unmount (React/Vue)
4. **Error Handling**: Wrap API calls in try-catch for production
5. **Throttle/Debounce**: Use throttling for scroll/resize-based triggers

### PHP

1. **Check Context**: Use conditionals (`is_admin()`, `is_singular()`) to apply filters appropriately
2. **Sanitize Data**: Always sanitize custom data attributes
3. **Performance**: Avoid heavy operations in frequently-fired actions
4. **Compatibility**: Test filters with other plugins and themes
5. **Documentation**: Comment custom implementations for future maintenance

---

## TypeScript Definitions

```typescript
interface DSGModalAPI {
  open(modalId: string, options?: { trigger?: HTMLElement }): boolean;
  close(modalId?: string): boolean | number;
  closeAll(): number;
  isOpen(modalId: string): boolean;
  getInstance(modalId: string): DSGModal | null;
  getAllModals(): ModalInfo[];
  on(event: ModalEvent, callback: (data: ModalEventData) => void): () => void;
  off(event: ModalEvent, callback: (data: ModalEventData) => void): boolean;
}

type ModalEvent = 'modalOpen' | 'modalClose' | 'modalBeforeOpen' | 'modalBeforeClose';

interface ModalEventData {
  modalId: string;
  element: HTMLElement;
  trigger?: HTMLElement;
}

interface ModalInfo {
  id: string;
  element: HTMLElement;
  instance: DSGModal;
  isOpen: boolean;
}

declare global {
  interface Window {
    dsgoModal: DSGModalAPI;
    dsgoModalAPI: DSGModalAPI; // Alias
  }
}
```

---

## Related Documentation

- [Modal Auto-Triggers](MODAL-AUTO-TRIGGERS.md)
- [Modal Gallery Navigation](MODAL-GALLERY-NAVIGATION.md)
- [Modal FSE Compatibility](MODAL-FSE-COMPATIBILITY.md)

---

**Need Help?** [Open an issue](https://github.com/jnealey-godaddy/designsetgo/issues).

**Last Updated**: November 2024
**Version**: 1.3.0
