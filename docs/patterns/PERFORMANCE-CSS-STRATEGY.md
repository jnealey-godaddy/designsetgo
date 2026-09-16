# CSS Loading Performance Strategy

**Status**: Implemented (Hybrid Approach / Option 3 below). See `includes/core/class-assets.php` — `optimize_css_loading()` (defer non-critical CSS), `inline_critical_css()` and `dequeue_inlined_css()` (inline + dequeue critical CSS), both gated by `get_present_critical_blocks()`.

## Overview

Optimize CSS loading to reduce render-blocking resources by ~160ms based on PageSpeed Insights analysis.

## Current State

- **Individual block CSS**: Loaded automatically via `block.json` (WordPress default)
- **Global frontend CSS**: `build/style-index.css` (85.3 KiB)
- **Conditional loading**: ✅ Already implemented (loads only when blocks detected)
- **Dashicons**: ✅ Already conditionally loaded for tabs/accordion only

## Problem

WordPress loads all block CSS files synchronously via `block.json`, resulting in:
- 20+ individual CSS files (1-12 KiB each)
- All loaded upfront regardless of viewport placement
- **160ms render-blocking time** per PageSpeed Insights

## Block Categorization

### Critical Blocks (Inline CSS - Above-the-Fold)

The blocks actually inlined + dequeued by `get_present_critical_blocks()` in `includes/core/class-assets.php`:

1. **Grid** - Common layout block
2. **Row** - Common layout block
3. **Icon** - Common decorative element
4. **Pill** - Common UI element

Only whichever of these are present on the current request (via `has_block()`) are inlined — the set is per-request, not a fixed bundle. Section, Card, and Icon Button are **not** in the critical set; their CSS loads via the normal `block.json`-enqueued stylesheet (neither inlined nor deferred).

**Rationale**: These blocks are most likely to be above-the-fold and are frequently used. Inlining their CSS eliminates render-blocking for critical content.

### Non-Critical Blocks (Defer Loading - Below-the-Fold)
**Total: ~80+ KiB**

**Interactive Blocks**:
- Tabs (12K) - Usually below-fold
- Slider (8.4K) - Can be above/below
- Accordion (3.2K + 5.2K item)
- Image-accordion (1.1K + 3K item)
- Flip-card (5.9K)

**Scroll Effects**:
- Scroll-accordion (0.9K + 2.6K item)
- Scroll-marquee (1.9K)

**Specialty Blocks**:
- Countdown-timer (3.9K)
- Counter (2.8K)
- Progress-bar (1.8K)
- Map (3.6K)
- Blobs (4.9K)
- Divider (5.3K)

**Form Blocks**: All form fields (usually below-fold in long pages)

**Rationale**: These blocks are typically below-the-fold or require user interaction. Deferring their CSS reduces initial render blocking.

## Implementation Strategy

### Option 1: WordPress Native Approach (Recommended)
Use WordPress's built-in `style_loader_tag` filter to modify CSS loading:

```php
// Defer non-critical block CSS
add_filter( 'style_loader_tag', array( $this, 'defer_noncritical_css' ), 10, 4 );

public function defer_noncritical_css( $html, $handle, $href, $media ) {
    // Only modify our block styles
    if ( strpos( $handle, 'designsetgo-' ) === false ) {
        return $html;
    }

    // List of non-critical blocks
    $noncritical_blocks = array(
        'tabs', 'slider', 'accordion', 'accordion-item',
        'scroll-accordion', 'scroll-marquee', 'image-accordion',
        'flip-card', 'countdown-timer', 'counter', 'progress-bar',
        'map', 'blobs', 'divider', 'form-'
    );

    // Check if this is a non-critical block
    foreach ( $noncritical_blocks as $block ) {
        if ( strpos( $handle, $block ) !== false ) {
            // Use media="print" trick for defer
            // Note: WordPress uses double quotes by default
            return str_replace(
                'media="all"',
                'media="print" onload="this.media=\'all\'; this.onload=null;"',
                $html
            );
        }
    }

    return $html;
}
```

### Option 2: Critical CSS Inlining
Extract and inline critical CSS in `<head>`:

```php
public function inline_critical_css() {
    $critical_blocks = array( 'grid', 'row', 'icon', 'pill' );

    $critical_css = '';
    foreach ( $critical_blocks as $block ) {
        $css_file = DESIGNSETGO_PATH . "build/blocks/{$block}/style-index.css";
        if ( file_exists( $css_file ) ) {
            $critical_css .= file_get_contents( $css_file );
        }
    }

    if ( ! empty( $critical_css ) ) {
        echo '<style id="designsetgo-critical-css">' . $critical_css . '</style>';
    }
}
add_action( 'wp_head', array( $this, 'inline_critical_css' ), 1 );
```

**⚠️ IMPORTANT: Prevent Duplicate CSS Loading**

When inlining critical CSS, you MUST dequeue the original block stylesheets to avoid loading the same CSS twice:

```php
/**
 * Dequeue block stylesheets for critical blocks whose CSS is inlined.
 *
 * Prevents duplicate CSS loading - WordPress enqueues block CSS via block.json,
 * but we're inlining critical CSS. This method dequeues the original stylesheets.
 *
 * Runs at priority 20 to ensure it runs after block styles are enqueued.
 */
public function dequeue_inlined_css() {
    // Only on frontend and when our blocks are present
    if ( is_admin() || ! $this->has_designsetgo_blocks() ) {
        return;
    }

    // Critical blocks whose CSS is inlined (must match inline_critical_css)
    $critical_blocks = array( 'grid', 'row', 'icon', 'pill' );

    foreach ( $critical_blocks as $block ) {
        // Dequeue the block's style-index.css that WordPress automatically enqueues
        // Handle names match WordPress's automatic style handle generation
        wp_dequeue_style( "designsetgo-{$block}-style" );
        wp_deregister_style( "designsetgo-{$block}-style" );
    }
}
add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_inlined_css' ), 20 );
```

### Option 3: Hybrid Approach (Best)
Combine both strategies:
1. **Inline critical CSS** for above-the-fold blocks (Grid, Row, Icon, Pill)
2. **Dequeue original stylesheets** for inlined blocks to prevent duplicate CSS
3. **Defer non-critical CSS** using media attribute trick
4. **Keep conditional loading** for unused blocks

## Tradeoffs

### Inlining Critical CSS
**Pros**:
- Eliminates render-blocking for critical content
- Faster First Contentful Paint (FCP)
- No additional HTTP requests

**Cons**:
- Increases HTML size by ~40KB
- CSS not cached separately
- More bytes on initial page load

**Verdict**: Worth it for critical blocks. The FCP improvement outweighs the larger HTML.

### Deferring Non-Critical CSS
**Pros**:
- Non-blocking load
- Better page performance metrics
- CSS is still cached

**Cons**:
- Potential FOUC (flash of unstyled content) for below-fold blocks
- Requires JavaScript fallback

**Verdict**: Safe for below-the-fold content. Users scroll to it after initial paint.

## Implementation Steps

1. Add `style_loader_tag` filter to defer non-critical blocks
2. Add critical CSS inlining for top blocks
3. Add dequeue method to prevent duplicate CSS loading (priority 20)
4. Test with PageSpeed Insights
5. Verify no FOUC issues
6. Verify no duplicate CSS in page source
7. Test on slow 3G connection
8. A/B test performance impact

## Expected Impact

- **Before**: 160ms render-blocking CSS
- **After**: <50ms render-blocking CSS
- **Improvement**: 110-160ms faster LCP/FCP

## Testing Checklist

- [ ] No FOUC (flash of unstyled content)
- [ ] No duplicate CSS in page source (check for both inlined and linked styles)
- [ ] Critical blocks render immediately
- [ ] Non-critical blocks load without visual issues
- [ ] Noscript fallback works for deferred CSS
- [ ] Caching works correctly
- [ ] Works with FSE themes
- [ ] Works on slow connections

## Resources

- [WordPress Developer Resources - style_loader_tag](https://developer.wordpress.org/reference/hooks/style_loader_tag/)
- [Web.dev - Defer non-critical CSS](https://web.dev/defer-non-critical-css/)
- [PageSpeed Insights](https://pagespeed.web.dev/)
